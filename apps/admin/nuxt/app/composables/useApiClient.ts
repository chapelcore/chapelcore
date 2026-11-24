// useApiClient.ts — the admin's one API client.
// Why: every page talks to the API through the same ApiClient from @chapelcore/core, pointed
// at the address from the build settings (phase-1.md §6.5); no page builds its own.
// How it connects: Nuxt auto-imports it into components. Pages must use the auto-import (not
// an explicit import), so the page tests can replace it with mockNuxtImport. Tested by
// test/nuxt/use-api-client.test.ts.
import { ApiClient } from '@chapelcore/core';
import { absoluteApiBase } from '~/utils/api-base';

// Made on the first call and kept, so every page shares one client. Safe because the admin
// runs only in the browser (`ssr: false`): with server rendering this would be shared between
// visitors, and `window` would not exist.
let client: ApiClient | undefined;

export function useApiClient(): ApiClient {
  client ??= new ApiClient({
    baseUrl: absoluteApiBase(useRuntimeConfig().public.apiBase, window.location.origin),
  });
  return client;
}
