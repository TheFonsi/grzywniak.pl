import { JSDOM } from '../tmp/contract-ui-tests/node_modules/jsdom/lib/api.js';
import { readFileSync } from 'node:fs';
import assert from 'node:assert/strict';
const read=p=>readFileSync(new URL(p,import.meta.url),'utf8');
const dom=new JSDOM(read('../tmp/contract-http/contract-editor.html'),{runScripts:'outside-only',url:'https://example.test/admin.php'});
const {window:w}=dom;const panel=w.document.querySelector('#contract-panel');
const banner=w.document.createElement('div');banner.dataset.contractOutdated='';banner.innerHTML='<strong>Stara umowa</strong><button type="button" data-contract-reprepare>Przygotuj ponownie</button><p data-contract-reprepare-status></p>';panel.prepend(banner);
w.eval(read('../api/contract-workspace.js'));w.eval(read('../api/contract-review.js'));
const form=panel.querySelector('form.contract-form');w.contractReview.init(form);
const admin=read('../api/admin.php');const start=admin.indexOf("  document.addEventListener('submit', async event => {");const end=admin.indexOf('  const enhanceUi',start);w.eval(admin.slice(start,end));
let requests=0;w.fetch=async()=>{requests++;return {ok:true,json:async()=>({fields:{scope:'Nowy zakres'},facts:{},replaceCommercial:true,commercialHash:'new-hash',commercialSections:{}})};};
banner.querySelector('button').click();await new Promise(resolve=>setTimeout(resolve,25));
assert.equal(requests,1,'Reprepare must actually submit auto-prepare');
assert.equal(form.elements.scope.value,'Nowy zakres',form.querySelector('[role=alert]')?.textContent);
assert.match(banner.querySelector('[data-contract-reprepare-status]').textContent,/PDF|Formularz/);
assert.equal(form.dataset.saving,undefined,'Buttons must be released');
assert.equal(banner.querySelector('button').disabled,false);
form.querySelector('[value="generate"]').click();
assert.equal(requests,1,'Incomplete or unapproved tabs must block PDF without submitting');
assert.equal(form.querySelector('[data-contract-workspace-errors]').hidden,false);

// Server failures must replace the loading message and allow a retry.
w.fetch=async()=>({ok:false,json:async()=>({message:'Offer version conflict'})});
banner.querySelector('button').click();await new Promise(resolve=>setTimeout(resolve,25));
assert.equal(banner.querySelector('[data-contract-reprepare-status]').textContent,'Offer version conflict');
assert.equal(form.dataset.saving,undefined);
assert.equal(banner.querySelector('button').disabled,false);

// Preserve actual edits made during the request, including checkbox changes.
const reviewCheckbox=w.document.createElement('input');reviewCheckbox.type='checkbox';form.append(reviewCheckbox);
for(const edit of [()=>{form.elements.scope.value='Manual edit';},()=>{
  const checkbox=form.querySelector('input[type=checkbox]');checkbox.checked=!checkbox.checked;
}]) {
  let resolveRequest;
  w.fetch=()=>{requests++;return new Promise(resolve=>{resolveRequest=resolve;});};
  banner.querySelector('button').click();
  const count=requests;
  banner.querySelector('button').click();
  assert.equal(requests,count,'No duplicate request while preparing');
  assert.equal(banner.querySelector('button').disabled,true);
  edit();
  resolveRequest({ok:true,json:async()=>({fields:{scope:'Must not overwrite'},facts:{}})});
  await new Promise(resolve=>setTimeout(resolve,25));
  assert.equal(form.elements.scope.value,'Manual edit');
  assert.match(banner.querySelector('[data-contract-reprepare-status]').textContent,/Zachowano/);
  assert.equal(form.dataset.saving,undefined);
  assert.equal(banner.querySelector('button').disabled,false);
}
console.log('Contract reprepare UI passed.');
