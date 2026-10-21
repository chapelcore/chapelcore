// openapi.test.ts — checks that openapi.json describes exactly the endpoints in
// DOMAIN-MODEL.md, with the right schema for every request and response.
// Why: openapi.json is what other tools (and other people) read to learn the API. If
// it drifts from the schemas or the model, they are misled. These tests keep three
// things in step: the endpoint list below (copied from DOMAIN-MODEL §4 and §5.8), the
// schema files, and the model version in DOMAIN-MODEL.md.
// How it connects: reads openapi.json, schemas/ (through support.ts) and
// docs/design/DOMAIN-MODEL.md. Later phases add their endpoints to ENDPOINTS in the
// same PR that adds them to the model. tests/php/OpenApiTest.php repeats these checks.

import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, test } from 'vitest';
import { CONTRACT_ROOT, loadSchemas, readJson } from './support.js';

type Json = Record<string, unknown>;

/** A request or response body: a schema file name, or `null` for "no body". */
type Body = string | null;

interface Endpoint {
  readonly method: 'get' | 'post' | 'put' | 'patch' | 'delete';
  readonly path: string;
  readonly requestBody: Body;
  /** HTTP status (or `default` for every other status) → its body. */
  readonly responses: Readonly<Record<string, Body>>;
}

/** Every endpoint in DOMAIN-MODEL, with the schema used for each body. */
const ENDPOINTS: readonly Endpoint[] = [
  {
    method: 'get',
    path: '/health',
    requestBody: null,
    // 503 still carries the health report, so the deploy check can read it (§4).
    responses: { '200': 'health-response', '503': 'health-response', default: 'error-envelope' },
  },
  {
    method: 'post',
    path: '/diagnostics/client-errors',
    requestBody: 'client-error-report',
    responses: { '202': null, default: 'error-envelope' },
  },
];

/** Keys of an OpenAPI path item that are operations; others (`summary`, `parameters`) are not. */
const HTTP_METHODS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

const OPENAPI_PATH = join(CONTRACT_ROOT, 'openapi.json');
const DOMAIN_MODEL_PATH = join(CONTRACT_ROOT, '../../../docs/design/DOMAIN-MODEL.md');
const doc = existsSync(OPENAPI_PATH) ? (readJson(OPENAPI_PATH) as Json) : undefined;
const schemaNames = loadSchemas().map((schema) => schema.name);

function isObject(value: unknown): value is Json {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** Follows a chain of keys into the document; `undefined` as soon as one is missing. */
function dig(node: unknown, ...keys: string[]): unknown {
  return keys.reduce<unknown>(
    (current, key) => (isObject(current) ? current[key] : undefined),
    node,
  );
}

/** The schema file a body points at (`schemas/<name>.schema.json` → `<name>`), or `null` if it has none. */
function bodySchema(body: unknown): Body {
  const ref = dig(body, 'content', 'application/json', 'schema', '$ref');
  if (ref === undefined) {
    return null;
  }
  const match = typeof ref === 'string' ? /^schemas\/([a-z0-9-]+)\.schema\.json$/.exec(ref) : null;
  return match?.[1] ?? `unexpected $ref: ${String(ref)}`;
}

/** Every `$ref` in the document, with where it was found. */
function collectRefs(node: unknown, at = ''): { at: string; ref: string }[] {
  if (Array.isArray(node)) {
    return node.flatMap((item, i) => collectRefs(item, `${at}/${i}`));
  }
  if (!isObject(node)) {
    return [];
  }
  return Object.entries(node).flatMap(([key, child]) =>
    key === '$ref' && typeof child === 'string'
      ? [{ at, ref: child }]
      : collectRefs(child, `${at}/${key}`),
  );
}

test('openapi.json exists', () => {
  expect(doc, 'libs/shared/contract/openapi.json is missing').toBeDefined();
});

describe.skipIf(doc === undefined)('openapi.json', () => {
  test('is OpenAPI 3.1', () => {
    expect(dig(doc, 'openapi')).toMatch(/^3\.1\.\d+$/);
  });

  test('carries the DOMAIN-MODEL version as info.version', () => {
    const model = /Model version: \*\*(\d+\.\d+)\*\*/.exec(readFileSync(DOMAIN_MODEL_PATH, 'utf8'));
    expect(model?.[1], 'model version line in DOMAIN-MODEL.md').toBeDefined();
    expect(dig(doc, 'info', 'version')).toBe(model?.[1]);
  });

  test('every $ref points at an existing schema file', () => {
    const broken = collectRefs(doc).filter(({ ref }) => {
      const match = /^schemas\/([a-z0-9-]+)\.schema\.json$/.exec(ref);
      return match?.[1] === undefined || !schemaNames.includes(match[1]);
    });
    expect(broken).toEqual([]);
  });

  test('documents exactly the endpoints in DOMAIN-MODEL', () => {
    const paths = dig(doc, 'paths');
    const documented = Object.entries(isObject(paths) ? paths : {})
      .flatMap(([path, item]) =>
        Object.keys(isObject(item) ? item : {})
          .filter((key) => HTTP_METHODS.includes(key))
          .map((method) => `${method} ${path}`),
      )
      .sort();
    expect(documented).toEqual(ENDPOINTS.map((e) => `${e.method} ${e.path}`).sort());
  });

  for (const endpoint of ENDPOINTS) {
    const label = `${endpoint.method.toUpperCase()} ${endpoint.path}`;

    test(`${label}: request body`, () => {
      const operation = dig(doc, 'paths', endpoint.path, endpoint.method);
      expect(bodySchema(dig(operation, 'requestBody'))).toBe(endpoint.requestBody);
    });

    test(`${label}: responses`, () => {
      const responses = dig(doc, 'paths', endpoint.path, endpoint.method, 'responses');
      const documented = Object.fromEntries(
        Object.entries(isObject(responses) ? responses : {}).map(([status, response]) => [
          status,
          bodySchema(response),
        ]),
      );
      expect(documented).toEqual(endpoint.responses);
    });
  }
});
