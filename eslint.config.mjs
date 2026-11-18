// eslint.config.mjs — ESLint rules for the TypeScript in every workspace package.
// Why: CLAUDE.md coding standards: plain TypeScript is checked by ESLint with
// typescript-eslint (plan §6.4, E7): ESLint's recommended rules plus typescript-eslint's
// strict, type-aware rules.
// How it connects: each package's `lint` script runs `eslint .` in its own folder; ESLint
// finds this file by looking upward. Type-aware rules read each file's nearest
// tsconfig.json. CI runs it through `pnpm lint` (job: lint). The Nuxt apps add theirs in 1.7.
import js from '@eslint/js';
import { defineConfig, globalIgnores } from 'eslint/config';
import tseslint from 'typescript-eslint';

export default defineConfig(
  globalIgnores(['**/dist/', '**/node_modules/', '**/vendor/', '**/src/generated/', '**/.turbo/']),
  js.configs.recommended,
  tseslint.configs.strictTypeChecked,
  {
    languageOptions: {
      parserOptions: { projectService: true, tsconfigRootDir: import.meta.dirname },
    },
    rules: {
      // Numbers read naturally in messages (`${at}/${i}`); objects and `unknown` stay banned.
      '@typescript-eslint/restrict-template-expressions': ['error', { allowNumber: true }],
    },
  },
  {
    // Plain JavaScript (this file) has no types to check.
    files: ['**/*.js', '**/*.mjs'],
    extends: [tseslint.configs.disableTypeChecked],
  },
  {
    // The TS core runs in browsers and in Node. It takes the path, user agent and clock
    // from its caller, never from globals (plan §6.4), which also keeps it testable.
    files: ['libs/ts/native/core/src/**/*.ts'],
    rules: {
      'no-restricted-globals': [
        'error',
        'window',
        'document',
        'location',
        'navigator',
        'localStorage',
        'sessionStorage',
        'process',
      ],
    },
  },
);
