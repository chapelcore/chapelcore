// eslint.config.mjs — ESLint rules for the web app.
// Why: CLAUDE.md coding standards: Vue follows the official style guide and Nuxt its own
// conventions (@nuxt/eslint), and our TypeScript follows the same strict, type-aware
// typescript-eslint rules as the TS core (phase-1.md §6.5 E7).
// How it connects: `pnpm lint` runs `eslint .` here; ESLint 10 uses this file instead of the
// root one for this folder. withNuxt() comes from .nuxt/, written by `nuxt prepare`. Only the
// *rules* of typescript-eslint's strict set are added: its full configs would replace the
// Vue parser on .vue files.
import tseslint from 'typescript-eslint';
import withNuxt from './.nuxt/eslint.config.mjs';

const strictTypeCheckedRules = Object.assign(
  {},
  ...tseslint.configs.strictTypeChecked.map((config) => config.rules ?? {}),
);

export default withNuxt({
  files: ['**/*.ts', '**/*.vue'],
  languageOptions: {
    parserOptions: {
      projectService: true,
      extraFileExtensions: ['.vue'],
      tsconfigRootDir: import.meta.dirname,
    },
  },
  rules: {
    ...strictTypeCheckedRules,
    // As in the root config: numbers read naturally in messages; objects and `unknown` stay banned.
    '@typescript-eslint/restrict-template-expressions': ['error', { allowNumber: true }],
  },
});
