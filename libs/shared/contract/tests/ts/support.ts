// support.ts — shared helpers for the contract's TypeScript self-tests.
// Why: every test needs the same three things: the schemas, the fixtures, and a
// validator that knows all the schemas. Loading them in one place keeps the tests short
// and makes sure every test reads the files the same way.
// How it connects: imported by tests/ts/*.test.ts. tests/php/Support.php (coming
// later in this iteration) does the same job for PHPUnit, with the same rules, so both
// languages judge the same files identically.

import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { Ajv2020, type ErrorObject } from 'ajv/dist/2020.js';
import addFormats from 'ajv-formats';

/** The contract package folder (libs/shared/contract), found from this file's location. */
export const CONTRACT_ROOT = fileURLToPath(new URL('../../', import.meta.url));
export const SCHEMAS_DIR = join(CONTRACT_ROOT, 'schemas');
export const FIXTURES_DIR = join(CONTRACT_ROOT, 'fixtures');
export const REDACTION_DIR = join(CONTRACT_ROOT, 'redaction');

/** Every schema's `$id` starts with this; the file name follows. Nothing is hosted there. */
export const SCHEMA_ID_BASE = 'https://chapelcore.org/contract/schemas/';

export interface SchemaFile {
  /** File name without `.schema.json`, e.g. `health-status`. */
  readonly name: string;
  readonly schema: Readonly<Record<string, unknown>>;
}

export interface ValidFixture {
  readonly schema: string;
  readonly name: string;
  readonly data: unknown;
}

/** Where and why the data must fail: a JSON Pointer to the offending field, and the rule it breaks. */
export interface Expectation {
  readonly path: string;
  readonly keyword: string;
}

export interface InvalidFixture {
  readonly schema: string;
  readonly name: string;
  readonly description: string;
  readonly expect: Expectation;
  readonly data: unknown;
}

export function readJson(path: string): unknown {
  return JSON.parse(readFileSync(path, 'utf8'));
}

/** Names of the entries in a folder, sorted so test order never depends on the disk. */
function list(dir: string): string[] {
  return existsSync(dir) ? readdirSync(dir).sort() : [];
}

export function schemaId(name: string): string {
  return `${SCHEMA_ID_BASE}${name}.schema.json`;
}

export function loadSchemas(): SchemaFile[] {
  return list(SCHEMAS_DIR)
    .filter((file) => file.endsWith('.schema.json'))
    .map((file) => ({
      name: file.slice(0, -'.schema.json'.length),
      schema: readJson(join(SCHEMAS_DIR, file)) as Record<string, unknown>,
    }));
}

/** fixtures/<kind>/<schema>/<case>.json, as [schema, case, parsed JSON]. */
function loadFixtureFiles(kind: 'valid' | 'invalid'): [string, string, unknown][] {
  const root = join(FIXTURES_DIR, kind);
  return list(root).flatMap((schema) =>
    list(join(root, schema))
      .filter((file) => file.endsWith('.json'))
      .map((file): [string, string, unknown] => [
        schema,
        file.slice(0, -'.json'.length),
        readJson(join(root, schema, file)),
      ]),
  );
}

export function loadValidFixtures(): ValidFixture[] {
  return loadFixtureFiles('valid').map(([schema, name, data]) => ({ schema, name, data }));
}

/**
 * Invalid fixtures are wrapped: { description, expect: { path, keyword }, data }.
 * A badly written wrapper stops the run with a clear message, so a typo in a fixture
 * can never pass as "the data was rejected".
 */
export function loadInvalidFixtures(): InvalidFixture[] {
  return loadFixtureFiles('invalid').map(([schema, name, file]) => {
    const where = `fixtures/invalid/${schema}/${name}.json`;
    if (typeof file !== 'object' || file === null || !('data' in file)) {
      throw new Error(`${where}: must be an object with description, expect and data`);
    }
    const { description, expect } = file as { description?: unknown; expect?: unknown };
    if (typeof description !== 'string' || description === '') {
      throw new Error(`${where}: "description" must say why the data is invalid`);
    }
    if (
      typeof expect !== 'object' ||
      expect === null ||
      typeof (expect as { path?: unknown }).path !== 'string' ||
      typeof (expect as { keyword?: unknown }).keyword !== 'string'
    ) {
      throw new Error(
        `${where}: "expect" must be { "path": "<JSON Pointer>", "keyword": "<rule>" }`,
      );
    }
    return { schema, name, description, expect: expect as Expectation, data: file.data };
  });
}

/** An ajv validator for draft 2020-12 that knows every schema, so `$ref`s between them resolve. */
export function createValidator(): Ajv2020 {
  const ajv = new Ajv2020({ strict: true, allErrors: true });
  addFormats.default(ajv);
  for (const { schema } of loadSchemas()) {
    ajv.addSchema(schema);
  }
  return ajv;
}

/**
 * Turns ajv errors into { path, keyword } pairs in the shape fixtures use. ajv reports a
 * missing or unexpected field at its parent object; the fixture points at the field
 * itself, so its name is appended. The PHP helper applies the same rule to opis errors.
 */
export function normalizeErrors(errors: readonly ErrorObject[] | null | undefined): Expectation[] {
  return (errors ?? []).map((error) => {
    const params = error.params as { missingProperty?: string; additionalProperty?: string };
    const field = params.missingProperty ?? params.additionalProperty;
    return {
      path: field === undefined ? error.instancePath : `${error.instancePath}/${field}`,
      keyword: error.keyword,
    };
  });
}

export interface RedactionCaseFile {
  /** File name without `.json`, e.g. `text-email`. */
  readonly name: string;
  readonly data: unknown;
}

/** redaction/<case>.json: the shared redaction examples (DOMAIN-MODEL §5.8). */
export function loadRedactionCases(): RedactionCaseFile[] {
  return list(REDACTION_DIR)
    .filter((file) => file.endsWith('.json'))
    .map((file) => ({
      name: file.slice(0, -'.json'.length),
      data: readJson(join(REDACTION_DIR, file)),
    }));
}
