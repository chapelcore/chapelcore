// api-client.ts — calls the ChapelCore API and turns every reply or failure into a result.
// Why: phase-1.md §6.4: the admin and site never handle a thrown error or a raw response.
// Each call answers `ok` (with data and meta, or for a report just its reference code), or
// an error with a kind, code, message, fields and reference code, so every screen can show
// "can't reach the API" or a reference code.
// How it connects: the admin (1.7) calls getHealth(), and both apps send their browser
// errors with reportClientError() (the report comes from client-error-report.ts).
// Paths are relative to {apiBase} (DOMAIN-MODEL §4).
import type { ClientErrorReport, HealthStatus, RefCode, ResponseMeta } from './types.js';

export const DEFAULT_TIMEOUT_MS = 10_000;

export type FetchErrorKind = 'api' | 'network' | 'timeout' | 'invalid-response';

export interface FetchError {
  readonly kind: FetchErrorKind;
  /** The HTTP status, or `null` when no reply arrived (`network`, `timeout`). */
  readonly status: number | null;
  /** The API's error code (`api` only); codes it doesn't know yet are kept. */
  readonly code: string | null;
  readonly message: string;
  /** Messages per field (`{}` unless the API sent some). */
  readonly fields: { readonly [field: string]: readonly string[] };
  /** The reference code people quote to us, when the reply had one. */
  readonly requestId: RefCode | null;
}

export type FetchResult<T> =
  | { readonly ok: true; readonly data: T; readonly meta: ResponseMeta }
  | { readonly ok: false; readonly error: FetchError };

/** The answer to a request whose success has no body (202 Accepted). */
export type SendResult =
  | { readonly ok: true; readonly requestId: RefCode | null }
  | { readonly ok: false; readonly error: FetchError };

export interface ApiClientOptions {
  /** `{apiBase}` as an absolute URL, e.g. `https://api.libbynaz.org` or `https://example.org/api`. */
  readonly baseUrl: string;
  readonly timeoutMs?: number;
  /** For tests and special runtimes; the browser's fetch by default. */
  readonly fetch?: typeof fetch;
}

const MESSAGES = {
  network: 'Could not reach the server.',
  timeout: 'The server took too long to answer.',
  'invalid-response': 'The server sent a reply that could not be read.',
} as const;

