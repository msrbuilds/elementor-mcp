#!/usr/bin/env node
/**
 * MCP Tools for Elementor — stdio-to-HTTP proxy (multi-site capable)
 *
 * Bridges the MCP stdio transport (Claude Desktop, Claude Code, etc.) to the
 * WordPress MCP Adapter HTTP endpoint. Supports a SITE REGISTRY so one session
 * can drive several WordPress installs, switching between them with the injected
 * `emcp_use_site` tool.
 *
 * Single-site (unchanged, backward-compatible):
 *   WP_URL               (required) WordPress site URL, e.g. http://mysite.test
 *   WP_USERNAME          (required) WordPress username
 *   WP_APP_PASSWORD      (required) WordPress Application Password
 *
 * Multi-site (any ONE of):
 *   EMCP_SITES           JSON: { "alias": { "url", "username", "appPassword" }, ... }
 *   EMCP_SITES_FILE      Path to a JSON file with the same shape.
 *   EMCP_DEFAULT_SITE    Alias to start on (defaults to the first key).
 *   When >1 site is configured, two extra tools appear: emcp_list_sites, emcp_use_site.
 *
 * Common (all modes):
 *   MCP_LOG_FILE         (optional) Path to a log file for debugging.
 *   MCP_PROTOCOL_VERSION (optional) Override protocolVersion in initialize responses.
 *
 * @package Elementor_MCP
 * @since   1.0.0
 */

import { createInterface } from 'node:readline';
import { request as httpRequest } from 'node:http';
import { request as httpsRequest } from 'node:https';
import { appendFileSync, readFileSync } from 'node:fs';

const MCP_REST_PATH = '/mcp/emcp-tools-server';
const MCP_LOG_FILE = process.env.MCP_LOG_FILE || '';
const MCP_PROTOCOL_VERSION = process.env.MCP_PROTOCOL_VERSION || '';

// ---------------------------------------------------------------------------
// Pure helpers (exported for tests)
// ---------------------------------------------------------------------------

/**
 * Resolve the site registry from the environment.
 * Priority: EMCP_SITES (JSON) / EMCP_SITES_FILE (path) → single WP_URL env → none.
 *
 * @param {Object} env process.env-like object.
 * @returns {{sites: Object, defaultSite: string}}
 */
export function loadSites(env) {
  let raw = null;
  if (env.EMCP_SITES) {
    try { raw = JSON.parse(env.EMCP_SITES); } catch { raw = null; }
  } else if (env.EMCP_SITES_FILE) {
    try { raw = JSON.parse(readFileSync(env.EMCP_SITES_FILE, 'utf8')); } catch { raw = null; }
  }

  const sites = {};
  if (raw && typeof raw === 'object') {
    for (const [alias, cfg] of Object.entries(raw)) {
      if (!cfg || !cfg.url) continue;
      sites[alias] = normalizeSite(cfg);
    }
  }

  // Fall back to the single-site env as "default".
  if (Object.keys(sites).length === 0 && env.WP_URL) {
    sites.default = normalizeSite({
      url: env.WP_URL,
      username: env.WP_USERNAME || '',
      appPassword: env.WP_APP_PASSWORD || '',
    });
  }

  const keys = Object.keys(sites);
  let defaultSite = env.EMCP_DEFAULT_SITE && sites[env.EMCP_DEFAULT_SITE] ? env.EMCP_DEFAULT_SITE : (keys[0] || '');
  return { sites, defaultSite };
}

function normalizeSite(cfg) {
  const url = String(cfg.url).replace(/\/+$/, '');
  const parsed = new URL(url);
  const isLocalDev = /\.(test|local|localhost|dev|invalid)$/.test(parsed.hostname)
    || parsed.hostname === 'localhost' || parsed.hostname === '127.0.0.1';
  return {
    url,
    username: cfg.username || '',
    appPassword: cfg.appPassword || cfg.app_password || '',
    parsed,
    isLocalDev,
  };
}

/**
 * The site's base path, for WordPress installs that live in a subdirectory
 * (e.g. `https://example.com/blog` → `/blog`). Root installs → `''`.
 *
 * @param {Object} site A normalized site object (has `.parsed`, a URL instance).
 * @returns {string}
 */
export function sitePath(site) {
  return site.parsed.pathname.replace(/\/$/, '');
}

/** The two injected site-switching tools. */
export const META_TOOLS = [
  {
    name: 'emcp_list_sites',
    description: 'List the WordPress sites this proxy can connect to, and which one is active. Use emcp_use_site to switch.',
    inputSchema: { type: 'object', properties: {} },
  },
  {
    name: 'emcp_use_site',
    description: 'Switch the active WordPress site for subsequent tool calls. Pass the site alias from emcp_list_sites.',
    inputSchema: { type: 'object', properties: { site: { type: 'string', description: 'Site alias to switch to.' } }, required: ['site'] },
  },
];

