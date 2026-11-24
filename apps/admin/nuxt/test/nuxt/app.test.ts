// app.test.ts — the admin's status page.
// Why: phase-1.md §6.5: the page shows the API's health in words: a summary in one
// `role="status"` region (so a screen reader announces each new result, and only that), each
// check in the API's order, both versions, and the API address it uses; "Check again" repeats
// the call and nothing polls. Marks (✓ ! ✕) are hidden from screen readers.
// How it connects: mounts app/app.vue in Vitest's `nuxt` project (@nuxt/test-utils, jsdom)
// with useApiClient() replaced by a stub (E8); the client is tested against a real server in
// the core, and the real API is checked in this checkpoint's "see it yourself" step and by
// 1.8's Playwright suite. The wording is tested in test/unit/health-view.test.ts.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime';
import type {
  FetchError,
  FetchResult,
  HealthStatus,
  ResponseMeta,
  VersionInfo,
} from '@chapelcore/core';
import { useRuntimeConfig } from '#imports';
import versionFile from '../../../../../version.json';
import allOk from '../../../../../libs/shared/contract/fixtures/valid/health-status/all-ok.json';
import degraded from '../../../../../libs/shared/contract/fixtures/valid/health-status/degraded.json';
import downResponse from '../../../../../libs/shared/contract/fixtures/valid/health-response/down.json';
import { buildInfo } from '~/utils/build-info';
import App from '~/app.vue';

type Health = FetchResult<HealthStatus>;

const { getHealth } = vi.hoisted(() => ({
  getHealth: vi.fn<() => Promise<Health>>(),
}));
mockNuxtImport('useApiClient', () => () => ({ getHealth }));

const meta = downResponse.meta as ResponseMeta;
const sameVersion: VersionInfo = {
  version: versionFile.version,
  codename: versionFile.codename,
};

function answer(data: object, version: VersionInfo = sameVersion): Health {
  return { ok: true, data: { ...(data as HealthStatus), version }, meta };
}

function failed(error: Pick<FetchError, 'kind' | 'message'> & Partial<FetchError>): Health {
  return {
    ok: false,
    error: { status: null, code: null, fields: {}, requestId: null, ...error },
  };
}

/** A reply that arrives only when the test says so. */
function later() {
  let resolve!: (result: Health) => void;
  const promise = new Promise<Health>((done) => {
    resolve = done;
  });
  return { promise, resolve };
}

async function mountWith(result: Health) {
  getHealth.mockResolvedValueOnce(result);
  const page = await mountSuspended(App);
  await flushPromises();
  return page;
}

beforeEach(() => {
  getHealth.mockReset();
});

afterEach(() => {
  vi.useRealTimers();
});

