// client-error-report.test.ts — clientErrorReport() redacts, then trims, in the DOMAIN-MODEL §5.8 order.
// Why: phase-1.md §6.4 and §7: a report must never carry an email or a token out of the
// browser, must always be valid when it is sent (the server never trims), and must measure
// 8 KB exactly as the server does (plan R18: 8192 bytes is sent, 8193 is trimmed).
// How it connects: tests src/client-error-report.ts through the package's entry point.
// Every report built here is checked against the contract's client-error-report schema
// with ajv (tests/support.ts).
import type { Ajv2020 } from 'ajv/dist/2020.js';
import { beforeAll, describe, expect, it } from 'vitest';
import {
  type ClientErrorReport,
  clientErrorReport,
  REPORT_LIMITS,
  type ReportSource,
} from '../src/index.js';
import { createValidator, schemaId } from './support.js';

const source: ReportSource = {
  app: 'admin',
  appVersion: '0.1.0',
  path: '/admin',
  userAgent: 'Mozilla/5.0 (test)',
  occurredAt: new Date('2026-11-20T12:34:56.789Z'),
};

const utf8 = new TextEncoder();
const size = (report: ClientErrorReport): number => utf8.encode(JSON.stringify(report)).length;
const characters = (text: string): number => Array.from(text).length;

/** An Error with exactly this stack text. */
function errorWithStack(message: string, stack: string): Error {
  const error = new Error(message);
  error.stack = stack;
  return error;
}

let ajv: Ajv2020;

beforeAll(() => {
  ajv = createValidator();
});

/** Builds the report, and fails unless it is there and valid against the schema. */
function build(error: unknown, overrides: Partial<ReportSource> = {}): ClientErrorReport {
  const report = clientErrorReport(error, { ...source, ...overrides });
  expect(report, 'a report').not.toBeNull();
  const valid = ajv.validate(schemaId('client-error-report'), report);
  expect(ajv.errors ?? [], 'schema errors').toEqual([]);
  expect(valid).toBe(true);
  expect(size(report as ClientErrorReport)).toBeLessThanOrEqual(REPORT_LIMITS.bytes);
  return report as ClientErrorReport;
}

