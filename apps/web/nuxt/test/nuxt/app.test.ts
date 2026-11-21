// app.test.ts — smoke test for the web app (checkpoint 1).
// Why: proves the skeleton works end to end in Nuxt: the app mounts, it is served from the
// site root, the site name comes from the build settings and the version from version.json
// (universal rule 12).
// How it connects: runs in Vitest's `nuxt` project (@nuxt/test-utils, jsdom), which clears
// CHAPELCORE_API_BASE and CHAPELCORE_SITE_NAME so the result doesn't depend on your local
// .env. Checkpoint 5 replaces the page and adds its own tests.
import { describe, expect, it } from 'vitest';
import { mountSuspended } from '@nuxt/test-utils/runtime';
import { useRuntimeConfig } from '#imports';
import versionFile from '../../../../../version.json';
import App from '~/app.vue';

describe('the web app', () => {
  it('shows the site name (ChapelCore when unset) and the version from version.json', async () => {
    const page = await mountSuspended(App);
    expect(page.get('h1').text()).toBe('ChapelCore');
    expect(page.text()).toContain(`Version ${versionFile.version}`);
  });

  it('is served from the site root and talks to the local API in tests', () => {
    const config = useRuntimeConfig();
    expect(config.app.baseURL).toBe('/');
    expect(config.public.apiBase).toBe('http://localhost:8000');
    expect(config.public.siteName).toBe('ChapelCore');
  });
});
