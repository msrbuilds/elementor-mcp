#!/usr/bin/env node
/**
 * Elementor MCP — stdio-to-HTTP proxy
 *
 * Bridges the MCP stdio transport (used by Claude Desktop, Claude Code, etc.)
 * to the WordPress MCP Adapter HTTP endpoint.
 *
 * Environment variables:
 *   WP_URL          (required) WordPress site URL, e.g. http://mysite.test
 *   WP_USERNAME     (required) WordPress username
 *   WP_APP_PASSWORD (required) WordPress Application Password
 *   MCP_LOG_FILE    (optional) Path to a log file for debugging
 *
 * Usage:
 *   node bin/mcp-proxy.mjs
 *
 * The proxy reads JSON-RPC messages (one per line) from stdin,
 * forwards them to the WordPress REST API, and writes responses to stdout.
 *
 * @package Elementor_MCP
 * @since   1.0.0
 */

import { createInterface } from 'node:readline';
import { request as httpRequest } from 'node:http';
import { request as httpsRequest } from 'node:https';
import { appendFileSync } from 'node:fs';

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------

const WP_URL = process.env.WP_URL?.replace(/\/+$/, '') || '';
const WP_USERNAME = process.env.WP_USERNAME || '';
const WP_APP_PASSWORD = process.env.WP_APP_PASSWORD || '';
const MCP_LOG_FILE = process.env.MCP_LOG_FILE || '';
const MCP_REST_PATH = '/mcp/elementor-mcp-server';

if (!WP_URL) {
  logStderr('ERROR: WP_URL environment variable is required.');
  process.exit(1);
}

if (!WP_USERNAME || !WP_APP_PASSWORD) {
  logStderr('ERROR: WP_USERNAME and WP_APP_PASSWORD environment variables are required.');
  logStderr('Create an Application Password at: WordPress Admin > Users > Profile > Application Passwords');
  process.exit(1);
}

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------

let sessionId = null;
let usePrettyPermalinks = null; // null = not yet detected, true/false after probe
let pendingRequests = 0;

// ---------------------------------------------------------------------------
// Logging
// ---------------------------------------------------------------------------

function logStderr(message) {
  const ts = new Date().toISOString();
  const line = `[${ts}] ${message}`;
  process.stderr.write(line + '\n');

  if (MCP_LOG_FILE) {
    try {
      appendFileSync(MCP_LOG_FILE, line + '\n');
    } catch { /* ignore log write failures */ }
  }
}

// ---------------------------------------------------------------------------
// SSL handling for local development
// ---------------------------------------------------------------------------

const parsedWpUrl = new URL(WP_URL);
const isLocalDev = /\.(test|local|localhost|dev|invalid)$/.test(parsedWpUrl.hostname)
  || parsedWpUrl.hostname === 'localhost'
  || parsedWpUrl.hostname === '127.0.0.1';

// ---------------------------------------------------------------------------
// HTTP transport
// ---------------------------------------------------------------------------

/**
 * Makes an HTTP(S) request, handling self-signed certs for local dev.
 *
 * @param {object} options  Node.js http/https request options.
 * @param {string} payload  Request body.
 * @returns {Promise<{body: string, headers: object, statusCode: number}>}
 */
function doHttpRequest(options, payload) {
  return new Promise((resolve, reject) => {
    const isHttps = parsedWpUrl.protocol === 'https:';
    const doRequest = isHttps ? httpsRequest : httpRequest;

    // Allow self-signed certs for local dev domains.
    if (isHttps && isLocalDev) {
      options.rejectUnauthorized = false;
    }

    const req = doRequest(options, (res) => {
      let body = '';
      res.on('data', (chunk) => { body += chunk; });
      res.on('end', () => {
        resolve({ body, headers: res.headers, statusCode: res.statusCode });
      });
    });

    req.on('error', (err) => reject(err));
    req.setTimeout(30000, () => req.destroy(new Error('Request timeout')));
    req.write(payload);
    req.end();
  });
}

/**
 * Probes the WordPress site to detect whether pretty permalinks are enabled.
 * Tries /wp-json/ first; if 404, falls back to ?rest_route=/.
 *
 * @returns {Promise<boolean>} True if pretty permalinks work.
 */
async function detectPermalinks() {
  try {
    const isHttps = parsedWpUrl.protocol === 'https:';
    const options = {
      hostname: parsedWpUrl.hostname,
      port: parsedWpUrl.port || (isHttps ? 443 : 80),
      path: '/wp-json/',
      method: 'HEAD',
      headers: { 'Accept': 'application/json' },
    };
    if (isHttps && isLocalDev) {
      options.rejectUnauthorized = false;
    }

    const { statusCode } = await doHttpRequest(options, '');
    return statusCode !== 404;
  } catch {
    return false;
  }
}

/**
 * Builds the request path for the MCP endpoint.
 *
 * @returns {string} URL path (or path + query string).
 */
function getMcpPath() {
  if (usePrettyPermalinks) {
    return '/wp-json' + MCP_REST_PATH;
  }
  return '/?rest_route=' + encodeURIComponent(MCP_REST_PATH);
}

