// use-api-client.test.ts — the admin's one API client.
// Why: every page gets the same ApiClient, pointed at the address from the build settings
// (phase-1.md §6.5). The client itself is tested against a real server in the core (E8), so
// here only the wiring is checked, with the browser's fetch replaced.
// How it connects: tests app/composables/useApiClient.ts in Vitest's `nuxt` project, where
// the API address is the local API (vitest.config.ts clears CHAPELCORE_API_BASE).
import { afterEach, describe, expect, it, vi } from 'vitest';
import downResponse from '../../../../../libs/shared/contract/fixtures/valid/health-response/down.json';
import { useApiClient } from '~/composables/useApiClient';

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('useApiClient()', () => {
  it('calls the API at the address from the build settings', async () => {
    const fakeFetch = vi.fn<typeof fetch>(() =>
      Promise.resolve(
        new Response(JSON.stringify(downResponse), {
          status: 503,
          headers: { 'Content-Type': 'application/json' },
        }),
      ),
    );
    vi.stubGlobal('fetch', fakeFetch);

    const result = await useApiClient().getHealth();

    expect(fakeFetch).toHaveBeenCalledOnce();
    expect(fakeFetch.mock.calls[0]?.[0]).toBe('http://localhost:8000/health');
    expect(result.ok).toBe(true);
  });

  it('gives the same client every time', () => {
    expect(useApiClient()).toBe(useApiClient());
  });
});
