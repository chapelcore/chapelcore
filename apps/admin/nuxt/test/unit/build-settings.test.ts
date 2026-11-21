// build-settings.test.ts — unit tests for the admin's build settings and build info.
// Why: the API address is baked into the static build, so a production build must never
// quietly fall back to localhost or carry an address the browser can't use (phase-1.md
// §6.5 E2, risk R22); the admin must never be indexed; the version and codename come from
// version.json, never a literal (universal rule 12).
// How it connects: nuxt.config.ts reads build-settings.ts, and the app reads
// app/utils/build-info.ts. This runs in Vitest's `unit` project (plain Node, no Nuxt).
import { describe, expect, it } from 'vitest';
import versionFile from '../../../../../version.json';
import { buildInfo } from '../../app/utils/build-info';
import { apiBaseFrom, appHead, DEV_API_BASE, requireApiBase } from '../../build-settings';

describe('apiBaseFrom()', () => {
  it('uses CHAPELCORE_API_BASE without surrounding spaces or trailing slashes', () => {
    expect(apiBaseFrom({ CHAPELCORE_API_BASE: ' https://api.test.libbynaz.org/ ' })).toBe(
      'https://api.test.libbynaz.org',
    );
  });

  it('keeps a relative base for the same-origin layout (D16)', () => {
    expect(apiBaseFrom({ CHAPELCORE_API_BASE: '/api/' })).toBe('/api');
  });

  it('falls back to the local API when the setting is unset or blank', () => {
    expect(DEV_API_BASE).toBe('http://localhost:8000');
    expect(apiBaseFrom({})).toBe(DEV_API_BASE);
    expect(apiBaseFrom({ CHAPELCORE_API_BASE: '  ' })).toBe(DEV_API_BASE);
  });

  it('refuses an address that is neither http(s) nor a path on this site', () => {
    for (const value of [
      'api.libbynaz.org',
      'ftp://api.libbynaz.org',
      'api',
      '//api.libbynaz.org',
      '/',
    ]) {
      expect(() => apiBaseFrom({ CHAPELCORE_API_BASE: value }), value).toThrow(
        /CHAPELCORE_API_BASE/,
      );
    }
  });
});

describe('requireApiBase()', () => {
  it('passes when the address is set', () => {
    expect(() => {
      requireApiBase({ CHAPELCORE_API_BASE: 'https://api.libbynaz.org' });
    }).not.toThrow();
  });

  it('stops the static build when the address is unset, blank or invalid, naming the setting', () => {
    for (const value of [undefined, ' ', 'api.libbynaz.org']) {
      expect(() => {
        requireApiBase({ CHAPELCORE_API_BASE: value });
      }, String(value)).toThrow(/CHAPELCORE_API_BASE/);
    }
  });
});

describe('appHead()', () => {
  it('sets the language, the title and noindex', () => {
    expect(appHead('ChapelCore Admin')).toEqual({
      htmlAttrs: { lang: 'en' },
      title: 'ChapelCore Admin',
      meta: [{ name: 'robots', content: 'noindex' }],
    });
  });
});

describe('buildInfo', () => {
  it('is the version and codename from version.json', () => {
    expect(buildInfo).toEqual({ version: versionFile.version, codename: versionFile.codename });
  });
});
