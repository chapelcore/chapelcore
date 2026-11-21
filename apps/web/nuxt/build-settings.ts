// build-settings.ts — the web's build settings, read from environment variables.
// Why: a static build bakes them in (phase-1.md §6.5 E2). The API address must be one the
// browser can use, and `nuxt generate` must never quietly fall back to localhost (risk R22).
// The site name lets each church's build show its own name (E5).
// How it connects: nuxt.config.ts calls apiBaseFrom() for runtimeConfig.public.apiBase,
// requireApiBase() when generating, siteNameFrom() for runtimeConfig.public.siteName and
// appHead() for the page head. The API rules are the admin's, repeated because each app has
// its own copy. Tested by test/unit/build-settings.test.ts.

export type Env = Readonly<Record<string, string | undefined>>;

/** The local API (`php artisan serve`), used by `nuxt dev`, `nuxt prepare` and the tests. */
export const DEV_API_BASE = 'http://localhost:8000';

const SETTING = 'CHAPELCORE_API_BASE';
// http(s)://host…, or a path on this site such as /api (D16), but never //host or /\host,
// which the browser reads as another site; no backslashes at all.
const VALID = /^(https?:\/\/[^/\s\\]+(\/[^\s\\]*)?|\/[^/\s\\][^\s\\]*)$/;

/** `{apiBase}` from CHAPELCORE_API_BASE without trailing slashes; the local API when unset. */
export function apiBaseFrom(env: Env): string {
  const raw = (env[SETTING] ?? '').trim();
  if (raw === '') {
    return DEV_API_BASE;
  }
  const value = raw.replace(/\/+$/, '');
  if (!VALID.test(value)) {
    throw new Error(
      `${SETTING} is "${raw}": use an address such as https://api.example.org or a path such as /api`,
    );
  }
  return value;
}

/** Stops `nuxt generate` when the address is missing or invalid. */
export function requireApiBase(env: Env): void {
  if ((env[SETTING] ?? '').trim() === '') {
    throw new Error(
      `${SETTING} is not set: the static build needs the API address (see .env.example)`,
    );
  }
  apiBaseFrom(env);
}

/** Shown when CHAPELCORE_SITE_NAME is unset (E5). */
export const DEFAULT_SITE_NAME = 'ChapelCore';

/** The church's name from CHAPELCORE_SITE_NAME, or ChapelCore. */
export function siteNameFrom(env: Env): string {
  const name = (env.CHAPELCORE_SITE_NAME ?? '').trim();
  return name === '' ? DEFAULT_SITE_NAME : name;
}

/**
 * Stops the build when a NUXT_PUBLIC_* or NUXT_APP_* variable is set: Nuxt would let it
 * replace a setting from this file (the API address, the base path) without these checks.
 */
export function refuseNuxtOverrides(env: Env): void {
  const names = Object.keys(env).filter(
    (name) => name.startsWith('NUXT_PUBLIC_') || name.startsWith('NUXT_APP_'),
  );
  if (names.length > 0) {
    throw new Error(
      `${names.join(', ')} would replace a ChapelCore build setting unchecked; ` +
        'remove it and use the CHAPELCORE_* settings in .env.example',
    );
  }
}

/** The page head: English, the title, and not indexed (the placeholder is only on test). */
export function appHead(title: string) {
  return {
    htmlAttrs: { lang: 'en' },
    title,
    meta: [{ name: 'robots', content: 'noindex' }],
  };
}
