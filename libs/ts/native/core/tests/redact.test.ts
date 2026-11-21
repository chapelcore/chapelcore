// redact.test.ts — the TypeScript redactor gives exactly the shared cases' results.
// Why: DOMAIN-MODEL §5.8: redaction is one pure function, the same in every language, and
// the shared cases decide (plan R17). PHP runs the same files in RedactorTest.php.
// How it connects: tests src/redact.ts through the package's entry point; reads the cases
// from libs/shared/contract/redaction through support.ts.
import { describe, expect, it } from 'vitest';
import {
  type JsonValue,
  type RedactionCase,
  redactContext,
  redactPath,
  redactText,
} from '../src/index.js';
import { readContractJson } from './support.js';

type Context = Readonly<Record<string, JsonValue>>;

const cases = readContractJson('redaction').map(({ name, data }) => ({
  name,
  ...(data as RedactionCase),
}));

function redact(kind: RedactionCase['kind'], value: unknown): unknown {
  switch (kind) {
    case 'text':
      return redactText(value as string);
    case 'path':
      return redactPath(value as string);
    case 'context':
      return redactContext(value as Context);
  }
}

/** Freezes a value at every depth, so any attempt to change it throws. */
function deepFreeze<T>(value: T): T {
  if (typeof value === 'object' && value !== null) {
    for (const item of Object.values(value)) {
      deepFreeze(item);
    }
    Object.freeze(value);
  }
  return value;
}

describe('redactText, redactPath and redactContext', () => {
  it('have text, path and context cases to run', () => {
    expect(new Set(cases.map(({ kind }) => kind))).toEqual(new Set(['text', 'path', 'context']));
  });

  describe.each(cases)('redaction/$name', ({ kind, input, expected }) => {
    it('gives the expected output', () => {
      expect(redact(kind, input)).toEqual(expected);
    });

    it('gives the same result when run twice', () => {
      const once = redact(kind, input);
      expect(redact(kind, once)).toEqual(once);
    });

    it('does not change its input', () => {
      const original = structuredClone(input);
      expect(() => redact(kind, deepFreeze(structuredClone(input)))).not.toThrow();
      expect(input).toEqual(original);
    });
  });

  it('keeps a "__proto__" key as plain data, without changing the prototype', () => {
    const input = JSON.parse('{"__proto__": {"token": "abc"}, "count": 3}') as Context;
    const output = redactContext(input);
    expect(Object.getPrototypeOf(output)).toBe(Object.prototype);
    expect(Object.hasOwn(output, '__proto__')).toBe(true);
    expect(output['__proto__']).toEqual({ token: '[redacted]' });
    expect(output['count']).toBe(3);
  });
});