/** A reference code: 8 Crockford Base32 characters written XXXX-XXXX (DOMAIN-MODEL §2). */
const REF_CODE = /^[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$/;

export class ApiClient {
  readonly #baseUrl: string;
  readonly #timeoutMs: number;
  readonly #fetch: typeof fetch;

  constructor(options: ApiClientOptions) {
    this.#baseUrl = options.baseUrl.replace(/\/+$/, '');
    this.#timeoutMs = options.timeoutMs ?? DEFAULT_TIMEOUT_MS;
    // A wrapper, not `fetch` itself: browsers throw "Illegal invocation" when fetch is
    // called as a method of another object.
    this.#fetch = options.fetch ?? ((input, init) => fetch(input, init));
  }

  /** GET {apiBase}/health. A 503 is data: the API is reporting `down` (E3). */
  getHealth(): Promise<FetchResult<HealthStatus>> {
    return this.#get<HealthStatus>('/health', [200, 503]);
  }

  /**
   * POST {apiBase}/diagnostics/client-errors: 202 with no body. `keepalive`, so a report
   * sent as the page unloads still arrives. No cookies: the endpoint is public.
   */
  async reportClientError(report: ClientErrorReport): Promise<SendResult> {
    const reply = await this.#send('/diagnostics/client-errors', {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(report),
      credentials: 'omit',
      keepalive: true,
    });
    if (!isReply(reply)) {
      return { ok: false, error: reply };
    }
    if (reply.status === 202) {
      return { ok: true, requestId: reply.requestId };
    }
    return { ok: false, error: errorIn(reply) ?? failure('invalid-response', reply) };
  }

  /** Sends one GET and reads the reply. `dataStatuses` are the statuses that carry data. */
  async #get<T>(path: string, dataStatuses: readonly number[]): Promise<FetchResult<T>> {
    const reply = await this.#send(path, {
      method: 'GET',
      headers: { Accept: 'application/json' },
      credentials: 'include',
    });
    if (!isReply(reply)) {
      return { ok: false, error: reply };
    }
    // An error envelope wins at any status (a 503 in maintenance mode is an error, not data).
    const error = errorIn(reply);
    if (error !== null) {
      return { ok: false, error };
    }
    const { status, body, meta } = reply;
    if (isObject(body) && meta !== null && 'data' in body && dataStatuses.includes(status)) {
      return { ok: true, data: body.data as T, meta: meta as ResponseMeta };
    }
    return { ok: false, error: failure('invalid-response', reply) };
  }

  /**
   * Sends one request and reads the whole reply, within the timeout. It never throws: no
   * reply at all is a `network` or `timeout` FetchError.
   */
  async #send(path: string, init: RequestInit): Promise<Reply | FetchError> {
    let response: Response;
    let text: string;
    try {
      response = await this.#fetch(`${this.#baseUrl}${path}`, {
        ...init,
        signal: AbortSignal.timeout(this.#timeoutMs),
      });
      // Inside the try, so a reply that stalls or breaks halfway is caught too.
      text = await response.text();
    } catch (error) {
      return failure(isTimeout(error) ? 'timeout' : 'network', { status: null, requestId: null });
    }
    const body = parseJson(text);
    const meta = isObject(body) && isObject(body.meta) ? body.meta : null;
    // The body's reference code first, then the header's; only a valid code counts.
    const requestId = refCode(meta?.requestId) ?? refCode(response.headers.get('X-Request-Id'));
    return { status: response.status, body, meta, requestId };
  }
}

/** A reply as read: the status, the parsed body (`undefined` when it isn't JSON), its meta. */
interface Reply {
  readonly status: number;
  readonly body: unknown;
  readonly meta: Record<string, unknown> | null;
  readonly requestId: RefCode | null;
}

function isReply(value: Reply | FetchError): value is Reply {
  return !('kind' in value);
}

/**
 * The `api` error when the body is an error envelope, an `invalid-response` error when its
 * `error` object isn't well formed or it has no meta, and null when it has no `error` object.
 */
function errorIn(reply: Reply): FetchError | null {
  const { status, body, meta, requestId } = reply;
  if (!isObject(body) || !isObject(body.error)) {
    return null;
  }
  if (meta !== null) {
    const { code, message, fields } = body.error;
    if (typeof code === 'string' && typeof message === 'string' && isFieldMessages(fields)) {
      return { kind: 'api', status, code, message, fields, requestId };
    }
  }
  return failure('invalid-response', reply);
}

function failure(
  kind: Exclude<FetchErrorKind, 'api'>,
  { status, requestId }: { readonly status: number | null; readonly requestId: RefCode | null },
): FetchError {
  return { kind, status, code: null, message: MESSAGES[kind], fields: {}, requestId };
}

/** AbortSignal.timeout() rejects with an error named TimeoutError (a DOMException). */
function isTimeout(error: unknown): boolean {
  return isObject(error) && error.name === 'TimeoutError';
}

function parseJson(text: string): unknown {
  try {
    return JSON.parse(text) as unknown;
  } catch {
    return undefined;
  }
}

function isObject(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** `{ field: ["message", …] }`: an object whose every value is a list of strings. */
function isFieldMessages(value: unknown): value is FetchError['fields'] {
  return (
    isObject(value) &&
    Object.values(value).every(
      (messages) => Array.isArray(messages) && messages.every((item) => typeof item === 'string'),
    )
  );
}

function refCode(value: unknown): RefCode | null {
  return typeof value === 'string' && REF_CODE.test(value) ? value : null;
}
