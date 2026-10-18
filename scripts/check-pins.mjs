// check-pins.mjs — fails the build if any dependency or GitHub Action is not
// pinned to an exact, stable version.
// Why: CLAUDE.md "Pin versions": a passing build must not start failing by itself,
// and a moved Action tag must never run new code in our CI. Pre-releases are
// rejected too ("latest" means latest stable).
// How it connects: run as `node scripts/check-pins.mjs` in the CI `lint` job and
// locally; checks the files git tracks. Tested by check-pins.test.mjs.

import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

// An exact, stable version: 1.2.3 (no ranges, tags or pre-release suffixes).
const EXACT = /^\d+\.\d+\.\d+$/;
// packageManager may carry Corepack's integrity hash: pnpm@12.14.0+sha512.…
const PACKAGE_MANAGER = /^[a-z]+@\d+\.\d+\.\d+(\+sha\d+\.[0-9a-f]+)?$/;
// peerDependencies are left out on purpose: they state what a package accepts.
const PINNED_SECTIONS = ['dependencies', 'devDependencies', 'optionalDependencies'];
const COMPOSER_SECTIONS = ['require', 'require-dev'];
// A workflow step or job line: `uses: owner/repo[/path]@ref  # comment`
const USES = /^\s*(?:-\s+)?uses:\s*(\S+)\s*(.*)$/;
const ACTION_AT_SHA = /^[\w.-]+\/[\w./-]+@[0-9a-f]{40}$/;
const VERSION_COMMENT = /^#\s*v?\d+\.\d+\.\d+$/;

export function checkPackageJson(pkg, file) {
  const problems = [];
  if (pkg.packageManager !== undefined && !PACKAGE_MANAGER.test(pkg.packageManager)) {
    problems.push(
      `${file}: packageManager is "${pkg.packageManager}"; use an exact version, e.g. pnpm@12.14.0`,
    );
  }
  for (const section of PINNED_SECTIONS) {
    for (const [name, version] of Object.entries(pkg[section] ?? {})) {
      if (version !== 'workspace:*' && !EXACT.test(version)) {
        problems.push(
          `${file}: ${section}.${name} is "${version}"; use an exact stable version (x.y.z)`,
        );
      }
    }
  }
  return problems;
}

export function checkComposerJson(composer, file) {
  const problems = [];
  for (const section of COMPOSER_SECTIONS) {
    for (const [name, version] of Object.entries(composer[section] ?? {})) {
      // The PHP version and extensions are platform requirements, not packages.
      if (name === 'php' || name.startsWith('ext-')) continue;
      if (!EXACT.test(version)) {
        problems.push(
          `${file}: ${section}.${name} is "${version}"; use an exact stable version (x.y.z)`,
        );
      }
    }
  }
  return problems;
}

export function checkWorkflow(text, file) {
  const problems = [];
  text.split('\n').forEach((line, index) => {
    const match = USES.exec(line);
    if (!match) return;
    const [, ref, rest] = match;
    if (ref.startsWith('./')) return; // an action stored in this repository
    if (!ACTION_AT_SHA.test(ref) || !VERSION_COMMENT.test(rest.trim())) {
      problems.push(
        `${file}:${index + 1}: "${ref}" must be pinned to a full 40-character commit SHA ` +
          'followed by a "# vX.Y.Z" comment',
      );
    }
  });
  return problems;
}

function trackedFiles(root, ...patterns) {
  const output = execFileSync('git', ['ls-files', '-z', '--', ...patterns], {
    cwd: root,
    encoding: 'utf8',
  });
  return output.split('\0').filter(Boolean);
}

function main() {
  const root = fileURLToPath(new URL('..', import.meta.url));
  const read = (file) => readFileSync(join(root, file), 'utf8');
  const problems = [];

  for (const file of trackedFiles(root, ':(glob)**/package.json')) {
    problems.push(...checkPackageJson(JSON.parse(read(file)), file));
  }
  for (const file of trackedFiles(root, ':(glob)**/composer.json')) {
    problems.push(...checkComposerJson(JSON.parse(read(file)), file));
  }
  for (const file of trackedFiles(
    root,
    ':(glob).github/workflows/*.yml',
    ':(glob).github/workflows/*.yaml',
    ':(glob).github/actions/**/action.yml',
    ':(glob).github/actions/**/action.yaml',
  )) {
    problems.push(...checkWorkflow(read(file), file));
  }
  if (JSON.parse(read('package.json')).packageManager === undefined) {
    problems.push('package.json: packageManager is missing; pin the pnpm version');
  }

  if (problems.length > 0) {
    console.error(problems.join('\n'));
    console.error(`\n${problems.length} unpinned version(s). See CLAUDE.md, "Pin versions".`);
    process.exitCode = 1;
  } else {
    console.log('All dependencies and GitHub Actions are pinned.');
  }
}

// Run the repo check only when executed directly, not when the tests import it.
if (process.argv[1] === fileURLToPath(import.meta.url)) main();
