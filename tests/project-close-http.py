from pathlib import Path
import base64, http.cookiejar, json, os, shutil, socket, sqlite3, subprocess, tempfile, time
import urllib.request, urllib.error

root = Path(__file__).resolve().parents[1]
tmp_root = root / "tmp"
tmp_root.mkdir(exist_ok=True)
work = Path(tempfile.mkdtemp(prefix="project-close-http-", dir=tmp_root))
(work / "api").mkdir()
for source in (root / "api").glob("*.php"):
    shutil.copy2(source, work / "api" / source.name)
env = dict(os.environ, ADMIN_USERNAME="close-test", ADMIN_PASSWORD="test-password")
(work / ".env").write_text("ADMIN_USERNAME=close-test\nADMIN_PASSWORD=test-password\n", encoding="utf-8")
seed = r"""
require 'api/project-model.php';
$id=bin2hex(random_bytes(16));
$session=['id'=>$id,'status'=>'COMPLETED','createdAt'=>time(),'updatedAt'=>time(),'projectState'=>['contactName'=>'Test','contactEmail'=>'client@example.invalid'],'internalAnalysis'=>['status'=>'COMPLETED'],'adminDecisions'=>[],'offer'=>[],'contract'=>[]];
writeSession($session); projectSave($id,[]); $db=projectDb();
$db->prepare("INSERT INTO project_jobs(session_id,kind,state,input,created_at,updated_at) VALUES(?,?,'queued','{}',?,?)")->execute([$id,'generate_plan',time(),time()]);
$db->prepare("INSERT INTO project_agent_tasks(session_id,task_key,title,role,dependencies,acceptance,state,updated_at) VALUES(?,?,?,?,?,?,'pending',?)")->execute([$id,'design','Design','ui','[]','review ready',time()]);
file_put_contents(__DIR__.'/session-id.txt',$id);
"""
subprocess.run(["php", "-r", seed], cwd=work, env=env, check=True)
session_id = (work / "session-id.txt").read_text(encoding="utf-8").strip()
sock = socket.socket(); sock.bind(("127.0.0.1", 0)); port = sock.getsockname()[1]; sock.close()
log = open(work / "server.log", "w", encoding="utf-8")
server = subprocess.Popen(["php", "-S", f"127.0.0.1:{port}", "-t", str(work)], cwd=work, env=env, stdout=log, stderr=log)
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
auth = "Basic " + base64.b64encode(b"close-test:test-password").decode()
def request(path, body=None, csrf=None):
    headers = {"Authorization": auth}
    if body is not None:
        headers["Content-Type"] = "application/json"
        if csrf: headers["X-CSRF-Token"] = csrf
    raw = json.dumps(body).encode("utf-8") if body is not None else None
    req = urllib.request.Request(f"http://127.0.0.1:{port}{path}", data=raw, headers=headers)
    try:
        with opener.open(req) as response: return response.status, json.loads(response.read())
    except urllib.error.HTTPError as error: return error.code, json.loads(error.read())
try:
    path = f"/api/project-api.php?session={session_id}"
    for _ in range(50):
        try:
            code, initial = request(path)
            if code == 200: break
        except urllib.error.URLError: time.sleep(.1)
    else: raise RuntimeError("PHP server did not start")
    csrf = initial["csrf"]
    invalid = request(path, {"action":"close_project", "reason":"test", "confirmStop":"on"}, csrf)
    assert invalid[0] == 409, invalid
    closed = request(path, {"action":"close_project", "reason":"Zamykam test po weryfikacji", "confirmStop":"on"}, csrf)
    assert closed[0] == 200, closed
    assert closed[1]["project"]["case"]["closedAt"]
    assert all(value in {"done", "cancelled"} for value in closed[1]["project"]["status"].values()), closed[1]["project"]["status"]
    blocked = request(path, {"action":"retry_job", "kind":"generate_plan"}, csrf)
    assert blocked[0] == 409, blocked
    db = sqlite3.connect(work / "api" / "storage" / "sessions.sqlite")
    jobs = db.execute("SELECT state FROM project_jobs WHERE session_id=?", (session_id,)).fetchall()
    tasks = db.execute("SELECT state FROM project_agent_tasks WHERE session_id=?", (session_id,)).fetchall()
    assert jobs == [("cancelled",)] and tasks == [("cancelled",)], (jobs, tasks)
    db.close()
    invoice = request(path, {"action":"save_invoice_info", "invoiceId":"", "number":"FV/1/2026", "issueDate":"2026-10-02", "dueDate":"2026-10-16", "paidDate":"", "amountGross":"123.45", "status":"issued", "note":"Informacyjny wpis"}, csrf)
    assert invoice[0] == 200, invoice
    saved = invoice[1]["project"]["case"]["invoiceInfo"]
    assert len(saved) == 1 and saved[0]["number"] == "FV/1/2026" and saved[0]["amountGross"] == 123.45, saved
    print("Project close and informational invoice HTTP checks passed.")
finally:
    server.terminate(); server.wait(timeout=5); log.close()
    shutil.rmtree(work, ignore_errors=True)

