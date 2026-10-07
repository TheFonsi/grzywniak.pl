"""Isolated HTTP checks: never access the live database or send email."""
from pathlib import Path
import base64, http.cookiejar, json, os, re, shutil, socket, subprocess, time
import urllib.request, urllib.error

root=Path(__file__).resolve().parents[1]
work=root/'tmp'/'contract-http'
(work/'api').mkdir(parents=True,exist_ok=True)
for p in (root/'api').glob('*.php'): shutil.copy2(p,work/'api'/p.name)
shutil.copy2(root/'api'/'contract-review.js',work/'api'/'contract-review.js')
env=dict(os.environ,ADMIN_USERNAME='contract-test',ADMIN_PASSWORD='test-password',DISCOVERY_MAIL_MOCK='true',CONTRACT_AI_MOCK='true')
sid='abcdef0123456789abcdef0123456789'
seed="require 'api/bootstrap.php'; require 'api/offer-agreement.php'; $facts=contractSamplePackageFacts(); $a=[]; foreach(offerAgreementFields() as $key=>$field) $a[$key]=$facts[$key]??'Agreed conditions'; $a['ipMode']='transfer'; $a['rightsTerms']=''; $a['dataRole']='none'; $a['publicationDestination']='agency'; $a['productionDomain']=''; writeSession(['id'=>'"+sid+"','status'=>'COMPLETED','messages'=>[['role'=>'user','content'=>'test']],'offer'=>['status'=>'ACCEPTED','version'=>3,'contact'=>['name'=>'Client','email'=>'test@example.com'],'sections'=>[['title'=>'Zakres realizacji','items'=>['Strona z kontaktem']]],'pricing'=>['net'=>100,'vatRate'=>8],'agreement'=>$a],'projectState'=>['deadline'=>'20 dni od otrzymania materiałów']]);"
subprocess.run(['php','-r',seed],cwd=work,env=env,check=True)
sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close()
log=open(work/'server.log','w')
server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(work)],cwd=work,env=env,stdout=log,stderr=log)
opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
auth='Basic '+base64.b64encode(b'contract-test:test-password').decode()
review_keys=[]
def request(path,body=None,authorized=True):
    headers={'Authorization':auth} if authorized else {}
    if body is not None:
        headers['Content-Type']='application/json'
        if path.startswith('/api/project-api.php'): headers['X-CSRF-Token']=body.get('csrf','')
        if body.get('action')=='generate' and 'reviewState' not in body:
            # Emulate explicit admin acceptance; the endpoint rejects unreviewed clauses.
            body=dict(body,reviewState=json.dumps({key:{'value':body.get(key,''),'accepted':True} for key in review_keys}))
    req=urllib.request.Request(f'http://127.0.0.1:{port}'+path,data=json.dumps(body).encode() if body is not None else None,headers=headers)
    try:
        with opener.open(req) as r:return r.status,r.read()
    except urllib.error.HTTPError as e:return e.code,e.read()
