// redact.ts — removes secrets and personal data from text and from log context.
// Why: DOMAIN-MODEL §5.8 Redaction: one pure function, the same in every language. In the
// browser it cleans a client error report before it's sent, so an email or a token in an
// error message never leaves the page (phase-1.md §6.4, E4).
// How it connects: clientErrorReport() (checkpoint 5) calls redactText(). The PHP twin is
// libs/php/native/core/src/Diagnostics/Redactor.php; the shared cases in
// libs/shared/contract/redaction decide what both must do (tests/redact.test.ts).

/** Plain JSON data: what a log context holds. */
export type JsonValue =
  string | number | boolean | null | readonly JsonValue[] | { readonly [key: string]: JsonValue };

export const REDACTED = '[redacted]';

/** Rule 1: a key is secret when its ASCII-lowercased form, without `_`, `-` and `.`, contains one of these. */
const SECRET_WORDS = [
  'password',
  'passwd',
  'secret',
  'token',
  'apikey',
  'authorization',
  'cookie',
  'session',
  'csrf',
  'prayer',
];

// Whitespace is written [ \t\n\v\f\r], not \s. In JavaScript \s also matches Unicode
// spaces such as the no-break space; in PHP (without the u flag) it doesn't, and both
// languages must give the same result.

/** Rule 2a: `Bearer` and a token (RFC 6750 token characters). */
const BEARER = /\bBearer[ \t\n\v\f\r]+[A-Za-z0-9\-._~+/]+=*/gi;

/** Rule 2b: the password in a URL, `scheme://user:password@` (the user may be empty, as in Redis URLs). */
const URL_PASSWORD = /\b([A-Za-z][A-Za-z0-9+.-]*:\/\/[^ \t\n\v\f\r:/@]*):[^ \t\n\v\f\r/]+@/g;

/** Rule 2c: one query parameter, `?name=value` or `&name=value`. */
const QUERY_PARAMETER = /([?&])([^=&# \t\n\v\f\r]+)=([^&# \t\n\v\f\r]*)/g;

/** Rule 2d: an email address. */
const EMAIL = /[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}/g;

/** Rule 2e: a standalone run of 32 or more base64 / base64url characters (hex is a subset). */
const TOKEN_CANDIDATE = /(?<![A-Za-z0-9+/_-])[A-Za-z0-9+/_-]{32,}={0,2}(?![A-Za-z0-9+/_=-])/g;

/** Rule 2f: a North American phone number: 406-293-1234, (406) 293 1234, 406.293.1234. */
const PHONE =
  /(?<!\d)(?:\(\d{3}\)[ \t\n\v\f\r]?|\d{3}[-. \t\n\v\f\r])\d{3}[-. \t\n\v\f\r]\d{4}(?!\d)/g;

/** Rule 2g: a run of 7 or more digits, except inside a UUID (all-lowercase or all-uppercase), which is kept whole. */
const DIGITS =
  /(?<![0-9A-Fa-f-])(?:[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}|[0-9A-F]{8}(?:-[0-9A-F]{4}){3}-[0-9A-F]{12})(?![0-9A-Fa-f-])|\d{7,}/g;

/** Rule 2h: a database error after its SQLSTATE and error code (no m flag: $ is the end of the text). */
const DATABASE_ERROR = /(SQLSTATE\[[0-9A-Z]{5}\](?::[^:\n]*:)?(?: ?\[?\d+\]?)?)[\s\S]*$/;

/** Rule 2, in order: Bearer, URL passwords, query values, email, token, phone, digits, database errors. */
export function redactText(text: string): string {
  const redacted = text
    .replace(BEARER, `Bearer ${REDACTED}`)
    .replace(URL_PASSWORD, `$1:${REDACTED}@`)
    .replace(QUERY_PARAMETER, (match: string, separator: string, name: string) =>
      isSecretKey(name) ? `${separator}${name}=${REDACTED}` : match,
    )
    .replace(EMAIL, '[email]')
    .replace(TOKEN_CANDIDATE, (run: string) => (looksLikeToken(run) ? '[token]' : run))
    .replace(PHONE, '[number]')
    .replace(DIGITS, (match: string) => (match.includes('-') ? match : '[number]'));
  return redactDatabaseError(redacted);
}

/** Rule 2h, keeping Laravel's own `(Connection: …, SQL: …)` ending when it ends the text. */
function redactDatabaseError(text: string): string {
  const cut = text.lastIndexOf(' (Connection: ');
  const suffix =
    cut !== -1 && text.endsWith(')') && text.slice(cut).includes(', SQL: ') ? text.slice(cut) : '';
  return (
    text.slice(0, text.length - suffix.length).replace(DATABASE_ERROR, '$1 [redacted]') + suffix
  );
}

/**
 * Rule 1 at every depth (including inside lists), and rule 2 for every other string.
 * Numbers, booleans and null under other keys are kept. Returns a new object; the
 * input is never changed.
 */
export function redactContext(context: { readonly [key: string]: JsonValue }): {
  readonly [key: string]: JsonValue;
} {
  // Object.fromEntries creates each key as the object's own property, so a key such as
  // "__proto__" stays plain data instead of changing the object's prototype.
  return Object.fromEntries(
    Object.entries(context).map(([key, value]) => [
      key,
      isSecretKey(key) ? REDACTED : redactValue(value),
    ]),
  );
}

function redactValue(value: JsonValue): JsonValue {
  if (typeof value === 'string') {
    return redactText(value);
  }
  if (isList(value)) {
    return value.map(redactValue);
  }
  if (typeof value === 'object' && value !== null) {
    return redactContext(value);
  }
  return value;
}

/** Array.isArray, typed for read-only lists of JSON values. */
function isList(value: JsonValue): value is readonly JsonValue[] {
  return Array.isArray(value);
}

function isSecretKey(key: string): boolean {
  // Lowercase ASCII letters only, as PHP's strtolower() does. toLowerCase() would also
  // turn other characters, such as the Kelvin sign, into ASCII letters.
  const normalized = key.replace(/[A-Z]/g, (letter) => letter.toLowerCase()).replace(/[_.-]/g, '');
  return SECRET_WORDS.some((word) => normalized.includes(word));
}

/** All hex, or a digit, an uppercase and a lowercase letter (so long words and paths are kept). */
function looksLikeToken(run: string): boolean {
  if (/^[0-9A-Fa-f]+$/.test(run)) {
    return true;
  }
  return /\d/.test(run) && /[A-Z]/.test(run) && /[a-z]/.test(run);
}
