"""Retry analysis without live AI: failure preserves documents; success versions analysis."""
from pathlib import Path
import base64, json, os, shutil, socket, subprocess, tempfile, time
import urllib.request, urllib.error

root = Path(__file__).resolve().parents[1]
(root / 'tmp').mkdir(exist_ok=True)
work = Path(tempfile.mkdtemp(prefix='analysis-retry-', dir=root / 'tmp'))
(work / 'api').mkdir()
for source in (root / 'api').glob('*.php'):
    shutil.copy2(source, work / 'api' / source.name)
analysis = work / 'api' / 'internal-analysis.php'
text = analysis.read_text(encoding='utf-8').replace('function internalAnalysis(', 'function liveInternalAnalysis(', 1)
text += "\nfunction internalAnalysis(array $s, bool $updateOffer=false): array { if(is_file(__DIR__.'/fail-test')) throw new RuntimeException('HTTP 429 insufficient_quota'); return ['status'=>'COMPLETED','createdAt'=>time(),'readiness'=>'READY','summary'=>'Updated analysis','missingInformation'=>[]]; }\n"
analysis.write_text(text, encoding='utf-8')
env = dict(os.environ, ADMIN_USERNAME='retry-test', ADMIN_PASSWORD='test-password')
seed = r"""
require 'api/bootstrap.php';
$s=['id'=>bin2hex(random_bytes(16)),'status'=>'COMPLETED','createdAt'=>time(),'language'=>'pl','messages'=>[],
'projectState'=>['contactEmail'=>'test@example.com','businessProblem'=>'Strona','targetUsers'=>'Firmy','mustHaveFeatures'=>'Kontakt','budget'=>'2000','deadline'=>'Miesiąc'],
'summary'=>['sections'=>[['title'=>'Brief','content'=>['Original brief']]]],
'internalAnalysis'=>['status'=>'COMPLETED','version'=>7,'createdAt'=>time(),'summary'=>'Original analysis'],
'offer'=>['status'=>'ACCEPTED','version'=>3], 'contract'=>['status'=>'SIGNED','version'=>2]];
writeSession($s); echo $s['id'];
"""
sid = subprocess.check_output(['php','-r',seed],cwd=work,env=env,text=True).strip()
sock=socket.socket(); sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]; sock.close()
log=open(work/'server.log','w')
server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(work)],cwd=work,env=env,stdout=log,stderr=log)
def read():
    return json.loads(subprocess.check_output(['php','-r',f"require 'api/bootstrap.php'; echo json_encode(readSession('{sid}'));"],cwd=work,env=env,text=True))
def request(auth=True):
    headers={'Content-Type':'application/json'}
    if auth: headers['Authorization']='Basic '+base64.b64encode(b'retry-test:test-password').decode()
    req=urllib.request.Request(f'http://127.0.0.1:{port}/api/discovery.php?action=retryAnalysis&sessionId={sid}',data=b'{}',headers=headers)
    try:
        with urllib.request.urlopen(req) as res: return res.status,json.loads(res.read())
    except urllib.error.HTTPError as res: return res.code,json.loads(res.read())
try:
    for _ in range(50):
        try:
            assert request(False)[0]==401
            break
        except urllib.error.URLError: time.sleep(.1)
    else: raise RuntimeError('Server unavailable')
    before=read(); (work/'api'/'fail-test').touch()
    status,data=request()
    assert status==502 and data['analysisStatus']=='FAILED' and 'limit' in data['message'],data
    failed=read()
    for field in ('internalAnalysis','offer','contract','summary'): assert failed[field]==before[field],field
    assert failed['analysisLastAttempt']['reference']==data['reference']
    (work/'api'/'fail-test').unlink()
    assert request()[0]==200
    after=read()
    assert after['internalAnalysis']['version']==8 and after['offer']['status']=='OUTDATED'
    assert after['analysisVersions']==[before['internalAnalysis']]
    assert after['offerVersions']==[dict(before['offer'],archivedAt=after['offerVersions'][0]['archivedAt'])]
    assert after['summary']==before['summary'] and after['contract']==before['contract']
    assert request()[0]==200 and read()['internalAnalysis']['version']==9
    print('Analysis retry HTTP checks passed (failure preservation, auth, history, versioning).')
finally:
    server.terminate(); server.wait(timeout=10); log.close()
