(() => {
  // On MyDevil the API files are served at /, locally they live under /api/.
  // External scripts are not transformed by PHP's apiRewritePaths().
  const script=document.currentScript;
  const endpoint=script?.src ? new URL('offer.php',script.src).pathname : location.pathname.startsWith('/api/') ? '/api/offer.php' : '/offer.php';
  async function readResponse(response) {
    try {return await response.json();}
    catch {throw new Error(`Serwer nie zwrócił danych formularza (HTTP ${response.status}). Odśwież panel; jeśli błąd pozostaje, sprawdź wdrożenie API.`);}
  }
  async function mount(container,offer,sessionId,onSave) {
    const box=document.createElement('details');box.style.cssText='margin:18px 0;padding:16px;border:1px solid #465474;border-radius:12px';
    box.dataset.offerAgreement='1';
    const heading=document.createElement('summary');heading.textContent='Warunki realizacji i dane do umowy';heading.style.cursor='pointer';box.append(heading);container.append(box);
    try {
      const response=await fetch(endpoint+'?session='+encodeURIComponent(sessionId)+'&format=agreement');const data=await readResponse(response);if(!response.ok) throw new Error(data.message||'Nie udało się pobrać ustaleń.');
      if(!box.isConnected) return;
      const form=document.createElement('form');form.className='contract-form offer-agreement-form';form.style.cssText='display:grid;gap:14px;margin-top:16px';box.append(form);
      const note=document.createElement('p');note.textContent='Gotowy pakiet propozycji: zachowujemy zapisane ustalenia, a brakujące warunki uzupełniamy standardem o niskim koszcie realizacji. Możesz go zmienić po rozmowie z klientem. Po zatwierdzeniu warunki trafią do PDF oferty, a po jej akceptacji do umowy.';form.append(note);
      const fastNote=document.createElement('p');fastNote.textContent='Przypisania ustaleń są proponowane automatycznie. Przejrzyj treść i wycenę; możesz zmienić każde pole. Przycisk poniżej zapisze warunki, potwierdzi ich sprawdzenie i oznaczy całą ofertę jako zweryfikowaną. Nie wysyła jej klientowi.';form.append(fastNote);
      let approveRequested=false;
      const approve=document.createElement('button');approve.type='button';approve.className='button';approve.dataset.approveAgreement='1';approve.textContent='Przejrzałem — zatwierdź całą ofertę';approve.onclick=()=>{approveRequested=true;try{form.requestSubmit();}finally{approveRequested=false;}};form.append(approve);
      const pendingReviews=Object.values(data.dependencyReview||{}).filter(value=>value!==true).length;
      const pendingDecisions=Object.values(data.decisions||{}).filter(decision=>!decision.target).length;
      if(pendingReviews || pendingDecisions) {
        box.open=true;
        heading.textContent=`Warunki realizacji i dane do umowy — do sprawdzenia: ${Math.max(data.missing.length,pendingReviews+pendingDecisions)}`;
        const guide=document.createElement('p');guide.setAttribute('role','status');guide.style.cssText='padding:12px;border:1px solid #d7a83e;border-radius:8px';
        guide.textContent=`Uzupełniony tekst nie zastępuje potwierdzenia. Do przejrzenia: ${pendingReviews} warunków po zmianie zakresu lub ceny i ${pendingDecisions} automatycznych propozycji przypisania. Po sprawdzeniu możesz zatwierdzić całość jednym przyciskiem, bez zaznaczania każdego pola osobno.`;form.append(guide);
      }
      const controls={};const labels={};const dependencies={};const decisionTargets={};
      for(const [key,field] of Object.entries(data.fields)) {
        const label=document.createElement('label');label.textContent=field.label;label.style.cssText='display:grid;gap:7px';labels[key]=label;
        const input=document.createElement(field.options?'select':key==='acceptanceDays'?'input':'textarea');input.name=key;
        if(field.options) for(const [value,title] of Object.entries(field.options)) {const option=document.createElement('option');option.value=value;option.textContent=title;input.append(option);}
        else if(key==='acceptanceDays') {input.type='number';input.min='1';input.max='90';input.step='1';}
        else {input.rows=4;input.maxLength=20000;}
        input.value=data.values[key]||'';input.style.cssText='box-sizing:border-box;width:100%;padding:10px;background:#0d1220;color:#e1e7fa;border:1px solid #465474;border-radius:7px';controls[key]=input;label.append(input);form.append(label);
        if(data.proposalFields?.includes(key)) {const origin=document.createElement('small');origin.textContent='Propozycja standardowa — do zatwierdzenia z całą ofertą.';label.append(origin);}
        const examples=data.suggestions[key]||{};
        if(Object.keys(examples).length) {
          const choices=document.createElement('select');choices.setAttribute('aria-label','Gotowe propozycje: '+field.label);const empty=document.createElement('option');empty.value='';empty.textContent='Zmień na inny wariant (opcjonalnie)';choices.append(empty);
          for(const title of Object.keys(examples)) {const option=document.createElement('option');option.value=title;option.textContent=title;choices.append(option);}
          choices.onchange=()=>{const title=choices.value;if(title&&(!input.value.trim()||confirm('Zastąpić wpisaną treść propozycją?'))) {input.value=examples[title];input.dispatchEvent(new Event('input',{bubbles:true}));}choices.value='';};label.append(choices);
        }
        if(key==='acceptanceDays') {const days=document.createElement('div');for(const n of [3,7,14,30]) {const button=document.createElement('button');button.type='button';button.className='button';button.textContent=n+' dni';button.onclick=()=>{input.value=String(n);input.dispatchEvent(new Event('input',{bubbles:true}));};days.append(button);}label.append(days);}
        if(Object.hasOwn(data.dependencyReview||{},key)) {
          const check=document.createElement('input');check.type='checkbox';check.checked=data.dependencyReview[key]===true;check.dataset.dependency=key;dependencies[key]=check;
          const review=document.createElement('label');review.style.display='block';review.append(check,' Sprawdziłem te warunki po zmianie zakresu / ceny.');label.append(review);
          if(!check.checked) {check.dataset.pendingReview='1';review.style.color='#f1cc77';}
          input.addEventListener('input',()=>{check.checked=false;});input.addEventListener('change',()=>{check.checked=false;});
        }
      }
      if(Object.hasOwn(data.dependencyReview||{},'pricing')) {
        const label=document.createElement('label');const check=document.createElement('input');check.type='checkbox';check.checked=data.dependencyReview.pricing===true;check.dataset.dependency='pricing';dependencies.pricing=check;
        label.append(check,` Sprawdziłem wpływ zakresu na wycenę (${Number(data.pricing?.gross||0).toLocaleString('pl-PL')} zł brutto) i zaliczkę.`);form.append(label);
        if(!check.checked) {check.dataset.pendingReview='1';label.style.color='#f1cc77';}
      }
      for(const [id,decision] of Object.entries(data.decisions||{})) {
        const card=document.createElement('section');card.style.cssText='padding:12px;border:1px solid #465474;border-radius:8px';
        const title=document.createElement('strong');title.textContent='Ustalenie: '+decision.question;
        const answer=document.createElement('p');answer.textContent=decision.answer;
        const select=document.createElement('select');select.dataset.decisionTarget=id;select.setAttribute('aria-label','Przypisanie ustalenia: '+decision.question);
        const empty=document.createElement('option');empty.value='';empty.textContent='Wybierz miejsce w ofercie i umowie';select.append(empty);
        for(const [value,label] of Object.entries(data.decisionTargets||{})) {const option=document.createElement('option');option.value=value;option.textContent=label;select.append(option);}
        const provenance=document.createElement('small');provenance.textContent=`Ustalenie ${id} · wersja ${decision.version} · autor: ${decision.author}`;
        select.value=decision.target||decision.suggestedTarget||'';decisionTargets[id]=select;card.append(title,provenance,answer,select);form.append(card);
        if(!decision.target) {select.dataset.pendingReview='1';card.style.borderColor='#d7a83e';const suggestion=document.createElement('small');suggestion.textContent=select.value?'Propozycja do zatwierdzenia: '+(decision.suggestionReason||'Automatycznie dopasowana sekcja. Możesz ją zmienić.'):'Wybierz miejsce dla tego ustalenia.';card.append(suggestion);}
      }
      const refresh=()=>{const destination=controls.publicationDestination.value;for(const [key,field] of Object.entries(data.fields)) {
        let visible=true;
        if(field.module==='hosting') visible=['agency','agency_purchase'].includes(destination);
        if(field.module==='domain_purchase') visible=destination==='agency_purchase';
        if(['productionDomain','domainRegistrar'].includes(key)) visible=['client_handoff','agency_purchase'].includes(destination);
        if(['domainOwnershipTerms','productionHosting','serverTarget','backupResponsibility','dnsTlsResponsibility'].includes(key)) visible=destination==='client_handoff';
        if(key==='rightsTerms') visible=['exclusive','nonexclusive'].includes(controls.ipMode.value);
        labels[key].hidden=!visible;
        labels[key].style.display=visible?'grid':'none';
      }};controls.publicationDestination.addEventListener('change',refresh);controls.ipMode.addEventListener('change',refresh);refresh();
      const status=document.createElement('p');status.setAttribute('role','status');status.textContent=data.missing.length?'Do uzgodnienia: '+data.missing.join(' · '):'Sprawdź ustalenia przed weryfikacją oferty.';form.append(status);
      const save=document.createElement('button');save.type='submit';save.className='button';save.textContent='Zapisz warunki i utwórz nową wersję oferty';form.append(save);
      form.onsubmit=async event=>{event.preventDefault();if(save.disabled) return;save.disabled=true;approve.disabled=true;const approveAll=approveRequested;
        try {const agreement=Object.fromEntries(Object.entries(controls).map(([key,input])=>[key,input.value]));const reviewedDependencies=Object.fromEntries(Object.entries(dependencies).map(([key,input])=>[key,approveAll||input.checked]));const targets=Object.fromEntries(Object.entries(decisionTargets).map(([key,input])=>[key,input.value]));const result=await fetch(endpoint+'?session='+encodeURIComponent(sessionId),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:approveAll?'approveAgreement':'updateAgreement',expectedVersion:offer.version,csrf:data.csrf,agreement,reviewedDependencies,decisionTargets:targets})});const payload=await readResponse(result);if(!result.ok) throw new Error(payload.message||'Nie udało się zapisać.');onSave(payload.offer);}
        catch(error) {status.textContent=error.message;save.disabled=false;approve.disabled=false;}
      };
    }catch(error) {const message=document.createElement('p');message.textContent=error.message;box.append(message);}
  }
  window.offerAgreement={mount};
})();
