// health-view.test.ts — what the admin's status page says for each answer from the API.
// Why: phase-1.md §6.5: the page's wording comes from healthView(), a plain function, so every
// state is tested here without mounting anything (E3, E8). Status is never shown by color
// alone: each state has words and a mark (✓ ! ✕), and a status this admin doesn't know is
// shown neutrally (DOMAIN-MODEL universal rule 5). The client checks only the envelope, so
// data that isn't shaped like a HealthStatus is caught here.
// How it connects: tests app/utils/health-view.ts (checkpoint 3); app.vue shows what it
// returns, and asks versionsDiffer() whether to show the "different versions" note. The health data comes from the contract's own fixtures.
import { describe, expect, it } from 'vitest';
import type { FetchError, FetchResult, HealthStatus, ResponseMeta } from '@chapelcore/core';
import allOk from '../../../../../libs/shared/contract/fixtures/valid/health-status/all-ok.json';
import degraded from '../../../../../libs/shared/contract/fixtures/valid/health-status/degraded.json';
import downResponse from '../../../../../libs/shared/contract/fixtures/valid/health-response/down.json';
import { CHECKING, healthView, versionsDiffer } from '../../app/utils/health-view';

const meta = downResponse.meta as ResponseMeta;
const ADVICE = 'Check that the API is running and that the API address below is right.';
const UNREADABLE = 'The server sent a reply that could not be read.';

/** A successful reply; `data` is typed loosely so the tests can send what the API might. */
function answer(data: unknown): FetchResult<HealthStatus> {
  return { ok: true, data: data as HealthStatus, meta };
}

function failed(error: Pick<FetchError, 'kind' | 'message'> & Partial<FetchError>) {
  const full: FetchError = {
    status: null,
    code: null,
    fields: {},
    requestId: null,
    ...error,
  };
  return { ok: false, error: full } as const;
}

