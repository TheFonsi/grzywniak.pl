(() => {
  async function mount(container,offer,sessionId,onSave) {
    const box=document.createElement('details');box.style.cssText='margin:18px 0;padding:16px;border:1px solid #465474;border-radius:12px';
    const heading=document.createElement('summary');heading.textContent='Warunki realizacji i dane do umowy';heading.style.cursor='pointer';box.append(heading);container.append(box);
    try {
      const response=await fetch('/api/offer.php?session='+encodeURIComponent(sessionId)+'&format=agreement');const data=await response.json();if(!response.ok) throw new Error(data.message||'Nie udało się pobrać ustaleń.');
      if(!box.isConnected) return;
      const form=document.createElement('form');form.className='contract-form';form.style.cssText='display:grid;gap:14px;margin-top:16px';box.append(form);
      const note=document.createElement('p');note.textContent='Te warunki pokażemy klientowi w PDF i przeniesiemy do umowy po akceptacji oferty. Zapis tworzy nową wersję do weryfikacji. Nieznane dane wymagają uzgodnienia.';form.append(note);
      const controls={};const labels={};
      for(const [key,field] of Object.entries(data.fields)) {
        const label=document.createElement('label');label.textContent=field.label;label.style.cssText='display:grid;gap:7px';labels[key]=label;
        const input=document.createElement(field.options?'select':key==='acceptanceDays'?'input':'textarea');input.name=key;
        if(field.options) for(const [value,title] of Object.entries(field.options)) {const option=document.createElement('option');option.value=value;option.textContent=title;input.append(option);}
        else if(key==='acceptanceDays') {input.type='number';input.min='1';input.max='90';input.step='1';}
        else {input.rows=4;input.maxLength=20000;}
        input.value=data.values[key]||'';input.style.cssText='box-sizing:border-box;width:100%;padding:10px;background:#0d1220;color:#e1e7fa;border:1px solid #465474;border-radius:7px';controls[key]=input;label.append(input);form.append(label);
        const examples=data.suggestions[key]||{};
        if(Object.keys(examples).length) {
          const choices=document.createElement('select');choices.setAttribute('aria-label','Gotowe propozycje: '+field.label);const empty=document.createElement('option');empty.value='';empty.textContent='Gotowe propozycje — wybierz';choices.append(empty);
          for(const title of Object.keys(examples)) {const option=document.createElement('option');option.value=title;option.textContent=title;choices.append(option);}
          choices.onchange=()=>{const title=choices.value;if(title&&(!input.value.trim()||confirm('Zastąpić wpisaną treść propozycją?'))) input.value=examples[title];choices.value='';};label.append(choices);
        }
        if(key==='acceptanceDays') {const days=document.createElement('div');for(const n of [3,7,14,30]) {const button=document.createElement('button');button.type='button';button.className='button';button.textContent=n+' dni';button.onclick=()=>{input.value=String(n);};days.append(button);}label.append(days);}
      }
      const refresh=()=>{const destination=controls.publicationDestination.value;for(const [key,field] of Object.entries(data.fields)) {
        let visible=true;
        if(field.module==='hosting') visible=['agency','agency_purchase'].includes(destination);
        if(field.module==='domain_purchase') visible=destination==='agency_purchase';
        if(['productionDomain','domainRegistrar'].includes(key)) visible=['client_handoff','agency_purchase'].includes(destination);
        if(['domainOwnershipTerms','productionHosting','serverTarget','backupResponsibility','dnsTlsResponsibility'].includes(key)) visible=destination==='client_handoff';
        labels[key].hidden=!visible;
        labels[key].style.display=visible?'grid':'none';
      }};controls.publicationDestination.onchange=refresh;refresh();
      const status=document.createElement('p');status.setAttribute('role','status');status.textContent=data.missing.length?'Do uzgodnienia: '+data.missing.join(' · '):'Sprawdź ustalenia przed weryfikacją oferty.';form.append(status);
      const save=document.createElement('button');save.type='submit';save.className='button';save.textContent='Zapisz warunki i utwórz nową wersję oferty';form.append(save);
      form.onsubmit=async event=>{event.preventDefault();save.disabled=true;
        try {const agreement=Object.fromEntries(Object.entries(controls).map(([key,input])=>[key,input.value]));const result=await fetch('/api/offer.php?session='+encodeURIComponent(sessionId),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'updateAgreement',expectedVersion:offer.version,csrf:data.csrf,agreement})});const payload=await result.json();if(!result.ok) throw new Error(payload.message||'Nie udało się zapisać.');onSave(payload.offer);}
        catch(error) {status.textContent=error.message;save.disabled=false;}
      };
    }catch(error) {const message=document.createElement('p');message.textContent=error.message;box.append(message);}
  }
  window.offerAgreement={mount};
})();
