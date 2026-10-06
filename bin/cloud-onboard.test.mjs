import { test } from 'node:test';
import assert from 'node:assert/strict';
import { validateManifest, siteArgs, runBatch } from './cloud-onboard.mjs';
const manifest = () => ({ version: 1, workspace: 'ws-1', sites: [{ id: 'one', path: '/srv/site one', user: 'admin', ssh: 'ops@example.test' }] });
test('arguments remain separate and do not invoke a shell', () => {
  const m = manifest();
  assert.deepEqual(siteArgs(m.sites[0], m.workspace, 'resume', true), ['--path=/srv/site one', '--user=admin', '--ssh=ops@example.test', 'emcp', 'cloud', 'onboard', '--phase=resume', '--workspace=ws-1', '--gateway']);
});
test('rejects duplicate targets, credentials, remote shell snippets and excessive fleets', () => {
  for (const alter of [m => m.sites.push({ ...m.sites[0], id: 'two' }), m => m.sites[0].password = 'secret',
    m => m.sites[0].ssh = 'host;touch /tmp/test', m => m.sites[0].path = '/site\n--exec=evil',
    m => m.sites = Array(1001).fill(m.sites[0])]) {
    const m = manifest(); alter(m); assert.throws(() => validateManifest(m));
  }
});
test('runs serially and continues after a failed site with explicit outcomes', async () => {
  const m = manifest(); m.sites.push({ id: 'two', path: '/srv/two', user: 'admin' });
  let running = 0; let max = 0; let calls = 0; const rows = [];
  const code = await runBatch(m, 'resume', true, async () => {
    running++; max = Math.max(max, running); await new Promise(r => setTimeout(r, 5)); running--;
    return ++calls === 1 ? { state: 'retry', reason: 'health_unavailable' } : { state: 'complete' };
  }, row => rows.push(row));
  assert.equal(max, 1); assert.equal(code, 1); assert.equal(rows[1].id, 'two'); assert.equal(rows[1].state, 'complete');
});
test('does not treat pending approval as completed onboarding', async () => {
  const rows = [];
  assert.equal(await runBatch(manifest(), 'prepare', false, async () => ({ state: 'awaiting_approval' }), row => rows.push(row)), 0);
  assert.equal(rows[0].state, 'awaiting_approval');
});

test('enrollment phase passes no grant secret through process arguments', async () => {
  const calls = [];
  assert.equal(await runBatch(manifest(), 'enroll', true, async args => {
    calls.push(args); return { state: 'complete' };
  }, () => {}), 0);
  assert.ok(calls[0].includes('--phase=enroll'));
  assert.ok(calls[0].includes('--gateway'));
  assert.ok(!calls[0].some(arg => arg.includes('emcp_enroll_')));
});
