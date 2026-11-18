// contract.ts — GENERATED from libs/shared/contract/schemas by scripts/generate-types.ts.
// Do not edit: change the schema, then run `pnpm --filter @chapelcore/core generate`.
// Why: one interface per schema, so TypeScript and the contract can't disagree.
// How it connects: src/types.ts re-exports each one as read-only; nothing else imports
// this file.

/**
 * DOMAIN-MODEL §2: a Semantic Versioning 2.0.0 version (official semver.org expression).
 */
export type SemVer = string;
/**
 * DOMAIN-MODEL §2: an instant in UTC, seconds with an optional 3- or 6-digit fraction, always ending in Z.
 */
export type Timestamp = string;
/**
 * DOMAIN-MODEL §2: a dotted identifier such as sermons.sync.
 */
export type Key = string;
/**
 * DOMAIN-MODEL §2: a stable machine code in UPPER_SNAKE.
 */
export type ErrorCode = string;
/**
 * DOMAIN-MODEL §2: a reference code, 8 uppercase Crockford Base32 characters written XXXX-XXXX, hyphen included.
 */
export type RefCode = string;
/**
 * DOMAIN-MODEL §2: an RFC 9562 UUID, lowercase and hyphenated.
 */
export type Uuid = string;

/**
 * Every contract type by name; src/types.ts reads its types from here.
 */
export interface Contract {
  ClientErrorReport: ClientErrorReport;
  DoctorCheck: DoctorCheck;
  ErrorCode: ErrorCode;
  ErrorEnvelope: ErrorEnvelope;
  ExceptionInfo: ExceptionInfo;
  HealthCheck: HealthCheck;
  HealthResponse: HealthResponse;
  HealthStatus: HealthStatus;
  Key: Key;
  LogEntry: LogEntry;
  PageInfo: PageInfo;
  RedactionCase: RedactionCase;
  RefCode: RefCode;
  ResponseMeta: ResponseMeta;
  SemVer: SemVer;
  ServedBy: ServedBy;
  Timestamp: Timestamp;
  Uuid: Uuid;
  VersionInfo: VersionInfo;
}
/**
 * DOMAIN-MODEL §5.8: a browser error sent to POST {apiBase}/diagnostics/client-errors (at most 8 KB in total).
 */
export interface ClientErrorReport {
  app: 'admin' | 'web';
  appVersion: SemVer;
  path: string;
  message: string;
  /**
   * @maxItems 50
   */
  stack: string[];
  userAgent: string;
  occurredAt: Timestamp;
}
/**
 * DOMAIN-MODEL §5.8: a HealthCheck plus a plain-language fix; never exposed on a public endpoint.
 */
export interface DoctorCheck {
  name: Key;
  status: 'ok' | 'degraded' | 'down';
  message: string | null;
  fix: string | null;
}
/**
 * DOMAIN-MODEL §4: the body of every error response.
 */
export interface ErrorEnvelope {
  error: {
    code: ErrorCode;
    message: string;
    fields: {
      [k: string]: string[];
    };
  };
  meta: ResponseMeta;
}
/**
 * DOMAIN-MODEL §4: metadata on every response, success or error.
 */
export interface ResponseMeta {
  requestId: RefCode;
  modelVersion: string;
  servedBy: ServedBy;
  page: PageInfo | null;
}
/**
 * DOMAIN-MODEL §4: which backend produced the response; values come from runtime introspection.
 */
export interface ServedBy {
  backend: string;
  backendVersion: string;
  language: string;
  languageVersion: string;
}
/**
 * DOMAIN-MODEL §4: position in a paginated list.
 */
export interface PageInfo {
  page: number;
  perPage: number;
  total: number;
}
/**
 * DOMAIN-MODEL §5.8: an exception as written to the log; never sent in a response.
 */
export interface ExceptionInfo {
  type: string;
  message: string;
  file: string | null;
  line: number | null;
  trace: string[];
  previous: ExceptionInfo | null;
}
/**
 * DOMAIN-MODEL §4: one public health check; messages are generic and never contain secrets.
 */
export interface HealthCheck {
  name: Key;
  status: 'ok' | 'degraded' | 'down';
  message: string | null;
}
/**
 * Envelope<HealthStatus>: the body of GET {apiBase}/health (200 and 503).
 */
export interface HealthResponse {
  data: HealthStatus;
  meta: ResponseMeta;
}
/**
 * DOMAIN-MODEL §4: the report from GET {apiBase}/health; status is the worst of the checks.
 */
export interface HealthStatus {
  status: 'ok' | 'degraded' | 'down';
  version: VersionInfo;
  checks: HealthCheck[];
}
/**
 * DOMAIN-MODEL §4: the release version, read from version.json.
 */
export interface VersionInfo {
  version: SemVer;
  codename: string | null;
}
/**
 * DOMAIN-MODEL §5.8: one line of the JSON log file; message and context are always redacted.
 */
export interface LogEntry {
  id: Uuid;
  occurredAt: Timestamp;
  level: 'debug' | 'info' | 'notice' | 'warning' | 'error' | 'critical' | 'alert' | 'emergency';
  channel: Key;
  message: string;
  requestId: RefCode | null;
  context: {
    [k: string]: unknown;
  };
  exception: ExceptionInfo | null;
}
/**
 * DOMAIN-MODEL §5.8: one shared redaction example. Every core library redacts input and must produce exactly expected.
 */
export interface RedactionCase {
  description: string;
  kind: 'text' | 'context';
  input: unknown;
  expected: unknown;
}
