import {JSDOM} from '../tmp/contract-ui-tests/node_modules/jsdom/lib/api.js';
import {readFileSync} from 'node:fs';
import assert from 'node:assert/strict';
const offer={version:3,status:'ACCEPTED',sections:[{title:'Zakres realizacji',items:['Strona firmy']},{title:'Poza obecnym zakresem / możliwe później',items:['Dodatkowa wersja językowa','Formularz zapytania']}]};
for(const [page,script,expected] of [['https://api.grzywniak.pl/admin.php','/offer-scope.js','/offer.php'],['http://localhost:8443/api/admin.php','/api/offer-scope.js','/api/offer.php']]) {
  const dom=new JSDOM('<article><section data-offer-section><h4>Zakres realizacji</h4><ul><li>Strona firmy</li></ul></section><section data-offer-section><h4>Poza obecnym zakresem / możliwe później</h4><ul><li>Dodatkowa wersja językowa</li><li>Formularz zapytania</li></ul></section></article>',{runScripts:'outside-only',url:page});
  const element=dom.window.document.createElement('script');element.src=script;Object.defineProperty(dom.window.document,'currentScript',{value:element});
  let body;let result;const urls=[];dom.window.confirm=()=>true;dom.window.alert=message=>{throw new Error(message);};
  dom.window.fetch=async(url,options)=>{urls.push(url);if(options) body=JSON.parse(options.body);return {ok:true,json:async()=>options?{offer:{...offer,version:4,status:'DRAFT'}}:{csrf:'test-csrf'}};};
  dom.window.eval(readFileSync('api/offer-scope.js','utf8'));
  const root=dom.window.document.querySelector('article');dom.window.offerScope.mount(root,offer,'abcdef',updated=>result=updated);
  const buttons=[...root.querySelectorAll('button')];assert.equal(buttons.length,3);
  const li=buttons[0].closest('li');assert.equal(dom.window.offerScope.readItem(li),'Dodatkowa wersja językowa','Editing must not include control text');
  buttons[0].click();await new Promise(resolve=>setTimeout(resolve,0));
  assert.equal(body.action,'promoteOptional');assert.equal(body.section,1);assert.equal(body.item,0);assert.equal(body.text,'Dodatkowa wersja językowa');assert.equal(body.expectedVersion,3);assert.equal(body.csrf,'test-csrf');assert.equal(result.status,'DRAFT');
  assert.deepEqual(urls,[expected+'?session=abcdef&format=agreement',expected+'?session=abcdef']);
  dom.window.close();
}
console.log('Optional scope UI passed: promotion, version/CSRF payload, production/local routes and clean editable content.');
