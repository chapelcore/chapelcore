// build-settings.test.ts — unit tests for the web's build settings and build info.
// Why: the API address (for error reports) and the site name are baked into the static
// build (phase-1.md §6.5 E2 and E5, risk R22); the placeholder is not indexed; the version
// comes from version.json (DOMAIN-MODEL universal rule 12). The API rules are the admin's,
// repeated here because each app has its own build-settings.ts.
// How it connects: nuxt.config.ts reads build-settings.ts, and the app reads
// app/utils/build-info.ts. This runs in Vitest's `unit` project (plain Node, no Nuxt).
import { describe, expect, it } from 'vitest';
import versionFile from '../../../../../version.json';
import { buildInfo } from '../../app/utils/build-info';
import {
  apiBaseFrom,
  appHead,
  DEFAULT_SITE_NAME,
  DEV_API_BASE,
  refuseNuxtOverrides,
  requireApiBase,
  siteNameFrom,
} from '../../build-settings';

describe('apiBaseFrom()', () => {
  it('uses CHAPELCORE_API_BASE without spaces or trailing slashes, or the local API', () => {
    expect(apiBaseFrom({ CHAPELCORE_API_BASE: ' https://api.test.libbynaz.org/ ' })).toBe(
      'https://api.test.libbynaz.org',
    );
    expect(apiBaseFrom({ CHAPELCORE_API_BASE: '/api' })).toBe('/api');
    expect(apiBaseFrom({})).toBe(DEV_API_BASE);
    expect(DEV_API_BASE).toBe('http://localhost:8000');
  });

  it('refuses an address that is neither http(s) nor a path on this site', () => {
    for (const value of ['api.libbynaz.org', '//api.libbynaz.org', '/\\api.libbynaz.org']) {
      expect(() => apiBaseFrom({ CHAPELCORE_API_BASE: value }), value).toThrow(
        /CHAPELCORE_API_BASE/,
      );
    }
  });
});

describe('requireApiBase()', () => {
  it('stops the static build only when the address is unset, blank or invalid', () => {
    expect(() => {
      requireApiBase({ CHAPELCORE_API_BASE: 'https://api.libbynaz.org' });
    }).not.toThrow();
    for (const value of [undefined, ' ', 'api.libbynaz.org']) {
      expect(() => {
        requireApiBase({ CHAPELCORE_API_BASE: value });
      }, String(value)).toThrow(/CHAPELCORE_API_BASE/);
    }
  });
});

describe('siteNameFrom()', () => {
  it('uses CHAPELCORE_SITE_NAME without surrounding spaces', () => {
    expect(siteNameFrom({ CHAPELCORE_SITE_NAME: ' Libby Church of the Nazarene ' })).toBe(
      'Libby Church of the Nazarene',
    );
  });

  it('falls back to ChapelCore when the setting is unset or blank', () => {
    expect(DEFAULT_SITE_NAME).toBe('ChapelCore');
    expect(siteNameFrom({})).toBe(DEFAULT_SITE_NAME);
    expect(siteNameFrom({ CHAPELCORE_SITE_NAME: '   ' })).toBe(DEFAULT_SITE_NAME);
  });
});

describe('refuseNuxtOverrides()', () => {
  it('passes when no NUXT_PUBLIC_ or NUXT_APP_ variable is set', () => {
    expect(() => {
      refuseNuxtOverrides({
        CHAPELCORE_API_BASE: 'https://api.libbynaz.org',
        NUXT_TELEMETRY_DISABLED: '1',
      });
    }).not.toThrow();
  });

  it('stops when one would replace a checked setting, naming it', () => {
    for (const name of ['NUXT_PUBLIC_API_BASE', 'NUXT_PUBLIC_SITE_NAME', 'NUXT_APP_BASE_URL']) {
      expect(() => {
        refuseNuxtOverrides({ [name]: 'https://evil.example' });
      }, name).toThrow(new RegExp(name));
    }
  });
});

describe('appHead()', () => {
  it('sets the language, the title and noindex', () => {
    expect(appHead('ChapelCore')).toEqual({
      htmlAttrs: { lang: 'en' },
      title: 'ChapelCore',
      meta: [{ name: 'robots', content: 'noindex' }],
    });
  });
});

describe('buildInfo', () => {
  it('is the version and codename from version.json', () => {
    expect(buildInfo).toEqual({ version: versionFile.version, codename: versionFile.codename });
  });
});
