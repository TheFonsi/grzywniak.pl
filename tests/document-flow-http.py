"""Real HTTP regression for all audited data-flow gates; isolated DB, no AI/infra/email."""
from pathlib import Path
import base64, http.cookiejar, json, os, re, shutil, socket, subprocess, tempfile, time
import urllib.request, urllib.error
root=Path(__file__).resolve().parents[1]
(root/'tmp').mkdir(exist_ok=True)
work=Path(tempfile.mkdtemp(prefix='document-flow-',dir=root/'tmp'));(work/'api').mkdir()
for p in (root/'api').glob('*.php'): shutil.copy2(p,work/'api'/p.name)
env=dict(os.environ,ADMIN_USERNAME='flow-test',ADMIN_PASSWORD='test-password',CONTRACT_AI_MOCK='true',DISCOVERY_MOCK='true',DISCOVERY_MAIL_MOCK='true')
seed=r"""
require 'api/bootstrap.php';require 'api/offer-agreement.php';require 'api/contract-pdf.php';
$id=bin2hex(random_bytes(16));$c=contractSampleContract('website');
$s=['id'=>$id,'status'=>'COMPLETED','createdAt'=>time(),'messages'=>[],'projectState'=>['contactEmail'=>'test@example.com','businessProblem'=>'Strona','targetUsers'=>'Firmy','mustHaveFeatures'=>'Kontakt','budget'=>'9000','deadline'=>'40 dni'],
'internalAnalysis'=>['status'=>'COMPLETED','version'=>1,'missingInformation'=>[],'recommendedScope'=>['Strona z kontaktem']],
'adminDecisions'=>['Kto dostarcza zdjęcia?'=>['source'=>'HUMAN','answer'=>'Klient dostarcza zdjęcia do 7 dni.','at'=>time(),'author'=>'flow-test']]];
$a=[];foreach(offerAgreementFields() as $key=>$field) $a[$key]=$c['facts'][$key]??'Uzgodnione warunki.';
$a['ipMode']='transfer';$a['rightsTerms']='';$a['dataRole']='none';$a['publicationDestination']='agency';
$a['deliverySchedule']='40 dni po dostarczeniu materiałów.';$a['paymentSchedule']='Pozostała kwota po odbiorze, płatna w 7 dni.';
$a['supportPlan']='SUPPORT 12 MONTHS';$a['rightsSummary']='Przeniesienie praw majątkowych po zapłacie.';$a['externalCosts']='COSTS LIMIT 100 PLN';$a['dataPlan']='DATA PLAN bez danych klienta.';
$s['offer']=['status'=>'ACCEPTED','version'=>1,'analysisVersion'=>1,'offerId'=>'OF-OLD','contact'=>['name'=>'Test client','email'=>'test@example.com'],
'sections'=>[['title'=>'Zakres realizacji','items'=>['OLD SCOPE']]],'pricing'=>['net'=>6000,'vatRate'=>23,'gross'=>7380],'payment'=>['depositRate'=>30],'agreement'=>$a];
$s['offer']['decisionCoverage']=offerDecisionCoverage($s,[],array_fill_keys(array_keys(decisionLedgerActive($s)),'cooperationTerms'));
$old=contractCommercialSnapshot($s);$c=array_replace($c,$old['fields']);$c['commercialSnapshot']=$old;$c['offerVersion']=1;$c['version']=1;$c['templateId']='website';$c['templateRevision']=1;$c['templateVersion']=0;
$c['package']=contractPackageBuild($c);$c['pdfBase64']=base64_encode(contractPdf($c));$c['package']['pdfSha256']=hash('sha256',base64_decode($c['pdfBase64']));$s['contract']=$c;
$s['offer']['version']=2;$s['offer']['offerId']='OF-NEW';$s['offer']['sections'][0]['items']=['NEW SCOPE'];$s['offer']['pricing']=['net'=>9000,'vatRate'=>23,'gross'=>11070];
$s['offer']['sourceHash']=hash('sha256',json_encode([$s['projectState'],$s['internalAnalysis'],$s['adminDecisions']],JSON_UNESCAPED_UNICODE));
writeSession($s);file_put_contents('seed.json',json_encode(['id'=>$id,'contract'=>$c]));
"""
subprocess.run(['php','-r',seed],cwd=work,env=env,check=True)
fixture=json.loads((work/'seed.json').read_text(encoding='utf-8'));sid=fixture['id'];c=fixture['contract']
sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close()
log=open(work/'server.log','w',encoding='utf-8');server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(work)],cwd=work,env=env,stdout=log,stderr=log)
opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(path,body=None):
    headers={'Authorization':'Basic '+base64.b64encode(b'flow-test:test-password').decode()}
    if body is not None: headers['Content-Type']='application/json'
    req=urllib.request.Request(f'http://127.0.0.1:{port}{path}',data=json.dumps(body).encode() if body is not None else None,headers=headers)
    try:
        with opener.open(req) as res: return res.status,res.read()
    except urllib.error.HTTPError as res: return res.code,res.read()
def call(path,body=None):
    code,raw=request(path,body);return code,json.loads(raw)
