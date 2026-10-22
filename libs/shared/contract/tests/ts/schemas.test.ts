// schemas.test.ts — rules every schema file must follow, checked before any data is.
// Why: DOMAIN-MODEL universal rules 2 and 6 (every field is always present; nothing
// beyond the model is sent) are easy to forget when a schema is written by hand. These
// tests check them by machine, together with the house style: draft 2020-12, an `$id`
// that matches the file name, and a `title` that is the DOMAIN-MODEL object name.
// How it connects: reads schemas/ through support.ts. tests/php/SchemasTest.php
// (later in this iteration) repeats these checks with opis.

import { beforeAll, describe, expect, test } from 'vitest';
import type { Ajv2020 } from 'ajv/dist/2020.js';
import { createValidator, loadSchemas, schemaId } from './support.js';

const DRAFT_2020_12 = 'https://json-schema.org/draft/2020-12/schema';
const schemas = loadSchemas();

type Json = Record<string, unknown>;

function isObject(value: unknown): value is Json {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * Walks a schema and lists every object definition that breaks the record rules:
 * - an object must say what keys it allows (`additionalProperties`): either `false`
 *   for a record with named fields, or a schema for a map such as `error.fields`;
 * - a record (an object with `properties`) must forbid extra keys and require
 *   every one of its fields, because nullable fields are present as `null`.
 * Each problem is reported with its location inside the schema, e.g. `/properties/meta`.
 */
function recordRuleViolations(node: unknown, at = ''): string[] {
  if (Array.isArray(node)) {
    return node.flatMap((item, i) => recordRuleViolations(item, `${at}/${i}`));
  }
  if (!isObject(node)) {
    return [];
  }
  const problems: string[] = [];
  const types = Array.isArray(node.type) ? node.type : [node.type];
  if (types.includes('object')) {
    if (!('additionalProperties' in node)) {
      problems.push(
        `${at || '/'}: object must set additionalProperties (false, or a schema for a map)`,
      );
    }
    if (isObject(node.properties)) {
      const fields = Object.keys(node.properties).sort();
      const required = Array.isArray(node.required) ? [...node.required].sort() : [];
      if (node.additionalProperties !== false) {
        problems.push(
          `${at || '/'}: a record with properties must set additionalProperties: false`,
        );
      }
      if (JSON.stringify(fields) !== JSON.stringify(required)) {
        problems.push(
          `${at || '/'}: required must list every field exactly once (fields: ${fields.join(', ')}; required: ${required.join(', ')})`,
        );
      }
    }
  }
  for (const [key, child] of Object.entries(node)) {
    if (key === 'enum' || key === 'const' || key === 'examples') {
      continue; // literal values, not schemas
    }
    problems.push(...recordRuleViolations(child, `${at}/${key}`));
  }
  return problems;
}

test('the contract has schemas', () => {
  expect(schemas.length).toBeGreaterThan(0);
});

let ajv: Ajv2020;

// Adding a schema checks it against the draft 2020-12 meta-schema; an invalid schema
// stops here with ajv's explanation of what is wrong.
beforeAll(() => {
  ajv = createValidator();
});

for (const { name, schema } of schemas) {
  describe(name, () => {
    test('uses draft 2020-12', () => {
      expect(schema.$schema).toBe(DRAFT_2020_12);
    });

    test('has an $id that matches its file name', () => {
      expect(schema.$id).toBe(schemaId(name));
    });

    test('has a title naming the DOMAIN-MODEL object', () => {
      expect(schema.title).toMatch(/^[A-Z][A-Za-z0-9]*$/);
    });

    test('compiles, with every $ref resolved', () => {
      expect(ajv.getSchema(schemaId(name))).toBeTypeOf('function');
    });

    test('requires every field and allows no others (DOMAIN-MODEL rules 2 and 6)', () => {
      expect(recordRuleViolations(schema)).toEqual([]);
    });
  });
}
