// prettier.config.mjs — formatting rules for code files (TS, JS, Vue, JSON, YAML).
// Why: CLAUDE.md "nothing is formatted by hand"; CI's `lint` job runs `prettier --check`.
// How it connects: .prettierignore excludes Markdown (owner decision 2026-10-18),
// lockfiles and build output. PHP is formatted by Pint, not Prettier.

/** @type {import('prettier').Config} */
export default {
  printWidth: 100,
  singleQuote: true,
  trailingComma: 'all',
};
