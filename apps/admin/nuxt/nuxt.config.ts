// nuxt.config.ts — the admin app's Nuxt settings.
// Why: phase-1.md §6.5: a browser-only app (ssr: false) built static by `nuxt generate` and
// served under /admin/, with its build settings from the environment (E2), never indexed,
// and with Nuxt's telemetry and DevTools off so nothing leaves the machine.
// How it connects: build-settings.ts reads the environment; `nuxt generate` writes
// .output/public/, which 1.9 deploys. The test folders are added to Nuxt's tsconfigs so the
// type check and ESLint see them.
import { apiBaseFrom, appHead, refuseNuxtOverrides, requireApiBase } from './build-settings';

refuseNuxtOverrides(process.env);

export default defineNuxtConfig({
  compatibilityDate: '2026-11-21',
  modules: [
    '@nuxt/eslint',
    // `nuxt generate` needs the real API address; dev, prepare and the tests don't.
    (_options, nuxt) => {
      if (nuxt.options.nitro.static === true) {
        requireApiBase(process.env);
      }
    },
  ],
  ssr: false,
  app: { baseURL: '/admin/', head: appHead('ChapelCore Admin') },
  runtimeConfig: { public: { apiBase: apiBaseFrom(process.env) } },
  typescript: {
    nodeTsConfig: { include: ['../build-settings.ts', '../vitest.config.ts', '../test/unit/**/*'] },
  },
  telemetry: false,
  devtools: { enabled: false },
});
