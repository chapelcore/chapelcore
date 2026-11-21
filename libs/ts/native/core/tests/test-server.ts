// test-server.ts — a real HTTP server on 127.0.0.1 that answers the way a test tells it to.
// Why: the API client's tests use the real fetch, so timeouts, refused and broken
// connections and headers behave as they do in a browser talking to the API. Nothing is mocked.
// How it connects: tests/api-client.test.ts starts one per test and points an ApiClient at
// it. Each test sets `reply` (status, headers, body) and, to simulate a failure, `mode`;
// `requests` records what arrived, body included.
import { createServer, type IncomingMessage, type ServerResponse } from 'node:http';
import type { AddressInfo } from 'node:net';

export interface Reply {
  status: number;
  headers?: Record<string, string>;
  body: string;
}

/**
 * - `reply`: send `reply` (the default).
 * - `hang`: accept the request and never answer.
 * - `stall-body`: send the headers and half the body, then wait forever.
 * - `break-body`: send the headers and half the body, then drop the connection.
 */
export type Mode = 'reply' | 'hang' | 'stall-body' | 'break-body';

export interface TestServer {
  /** `http://127.0.0.1:<port>`, without a trailing slash. */
  origin: string;
  reply: Reply;
  mode: Mode;
  /** Every request received, in order. */
  readonly requests: {
    method: string;
    url: string;
    headers: IncomingMessage['headers'];
    body: string;
  }[];
  close(): Promise<void>;
}

/** A JSON reply, as the API sends it. */
export function json(status: number, body: unknown, headers: Record<string, string> = {}): Reply {
  return {
    status,
    headers: { 'Content-Type': 'application/json', ...headers },
    body: JSON.stringify(body),
  };
}

export async function startTestServer(): Promise<TestServer> {
  const state: TestServer = {
    origin: '',
    reply: { status: 500, body: 'no reply set' },
    mode: 'reply',
    requests: [],
    close: () =>
      new Promise((resolve) => {
        server.closeAllConnections();
        server.close(() => {
          resolve();
        });
      }),
  };
  const server = createServer((request, response) => {
    // The whole request body is read first, so tests can check what was sent.
    const chunks: Buffer[] = [];
    request.on('data', (chunk: Buffer) => chunks.push(chunk));
    request.on('end', () => {
      state.requests.push({
        method: request.method ?? '',
        url: request.url ?? '',
        headers: request.headers,
        body: Buffer.concat(chunks).toString('utf8'),
      });
      answer(request, response);
    });
  });
  const answer = (request: IncomingMessage, response: ServerResponse): void => {
    const { status, headers = {}, body } = state.reply;
    switch (state.mode) {
      case 'hang':
        return;
      case 'stall-body':
      case 'break-body':
        // The Content-Length promises the whole body; only half of it is sent.
        response.writeHead(status, { ...headers, 'Content-Length': String(body.length) });
        response.write(body.slice(0, body.length / 2));
        if (state.mode === 'break-body') {
          setTimeout(() => request.socket.destroy(), 10);
        }
        return;
      case 'reply':
        response.writeHead(status, headers);
        response.end(body);
    }
  };
  await new Promise<void>((resolve) => {
    server.listen(0, '127.0.0.1', resolve);
  });
  state.origin = `http://127.0.0.1:${String((server.address() as AddressInfo).port)}`;
  return state;
}

/** An origin where nothing listens: a server is started to get a free port, then closed. */
export async function closedOrigin(): Promise<string> {
  const server = await startTestServer();
  await server.close();
  return server.origin;
}
