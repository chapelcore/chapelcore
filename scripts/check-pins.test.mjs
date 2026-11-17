// check-pins.test.mjs — unit tests for scripts/check-pins.mjs.
// Why: the pin checker enforces a CLAUDE.md rule (every dependency and GitHub
// Action pinned to an exact, stable version). A checker that silently passes
// would be worse than none, so it is tested itself.
// How it connects: run with Node's built-in test runner in the CI `test-js` job;
// the checker itself runs against the whole repo in the `lint` job.

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { checkPackageJson, checkComposerJson, checkWorkflow } from './check-pins.mjs';

const SHA = 'a'.repeat(40);
const DIGEST = 'b'.repeat(64);

// ── package.json ────────────────────────────────────────────────────────────

test('package.json: exact versions and workspace links pass', () => {
  const problems = checkPackageJson(
    {
      packageManager: 'pnpm@12.14.0',
      dependencies: { vue: '3.5.44' },
      devDependencies: { turbo: '2.11.9', '@chapelcore/core': 'workspace:*' },
    },
    'package.json',
  );
  assert.deepEqual(problems, []);
});

test('package.json: ranges and tags fail, one problem each, naming file and field', () => {
  const problems = checkPackageJson(
    { dependencies: { a: '^1.2.3', b: '~1.2.3', c: '>=1.0.0', d: 'latest', e: '*', f: '1.2' } },
    'apps/web/nuxt/package.json',
  );
  assert.equal(problems.length, 6);
  assert.match(problems[0], /^apps\/web\/nuxt\/package\.json: dependencies\.a is "\^1\.2\.3"/);
});

test('package.json: pre-releases fail (stable releases only)', () => {
  const problems = checkPackageJson(
    { devDependencies: { typescript: '7.0.1-rc' } },
    'package.json',
  );
  assert.equal(problems.length, 1);
});

test('package.json: peerDependencies may be ranges (they describe what a package accepts)', () => {
  assert.deepEqual(checkPackageJson({ peerDependencies: { vue: '^3.5.0' } }, 'package.json'), []);
});

test('package.json: packageManager must be exact; an integrity hash is allowed', () => {
  assert.equal(checkPackageJson({ packageManager: 'pnpm@12' }, 'package.json').length, 1);
  assert.deepEqual(
    checkPackageJson({ packageManager: 'pnpm@12.14.0+sha512.0f3e' }, 'package.json'),
    [],
  );
});

// ── composer.json ───────────────────────────────────────────────────────────

test('composer.json: exact versions pass; php and ext-* constraints are allowed', () => {
  const problems = checkComposerJson(
    {
      require: { php: '^8.4', 'ext-mbstring': '*', 'psr/log': '3.0.2' },
      'require-dev': { 'phpunit/phpunit': '13.4.3' },
    },
    'libs/php/native/core/composer.json',
  );
  assert.deepEqual(problems, []);
});

test('composer.json: ranges, branches and pre-releases fail in require and require-dev', () => {
  const problems = checkComposerJson(
    {
      require: { 'laravel/framework': '^13.37' },
      'require-dev': { 'a/b': 'dev-main', 'c/d': '2.0.0-beta1' },
    },
    'composer.json',
  );
  assert.equal(problems.length, 3);
  assert.match(problems[0], /^composer\.json: require\.laravel\/framework is "\^13\.37"/);
});

test('composer.json: a local path package is pinned like any other package', () => {
  assert.deepEqual(
    checkComposerJson({ 'require-dev': { 'chapelcore/contract': '0.4.0' } }, 'composer.json'),
    [],
  );
  assert.equal(
    checkComposerJson({ 'require-dev': { 'chapelcore/contract': '*@dev' } }, 'composer.json')
      .length,
    1,
  );
});

// ── GitHub workflows ────────────────────────────────────────────────────────

test('workflow: SHA-pinned actions with a version comment pass, including sub-path actions', () => {
  const yaml = [
    'steps:',
    `  - uses: actions/checkout@${SHA} # v7.0.1`,
    `  - uses: github/codeql-action/init@${SHA}  # v3.30.1`,
    '  - uses: ./.github/actions/local-thing',
  ].join('\n');
  assert.deepEqual(checkWorkflow(yaml, 'ci.yml'), []);
});

test('workflow: a tag reference fails and names the file and line', () => {
  const problems = checkWorkflow(
    'steps:\n  - uses: actions/checkout@v4\n',
    '.github/workflows/ci.yml',
  );
  assert.equal(problems.length, 1);
  assert.match(problems[0], /^\.github\/workflows\/ci\.yml:2: /);
});

test('workflow: a SHA without its version comment fails', () => {
  assert.equal(checkWorkflow(`- uses: actions/checkout@${SHA}`, 'ci.yml').length, 1);
});

test('workflow: a short SHA fails', () => {
  assert.equal(checkWorkflow('- uses: actions/checkout@a1b2c3d # v7.0.1', 'ci.yml').length, 1);
});

test('workflow: a service image pinned to an exact version and its digest passes', () => {
  const yaml = [
    'services:',
    '  mariadb:',
    `    image: mariadb:11.4.13@sha256:${DIGEST}`,
    '  other:',
    `    image: 'ghcr.io/owner/tool:1.2.3@sha256:${DIGEST}'`,
  ].join('\n');
  assert.deepEqual(checkWorkflow(yaml, 'ci.yml'), []);
});

test('workflow: an image without its digest fails and names the file and line', () => {
  const problems = checkWorkflow(
    'services:\n  mariadb:\n    image: mariadb:11.4.13\n',
    '.github/workflows/ci.yml',
  );
  assert.equal(problems.length, 1);
  assert.match(problems[0], /^\.github\/workflows\/ci\.yml:3: /);
});

test('workflow: an image tag that is not an exact stable version fails, even with a digest', () => {
  for (const tag of ['11.4', 'latest', '11.4.13-rc', 'lts']) {
    const yaml = `    image: mariadb:${tag}@sha256:${DIGEST}`;
    assert.equal(checkWorkflow(yaml, 'ci.yml').length, 1, tag);
  }
});

test('workflow: a job container given as one line is checked like an image', () => {
  assert.equal(checkWorkflow('    container: node:24', 'ci.yml').length, 1);
  assert.deepEqual(checkWorkflow(`    container: node:24.11.1@sha256:${DIGEST}`, 'ci.yml'), []);
  assert.deepEqual(checkWorkflow('    container:', 'ci.yml'), []);
});
