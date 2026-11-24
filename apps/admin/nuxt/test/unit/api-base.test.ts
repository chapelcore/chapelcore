// api-base.test.ts — how the admin turns its API address setting into an absolute URL.
// Why: CHAPELCORE_API_BASE may be a path on this site such as /api (D16 same-origin layout),
// but ApiClient takes an absolute URL, so a relative base is resolved against the page's
// origin at run time (phase-1.md §6.5, build settings).
// How it connects: tests app/utils/api-base.ts; useApiClient() calls it with
// window.location.origin.
import { describe, expect, it } from 'vitest';
import { absoluteApiBase } from '../../app/utils/api-base';

describe('absoluteApiBase()', () => {
  it.each([
    ['an API host', 'https://api.example.org', 'https://api.example.org'],
    ['an API host with a path', 'https://example.org/api', 'https://example.org/api'],
    ['the local API', 'http://localhost:8000', 'http://localhost:8000'],
    ['a path on this site', '/api', 'https://test.example.org/api'],
    ['a deeper path on this site', '/church/api', 'https://test.example.org/church/api'],
    ['an address with a trailing slash', 'https://example.org/api/', 'https://example.org/api'],
    ['a path with a trailing slash', '/api/', 'https://test.example.org/api'],
  ])('resolves %s', (_case, apiBase, expected) => {
    expect(absoluteApiBase(apiBase, 'https://test.example.org')).toBe(expected);
  });

  it("keeps an absolute address's own host whatever the page's origin", () => {
    expect(absoluteApiBase('https://api.example.org', 'http://localhost:3001')).toBe(
      'https://api.example.org',
    );
  });
});
