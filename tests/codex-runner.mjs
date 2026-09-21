import { spawn } from 'node:child_process';
import { mkdtemp, rm, realpath, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, sep } from 'node:path';
import { strict as assert } from 'node:assert';

const root = await mkdtemp(join(tmpdir(), 'grzywniak-runner-test-'));
const port = 32000 + Math.floor(Math.random() * 1000);
const token = 'test-token-' + 'x'.repeat(40);
const secret = join(root, 'github-token');
await writeFile(secret, 'test-github-token-' + 'x'.repeat(30), { mode: 0o600 });
const server = spawn(process.execPath, [resolve('services/codex-runner/server.mjs')], { cwd: resolve('.'), env: { ...process.env, RUNNER_WORK_DIR: join(root, 'work'), RUNNER_GITHUB_TOKEN_FILE: secret, RUNNER_PORT: String(port), RUNNER_TOKEN: token, RUNNER_INPUT_PLN_PER_MILLION: '1', RUNNER_PAUSED: 'true' }, stdio: 'ignore' });
try {
  let ready = false;
  for (let attempt = 0; attempt < 30; attempt++) {
    try { const response = await fetch(`http://127.0.0.1:${port}/missing`); if (response.status === 401) { ready = true; break; } } catch {}
    await new Promise((done) => setTimeout(done, 100));
  }
  assert(ready, 'Runner nie wystartował.');
  const invalid = await fetch(`http://127.0.0.1:${port}/v1/tasks`, { method: 'POST', headers: { authorization: `Bearer ${token}`, 'content-type': 'application/json', 'idempotency-key': 'bad' }, body: JSON.stringify({ id: 'bad', repository: 'https://github.com/Other/repo' }) });
  assert.equal(invalid.status, 400);
  const missing = await fetch(`http://127.0.0.1:${port}/v1/tasks/${'a'.repeat(32)}:test:1`, { headers: { authorization: `Bearer ${token}` } });
  assert.equal(missing.status, 404);
  const id = `${'a'.repeat(32)}:frontend:1`;
  const task = { id, projectId: 'a'.repeat(32), taskId: 'frontend', role: 'frontend', title: 'Zbuduj widok', acceptance: ['Widok działa'], approvedScope: 'Strona projektu', repository: 'https://github.com/Grzywniak/example', maxCostPln: 20, timeoutMinutes: 60 };
  const submit = () => fetch(`http://127.0.0.1:${port}/v1/tasks`, { method: 'POST', headers: { authorization: `Bearer ${token}`, 'content-type': 'application/json', 'idempotency-key': id }, body: JSON.stringify(task) });
  assert.equal((await submit()).status, 202);
  assert.equal((await submit()).status, 200);
  const queued = await (await fetch(`http://127.0.0.1:${port}/v1/tasks/${encodeURIComponent(id)}`, { headers: { authorization: `Bearer ${token}` } })).json();
  assert.equal(queued.state, 'queued');
  const cancel = await (await fetch(`http://127.0.0.1:${port}/v1/tasks/${encodeURIComponent(id)}/cancel`, { method: 'POST', headers: { authorization: `Bearer ${token}` } })).json();
  assert.equal(cancel.state, 'failed');
  assert.equal(cancel.costPln, 0);
  console.log('Codex runner auth and contract checks OK');
} finally {
  server.kill();
  const target = await realpath(root);
  const parent = await realpath(tmpdir());
  if (!target.startsWith(parent + sep)) throw new Error('Odmowa usunięcia katalogu poza temp.');
  await rm(target, { recursive: true, force: true });
}
