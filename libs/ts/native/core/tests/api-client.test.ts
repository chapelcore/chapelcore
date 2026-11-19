// api-client.test.ts — the API client turns every reply, and every failure, into a FetchResult.
// Why: phase-1.md §6.4 and §7: the admin and site never see a thrown error or a raw
// response; they get `ok` with data and meta, or an error with a kind, status, code,
// message, fields and reference code. A 503 from /health is data (E3), and unknown fields
// and enum values are tolerated (DOMAIN-MODEL universal rules 5 and 6).
// How it connects: tests src/api-client.ts through the package's entry point, with the real
// fetch against a local server (tests/test-server.ts). The replies and reference codes are
// the contract's own fixtures (libs/shared/contract/fixtures), which the contract's
// self-tests prove match the schemas.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  ApiClient,
  DEFAULT_TIMEOUT_MS,
  type ErrorEnvelope,
  type FetchResult,
  type HealthResponse,
  type HealthStatus,
} from '../src/index.js';
import { readContractJson } from './support.js';
import { closedOrigin, json, startTestServer, type TestServer } from './test-server.js';

/** One of the contract's valid fixtures, e.g. fixtures/valid/health-response/ok.json. */
function fixture(folder: string, name: string): unknown {
  const found = readContractJson(`fixtures/valid/${folder}`).find((file) => file.name === name);
  if (found === undefined) {
    throw new Error(`no fixture ${folder}/${name}`);
  }
  return found.data;
}

const healthOk = fixture('health-response', 'ok') as HealthResponse;
const healthDown = fixture('health-response', 'down') as HealthResponse;
const notFound = fixture('error-envelope', 'not-found') as ErrorEnvelope;
const validationFailed = fixture('error-envelope', 'validation-failed') as ErrorEnvelope;

/** Every valid reference code in the contract, and every invalid one plus two more. */
const validCodes = readContractJson('fixtures/valid/ref-code').map(({ data }) => data as string);
const invalidCodes = [
  ...readContractJson('fixtures/invalid/ref-code').map(
    ({ data }) => (data as { data: string }).data,
  ),
  'AB12-CD345',
  'xAB12-CD34',
];

const CANNOT_READ = 'The server sent a reply that could not be read.';

/** An error envelope with another error, the same meta. */
function errorEnvelope(code: string, message: string): ErrorEnvelope {
  return { error: { code, message, fields: {} }, meta: notFound.meta };
}

let server: TestServer;
let client: ApiClient;

beforeEach(async () => {
  server = await startTestServer();
  client = new ApiClient({ baseUrl: server.origin });
});

afterEach(async () => {
  vi.restoreAllMocks();
  await server.close();
});

