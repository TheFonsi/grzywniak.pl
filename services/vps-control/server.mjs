import { createServer } from 'node:http';
import { readFile, writeFile, mkdir, rename } from 'node:fs/promises';
import { join } from 'node:path';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { timingSafeEqual } from 'node:crypto';
import { connect as tlsConnect } from 'node:tls';

const exec = promisify(execFile);
const directory = process.env.VPS_DATA_DIR || '/data';
const dynamic = process.env.TRAEFIK_DYNAMIC_DIR || '/dynamic';
const network = process.env.VPS_DOCKER_NETWORK || 'grzywniak-edge';
const listenPort = Number(process.env.VPS_LISTEN_PORT || 3010);
const token = process.env.VPS_CONTROL_TOKEN || '';
const organization = process.env.GITHUB_ORG || 'Grzywniak';
const previewDomain = process.env.PREVIEW_BASE_DOMAIN || 'preview.grzywniak.pl';
const productionDomain = process.env.PRODUCTION_BASE_DOMAIN || 'app.grzywniak.pl';
const controlHost = process.env.VPS_CONTROL_HOST || '';
const stateFile = join(directory, 'state.json');
const idPattern = /^[a-f0-9]{32}$/;
const digestPattern = /^sha256:[a-f0-9]{64}$/;
const commitPattern = /^[a-f0-9]{40}$/;
let state = { projects: {}, deployments: {} };
let mutation = Promise.resolve();

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const json = (response, status, data) => {
  response.writeHead(status, { 'content-type': 'application/json; charset=utf-8', 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' });
  response.end(JSON.stringify(data));
};
const validKind = (kind) => kind === 'preview' || kind === 'production';
const expectedHostname = (id, kind) => `${kind === 'preview' ? 'p' : 'a'}-${id.slice(0, 12)}.${kind === 'preview' ? previewDomain : productionDomain}`;
const repositoryName = (url) => {
  const prefix = `https://github.com/${organization}/`;
  if (typeof url !== 'string' || !url.startsWith(prefix)) return null;
  const name = url.slice(prefix.length);
  return /^[A-Za-z0-9_.-]{1,100}$/.test(name) ? name : null;
};
const authenticated = (request) => {
  const supplied = request.headers.authorization?.replace(/^Bearer /, '') || '';
  const a = Buffer.from(supplied); const b = Buffer.from(token);
  return token.length >= 32 && a.length === b.length && timingSafeEqual(a, b);
};
const readBody = async (request) => {
  let text = '';
  for await (const chunk of request) {
    text += chunk;
    if (text.length > 16000) throw new Error('Żądanie jest zbyt duże.');
  }
  const body = JSON.parse(text || '{}');
  if (!body || typeof body !== 'object' || Array.isArray(body)) throw new Error('Niepoprawne dane JSON.');
  return body;
};
const save = async () => {
  await mkdir(directory, { recursive: true });
  const temp = `${stateFile}.tmp`;
  await writeFile(temp, JSON.stringify(state), { mode: 0o600 });
  await rename(temp, stateFile);
};
const docker = async (...args) => {
  const result = await exec('docker', args, { timeout: 120000, maxBuffer: 1024 * 1024 });
  return result.stdout.trim();
};
const routeName = (id, kind) => `gw-${id.slice(0, 16)}-${kind}`;
const routeFile = (id, kind) => join(dynamic, `${routeName(id, kind)}.yml`);
const routeConfig = (project) => {
  const name = routeName(project.projectId, project.kind);
  const current = project.current ? state.deployments[project.current] : null;
  const upstream = current ? `http://${current.container}:${current.appPort}` : 'http://vps-control:3010';
  return `http:\n  routers:\n    ${name}-metadata:\n      rule: "Host(\`${project.hostname}\`) && Path(\`/.well-known/grzywniak/deployment\`)"\n      entryPoints: [websecure]\n      middlewares: [${name}-metadata-path]\n      service: ${name}-metadata\n      priority: 100\n      tls:\n        certResolver: cf\n    ${name}-app:\n      rule: "Host(\`${project.hostname}\`)"\n      entryPoints: [websecure]\n      service: ${name}-app\n      priority: 1\n      tls:\n        certResolver: cf\n  middlewares:\n    ${name}-metadata-path:\n      replacePath:\n        path: /metadata/${project.projectId}/${project.kind}\n  services:\n    ${name}-metadata:\n      loadBalancer:\n        servers:\n          - url: http://vps-control:3010\n    ${name}-app:\n      loadBalancer:\n        servers:\n          - url: ${upstream}\n`;
};
const writeRoute = async (project) => {
  await mkdir(dynamic, { recursive: true });
  const file = routeFile(project.projectId, project.kind);
  const temp = `${file}.tmp`;
  await writeFile(temp, routeConfig(project));
  await rename(temp, file);
};
const tlsReady = (hostname) => new Promise((resolve) => {
  const socket = tlsConnect({ host: 'traefik', port: 443, servername: hostname, rejectUnauthorized: true, timeout: 5000 }, () => { socket.end(); resolve(true); });
  socket.on('error', () => resolve(false));
  socket.on('timeout', () => { socket.destroy(); resolve(false); });
});
const readyCertificate = async (hostname) => {
  for (let attempt = 0; attempt < 10; attempt++) {
    if (await tlsReady(hostname)) return true;
    await sleep(1500);
  }
  return false;
};
const containerName = (id, kind, digest) => `gw-${id.slice(0, 16)}-${kind}-${digest.slice(7, 19)}`;
const ensureContainer = async (name, image) => {
  try {
    const existing = JSON.parse(await docker('inspect', '--type=container', name));
    if (existing[0]?.Config?.Image !== image) throw new Error('Nazwa kontenera jest zajęta przez inny obraz.');
    if (!existing[0]?.State?.Running) await docker('start', name);
    return;
  } catch (error) { if (error instanceof Error && error.message === 'Nazwa kontenera jest zajęta przez inny obraz.') throw error; }
  await docker('pull', image);
  await docker('run', '-d', '--name', name, '--network', network, '--read-only', '--tmpfs', '/tmp:rw,noexec,nosuid,size=64m', '--tmpfs', '/var/cache/nginx:rw,nosuid,size=32m', '--tmpfs', '/var/run:rw,nosuid,size=8m', '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges', '--memory', '512m', '--cpus', '1', '--pids-limit', '128', '--restart', 'unless-stopped', image);
};
const waitHealth = async (container, port, path) => {
  for (let attempt = 0; attempt < 20; attempt++) {
    try {
      const response = await fetch(`http://${container}:${port}${path}`, { signal: AbortSignal.timeout(3000) });
      if (response.status === 200) return;
    } catch {}
    await sleep(1000);
  }
  throw new Error('Nowy kontener nie przeszedł kontroli zdrowia.');
};
const serialized = (work) => {
  const result = mutation.then(work);
  mutation = result.catch(() => {});
  return result;
};

await mkdir(directory, { recursive: true });
try { state = JSON.parse(await readFile(stateFile, 'utf8')); } catch {}
if (!state.projects || !state.deployments) throw new Error('Uszkodzony stan VPS.');
if (!token || token.length < 32 || !/^[a-z0-9.-]+$/.test(controlHost) || !/^[a-z0-9.-]+$/.test(previewDomain) || !/^[a-z0-9.-]+$/.test(productionDomain)) throw new Error('Ustaw poprawne domeny i VPS_CONTROL_TOKEN (min. 32 znaki).');
await mkdir(dynamic, { recursive: true });
await writeFile(join(dynamic, 'vps-control.yml'), `http:\n  routers:\n    vps-control:\n      rule: "Host(\`${controlHost}\`)"\n      entryPoints: [websecure]\n      service: vps-control\n      tls:\n        certResolver: cf\n  services:\n    vps-control:\n      loadBalancer:\n        servers:\n          - url: http://vps-control:3010\n`);
for (const project of Object.values(state.projects)) await writeRoute(project);

createServer(async (request, response) => {
  try {
    const url = new URL(request.url || '/', 'http://localhost');
    const metadata = /^\/metadata\/([a-f0-9]{32})\/(preview|production)$/.exec(url.pathname);
    if (request.method === 'GET' && metadata) {
      const project = state.projects[`${metadata[1]}:${metadata[2]}`];
      if (!project || request.headers.host !== project.hostname) return json(response, 404, { message: 'Nie znaleziono zasobu.' });
      const deployment = project?.current ? state.deployments[project.current] : null;
      return json(response, deployment ? 200 : 503, deployment ? { projectId: project.projectId, imageDigest: deployment.imageDigest, commitSha: deployment.commitSha } : { message: 'Brak wdrożenia.' });
    }
    if (!authenticated(request)) return json(response, 401, { message: 'Brak dostępu.' });
    if (request.method === 'POST' && url.pathname === '/v1/projects') {
      const body = await readBody(request);
      if (!idPattern.test(body.projectId) || !validKind(body.kind) || body.hostname !== expectedHostname(body.projectId, body.kind) || !repositoryName(body.repository) || request.headers['idempotency-key'] !== `${body.projectId}:${body.kind}`) return json(response, 400, { message: 'Niepoprawna konfiguracja projektu.' });
      const project = await serialized(async () => {
        const key = `${body.projectId}:${body.kind}`;
        const existing = state.projects[key];
        if (existing && (existing.hostname !== body.hostname || existing.repository !== body.repository)) throw new Error('Zasób jest przypisany do innego projektu.');
        const value = existing || { projectId: body.projectId, kind: body.kind, hostname: body.hostname, repository: body.repository, current: null, previous: null };
        state.projects[key] = value;
        await writeRoute(value); await save();
        return value;
      });
      const cert = await readyCertificate(project.hostname);
      return json(response, cert ? 200 : 503, { projectId: project.projectId, hostname: project.hostname, ready: cert, routingReady: true, tlsReady: cert });
    }
    if (request.method === 'POST' && url.pathname === '/v1/deployments') {
      const body = await readBody(request);
      const name = repositoryName(body.repository);
      const port = Number(body.appPort);
      if (!idPattern.test(body.projectId) || !validKind(body.kind) || !name || body.hostname !== expectedHostname(body.projectId, body.kind) || !commitPattern.test(body.commitSha) || !digestPattern.test(body.imageDigest) || !Number.isInteger(port) || port < 1 || port > 65535 || typeof body.healthPath !== 'string' || !/^\/[A-Za-z0-9/_-]{1,100}$/.test(body.healthPath) || request.headers['idempotency-key'] !== `${body.projectId}:${body.kind}:${body.imageDigest}`) return json(response, 400, { message: 'Niepoprawna wersja wdrożenia.' });
      const result = await serialized(async () => {
        const key = `${body.projectId}:${body.kind}`;
        const project = state.projects[key];
        if (!project || project.hostname !== body.hostname || project.repository !== body.repository) throw new Error('Środowisko projektu nie jest gotowe.');
        const deploymentId = `d${body.projectId.slice(0, 8)}${body.kind[0]}${body.imageDigest.slice(7, 23)}`;
        const container = containerName(body.projectId, body.kind, body.imageDigest);
        const image = `ghcr.io/${organization.toLowerCase()}/${name.toLowerCase()}@${body.imageDigest}`;
        await ensureContainer(container, image);
        await waitHealth(container, port, body.healthPath);
        if (project.current !== deploymentId) project.previous = project.current;
        project.current = deploymentId;
        state.deployments[deploymentId] = { deploymentId, projectId: body.projectId, kind: body.kind, hostname: body.hostname, repository: body.repository, commitSha: body.commitSha, imageDigest: body.imageDigest, appPort: port, healthPath: body.healthPath, container };
        await save(); await writeRoute(project);
        return state.deployments[deploymentId];
      });
      return json(response, 200, { deploymentId: result.deploymentId, hostname: result.hostname, imageDigest: result.imageDigest, ready: true });
    }
    const rollback = /^\/v1\/deployments\/([A-Za-z0-9_-]{4,100})\/rollback$/.exec(url.pathname);
    if (request.method === 'POST' && rollback) {
      const result = await serialized(async () => {
        const deployment = state.deployments[rollback[1]];
        if (!deployment) throw new Error('Nie znaleziono wdrożenia.');
        const project = state.projects[`${deployment.projectId}:${deployment.kind}`];
        if (project.current === deployment.deploymentId) { project.current = project.previous; project.previous = null; deployment.rolledBack = true; await save(); await writeRoute(project); }
        else if (!deployment.rolledBack) throw new Error('To wdrożenie nie jest aktywne.');
        return { rolledBack: true };
      });
      return json(response, 200, result);
    }
    return json(response, 404, { message: 'Nie znaleziono zasobu.' });
  } catch (error) {
    console.error('VPS control:', error instanceof Error ? error.message : 'unknown error');
    return json(response, 500, { message: 'Operacja VPS nie została ukończona.' });
  }
}).listen(listenPort, '0.0.0.0');
