"""Isolated HTTP regression checks for the offer, contract and kickoff gates."""
from pathlib import Path
import base64, http.cookiejar, json, os, shutil, socket, sqlite3, subprocess, tempfile, time
import urllib.request, urllib.error

root = Path(__file__).resolve().parents[1]
tmp_root = root / "tmp"
tmp_root.mkdir(exist_ok=True)
work = Path(tempfile.mkdtemp(prefix="project-gates-http-", dir=tmp_root))
(work / "api").mkdir()
for source in (root / "api").glob("*.php"):
    shutil.copy2(source, work / "api" / source.name)

env = dict(os.environ, ADMIN_USERNAME="gate-test", ADMIN_PASSWORD="test-password", DISCOVERY_MAIL_MOCK="true", DISCOVERY_MOCK="true")
(work / ".env").write_text(
    "ADMIN_USERNAME=gate-test\nADMIN_PASSWORD=test-password\nDISCOVERY_MAIL_MOCK=true\nDISCOVERY_MOCK=true\n",
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
    'pricing'=>['net'=>1500,'vatRate'=>23,'gross'=>1845],
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

    chat_code, chat_session = request("/api/discovery.php?action=session", {"language": "pl"})
    assert chat_code == 200, (chat_code, chat_session)
    chat_id = chat_session["session"]["id"]
    chat_db = sqlite3.connect(work / "api" / "storage" / "sessions.sqlite")
    row = chat_db.execute("SELECT data FROM sessions WHERE id=?", (chat_id,)).fetchone()
    chat_data = json.loads(row[0])
    if not isinstance(chat_data.get("projectState"), dict): chat_data["projectState"] = {}
    chat_data["projectState"].update({"businessProblem": "Pozyskiwanie zapytań dla Jar-Bud", "targetUsers": "Inwestorzy i firmy", "mustHaveFeatures": "Strona portfolio i formularz kontaktu", "budget": "Do ustalenia", "deadline": "Do ustalenia"})
    chat_db.execute("UPDATE sessions SET data=? WHERE id=?", (json.dumps(chat_data, ensure_ascii=False), chat_id))
    chat_db.commit(); chat_db.close()
    fake_contact = "Dane fikcyjne tylko do testu: firma: Jar-Bud Grzywniak, telefon +48 000 000 000, e-mail jarbud-test@example.invalid."
    sent = request(f"/api/discovery.php?action=message&sessionId={chat_id}", {"message": fake_contact})
    assert sent[0] == 200, sent
    finish = request(f"/api/discovery.php?action=finish&sessionId={chat_id}", {})
    assert finish[0] == 200 and finish[1]["session"]["readyForSummary"] is False and finish[1]["session"]["status"] == "NEEDS_INFORMATION", finish
    complete_fake = request(f"/api/discovery.php?action=complete&sessionId={chat_id}", {})
    assert complete_fake[0] == 409, complete_fake

    code, project = request(project_path)
    assert code == 200, (code, project)
    csrf = project["csrf"]

    start_before = request(project_path, {"action": "start", "scope": "Test portfolio project", "owner": "Test admin", "budget": 100}, csrf)
    confirm_before = request(project_path, {"action": "confirm_contract", "evidence": "Test agreement evidence"}, csrf)
    accept_draft = request(offer_path, {"action": "accept"})
    assert start_before[0] == 409 and confirm_before[0] == 409 and accept_draft[0] == 409, (start_before,confirm_before,accept_draft)

    assert request(offer_path, {"action": "review"})[0] == 422, 'Legacy offer needs complete terms before new review'
    agreement_meta = request(offer_path + '&format=agreement')[1]
    initial_terms = {key: ('agency' if field.get('options') else 'Agreed terms for this project') for key,field in agreement_meta['fields'].items()}
    initial_terms.update(ipMode='transfer', rightsTerms='', dataRole='none', acceptanceDays='7', productionDomain='example.test')
    filled = request(offer_path, {'action':'updateAgreement','expectedVersion':1,'csrf':agreement_meta['csrf'],'agreement':initial_terms})
    assert filled[0] == 200, filled
    review = request(offer_path, {"action": "review"})
    mock_send = request(offer_path, {"action": "send"})
    assert review[0] == 200 and review[1]["offer"]["status"] == "REVIEWED", review
    assert mock_send[0] == 200 and mock_send[1]["offer"]["status"] == "SENT" and not mock_send[1]["offer"].get("acceptedBy"), mock_send
    start_after_send = request(project_path, {"action": "start", "scope": "Test portfolio project", "owner": "Test admin", "budget": 100}, csrf)
    confirm_after_send = request(project_path, {"action": "confirm_contract", "evidence": "Test agreement evidence"}, csrf)
    assert start_after_send[0] == 409 and confirm_after_send[0] == 409, (start_after_send, confirm_after_send)
    accepted = request(offer_path, {"action": "accept"})
    assert accepted[0] == 200, accepted
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

    # Execute exactly the deterministic planning job in the disposable API copy.
    # The worker may enqueue repository creation, but this test must never call GitHub.
    worker_env = dict(env, PROJECT_AI_MOCK="true")
    worker = subprocess.run(["php", "api/project-worker.php", "--once"], cwd=work, env=worker_env, text=True, capture_output=True)
    assert worker.returncode == 0, (worker.stdout, worker.stderr)
    db = sqlite3.connect(work / "api" / "storage" / "sessions.sqlite")
    case_row = db.execute("SELECT data FROM project_cases WHERE session_id=?", (session_id,)).fetchone()
    assert case_row, "The worker should persist the generated project plan."
    project_case = json.loads(case_row[0])
    assert project_case["plan"]["templateDecision"]["templateId"] == "web-vite"
    assert {task["id"] for task in project_case["plan"]["tasks"]} == {"ux", "frontend", "qa"}
    states = dict(db.execute("SELECT kind,state FROM project_jobs WHERE session_id=?", (session_id,)).fetchall())
    repository_job = db.execute("SELECT id FROM project_jobs WHERE session_id=? AND kind='create_repository'", (session_id,)).fetchone()
    db.close()
    assert states["generate_plan"] == "done", states
    assert states["create_repository"] == "queued", states

    # With no GitHub App configuration, the worker must stop before any API request.
    no_github_env = dict(worker_env, GITHUB_APP_ID="", GITHUB_INSTALLATION_ID="", GITHUB_APP_PRIVATE_KEY="", GITHUB_APP_PRIVATE_KEY_FILE="")
    blocked = subprocess.run(["php", "api/project-worker.php", "--once"], cwd=work, env=no_github_env, text=True, capture_output=True)
    assert blocked.returncode == 0, (blocked.stdout, blocked.stderr)
    db = sqlite3.connect(work / "api" / "storage" / "sessions.sqlite")
    repo_state = db.execute("SELECT state,error FROM project_jobs WHERE id=?", (repository_job[0],)).fetchone()
    db.close()
    assert repo_state and repo_state[0] == "failed" and "GitHub App ID" in repo_state[1], repo_state

    # Editing terms creates new versions and incomplete edits block review.
    code, editor = request(offer_path+"&format=agreement")
    assert code == 200, (code, editor)
    (tmp_root / 'offer-agreement-ui-fixture.json').write_text(json.dumps(editor,ensure_ascii=False),encoding='utf-8')
    assert request(offer_path,dict(action='updateAgreement',expectedVersion=1,csrf='wrong',agreement={}))[0] == 403
    assert request(offer_path,dict(action='updateAgreement',expectedVersion=99,csrf=editor['csrf'],agreement={}))[0] == 409
    saved=request(offer_path,dict(action='updateAgreement',expectedVersion=2,csrf=editor['csrf'],agreement={}))
    assert saved[0] == 200 and saved[1]['offer']['version'] == 3 and saved[1]['offer']['status'] == 'DRAFT',saved
    assert request(offer_path,dict(action='review'))[0] == 422,'Missing offer terms must block review'
    terms={key:('agency' if field.get('options') else 'Fictional agreed terms') for key,field in editor['fields'].items()}
    terms['acceptanceDays']='14';terms['productionDomain']='example.test'
    terms['ipMode']='transfer';terms['dataRole']='none';terms['rightsTerms']=''
    saved=request(offer_path,dict(action='updateAgreement',expectedVersion=3,csrf=editor['csrf'],agreement=terms))
    assert saved[0] == 200 and saved[1]['offer']['version'] == 4,saved
    reviewed=request(offer_path,dict(action='review'));assert reviewed[0] == 200,reviewed
    accepted=request(offer_path,dict(action='accept'));assert accepted[0] == 200,accepted
    assert accepted[1]['offer']['agreement']['acceptanceDays'] == '14'
    assert len(request(offer_path)[1]['offerVersions']) == 3,'Prior versions must remain available'
    print('HTTP workflow and offer agreement gates passed, including CSRF, version conflicts, missing terms and acceptance snapshot.')
finally:
    server.terminate()
    server.wait(timeout=5)
    server_log.close()
    shutil.rmtree(work, ignore_errors=True)
