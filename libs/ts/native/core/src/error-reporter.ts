// error-reporter.ts — reports a page's unexpected errors to the API: at most 5, each once.
// Why: phase-1.md §6.4 (iteration 1.7, E4): the limit, the duplicate rule and "never report
// a failed report" are rules both apps share, so they live in the core, not in Nuxt.
// How it connects: each app's client plugin (checkpoint 4) calls createErrorReporter() with
// the page's window, the API client and what only the page knows (path, user agent, clock),
// and passes Vue's errors to report(). Reports are built by clientErrorReport() and sent by
// ApiClient.reportClientError(). It uses no browser globals, so it runs and is tested in Node.
import type { ApiClient } from './api-client.js';
import { clientErrorReport } from './client-error-report.js';
import type { ClientErrorReport, RefCode } from './types.js';

/** Reports sent per page load. The API takes 10 a minute from one address (DOMAIN-MODEL §5.8). */
export const REPORTS_PER_PAGE = 5;

/** What became of one error: sent (with its reference code), not sent, or over the limit. */
export type ReportOutcome =
  | { readonly status: 'sent'; readonly requestId: RefCode | null }
  | { readonly status: 'not-sent' }
  | { readonly status: 'limit' };

/** Where `error` and `unhandledrejection` events come from: the page's `window`. */
export type ReporterTarget = Pick<EventTarget, 'addEventListener' | 'removeEventListener'>;

export interface ErrorReporterOptions {
  readonly client: Pick<ApiClient, 'reportClientError'>;
  readonly app: ClientErrorReport['app'];
  /** From version.json. */
  readonly appVersion: string;
  readonly target: ReporterTarget;
  /** The current time. */
  readonly now: () => Date;
  /** The current `location.pathname`, read when each error happens. */
  readonly path: () => string;
  /** `navigator.userAgent`. */
  readonly userAgent: string;
  /**
   * Told what became of each new error, and once that the limit is reached. Not told about
   * a repeat or a later error over the limit, so a callback that itself fails can't loop.
   * What it returns is ignored; a promise it returns may reject.
   */
  readonly onReport?: ((outcome: ReportOutcome) => unknown) | undefined;
}

export interface ErrorReporter {
  /** Reports an error caught elsewhere (Vue's hooks). Never rejects. */
  report(error: unknown): Promise<ReportOutcome>;
  /** Stops listening to the target's events. */
  stop(): void;
}

/** For a promise whose failure is deliberately dropped. */
const ignore = (): void => undefined;

const NOT_SENT: ReportOutcome = { status: 'not-sent' };
const LIMIT: ReportOutcome = { status: 'limit' };

/** An outcome, and whether the callback is told about it. */
type Result = readonly [outcome: ReportOutcome, tell: boolean];

export function createErrorReporter(options: ErrorReporterOptions): ErrorReporter {
  /** Each sent error's outcome, by its message and first stack line (both as redacted). */
  const seen = new Map<string, Promise<ReportOutcome>>();
  let sent = 0;
  let limitTold = false;

  async function resultOf(error: unknown): Promise<Result> {
    try {
      const report = clientErrorReport(error, {
        app: options.app,
        appVersion: options.appVersion,
        path: options.path(),
        userAgent: options.userAgent,
        occurredAt: options.now(),
      });
      if (report === null) {
        return [NOT_SENT, true];
      }
      // A stack line never contains a line break, so this key can't be ambiguous.
      const key = `${report.message}\n${report.stack[0] ?? ''}`;
      const earlier = seen.get(key);
      if (earlier !== undefined) {
        return [await earlier, false];
      }
      if (sent >= REPORTS_PER_PAGE) {
        const tell = !limitTold;
        limitTold = true;
        return [LIMIT, tell];
      }
      // Counted and remembered before the first await, so errors that arrive together
      // can't pass the limit or send the same error twice.
      sent++;
      const sending = send(report);
      seen.set(key, sending);
      return [await sending, true];
    } catch {
      return [NOT_SENT, true];
    }
  }

  async function send(report: ClientErrorReport): Promise<ReportOutcome> {
    try {
      const result = await options.client.reportClientError(report);
      return result.ok ? { status: 'sent', requestId: result.requestId } : NOT_SENT;
    } catch {
      return NOT_SENT;
    }
  }

  async function report(error: unknown): Promise<ReportOutcome> {
    const [outcome, tell] = await resultOf(error);
    if (tell) {
      try {
        const returned: unknown = options.onReport?.(outcome);
        if (returned instanceof Promise) {
          returned.catch(ignore);
        }
      } catch {
        // The caller's callback broke; reporting that could loop, so it is dropped.
      }
    }
    return outcome;
  }

  // Events are read by their shape: Node and some test setups have no ErrorEvent.
  const onError = (event: Event): void => {
    const { error, message } = event as Partial<Record<'error' | 'message', unknown>>;
    void report(error ?? message);
  };
  const onRejection = (event: Event): void => {
    void report((event as Partial<Record<'reason', unknown>>).reason);
  };

  options.target.addEventListener('error', onError);
  options.target.addEventListener('unhandledrejection', onRejection);

  return {
    report,
    stop() {
      options.target.removeEventListener('error', onError);
      options.target.removeEventListener('unhandledrejection', onRejection);
    },
  };
}
