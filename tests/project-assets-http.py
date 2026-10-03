"""Isolated HTTP checks for private project material upload and approval."""
from pathlib import Path
import base64, http.cookiejar, json, os, shutil, socket, subprocess, tempfile, time
import urllib.request, urllib.error

root = Path(__file__).resolve().parents[1]
tmp_root = root / "tmp"
tmp_root.mkdir(exist_ok=True)
work = Path(tempfile.mkdtemp(prefix="project-assets-http-", dir=tmp_root))
(work / "api").mkdir()
for source in (root / "api").glob("*.php"):
    shutil.copy2(source, work / "api" / source.name)
env = dict(os.environ, ADMIN_USERNAME="assets-test", ADMIN_PASSWORD="test-password", PUBLIC_API_URL="http://127.0.0.1")
(work / ".env").write_text("ADMIN_USERNAME=assets-test\nADMIN_PASSWORD=test-password\nPUBLIC_API_URL=http://127.0.0.1\n", encoding="utf-8")
seed = r'''
require 'api/project-model.php';
$id=bin2hex(random_bytes(16));
$session=['id'=>$id,'status'=>'COMPLETED','createdAt'=>time(),'updatedAt'=>time(),'projectState'=>['businessProblem'=>'Asset test','contactName'=>'Test client'],'internalAnalysis'=>['status'=>'COMPLETED','missingInformation'=>[]],'adminDecisions'=>[]];
writeSession($session); projectSave($id,[]); file_put_contents(__DIR__.'/session-id.txt',$id);
'''
subprocess.run(["php", "-r", seed], cwd=work, env=env, check=True)
session_id = (work / "session-id.txt").read_text(encoding="utf-8").strip()
sock = socket.socket(); sock.bind(("127.0.0.1", 0)); port = sock.getsockname()[1]; sock.close()
server_log = open(work / "server.log", "w", encoding="utf-8")
server = subprocess.Popen(["php", "-S", f"127.0.0.1:{port}", "-t", str(work)], cwd=work, env=env, stdout=server_log, stderr=server_log)
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
auth = "Basic " + base64.b64encode(b"assets-test:test-password").decode()

def call(url, data=None, headers=None):
    request = urllib.request.Request(url, data=data, headers={"Authorization": auth, **(headers or {})})
    try:
        with opener.open(request) as response: return response.status, response.read(), response.headers
    except urllib.error.HTTPError as error: return error.code, error.read(), error.headers

def multipart(fields, file_bytes=None):
    boundary = "----project-assets-boundary"
    parts = []
    for name, value in fields.items():
        parts.extend([f"--{boundary}\r\nContent-Disposition: form-data; name=\"{name}\"\r\n\r\n{value}\r\n".encode()])
    if file_bytes is not None:
        parts.extend([f"--{boundary}\r\nContent-Disposition: form-data; name=\"files[]\"; filename=\"logo.png\"\r\nContent-Type: image/png\r\n\r\n".encode(), file_bytes, b"\r\n"])
    parts.append(f"--{boundary}--\r\n".encode())
    return b"".join(parts), f"multipart/form-data; boundary={boundary}"

try:
    base = f"http://127.0.0.1:{port}"
    project_url = f"{base}/api/project-api.php?session={session_id}"
    for _ in range(50):
        code, raw, _ = call(project_url)
        if code == 200: break
        time.sleep(.1)
    else: raise RuntimeError("Isolated PHP server did not become ready.")
    csrf = json.loads(raw)["csrf"]
    png = base64.b64decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j2ioAAAAASUVORK5CYII=")
    body, content_type = multipart({"action":"upload", "category":"logo", "source_note":"Client supplied; approved for the project"}, png)
    code, raw, _ = call(f"{base}/api/project-assets.php?session={session_id}", body, {"Content-Type":content_type, "X-CSRF-Token":csrf})
    assert code == 200, (code, raw)
    code, raw, _ = call(project_url)
    snapshot = json.loads(raw)["project"]; asset = snapshot["assets"][0]
    assert asset["status"] == "received" and asset["category"] == "logo", asset
    code, downloaded, headers = call(f"{base}/api/project-assets.php?session={session_id}&asset={asset['asset_key']}&inline=1")
    assert code == 200 and downloaded == png and headers.get_content_type() == "image/png", (code, downloaded[:120], len(downloaded), len(png), headers.get_content_type())
    review, content_type = multipart({"action":"review", "asset_id":str(asset["id"]), "status":"approved", "admin_note":"Use as the project logo"})
    code, raw, _ = call(f"{base}/api/project-assets.php?session={session_id}", review, {"Content-Type":content_type, "X-CSRF-Token":csrf})
    assert code == 200, (code, raw)
    signed_script = f"require 'api/project-assets-lib.php'; echo projectAssetSignedUrl('{session_id}',projectAssets('{session_id}')[0],3600);"
    signed = subprocess.check_output(["php", "-r", signed_script], cwd=work, env=env, text=True).strip()
    signed = signed.replace("http://127.0.0.1", base, 1)
    signed = signed.replace("/project-assets.php?", "/api/project-assets.php?", 1)
    code, downloaded, headers = call(signed, headers={"Authorization":""})
    assert code == 200 and downloaded == png and headers.get_content_type() == "application/octet-stream", (code, headers)
    print("Project asset flow passed: upload is private, admin can review, and an approved file is downloadable with a signed link.")
finally:
    server.terminate(); server.wait(timeout=5); server_log.close(); shutil.rmtree(work, ignore_errors=True)