try:
    for _ in range(40):
        try: request('/api/contract.php');break
        except urllib.error.URLError:time.sleep(.1)
    assert request('/api/contract.php',authorized=False)[0]==401
    code,html=request('/api/contract.php?session='+sid+'&format=editor');assert code==200
    token=re.search(rb'name="csrf" value="([^"]+)"',html)[1].decode()
    assert request('/api/contract.php',dict(csrf=token,contract_session=sid,expectedVersion=0,profileVersion=0,action='ai-fill'))[0]==422
    code,settings=request('/api/admin.php?view=contract-settings'); assert code==200
    profile_form=re.search(rb'<form[^>]*>.*?name="action" value="save-profile".*?</form>',settings,re.S)[0]
    profile={key.decode():'' for key in re.findall(rb'<input name="([^"]+)"',profile_form)}
    profile.update(legalName='Test Provider',address='Testowa 1, Warszawa',representative='Jan Test',email='provider@example.com',phone='+48 123456789',bankAccount='TEST ACCOUNT',action='save-profile',csrf=token,expectedVersion=0)
    assert request('/api/contract.php',dict(profile,email='invalid'))[0]==422
    assert request('/api/contract.php',profile)[0]==200
    assert request('/api/contract.php',profile)[0]==409
    code,html=request('/api/contract.php?session='+sid+'&format=editor');assert code==200 and b'Test Provider' in html and b'TEST ACCOUNT' in html
    (work/'contract-editor.html').write_bytes(html)
    assert b'VAT 8%' in html
    fields=re.findall(rb'<textarea name="([^"]+)"',html)
    review_keys=[key.decode() for key in re.findall(rb'<(?:textarea|input|select)[^>]*name="([^"]+)"',html)]
    body={k.decode():'Example text' for k in fields}
    for key in ['scope','price','deposit','deadline']:
        value=re.search(rb'<textarea[^>]*name="'+key.encode()+rb'"[^>]*>(.*?)</textarea>',html,re.S)[1].decode()
        import html as html_module
        body[key]=html_module.unescape(value)
    body['commercialHash']=re.search(rb'name="commercialHash" value="([^"]+)"',html)[1].decode()
    body.update(csrf=token,contract_session=sid,expectedVersion=0,templateVersion=0,profileVersion=1,action='generate',clientType='business',ipMode='transfer',signing='qualified',dataRole='none',clientAddress='Testowa 2, Warszawa',clientTaxId='',clientRepresentative='Jan Test',contractDate='2026-09-15',publicationDestination='agency')
    body.update(json.loads(subprocess.check_output(['php','-r',"require 'api/contract-model.php'; echo json_encode(contractSamplePackageFacts());"],cwd=work,env=env)))
    assert b'name="ipPayment"' not in html and b'data-license-terms hidden' in html
    assert request('/api/contract.php',dict(body,ipMode='exclusive',rightsTerms=''))[0]==422
    code,data=request('/api/contract.php',dict(body,action='ai-fill'));assert code==200,(code,data)
    ai=json.loads(data);assert 'scope' in ai['fields'] and ai['fields']['price']==body['price'] and ai['fields']['provider']==body['provider'] and ai['facts']['clientAddress']==body['clientAddress']
    assert all(ai['fields'][k]==body[k] for k in ['terms','ip','acceptance','deploymentTerms','support','extras','exclusions']), 'AI must retain unaccepted manual legal clauses too'
    assert ai['facts']['publicationDestination']=='agency' and ai['facts']['productionDomain']==''
    assert json.loads(request('/api/contract.php?session='+sid)[1])['contract'] is None, 'AI must not persist or send'
    # Brief fill is a read-only draft operation; populated facts must survive.
    seed_brief="require 'api/bootstrap.php'; $s=readSession('"+sid+"'); $s['projectState']['materialsTerms']='Client supplies logo by agreed date.'; writeSession($s);"
    subprocess.run(['php','-r',seed_brief],cwd=work,env=env,check=True)
    code,data=request('/api/contract.php',dict(body,action='brief-fill',cooperationTerms=''))
    assert code==200,(code,data)
    assert json.loads(data)['facts']['cooperationTerms']==body['cooperationTerms'], 'Accepted offer terms take priority over a later brief edit'
    code,data=request('/api/contract.php',dict(body,action='brief-fill',cooperationTerms='Admin edit'))
    assert code==200 and 'cooperationTerms' not in json.loads(data)['facts']
    assert json.loads(request('/api/contract.php?session='+sid)[1])['contract'] is None,'Brief fill must not persist a contract'
    code,prepared=request('/api/contract.php',dict(body,action='auto-prepare'));assert code==200,(code,prepared)
    automatic=json.loads(prepared)
    assert automatic['template']['id']=='website' and automatic['replaceTemplate'] and automatic['replaceCommercial']
    assert automatic['fields']['scope']==body['scope'] and automatic['fields']['price']==body['price']
    assert 'ODPOWIEDZIALNOŚĆ' in automatic['fields']['terms'] and 'pola eksploatacji' in automatic['fields']['ip']
    assert json.loads(request('/api/contract.php?session='+sid)[1])['contract'] is None,'Preparing the automatic proposal does not persist or send it'
    assert request('/api/contract.php',dict(body,action='approve-generate',clientAddress=''))[0]==422,'Whole approval cannot fabricate client data'
    assert request('/api/contract.php',dict(body,clientType='invented',action='ai-fill'))[0]==422
    assert request('/api/contract.php',dict(body,scope='[DO UZUPEŁNIENIA: brak danych]'))[0]==422
    assert request('/api/contract.php',dict(body,clientType=''))[0]==422
    assert request('/api/contract.php',dict(body,contractDate='2026-02-31'))[0]==422
    assert request('/api/contract.php',dict(body,csrf='bad'))[0]==403
    code,data=request('/api/contract.php',dict(body,action='approve-generate'));assert code==200,(code,data)
    assert json.loads(data)['contract']['version']==1
    saved_facts=json.loads(data)['contract']['facts']
    assert 'pełnego wynagrodzenia' in saved_facts['rightsTerms'] and 'zawarte w cenie' in saved_facts['ipPayment']
    code,pdf=request('/api/contract.php?session='+sid+'&format=pdf');assert code==200 and pdf.startswith(b'%PDF-')
    before_preview=json.loads(request('/api/contract.php?session='+sid)[1])['contract']
    change_offer="require 'api/bootstrap.php'; $s=readSession('"+sid+"'); $s['offer']['version']=4; $s['offer']['offerId']='OF-NEW-TEST'; writeSession($s);"
    subprocess.run(['php','-r',change_offer],cwd=work,env=env,check=True)
    code,stale_editor=request('/api/contract.php?session='+sid+'&format=editor');assert code==200
    assert b'data-contract-outdated' in stale_editor and b'OF-NEW-TEST' in stale_editor and b'data-contract-reprepare' in stale_editor
    assert json.loads(request('/api/contract.php?session='+sid)[1])['contract']==before_preview,'Mismatch notice must not regenerate or mutate saved contract'
    code,reprepared=request('/api/contract.php',dict(body,expectedVersion=1,action='auto-prepare'));assert code==200,(code,reprepared)
    for _ in range(2):
        code,reloaded=request('/api/contract.php?session='+sid+'&format=editor');assert code==200
        assert b'data-contract-working-draft' in reloaded and b'data-contract-outdated' not in reloaded,'Prepared draft must survive page reload'
        assert b'OF-NEW-TEST' in reloaded
    assert json.loads(request('/api/contract.php?session='+sid)[1])['contract']==before_preview,'Preparing a working draft must preserve saved PDF and approvals'
    assert request('/api/contract.php?session='+sid+'&format=pdf')[1]==pdf,'Old PDF bytes must stay unchanged'
    restore_offer="require 'api/bootstrap.php'; $s=readSession('"+sid+"'); $s['offer']['version']=3; unset($s['offer']['offerId']); writeSession($s);"
    subprocess.run(['php','-r',restore_offer],cwd=work,env=env,check=True)
    assert b'data-contract-working-draft' not in request('/api/contract.php?session='+sid+'&format=editor')[1],'Draft must not survive a change of offer source'
    code,preview=request('/api/contract.php?session='+sid+'&format=layout-preview&inline=1');assert code==200 and b'/BaseFont /Times-Roman' in preview
    assert json.loads(request('/api/contract.php?session='+sid)[1])['contract']==before_preview,'Visual preview must not change saved bytes, package approval or history'
    assert request('/api/contract.php',body)[0]==409
    assert request('/api/contract.php',dict(body,expectedVersion=1,action='send'))[0]==409
    current=json.loads(request('/api/contract.php?session='+sid)[1])['contract']
    assert len(current['package']['documents'])==5
    approve=dict(csrf=token,contract_session=sid,expectedVersion=1,action='legal-review',packageHash=current['package']['hash'],reviewer='Test legal reviewer',evidence='Isolated test only: fictional legal review evidence')
    assert request('/api/contract.php',dict(approve,packageHash='stale'))[0]==409
    assert request('/api/contract.php',approve)[0]==200
    code,data=request('/api/contract.php',dict(body,expectedVersion=1,action='send'));assert code==200,(code,data)
    assert request('/api/contract.php',dict(body,expectedVersion=1,action='send'))[0]==409
    sent=json.loads(data)['contract']
    assert sent['deliveryLog'][0]['pdfSha256']==sent['package']['pdfSha256']
    assert sent['deliveryLog'][0]['manifest']==sent['package']['manifest']
    assert 'receipt' not in sent['package']
    assert request('/api/contract.php?session='+sid+'&format=manifest')[0]==200
    assert json.loads(data)['contract']['status']=='MOCK_SENT'
    assert request('/api/contract.php?session='+sid+'&format=layout-preview')[0]==409,'Sent versions must retain the original PDF'
    code,data=request('/api/contract.php',dict(body,expectedVersion=1,action='save',party='Changed client'));assert code==200
    assert json.loads(data)['contract']['party']=='Changed client'
    assert json.loads(data)['contract']['facts']['ipMode']=='transfer'
    assert json.loads(data)['contract']['profileVersion']==1
    assert request('/api/contract.php?session='+sid+'&format=pdf')[0]==404
    assert request('/api/contract.php?session='+sid+'&format=pdf&version=1')[0]==200
    assert request('/api/contract.php',dict(body,expectedVersion=2,action='send'))[0]==409
    code,html=request('/api/admin.php?view=contract-settings');assert code==200
    assert 'Ustawienia wzorów umów'.encode() in html and b'>Umowy</a>' not in html
    assert b'class="active" aria-current="page" href="?view=contract-settings"' in html
    keys=re.findall(rb'<textarea[^>]+name="([^"]+)"',html)
    template={key.decode():'Template revised' for key in keys};template.update(csrf=token,expectedVersion=0,action='save-template')
    code,data=request('/api/contract.php',template);assert code==200,(code,data)
    code,data=request('/api/contract.php',dict(body,expectedVersion=2,action='save',party='Changed client'));assert code==200
    assert json.loads(data)['contract']['facts']['rightsTerms']==saved_facts['rightsTerms']
    assert json.loads(data)['contract']['facts']['ipPayment']==saved_facts['ipPayment']
    code,data=request('/api/contract.php?session='+sid);assert json.loads(data)['contract']['party']=='Changed client'
    code,html=request('/api/admin.php?view=all&session='+sid);assert code==200
    for i,js in enumerate(re.findall(rb'<script>(.*?)</script>',html,re.S)):
        path=work/f'admin-{i}.js';path.write_bytes(js);subprocess.run(['node','--check',str(path)],check=True)
    assert request('/api/contract.php',dict(profile,expectedVersion=1,legalName='Revised Provider'))[0]==200
    assert json.loads(request('/api/contract.php?session='+sid)[1])['contract']['provider']=='Example text'
    # Proposals must be accepted, survive a draft save, and be invalidated on edit.
    proposal=dict(body,expectedVersion=3,party='Changed client',action='ai-fill')
    code,data=request('/api/contract.php',proposal); assert code==200,(code,data)
    generated=json.loads(data)
    proposal.update(generated['fields']); proposal.update(generated['facts'])
    review={key:{'value':value,'accepted':False} for key,value in dict(generated['fields'],**generated['facts']).items() if key not in ['ipPayment','rightsTerms']}
    proposal.update(action='generate',reviewState=json.dumps(review))
    assert request('/api/contract.php',proposal)[0]==422
    code,data=request('/api/contract.php',dict(proposal,action='save'));assert code==200,(code,data)
    assert json.loads(data)['contract']['review']['support']['accepted'] is False
    code,html=request('/api/contract.php?session='+sid+'&format=editor'); assert b'name="reviewState"' in html
    for entry in review.values():entry['accepted']=True
    proposal.update(expectedVersion=4,reviewState=json.dumps(review))
    code,data=request('/api/contract.php',proposal);assert code==200,(code,data)
    assert json.loads(data)['contract']['review']['support']['accepted'] is True
    assert request('/api/contract.php',dict(proposal,expectedVersion=5,support='Changed after acceptance'))[0]==422
    code,data=request('/api/contract.php',dict(proposal,expectedVersion=5,action='ai-fill'));assert code==200,(code,data)
    assert json.loads(data)['fields']['support']==proposal['support'], 'AI must retain accepted text'
    # Unknown identifiers remain blank; absent negotiable terms get proposals.
    code,data=request('/api/contract.php',dict(body,expectedVersion=5,action='ai-fill',reviewState='{}',deadline='',clientAddress=''))
    assert code==200,(code,data)
    assert json.loads(data)['facts']['clientAddress']=='', 'Never invent an address'
    subprocess.run(['node','--check',str(root/'api'/'contract-review.js')],check=True)
    assert request('/api/contract.php?templatePreview=website',authorized=False)[0]==401
    assert request('/api/contract.php?templatePreview=unknown')[0]==422
    assert request('/api/contract.php?templatePreview=ecommerce')[1].startswith(b'%PDF-1.4')
    assert request('/api/contract.php',dict(body,action='save',expectedVersion=5,templateId='unknown'))[0]==422
    assert request('/api/contract.php',dict(body,action='generate',expectedVersion=5,reviewState='{}'))[0]==422, 'Every catalog contract must be manually reviewed'
    code,data=request('/api/contract.php',dict(body,action='apply-template',expectedVersion=5,requestedTemplateId='ecommerce'))
    assert code==200,(code,data)
    applied=json.loads(data)
    assert applied['replaceTemplate'] is True and applied['template']['id']=='ecommerce'
    assert 'price' not in applied['fields'] and applied['facts']==[], 'Applying a template must not change factual/commercial particulars'
    assert json.loads(request('/api/contract.php?session='+sid)[1])['contract']['version']==5, 'Applying a template must only fill the form'
    changed=dict(body,**applied['fields'],action='save',expectedVersion=5,templateId='ecommerce',templateRevision=1,templateVersion=0)
    code,data=request('/api/contract.php',changed); assert code==200,(code,data)
    saved=json.loads(data)['contract']
    assert saved['templateId']=='ecommerce' and saved['templateRevision']==1 and saved['templateSnapshot']['ip']==applied['fields']['ip']
    old_snapshot=saved['templateSnapshot']
    catalog_update=dict(applied['fields'],defaultTransferTerms='Payment first',defaultIpPayment='Included',action='save-template',templateId='ecommerce',csrf=token,expectedVersion=0)
    code,data=request('/api/contract.php',catalog_update);assert code==200,(code,data)
    assert json.loads(data)['template']['version']==1
    assert request('/api/contract.php',catalog_update)[0]==409
    code,data=request('/api/contract.php',dict(changed,expectedVersion=6));assert code==200,(code,data)
    assert json.loads(data)['contract']['templateVersion']==0 and json.loads(data)['contract']['templateSnapshot']==old_snapshot, 'Library edits must not rewrite a saved contract snapshot'
    assert request('/api/contract.php',dict(changed,expectedVersion=7,action='generate',clientType='protected',consumerDocuments='',reviewState='{}'))[0]==422
    assert request('/api/contract.php',dict(changed,expectedVersion=7,action='generate',dataRole='processor',dataProcessingTerms='',reviewState='{}'))[0]==422
    # The actual project API must enforce package review, receipt and start gates.
    seed_packages=r"""
require 'api/project-model.php'; require 'api/contract-pdf.php';
foreach(['website'=>'11111111111111111111111111111111','ecommerce'=>'22222222222222222222222222222222'] as $type=>$id) {
  $c=contractSampleContract($type); $c['offerVersion']=1; $c['package']=contractPackageBuild($c);
  $pdf=contractPdf($c); $c['pdfBase64']=base64_encode($pdf); $c['package']['pdfSha256']=hash('sha256',$pdf);
  writeSession(['id'=>$id,'status'=>'COMPLETED','projectState'=>[],'offer'=>['status'=>'ACCEPTED','version'=>1,'contact'=>['email'=>'test@example.com']],'contract'=>$c]); projectSave($id,[]);
}
"""
    subprocess.run(['php','-r',seed_packages],cwd=work,env=env,check=True)
    for case_id in ['1'*32,'2'*32]:
        path='/api/project-api.php?session='+case_id
        project=json.loads(request(path)[1]); project_token=project['csrf']
        confirm=dict(action='confirm_contract',csrf=project_token,evidence='Isolated test: paper agreement and every annex checked')
        assert request(path,confirm)[0]==409, 'Unreviewed package cannot be confirmed as signed'
        c=json.loads(request('/api/contract.php?session='+case_id)[1])['contract']
        event=dict(csrf=token,contract_session=case_id,expectedVersion=1,packageHash=c['package']['hash'],reviewer='Fictional reviewer',evidence='Isolated test evidence for this complete package only')
        assert request('/api/contract.php',dict(event,action='legal-review'))[0]==200
        assert request(path,confirm)[0]==200
        kickoff=dict(action='start',csrf=project_token,scope='Fictional scope for gate verification',owner='Test admin',budget=10)
        if case_id=='1'*32:
            assert request(path,kickoff)[0]==200, 'Reviewed, confirmed B2B can start'
            # Simulate interruption after reserving a mail side effect.
            pending=r"""
require 'api/project-model.php';
$id='11111111111111111111111111111111'; $s=readSession($id); $c=$s['contract'];
$entry=['reference'=>'uncertain-test','state'=>'sending','to'=>'test@example.com','at'=>time()];
$s['contract']['deliveryLog'][]=$entry; writeSession($s);
sessionDb()->prepare('INSERT INTO contract_delivery_outbox(session_id,version,package_hash,state,data,updated_at) VALUES(?,?,?,\'sending\',?,?)')->execute([$id,1,$c['package']['hash'],json_encode($entry),time()]);
"""
            subprocess.run(['php','-r',pending],cwd=work,env=env,check=True)
            assert request('/api/contract.php',dict(event,action='send'))[0]==409, 'Uncertain attempt must not duplicate mail'
            assert request('/api/contract.php',dict(event,action='resolve-delivery',deliveryOutcome='failed'))[0]==409, 'Do not resolve an active attempt'
            age="require 'api/bootstrap.php'; sessionDb()->exec(\"UPDATE contract_delivery_outbox SET updated_at=updated_at-601 WHERE session_id='11111111111111111111111111111111'\");"
            subprocess.run(['php','-r',age],cwd=work,env=env,check=True)
            assert request('/api/contract.php',dict(event,action='resolve-delivery',deliveryOutcome='failed'))[0]==200
            code,mailed=request('/api/contract.php',dict(event,action='send')); assert code==200,(code,mailed)
            assert request('/api/contract.php',dict(event,action='send'))[0]==409, 'Resolved and sent version remains idempotent'
        else:
            assert request(path,kickoff)[0]==409, 'Protected client without receipt cannot start'
            assert request('/api/contract.php',dict(event,action='record-receipt'))[0]==200
            code,denied=request(path,kickoff)
            assert code==409 and 'Start po terminie'.encode() in denied, (code,denied)
            snapshot=json.loads(request(path)[1])['project']
            assert snapshot['contract']['package']['earliestStart'] and not snapshot['jobs'], 'No agent job can escape withdrawal gate'
    print('HTTP checks passed: profile, AI fill (mock), protected fields, no AI persistence, required facts, markers, snapshots, auth, CSRF, PDF, version conflict, mock send, history, templates, admin JavaScript.')
finally:
    server.terminate();server.wait(timeout=10);log.close()
    # Only this disposable test database, never the live storage directory.
    for p in (work/'api'/'storage').glob('sessions.sqlite*'):p.unlink()