const META_NAMES = new Set(META_TOOLS.map((t) => t.name));

/** Whether a tool name is one of the local meta-tools. */
export function isMetaCall(name) {
  return META_NAMES.has(name);
}

/**
 * Append the meta-tools to a tools/list result, but only when >1 site exists.
 *
 * @param {Object} response Parsed JSON-RPC tools/list response.
 * @param {number} siteCount Number of configured sites.
 * @returns {Object} The (possibly mutated) response.
 */
export function injectMetaTools(response, siteCount) {
  if (siteCount > 1 && response?.result?.tools && Array.isArray(response.result.tools)) {
    response.result.tools.push(...META_TOOLS);
  }
  return response;
}

/**
 * Handle a meta tools/call locally (no HTTP). Returns a JSON-RPC-ish result object.
 *
 * @param {string} name Tool name.
 * @param {Object} args Tool arguments.
 * @param {Object} state Proxy state ({ sites, active, session, permalinks }).
 * @param {number|string|null} id JSON-RPC id.
 * @returns {Object}
 */
export function handleMetaCall(name, args, state, id = null) {
  if (name === 'emcp_list_sites') {
    const sites = Object.keys(state.sites).map((alias) => ({ alias, url: state.sites[alias].url, active: alias === state.active }));
    return jsonRpcText(id, { sites, active: state.active });
  }
  if (name === 'emcp_use_site') {
    const site = String(args?.site || '');
    if (!state.sites[site]) {
      return jsonRpcError(id, `Unknown site "${site}". Available: ${Object.keys(state.sites).join(', ')}`);
    }
    state.active = site;
    return jsonRpcText(id, { active: site, url: state.sites[site].url });
  }
  return jsonRpcError(id, `Unknown meta tool "${name}"`);
}

function jsonRpcText(id, obj) {
  return { jsonrpc: '2.0', id, result: { content: [{ type: 'text', text: JSON.stringify(obj) }], ...obj } };
}
function jsonRpcError(id, message) {
  return { jsonrpc: '2.0', id, result: { content: [{ type: 'text', text: message }], isError: true } };
}

// ---------------------------------------------------------------------------
// Everything below is the running proxy — skipped when imported for tests.
// ---------------------------------------------------------------------------

if (!process.env.EMCP_PROXY_NO_MAIN) {
  runProxy();
}