describe('ApiClient.getHealth', () => {
  describe('data', () => {
    it('returns ok with the data and meta of a 200 reply', async () => {
      server.reply = json(200, healthOk);
      const result: FetchResult<HealthStatus> = await client.getHealth();
      expect(result).toEqual({ ok: true, data: healthOk.data, meta: healthOk.meta });
    });

    it('returns a 503 reporting down as data, not as an error (E3)', async () => {
      server.reply = json(503, healthDown);
      expect(await client.getHealth()).toEqual({
        ok: true,
        data: healthDown.data,
        meta: healthDown.meta,
      });
    });

    it('ignores unknown fields and keeps an unknown status value (universal rules 5 and 6)', async () => {
      const newer = {
        data: { ...healthOk.data, status: 'maintenance', uptime: 12 },
        meta: { ...healthOk.meta, region: 'us-west' },
        error: null,
      };
      server.reply = json(200, newer);
      const result = await client.getHealth();
      expect(result.ok).toBe(true);
      expect(result.ok && result.data.status).toBe('maintenance');
      expect(result.ok && result.meta.requestId).toBe('7F3K-92QD');
    });
  });

  describe('API errors', () => {
    it('returns the status, code, message, fields and reference code of an error envelope', async () => {
      server.reply = json(404, notFound);
      expect(await client.getHealth()).toEqual({
        ok: false,
        error: {
          kind: 'api',
          status: 404,
          code: 'NOT_FOUND',
          message: 'There is nothing at this address.',
          fields: {},
          requestId: '7F3K-92QD',
        },
      });
    });

    it('keeps the messages for each field', async () => {
      server.reply = json(422, validationFailed);
      const result = await client.getHealth();
      expect(!result.ok && result.error.fields).toEqual(validationFailed.error.fields);
    });

    it.each([
      ['a 503 (maintenance mode)', 503],
      ['a 200', 200],
    ])('reads an error envelope sent with %s as an error, not data', async (_, status) => {
      server.reply = json(status, errorEnvelope('SERVICE_UNAVAILABLE', 'Back soon.'));
      const result = await client.getHealth();
      expect(!result.ok && result.error.kind).toBe('api');
      expect(!result.ok && result.error.code).toBe('SERVICE_UNAVAILABLE');
    });

    it('keeps an error code it does not know (universal rule 5)', async () => {
      server.reply = json(418, errorEnvelope('TEAPOT_EMPTY', 'No tea.'));
      const result = await client.getHealth();
      expect(!result.ok && result.error.code).toBe('TEAPOT_EMPTY');
    });
  });

  describe('reference codes', () => {
    it.each(validCodes)(
      'takes %s from the X-Request-Id header when the body is not JSON',
      async (code) => {
        server.reply = {
          status: 502,
          headers: { 'Content-Type': 'text/html', 'X-Request-Id': code },
          body: '<h1>Bad gateway</h1>',
        };
        expect(await client.getHealth()).toEqual({
          ok: false,
          error: {
            kind: 'invalid-response',
            status: 502,
            code: null,
            message: CANNOT_READ,
            fields: {},
            requestId: code,
          },
        });
      },
    );

    it.each(invalidCodes)('ignores %s in the X-Request-Id header', async (code) => {
      server.reply = { status: 500, headers: { 'X-Request-Id': code }, body: 'Server error' };
      const result = await client.getHealth();
      expect(!result.ok && result.error.requestId).toBeNull();
    });

    it('gives null when there is no header and no JSON', async () => {
      server.reply = { status: 500, body: 'Server error' };
      const result = await client.getHealth();
      expect(!result.ok && result.error.requestId).toBeNull();
    });

    it("prefers the body's reference code to the header's", async () => {
      server.reply = json(404, notFound, { 'X-Request-Id': 'AB12-CD34' });
      const result = await client.getHealth();
      expect(!result.ok && result.error.requestId).toBe('7F3K-92QD');
    });

    it("uses the header's code when the body's is not valid", async () => {
      const badMeta = { ...notFound, meta: { ...notFound.meta, requestId: 'abc' } };
      server.reply = json(404, badMeta, { 'X-Request-Id': 'AB12-CD34' });
      const result = await client.getHealth();
      expect(!result.ok && result.error.requestId).toBe('AB12-CD34');
    });
  });

  describe('replies it cannot read', () => {
    const { error, meta } = notFound;
    it.each([
      ['an empty body', ''],
      ['JSON null', 'null'],
      ['a JSON list', '[]'],
      ['an object without data or error', '{"status":"ok"}'],
      ['data without meta', JSON.stringify({ data: healthOk.data })],
      ['meta that is not an object', JSON.stringify({ data: healthOk.data, meta: 'x' })],
      ['meta without data or error', JSON.stringify({ meta })],
      [
        'an error without a message',
        JSON.stringify({ error: { ...error, message: undefined }, meta }),
      ],
      ['an error code that is not text', JSON.stringify({ error: { ...error, code: 404 }, meta })],
      ['an error without fields', JSON.stringify({ error: { ...error, fields: undefined }, meta })],
      ['fields that are not an object', JSON.stringify({ error: { ...error, fields: [] }, meta })],
      [
        'field messages that are not a list',
        JSON.stringify({ error: { ...error, fields: { path: 'x' } }, meta }),
      ],
      [
        'field messages that are not text',
        JSON.stringify({ error: { ...error, fields: { path: [1] } }, meta }),
      ],
    ])('reads %s as invalid-response', async (_, body) => {
      server.reply = { status: 200, headers: { 'Content-Type': 'application/json' }, body };
      const result = await client.getHealth();
      expect(!result.ok && result.error).toMatchObject({
        kind: 'invalid-response',
        status: 200,
        code: null,
        message: CANNOT_READ,
        fields: {},
      });
    });

    it('reads data with a status that does not carry data (a 500) as invalid-response', async () => {
      server.reply = json(500, healthOk);
      const result = await client.getHealth();
      expect(!result.ok && result.error.kind).toBe('invalid-response');
      expect(!result.ok && result.error.status).toBe(500);
      expect(!result.ok && result.error.requestId).toBe('7F3K-92QD');
    });
  });

  describe('failures', () => {
    const quickly = () => new ApiClient({ baseUrl: server.origin, timeoutMs: 100 });

    it('reports a timeout when the API does not answer in time', async () => {
      server.mode = 'hang';
      expect(await quickly().getHealth()).toEqual({
        ok: false,
        error: {
          kind: 'timeout',
          status: null,
          code: null,
          message: 'The server took too long to answer.',
          fields: {},
          requestId: null,
        },
      });
    });

    it('reports a timeout when the reply stops halfway', async () => {
      server.reply = json(200, healthOk);
      server.mode = 'stall-body';
      const result = await quickly().getHealth();
      expect(!result.ok && result.error.kind).toBe('timeout');
    });

    it('reports a network error when the connection drops halfway through the reply', async () => {
      server.reply = json(200, healthOk);
      server.mode = 'break-body';
      const result = await quickly().getHealth();
      expect(!result.ok && result.error.kind).toBe('network');
    });

    it('reports a network error when nothing is listening', async () => {
      const unreachable = new ApiClient({ baseUrl: await closedOrigin() });
      expect(await unreachable.getHealth()).toEqual({
        ok: false,
        error: {
          kind: 'network',
          status: null,
          code: null,
          message: 'Could not reach the server.',
          fields: {},
          requestId: null,
        },
      });
    });

    it('waits 10 seconds by default', async () => {
      const timeout = vi.spyOn(AbortSignal, 'timeout');
      server.reply = json(200, healthOk);
      await client.getHealth();
      expect(DEFAULT_TIMEOUT_MS).toBe(10_000);
      expect(timeout).toHaveBeenCalledWith(10_000);
    });
  });

  describe('the request', () => {
    it('is a GET of {apiBase}/health that asks for JSON', async () => {
      server.reply = json(200, healthOk);
      await client.getHealth();
      expect(server.requests).toHaveLength(1);
      expect(server.requests[0]?.method).toBe('GET');
      expect(server.requests[0]?.url).toBe('/health');
      expect(server.requests[0]?.headers.accept).toBe('application/json');
    });

    it.each([
      ['an /api base (D16)', '/api', '/api/health'],
      ['a base with a trailing slash', '/api/', '/api/health'],
      ['a base with two trailing slashes', '/api//', '/api/health'],
    ])('works with %s', async (_, base, path) => {
      server.reply = json(200, healthOk);
      await new ApiClient({ baseUrl: `${server.origin}${base}` }).getHealth();
      expect(server.requests[0]?.url).toBe(path);
    });

    it('sends cookies (credentials: include, E6) and a timeout signal', async () => {
      server.reply = json(200, healthOk);
      const calls: RequestInit[] = [];
      const recording = new ApiClient({
        baseUrl: server.origin,
        fetch: (input, init) => {
          calls.push(init ?? {});
          return fetch(input, init);
        },
      });
      await recording.getHealth();
      expect(calls).toHaveLength(1);
      expect(calls[0]?.credentials).toBe('include');
      expect(calls[0]?.signal).toBeInstanceOf(AbortSignal);
    });
  });
});
