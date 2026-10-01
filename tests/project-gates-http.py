"""Isolated HTTP regression checks for the offer, contract and kickoff gates."""
from pathlib import Path
import base64, http.cookiejar, json, os, shutil, socket, subprocess, tempfile, time
import urllib.request, urllib.error

root = Path(__file__).resolve().parents[1]
tmp_root = root / "tmp"
tmp_root.mkdir(exist_ok=True)
work = Path(tempfile.mkdtemp(prefix="project-gates-http-", dir=tmp_root))
(work / "api").mkdir()
for source in (root / "api").glob("*.php"):
    shutil.copy2(source, work / "api" / source.name)

env = dict(os.environ, ADMIN_USERNAME="gate-test", ADMIN_PASSWORD="test-password", DISCOVERY_MAIL_MOCK="true")
(work / ".env").write_text(
    "ADMIN_USERNAME=gate-test\nADMIN_PASSWORD=test-password\nDISCOVERY_MAIL_MOCK=true\n",
    encoding="utf-8",
)
seed = r"""
require 'api/project-model.php';
$id=bin2hex(random_bytes(16));
$session=['id'=>$id,'status'=>'COMPLETED','createdAt'=>time(),'updatedAt'=>time(),
  'projectState'=>['contactName'=>'Jar-Bud test','contactEmail'=>'client@example.invalid'],
  'internalAnalysis'=>['status'=>'COMPLETED','version'=>1,'missingInformation'=>[]],
  'adminDecisions'=>[],
  'offer'=>['status'=>'DRAFT','version'=>1,'analysisVersion'=>1,'offerId'=>'OF-SIM-1',
    'contact'=>['name'=>'Jar-Bud test','email'=>'client@example.invalid'],'project'=>'Portfolio testowy'],
  'contract'=>['version'=>1,'offerVersion'=>99,'status'=>'DRAFT','pdfBase64'=>base64_encode("%PDF-1.4\nsimulated")]];
$session['offer']['sourceHash']=hash('sha256',json_encode([$session['projectState'],$session['internalAnalysis'],$session['adminDecisions']],JSON_UNESCAPED_UNICODE));
writeSession($session); projectSave($id,[]); file_put_contents(__DIR__.'/session-id.txt',$id);
"""
subprocess.run(["php", "-r", seed], cwd=work, env=env, check=True)
session_id = (work / "session-id.txt").read_text(encoding="utf-8").strip()

sock = socket.socket()
sock.bind(("127.0.0.1", 0))
port = sock.getsockname()[1]
sock.close()
server_log = open(work / "server.log", "w", encoding="utf-8")
server = subprocess.Popen(["php", "-S", f"127.0.0.1:{port}", "-t", str(work)], cwd=work, env=env, stdout=server_log, stderr=server_log)
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
auth = "Basic " + base64.b64encode(b"gate-test:test-password").decode()


def request(path, body=None, csrf=None):
    headers = {"Authorization": auth}
    if body is not None:
        headers["Content-Type"] = "application/json"
        if csrf:
            headers["X-CSRF-Token"] = csrf
    data = json.dumps(body).encode("utf-8") if body is not None else None
    req = urllib.request.Request(f"http://127.0.0.1:{port}{path}", data=data, headers=headers)
    try:
        with opener.open(req) as response:
            raw = response.read()
            return response.status, json.loads(raw) if raw else {}
    except urllib.error.HTTPError as error:
        raw = error.read()
        return error.code, json.loads(raw) if raw else {}


try:
    offer_path = f"/api/offer.php?session={session_id}"
    project_path = f"/api/project-api.php?session={session_id}"
    for _ in range(50):
        try:
            code, _ = request(offer_path)
            if code == 200:
                break
        except urllib.error.URLError:
            time.sleep(0.1)
    else:
        raise RuntimeError("Isolated PHP server did not become ready.")

    code, project = request(project_path)
    assert code == 200, (code, project)
    csrf = project["csrf"]

    start_before = request(project_path, {"action": "start", "scope": "Test portfolio project", "owner": "Test admin", "budget": 100}, csrf)
    confirm_before = request(project_path, {"action": "confirm_contract", "evidence": "Test agreement evidence"}, csrf)
    accept_draft = request(offer_path, {"action": "accept"})
    assert start_before[0] == 409 and confirm_before[0] == 409 and accept_draft[0] == 409

    review = request(offer_path, {"action": "review"})
    mock_send = request(offer_path, {"action": "send"})
    accepted = request(offer_path, {"action": "accept"})
    assert review[0] == 200 and mock_send[0] == 200 and accepted[0] == 200, (review, mock_send, accepted)
    assert accepted[1]["offer"]["status"] == "ACCEPTED"
    assert accepted[1]["offer"]["acceptedBy"] == "gate-test"

    stale_contract = request(project_path, {"action": "confirm_contract", "evidence": "Test agreement evidence"}, csrf)
    assert stale_contract[0] == 409
    fix_contract = r"""
require 'api/project-model.php';
$id=trim(file_get_contents(__DIR__.'/session-id.txt')); $session=readSession($id);
$session['contract']['offerVersion']=$session['offer']['version']; writeSession($session);
"""
    subprocess.run(["php", "-r", fix_contract], cwd=work, env=env, check=True)

    confirm = request(project_path, {"action": "confirm_contract", "evidence": "Test agreement evidence"}, csrf)
    start = request(project_path, {"action": "start", "scope": "Test portfolio site for construction leads", "owner": "Test admin", "budget": 100}, csrf)
    assert confirm[0] == 200 and start[0] == 200, (confirm, start)
    plan = next(job for job in start[1]["project"]["jobs"] if job["kind"] == "generate_plan")
    assert plan["state"] == "queued", plan

    print("Project offer HTTP gates passed: DRAFT blocked; current accepted offer and matching contract required; kickoff queued plan.")
finally:
    server.terminate()
    server.wait(timeout=5)
    server_log.close()
    shutil.rmtree(work, ignore_errors=True)