describe('clientErrorReport', () => {
  describe('building the report', () => {
    it("takes the Error's type and message, and each stack line without the repeated message", () => {
      const error = new TypeError('x is undefined');
      error.stack = 'TypeError: x is undefined\n    at f (app.js:1:2)\n\n    at g (app.js:3:4)\n';
      expect(build(error)).toEqual({
        app: 'admin',
        appVersion: '0.1.0',
        path: '/admin',
        message: 'TypeError: x is undefined',
        stack: ['    at f (app.js:1:2)', '    at g (app.js:3:4)'],
        userAgent: 'Mozilla/5.0 (test)',
        occurredAt: '2026-11-20T12:34:56.789Z',
      });
    });

    it('keeps every line of a stack that does not start with the message (Firefox, Safari)', () => {
      const error = errorWithStack(
        'boom',
        'f@https://libbynaz.org/app.js:1:2\r\ng@https://libbynaz.org/app.js:3:4',
      );
      expect(build(error).stack).toEqual([
        'f@https://libbynaz.org/app.js:1:2',
        'g@https://libbynaz.org/app.js:3:4',
      ]);
    });

    it.each([
      ['a string', 'Network down', 'Network down'],
      ['a number', 42, '42'],
      ['null', null, 'null'],
      ['undefined', undefined, 'undefined'],
      ['a plain object', {}, '[object Object]'],
      ['an Error with no message', new Error(''), 'Error'],
      ['an empty string', '', '(no message)'],
      ['an object without a prototype', Object.create(null) as unknown, '(no message)'],
      [
        'an object whose toString() throws',
        {
          toString(): string {
            throw new Error('no');
          },
        },
        '(no message)',
      ],
    ])('describes %s that was thrown', (_, thrown, message) => {
      const report = build(thrown);
      expect(report.message).toBe(message);
      if (!(thrown instanceof Error)) {
        expect(report.stack).toEqual([]);
      }
    });

    it('uses (unknown) when there is no user agent', () => {
      expect(build('boom', { userAgent: '' }).userAgent).toBe('(unknown)');
    });

    it('cuts a query string or fragment off the path', () => {
      expect(build('boom', { path: '/admin/members?email=ann@example.org#top' }).path).toBe(
        '/admin/members',
      );
      expect(build('boom', { path: '/admin#top?x=1' }).path).toBe('/admin');
    });

    it.each([['admin'], [''], ['?x=1'], ['https://libbynaz.org/admin']])(
      'returns null when the path %j does not start with /',
      (path) => {
        expect(clientErrorReport('boom', { ...source, path })).toBeNull();
      },
    );

    it.each([
      ['an invalid date', { occurredAt: new Date(Number.NaN) }],
      ['a year after 9999', { occurredAt: new Date('+010000-01-01T00:00:00Z') }],
      ['a time that is not a Date', { occurredAt: '2026-11-20T12:00:00Z' }],
      ['an unknown app', { app: 'other' }],
      ['a version that is not SemVer', { appVersion: 'v1' }],
    ])('returns null, without throwing, for %s', (_, overrides) => {
      // As an untyped caller (plain JavaScript) could pass it.
      const unchecked = { ...source, ...overrides } as unknown as ReportSource;
      expect(clientErrorReport('boom', unchecked)).toBeNull();
    });

    it('replaces a lone surrogate with U+FFFD in every text, since PHP would reject the JSON', () => {
      const report = build(errorWithStack('a\uD800b', 'at \uDC00f'), {
        path: '/x\uD800',
        userAgent: 'UA\uDFFF',
      });
      expect(report.message).toBe('Error: a\uFFFDb');
      expect(report.stack).toEqual(['at \uFFFDf']);
      expect(report.path).toBe('/x\uFFFD');
      expect(report.userAgent).toBe('UA\uFFFD');
      expect(JSON.stringify(report)).not.toMatch(/\\u[dD][89a-fA-F]/);
    });

    it('does not repeat a message with a lone surrogate as the first stack line', () => {
      expect(build(new Error('a\uD800b')).stack[0]).toMatch(/^ +at /);
    });
  });

  describe('redaction (before trimming)', () => {
    it('redacts the message and every stack line', () => {
      const error = errorWithStack(
        'Could not save ann@example.org',
        'Error: Could not save ann@example.org\n    at save (https://libbynaz.org/app.js?token=abc:1:2)\n    at call 4062931234',
      );
      const report = build(error);
      expect(report.message).toBe('Error: Could not save [email]');
      expect(report.stack).toEqual([
        // A query value runs to the next whitespace, so the line number goes with it.
        '    at save (https://libbynaz.org/app.js?token=[redacted]',
        '    at call [number]',
      ]);
    });

    it('redacts the path one segment at a time, so a long path is not taken for a token', () => {
      expect(build('boom', { path: '/admin/members/Profile-2026/edit-contact' }).path).toBe(
        '/admin/members/Profile-2026/edit-contact',
      );
      expect(build('boom', { path: '/members/ann@example.org/edit' }).path).toBe(
        '/members/[email]/edit',
      );
    });

    it('redacts the user agent too', () => {
      const userAgent = 'Mozilla/5.0 (Linux; Android 14; Build/UP1A.231005007) Chrome/141.0';
      expect(build('boom', { userAgent }).userAgent).toBe(
        'Mozilla/5.0 (Linux; Android 14; Build/UP1A.[number]) Chrome/141.0',
      );
    });

    it('keeps rules 2a and 2b working on a whole path, across its slashes', () => {
      expect(build('boom', { path: '/go/https://bob:hunter2@example.org/x' }).path).toBe(
        '/go/https://bob:[redacted]@example.org/x',
      );
    });

    it.each([
      ['an email', 'ann.smith@example.org', 'ann'],
      ['a phone number', '406-293-1234', '406'],
    ])('never sends part of %s cut in half by the cap', (_, secret, part) => {
      // Tokens before it shrink to [token], so text near the cap would reach the report.
      const tokens = Array(55).fill('aB3dE5fG7hI9jK1lM3nO5pQ7rS9tU1vW').join(' ');
      const filler = 'x'.repeat(2 * REPORT_LIMITS.message - tokens.length - 6);
      const report = build(`${tokens} ${filler} ${secret}`);
      expect(report.message).toContain('[token]');
      expect(report.message).not.toContain(part);
    });

    it('cuts each text to twice its limit before redacting, so a huge one is quick', () => {
      // Without the cut, redacting these takes over a minute in V8.
      // Not a hex letter, so the path segment isn't a [token].
      const huge = 'z'.repeat(100_000);
      const started = performance.now();
      const report = build(errorWithStack(huge, Array(60).fill(huge).join('\n')), {
        path: `/${huge}`,
        userAgent: huge,
      });
      expect(performance.now() - started).toBeLessThan(2_000);
      expect(report.path).toBe(`/${'z'.repeat(REPORT_LIMITS.path - 1)}`);
    });
  });

  describe('trimming', () => {
    it('step 1: cuts each text to its limit from the end, never inside a character', () => {
      const emoji = '😀';
      const longPath = build('boom', { path: `/${'x'.repeat(2045)}${emoji.repeat(3)}` }).path;
      expect(longPath).toBe(`/${'x'.repeat(2045)}${emoji.repeat(2)}`);
      expect(characters(longPath)).toBe(REPORT_LIMITS.path);

      const message = build(`${'x'.repeat(999)}${emoji.repeat(2)}`).message;
      expect(message).toBe(`${'x'.repeat(999)}${emoji}`);

      const userAgent = build('boom', {
        userAgent: `${'x'.repeat(511)}${emoji.repeat(2)}`,
      }).userAgent;
      expect(userAgent).toBe(`${'x'.repeat(511)}${emoji}`);

      const line = `at ${'x'.repeat(496)}${emoji.repeat(2)}`;
      expect(build(errorWithStack('boom', line)).stack).toEqual([`at ${'x'.repeat(496)}${emoji}`]);
    });

    it('step 1: keeps the first 50 stack lines', () => {
      const lines = Array.from({ length: 60 }, (_, index) => `at f${String(index)}`);
      expect(build(errorWithStack('boom', lines.join('\n'))).stack).toEqual(lines.slice(0, 50));
    });

    it('step 2: drops stack lines from the end until the report fits, keeping the message', () => {
      const lines = Array.from(
        { length: 50 },
        (_, index) => `${String(index).padStart(2, '0')} ${'x'.repeat(497)}`,
      );
      const report = build(errorWithStack('m'.repeat(993), lines.join('\n')));
      expect(report.message).toBe(`Error: ${'m'.repeat(993)}`);
      expect(report.stack).toEqual(lines.slice(0, report.stack.length));
      // One more line would not have fitted.
      const kept = report.stack.length;
      expect(size({ ...report, stack: lines.slice(0, kept + 1) })).toBeGreaterThan(
        REPORT_LIMITS.bytes,
      );
    });

    it('step 3: shortens the message only once no stack line is left, never inside a character', () => {
      const report = build(errorWithStack('😀'.repeat(1000), 'at f'), {
        path: `/${'é'.repeat(2047)}`,
        userAgent: 'x'.repeat(512),
      });
      expect(report.stack).toEqual([]);
      expect(report.message).toMatch(/^Error: (?:😀)+$/u);
      expect(size({ ...report, message: `${report.message}😀` })).toBeGreaterThan(
        REPORT_LIMITS.bytes,
      );
    });

    it('step 4: returns null when even a one-character message does not fit', () => {
      expect(clientErrorReport('boom', { ...source, path: `/${'😀'.repeat(2047)}` })).toBeNull();
    });

    /** A path of emoji and letters that brings the report to exactly 8192 bytes. */
    function fillingPath(base: number): string {
      const missing = REPORT_LIMITS.bytes - base;
      return `/${'😀'.repeat(Math.floor(missing / 4))}${'y'.repeat(missing % 4)}`;
    }

    it.each([
      ['letters', 'x'],
      ['quotes (2 bytes each in JSON)', '"'],
      ['control characters (6 bytes each in JSON)', '\u0001'],
    ])(
      'step 3 at the limit: 8192 bytes are sent as they are, 8193 are trimmed (R18), with %s',
      (_, character) => {
        const message = character.repeat(1000);
        const path = fillingPath(size(build(message, { path: '/' })));

        const exact = build(message, { path });
        expect(size(exact)).toBe(8192);
        expect(exact.message).toBe(message);

        const over = build(message, { path: `${path}y` });
        // One character less: 8193 bytes minus that character's size in JSON.
        expect(over.message).toBe(character.repeat(999));
        expect(size(over)).toBe(8193 - (JSON.stringify(character).length - 2));
      },
    );

    it('step 2 at the limit: a stack line is kept at 8192 bytes and dropped at 8193 (R18)', () => {
      const error = errorWithStack('m', 'at f');
      const path = fillingPath(size(build(error, { path: '/' })));

      const exact = build(error, { path });
      expect(size(exact)).toBe(8192);
      expect(exact.stack).toEqual(['at f']);

      const over = build(error, { path: `${path}y` });
      expect(over.stack).toEqual([]);
      expect(over.message).toBe('Error: m');
    });
  });
});
