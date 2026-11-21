// vitest.config.ts — how the web's tests run.
// Why: two kinds of tests (phase-1.md §6.5 E8): plain TypeScript in Node (test/unit) and
// components mounted in Nuxt (test/nuxt), in jsdom rather than @nuxt/test-utils' default,
// happy-dom, which we rejected (§9).
// How it connects: `pnpm test` runs `vitest run`. The build settings are emptied first, so
// the tests use the local API and the default site name whatever your .env says: Nuxt reads
// .env when it starts the `nuxt` project, but never replaces a variable that is already set.
import { defineVitestProject } from '@nuxt/test-utils/config';
import { defineConfig } from 'vitest/config';

process.env.CHAPELCORE_API_BASE = '';
process.env.CHAPELCORE_SITE_NAME = '';

export default defineConfig({
  test: {
    projects: [
      { test: { name: 'unit', include: ['test/unit/**/*.test.ts'], environment: 'node' } },
      await defineVitestProject({
        test: {
          name: 'nuxt',
          include: ['test/nuxt/**/*.test.ts'],
          environment: 'nuxt',
          environmentOptions: { nuxt: { domEnvironment: 'jsdom' } },
        },
      }),
    ],
  },
});
