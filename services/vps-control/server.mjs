import { createServer, request as httpRequest } from 'node:http';
import { readFile, writeFile, mkdir, rename } from 'node:fs/promises';
import { join } from 'node:path';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { randomBytes, timingSafeEqual } from 'node:crypto';
import { connect as tlsConnect } from 'node:tls';
import { previewPasswordHash, previewPasswordMatches } from './auth.mjs';

const exec = promisify(execFile);
const directory = process.env.VPS_DATA_DIR || '/data';
const dynamic = process.env.TRAEFIK_DYNAMIC_DIR || '/dynamic';
const network = process.env.VPS_DOCKER_NETWORK || 'grzywniak-edge';
const listenPort = Number(process.env.VPS_LISTEN_PORT || 3010);
const token = process.env.VPS_CONTROL_TOKEN || '';
const organization = process.env.GITHUB_ORG || 'Grzywniak';
const previewDomain = process.env.PREVIEW_BASE_DOMAIN || 'grzywniak.pl';
const productionDomain = process.env.PRODUCTION_BASE_DOMAIN || 'grzywniak.pl';
const controlHost = process.env.VPS_CONTROL_HOST || '';
const stateFile = join(directory, 'state.json');
const idPattern = /^[a-f0-9]{32}$/;
const digestPattern = /^sha256:[a-f0-9]{64}$/;
const commitPattern = /^[a-f0-9]{40}$/;
const feedbackTokenPattern = /^[a-f0-9]{48}$/;
const publicApiHost = process.env.PUBLIC_API_HOST || 'api.grzywniak.pl';
const feedbackScript = await readFile(new URL('./feedback.js', import.meta.url));
const html2canvasScript = await readFile(new URL('./html2canvas.min.js', import.meta.url));
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
  const chunks = [];
  let size = 0;
  for await (const chunk of request) {
    size += chunk.length;
    if (size > 900000) throw new Error('Żądanie jest zbyt duże.');
    chunks.push(chunk);
  }
  const text = Buffer.concat(chunks).toString('utf8');
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
  const upstream = project.kind === 'preview' ? 'http://vps-control:3010' : (current ? `http://${current.container}:${current.appPort}` : 'http://vps-control:3010');
  const authMiddleware = project.kind === 'preview' ? `${name}-preview-auth` : null;
  const previewRoutes = project.kind === 'preview' ? `    ${name}-feedback-config:\n      rule: "Host(\`${project.hostname}\`) && Path(\`/.well-known/grzywniak/feedback-config\`)"\n      entryPoints: [websecure]\n      middlewares: [${name}-preview-auth, ${name}-feedback-config-path]\n      service: ${name}-metadata\n      priority: 110\n      tls:\n        certResolver: cf\n` : '';
  const previewMiddlewares = authMiddleware ? `    ${name}-preview-auth:\n      forwardAuth:\n        address: http://vps-control:3010/internal/preview-auth?key=${encodeURIComponent(token)}\n        trustForwardHeader: true\n        authRequestHeaders:\n          - Authorization\n          - X-Forwarded-Host\n    ${name}-feedback-config-path:\n      replacePath:\n        path: /internal/feedback-config\n` : '';
  const appMiddlewares = authMiddleware ? `      middlewares: [${authMiddleware}]\n` : '';
  return `http:\n  routers:\n    ${name}-metadata:\n      rule: "Host(\`${project.hostname}\`) && Path(\`/.well-known/grzywniak/deployment\`)"\n      entryPoints: [websecure]\n      middlewares: [${name}-metadata-path]\n      service: ${name}-metadata\n      priority: 100\n      tls:\n        certResolver: cf\n${previewRoutes}    ${name}-app:\n      rule: "Host(\`${project.hostname}\`)"\n      entryPoints: [websecure]\n${appMiddlewares}      service: ${name}-app\n      priority: 1\n      tls:\n        certResolver: cf\n  middlewares:\n    ${name}-metadata-path:\n      replacePath:\n        path: /metadata/${project.projectId}/${project.kind}\n${previewMiddlewares}  services:\n    ${name}-metadata:\n      loadBalancer:\n        servers:\n          - url: http://vps-control:3010\n    ${name}-app:\n      loadBalancer:\n        servers:\n          - url: ${upstream}\n`;
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
const previewProjectForHost = (host) => {
  const hostname = String(host || '').split(',')[0].split(':')[0].trim().toLowerCase();
  return Object.values(state.projects).find((item) => item.kind === 'preview' && item.hostname === hostname);
};
const proxyPreview = (request, response, project) => {
  const deployment = project.current ? state.deployments[project.current] : null;
  if (!deployment) return json(response, 503, { message: 'Podgląd nie ma aktywnego wdrożenia.' });
  if (!previewPasswordMatches(project, request.headers.authorization || '')) {
    response.writeHead(401, { 'www-authenticate': 'Basic realm="Project preview", charset="UTF-8"', 'cache-control': 'no-store', 'content-type': 'text/plain; charset=utf-8' });
    return response.end('Wymagane hasło do podglądu.');
  }
  if (request.method === 'GET' && request.url?.split('?')[0] === '/grzywniak-feedback.js') {
    response.writeHead(200, { 'content-type': 'text/javascript; charset=utf-8', 'content-length': feedbackScript.length, 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' });
    return response.end(feedbackScript);
  }
  if (request.method === 'GET' && request.url?.split('?')[0] === '/grzywniak-html2canvas.js') {
    response.writeHead(200, { 'content-type': 'text/javascript; charset=utf-8', 'content-length': html2canvasScript.length, 'cache-control': 'public, max-age=3600', 'x-content-type-options': 'nosniff' });
    return response.end(html2canvasScript);
  }
  const headers = { ...request.headers, host: `${deployment.container}:${deployment.appPort}`, 'accept-encoding': '' };
  for (const name of ['connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization', 'te', 'trailer', 'transfer-encoding', 'upgrade']) delete headers[name];
  const upstream = httpRequest({ hostname: deployment.container, port: deployment.appPort, path: request.url || '/', method: request.method, headers, timeout: 30000 }, (upstreamResponse) => {
    const responseHeaders = { ...upstreamResponse.headers };
    for (const name of ['connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization', 'te', 'trailer', 'transfer-encoding', 'upgrade']) delete responseHeaders[name];
    const contentType = String(responseHeaders['content-type'] || '').toLowerCase();
    if (request.method === 'GET' && upstreamResponse.statusCode === 200 && contentType.includes('text/html')) {
      const chunks = []; let size = 0;
      upstreamResponse.on('data', (chunk) => { size += chunk.length; if (size > 2 * 1024 * 1024) { upstream.destroy(new Error('Dokument HTML podglądu przekracza limit.')); return; } chunks.push(chunk); });
      upstreamResponse.on('end', () => {
        const html = Buffer.concat(chunks).toString('utf8');
        const captureTag = '<script src="/grzywniak-html2canvas.js" defer></script>';
        const feedbackTag = '<script src="/grzywniak-feedback.js" defer></script>';
        let injected = html;
        if (!/<script\s+src=["']\/grzywniak-feedback\.js["']/i.test(injected)) injected = /<\/body\s*>/i.test(injected) ? injected.replace(/<\/body\s*>/i, `${captureTag}${feedbackTag}</body>`) : `${injected}${captureTag}${feedbackTag}`;
        else if (!/<script\s+src=["']\/grzywniak-html2canvas\.js["']/i.test(injected)) injected = injected.replace(/(<script\s+src=["']\/grzywniak-feedback\.js["'][^>]*>\s*<\/script>)/i, `${captureTag}$1`);
        delete responseHeaders['content-length']; delete responseHeaders.etag;
        responseHeaders['cache-control'] = 'private, no-cache';
        responseHeaders['content-length'] = Buffer.byteLength(injected);
        response.writeHead(upstreamResponse.statusCode || 200, responseHeaders);
        response.end(injected);
      });
    } else {
      response.writeHead(upstreamResponse.statusCode || 502, responseHeaders);
      upstreamResponse.pipe(response);
    }
  });
  upstream.on('timeout', () => upstream.destroy(new Error('Przekroczono czas odpowiedzi aplikacji.')));
  upstream.on('error', () => { if (!response.headersSent) json(response, 502, { message: 'Nie udało się pobrać podglądu aplikacji.' }); else response.destroy(); });
  request.pipe(upstream);
};
const submitPreviewFeedback = async (request, response, project) => {
  if (!previewPasswordMatches(project, request.headers.authorization || '')) {
    response.writeHead(401, { 'www-authenticate': 'Basic realm="Project preview", charset="UTF-8"', 'cache-control': 'no-store' });
    return response.end();
  }
  if (!feedbackTokenPattern.test(project.feedbackToken || '') || typeof project.feedbackUrl !== 'string') return json(response, 409, { message: 'Formularz uwag nie jest aktywny dla tej wersji.' });
  let endpoint;
  try { endpoint = new URL(project.feedbackUrl); } catch { return json(response, 502, { message: 'Adres formularza uwag jest nieprawidłowy.' }); }
  if (endpoint.protocol !== 'https:' || endpoint.hostname !== publicApiHost || !endpoint.pathname.endsWith('/project-feedback.php')) return json(response, 502, { message: 'Adres formularza uwag nie wskazuje zaufanego API.' });
  let payload;
  try { payload = await readBody(request); } catch { return json(response, 400, { message: 'Nie udało się odczytać zgłoszenia. Sprawdź treść i spróbuj ponownie.' }); }
  const closing = payload.action === 'close';
  if (project.feedbackClosedDigest === project.feedbackDigest && project.feedbackDigest && !closing) return json(response, 409, { message: 'Zglaszanie uwag do tej wersji zostalo juz zakonczone.' });
  const safePayload = { project: project.projectId, token: project.feedbackToken, ...(closing ? { action: 'close' } : { message: typeof payload.message === 'string' ? payload.message : '', page_url: typeof payload.page_url === 'string' ? payload.page_url : '', annotation: payload.annotation }) };
  try {
    const result = await fetch(endpoint, { method: 'POST', headers: { 'content-type': 'application/json', origin: `https://${project.hostname}`, accept: 'application/json' }, body: JSON.stringify(safePayload), signal: AbortSignal.timeout(20000) });
    const text = await result.text();
    let body;
    try { body = JSON.parse(text); } catch { body = null; }
    if (!result.ok) return json(response, result.status, { ...(body && typeof body === 'object' ? body : {}), message: typeof body?.message === 'string' ? body.message : `Serwer formularza odrzucił zgłoszenie (HTTP ${result.status}). Spróbuj ponownie.` });
    if (closing) await serialized(async () => { project.feedbackClosedDigest = project.feedbackDigest; await save(); });
    return json(response, closing ? 200 : 201, body && typeof body.message === 'string' ? body : { message: closing ? 'Lista uwag zostala zamknieta.' : 'Uwaga zostala zapisana.' });
  } catch {
    return json(response, 502, { message: 'Nie udało się połączyć z API uwag. Twoje zgłoszenie pozostało w formularzu; spróbuj ponownie.' });
  }
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
    if (request.method === 'GET' && url.pathname === '/internal/preview-auth') {
      if (url.searchParams.get('key') !== token) return json(response, 404, { message: 'Nie znaleziono zasobu.' });
      const hostname = String(request.headers['x-forwarded-host'] || '').split(',')[0].trim().replace(/:\d+$/, '').toLowerCase();
      const project = Object.values(state.projects).find((item) => item.kind === 'preview' && item.hostname === hostname);
      if (!project) return json(response, 404, { message: 'Nie znaleziono podglądu.' });
      if (previewPasswordMatches(project, request.headers.authorization || '')) { response.writeHead(200, { 'cache-control': 'no-store' }); return response.end(); }
      response.writeHead(401, { 'www-authenticate': 'Basic realm="Project preview", charset="UTF-8"', 'cache-control': 'no-store', 'content-type': 'text/plain; charset=utf-8' });
      return response.end('Wymagane hasło do podglądu.');
    }
    if (request.method === 'GET' && url.pathname === '/internal/feedback-config') {
      const hostname = String(request.headers['x-forwarded-host'] || request.headers.host || '').split(',')[0].split(':')[0].trim().toLowerCase();
      const project = Object.values(state.projects).find((item) => item.kind === 'preview' && item.hostname === hostname);
      if (!project || !previewPasswordMatches(project, request.headers.authorization || '') || !feedbackTokenPattern.test(project.feedbackToken || '')) return json(response, 404, { message: 'Konfiguracja uwag nie jest dostępna.' });
      return json(response, 200, { projectId: project.projectId, feedbackToken: project.feedbackToken, feedbackUrl: project.feedbackUrl || '', feedbackClosed: project.feedbackClosedDigest === project.feedbackDigest && Boolean(project.feedbackDigest) });
    }
    const previewProject = previewProjectForHost(request.headers['x-forwarded-host'] || request.headers.host);
    if (previewProject && request.method === 'POST' && url.pathname === '/.well-known/grzywniak/feedback-submit') return await submitPreviewFeedback(request, response, previewProject);
    if (previewProject && !url.pathname.startsWith('/internal/') && !url.pathname.startsWith('/metadata/')) return proxyPreview(request, response, previewProject);
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
      if (body.kind === 'preview' && (typeof body.previewPassword !== 'string' || body.previewPassword.length < 16 || body.previewPassword.length > 128 || typeof body.previewUsername !== 'string' || body.previewUsername.length < 3 || body.previewUsername.length > 254 || /[:\u0000-\u001f\u007f]/.test(body.previewUsername))) return json(response, 400, { message: 'Podgląd wymaga loginu klienta i unikalnego hasła.' });
      const project = await serialized(async () => {
        const key = `${body.projectId}:${body.kind}`;
        const existing = state.projects[key];
        if (existing && (existing.hostname !== body.hostname || existing.repository !== body.repository)) throw new Error('Zasób jest przypisany do innego projektu.');
        const value = existing || { projectId: body.projectId, kind: body.kind, hostname: body.hostname, repository: body.repository, current: null, previous: null };
        if (body.kind === 'preview') { value.previewAuthUser = body.previewUsername; Object.assign(value, previewPasswordHash(body.previewPassword)); }
        state.projects[key] = value;
        await writeRoute(value); await save();
        return value;
      });
      const cert = await readyCertificate(project.hostname);
      return json(response, cert ? 200 : 503, { projectId: project.projectId, hostname: project.hostname, ready: cert, routingReady: true, tlsReady: cert });
    }
    if (request.method === 'POST' && url.pathname === '/v1/projects/feedback-token') {
      const body = await readBody(request);
      if (!idPattern.test(body.projectId) || !feedbackTokenPattern.test(body.feedbackToken) || typeof body.feedbackUrl !== 'string' || body.feedbackUrl.length > 2000 || !digestPattern.test(body.feedbackDigest || '')) return json(response, 400, { message: 'Niepoprawna konfiguracja formularza uwag.' });
      const project = await serialized(async () => {
        const value = state.projects[`${body.projectId}:preview`];
        if (!value || value.hostname !== expectedHostname(body.projectId, 'preview') || !value.previewAuthHash) throw new Error('Najpierw zabezpiecz środowisko podglądu hasłem.');
        if (value.feedbackDigest !== body.feedbackDigest) value.feedbackClosedDigest = null;
        value.feedbackToken = body.feedbackToken; value.feedbackUrl = body.feedbackUrl; value.feedbackDigest = body.feedbackDigest;
        await save();
        return value;
      });
      return json(response, 200, { projectId: project.projectId, ready: true });
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
