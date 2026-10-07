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
    const summary=panes.get('summary');const intro=document.createElement('p');intro.textContent='1. Wczytaj aktualną ofertę, jeśli formularz jest nieaktualny. 2. Przejrzyj sekcje i uzupełnij wskazane dane. 3. Zatwierdź całość i przygotuj PDF. Wysłanie i podpisanie są osobnymi krokami.';summary.append(intro);
    const state=document.createElement('p');state.dataset.contractWorkspaceStatus='';state.setAttribute('role','status');summary.append(state);
    const advanced=document.createElement('details');const advancedTitle=document.createElement('summary');advancedTitle.textContent='Zaawansowane: zmiana wzoru i dodatkowe uzupełnianie';advanced.append(advancedTitle);
    const actions=document.createElement('div');actions.className='contract-workspace-actions';
    const used=new Set();
    for(const button of [...form.querySelectorAll('button[name="contract_action"]')]){
      const action=button.value;
      if(used.has(action)){button.remove();continue;}used.add(action);
      if(['auto-prepare','approve-generate','save'].includes(action)){actions.append(button);if(action==='auto-prepare')button.textContent='Przygotuj automatycznie z aktualnej oferty';}
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
    form.append(nav,...panes.values(),actions);
    function select(name){for(const [id,pane]of panes){pane.hidden=id!==name;buttons.get(id).setAttribute('aria-selected',String(id===name));buttons.get(id).tabIndex=id===name?0:-1;}}
    form._selectContractPane=select;
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
    status.textContent=missing.length?`Wymaga uzupełnienia: ${missing.length} pól. Otwórz odpowiednią sekcję. Gotowe zapisy zatwierdzisz jednym przyciskiem poniżej.`:'Przejrzyj sekcje. Gotowe zapisy zatwierdzisz jednym przyciskiem poniżej.';
    for(const button of form.querySelectorAll('[data-contract-pane-button]')){
      const count=missing.filter(card=>card.closest('[data-contract-pane]')?.dataset.contractPane===button.dataset.contractPaneButton).length;
      button.textContent=groups.find(([id])=>id===button.dataset.contractPaneButton)[1]+(count?` (${count} braków)`:'');
    }
  }
  window.contractWorkspace={init,refresh};
})();
