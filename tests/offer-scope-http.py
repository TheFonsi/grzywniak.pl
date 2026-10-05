"""Optional ideas and promotions: disposable database, no AI/infra/mail."""
from pathlib import Path
import base64, json, os, shutil, socket, subprocess, tempfile, time
import urllib.request, urllib.error
root=Path(__file__).resolve().parents[1]
work=Path(tempfile.mkdtemp(prefix='offer-scope-',dir=root/'tmp'));(work/'api').mkdir()
for file in (root/'api').glob('*.php'):shutil.copy2(file,work/'api'/file.name)
env=dict(os.environ,ADMIN_USERNAME='scope-test',ADMIN_PASSWORD='test-password')
seed=r"""
require 'api/bootstrap.php';require 'api/offer-agreement.php';require 'api/offer-scope.php';
$id=bin2hex(random_bytes(16));$s=['id'=>$id,'projectState'=>[],'internalAnalysis'=>['status'=>'COMPLETED','version'=>1,'missingInformation'=>[]], 'adminDecisions'=>[], 'contract'=>['version'=>1,'status'=>'SIGNED','pdfBase64'=>'original-pdf']];
$facts=contractSamplePackageFacts();$a=[];foreach(offerAgreementFields() as $key=>$field)$a[$key]=$facts[$key]??'Agreed conditions';$a['publicationDestination']='agency';$a['ipMode']='transfer';$a['rightsTerms']='';$a['dataRole']='none';
$s['offer']=['status'=>'ACCEPTED','version'=>3,'analysisVersion'=>1,'offerId'=>'OF-TEST','pricing'=>['net'=>2000,'vatRate'=>23,'gross'=>2460],'payment'=>['depositRate'=>10],'agreement'=>$a,'sections'=>[['title'=>'Zakres realizacji','items'=>['Strona one page']]],'verification'=>'HUMAN_REVIEWED','acceptedAt'=>time()];
$s['offer']=offerExpandOptional($s['offer']);$s['offer']['sourceHash']=hash('sha256',json_encode([$s['projectState'],$s['internalAnalysis'],$s['adminDecisions']],JSON_UNESCAPED_UNICODE));
writeSession($s);file_put_contents('seed.json',json_encode(['id'=>$id,'session'=>$s]));
"""
subprocess.run(['php','-r',seed],cwd=work,env=env,check=True)
fixture=json.loads((work/'seed.json').read_text(encoding='utf-8'));sid=fixture['id'];before=fixture['session'];idea=before['offer']['sections'][1]['items'][0]
sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close()
log=open(work/'server.log','w');server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(work)],cwd=work,env=env,stdout=log,stderr=log)
import http.cookiejar
opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(body=None,extra=''):
    req=urllib.request.Request(f'http://127.0.0.1:{port}/api/offer.php?session={sid}{extra}',data=json.dumps(body).encode() if body is not None else None,headers={'Authorization':'Basic '+base64.b64encode(b'scope-test:test-password').decode(),'Content-Type':'application/json'})
    try:
        with opener.open(req) as res:return res.status,json.loads(res.read())
    except urllib.error.HTTPError as res:return res.code,json.loads(res.read())
try:
    for _ in range(50):
        try:code,meta=call(extra='&format=agreement');break
        except urllib.error.URLError:time.sleep(.1)
    assert code==200
    assert len(before['offer']['sections'][1]['items'])==5
    body={'action':'promoteOptional','expectedVersion':3,'csrf':meta['csrf'],'section':1,'item':0,'text':idea}
    assert call(dict(body,csrf='wrong'))[0]==403
    assert call(dict(body,expectedVersion=2))[0]==409
    assert call(dict(body,section=0))[0]==422
    assert call(dict(body,text='different'))[0]==422
    code,data=call(body);assert code==200,(code,data)
    new=data['offer'];assert new['version']==4 and new['status']=='DRAFT' and 'acceptedAt' not in new and 'verification' not in new
    assert idea in new['sections'][0]['items'] and idea not in new['sections'][1]['items']
    assert new['pricing']==before['offer']['pricing'] and new['dependencyReview']['pricing'] is False
    assert call(body)[0]==409,'Retry cannot duplicate an item'
    assert call({'action':'review'})[0]==422,'New scope needs a review of terms and price'
    state=call()[1];assert state['offerVersions'][0]['status']=='ACCEPTED' and state['offerVersions'][0]['sections']==before['offer']['sections']
    restored=call({'action':'expandOptional','csrf':meta['csrf'],'expectedVersion':4});assert restored[0]==200
    assert idea not in restored[1]['offer']['sections'][1]['items'],'Already promoted idea must not return as optional'
    stored=json.loads(subprocess.check_output(['php','-r',"require 'api/bootstrap.php'; echo json_encode(readSession('"+sid+"'));"],cwd=work,env=env))
    assert stored['contract']==before['contract'],'Signed contract must remain unchanged'
    print('Optional scope HTTP passed: five ideas, promotion, CSRF, conflict protection, preserved price/archive/contract and required review.')
finally:
    server.terminate();server.wait(timeout=10);log.close()
