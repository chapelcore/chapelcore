// health-view.ts — what the admin's status page says for each answer from the API.
// Why: phase-1.md §6.5 and E3: the wording is text for this one page, kept in a plain
// function with no Vue imports, so every state is tested without mounting anything. Status is
// never shown by color alone: words, with a mark (✓ ! ✕) that the page hides from screen
// readers; a status this admin doesn't know is shown neutrally (DOMAIN-MODEL universal
// rule 5), and the checks keep the API's order (rule 7).
// How it connects: app.vue shows CHECKING, then healthView() of what getHealth() returned,
// and asks versionsDiffer() whether to show the "different versions" note. Tested by
// test/unit/health-view.test.ts. Plain TypeScript: no Nuxt auto-imports, since the unit
// tests run it in Node.
import type { FetchResult, HealthStatus, RefCode, VersionInfo } from '@chapelcore/core';

/** Which of the page's states the view is in. */
export type HealthState =
  'checking' | 'ok' | 'degraded' | 'down' | 'unknown' | 'unreachable' | 'error';

/** The mark shown beside a status, hidden from screen readers (the words say it). */
export type Mark = '✓' | '!' | '✕';

/** One check as the page lists it. */
export interface CheckLine {
  readonly name: string;
  /** "OK", "Warning", "Down" or "Unknown status". */
  readonly status: string;
  readonly mark: Mark | null;
  readonly message: string | null;
}

/** Everything the page shows about the API's health. */
export interface HealthView {
  readonly state: HealthState;
  readonly mark: Mark | null;
  /** The one line announced to screen readers. */
  readonly summary: string;
  /** What went wrong, or what to check; `null` when the API sent a health report. */
  readonly detail: string | null;
  readonly requestId: RefCode | null;
  readonly checks: readonly CheckLine[];
  readonly apiVersion: VersionInfo | null;
}

/** What the page shows before the API has answered. */
export const CHECKING: HealthView = {
  state: 'checking',
  mark: null,
  summary: 'Checking the API…',
  detail: null,
  requestId: null,
  checks: [],
  apiVersion: null,
};

const ADVICE = 'Check that the API is running and that the API address below is right.';
const UNREADABLE = 'The server sent a reply that could not be read.';
const UNKNOWN = 'Unknown status';

// The words for each status this admin knows. A Map, not an object, so a status such as
// "constructor" is not found by accident.
const OVERALL = new Map<string, { state: HealthState; mark: Mark; summary: string }>([
  ['ok', { state: 'ok', mark: '✓', summary: 'All systems OK' }],
  ['degraded', { state: 'degraded', mark: '!', summary: 'Working, with warnings' }],
  ['down', { state: 'down', mark: '✕', summary: 'The API reports a problem' }],
]);

const CHECK = new Map<string, { status: string; mark: Mark }>([
  ['ok', { status: 'OK', mark: '✓' }],
  ['degraded', { status: 'Warning', mark: '!' }],
  ['down', { status: 'Down', mark: '✕' }],
]);

function isObject(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isTextOrNull(value: unknown): value is string | null {
  return typeof value === 'string' || value === null;
}

// The client checks the envelope, not what is inside `data`, so check what this page reads.
function isHealthStatus(data: unknown): data is HealthStatus {
  if (!isObject(data) || typeof data.status !== 'string' || !Array.isArray(data.checks)) {
    return false;
  }
  const version = data.version;
  if (
    !isObject(version) ||
    typeof version.version !== 'string' ||
    !isTextOrNull(version.codename)
  ) {
    return false;
  }
  return data.checks.every(
    (check: unknown) =>
      isObject(check) &&
      typeof check.name === 'string' &&
      typeof check.status === 'string' &&
      isTextOrNull(check.message),
  );
}

// CHECKING only supplies the empty fields; every field that is shown is overridden.
function errorView(detail: string, requestId: RefCode | null): HealthView {
  return {
    ...CHECKING,
    state: 'error',
    mark: '✕',
    summary: 'The API sent an error',
    detail,
    requestId,
  };
}

/** What the page says for one answer from `getHealth()`. */
export function healthView(result: FetchResult<HealthStatus>): HealthView {
  if (!result.ok) {
    const { kind, message, requestId } = result.error;
    if (kind === 'network' || kind === 'timeout') {
      return {
        ...CHECKING,
        state: 'unreachable',
        mark: '✕',
        summary: "Can't reach the API",
        detail: `${message} ${ADVICE}`,
        requestId,
      };
    }
    return errorView(message, requestId);
  }

  // `data` is typed as a HealthStatus, but nothing has checked it yet.
  const data: unknown = result.data;
  if (!isHealthStatus(data)) {
    return errorView(UNREADABLE, result.meta.requestId);
  }

  const overall = OVERALL.get(data.status);
  return {
    ...CHECKING,
    state: overall?.state ?? 'unknown',
    mark: overall?.mark ?? null,
    summary: overall?.summary ?? UNKNOWN,
    checks: data.checks.map((check) => {
      const known = CHECK.get(check.status);
      return {
        name: check.name,
        status: known?.status ?? UNKNOWN,
        mark: known?.mark ?? null,
        message: check.message,
      };
    }),
    apiVersion: {
      version: data.version.version,
      codename: data.version.codename,
    },
  };
}

/** True when the API has said its version and it is not this admin's. */
export function versionsDiffer(admin: VersionInfo, api: VersionInfo | null): boolean {
  return api !== null && api.version !== admin.version;
}
