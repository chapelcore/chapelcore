// index.ts — the public entry point of @chapelcore/core.
// Why: apps import only '@chapelcore/core' (package.json `exports`), so what this file
// exports is the package's whole API.
// How it connects: built to dist/index.js. The contract's types come from src/types.ts
// (generated), redaction from src/redact.ts, the API client from src/api-client.ts and
// client error reports from src/client-error-report.ts.
export type * from './types.js';
export {
  ApiClient,
  type ApiClientOptions,
  DEFAULT_TIMEOUT_MS,
  type FetchError,
  type FetchErrorKind,
  type FetchResult,
  type SendResult,
} from './api-client.js';
export { clientErrorReport, REPORT_LIMITS, type ReportSource } from './client-error-report.js';
export { type JsonValue, REDACTED, redactContext, redactPath, redactText } from './redact.js';
