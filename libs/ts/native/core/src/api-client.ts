// api-client.ts — calls the ChapelCore API and turns every reply or failure into a FetchResult.
// Why: phase-1.md §6.4: the admin and site never handle a thrown error or a raw response.
// Each call answers `ok` with data and meta, or an error with a kind, code, message, fields
// and reference code, so every screen can show "can't reach the API" or a reference code.
// How it connects: the admin (1.7) calls getHealth(); checkpoint 5 adds reportClientError().
// Paths are relative to {apiBase} (DOMAIN-MODEL §4).
import type { HealthStatus, RefCode, ResponseMeta } from './types.js';

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
   * Sends one GET and reads the reply. It never throws: every outcome is a FetchResult.
   * `dataStatuses` are the HTTP statuses on which this endpoint sends data.
   */
  async #get<T>(path: string, dataStatuses: readonly number[]): Promise<FetchResult<T>> {
    let response: Response;
    let text: string;
    try {
      response = await this.#fetch(`${this.#baseUrl}${path}`, {
        method: 'GET',
        headers: { Accept: 'application/json' },
        credentials: 'include',
        signal: AbortSignal.timeout(this.#timeoutMs),
      });
      // Inside the try, so a reply that stalls or breaks halfway is caught too.
      text = await response.text();
    } catch (error) {
      return failure(isTimeout(error) ? 'timeout' : 'network', null, null);
    }

    const { status } = response;
    const body = parseJson(text);
    const meta = isObject(body) && isObject(body.meta) ? body.meta : null;
    // The body's reference code first, then the header's; only a valid code counts.
    const requestId = refCode(meta?.requestId) ?? refCode(response.headers.get('X-Request-Id'));

    if (!isObject(body) || meta === null) {
      return failure('invalid-response', status, requestId);
    }
    // An error envelope wins at any status (a 503 in maintenance mode is an error, not data).
    if (isObject(body.error)) {
      const { code, message, fields } = body.error;
      if (typeof code === 'string' && typeof message === 'string' && isFieldMessages(fields)) {
        return { ok: false, error: { kind: 'api', status, code, message, fields, requestId } };
      }
      return failure('invalid-response', status, requestId);
    }
    if ('data' in body && dataStatuses.includes(status)) {
      return { ok: true, data: body.data as T, meta: meta as ResponseMeta };
    }
    return failure('invalid-response', status, requestId);
  }
}

function failure(
  kind: Exclude<FetchErrorKind, 'api'>,
  status: number | null,
  requestId: RefCode | null,
): FetchResult<never> {
  return {
    ok: false,
    error: { kind, status, code: null, message: MESSAGES[kind], fields: {}, requestId },
  };
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