/**
 * Sends a JSON-RPC message to the WordPress MCP endpoint via HTTP POST.
 *
 * @param {object} jsonRpcMessage  Parsed JSON-RPC request object.
 * @returns {Promise<{body: string, headers: object, statusCode: number}>}
 */
async function sendToWordPress(jsonRpcMessage) {
  // Detect permalink structure on first request.
  if (usePrettyPermalinks === null) {
    usePrettyPermalinks = await detectPermalinks();
    logStderr(`Permalink detection: ${usePrettyPermalinks ? 'pretty (/wp-json/)' : 'plain (?rest_route=)'}`);
  }

  const auth = Buffer.from(`${WP_USERNAME}:${WP_APP_PASSWORD}`).toString('base64');

  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'Authorization': `Basic ${auth}`,
  };

  if (sessionId) {
    headers['Mcp-Session-Id'] = sessionId;
  }

  const payload = JSON.stringify(jsonRpcMessage);
  const isHttps = parsedWpUrl.protocol === 'https:';

  const options = {
    hostname: parsedWpUrl.hostname,
    port: parsedWpUrl.port || (isHttps ? 443 : 80),
    path: getMcpPath(),
    method: 'POST',
    headers: {
      ...headers,
      'Content-Length': Buffer.byteLength(payload),
    },
  };

  return doHttpRequest(options, payload);
}

// ---------------------------------------------------------------------------
// Message handling
// ---------------------------------------------------------------------------

/**
 * Processes a single JSON-RPC message from stdin.
 *
 * @param {string} line  Raw JSON line from stdin.
 */
async function handleMessage(line) {
  let message;

  try {
    message = JSON.parse(line);
  } catch {
    logStderr(`Parse error: ${line.substring(0, 200)}`);
    const errorResponse = {
      jsonrpc: '2.0',
      error: { code: -32700, message: 'Parse error' },
      id: null,
    };
    process.stdout.write(JSON.stringify(errorResponse) + '\n');
    return;
  }

  const method = message.method || '';
  const id = message.id ?? null;

  logStderr(`→ ${method} (id=${id})`);
  pendingRequests++;

  try {
    const { body, headers, statusCode } = await sendToWordPress(message);

    // Capture session ID from initialize response.
    if (method === 'initialize' && headers['mcp-session-id']) {
      sessionId = headers['mcp-session-id'];
      logStderr(`Session established: ${sessionId}`);
    }

    // Notifications (no id) don't expect a response.
    if (id === null && !method.startsWith('initialize')) {
      logStderr(`← notification acknowledged (${statusCode})`);
      return;
    }

    if (statusCode >= 400) {
      logStderr(`← HTTP ${statusCode}: ${body.substring(0, 500)}`);

      // Try to parse as JSON-RPC error.
      try {
        const parsed = JSON.parse(body);
        if (parsed.error || parsed.jsonrpc) {
          process.stdout.write(JSON.stringify(parsed) + '\n');
          return;
        }
      } catch { /* not JSON, send generic error */ }

      const errorResponse = {
        jsonrpc: '2.0',
        error: {
          code: -32603,
          message: `HTTP ${statusCode}`,
          data: { body: body.substring(0, 1000) },
        },
        id,
      };
      process.stdout.write(JSON.stringify(errorResponse) + '\n');
      return;
    }

    // Forward the response as-is to stdout.
    // The MCP adapter returns proper JSON-RPC responses.
    const trimmed = body.trim();
    if (trimmed) {
      process.stdout.write(trimmed + '\n');
      logStderr(`← response (${trimmed.length} bytes)`);
    }
  } catch (err) {
    logStderr(`← error: ${err.message}`);

    const errorResponse = {
      jsonrpc: '2.0',
      error: {
        code: -32603,
        message: 'Proxy error',
        data: { details: err.message },
      },
      id,
    };
    process.stdout.write(JSON.stringify(errorResponse) + '\n');
  } finally {
    pendingRequests--;
  }
}

// ---------------------------------------------------------------------------
// Main loop
// ---------------------------------------------------------------------------

logStderr(`Elementor MCP proxy starting`);
logStderr(`WordPress URL: ${WP_URL}`);
logStderr(`REST path: ${MCP_REST_PATH}`);
logStderr(`User: ${WP_USERNAME}`);

const rl = createInterface({
  input: process.stdin,
  terminal: false,
});

rl.on('line', (line) => {
  const trimmed = line.trim();
  if (!trimmed) return;
  handleMessage(trimmed);
});

rl.on('close', async () => {
  logStderr('stdin closed, waiting for pending requests...');
  // Wait for in-flight requests to complete before exiting.
  while (pendingRequests > 0) {
    await new Promise((r) => setTimeout(r, 50));
  }
  logStderr('shutting down');
  process.exit(0);
});

// Handle process signals gracefully.
process.on('SIGINT', () => {
  logStderr('SIGINT received, shutting down');
  process.exit(0);
});

process.on('SIGTERM', () => {
  logStderr('SIGTERM received, shutting down');
  process.exit(0);
});

process.on('uncaughtException', (err) => {
  logStderr(`Uncaught exception: ${err.message}`);
  process.exit(1);
});
