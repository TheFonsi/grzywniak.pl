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
seed="require 'api/bootstrap.php'; writeSession(['id'=>'"+sid+"','status'=>'COMPLETED','messages'=>[['role'=>'user','content'=>'test']],'offer'=>['status'=>'ACCEPTED','version'=>3,'contact'=>['name'=>'Client','email'=>'test@example.com'],'pricing'=>['net'=>100,'vatRate'=>8]],'projectState'=>[]]);"
subprocess.run(['php','-r',seed],cwd=work,env=env,check=True)
sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close()
log=open(work/'server.log','w')
server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(work)],cwd=work,env=env,stdout=log,stderr=log)
opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
auth='Basic '+base64.b64encode(b'contract-test:test-password').decode()
def request(path,body=None,authorized=True):
    headers={'Authorization':auth} if authorized else {}
    if body is not None:headers['Content-Type']='application/json'
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
    body={k.decode():'Example text' for k in fields}
    body.update(csrf=token,contract_session=sid,expectedVersion=0,templateVersion=0,profileVersion=1,action='generate',clientType='business',ipMode='transfer',signing='qualified',dataRole='none',clientAddress='Testowa 2, Warszawa',clientTaxId='',clientRepresentative='Jan Test',contractDate='2026-09-15')
    assert b'name="ipPayment"' not in html and b'data-license-terms hidden' in html
    assert request('/api/contract.php',dict(body,ipMode='exclusive',rightsTerms=''))[0]==422
    code,data=request('/api/contract.php',dict(body,action='ai-fill'));assert code==200,(code,data)
    ai=json.loads(data);assert 'scope' in ai['fields'] and ai['fields']['price']==body['price'] and ai['fields']['provider']==body['provider'] and ai['facts']['clientAddress']==body['clientAddress']
    assert json.loads(request('/api/contract.php?session='+sid)[1])['contract'] is None, 'AI must not persist or send'
    assert request('/api/contract.php',dict(body,clientType='invented',action='ai-fill'))[0]==422
    assert request('/api/contract.php',dict(body,scope='[DO UZUPEŁNIENIA: brak danych]'))[0]==422
    assert request('/api/contract.php',dict(body,clientType=''))[0]==422
    assert request('/api/contract.php',dict(body,contractDate='2026-02-31'))[0]==422
    assert request('/api/contract.php',dict(body,csrf='bad'))[0]==403
    code,data=request('/api/contract.php',body);assert code==200,(code,data)
    assert json.loads(data)['contract']['version']==1
    saved_facts=json.loads(data)['contract']['facts']
    assert 'pełnego wynagrodzenia' in saved_facts['rightsTerms'] and 'zawarte w cenie' in saved_facts['ipPayment']
    code,pdf=request('/api/contract.php?session='+sid+'&format=pdf');assert code==200 and pdf.startswith(b'%PDF-')
    assert request('/api/contract.php',body)[0]==409
    code,data=request('/api/contract.php',dict(body,expectedVersion=1,action='send'));assert code==200,(code,data)
    assert json.loads(data)['contract']['status']=='MOCK_SENT'
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
    print('HTTP checks passed: profile, AI fill (mock), protected fields, no AI persistence, required facts, markers, snapshots, auth, CSRF, PDF, version conflict, mock send, history, templates, admin JavaScript.')
finally:
    server.terminate();server.wait(timeout=10);log.close()
    # Only this disposable test database, never the live storage directory.
    for p in (work/'api'/'storage').glob('sessions.sqlite*'):p.unlink()
