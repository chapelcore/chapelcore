// error-reporter.test.ts — createErrorReporter() reports a page's errors, at most 5, each once.
// Why: phase-1.md §6.4 and §7 (iteration 1.7, E4): both apps share these rules. A thrown
// error and an unhandled rejection are reported; a sixth report on one page isn't sent; the
// same error twice is sent once and the repeat answers with the first one's reference code;
// a failed send isn't itself reported; nothing throws, even when the caller's callback does.
// How it connects: tests src/error-reporter.ts through the package's entry point, with a
// real ApiClient and the real fetch against a local server (tests/test-server.ts). The page
// is a plain EventTarget and its events are plain Events with the browser's fields added,
// since Node has no window and no ErrorEvent. Every report that arrives is checked against
// the contract's client-error-report schema with ajv (tests/support.ts).
import type { Ajv2020 } from 'ajv/dist/2020.js';
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  ApiClient,
  type ClientErrorReport,
  createErrorReporter,
  type ErrorReporter,
  type ErrorReporterOptions,
  REPORTS_PER_PAGE,
  type ReportOutcome,
} from '../src/index.js';
import { createValidator, readContractJson, schemaId } from './support.js';
import { closedOrigin, startTestServer, type TestServer } from './test-server.js';

/** Valid reference codes from the contract, so each reply can carry a different one. */
const codes = readContractJson('fixtures/valid/ref-code').map(({ data }) => data as string);
const code = (index: number): string => codes[index] ?? '';

const NOW = new Date('2026-11-21T09:30:00.000Z');

let ajv: Ajv2020;
let server: TestServer;
let page: EventTarget;
let outcomes: ReportOutcome[];
/** Every report handed to the client, recorded as it is handed over (no network wait). */
let handed: ClientErrorReport[];
let reporter: ErrorReporter;

beforeAll(() => {
  ajv = createValidator();
  // At least two different codes, for the duplicate tests.
  expect(new Set(codes).size).toBeGreaterThanOrEqual(2);
});

beforeEach(async () => {
  server = await startTestServer();
  server.reply = accepted(code(0));
  page = new EventTarget();
  outcomes = [];
  handed = [];
  reporter = createErrorReporter(options());
});

afterEach(async () => {
  reporter.stop();
  // Every report sent in every test is valid (§7).
  received();
  vi.restoreAllMocks();
  await server.close();
});

/** A real client for the test server that also records each report it is given. */
function recordingClient(baseUrl: string): ErrorReporterOptions['client'] {
  const client = new ApiClient({ baseUrl });
  return {
    reportClientError: (report) => {
      handed.push(report);
      return client.reportClientError(report);
    },
  };
}

/** The options every test starts from; `overrides` replaces some of them. */
function options(overrides: Partial<ErrorReporterOptions> = {}): ErrorReporterOptions {
  return {
    client: recordingClient(server.origin),
    app: 'admin',
    appVersion: '0.1.0',
    target: page,
    now: () => NOW,
    path: () => '/admin/',
    userAgent: 'Mozilla/5.0 (test)',
    onReport: (outcome) => {
      outcomes.push(outcome);
    },
    ...overrides,
  };
}

/** The API's answer to a report: 202, no body, the reference code in the header. */
function accepted(requestId: string): TestServer['reply'] {
  return { status: 202, headers: { 'X-Request-Id': requestId }, body: '' };
}

/** What the browser dispatches for an uncaught error: `error` and `message` on the event. */
function errorEvent(error: unknown, message = ''): Event {
  return Object.assign(new Event('error'), { error, message });
}

/** What the browser dispatches for a promise rejected with nothing to catch it. */
function rejectionEvent(reason: unknown): Event {
  return Object.assign(new Event('unhandledrejection'), { reason });
}

/** An Error with exactly this stack text. */
function errorWithStack(message: string, stack: string): Error {
  const error = new Error(message);
  error.stack = stack;
  return error;
}

/** Waits until the callback has had `count` outcomes. */
async function untilOutcomes(count: number): Promise<void> {
  await vi.waitFor(() => {
    expect(outcomes).toHaveLength(count);
  });
}

/**
 * Lets everything already queued run (promise callbacks and event-loop turns), so a report
 * that was wrongly started would have been handed to the client by now.
 */
async function settle(): Promise<void> {
  for (let n = 0; n < 3; n++) {
    await new Promise((resolve) => setImmediate(resolve));
  }
}

/** Every report that reached the server, parsed, each checked against the schema. */
function received(): Record<string, unknown>[] {
  return server.requests.map(({ body }) => {
    const report = JSON.parse(body) as Record<string, unknown>;
    ajv.validate(schemaId('client-error-report'), report);
    expect(ajv.errors ?? [], 'schema errors').toEqual([]);
    return report;
  });
}

