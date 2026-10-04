(() => {
  const optional = new Set(['clientTaxId', 'paymentDetails', 'domainRegistrar', 'serverTarget','consumerDocuments','dataProcessingTerms']);
  const unresolved = value => /\[DO (UZUPEŁNIENIA|UZGODNIENIA):/iu.test(value);
  const read = form => { try { return JSON.parse(form.elements.reviewState.value) || {}; } catch { return {}; } };
  const write = (form, state) => { form.elements.reviewState.value = JSON.stringify(state); };
  function inventoryEditor(input,card) {
    const rights=input.name==='rightsInventory';
    const labels=rights?{name:'Nazwa składnika',origin:'Pochodzenie',author:'Autor / dostawca',license:'Prawa lub warunki licencji',rightsBasis:'Podstawa dysponowania prawami',maintenanceRights:'Utrzymanie i modyfikacje przez inny zespół'}:{name:'Dostawca',service:'Usługa',location:'Lokalizacja danych'};
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
          const control=document.createElement(key==='origin'?'select':'input'); control.name=`_${input.name}_${index}_${key}`;
          if(key==='origin') for(const [value,text] of Object.entries(origins)) {const option=document.createElement('option');option.value=value;option.textContent=text;control.append(option);}
          control.value=item[key]||''; control.maxLength=2000; control.setAttribute('aria-label',title);
          control.addEventListener('input',()=>{item[key]=control.value;sync();});
          control.addEventListener('keydown',event=>{if(event.key==='Enter') event.preventDefault();});
          label.append(control); row.append(label);
        }
        const remove=document.createElement('button');remove.type='button';remove.className='button';remove.textContent='Usuń pozycję';remove.onclick=()=>{items.splice(index,1);render();sync();}; row.append(remove);rows.append(row);
      });
    };
    const add=document.createElement('button');add.type='button';add.className='button';add.textContent=rights?'Dodaj składnik':'Dodaj podwykonawcę';add.onclick=()=>{items.push(Object.fromEntries(Object.keys(labels).map(key=>[key,''])));render();sync();};editor.append(add);
    input.addEventListener('contract-inventory-refresh',()=>{try { const value=JSON.parse(input.value); if(Array.isArray(value)) {items=value;render();} }catch{} });
    if(!rights) {const none=document.createElement('button');none.type='button';none.className='button';none.textContent='Potwierdź brak podwykonawców';none.onclick=()=>{items=[];render();sync();};editor.append(none);}
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
      const missing = (!input.value.trim() && !optional.has(name)) || unresolved(input.value) || invalidDays;
      card.dataset.accepted = accepted ? '1' : '0';
      card.querySelector('[data-review-status]').textContent = accepted ? 'Zaakceptowano' : missing ? 'Uzupełnij dane' : entry ? 'Propozycja do akceptacji' : 'Dane do sprawdzenia';
      const accept = card.querySelector('[data-review-accept]');
      accept.disabled = !!accepted || missing || !!form.dataset.saving;
      accept.textContent = accepted ? 'Zaakceptowano' : 'Akceptuj';
      const edit = card.querySelector('[data-review-edit]');
      edit.disabled = !!form.dataset.saving;
      input.hidden = !!accepted || !!input.dataset.inventoryReady;
      const inventory=card.querySelector('[data-inventory-editor]'); if(inventory) inventory.hidden=!!accepted;
      const choices=card.querySelector('[data-day-choices]'); if(choices) { choices.hidden=!!accepted; choices.querySelectorAll('button').forEach(button=>{button.disabled=!!form.dataset.saving;button.setAttribute('aria-pressed',String(button.dataset.days===input.value));}); }
      const suggestions=card.querySelector('[data-field-suggestions]'); if(suggestions) { suggestions.hidden=!!accepted; suggestions.querySelectorAll('select,button').forEach(control=>{control.disabled=!!form.dataset.saving;}); }
      const preview = card.querySelector('[data-review-preview]');
      preview.hidden = !accepted;
      preview.textContent = input.tagName === 'SELECT' ? input.selectedOptions[0]?.textContent || input.value : input.value || 'Nie dotyczy';
      if(input.dataset.inventoryReady && accepted) {
        try {const rows=JSON.parse(input.value); preview.textContent=rows.length?rows.map((item,i)=>`${i+1}. ${Object.values(item).join(' · ')}`).join('\n'):'Potwierdzono brak podwykonawców.';preview.style.whiteSpace='pre-wrap';}catch{}
      }
    });
    const pending = Object.entries(state).filter(([name,item]) => {
      const input=form.elements.namedItem(name);
      const card=input?.closest('[data-review-field]');
      return card && !card.hidden && (!item.accepted || item.value.trim()!==input.value.trim());
    }).length;
    form.querySelector('[data-review-summary]').textContent = pending ? `Do akceptacji: ${pending} pól. Możesz zapisać projekt i wrócić do niego później.` : 'Akceptacja pól zatwierdza projekt wewnętrznie — nie oznacza podpisania umowy przez klienta.';
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
      if(input.hasAttribute('data-contract-inventory')) inventoryEditor(input,card);
      if(input.dataset.contractSuggestions) {
        let examples={};try {examples=JSON.parse(input.dataset.contractSuggestions);}catch{}
        if(Object.keys(examples).length) {
          const box=document.createElement('details');box.dataset.fieldSuggestions='';box.style.cssText='margin:12px 0;padding:12px;border:1px solid #40516b;border-radius:8px';
          const heading=document.createElement('summary');heading.textContent='Gotowe propozycje';heading.style.cursor='pointer';box.append(heading);
          const note=document.createElement('p');note.textContent='Wybierz wariant, dopasuj treść i uzupełnij oznaczone miejsca. Propozycja wymaga osobnej akceptacji.';box.append(note);
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
  }
  function apply(form, result) {
    init(form); const state=read(form);
    for(const [name,value] of Object.entries({...result.fields,...result.facts})) {
      const input=form.elements.namedItem(name); if(!input || typeof value!=='string') continue;
      // Re-running AI never overwrites an accepted value.
      if(!result.replaceTemplate && state[name]?.accepted && state[name].value.trim()===input.value.trim()) continue;
      input.value=value; state[name]={value,accepted:optional.has(name)&&!value.trim()};
      if(input.dataset.inventoryReady) input.dispatchEvent(new Event('contract-inventory-refresh'));
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
  }
  window.contractReview={init,apply,refresh};
})();