function runProxy() {
  const { sites, defaultSite } = loadSites(process.env);

  if (Object.keys(sites).length === 0) {
    logStderr('ERROR: No site configured. Set WP_URL/WP_USERNAME/WP_APP_PASSWORD, or EMCP_SITES / EMCP_SITES_FILE.');
    process.exit(1);
  }

  const state = { sites, active: defaultSite, session: {}, permalinks: {}, initializeMessage: null };
  const siteCount = Object.keys(sites).length;

  function activeSite() { return state.sites[state.active]; }

  function doHttpRequest(site, options, payload) {
    return new Promise((resolve, reject) => {
      const isHttps = site.parsed.protocol === 'https:';
      const doRequest = isHttps ? httpsRequest : httpRequest;
      if (isHttps && site.isLocalDev) options.rejectUnauthorized = false;
      const req = doRequest(options, (res) => {
        const chunks = [];
        res.on('data', (c) => chunks.push(c));
        res.on('end', () => resolve({ body: Buffer.concat(chunks).toString('utf8'), headers: res.headers, statusCode: res.statusCode }));
      });
      req.on('error', reject);
      req.setTimeout(30000, () => req.destroy(new Error('Request timeout')));
      req.write(payload);
      req.end();
    });
  }

  async function detectPermalinks(site) {
    try {
      const isHttps = site.parsed.protocol === 'https:';
      const options = {
        hostname: site.parsed.hostname,
        port: site.parsed.port || (isHttps ? 443 : 80),
        path: `${sitePath(site)}/wp-json/`, method: 'HEAD', headers: { Accept: 'application/json' },
      };
      if (isHttps && site.isLocalDev) options.rejectUnauthorized = false;
      const { statusCode } = await doHttpRequest(site, options, '');
      return statusCode !== 404;
    } catch { return false; }
  }

  function mcpPath(alias) {
    const basePath = sitePath(state.sites[alias]);
    return state.permalinks[alias] ? `${basePath}/wp-json${MCP_REST_PATH}` : `${basePath}/?rest_route=${encodeURIComponent(MCP_REST_PATH)}`;
  }

  async function sendToSite(alias, message) {
    const site = state.sites[alias];
    if (state.permalinks[alias] === undefined || state.permalinks[alias] === null) {
      state.permalinks[alias] = await detectPermalinks(site);
      logStderr(`[${alias}] permalinks: ${state.permalinks[alias] ? 'pretty' : 'plain'}`);
    }
    const auth = Buffer.from(`${site.username}:${site.appPassword}`).toString('base64');
    const headers = { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Basic ${auth}` };
    if (state.session[alias]) headers['Mcp-Session-Id'] = state.session[alias];
    const payload = JSON.stringify(message);
    const isHttps = site.parsed.protocol === 'https:';
    const options = {
      hostname: site.parsed.hostname,
      port: site.parsed.port || (isHttps ? 443 : 80),
      path: mcpPath(alias), method: 'POST',
      headers: { ...headers, 'Content-Length': Buffer.byteLength(payload) },
    };
    const res = await doHttpRequest(site, options, payload);
    if (res.headers['mcp-session-id']) state.session[alias] = res.headers['mcp-session-id'];
    return res;
  }

  // Transparently initialize a site that has no session yet (so switching sites
  // mid-conversation "just works" without the client re-initializing).
  async function ensureSession(alias) {
    if (state.session[alias] || !state.initializeMessage) return;
    logStderr(`[${alias}] no session — sending transparent initialize`);
    try { await sendToSite(alias, state.initializeMessage); } catch (e) { logStderr(`[${alias}] init failed: ${e.message}`); }
  }

  async function handleMessage(line) {
    let message;
    try { message = JSON.parse(line); } catch {
      process.stdout.write(JSON.stringify({ jsonrpc: '2.0', error: { code: -32700, message: 'Parse error' }, id: null }) + '\n');
      return;
    }
    const method = message.method || '';
    const id = message.id ?? null;
    logStderr(`→ ${method} (id=${id}) [site=${state.active}]`);

    if (method === 'initialize') state.initializeMessage = message;

    // Local meta tools/call — never forwarded.
    if (method === 'tools/call' && isMetaCall(message.params?.name)) {
      const out = handleMetaCall(message.params.name, message.params.arguments || {}, state, id);
      process.stdout.write(JSON.stringify(out) + '\n');
      return;
    }

    try {
      if (method !== 'initialize') await ensureSession(state.active);
      const { body, headers, statusCode } = await sendToSite(state.active, message);

      if (id === null && !method.startsWith('initialize')) return; // notification

      if (statusCode >= 400) {
        try {
          const parsed = JSON.parse(body);
          if (parsed.error || parsed.jsonrpc) { process.stdout.write(JSON.stringify(parsed) + '\n'); return; }
        } catch { /* fall through */ }
        process.stdout.write(JSON.stringify({ jsonrpc: '2.0', error: { code: -32603, message: `HTTP ${statusCode}`, data: { body: body.substring(0, 1000) } }, id }) + '\n');
        return;
      }

      const trimmed = body.trim();
      if (!trimmed) return;
      let output;
      try {
        const parsed = JSON.parse(trimmed);
        if (method === 'initialize' && MCP_PROTOCOL_VERSION && parsed.result?.protocolVersion) {
          parsed.result.protocolVersion = MCP_PROTOCOL_VERSION;
        }
        if (method === 'tools/list') injectMetaTools(parsed, siteCount);
        output = JSON.stringify(parsed);
      } catch {
        // Never forward non-JSON upstream bodies to stdout — that corrupts the
        // stdio JSON-RPC framing and kills the connection.
        logStderr(`Non-JSON upstream response for id=${id}: ${trimmed.slice(0, 2000)}`);
        output = JSON.stringify({
          jsonrpc: '2.0',
          id,
          error: { code: -32603, message: 'Upstream returned a non-JSON response', data: { body: trimmed.slice(0, 1000) } },
        });
      }
      process.stdout.write(output + '\n');
    } catch (err) {
      logStderr(`← error: ${err.message}`);
      process.stdout.write(JSON.stringify({ jsonrpc: '2.0', error: { code: -32603, message: 'Proxy error', data: { details: err.message } }, id }) + '\n');
    }
  }

  logStderr('MCP Tools for Elementor proxy starting');
  logStderr(`Sites: ${Object.keys(sites).join(', ')} (active: ${state.active})`);
  if (siteCount > 1) logStderr('Multi-site mode: emcp_list_sites / emcp_use_site available.');

  const rl = createInterface({ input: process.stdin, terminal: false });
  let pending = 0;
  rl.on('line', (line) => { const t = line.trim(); if (t) { pending++; handleMessage(t).finally(() => pending--); } });
  rl.on('close', async () => { while (pending > 0) await new Promise((r) => setTimeout(r, 50)); process.exit(0); });
  process.on('SIGINT', () => process.exit(0));
  process.on('SIGTERM', () => process.exit(0));
  process.on('uncaughtException', (err) => { logStderr(`Uncaught exception: ${err.message}`); process.exit(1); });
}

// ---------------------------------------------------------------------------
// Logging
// ---------------------------------------------------------------------------

function logStderr(message) {
  const line = `[${new Date().toISOString()}] ${message}`;
  process.stderr.write(line + '\n');
  if (MCP_LOG_FILE) { try { appendFileSync(MCP_LOG_FILE, line + '\n'); } catch { /* ignore */ } }
}
