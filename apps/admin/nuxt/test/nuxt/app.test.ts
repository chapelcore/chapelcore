// app.test.ts — smoke test for the admin app (checkpoint 1).
// Why: proves the skeleton works end to end in Nuxt: the app mounts with Nuxt's plugins and
// auto-imports, it is served under /admin/ as a browser-only app, it reads the API address
// from the build settings, and the version comes from version.json (DOMAIN-MODEL universal
// rule 12).
// How it connects: runs in Vitest's `nuxt` project (@nuxt/test-utils, jsdom), which clears
// CHAPELCORE_API_BASE so the result doesn't depend on your local .env. Checkpoint 3 replaces
// the page and adds its own tests.
import { describe, expect, it } from 'vitest';
import { mountSuspended } from '@nuxt/test-utils/runtime';
import { useRuntimeConfig } from '#imports';
import versionFile from '../../../../../version.json';
import App from '~/app.vue';

describe('the admin app', () => {
  it('shows its name and the version from version.json', async () => {
    const page = await mountSuspended(App);
    expect(page.get('h1').text()).toBe('ChapelCore Admin');
    expect(page.text()).toContain(`Version ${versionFile.version}`);
    // html() runs Vue Test Utils' formatter, which uses the overridden glob (phase-1.md §9)
    expect(page.html()).toContain('<h1>ChapelCore Admin</h1>');
  });

  it('is served under /admin/ and talks to the local API in tests', () => {
    const config = useRuntimeConfig();
    expect(config.app.baseURL).toBe('/admin/');
    expect(config.public.apiBase).toBe('http://localhost:8000');
  });
});
