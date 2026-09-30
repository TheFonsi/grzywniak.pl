import { spawn } from 'node:child_process';
import { mkdtemp, readFile, rm, realpath, mkdir, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, sep } from 'node:path';
import { strict as assert } from 'node:assert';
import { previewPasswordHash, previewPasswordMatches } from '../services/vps-control/auth.mjs';

const root = await mkdtemp(join(tmpdir(), 'grzywniak-vps-test-'));
const port = 31000 + Math.floor(Math.random() * 1000);
const token = 'test-token-' + 'x'.repeat(40);
const projectId = 'a'.repeat(32);
const password = 'preview-test-password-123456';
const salt = '00112233445566778899aabbccddeeff';
const generatedAuth = { previewAuthUser: 'client', ...previewPasswordHash(password, salt) };
assert.equal(previewPasswordMatches(generatedAuth, `Basic ${Buffer.from(`client:${password}`).toString('base64')}`), true, 'Wygenerowane dane muszą być zapisane w polach odczytywanych przez bramkę logowania.');
assert.equal(previewPasswordMatches(generatedAuth, `Basic ${Buffer.from(`wrong:${password}`).toString('base64')}`), false);
await mkdir(join(root, 'state'), { recursive: true });
await writeFile(join(root, 'state', 'state.json'), JSON.stringify({ projects: { [`${projectId}:preview`]: { projectId, kind: 'preview', hostname: 'p-aaaaaaaaaaaa.grzywniak.pl', repository: 'https://github.com/Grzywniak/test', current: null, previous: null, ...generatedAuth, feedbackToken: 'b'.repeat(48), feedbackUrl: 'https://api.grzywniak.pl/api/project-feedback.php?project=x' } }, deployments: {} }));
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
  const controlRoute = await readFile(join(root, 'dynamic', 'vps-control.yml'), 'utf8');
  assert(controlRoute.includes('Host(`control.grzywniak.pl`)'));
  const route = await readFile(join(root, 'dynamic', 'gw-aaaaaaaaaaaaaaaa-preview.yml'), 'utf8');
  assert(route.includes('forwardAuth:'));
  assert(route.includes('/internal/preview-auth?key=' + encodeURIComponent(token)));
  const headers = { 'x-forwarded-host': 'p-aaaaaaaaaaaa.grzywniak.pl' };
  const blocked = await fetch(`http://127.0.0.1:${port}/internal/preview-auth?key=${encodeURIComponent(token)}`, { headers });
  assert.equal(blocked.status, 401, 'Podgląd bez hasła powinien zostać zablokowany.');
  const allowed = await fetch(`http://127.0.0.1:${port}/internal/preview-auth?key=${encodeURIComponent(token)}`, { headers: { ...headers, authorization: `Basic ${Buffer.from(`client:${password}`).toString('base64')}` } });
  assert.equal(allowed.status, 200, 'Poprawne hasło powinno odblokować podgląd.');
  const config = await fetch(`http://127.0.0.1:${port}/internal/feedback-config`, { headers: { ...headers, authorization: `Basic ${Buffer.from(`client:${password}`).toString('base64')}` } });
  assert.equal(config.status, 200);
  assert.equal((await config.json()).feedbackToken, 'b'.repeat(48));
  console.log('VPS control auth, password gate and feedback configuration checks OK');
} finally {
  server.kill();
  const target = await realpath(root);
  const parent = await realpath(tmpdir());
  if (!target.startsWith(parent + sep)) throw new Error('Odmowa usunięcia katalogu poza temp.');
  await rm(target, { recursive: true, force: true });
}
