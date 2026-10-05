import {JSDOM} from '../tmp/contract-ui-tests/node_modules/jsdom/lib/api.js';
import {readFileSync} from 'node:fs';
import assert from 'node:assert/strict';
const fixture=JSON.parse(readFileSync('tmp/offer-agreement-ui-fixture.json','utf8'));
fixture.dependencyReview={pricing:false,cooperationTerms:false,acceptanceDays:true};
fixture.decisions={decision1:{question:'Kto dostarcza zdjęcia?',answer:'Klient w 7 dni',version:2,author:'owner',target:''}};
fixture.decisionTargets={cooperationTerms:'Materiały'};
const dom=new JSDOM('<div id="offer"></div>',{runScripts:'outside-only'});
let saved;let updated;
dom.window.fetch=async (url,options)=>({ok:true,json:async()=>options?(saved=JSON.parse(options.body),{offer:{version:2,status:'DRAFT'}}):fixture});
dom.window.eval(readFileSync('api/offer-agreement.js','utf8'));
await dom.window.offerAgreement.mount(dom.window.document.querySelector('#offer'),{version:1},'abcdef',offer=>{updated=offer;});
const form=dom.window.document.querySelector('form');assert.ok(form);
assert.equal(form.matches('.contract-form:not(.offer-agreement-form)'),false,'Admin contract handler must not intercept offer saves');
const materialsCheck=form.querySelector('[data-dependency="cooperationTerms"]');
materialsCheck.checked=true;
form.elements.cooperationTerms.dispatchEvent(new dom.window.Event('input'));
assert.equal(materialsCheck.checked,false,'Editing a dependency cancels its review');
form.querySelector('[data-dependency="acceptanceDays"]').checked=true;
[...form.elements.acceptanceDays.closest('label').querySelectorAll('button')].find(b=>b.textContent==='7 dni').click();
assert.equal(form.querySelector('[data-dependency="acceptanceDays"]').checked,false,'Preset cancels dependency review too');
materialsCheck.checked=true;
form.querySelector('[data-decision-target]').value='cooperationTerms';
const publication=form.elements.publicationDestination;
publication.value='agency_purchase';publication.dispatchEvent(new dom.window.Event('change'));
assert.equal(form.elements.domainPurchaseTerms.closest('label').hidden,false);
assert.equal(form.elements.productionHosting.closest('label').hidden,true);
assert.equal(dom.window.getComputedStyle(form.elements.productionHosting.closest('label')).display,'none','Conditional fields are visually hidden despite inline grid styling');
publication.value='client_handoff';publication.dispatchEvent(new dom.window.Event('change'));
assert.equal(form.elements.productionHosting.closest('label').hidden,false);
assert.equal(form.elements.hostingFee.closest('label').hidden,true);
assert.equal(form.elements.acceptanceDays.type,'number');
form.elements.cooperationTerms.value='Agreed materials';
form.dispatchEvent(new dom.window.Event('submit',{bubbles:true,cancelable:true}));
await new Promise(resolve=>setTimeout(resolve,0));
assert.equal(saved.action,'updateAgreement');assert.equal(saved.csrf,fixture.csrf);assert.equal(saved.expectedVersion,1);
assert.equal(saved.agreement.cooperationTerms,'Agreed materials');assert.equal(updated.status,'DRAFT');
assert.equal(saved.decisionTargets.decision1,'cooperationTerms');
assert.equal(saved.reviewedDependencies.cooperationTerms,true);
assert.equal(saved.reviewedDependencies.pricing,false);
// Production has a flat API document root; local development uses /api/.
for(const [page,source,expected] of [
  ['https://api.grzywniak.pl/admin.php','/offer-agreement.js','/offer.php'],
  ['http://localhost:8443/api/admin.php','/api/offer-agreement.js','/api/offer.php'],
]) {
  const routing=new JSDOM('<div id="offer"></div>',{runScripts:'outside-only',url:page});
  const external=routing.window.document.createElement('script');external.src=source;
  Object.defineProperty(routing.window.document,'currentScript',{value:external});
  const requests=[];
  routing.window.fetch=async(url,options)=>{requests.push(url);return {ok:true,json:async()=>options?{offer:{version:2,status:'DRAFT'}}:fixture};};
  routing.window.eval(readFileSync('api/offer-agreement.js','utf8'));
  await routing.window.offerAgreement.mount(routing.window.document.querySelector('#offer'),{version:1},'abcdef',()=>{});
  const routedForm=routing.window.document.querySelector('form');assert.ok(routedForm);
  routedForm.dispatchEvent(new routing.window.Event('submit',{bubbles:true,cancelable:true}));
  await new Promise(resolve=>setTimeout(resolve,0));
  assert.deepEqual(requests,[expected+'?session=abcdef&format=agreement',expected+'?session=abcdef'],'GET and POST must use the actual API document root');
  routing.window.close();
}
const errorDom=new JSDOM('<div id="offer"></div>',{runScripts:'outside-only',url:'https://api.grzywniak.pl/admin.php'});
errorDom.window.fetch=async()=>({ok:false,status:404,json:async()=>{throw new SyntaxError('Unexpected token <');}});
errorDom.window.eval(readFileSync('api/offer-agreement.js','utf8'));
await errorDom.window.offerAgreement.mount(errorDom.window.document.querySelector('#offer'),{version:1},'abcdef',()=>{});
assert.match(errorDom.window.document.body.textContent,/HTTP 404/);
assert.doesNotMatch(errorDom.window.document.body.textContent,/Unexpected token/);
errorDom.window.close();
console.log('Offer agreement UI passed: fields, versioned save, production/local API routes and readable HTML response errors.');
