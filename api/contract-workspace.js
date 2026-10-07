(() => {
  const groups=[['summary','Podsumowanie'],['parties','Dane stron'],['scope','Zakres i odbiór'],['publication','Publikacja'],['rights','Prawa i dane']];
  const parties=new Set(['provider','party','paymentDetails','clientType','clientAddress','clientTaxId','clientRepresentative','contractDate','signing','legalStatusBasis']);
  const scope=new Set(['scope','price','deposit','deadline','acceptance','support','extras','acceptanceCriteria','acceptanceDays','cooperationTerms','obligationKind']);
  const publication=new Set(['deploymentTerms','publicationDestination','productionDomain','domainRegistrar','domainOwnershipTerms','productionHosting','serverTarget','backupResponsibility','dnsTlsResponsibility']);
  function init(form) {
    if(!form || form.matches('.offer-agreement-form') || form.dataset.workspaceReady || !form.querySelector('[data-review-field]'))return;
    form.dataset.workspaceReady='1';
    const nav=document.createElement('div');nav.className='case-tabs contract-workspace-tabs';nav.setAttribute('role','tablist');nav.setAttribute('aria-label','Przegląd projektu umowy');
    const panes=new Map();const buttons=new Map();
    for(const [name,title] of groups){const pane=document.createElement('section');pane.dataset.contractPane=name;pane.id=`contract-pane-${name}`;pane.setAttribute('role','tabpanel');pane.setAttribute('aria-labelledby',`contract-workspace-${name}`);const button=document.createElement('button');button.type='button';button.id=`contract-workspace-${name}`;button.dataset.contractPaneButton=name;button.setAttribute('role','tab');button.setAttribute('aria-controls',pane.id);button.textContent=title;nav.append(button);buttons.set(name,button);panes.set(name,pane);}
    const summary=panes.get('summary');const intro=document.createElement('p');intro.textContent='Przejrzyj i zatwierdź każdą zakładkę osobno. Następnie kliknij „Przygotuj PDF”. W razie braków pokażemy, co trzeba uzupełnić lub zatwierdzić. Wysłanie i podpisanie są osobnymi krokami.';summary.append(intro);
    const rightsNote=document.createElement('p');rightsNote.textContent='Pola opcjonalne możesz oznaczyć „Nie dotyczy”. Powierzenie i ochrona konsumencka pojawiają się tylko przy odpowiednim dostępie do danych i statusie klienta. Prawa do projektu oraz informacje o danych kontaktowych stron wymagają przeglądu.';panes.get('rights').append(rightsNote);
    const state=document.createElement('p');state.dataset.contractWorkspaceStatus='';state.setAttribute('role','status');summary.append(state);
    const advanced=document.createElement('details');const advancedTitle=document.createElement('summary');advancedTitle.textContent='Zaawansowane: zmiana wzoru i dodatkowe uzupełnianie';advanced.append(advancedTitle);
    const actions=document.createElement('div');actions.className='contract-workspace-actions';
    const approve=document.createElement('button');approve.type='button';approve.className='button';approve.dataset.contractApprovePane='';approve.textContent='Przejrzałem — zatwierdź';actions.append(approve);
    const errors=document.createElement('div');errors.dataset.contractWorkspaceErrors='';errors.setAttribute('role','alert');errors.style.cssText='color:#ffb4b4;background:#341b24;border:1px solid #df616b;padding:12px;border-radius:8px';errors.hidden=true;
    const used=new Set();
    const panel=form.closest('#contract-panel');
    let refreshBox=panel?.querySelector('[data-contract-outdated]');
    if(panel&&!refreshBox){refreshBox=document.createElement('div');refreshBox.innerHTML='<strong>Projekt umowy z aktualnej oferty</strong><p data-contract-reprepare-status role="status"></p>';panel.insertBefore(refreshBox,panel.querySelector('details'));}
    if(refreshBox){
      refreshBox.dataset.contractRefresh='';
      let refreshButton=refreshBox.querySelector('[data-contract-reprepare]');
      if(!refreshButton){refreshButton=document.createElement('button');refreshButton.type='button';refreshButton.className='button';refreshButton.dataset.contractReprepare='';refreshBox.append(refreshButton);}
      refreshButton.textContent='Odśwież na podstawie aktualnej oferty';
      const explanation=document.createElement('p');explanation.textContent='Ponownie dobiera wzór i wczytuje ustalenia oferty. Zastępuje robocze zapisy zakresu i klauzul oraz cofa ich zatwierdzenia. Zapisany PDF pozostaje bez zmian.';refreshBox.append(explanation);
    }
    for(const button of [...form.querySelectorAll('button[name="contract_action"]')]){
      const action=button.value;
      if(action==='approve-generate'){button.remove();continue;}
      if(action==='sync-offer'){button.remove();continue;}
      if(used.has(action)){button.remove();continue;}used.add(action);
      if(['auto-prepare','generate','save'].includes(action)){actions.append(button);if(action==='auto-prepare')button.hidden=true;if(action==='generate'){button.textContent='Przygotuj PDF';button.formNoValidate=true;}}
      else advanced.append(button);
    }
    summary.append(advanced);
    // Existing field cards retain their controls, review state and listeners.
    for(const card of [...form.querySelectorAll('[data-review-field]')]){
      const key=card.dataset.reviewField;const module=card.querySelector('[data-contract-module]')?.dataset.contractModule;
      const target=parties.has(key)?'parties':scope.has(key)?'scope':publication.has(key)||['hosting','domain_purchase'].includes(module)?'publication':'rights';
      panes.get(target).append(card);
    }
    for(const node of [...form.children]){
      if(node.matches('input[type="hidden"]'))continue;
      if(node.matches('[data-commercial-panel]'))summary.append(node);
      else if(node.querySelector('[data-template-current]'))advanced.append(node);
      else if(node.matches('[data-ai-feedback],[role="alert"],[data-review-summary]'))summary.append(node);
      else node.hidden=true;
    }
    const pdf=actions.querySelector('[value="generate"]');if(pdf)approve.after(pdf);
    form.append(nav,...panes.values(),errors,actions);
    function select(name){form.dataset.activeContractPane=name;for(const [id,pane]of panes){pane.hidden=id!==name;buttons.get(id).setAttribute('aria-selected',String(id===name));buttons.get(id).tabIndex=id===name?0:-1;}approve.hidden=name==='summary';}
    form._selectContractPane=select;
    approve.addEventListener('click',()=>{
      if(form.dataset.saving)return;
      const pane=panes.get(form.dataset.activeContractPane);
      for(const card of pane.querySelectorAll('[data-review-field]'))if(!card.hidden && card.dataset.missing!=='1')card.querySelector('[data-review-accept]:not(:disabled)')?.click();
      validate(form,form.dataset.activeContractPane,false);
    });
    nav.addEventListener('click',event=>{const b=event.target.closest('[data-contract-pane-button]');if(b)select(b.dataset.contractPaneButton);});
    nav.addEventListener('keydown',event=>{const b=event.target.closest('[data-contract-pane-button]');if(!b)return;const names=groups.map(([id])=>id);let i=names.indexOf(b.dataset.contractPaneButton);if(event.key==='ArrowRight')i=(i+1)%names.length;else if(event.key==='ArrowLeft')i=(i+names.length-1)%names.length;else if(event.key==='Home')i=0;else if(event.key==='End')i=names.length-1;else return;event.preventDefault();select(names[i]);buttons.get(names[i]).focus();});
    form.addEventListener('invalid',event=>{const pane=event.target.closest('[data-contract-pane]');if(pane)select(pane.dataset.contractPane);},true);
    form.addEventListener('input',()=>refresh(form));form.addEventListener('change',()=>refresh(form));
    select('summary');refresh(form);
  }
  function refresh(form){
    const status=form?.querySelector('[data-contract-workspace-status]');if(!status)return;
    const cards=[...form.querySelectorAll('[data-review-field]')].filter(card=>!card.hidden);
    const missing=cards.filter(card=>card.querySelector('[data-review-accept]')?.disabled && !card.dataset.accepted?.includes('1'));
    status.textContent=missing.length?`Wymaga uzupełnienia: ${missing.length} pól. Otwórz odpowiednią zakładkę i zatwierdź jej treść.`:'Przejrzyj i zatwierdź każdą zakładkę, następnie przygotuj PDF.';
    for(const button of form.querySelectorAll('[data-contract-pane-button]')){
      const count=missing.filter(card=>card.closest('[data-contract-pane]')?.dataset.contractPane===button.dataset.contractPaneButton).length;
      button.textContent=groups.find(([id])=>id===button.dataset.contractPaneButton)[1]+(count?` (${count} braków)`:'');
    }
  }
  function validate(form,paneName=null,requireAccepted=true){
    const box=form.querySelector('[data-contract-workspace-errors]');if(!box)return true;
    const cards=[...form.querySelectorAll('[data-review-field]')].filter(card=>!card.hidden && (!paneName||card.closest('[data-contract-pane]')?.dataset.contractPane===paneName));
    const invalid=cards.filter(card=>card.dataset.missing==='1'||(requireAccepted&&card.dataset.accepted!=='1'));
    box.replaceChildren();box.hidden=!invalid.length;
    for(const card of form.querySelectorAll('[data-review-field]'))card.style.outline=invalid.includes(card)?'2px solid #df616b':'';
    if(!invalid.length)return true;
    const title=document.createElement('strong');title.textContent='Przed przygotowaniem PDF popraw wskazane pola:';box.append(title);
    const list=document.createElement('ul');box.append(list);
    for(const card of invalid){const item=document.createElement('li');const link=document.createElement('button');link.type='button';link.className='button';link.style.color='#ffb4b4';const label=card.querySelector('label');link.textContent=(card.dataset.missing==='1'?'Uzupełnij: ':'Zatwierdź w zakładce: ')+(label?.firstChild?.textContent||card.dataset.reviewField);link.onclick=()=>{form._selectContractPane(card.closest('[data-contract-pane]').dataset.contractPane);card.scrollIntoView?.({block:'center',behavior:'smooth'});};item.append(link);list.append(item);}
    box.scrollIntoView?.({block:'nearest',behavior:'smooth'});return false;
  }
  window.contractWorkspace={init,refresh,validate};
})();