describe('createErrorReporter', () => {
  it('allows 5 reports per page', () => {
    // The API takes 10 a minute from one address (DOMAIN-MODEL §5.8); a page sends half.
    expect(REPORTS_PER_PAGE).toBe(5);
  });

  describe('what it reports', () => {
    it("reports an error thrown on the page, with the caller's app, version, path, user agent and time", async () => {
      page.dispatchEvent(errorEvent(new TypeError('x is undefined'), 'Uncaught TypeError'));
      await untilOutcomes(1);
      expect(outcomes).toEqual([{ status: 'sent', requestId: code(0) }]);
      expect(server.requests[0]?.url).toBe('/diagnostics/client-errors');
      expect(received()).toEqual([
        expect.objectContaining({
          app: 'admin',
          appVersion: '0.1.0',
          path: '/admin/',
          message: 'TypeError: x is undefined',
          userAgent: 'Mozilla/5.0 (test)',
          occurredAt: '2026-11-21T09:30:00.000Z',
        }),
      ]);
    });

    it('reports an unhandled rejection', async () => {
      page.dispatchEvent(rejectionEvent(new RangeError('too far')));
      await untilOutcomes(1);
      expect(outcomes).toEqual([{ status: 'sent', requestId: code(0) }]);
      expect(received()[0]?.message).toBe('RangeError: too far');
    });

    it('reports a rejection whose reason is not an Error', async () => {
      page.dispatchEvent(rejectionEvent('plain text'));
      await untilOutcomes(1);
      expect(received()[0]?.message).toBe('plain text');
    });

    it('uses the message of an error event that has no error object ("Script error.")', async () => {
      // A script from another origin: the browser hides the error and sends only this.
      page.dispatchEvent(errorEvent(null, 'Script error.'));
      await untilOutcomes(1);
      expect(received()[0]?.message).toBe('Script error.');
    });

    it('still reports an error event that has neither field, as "undefined"', async () => {
      // What was thrown is reported as it is: String(undefined).
      page.dispatchEvent(new Event('error'));
      await untilOutcomes(1);
      expect(outcomes).toEqual([{ status: 'sent', requestId: code(0) }]);
      expect(received()[0]?.message).toBe('undefined');
    });

    it('reports an error passed to report(), and answers with the outcome', async () => {
      await expect(reporter.report(new Error('from Vue'))).resolves.toEqual({
        status: 'sent',
        requestId: code(0),
      });
      expect(outcomes).toEqual([{ status: 'sent', requestId: code(0) }]);
      expect(received()[0]?.message).toBe('Error: from Vue');
    });

    it('reads the path and the time when each error happens', async () => {
      let path = '/admin/';
      let now = new Date('2026-11-21T10:00:00.000Z');
      reporter.stop();
      reporter = createErrorReporter(options({ path: () => path, now: () => now }));
      await reporter.report(new Error('first'));
      path = '/admin/people';
      now = new Date('2026-11-21T10:05:00.000Z');
      await reporter.report(new Error('second'));
      expect(received().map(({ path, occurredAt }) => [path, occurredAt])).toEqual([
        ['/admin/', '2026-11-21T10:00:00.000Z'],
        ['/admin/people', '2026-11-21T10:05:00.000Z'],
      ]);
    });

    it('answers with a null reference code when the API sends none', async () => {
      server.reply = { status: 202, body: '' };
      await expect(reporter.report(new Error('no code'))).resolves.toEqual({
        status: 'sent',
        requestId: null,
      });
      expect(outcomes).toEqual([{ status: 'sent', requestId: null }]);
    });
  });

  describe('the limit', () => {
    it('sends 5 reports per page, and answers the sixth with the limit', async () => {
      for (let n = 1; n <= REPORTS_PER_PAGE; n++) {
        await expect(reporter.report(new Error(`Test error ${String(n)}`))).resolves.toEqual({
          status: 'sent',
          requestId: code(0),
        });
      }
      await expect(reporter.report(new Error('Test error 6'))).resolves.toEqual({
        status: 'limit',
      });
      expect(server.requests).toHaveLength(REPORTS_PER_PAGE);
      expect(outcomes.at(-1)).toEqual({ status: 'limit' });
    });

    it('sends only 5 when more errors arrive at once', async () => {
      // A render loop can throw several different errors before any send has finished.
      for (let n = 1; n <= REPORTS_PER_PAGE + 2; n++) {
        page.dispatchEvent(errorEvent(new Error(`burst ${String(n)}`)));
      }
      await untilOutcomes(REPORTS_PER_PAGE + 1);
      await settle();
      expect(handed).toHaveLength(REPORTS_PER_PAGE);
      expect(outcomes.filter(({ status }) => status === 'sent')).toHaveLength(REPORTS_PER_PAGE);
      expect(outcomes.filter(({ status }) => status === 'limit')).toHaveLength(1);
    });

    it('tells the callback about the limit once', async () => {
      for (let n = 1; n <= REPORTS_PER_PAGE + 3; n++) {
        await reporter.report(new Error(`Test error ${String(n)}`));
      }
      await expect(reporter.report(new Error('one more'))).resolves.toEqual({ status: 'limit' });
      expect(outcomes).toHaveLength(REPORTS_PER_PAGE + 1);
      expect(outcomes.at(-1)).toEqual({ status: 'limit' });
    });

    it('counts errors from the page and from report() together', async () => {
      for (let n = 1; n <= 3; n++) {
        page.dispatchEvent(errorEvent(new Error(`page ${String(n)}`)));
      }
      await untilOutcomes(3);
      await reporter.report(new Error('vue 1'));
      await reporter.report(new Error('vue 2'));
      await expect(reporter.report(new Error('vue 3'))).resolves.toEqual({ status: 'limit' });
      expect(server.requests).toHaveLength(5);
    });

    it('counts a report that failed to send', async () => {
      server.reply = { status: 500, body: '' };
      for (let n = 1; n <= REPORTS_PER_PAGE; n++) {
        await reporter.report(new Error(`failing ${String(n)}`));
      }
      server.reply = accepted(code(0));
      await expect(reporter.report(new Error('after'))).resolves.toEqual({ status: 'limit' });
      expect(server.requests).toHaveLength(REPORTS_PER_PAGE);
    });

    it('does not count a report that could not be built', async () => {
      // clientErrorReport() returns null for a path that doesn't start with "/".
      let path = 'no slash';
      reporter.stop();
      reporter = createErrorReporter(options({ path: () => path }));
      for (let n = 1; n <= REPORTS_PER_PAGE; n++) {
        await expect(reporter.report(new Error(`unbuildable ${String(n)}`))).resolves.toEqual({
          status: 'not-sent',
        });
      }
      path = '/admin/';
      await expect(reporter.report(new Error('buildable'))).resolves.toEqual({
        status: 'sent',
        requestId: code(0),
      });
      expect(server.requests).toHaveLength(1);
    });
  });

  describe('the same error twice', () => {
    it('sends it once; the repeat answers with the first reference code', async () => {
      const error = new Error('again');
      await reporter.report(error);
      server.reply = accepted(code(1));
      await expect(reporter.report(error)).resolves.toEqual({
        status: 'sent',
        requestId: code(0),
      });
      expect(server.requests).toHaveLength(1);
    });

    it('does not tell the callback about a repeat', async () => {
      // So a notice that fails while showing an error can't start a loop.
      const error = new Error('again');
      await reporter.report(error);
      await reporter.report(error);
      page.dispatchEvent(errorEvent(error));
      await reporter.report(error);
      expect(outcomes).toEqual([{ status: 'sent', requestId: code(0) }]);
    });

    it('treats a new Error with the same message and first stack line as the same error', async () => {
      // A render loop throws a new Error object each time, from the same place.
      await reporter.report(
        errorWithStack('loop', 'Error: loop\n    at render (app.js:1:2)\n    at a'),
      );
      await reporter.report(
        errorWithStack('loop', 'Error: loop\n    at render (app.js:1:2)\n    at b'),
      );
      expect(server.requests).toHaveLength(1);
    });

    it('sends the same message from another place as another error', async () => {
      await reporter.report(errorWithStack('boom', 'Error: boom\n    at f (app.js:1:2)'));
      await reporter.report(errorWithStack('boom', 'Error: boom\n    at g (app.js:3:4)'));
      expect(server.requests).toHaveLength(2);
    });

    it('sends it once even when the repeat comes before the first send has finished', async () => {
      const error = new Error('quick');
      page.dispatchEvent(errorEvent(error));
      page.dispatchEvent(errorEvent(error));
      await expect(reporter.report(error)).resolves.toEqual({
        status: 'sent',
        requestId: code(0),
      });
      expect(handed).toHaveLength(1);
      expect(outcomes).toEqual([{ status: 'sent', requestId: code(0) }]);
    });

    it('does not count a repeat against the limit', async () => {
      const error = new Error('repeated');
      for (let n = 0; n < 10; n++) {
        await reporter.report(error);
      }
      for (let n = 2; n <= REPORTS_PER_PAGE; n++) {
        await reporter.report(new Error(`other ${String(n)}`));
      }
      expect(server.requests).toHaveLength(REPORTS_PER_PAGE);
    });

    it('answers a repeat over the limit with its first outcome', async () => {
      const first = new Error('early');
      await reporter.report(first);
      for (let n = 2; n <= REPORTS_PER_PAGE + 1; n++) {
        await reporter.report(new Error(`filler ${String(n)}`));
      }
      await expect(reporter.report(first)).resolves.toEqual({
        status: 'sent',
        requestId: code(0),
      });
    });

    it('does not send a repeat of an error whose report failed', async () => {
      server.reply = { status: 500, body: '' };
      const error = new Error('failed once');
      await expect(reporter.report(error)).resolves.toEqual({ status: 'not-sent' });
      server.reply = accepted(code(0));
      await expect(reporter.report(error)).resolves.toEqual({ status: 'not-sent' });
      expect(server.requests).toHaveLength(1);
    });
  });

  describe('when sending fails', () => {
    it('answers not-sent, and does not report the failure', async () => {
      server.reply = { status: 500, body: '<h1>Server Error</h1>' };
      page.dispatchEvent(errorEvent(new Error('first')));
      await untilOutcomes(1);
      await settle();
      expect(outcomes).toEqual([{ status: 'not-sent' }]);
      expect(handed).toHaveLength(1);
    });

    it('answers not-sent when the API cannot be reached', async () => {
      reporter.stop();
      reporter = createErrorReporter(
        options({ client: new ApiClient({ baseUrl: await closedOrigin() }) }),
      );
      await expect(reporter.report(new Error('offline'))).resolves.toEqual({
        status: 'not-sent',
      });
    });

    it.each([
      [
        'throws',
        () => {
          throw new Error('client broke');
        },
      ],
      ['rejects', () => Promise.reject(new Error('client broke'))],
    ])('answers not-sent, and does not throw, when the client %s', async (_, reportClientError) => {
      reporter.stop();
      reporter = createErrorReporter(options({ client: { reportClientError } }));
      await expect(reporter.report(new Error('x'))).resolves.toEqual({ status: 'not-sent' });
      expect(outcomes).toEqual([{ status: 'not-sent' }]);
    });

    it('answers not-sent, and does not throw, when the path or the clock throws', async () => {
      reporter.stop();
      reporter = createErrorReporter(
        options({
          path: () => {
            throw new Error('no path');
          },
        }),
      );
      await expect(reporter.report(new Error('x'))).resolves.toEqual({ status: 'not-sent' });
      reporter.stop();
      reporter = createErrorReporter(
        options({
          now: () => {
            throw new Error('no clock');
          },
        }),
      );
      await expect(reporter.report(new Error('y'))).resolves.toEqual({ status: 'not-sent' });
      expect(server.requests).toHaveLength(0);
    });
  });

  describe('the callback', () => {
    it('is optional', async () => {
      reporter.stop();
      reporter = createErrorReporter(options({ onReport: undefined }));
      await expect(reporter.report(new Error('quiet'))).resolves.toEqual({
        status: 'sent',
        requestId: code(0),
      });
    });

    it.each([
      [
        'throws',
        (outcome: ReportOutcome) => {
          outcomes.push(outcome);
          throw new Error('callback broke');
        },
      ],
      [
        'returns a promise that rejects',
        async (outcome: ReportOutcome) => {
          outcomes.push(outcome);
          await Promise.resolve();
          throw new Error('callback broke');
        },
      ],
    ])(
      'can be one that %s, without breaking the reporter or an unhandled rejection',
      async (_, onReport) => {
        reporter.stop();
        const unhandled = vi.fn();
        process.on('unhandledRejection', unhandled);
        try {
          reporter = createErrorReporter(options({ onReport }));
          await expect(reporter.report(new Error('first'))).resolves.toEqual({
            status: 'sent',
            requestId: code(0),
          });
          page.dispatchEvent(errorEvent(new Error('second')));
          await untilOutcomes(2);
          await settle();
          expect(handed).toHaveLength(2);
          expect(unhandled).not.toHaveBeenCalled();
        } finally {
          process.off('unhandledRejection', unhandled);
        }
      },
    );
  });

  describe('stop()', () => {
    it('stops listening for errors and rejections on the page', async () => {
      reporter.stop();
      page.dispatchEvent(errorEvent(new Error('after stop')));
      page.dispatchEvent(rejectionEvent(new Error('after stop')));
      await settle();
      expect(handed).toHaveLength(0);
      expect(outcomes).toEqual([]);
    });
  });
});
