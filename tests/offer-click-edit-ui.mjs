import {JSDOM} from '../tmp/contract-ui-tests/node_modules/jsdom/lib/api.js';
import {readFileSync} from 'node:fs';
import assert from 'node:assert/strict';
const php=readFileSync('api/admin.php','utf8');
const source=php.slice(php.indexOf('  const renderOfferPreview ='),php.indexOf('  const addVisibleOfferButton ='));
for(const status of ['DRAFT','ACCEPTED','OUTDATED']) {
  const dom=new JSDOM('<section class="panel"><div id="offer"></div></section>',{runScripts:'outside-only',url:'https://api.grzywniak.pl/admin.php'});
  let payload;let fail=false;
  const offer={status,version:7,project:'Firma',summary:'Opis firmy',sections:[{title:'Zakres realizacji',items:['Strona firmy']},{title:'Poza obecnym zakresem / możliwe później',items:['Galeria']}],pricing:{net:1000,vatRate:23,gross:1230},_versions:[{version:6}]};
  dom.window.confirm=()=>true;dom.window.alert=message=>{throw new Error(message);};
  dom.window.fetch=async(url,options)=>({ok:!fail,status:fail?409:200,json:async()=>{if(options)payload=JSON.parse(options.body);return fail?{message:'Oferta zmieniła się. Zachowano Twoje wpisy.'}:options?{offer:{...offer,version:8,summary:payload.summary,status:status==='OUTDATED'?'OUTDATED':'DRAFT'}}:{csrf:'test-csrf'};}});
  dom.window.eval(source+'window.renderOffer=renderOfferPreview;');
  const container=dom.window.document.querySelector('#offer');dom.window.renderOffer(container,offer,'abcdef');
  const summary=[...container.querySelectorAll('p')].find(p=>p.textContent==='Opis firmy');summary.click();
  assert.equal(summary.contentEditable,'true',status+' supports click editing');
  const save=[...container.querySelectorAll('button')].find(b=>b.textContent==='Zapisz zmiany');assert.ok(save && !save.parentElement.hidden);
  summary.textContent='Zmieniony opis';fail=true;save.click();await new Promise(resolve=>setTimeout(resolve,0));
  assert.equal(summary.textContent,'Zmieniony opis','Conflict preserves entered text');assert.equal(save.disabled,false);
  assert.match(save.parentElement.textContent,/Zachowano/);
  fail=false;save.click();await new Promise(resolve=>setTimeout(resolve,0));
  assert.equal(payload.expectedVersion,7);assert.equal(payload.csrf,'test-csrf');assert.equal(payload.summary,'Zmieniony opis');
  assert.ok([...container.querySelectorAll('p')].some(p=>p.textContent==='Zmieniony opis'));
  dom.window.close();
}
console.log('Click editing UI passed: drafts/accepted/outdated offers, explicit versioned save and preserving text on conflict.');
