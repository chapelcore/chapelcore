// api-base.ts — turns the admin's API address setting into an absolute URL.
// Why: CHAPELCORE_API_BASE may be a path on this site such as /api (D16 same-origin layout),
// but ApiClient takes an absolute URL, so a relative base is resolved against the page's
// origin at run time (phase-1.md §6.5, build settings).
// How it connects: useApiClient() and app.vue call it with window.location.origin. Tested by
// test/unit/api-base.test.ts. Plain TypeScript, no Nuxt auto-imports.

/**
 * `apiBase` as an absolute URL without a trailing slash. An address that is already absolute
 * keeps its own host; a path such as `/api` is placed on `origin`.
 */
export function absoluteApiBase(apiBase: string, origin: string): string {
  return new URL(apiBase, origin).href.replace(/\/+$/, '');
}
