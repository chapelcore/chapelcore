// index.ts — the public entry point of @chapelcore/core.
// Why: apps import only '@chapelcore/core' (package.json `exports`), so what this file
// exports is the package's whole API.
// How it connects: built to dist/index.js. The contract's types come from src/types.ts
// (generated), redaction from src/redact.ts and the API client from src/api-client.ts;
// checkpoint 5 adds the client error report.
export type * from './types.js';
export {
  ApiClient,
  type ApiClientOptions,
  DEFAULT_TIMEOUT_MS,
  type FetchError,
  type FetchErrorKind,
  type FetchResult,
} from './api-client.js';
export { type JsonValue, REDACTED, redactContext, redactText } from './redact.js';
