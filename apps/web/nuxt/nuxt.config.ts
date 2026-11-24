// nuxt.config.ts — the web app's Nuxt settings.
// Why: phase-1.md §6.5: the public site, prerendered to static HTML by `nuxt generate` so its
// text shows without JavaScript, with its build settings from the environment (E2 and E5),
// not indexed while it is a placeholder, and with Nuxt's telemetry and DevTools off so
// nothing leaves the machine.
// How it connects: build-settings.ts reads the environment; `nuxt generate` writes
// .output/public/, which 1.9 deploys to test only. The test folders are added to Nuxt's
// tsconfigs so the type check and ESLint see them.
import {
  apiBaseFrom,
  appHead,
  refuseNuxtOverrides,
  requireApiBase,
  siteNameFrom,
} from './build-settings';

refuseNuxtOverrides(process.env);

const siteName = siteNameFrom(process.env);

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
  app: { head: appHead(siteName) },
  runtimeConfig: { public: { apiBase: apiBaseFrom(process.env), siteName } },
  typescript: {
    nodeTsConfig: { include: ['../build-settings.ts', '../vitest.config.ts', '../test/unit/**/*'] },
  },
  telemetry: false,
  devtools: { enabled: false },
});
