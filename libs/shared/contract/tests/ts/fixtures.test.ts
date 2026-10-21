// fixtures.test.ts — checks the schemas against example data: every valid fixture
// must pass, and every invalid fixture must fail for the reason it states.
// Why: a schema can be well-formed and still say the wrong thing (a pattern that lets
// a lowercase reference code through, a missing enum value). The fixtures are the
// DOMAIN-MODEL's rules written as examples, and these tests prove the schemas enforce
// them. Every schema needs at least one example of each kind.
// How it connects: reads schemas/ and fixtures/ through support.ts.
// tests/php/FixturesTest.php (later in this iteration) runs the same fixtures through
// opis, so PHP and TypeScript must agree on every one.

import { beforeAll, describe, expect, test } from 'vitest';
import type { Ajv2020 } from 'ajv/dist/2020.js';
import {
  createValidator,
  loadInvalidFixtures,
  loadSchemas,
  loadValidFixtures,
  normalizeErrors,
  schemaId,
} from './support.js';

const schemaNames = loadSchemas().map((schema) => schema.name);
const validFixtures = loadValidFixtures();
const invalidFixtures = loadInvalidFixtures();

let ajv: Ajv2020;

beforeAll(() => {
  ajv = createValidator();
});

test('the contract has valid and invalid fixtures', () => {
  expect(validFixtures.length, 'valid fixtures').toBeGreaterThan(0);
  expect(invalidFixtures.length, 'invalid fixtures').toBeGreaterThan(0);
});

describe('coverage', () => {
  test('every fixture folder belongs to a schema', () => {
    const folders = new Set([...validFixtures, ...invalidFixtures].map((f) => f.schema));
    expect([...folders].filter((folder) => !schemaNames.includes(folder))).toEqual([]);
  });

  for (const name of schemaNames) {
    test(`${name} has at least one valid and one invalid fixture`, () => {
      const hasValid = validFixtures.some((f) => f.schema === name);
      const hasInvalid = invalidFixtures.some((f) => f.schema === name);
      expect({ hasValid, hasInvalid }).toEqual({ hasValid: true, hasInvalid: true });
    });
  }
});

for (const { schema, name, data } of validFixtures) {
  test(`valid/${schema}/${name} passes`, () => {
    const passed = ajv.validate(schemaId(schema), data);
    expect(normalizeErrors(ajv.errors)).toEqual([]);
    expect(passed).toBe(true);
  });
}

for (const { schema, name, description, expect: expected, data } of invalidFixtures) {
  test(`invalid/${schema}/${name} fails: ${description}`, () => {
    expect(ajv.validate(schemaId(schema), data)).toBe(false);
    expect(normalizeErrors(ajv.errors)).toContainEqual(expected);
  });
}
