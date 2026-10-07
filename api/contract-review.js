(() => {
  const optional = new Set(['clientTaxId', 'paymentDetails', 'domainRegistrar', 'serverTarget','consumerDocuments','dataProcessingTerms','customClauses']);
  const unresolved = value => /\[DO (UZUPEŁNIENIA|UZGODNIENIA):/iu.test(value);
  const read = form => { try { return JSON.parse(form.elements.reviewState.value) || {}; } catch { return {}; } };
  const write = (form, state) => { form.elements.reviewState.value = JSON.stringify(state); };
  function inventoryEditor(input,card) {
    const rights=input.name==='rightsInventory';
    const custom=input.name==='customClauses';
    const labels=custom?{title:'Tytuł postanowienia',text:'Treść uzgodnienia z klientem'}:rights?{name:'Nazwa składnika',origin:'Pochodzenie',author:'Autor / dostawca',license:'Prawa lub warunki licencji',rightsBasis:'Podstawa dysponowania prawami',maintenanceRights:'Utrzymanie i modyfikacje przez inny zespół'}:{name:'Dostawca',service:'Usługa',location:'Lokalizacja danych'};
    const origins={'':'Wybierz',own:'Własny utwór',reusable:'Wcześniejszy komponent',third_party:'Biblioteka / osoba trzecia',client:'Materiał klienta',ai:'Element z użyciem AI'};
    const editor=document.createElement('div'); editor.dataset.inventoryEditor=''; card.append(editor);
    const rows=document.createElement('div'); editor.append(rows);
    let items;
    try { items=input.value?JSON.parse(input.value):[]; if(!Array.isArray(items)) throw new Error(); }
    catch { editor.textContent='Zapisany wykaz wymaga poprawienia formatu. Zachowano jego treść.'; return; }
    input.dataset.inventoryReady='1';
    const sync=()=>{input.value=JSON.stringify(items); input.dispatchEvent(new Event('input',{bubbles:true}));};
    const render=()=>{
      rows.replaceChildren();
      items.forEach((item,index)=>{
        const row=document.createElement('section'); row.className='contract-inventory-item'; row.style.cssText='padding:12px;margin:10px 0;border:1px solid #40516b;border-radius:8px';
        const heading=document.createElement('h5'); heading.textContent=`Pozycja ${index+1}`; row.append(heading);
        for(const [key,title] of Object.entries(labels)) {
          const label=document.createElement('label'); label.textContent=title;
          const control=document.createElement(key==='origin'?'select':custom&&key==='text'?'textarea':'input'); control.name=`_${input.name}_${index}_${key}`;
          if(key==='origin') for(const [value,text] of Object.entries(origins)) {const option=document.createElement('option');option.value=value;option.textContent=text;control.append(option);}
          control.value=item[key]||''; control.maxLength=custom?(key==='title'?200:5000):2000; control.setAttribute('aria-label',title);
          control.addEventListener('input',()=>{item[key]=control.value;sync();});
          control.addEventListener('keydown',event=>{if(event.key==='Enter'&&control.tagName!=='TEXTAREA') event.preventDefault();});
          label.append(control); row.append(label);
        }
        const remove=document.createElement('button');remove.type='button';remove.className='button';remove.textContent='Usuń pozycję';remove.onclick=()=>{items.splice(index,1);render();sync();}; row.append(remove);rows.append(row);
      });
    };
    const add=document.createElement('button');add.type='button';add.className='button';add.textContent=custom?'Dodaj własne postanowienie':rights?'Dodaj składnik':'Dodaj podwykonawcę';add.onclick=()=>{items.push(Object.fromEntries(Object.keys(labels).map(key=>[key,''])));render();sync();};editor.append(add);
    input.addEventListener('contract-inventory-refresh',()=>{try { const value=input.value?JSON.parse(input.value):[]; if(Array.isArray(value)) {items=value;render();} }catch{} });
    if(!rights&&!custom) {const none=document.createElement('button');none.type='button';none.className='button';none.textContent='Potwierdź brak podwykonawców';none.onclick=()=>{items=[];render();sync();};editor.append(none);}
    render();
  }
  function refresh(form) {
    form.querySelectorAll('[data-contract-module]').forEach(label => {
      const module=label.dataset.contractModule;
      label.hidden=(module==='hosting'&&!['agency','agency_purchase'].includes(form.elements.publicationDestination.value)) ||
        (module==='domain_purchase'&&form.elements.publicationDestination.value!=='agency_purchase') ||
        (module==='consumer'&&!['consumer','protected'].includes(form.elements.clientType.value)) ||
        (module==='dpa'&&form.elements.dataRole.value!=='processor');
    });
    form.querySelectorAll('[data-client-publication-field]').forEach(label => {
      const destination=form.elements.publicationDestination.value;
      label.hidden=destination!=='client_handoff' && !(destination==='agency_purchase' && ['productionDomain','domainRegistrar'].includes(label.dataset.publicationKey));
    });
    const state = read(form);
    form.querySelectorAll('[data-review-field]').forEach(card => {
      const name = card.dataset.reviewField;
      const input = form.elements.namedItem(name);
      const label = card.querySelector('label');
      card.hidden = label.hidden;
      const entry = state[name];
      const accepted = entry?.accepted && entry.value.trim() === input.value.trim();
      const invalidDays=name==='acceptanceDays' && (!/^\d+$/.test(input.value)||Number(input.value)<1||Number(input.value)>90);
      let invalidCustom=false;
      if(name==='customClauses'&&input.value.trim()){try{const rows=JSON.parse(input.value);invalidCustom=!Array.isArray(rows)||rows.length>30||rows.some(row=>typeof row.title!=='string'||!row.title.trim()||row.title.length>200||typeof row.text!=='string'||!row.text.trim()||row.text.length>5000);}catch{invalidCustom=true;}}
      const missing = (!input.value.trim() && !optional.has(name)) || unresolved(input.value) || invalidDays || invalidCustom;
      card.dataset.missing=missing?'1':'0';
      card.dataset.accepted = accepted ? '1' : '0';
      card.querySelector('[data-review-status]').textContent = accepted ? (!input.value.trim()&&optional.has(name)?'Nie dotyczy':'Zaakceptowano') : missing ? 'Uzupełnij dane' : entry ? 'Propozycja do akceptacji' : 'Dane do sprawdzenia';
      card.querySelectorAll('[data-review-skip],[data-no-data-processing]').forEach(button=>{button.disabled=!!form.dataset.saving;});
      const accept = card.querySelector('[data-review-accept]');
      accept.disabled = !!accepted || missing || !!form.dataset.saving;
      accept.textContent = accepted ? 'Zaakceptowano' : 'Akceptuj';
      const edit = card.querySelector('[data-review-edit]');
      edit.disabled = !!form.dataset.saving;
      input.hidden = !!accepted || !!input.dataset.inventoryReady;
      const inventory=card.querySelector('[data-inventory-editor]'); if(inventory) inventory.hidden=!!accepted && !(name==='customClauses'&&(!input.value||input.value==='[]'));
      const choices=card.querySelector('[data-day-choices]'); if(choices) { choices.hidden=!!accepted; choices.querySelectorAll('button').forEach(button=>{button.disabled=!!form.dataset.saving;button.setAttribute('aria-pressed',String(button.dataset.days===input.value));}); }
      const suggestions=card.querySelector('[data-field-suggestions]'); if(suggestions) { suggestions.hidden=!!accepted; suggestions.querySelectorAll('select,button').forEach(control=>{control.disabled=!!form.dataset.saving;}); }
      const preview = card.querySelector('[data-review-preview]');
      preview.hidden = !accepted;
      preview.textContent = input.tagName === 'SELECT' ? input.selectedOptions[0]?.textContent || input.value : input.value || 'Nie dotyczy';
      if(input.dataset.inventoryReady && accepted) {
        try {const rows=JSON.parse(input.value); preview.textContent=rows.length?rows.map((item,i)=>`${i+1}. ${Object.values(item).join(' · ')}`).join('\n'):name==='customClauses'?'Brak dodatkowych postanowień.':'Potwierdzono brak podwykonawców.';preview.style.whiteSpace='pre-wrap';}catch{}
      }
    });
    const pending = Object.entries(state).filter(([name,item]) => {
      const input=form.elements.namedItem(name);
      const card=input?.closest('[data-review-field]');
      return card && !card.hidden && (!item.accepted || item.value.trim()!==input.value.trim());
    }).length;
    form.querySelector('[data-review-summary]').textContent = pending ? `Do przeglądu: ${pending} pól. Przejrzyj i zatwierdź poszczególne zakładki, następnie przygotuj PDF.` : 'Akceptacja pól zatwierdza projekt wewnętrznie — nie oznacza podpisania umowy przez klienta.';
    window.contractWorkspace?.refresh(form);
  }
  function init(form) {
    if(!form?.elements.reviewState || form.dataset.reviewReady) return;
    form.dataset.reviewReady='1';
    const summary=document.createElement('p'); summary.dataset.reviewSummary=''; summary.setAttribute('role','status'); form.prepend(summary);
    [...form.querySelectorAll('textarea,input:not([type="hidden"]),select')].forEach(input => {
      const label=input.closest('label'); if(!label || input.hasAttribute('data-template-selector')) return;
      const card=document.createElement('section'); card.dataset.reviewField=input.name;
      const state=read(form);
      if(!state[input.name]) { state[input.name]={value:input.value,accepted:optional.has(input.name)&&!input.value.trim()}; write(form,state); }
      label.before(card); card.append(label);
      const preview=document.createElement('p'); preview.dataset.reviewPreview=''; preview.style.whiteSpace='pre-wrap'; card.append(preview);
      const status=document.createElement('span'); status.dataset.reviewStatus=''; status.setAttribute('role','status'); card.append(status);
      const accept=document.createElement('button'); accept.type='button'; accept.className='button'; accept.dataset.reviewAccept='';
      accept.onclick=() => { const state=read(form); state[input.name]={value:input.value,accepted:true}; write(form,state); refresh(form); };
      const edit=document.createElement('button'); edit.type='button'; edit.className='button'; edit.dataset.reviewEdit=''; edit.textContent='Zmień';
      edit.onclick=() => { const state=read(form); state[input.name]={value:input.value,accepted:false}; write(form,state); refresh(form); input.focus(); };
      card.append(accept,edit);
      if(optional.has(input.name)) {
        const skip=document.createElement('button');skip.type='button';skip.className='button';skip.dataset.reviewSkip='';skip.textContent='Nie dotyczy';
        skip.onclick=()=>{
          if(form.dataset.saving)return;
          input.value='';input.dispatchEvent(new Event('input',{bubbles:true}));
          input.dispatchEvent(new Event('contract-inventory-refresh'));
          const state=read(form);state[input.name]={value:'',accepted:true};write(form,state);refresh(form);
        };
        card.append(skip);
      }
      if(input.name==='dataRole') {
        const none=document.createElement('button');none.type='button';none.className='button';none.dataset.noDataProcessing='';none.textContent='Nie dotyczy — brak powierzenia';
        none.onclick=()=>{if(form.dataset.saving)return;input.value='none';input.dispatchEvent(new Event('change',{bubbles:true}));};
        const note=document.createElement('p');note.textContent='Wybierz tylko, gdy nie przetwarzamy danych osobowych w imieniu klienta. Ukryje pola powierzenia. Dane kontaktowe stron umowy są sprawdzane osobno.';card.append(none,note);
      }
      const change=() => {
        const state=read(form); state[input.name]={value:input.value,accepted:false};
        for(const [key,visible] of [['consumerDocuments',['consumer','protected'].includes(form.elements.clientType.value)],['dataProcessingTerms',form.elements.dataRole.value==='processor']]) {
          const label=form.elements.namedItem(key)?.closest('label'); if(label) label.hidden=!visible;
        }
        if(input.name==='ipMode') {
          const license=form.querySelector('[data-license-terms]'); if(license) license.hidden=!['exclusive','nonexclusive'].includes(input.value);
          for(const key of ['ip','terms']) if(state[key]) state[key].accepted=false;
        }
        const dependencies = {
          clientType:['terms'], dataRole:['terms'], rightsTerms:['ip'], signing:['ip'],
          consumerDocuments:['terms'], dataProcessingTerms:['terms'],
          publicationDestination:['deploymentTerms'], productionDomain:['deploymentTerms'],
          domainRegistrant:['deploymentTerms'], domainPurchaseTerms:['deploymentTerms'],
          domainAvailabilityTerms:['deploymentTerms'], domainRenewalTerms:['deploymentTerms'],
          domainHandoverTerms:['deploymentTerms'],
          productionHosting:['deploymentTerms'], domainOwnershipTerms:['deploymentTerms'],
          backupResponsibility:['deploymentTerms'], dnsTlsResponsibility:['deploymentTerms']
        };
        for(const key of dependencies[input.name] || []) if(state[key]) state[key].accepted=false;
        write(form,state); refresh(form);
      };
      input.addEventListener('input',change); input.addEventListener('change',change);
      if(input.name==='acceptanceDays') {
        const choices=document.createElement('div'); choices.dataset.dayChoices=''; choices.setAttribute('role','group'); choices.setAttribute('aria-label','Propozycje terminu sprawdzenia'); choices.style.cssText='display:flex;gap:8px;flex-wrap:wrap;margin:10px 0';
        for(const days of [3,7,14,30]) { const button=document.createElement('button');button.type='button';button.className='button';button.dataset.days=String(days);button.textContent=`${days} dni`;button.onclick=()=>{input.value=String(days);change();input.focus();};choices.append(button); }
        label.after(choices);
      }
      if(input.hasAttribute('data-contract-inventory')||input.name==='customClauses') inventoryEditor(input,card);
      if(input.dataset.contractSuggestions) {
        let examples={};try {examples=JSON.parse(input.dataset.contractSuggestions);}catch{}
        if(Object.keys(examples).length) {
          const box=document.createElement('details');box.dataset.fieldSuggestions='';box.style.cssText='margin:12px 0;padding:12px;border:1px solid #40516b;border-radius:8px';
          const heading=document.createElement('summary');heading.textContent='Gotowe propozycje';heading.style.cursor='pointer';box.append(heading);
          const note=document.createElement('p');note.textContent='Opcjonalny wariant do zastąpienia obecnego zapisu. Przejrzyj treść i zatwierdź ją w tej zakładce.';box.append(note);
          const select=document.createElement('select');select.setAttribute('aria-label','Propozycja: '+label.firstChild.textContent);select.style.cssText='width:100%;margin-bottom:10px';
          for(const [title,value] of Object.entries(examples)) {const option=document.createElement('option');option.value=title;option.textContent=title;select.append(option);}
          const preview=document.createElement('p');preview.style.cssText='white-space:pre-wrap;max-height:180px;overflow:auto;font-size:13px';
          const show=()=>{preview.textContent=input.hasAttribute('data-contract-inventory')?'Doda pola wykazu z oznaczeniami do uzupełnienia.':examples[select.value];};select.onchange=show;show();
          const use=document.createElement('button');use.type='button';use.className='button';use.textContent=input.hasAttribute('data-contract-inventory')?'Dodaj przykładową pozycję':'Wstaw propozycję';
          use.onclick=()=>{
            const value=examples[select.value];
            if(input.hasAttribute('data-contract-inventory')) {
              let rows=[];try {rows=input.value?JSON.parse(input.value):[];if(!Array.isArray(rows)) throw new Error();}catch {window.alert('Popraw format istniejącego wykazu przed dodaniem propozycji.');return;}
              input.value=JSON.stringify([...rows,...JSON.parse(value)]);input.dispatchEvent(new Event('contract-inventory-refresh'));
            } else {
              if(input.value.trim() && !window.confirm('Zastąpić obecną treść pola wybraną propozycją?')) return;
              input.value=value;
            }
            change();box.open=false;
            if(!input.dataset.inventoryReady) input.focus();
          };
          box.append(select,preview,use);card.append(box);
        }
      }
      input.addEventListener('keydown',event=>{
        if(event.key!=='Enter'||input.tagName==='TEXTAREA'||event.isComposing) return;
        const accept=card.querySelector('[data-review-accept]');
        if(!accept) return;
        // Enter in a single-line review field confirms that field, rather than
        // implicitly submitting the whole contract form (usually AI fill).
        event.preventDefault();
        if(!accept.disabled) accept.click();
      });
    });
    refresh(form);
    window.contractWorkspace?.init(form);
  }
  function apply(form, result) {
    init(form); const state=read(form);
    for(const [name,value] of Object.entries({...result.fields,...result.facts})) {
      const input=form.elements.namedItem(name); if(!input || typeof value!=='string') continue;
      // Re-running AI never overwrites an accepted value.
      if(!result.replaceTemplate && !result.replaceCommercial && state[name]?.accepted && state[name].value.trim()===input.value.trim()) continue;
      input.value=value; state[name]={value,accepted:optional.has(name)&&!value.trim()};
      if(input.dataset.inventoryReady) input.dispatchEvent(new Event('contract-inventory-refresh'));
    }
    if(result.replaceCommercial) {
      form.elements.commercialHash.value=result.commercialHash;
      const preview=form.querySelector('[data-commercial-preview]');
      if(preview) { preview.replaceChildren(); for(const [label,text] of Object.entries(result.commercialSections||{})) {const title=document.createElement('h5');title.textContent=label;const p=document.createElement('p');p.style.whiteSpace='pre-wrap';p.textContent=text;preview.append(title,p);} }
      form.querySelector('[data-commercial-warning]')?.remove();
      const warning=form.closest('#contract-panel')?.querySelector('[data-contract-refresh],[data-contract-outdated]');
      if(warning){warning.style.background='#123322';warning.style.borderColor='#3eaf73';warning.setAttribute('role','status');warning.querySelector('strong').textContent='Formularz uzupełniony z aktualnej oferty. Przejrzyj sekcje i zatwierdź nowy PDF.';warning.querySelector('[data-contract-reprepare-status]').textContent='Poprzedni zapisany PDF pozostaje bez zmian do zatwierdzenia nowej wersji.';}
      const diff=form.querySelector('[data-commercial-diff]');
      if(diff) {
        diff.replaceChildren(); const note=document.createElement('p');note.textContent='Wczytano warunki aktualnej oferty. Sprawdź zmienione pola i zaakceptuj je przed PDF. Poprzednia umowa pozostaje w historii.';diff.append(note);
        for(const change of result.differences||[]) {const detail=document.createElement('details');const title=document.createElement('summary');title.textContent=change.label;const old=document.createElement('p');old.textContent='Poprzednio: '+change.previous;const next=document.createElement('p');next.textContent='Aktualna oferta: '+change.current;detail.append(title,old,next);diff.append(detail);}
      }
    }
    if(result.template) {
      form.elements.templateId.value=result.template.id;
      form.elements.templateVersion.value=result.template.version;
      form.elements.templateRevision.value=result.template.revision;
      form.querySelector('[data-template-current]').textContent='Aktualny: '+result.template.title;
    }
    const license=form.querySelector('[data-license-terms]'); if(license) license.hidden=!['exclusive','nonexclusive'].includes(form.elements.ipMode.value);
    for(const [key,visible] of [['consumerDocuments',['consumer','protected'].includes(form.elements.clientType.value)],['dataProcessingTerms',form.elements.dataRole.value==='processor']]) {
      const label=form.elements.namedItem(key)?.closest('label'); if(label) label.hidden=!visible;
    }
    write(form,state); refresh(form);
    window.contractWorkspace?.refresh(form);
  }
  window.contractReview={init,apply,refresh};
  document.addEventListener('submit',async event=>{
    const form=event.target;if(!form.matches('.contract-archive-upload'))return;
    event.preventDefault();if(form.dataset.saving)return;
    const feedback=form.querySelector('[role="alert"]');const button=form.querySelector('button');
    const file=form.elements.document.files[0];
    if(!file||file.size>10*1024*1024){feedback.textContent='Wybierz PDF o rozmiarze do 10 MB.';feedback.style.color='#ffb4b4';return;}
    form.dataset.saving='1';button.disabled=true;feedback.textContent='Zapisywanie dokumentu w archiwum…';
    try{const response=await fetch(form.action,{method:'POST',headers:{Accept:'application/json'},body:new FormData(form)});const result=await response.json();if(!response.ok)throw new Error(result.message||'Nie udało się zapisać dokumentu.');location.reload();}
    catch(error){feedback.textContent=error.message||'Nie udało się przesłać dokumentu.';feedback.style.color='#ffb4b4';button.disabled=false;delete form.dataset.saving;}
  });
  document.addEventListener('click',event=>{
    const button=event.target.closest('[data-contract-reprepare]');if(!button)return;
    const panel=button.closest('#contract-panel');const form=panel?.querySelector('form.contract-form');
    const action=form?.querySelector('[name="contract_action"][value="auto-prepare"]');if(!action || form.dataset.saving)return;
    for(let parent=form.parentElement;parent&&parent!==panel;parent=parent.parentElement) if(parent.tagName==='DETAILS')parent.open=true;
    form._selectContractPane?.('summary');
    action.click();
  });
})();
