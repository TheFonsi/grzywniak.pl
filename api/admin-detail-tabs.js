(() => {
  const definitions = [['brief', 'Brief'], ['analysis', 'Analiza i ustalenia'], ['offer', 'Oferta'], ['contract', 'Umowa'], ['conversation', 'Rozmowa']];
  const anchors = { 'brief-panel': 'brief', 'analysis-panel': 'analysis', 'offer-panel': 'offer', 'contract-panel': 'contract', 'conversation-panel': 'conversation' };
  function init(root) {
    if (!root?.querySelector('.topline') || root.dataset.detailTabsReady) return;
    root.dataset.detailTabsReady = '1';
    const session = new URL(location.href).searchParams.get('session') || '';
    const key = `admin-detail-tab:${session}`;
    const nav = document.createElement('div'); nav.className = 'case-tabs'; nav.setAttribute('role', 'tablist'); nav.setAttribute('aria-label', 'Sekcje sprawy');
    const panels = new Map(); const buttons = new Map();
    for (const [name, label] of definitions) {
      const panel = document.createElement('div'); panel.id = `case-panel-${name}`; panel.dataset.caseTabPanel = name; panel.className = 'case-tab-panel'; panel.setAttribute('role', 'tabpanel'); panel.setAttribute('aria-labelledby', `case-tab-${name}`); panel.tabIndex = 0;
      const button = document.createElement('button'); button.id = `case-tab-${name}`; button.type = 'button'; button.setAttribute('role', 'tab'); button.setAttribute('aria-controls', panel.id); button.dataset.caseTab = name; button.textContent = label;
      nav.append(button); panels.set(name, panel); buttons.set(name, button);
    }
    // Move the existing DOM, retaining edits, focusable controls and handlers.
    for (const node of [...root.children]) {
      let target;
      if (node.matches('[data-visible-offer]')) target = 'offer';
      else if (node.matches('[data-contract-host],#contract-panel')) target = 'contract';
      else if (node.matches('section.analysis,[data-decision-ledger]')) target = 'analysis';
      else if (node.matches('.metrics') || (node.matches('section') && [...node.querySelectorAll('h3')].some(h => h.textContent.trim() === 'Rozmowa'))) target = 'conversation';
      else if (node.matches('.brief-grid,.tags') || (node.matches('h3') && ['Co ustalono', 'Flagi ryzyka', 'Obszary wymagające uwagi'].includes(node.textContent.trim()))) target = 'brief';
      else if (node.matches('p') && node.textContent.includes('Rozmowa → brief')) target = 'analysis';
      if (target) panels.get(target).append(node);
    }
    for (const [name, panel] of panels) {
      if (!panel.children.length) {
        const empty = document.createElement('p'); empty.className = 'muted';
        empty.textContent = ({brief: 'Brief pojawi się po zakończeniu zbierania potrzeb.', analysis: 'Analiza pojawi się po przekazaniu briefu.', offer: 'Oferta będzie dostępna po przygotowaniu analizy.', contract: 'Umowa będzie dostępna po zaakceptowaniu oferty.', conversation: 'Brak wiadomości w tej rozmowie.'})[name];
        panel.append(empty);
      }
    }
    root.append(nav, ...panels.values());
    function select(name, remember = false) {
      if (!panels.has(name)) return;
      for (const [id, panel] of panels) {
        panel.hidden = id !== name;
        buttons.get(id).setAttribute('aria-selected', String(id === name)); buttons.get(id).tabIndex = id === name ? 0 : -1;
      }
      try { sessionStorage.setItem(key, name); } catch {}
      if (remember) {
        const anchor = Object.keys(anchors).find(id => anchors[id] === name);
        history.replaceState(history.state, '', `${location.pathname}${location.search}#${anchor}`);
      }
    }
    function fromHash() {
      const hash = location.hash.slice(1);
      return anchors[hash] || document.getElementById(hash)?.closest('[data-case-tab-panel]')?.dataset.caseTabPanel;
    }
    let saved; try { saved = sessionStorage.getItem(key); } catch {}
    select(fromHash() || (panels.has(saved) ? saved : 'brief'));
    nav.addEventListener('click', event => { const button = event.target.closest('[data-case-tab]'); if (button) select(button.dataset.caseTab, true); });
    nav.addEventListener('keydown', event => {
      const button = event.target.closest('[data-case-tab]'); if (!button) return;
      const names = definitions.map(([name]) => name); let index = names.indexOf(button.dataset.caseTab);
      if (event.key === 'ArrowRight') index = (index + 1) % names.length;
      else if (event.key === 'ArrowLeft') index = (index + names.length - 1) % names.length;
      else if (event.key === 'Home') index = 0;
      else if (event.key === 'End') index = names.length - 1;
      else return;
      event.preventDefault(); select(names[index], true); buttons.get(names[index]).focus();
    });
    // A single listener follows replacement of the detail panel by AJAX.
    root._selectCaseHash = () => { const name = fromHash(); if (name) select(name); };
  }
  async function openContract(sessionId, beforeLoad) {
    const root=document.querySelector('[data-detail-tabs-ready]');
    const host=root?.querySelector('[data-contract-host]');
    if(!host) { location.hash='contract-panel'; return; }
    root.querySelector('[data-case-tab="contract"]').click();
    if(host.dataset.contractLoading==='1') return;
    if(!beforeLoad && host.querySelector('form.contract-form')) return;
    host._contractAbort?.abort();
    let prepared=false;
    const status=document.createElement('div');host.before(status);
    async function load() {
      host.dataset.contractLoading='1';host.hidden=true;host.setAttribute('aria-busy','true');
      status.className='detail-loader';status.setAttribute('role','status');status.textContent='Przygotowywanie umowy z zaakceptowanej oferty…';
      try {
        if(!prepared && beforeLoad) await beforeLoad();
        prepared=true;
        const base=location.pathname.startsWith('/api/')?'/api':'';
        const response=await fetch(`${base}/contract.php?session=${encodeURIComponent(sessionId)}&format=editor`,{cache:'no-store'});
        if(!response.ok) throw new Error('Nie udało się wczytać formularza umowy. Spróbuj ponownie.');
        const html=await response.text();
        if(!host.isConnected) return;
        host.innerHTML=html;window.contractReview?.init(host.querySelector('form.contract-form'));status.remove();
      } catch(error) {
        status.className='';status.setAttribute('role','alert');status.textContent=error.message || 'Nie udało się przygotować umowy.';
        const retry=document.createElement('button');retry.type='button';retry.className='button';retry.textContent='Spróbuj ponownie';retry.onclick=()=>{status.replaceChildren();void load();};status.append(document.createElement('br'),retry);
      } finally { host.hidden=false;host.removeAttribute('aria-busy');delete host.dataset.contractLoading; }
    }
    await load();
  }
  window.adminDetailTabs = { init, openContract };
  window.addEventListener('hashchange', () => document.querySelector('[data-detail-tabs-ready]')?._selectCaseHash());
  init(document.querySelectorAll('section.panel')[1]);
})();
