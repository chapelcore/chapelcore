<!--
  README.md — what the contract package holds and how to change it.
  Why: the contract is edited by hand whenever DOMAIN-MODEL.md changes, and its tests
  have rules that are easy to trip over (fixture wrapper, one broken rule per location).
  Connects to: docs/design/DOMAIN-MODEL.md (the normative model), tests/ts and tests/php
  (the self-tests), docs/runbooks/development.md §4 (how to run them).
-->

# @chapelcore/contract · chapelcore/contract

The machine-checkable form of [DOMAIN-MODEL.md](../../../docs/design/DOMAIN-MODEL.md).
The model document is normative; these files enforce it. They change in the same PR
as the document (DOMAIN-MODEL §10).

| Folder / file | What it is |
|---|---|
| `schemas/<name>.schema.json` | One JSON Schema (draft 2020-12) per model object or notation type. `$id` is `https://chapelcore.org/contract/schemas/<name>.schema.json` (an identifier only; nothing is hosted there); `title` is the DOMAIN-MODEL name |
| `fixtures/valid/<schema>/<case>.json` | Example data that must pass |
| `fixtures/invalid/<schema>/<case>.json` | Example data that must fail, wrapped with the reason (below) |
| `openapi.json` | OpenAPI 3.1 description of every endpoint; `info.version` is the model version |
| `tests/ts/`, `tests/php/` | The same self-tests in TypeScript (ajv) and PHP (opis) |

## Rules the tests enforce

- **Records are closed and complete:** an object with `properties` sets
  `additionalProperties: false` and lists every field in `required` (model rules 2 and 6).
  A map (any keys) sets `additionalProperties` to the schema of its values.
- **Nullable fields** use `"type": ["string", "null"]`; a nullable reference uses
  `"anyOf": [{ "$ref": "…" }, { "type": "null" }]`.
- **Strings are never empty** unless the model says so: `"minLength": 1` (model rule 4).
- **Every schema has at least one valid and one invalid fixture.**
- **`openapi.json` lists exactly the model's endpoints.** A new endpoint is also added to
  `ENDPOINTS` in `tests/ts/openapi.test.ts` and `tests/php/OpenApiTest.php`.
- Every body in `openapi.json` points straight at a schema file
  (`schemas/<name>.schema.json`); there is no `components` section.

## Writing an invalid fixture

```json
{
  "description": "the reference code is uppercase",
  "expect": { "path": "/requestId", "keyword": "pattern" },
  "data": { "requestId": "7f3k-92qd", "…": "…" }
}
```

- `path` is a JSON Pointer to the **field itself** (for a missing or unexpected field
  too, e.g. `/page` with keyword `required`). `""` means the whole value.
- `keyword` is the JSON Schema rule that should reject it (`pattern`, `required`,
  `enum`, `maxLength`, …).
- **Break one rule at that location.** ajv reports every rule that fails at a location,
  but opis stops at the first one, so a value that breaks two rules there (a date-only
  timestamp breaks both `format` and `pattern`) is only reliable with the rule opis
  checks first. The tests in both languages will tell you if they disagree.

## Running the tests

From the repository root:

```bash
pnpm --filter @chapelcore/contract test
```

```bash
composer --working-dir=libs/shared/contract test
```
