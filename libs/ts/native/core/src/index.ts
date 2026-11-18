// index.ts — the public entry point of @chapelcore/core.
// Why: apps import only '@chapelcore/core' (package.json `exports`), so what this file
// exports is the package's whole API.
// How it connects: built to dist/index.js. The contract's types come from src/types.ts
// (generated), redaction from src/redact.ts; later checkpoints add the API client (4)
// and the client error report (5).
export type * from './types.js';
export { type JsonValue, REDACTED, redactContext, redactText } from './redact.js';
