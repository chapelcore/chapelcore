// check-pins.mjs — fails the build if any dependency, GitHub Action, Docker image or
// pnpm override is not pinned to an exact, stable version.
// Why: CLAUDE.md "Pin versions": a passing build must not start failing by itself,
// and a moved Action tag must never run new code in our CI. Pre-releases are
// rejected too ("latest" means latest stable). A Docker image also needs its digest,
// because a tag can be moved to a different image; a digest can't. In
// pnpm-workspace.yaml, overrides must be exact, every install-script decision must be
// a plain true or false, and any other pnpm setting is refused until this checker
// knows it, since several (minimumReleaseAgeExclude, dangerouslyAllowAllBuilds, …)
// would quietly undo what the pins and decisions protect (phase-1.md §9, R20, R25).
// How it connects: run as `pnpm check:pins` in the CI `lint` job and locally;
// checks the files git tracks. Tested by check-pins.test.mjs.

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
// A service or job-container image on one line: `image: name:tag@sha256:…  # comment`
const IMAGE = /^\s*(?:-\s+)?(?:image|container):\s*(['"]?)([^\s'"#]+)\1\s*(?:#.*)?$/;
const IMAGE_AT_DIGEST = /^[\w./-]+:\d+\.\d+\.\d+@sha256:[0-9a-f]{64}$/;
// pnpm-workspace.yaml: the sections this checker reads, and the other settings it accepts.
const PNPM_CHECKED = { overrides: 'version', allowBuilds: 'decision' };
const PNPM_KNOWN = new Set(['packages', 'auditConfig', ...Object.keys(PNPM_CHECKED)]);

export function checkPackageJson(pkg, file) {
  const problems = [];
  for (const field of ['pnpm', 'resolutions']) {
    if (pkg[field] !== undefined) {
      problems.push(
        `${file}: ${field} is not checked here; put pnpm settings in pnpm-workspace.yaml`,
      );
    }
  }
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
    const image = IMAGE.exec(line);
    if (image) {
      if (!IMAGE_AT_DIGEST.test(image[2])) {
        problems.push(
          `${file}:${index + 1}: image "${image[2]}" must be pinned to an exact version and ` +
            'its digest, e.g. mariadb:11.4.13@sha256:<64 hex characters>',
        );
      }
      return;
    }
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

const unquote = (text) => text.replace(/^(['"])(.*)\1$/, '$2');

/**
 * Reads only the parts of pnpm-workspace.yaml that matter, line by line, so no YAML parser
 * is needed.
 */
export function checkPnpmWorkspace(text, file) {
  const problems = [];
  let section = null; // a key of PNPM_CHECKED while inside it
  let indent = null; // the first entry's indentation; every entry must use it
  let seenTop = false; // YAML may indent the whole file, which would hide every key
  text
    .replace(/^\uFEFF/, '')
    .split(/\r?\n/)
    .forEach((raw, index) => {
      const where = `${file}:${index + 1}`;
      const line = raw.replace(/(^|\s)#.*$/, '');
      if (line.trim() === '') return;
      const top = /^(\S[^:]*):\s*(.*)$/.exec(line);
      if (top) {
        seenTop = true;
        const key = unquote(top[1].trim());
        section = null;
        indent = null;
        if (!PNPM_KNOWN.has(key)) {
          problems.push(
            `${where}: pnpm setting "${key}" is not checked by check-pins; teach the checker ` +
              'about it, with a test, before using it',
          );
        } else if (key in PNPM_CHECKED) {
          section = key;
          if (top[2] !== '') {
            problems.push(
              `${where}: write ${key} with one "name: value" entry per line, so it can be checked`,
            );
          }
        }
        return;
      }
      if (!seenTop) {
        problems.push(`${where}: start the file's settings at the beginning of the line`);
        seenTop = true;
        return;
      }
      if (section === null) return;
      const depth = /^\s*/.exec(line)[0].length;
      indent ??= depth;
      const entry = /^\s+([^:]+?):\s*(.*?)\s*$/.exec(line);
      if (!entry || depth !== indent) {
        problems.push(
          `${where}: this ${section} entry can't be read; write "name: value" on one line`,
        );
        return;
      }
      const name = unquote(entry[1].trim());
      const value = entry[2];
      if (PNPM_CHECKED[section] === 'version' && !EXACT.test(unquote(value))) {
        problems.push(
          `${where}: overrides.${name} is "${unquote(value)}"; use an exact stable version (x.y.z)`,
        );
      }
      if (PNPM_CHECKED[section] === 'decision' && name.includes('*')) {
        problems.push(
          `${where}: allowBuilds.${name} is a pattern; decide for each package by name`,
        );
        return;
      }
      if (PNPM_CHECKED[section] === 'decision' && value !== 'true' && value !== 'false') {
        problems.push(`${where}: allowBuilds.${name} is "${value}"; use true or false, unquoted`);
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
  for (const file of trackedFiles(root, 'pnpm-workspace.yaml')) {
    problems.push(...checkPnpmWorkspace(read(file), file));
  }
  if (JSON.parse(read('package.json')).packageManager === undefined) {
    problems.push('package.json: packageManager is missing; pin the pnpm version');
  }

  if (problems.length > 0) {
    console.error(problems.join('\n'));
    console.error(`\n${problems.length} unpinned version(s). See CLAUDE.md, "Pin versions".`);
    process.exitCode = 1;
  } else {
    console.log('All dependencies, GitHub Actions, Docker images and pnpm overrides are pinned.');
  }
}

// Run the repo check only when executed directly, not when the tests import it.
if (process.argv[1] === fileURLToPath(import.meta.url)) main();
