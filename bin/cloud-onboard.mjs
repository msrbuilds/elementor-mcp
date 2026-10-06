#!/usr/bin/env node
/** Sequential operator-side WP-CLI runner. No credentials, shell snippets or web admin passwords in the manifest. */
import { spawn } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';

export function validateManifest(value) {
  if (!value || value.version !== 1 || !/^[a-zA-Z0-9_-]{1,200}$/.test(value.workspace ?? '') ||
      !Array.isArray(value.sites) || !value.sites.length || value.sites.length > 1000) throw new Error('invalid_manifest');
  const ids = new Set();
  const targets = new Set();
  for (const site of value.sites) {
    if (!site || Object.keys(site).some(key => !['id', 'path', 'user', 'ssh'].includes(key)) ||
        !/^[a-zA-Z0-9_-]{1,80}$/.test(site.id ?? '') || ids.has(site.id) ||
        typeof site.path !== 'string' || !site.path || site.path.length > 1024 || /[\r\n\0]/.test(site.path) ||
        !/^[a-zA-Z0-9_.@+-]{1,100}$/.test(site.user ?? '') ||
        (site.ssh !== undefined && !/^(?:[a-zA-Z0-9_.-]+@)?[a-zA-Z0-9.-]+(?::[0-9]{1,5})?$/.test(site.ssh))) throw new Error('invalid_site');
    const target = `${site.ssh ?? 'local'}:${site.path.replace(/[\\/]+$/, '')}`;
    if (targets.has(target)) throw new Error('duplicate_target');
    ids.add(site.id); targets.add(target);
  }
  return value;
}

export function siteArgs(site, workspace, phase, gateway) {
  return [`--path=${site.path}`, `--user=${site.user}`, ...(site.ssh ? [`--ssh=${site.ssh}`] : []),
    'emcp', 'cloud', 'onboard', `--phase=${phase}`, `--workspace=${workspace}`, ...(gateway ? ['--gateway'] : [])];
}

export function execute(args) {
  return new Promise(resolve => {
    // PHP + wp-cli.phar works on Windows without cmd.exe or PowerShell quoting.
    const php = process.env.EMCP_PHP;
    const phar = process.env.EMCP_WP_CLI_PHAR;
    const child = spawn(php && phar ? php : 'wp', php && phar ? [phar, ...args] : args,
      { shell: false, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
    let output = ''; let oversized = false; let timedOut = false;
    const timer = setTimeout(() => { timedOut = true; child.kill(); }, 90000);
    child.stdout.on('data', chunk => {
      output += chunk.toString();
      if (output.length > 256000) { oversized = true; output = ''; child.kill(); }
    });
    // Do not echo plugin diagnostics: third-party plugins can print credentials.
    child.stderr.resume();
    child.on('error', () => { clearTimeout(timer); resolve({ state: 'blocked', reason: 'wp_cli_unavailable' }); });
    child.on('close', code => {
      clearTimeout(timer);
      if (timedOut || oversized) return resolve({ state: 'retry', reason: timedOut ? 'timeout_check_before_retry' : 'invalid_output' });
      try {
        const lines = output.trim().split(/\r?\n/);
        const result = JSON.parse(lines.findLast(line => line.startsWith('{')) ?? '');
        if (result.contract_version !== 1 || !['ready', 'blocked', 'retry', 'awaiting_approval', 'connected', 'complete'].includes(result.state)) throw new Error();
        if (code !== 0 && !['blocked', 'retry'].includes(result.state)) throw new Error();
        // Explicit allowlist keeps tokens and third-party output out of reports.
        resolve(Object.fromEntries(['state', 'reason', 'site_url', 'site_uuid', 'plugin_version', 'license',
          'cloud_connected', 'cloud_bound', 'gateway_local', 'gateway_uploaded', 'identity_conflict',
          'health', 'authorization_url', 'expires_at'].filter(key => key in result).map(key => [key, result[key]])));
      } catch { resolve({ state: 'blocked', reason: 'invalid_output' }); }
    });
  });
}

export async function runBatch(manifest, phase, gateway, run = execute, emit = row => console.log(JSON.stringify(row))) {
  validateManifest(manifest);
  if (!['preflight', 'prepare', 'resume'].includes(phase)) throw new Error('invalid_phase');
  let failed = false;
  for (const site of manifest.sites) {
    const result = await run(siteArgs(site, manifest.workspace, phase, gateway));
    emit({ id: site.id, ...result });
    failed ||= result.state === 'blocked' || result.state === 'retry';
  }
  return failed ? 1 : 0;
}

async function main() {
  const [manifestPath, phase = 'preflight', flag] = process.argv.slice(2);
  if (!manifestPath || process.argv.length > 5 || (flag && flag !== '--gateway')) throw new Error('usage');
  const raw = await readFile(manifestPath, 'utf8');
  if (raw.length > 2000000) throw new Error('manifest_too_large');
  process.exitCode = await runBatch(JSON.parse(raw), phase, flag === '--gateway');
}
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  main().catch(() => { console.error('Use: node cloud-onboard.mjs manifest.json [preflight|prepare|resume] [--gateway]. Check the manifest and WP-CLI installation.'); process.exitCode = 1; });
}