try:
    contract_path='/api/contract.php?session='+sid;offer_path='/api/offer.php?session='+sid
    for _ in range(50):
        try: code,editor=request(contract_path+'&format=editor');break
        except urllib.error.URLError: time.sleep(.1)
    else: raise RuntimeError('PHP server unavailable')
    assert code==200
    token=re.search(rb'name="csrf" value="([^"]+)"',editor)[1].decode()
    assert b'data-commercial-warning' in editor and b'readonly data-commercial-field' in editor
    keys=json.loads(subprocess.check_output(['php','-r',"require 'api/contract-model.php'; echo json_encode(array_keys(contractFields()));"],cwd=work,env=env))
    body={k:c[k] for k in keys};body.update(c['facts']);body.update(csrf=token,contract_session=sid,expectedVersion=1,templateId='website',templateRevision=1,templateVersion=0,profileVersion=0,action='generate',commercialHash=c['commercialSnapshot']['hash'])
    def accept_fields(payload):
        payload['reviewState']=json.dumps({key:{'value':value,'accepted':True} for key,value in payload.items() if key in keys or key in c['facts']})
        return payload
    assert call(contract_path,accept_fields(dict(body)))[0]==422,'Old terms must not be relabelled with new offer'
    saved=call(contract_path,dict(body,action='save'))
    assert saved[0]==200 and saved[1]['contract']['offerVersion']==1,'Draft must retain old source binding until explicit reconciliation'
    body['expectedVersion']=2
    sync=call(contract_path,dict(body,action='sync-offer'));assert sync[0]==200,sync
    assert sync[1]['differences'] and '9 000,00' in sync[1]['fields']['price']
    body.update(sync[1]['fields']);body.update(sync[1]['facts']);body['commercialHash']=sync[1]['commercialHash']
    for key in ['scope','price','deposit','deadline']:
        assert call(contract_path,accept_fields(dict(body,**{key:'OLD VALUE'})))[0]==422,key
    for key,value in [('ipMode','nonexclusive'),('dataRole','processor')]:
        assert call(contract_path,accept_fields(dict(body,**{key:value})))[0]==422,key
    generated=call(contract_path,accept_fields(dict(body)));assert generated[0]==200,generated
    new=generated[1]['contract'];assert new['offerVersion']==2 and new['version']==3
    commercial=next(doc for doc in new['package']['documents'] if doc['id']=='commercial')
    text='\n'.join(commercial['sections'].values())
    for term in ['SUPPORT 12 MONTHS','COSTS LIMIT 100 PLN','DATA PLAN','NEW SCOPE','Klient dostarcza zdjęcia do 7 dni.']: assert term in text,term
    body['expectedVersion']=3
    applied=call(contract_path,dict(body,action='apply-template',requestedTemplateId='webapp'));assert applied[0]==200,applied
    body.update(applied[1]['fields']);body.update(templateId='webapp',templateRevision=1,templateVersion=0)
    regenerated=call(contract_path,accept_fields(dict(body)));assert regenerated[0]==200,regenerated
    assert regenerated[1]['contract']['commercialSnapshot']==new['commercialSnapshot'],'Template must not modify individual conditions'
    old_pdf=request(contract_path+'&format=pdf&version=1');assert old_pdf[0]==200 and old_pdf[1]==base64.b64decode(c['pdfBase64']),'Original historical PDF must remain exact'
    # Scope edit preserves terms but blocks review until every affected condition is checked.
    offer=call(offer_path)[1]['offer']
    edited=call(offer_path,{'action':'updateText','sections':[{'title':'Zakres realizacji','items':['NEW SCOPE 2']} ]});assert edited[0]==200,edited
    assert edited[1]['offer']['agreement']['supportPlan']=='SUPPORT 12 MONTHS' and edited[1]['offer']['dependencyReview']
    assert call(offer_path,{'action':'review'})[0]==422
    metadata=call(offer_path+'&format=agreement')[1]
    update={'action':'updateAgreement','expectedVersion':edited[1]['offer']['version'],'csrf':metadata['csrf'],'agreement':metadata['values'],'decisionTargets':{key:value['target'] for key,value in metadata['decisions'].items()},'reviewedDependencies':{key:True for key in metadata['dependencyReview']}}
    complete=call(offer_path,update);assert complete[0]==200,complete
    assert call(offer_path,{'action':'review'})[0]==200
    assert call(offer_path,{'action':'accept'})[0]==200
    body['expectedVersion']=4
    assert call(contract_path,accept_fields(dict(body)))[0]==422,'New accepted offer invalidates old contract binding'
    # A changed human answer gets a new ledger revision, resets mapping and survives retry.
    confirmed=call('/api/brief-assist.php',{'session':sid,'question':'Kto dostarcza zdjęcia?','action':'confirm','answer':'Klient dostarcza zdjęcia do 9 dni.'});assert confirmed[0]==200,confirmed
    retry=call('/api/discovery.php?action=retryAnalysis&sessionId='+sid,{});assert retry[0]==200,retry
    stored=json.loads(subprocess.check_output(['php','-r',"require 'api/bootstrap.php'; $s=readSession('"+sid+"'); normalizeSessionData($s); echo json_encode($s);"],cwd=work,env=env))
    decision=next(iter(stored['decisionLedger'].values()))
    assert decision['version']==2 and decision['answer'].endswith('9 dni.') and stored['adminDecisions']
    assert offer_path and stored['decisionLedgerHistory'][0]['answer'].endswith('7 dni.')
    print('Document flow HTTP passed: stale content blocked, explicit sync, full commercial annex, template isolation, old PDF, dependency review, mapped decisions and persistent revisions.')
finally:
    server.terminate();server.wait(timeout=10);log.close()
