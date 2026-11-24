// useApiClient.ts — the admin's one API client.
// Why: every page talks to the API through the same ApiClient from @chapelcore/core, pointed
// at the address from the build settings (phase-1.md §6.5); no page builds its own.
// How it connects: Nuxt auto-imports it into components. Pages must use the auto-import (not
// an explicit import), so the page tests can replace it with mockNuxtImport. Tested by
// test/nuxt/use-api-client.test.ts.
import type { ApiClient } from '@chapelcore/core';

// Checkpoint 3: you type this function from the worked example.
export function useApiClient(): ApiClient {
  throw new Error('useApiClient() is not written yet (iteration 1.7, checkpoint 3)');
}