describe('the admin status page', () => {
  it('shows its name, and says it is checking until the API answers', async () => {
    getHealth.mockReturnValueOnce(later().promise);
    const page = await mountSuspended(App);
    expect(page.get('h1').text()).toBe('ChapelCore Admin');
    expect(page.get('[role="status"]').text()).toBe('Checking the API…');
    // html() runs Vue Test Utils' formatter, which uses the overridden glob (phase-1.md §9)
    expect(page.html()).toContain('<h1>ChapelCore Admin</h1>');
  });

  it('says "All systems OK" and lists each check', async () => {
    const page = await mountWith(answer(allOk));
    expect(page.get('[role="status"]').text()).toContain('All systems OK');
    const checks = page.findAll('li').map((item) => item.text());
    expect(checks).toHaveLength(3);
    expect(checks[0]).toContain('database: OK');
    expect(checks[1]).toContain('scheduler: OK');
    expect(checks[2]).toContain('storage: OK');
  });

  it('says "Working, with warnings" with the check and its message', async () => {
    const page = await mountWith(answer(degraded));
    expect(page.get('[role="status"]').text()).toContain('Working, with warnings');
    const scheduler = page.findAll('li')[1]?.text() ?? '';
    expect(scheduler).toContain('scheduler: Warning');
    expect(scheduler).toContain('Scheduled tasks have not run recently');
  });

  it('says "The API reports a problem" with the database down', async () => {
    const page = await mountWith(answer(downResponse.data));
    expect(page.get('[role="status"]').text()).toContain('The API reports a problem');
    const database = page.findAll('li')[0]?.text() ?? '';
    expect(database).toContain('database: Down');
    expect(database).toContain('Database unavailable');
  });

  it("keeps the checks in the API's order", async () => {
    const page = await mountWith(answer({ ...degraded, checks: [...degraded.checks].reverse() }));
    const names = page.findAll('li').map((item) => item.text().split(':')[0]?.replace(/^\W+/, ''));
    expect(names).toEqual(['storage', 'scheduler', 'database']);
  });

  it('hides the marks from screen readers', async () => {
    const page = await mountWith(answer(degraded));
    const hidden = page.findAll('[aria-hidden="true"]').map((item) => item.text());
    // the summary's mark, then one per check
    expect(hidden).toEqual(['!', '✓', '!', '✓']);
  });

  it('announces only the summary', async () => {
    const page = await mountWith(answer(degraded));
    const live = page.findAll('[role="status"], [aria-live]');
    expect(live).toHaveLength(1);
    expect(live[0]?.text()).toBe('! Working, with warnings');
  });

  it('shows an unknown status neutrally', async () => {
    const page = await mountWith(answer({ ...allOk, status: 'maintenance' }));
    expect(page.get('[role="status"]').text()).toBe('Unknown status');
  });

  it('shows a check status it does not know neutrally, with no mark', async () => {
    const checks = [{ name: 'cache', status: 'paused', message: null }];
    const page = await mountWith(answer({ ...allOk, checks }));
    const cache = page.get('li');
    expect(cache.text()).toBe('cache: Unknown status');
    expect(cache.find('[aria-hidden]').exists()).toBe(false);
  });

  it('says "Can\'t reach the API" and what to check, with no checks listed', async () => {
    const page = await mountWith(
      failed({ kind: 'network', message: 'Could not reach the server.' }),
    );
    expect(page.get('[role="status"]').text()).toContain("Can't reach the API");
    expect(page.text()).toContain('Check that the API is running');
    expect(page.text()).not.toContain('Reference');
    expect(page.find('ul').exists()).toBe(false);
  });

  it('says "Can\'t reach the API" when the API takes too long', async () => {
    const page = await mountWith(
      failed({
        kind: 'timeout',
        message: 'The server took too long to answer.',
      }),
    );
    expect(page.get('[role="status"]').text()).toContain("Can't reach the API");
    expect(page.text()).toContain('The server took too long to answer.');
  });

  it("shows an API error's message and its reference code", async () => {
    const page = await mountWith(
      failed({
        kind: 'api',
        status: 500,
        code: 'INTERNAL_ERROR',
        message: 'Something went wrong on our side.',
        requestId: '7F3K-92QD',
      }),
    );
    expect(page.get('[role="status"]').text()).toContain('The API sent an error');
    expect(page.text()).toContain('Something went wrong on our side.');
    expect(page.text()).toContain('Reference: 7F3K-92QD');
  });

  it('shows no reference line when the error had no reference code', async () => {
    const page = await mountWith(
      failed({
        kind: 'api',
        status: 503,
        code: 'SERVICE_UNAVAILABLE',
        message: 'Down for maintenance.',
      }),
    );
    expect(page.text()).toContain('Down for maintenance.');
    expect(page.text()).not.toContain('Reference');
  });

  it('shows data that is not a HealthStatus as an unreadable reply', async () => {
    const page = await mountWith({
      ok: true,
      data: { status: 'ok' } as HealthStatus,
      meta,
    });
    expect(page.get('[role="status"]').text()).toContain('The API sent an error');
    expect(page.text()).toContain('The server sent a reply that could not be read.');
  });

  it("shows the admin's version, and the API's without a codename when it has none", async () => {
    const page = await mountWith(answer(allOk, { version: '0.1.0', codename: null }));
    const admin =
      buildInfo.codename === null
        ? `Admin version ${buildInfo.version}`
        : `Admin version ${buildInfo.version} (${buildInfo.codename})`;
    expect(buildInfo.version).toBe(versionFile.version);
    expect(page.text()).toContain(admin);
    expect(page.text()).toContain('API version 0.1.0');
    expect(page.text()).not.toMatch(/API version 0\.1\.0 \(/);
  });

  it("shows the API's codename when it has one", async () => {
    const page = await mountWith(answer(allOk, { version: '1.0.0', codename: 'Kootenai' }));
    expect(page.text()).toContain('API version 1.0.0 (Kootenai)');
  });

  it('says when the admin and the API are on different versions', async () => {
    const page = await mountWith(answer(allOk, { version: '9.9.9', codename: null }));
    expect(page.text()).toContain('The admin and the API are on different versions');
  });

  it('says nothing about versions when they match', async () => {
    const page = await mountWith(answer(allOk));
    expect(page.text()).not.toContain('different versions');
  });

  it('shows the API address it uses', async () => {
    const page = await mountWith(answer(allOk));
    expect(page.text()).toContain('API address: http://localhost:8000');
  });

  it('asks the API once and does not poll', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    await mountWith(answer(allOk));
    vi.advanceTimersByTime(10 * 60_000);
    await flushPromises();
    expect(getHealth).toHaveBeenCalledOnce();
  });

  it('"Check again" asks the API again and shows the new answer', async () => {
    const page = await mountWith(answer(allOk));
    const region = page.get('[role="status"]').element;
    const reply = later();
    getHealth.mockReturnValueOnce(reply.promise);

    await page.get('button').trigger('click');
    expect(page.get('button').text()).toBe('Check again');
    expect(page.get('[role="status"]').text()).toBe('Checking the API…');

    reply.resolve(answer(downResponse.data));
    await flushPromises();
    expect(getHealth).toHaveBeenCalledTimes(2);
    expect(page.get('[role="status"]').text()).toContain('The API reports a problem');
    // the same region, so screen readers announce the change (it is never re-created)
    expect(page.get('[role="status"]').element).toBe(region);
  });

  it('"Check again" does not start a second call while one is running', async () => {
    getHealth.mockReturnValueOnce(later().promise);
    const page = await mountSuspended(App);
    // not `disabled`: keyboard focus stays on the button (a disabled one can't be clicked)
    expect(page.get('button').attributes('disabled')).toBeUndefined();
    await page.get('button').trigger('click');
    expect(getHealth).toHaveBeenCalledOnce();
    expect(page.get('[role="status"]').text()).toBe('Checking the API…');
  });

  it('is served under /admin/ and talks to the local API in tests', () => {
    const config = useRuntimeConfig();
    expect(config.app.baseURL).toBe('/admin/');
    expect(config.public.apiBase).toBe('http://localhost:8000');
  });
});
