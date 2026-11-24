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
  /** What went wrong, or what to check; `null` when the API answered. */
  readonly detail: string | null;
  readonly requestId: RefCode | null;
  readonly checks: readonly CheckLine[];
  readonly apiVersion: VersionInfo | null;
}

// Checkpoint 3: you type the rest of this file from the worked example.
export const CHECKING = {} as HealthView;

export function healthView(_result: FetchResult<HealthStatus>): HealthView {
  throw new Error('healthView() is not written yet (iteration 1.7, checkpoint 3)');
}

export function versionsDiffer(_admin: VersionInfo, _api: VersionInfo | null): boolean {
  throw new Error('versionsDiffer() is not written yet (iteration 1.7, checkpoint 3)');
}