describe('healthView()', () => {
  it('says it is checking before the API answers', () => {
    expect(CHECKING).toEqual({
      state: 'checking',
      mark: null,
      summary: 'Checking the API…',
      detail: null,
      requestId: null,
      checks: [],
      apiVersion: null,
    });
  });

  it('says "All systems OK" and lists every check', () => {
    expect(healthView(answer(allOk))).toEqual({
      state: 'ok',
      mark: '✓',
      summary: 'All systems OK',
      detail: null,
      requestId: null,
      checks: [
        { name: 'database', status: 'OK', mark: '✓', message: null },
        { name: 'scheduler', status: 'OK', mark: '✓', message: null },
        { name: 'storage', status: 'OK', mark: '✓', message: null },
      ],
      apiVersion: { version: '0.1.0', codename: null },
    });
  });

  it('says "Working, with warnings" and which check warns', () => {
    const view = healthView(answer(degraded));
    expect(view.state).toBe('degraded');
    expect(view.mark).toBe('!');
    expect(view.summary).toBe('Working, with warnings');
    expect(view.checks[1]).toEqual({
      name: 'scheduler',
      status: 'Warning',
      mark: '!',
      message: 'Scheduled tasks have not run recently',
    });
  });

  it('says "The API reports a problem" when it is down (a 503 is data)', () => {
    const view = healthView(answer(downResponse.data));
    expect(view.state).toBe('down');
    expect(view.mark).toBe('✕');
    expect(view.summary).toBe('The API reports a problem');
    expect(view.checks[0]).toEqual({
      name: 'database',
      status: 'Down',
      mark: '✕',
      message: 'Database unavailable',
    });
  });

  it("keeps the checks in the API's order (universal rule 7)", () => {
    const reversed = { ...degraded, checks: [...degraded.checks].reverse() };
    expect(healthView(answer(reversed)).checks.map((check) => check.name)).toEqual([
      'storage',
      'scheduler',
      'database',
    ]);
  });

  it('shows an overall status it does not know as "Unknown status", with no mark', () => {
    const view = healthView(answer({ ...allOk, status: 'maintenance' }));
    expect(view.state).toBe('unknown');
    expect(view.mark).toBeNull();
    expect(view.summary).toBe('Unknown status');
    expect(view.checks).toHaveLength(3);
  });

  it('shows a check status it does not know as "Unknown status", with no mark', () => {
    const checks = [{ name: 'cache', status: 'paused', message: 'Paused by the host' }];
    const view = healthView(answer({ ...allOk, checks }));
    expect(view.state).toBe('ok');
    expect(view.checks).toEqual([
      {
        name: 'cache',
        status: 'Unknown status',
        mark: null,
        message: 'Paused by the host',
      },
    ]);
  });

  it('lists no checks when the API sends none', () => {
    expect(healthView(answer({ ...allOk, checks: [] })).checks).toEqual([]);
  });

  it('shows the API version with its codename', () => {
    const version = { version: '1.0.0', codename: 'Kootenai' };
    expect(healthView(answer({ ...allOk, version })).apiVersion).toEqual(version);
  });

  it('says "Can\'t reach the API", and what to check, on a network error', () => {
    const view = healthView(failed({ kind: 'network', message: 'Could not reach the server.' }));
    expect(view).toEqual({
      state: 'unreachable',
      mark: '✕',
      summary: "Can't reach the API",
      detail: `Could not reach the server. ${ADVICE}`,
      requestId: null,
      checks: [],
      apiVersion: null,
    });
  });

  it('says "Can\'t reach the API" on a timeout, with the client\'s message', () => {
    const view = healthView(
      failed({
        kind: 'timeout',
        message: 'The server took too long to answer.',
      }),
    );
    expect(view.state).toBe('unreachable');
    expect(view.summary).toBe("Can't reach the API");
    expect(view.detail).toBe(`The server took too long to answer. ${ADVICE}`);
  });

  it("shows an API error's safe message and its reference code", () => {
    const view = healthView(
      failed({
        kind: 'api',
        status: 500,
        code: 'INTERNAL_ERROR',
        message: 'Something went wrong on our side.',
        requestId: '7F3K-92QD',
      }),
    );
    expect(view).toEqual({
      state: 'error',
      mark: '✕',
      summary: 'The API sent an error',
      detail: 'Something went wrong on our side.',
      requestId: '7F3K-92QD',
      checks: [],
      apiVersion: null,
    });
  });

  it('shows an API error without a reference code when the reply had none', () => {
    const view = healthView(
      failed({
        kind: 'api',
        status: 503,
        code: 'SERVICE_UNAVAILABLE',
        message: 'Down for maintenance.',
      }),
    );
    expect(view.state).toBe('error');
    expect(view.detail).toBe('Down for maintenance.');
    expect(view.requestId).toBeNull();
  });

  it('shows a reply the client could not read as an error', () => {
    const view = healthView(
      failed({
        kind: 'invalid-response',
        status: 200,
        message: UNREADABLE,
        requestId: '7F3K-92QD',
      }),
    );
    expect(view.state).toBe('error');
    expect(view.summary).toBe('The API sent an error');
    expect(view.detail).toBe(UNREADABLE);
    expect(view.requestId).toBe('7F3K-92QD');
  });

  const check = { name: 'database', status: 'ok', message: null };
  const version = { version: '0.1.0', codename: null };
  it.each([
    ['null', null],
    ['a list', []],
    ['a text', 'ok'],
    ['no version or checks', { status: 'ok' }],
    ['a status that is not text', { status: 1, version, checks: [check] }],
    ['no version', { status: 'ok', checks: [check] }],
    [
      'a version that is not text',
      { status: 'ok', version: { version: 1, codename: null }, checks: [] },
    ],
    [
      'a codename that is not text or null',
      { status: 'ok', version: { version: '0.1.0', codename: 7 }, checks: [] },
    ],
    ['a version without its codename', { status: 'ok', version: { version: '0.1.0' }, checks: [] }],
    ['a version that is null', { status: 'ok', version: null, checks: [] }],
    ['checks that are not a list', { status: 'ok', version, checks: 'none' }],
    ['a check that is not an object', { status: 'ok', version, checks: ['database'] }],
    [
      'a check without a name',
      { status: 'ok', version, checks: [{ status: 'ok', message: null }] },
    ],
    ['a check name that is not text', { status: 'ok', version, checks: [{ ...check, name: 5 }] }],
    [
      'a check status that is not text',
      { status: 'ok', version, checks: [{ ...check, status: null }] },
    ],
    [
      'a check message that is not text or null',
      { status: 'ok', version, checks: [{ ...check, message: 5 }] },
    ],
  ])('shows data that is not a HealthStatus (%s) as an unreadable reply', (_case, data) => {
    expect(healthView(answer(data))).toEqual({
      state: 'error',
      mark: '✕',
      summary: 'The API sent an error',
      detail: UNREADABLE,
      requestId: meta.requestId,
      checks: [],
      apiVersion: null,
    });
  });

  it('ignores fields it does not know (universal rule 6)', () => {
    const checks = [{ name: 'database', status: 'ok', message: null, tookMs: 3 }];
    const view = healthView(answer({ ...allOk, uptime: 5, checks }));
    expect(view.state).toBe('ok');
    expect(view.checks).toEqual([{ name: 'database', status: 'OK', mark: '✓', message: null }]);
  });

  it('does not change the result it is given', () => {
    const result = answer(structuredClone(degraded));
    const before = structuredClone(result);
    healthView(result);
    expect(result).toEqual(before);
  });
});

describe('versionsDiffer()', () => {
  const admin = { version: '0.1.0', codename: null };

  it('is false when the versions match', () => {
    expect(versionsDiffer(admin, { version: '0.1.0', codename: null })).toBe(false);
  });

  it('is true when they differ', () => {
    expect(versionsDiffer(admin, { version: '1.0.0', codename: 'Kootenai' })).toBe(true);
  });

  it('compares the version only, not the codename', () => {
    expect(versionsDiffer(admin, { version: '0.1.0', codename: 'Kootenai' })).toBe(false);
  });

  it('is false before the API has said its version', () => {
    expect(versionsDiffer(admin, null)).toBe(false);
  });
});
