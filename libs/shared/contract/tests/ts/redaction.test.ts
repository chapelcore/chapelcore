// redaction.test.ts — checks the shared redaction cases are well-formed.
// Why: DOMAIN-MODEL §5.8 makes these cases the definitive examples of the redaction
// rules, and every core library (PHP from 1.4, TypeScript from 1.6) runs them. A
// malformed case would silently test nothing, so each one must match its schema.
// How it connects: reads redaction/ and schemas/ through support.ts.
// tests/php/RedactionCasesTest.php checks the same files with opis.

import { beforeAll, expect, test } from 'vitest';
import type { Ajv2020 } from 'ajv/dist/2020.js';
import { createValidator, loadRedactionCases, normalizeErrors, schemaId } from './support.js';

const cases = loadRedactionCases();

let ajv: Ajv2020;

beforeAll(() => {
  ajv = createValidator();
});

test('there are text and context cases', () => {
  const kinds = new Set(cases.map(({ data }) => (data as { kind?: unknown }).kind));
  expect([...kinds].sort()).toEqual(['context', 'text']);
});

for (const { name, data } of cases) {
  test(`redaction/${name} matches the redaction-case schema`, () => {
    const passed = ajv.validate(schemaId('redaction-case'), data);
    expect(normalizeErrors(ajv.errors)).toEqual([]);
    expect(passed).toBe(true);
  });
}
