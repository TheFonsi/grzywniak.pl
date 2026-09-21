import { spawn } from 'node:child_process';
import { mkdtemp, readFile, rm, realpath } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, sep } from 'node:path';
import { strict as assert } from 'node:assert';

const root = await mkdtemp(join(tmpdir(), 'grzywniak-vps-test-'));
const port = 31000 + Math.floor(Math.random() * 1000);
const token = 'test-token-' + 'x'.repeat(40);
const server = spawn(process.execPath, [resolve('services/vps-control/server.mjs')], { cwd: resolve('.'), env: { ...process.env, VPS_DATA_DIR: join(root, 'state'), TRAEFIK_DYNAMIC_DIR: join(root, 'dynamic'), VPS_LISTEN_PORT: String(port), VPS_CONTROL_TOKEN: token, VPS_CONTROL_HOST: 'control.grzywniak.pl' }, stdio: 'ignore' });
try {
  let ready = false;
  for (let attempt = 0; attempt < 30; attempt++) {
    try { const response = await fetch(`http://127.0.0.1:${port}/missing`); if (response.status === 401) { ready = true; break; } } catch {}
    await new Promise((done) => setTimeout(done, 100));
  }
  assert(ready, 'Usługa VPS nie wystartowała.');
  const unauthorized = await fetch(`http://127.0.0.1:${port}/v1/projects`, { method: 'POST' });
  assert.equal(unauthorized.status, 401);
  const invalid = await fetch(`http://127.0.0.1:${port}/v1/projects`, { method: 'POST', headers: { authorization: `Bearer ${token}`, 'content-type': 'application/json', 'idempotency-key': 'bad' }, body: JSON.stringify({ projectId: 'bad', hostname: 'bad', kind: 'preview', repository: 'https://github.com/Other/repo' }) });
  assert.equal(invalid.status, 400);
  const route = await readFile(join(root, 'dynamic', 'vps-control.yml'), 'utf8');
  assert(route.includes('Host(`control.grzywniak.pl`)'));
  console.log('VPS control auth and routing checks OK');
} finally {
  server.kill();
  const target = await realpath(root);
  const parent = await realpath(tmpdir());
  if (!target.startsWith(parent + sep)) throw new Error('Odmowa usunięcia katalogu poza temp.');
  await rm(target, { recursive: true, force: true });
}
