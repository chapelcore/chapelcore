// client-error-report.ts — turns an error caught in the browser into a report that is safe to send.
// Why: DOMAIN-MODEL §5.8 ClientErrorReport: clients redact, then trim, before sending, so
// personal data doesn't leave the page and the server never has to trim (phase-1.md §6.4,
// E4, E5). A report that can't be made to fit is not sent at all.
// How it connects: the admin and site (1.7) call clientErrorReport() from their error
// handlers with what only they know (app, version, path, user agent, time) and send the
// result with ApiClient.reportClientError(). It uses no browser globals, so it runs and is
// tested in Node. Redaction comes from redact.ts.
import { redactPath, redactText } from './redact.js';
import type { ClientErrorReport } from './types.js';

/** What the caller knows about where and when the error happened. */
export interface ReportSource {
  readonly app: ClientErrorReport['app'];
  /** From version.json. */
  readonly appVersion: string;
  /** `location.pathname`. A query string or fragment is cut off; it must start with `/`. */
  readonly path: string;
  /** `navigator.userAgent`. */
  readonly userAgent: string;
  readonly occurredAt: Date;
}

/** The limits in DOMAIN-MODEL §5.8: texts in characters (code points), the body in UTF-8 bytes. */
export const REPORT_LIMITS = {
  bytes: 8192,
  path: 2048,
  message: 1000,
  stackLines: 50,
  stackLine: 500,
  userAgent: 512,
} as const;

/**
 * Before redacting, each text is cut to this many times its limit. Some redaction
 * patterns take time that grows with the square of the text's length in V8 (about 3 s for
 * 40,000 characters), and the report never keeps more than the limit anyway.
 */
const CAP_FACTOR = 2;

const NO_MESSAGE = '(no message)';
const NO_USER_AGENT = '(unknown)';

const utf8 = new TextEncoder();

/** A report while it is being trimmed: the same fields, changeable. */
type Draft = Omit<{ -readonly [K in keyof ClientErrorReport]: ClientErrorReport[K] }, 'stack'> & {
  stack: string[];
};

/** Checked at run time too, since a plain JavaScript caller isn't type-checked. */
const APPS: readonly string[] = ['admin', 'web'];

/** The official Semantic Versioning 2.0.0 expression, as in the contract's sem-ver schema. */
const SEM_VER =
  /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][a-zA-Z0-9-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][a-zA-Z0-9-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/;

/**
 * The report for `error`, redacted and trimmed in the §5.8 order, or `null` when it can't
 * be made valid: an unknown app, a version that isn't SemVer, a path that doesn't start
 * with `/`, a time that isn't a valid date in the years 0–9999, or a report that doesn't
 * fit in 8 KB. It never throws, whatever the caller passes.
 */
export function clientErrorReport(error: unknown, source: ReportSource): ClientErrorReport | null {
  try {
    return build(error, source);
  } catch {
    return null;
  }
}

function build(error: unknown, source: ReportSource): ClientErrorReport | null {
  const path = wellFormed(source.path).replace(/[?#][\s\S]*$/, '');
  const year = source.occurredAt.getUTCFullYear();
  if (
    !APPS.includes(source.app) ||
    !SEM_VER.test(source.appVersion) ||
    !path.startsWith('/') ||
    !(year >= 0 && year <= 9999)
  ) {
    return null;
  }
  const { message, stack } = describe(error);

  // Redact (rule 2 on every text, the path one segment at a time), then cut every field to
  // its limit: trimming step 1.
  const report: Draft = {
    app: source.app,
    appVersion: source.appVersion,
    path: cut(redactPath(cap(path, REPORT_LIMITS.path, '/')), REPORT_LIMITS.path),
    message: cut(redactText(cap(message, REPORT_LIMITS.message)), REPORT_LIMITS.message),
    stack: stack
      .slice(0, REPORT_LIMITS.stackLines)
      .map((line) => cut(redactText(cap(line, REPORT_LIMITS.stackLine)), REPORT_LIMITS.stackLine)),
    userAgent: cut(
      redactText(cap(wellFormed(source.userAgent) || NO_USER_AGENT, REPORT_LIMITS.userAgent)),
      REPORT_LIMITS.userAgent,
    ),
    occurredAt: source.occurredAt.toISOString(),
  };
  return fit(report);
}

/** The message and stack lines of whatever was thrown, as well-formed text. */
function describe(error: unknown): { message: string; stack: string[] } {
  try {
    // An Error's own text is "Name: message" (Error.prototype.toString), so the type is kept.
    const message = wellFormed(String(error)) || NO_MESSAGE;
    const raw = error instanceof Error && typeof error.stack === 'string' ? error.stack : '';
    // V8 starts the stack with that same text; it is already the message.
    const stack = wellFormed(raw);
    const frames = stack.startsWith(message) ? stack.slice(message.length) : stack;
    // Blank (whitespace-only) lines are dropped: the schema needs every line to have text.
    return { message, stack: frames.split(/\r\n|\n|\r/).filter((line) => line.trim() !== '') };
  } catch {
    // String() throws for an object without a prototype, or whose toString() throws; the
    // report still goes out.
    return { message: NO_MESSAGE, stack: [] };
  }
}

/** Trimming steps 2 to 4: drop stack lines from the end, then shorten the message, else give up. */
function fit(report: Draft): ClientErrorReport | null {
  while (bytes(report) > REPORT_LIMITS.bytes && report.stack.length > 0) {
    report.stack.pop();
  }
  if (bytes(report) <= REPORT_LIMITS.bytes) {
    return report;
  }
  // The longest message (at least 1 character) that fits, found by halving the range.
  const characters = codePoints(report.message);
  let fits = 0;
  let tooLong = characters.length;
  while (tooLong - fits > 1) {
    const length = Math.floor((fits + tooLong) / 2);
    if (
      bytes({ ...report, message: characters.slice(0, length).join('') }) <= REPORT_LIMITS.bytes
    ) {
      fits = length;
    } else {
      tooLong = length;
    }
  }
  return fits === 0 ? null : { ...report, message: characters.slice(0, fits).join('') };
}

/** The size of the body as sent: UTF-8 bytes of the JSON. */
function bytes(report: Draft): number {
  return utf8.encode(JSON.stringify(report)).length;
}

/** The first `limit` characters (code points), so a character is never split. */
function cut(text: string, limit: number): string {
  const characters = codePoints(text);
  return characters.length <= limit ? text : characters.slice(0, limit).join('');
}

/**
 * The text's characters as §5.8 counts them: code points, as JSON Schema's maxLength and
 * PHP's mb_strlen do. (An emoji made of several code points counts as several, there too.)
 */
function codePoints(text: string): string[] {
  return Array.from(text);
}

/**
 * Cut to CAP_FACTOR times the limit, before redacting. When it cuts, it also drops the
 * text after the last whitespace (`/` in a path), so no pattern is cut in half and left
 * unrecognized; a text without one is kept as cut.
 */
function cap(text: string, limit: number, separator = ''): string {
  const capped = cut(text, limit * CAP_FACTOR);
  if (capped === text) {
    return text;
  }
  const end =
    separator === ''
      ? capped.search(/[ \t\n\v\f\r][^ \t\n\v\f\r]*$/)
      : capped.lastIndexOf(separator);
  return end > 0 ? capped.slice(0, end) : capped;
}

/** A lone surrogate becomes U+FFFD, since PHP's JSON parser rejects one. */
function wellFormed(text: string): string {
  return text.toWellFormed();
}
