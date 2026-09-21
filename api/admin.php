<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/contract-model.php';
require_once __DIR__ . '/admin-shell.php';
require_once __DIR__ . '/project-delete.php';

$adminUser=getenv('ADMIN_USERNAME')?:''; $adminPassword=getenv('ADMIN_PASSWORD')?:'';
if($adminUser===''||$adminPassword===''||!isset($_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW'])||!hash_equals($adminUser,$_SERVER['PHP_AUTH_USER'])||!hash_equals($adminPassword,$_SERVER['PHP_AUTH_PW'])){header('WWW-Authenticate: Basic realm="Grzywniak Discovery"');http_response_code(401);exit('Authentication required.');}
header('Content-Type: text/html; charset=utf-8');
contractToken();
$requestedView = (string) ($_GET['view'] ?? 'active');
ob_start();
function esc(mixed $value):string{$text=(string)$value;$text=match($text){'PERSONAL_DATA'=>'Dane osobowe','SENSITIVE_DATA'=>'Dane wrażliwe','PAYMENTS'=>'Płatności online','MEDICAL'=>'Dane medyczne','FINANCIAL'=>'Dane finansowe','LEGAL'=>'Wymogi prawne','HIGH_SECURITY'=>'Podwyższone wymagania bezpieczeństwa','EXTERNAL_INTEGRATION'=>'Połączenie z zewnętrznymi usługami','DATA_MIGRATION'=>'Przeniesienie danych','LARGE_SCALE'=>'Duża skala rozwiązania','UNCLEAR_SCOPE'=>'Zakres wymaga doprecyzowania','UNREALISTIC_BUDGET'=>'Budżet może wymagać weryfikacji','UNREALISTIC_DEADLINE'=>'Termin może wymagać weryfikacji','READY'=>'Gotowy do przygotowania oferty','NEEDS_CLARIFICATION'=>'Wymaga doprecyzowania','NOT_A_FIT'=>'Poza zakresem usług','COMPLETED'=>'Analiza gotowa','WAITING'=>'Oczekuje na analizę','NOT_RUN'=>'Analiza niedostępna','FAILED'=>'Analiza wymaga ponowienia','Pytania do klienta'=>'Pytania klienta do zespołu',default=>$text};return htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function sessionFile(string $id):string{return __DIR__.'/storage/'.preg_replace('/[^a-f0-9]/','',$id).'.json';}
function sessions():array{$items=allSessions();foreach($items as &$data)if(normalizeSessionData($data))writeSession($data);unset($data);usort($items,static fn($a,$b)=>($b['updatedAt']??0)<=>($a['updatedAt']??0));return $items;}
function save(array $session):void{writeSession($session);}
function hasClientMessage(array $session):bool{foreach(($session['messages']??[])as $message)if(($message['role']??'')==='user')return true;return false;}
function analyticsMetrics(array $all): array {
    $sessions = array_values(array_filter($all, 'hasClientMessage'));
    $count = count($sessions); $active = $completed = $ready = $withOffer = $reviewed = $sent = 0;
    $totalInput = $totalOutput = $totalCached = $totalTurns = $totalUserMessages = 0;
    foreach ($sessions as $session) {
        $status = (string) ($session['status'] ?? '');
        if (in_array($status, ['STARTED','DISCOVERY','NEEDS_INFORMATION','READY_FOR_SUMMARY'], true)) $active++;
        if ($status === 'COMPLETED') $completed++;
        if (($session['readyForSummary'] ?? false) === true) $ready++;
        $metrics = is_array($session['metrics'] ?? null) ? $session['metrics'] : [];
        $totalInput += (int) ($metrics['inputTokens'] ?? 0); $totalOutput += (int) ($metrics['outputTokens'] ?? 0); $totalCached += (int) ($metrics['cachedTokens'] ?? 0); $totalTurns += (int) ($metrics['turns'] ?? 0);
        $totalUserMessages += count(array_filter($session['messages'] ?? [], static fn($message): bool => is_array($message) && ($message['role'] ?? '') === 'user'));
        $offer = is_array($session['offer'] ?? null) ? $session['offer'] : null;
        if ($offer) { $withOffer++; $offerStatus = (string) ($offer['status'] ?? 'DRAFT'); if ($offerStatus === 'REVIEWED') $reviewed++; if ($offerStatus === 'SENT') { $reviewed++; $sent++; } }
    }
    $totalTokens = $totalInput + $totalOutput;
    return ['sessions'=>$count,'active'=>$active,'completed'=>$completed,'ready'=>$ready,'offers'=>$withOffer,'reviewedOffers'=>$reviewed,'sentOffers'=>$sent,'totalInputTokens'=>$totalInput,'totalOutputTokens'=>$totalOutput,'totalCachedTokens'=>$totalCached,'totalTokens'=>$totalTokens,'totalTurns'=>$totalTurns,'userMessages'=>$totalUserMessages,'avgTokens'=>$count ? (int) round($totalTokens/$count) : 0,'avgTokensPerTurn'=>$totalTurns ? (int) round($totalTokens/$totalTurns) : 0,'avgMessages'=>$count ? round($totalUserMessages/$count,1) : 0,'briefConversion'=>$count ? round($completed/$count*100,1) : 0,'offerConversion'=>$count ? round($withOffer/$count*100,1) : 0,'sentConversion'=>$count ? round($sent/$count*100,1) : 0];
}
register_shutdown_function(static function(): void {
    $script = <<<'HTML'
<script>
(() => {
  document.querySelectorAll('aside .row').forEach(row => {
    if (!row.querySelector('.meta')?.textContent.includes('Brief przekazany')) return;
    const sessionId = new URL(row.href, location.href).searchParams.get('session');
    if (sessionId) row.href = '/api/project-case.php?session=' + encodeURIComponent(sessionId);
  });
  document.addEventListener('change', event => {
    if (event.target.name !== 'ipMode') return;
    const field = event.target.form?.querySelector('[data-license-terms]');
    if (field) field.hidden = !['exclusive','nonexclusive'].includes(event.target.value);
  });
  document.addEventListener('submit', async event => {
    const form = event.target;
    if (!form.matches('.contract-form,.contract-send')) return;
    event.preventDefault();
    if (form.dataset.saving) return;
    form.dataset.saving = '1';
    const data = Object.fromEntries(new FormData(form));
    if (event.submitter?.name) data[event.submitter.name] = event.submitter.value;
    const buttons = [...form.querySelectorAll('button')]; buttons.forEach(button => button.disabled = true);
    let feedback = form.querySelector('[role="alert"]');
    if (!feedback) { feedback = document.createElement('p'); feedback.setAttribute('role','alert'); form.append(feedback); }
    const aiFill = data.contract_action === 'ai-fill';
    const previous = aiFill ? Object.fromEntries([...form.querySelectorAll('textarea,input,select')].map(input => [input.name,input.value])) : null;
    feedback.textContent = aiFill ? 'AI przygotowuje projekt umowy. Może to potrwać około minuty…' : 'Zapisywanie…';
    try {
      const response = await fetch('/api/contract.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || 'Nie udało się zapisać umowy.');
      if (aiFill) {
        const changed = [...form.querySelectorAll('textarea,input,select')].some(input => input.value !== previous[input.name]);
        if (changed) throw new Error('Formularz zmienił się podczas pracy AI. Zachowano Twoje zmiany. Uruchom AI ponownie, aby je uwzględnić.');
        window.contractReview.apply(form, result);
        const notes = form.querySelector('[data-ai-feedback]'); notes.replaceChildren();
        const title = document.createElement('p'); title.textContent = 'AI przygotowało propozycje. Przy każdym polu wybierz Akceptuj lub Zmień. Brakujące dane faktyczne uzupełnij ręcznie.'; notes.append(title);
        if (result.missing?.length) { const list = document.createElement('ul'); for (const text of result.missing) { const item=document.createElement('li'); item.textContent=text; list.append(item); } notes.append(list); }
        feedback.textContent = 'Formularz uzupełniony. Dane nie zostały jeszcze zapisane.';
        buttons.forEach(button => button.disabled=false); delete form.dataset.saving; window.contractReview.refresh(form); return;
      }
      if (data.action === 'save-profile') location.href = '?view=contract-settings&profileSaved=1';
      else if (data.action === 'save-template') location.href = '?view=contract-settings&saved=1';
      else location.href = '?view=all&session=' + encodeURIComponent(data.contract_session) + '&saved=1#contract-panel';
    } catch(error) { feedback.textContent = error.message || 'Nie udało się połączyć z serwerem.'; buttons.forEach(button => button.disabled = false); delete form.dataset.saving; if(form.elements.reviewState) window.contractReview.refresh(form); }
  });
  const enhanceUi = () => { const style = document.createElement('style'); style.textContent = '.nav a:focus-visible,.button:focus-visible,.row:focus-visible,.analytics-filter:focus-visible{outline:2px solid #9daaff;outline-offset:2px}.nav a:hover,.button:hover:not(:disabled),.analytics-filter:hover{border-color:#7181f1;background:#1a2340;color:#fff}.button:disabled{cursor:not-allowed;opacity:.55}.status{display:inline-flex;align-items:center;white-space:nowrap;max-width:100%}.layout{align-items:start}.layout>.panel:first-child{position:sticky;top:18px;max-height:calc(100vh - 36px);overflow:auto}.actions form{margin:0}.brief-section p,.message{overflow-wrap:anywhere}.analytics-panel{margin-bottom:20px;background:linear-gradient(135deg,#11172a,#0f1218)}.analytics-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:12px}.analytics-heading h2{margin:0 0 3px;font-size:20px}.analytics-filters{display:flex;gap:7px;flex-wrap:wrap;margin:0 0 16px}.analytics-filter{border:1px solid #354052;border-radius:999px;padding:6px 11px;color:#adb8d2;background:#111622;text-decoration:none;font-size:12px}.analytics-filter.active{border-color:#7181f1;background:#263271;color:#fff}.analytics-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}.analytics-card{min-width:0;border:1px solid #2d385b;border-radius:12px;background:#121827;padding:13px}.analytics-card b{display:block;font-size:23px;letter-spacing:-.03em;color:#eef1ff}.analytics-card span{display:block;color:#c9d1e8;font-size:12px;line-height:1.35}.analytics-card small{display:block;color:#7f8ca8;font-size:11px;margin-top:5px}.analytics-charts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:12px}.chart-card{border:1px solid #2d385b;border-radius:12px;background:#101522;padding:14px}.chart-card h3{margin:0 0 12px;font-size:13px;color:#cbd4ef}.chart-row{display:grid;grid-template-columns:130px minmax(60px,1fr) auto;align-items:center;gap:9px;margin:9px 0;color:#aeb9d2;font-size:12px}.chart-row b{font-size:12px;color:#edf0ff;min-width:48px;text-align:right}.chart-track{height:8px;background:#252d43;border-radius:999px;overflow:hidden}.chart-track i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#6475ed,#9ca8ff)}.analytics-foot{display:flex;gap:18px;flex-wrap:wrap;margin-top:14px;padding-top:12px;border-top:1px solid #2d385b;color:#9da8bf;font-size:12px}.analytics-foot strong{color:#e5e9ff}@media(max-width:1100px){.analytics-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:900px){.layout>.panel:first-child{position:static;max-height:280px}.analysis-head{align-items:flex-start;flex-direction:column}.analytics-charts{grid-template-columns:1fr}}@media(max-width:560px){.analytics-heading{display:block}.analytics-heading time{display:block;margin-top:5px}.analytics-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.chart-row{grid-template-columns:100px minmax(45px,1fr) auto}}'; document.head.append(style); };
  const addBulkActions = () => {
    const aside = document.querySelector('aside.panel');
    if (!aside || aside.querySelector('[data-bulk-actions]')) return;
    const bulkStyle = document.createElement('style'); bulkStyle.textContent = '.bulk-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:14px;padding-top:13px;border-top:1px solid #29313c;color:#9da8bf;font-size:12px}.bulk-actions label{display:inline-flex;align-items:center;gap:7px;margin-right:auto;cursor:pointer}.bulk-actions input,.conversation-select{appearance:none;width:17px;height:17px;margin:0;border:1px solid #53638f;border-radius:5px;background:#0d1220;vertical-align:middle;cursor:pointer;transition:background .15s,border-color .15s,box-shadow .15s}.bulk-actions input:checked,.conversation-select:checked{background:#6678ef;border-color:#93a0ff;box-shadow:inset 0 0 0 3px #141a32}.bulk-actions input:focus-visible,.conversation-select:focus-visible{outline:2px solid #9daaff;outline-offset:2px}.bulk-actions .button{padding:6px 9px;font-size:12px}.selectable-row{position:relative}.conversation-select{position:absolute!important;left:5px;top:17px}'; document.head.append(bulkStyle);
    const rows = [...aside.querySelectorAll('.row')];
    if (!rows.length) return;
    rows.forEach((row) => {
      const wrapper = document.createElement('div'); wrapper.className = 'selectable-row'; row.parentNode.insertBefore(wrapper, row); wrapper.append(row);
      row.style.paddingLeft = '31px';
      const metaText = row.querySelector('.meta')?.textContent || '';
      if (metaText.includes('Brief przekazany')) row.classList.add('status-completed');
      else if (metaText.includes('Gotowe do przekazania')) row.classList.add('status-ready');
      else if (metaText.includes('Rozmowa przerwana')) row.classList.add('status-interrupted');
      else row.classList.add('status-active');
      const checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.className = 'conversation-select'; checkbox.setAttribute('aria-label', 'Zaznacz rozmowę');
      checkbox.dataset.session = new URL(row.href, location.href).searchParams.get('session') || '';
      checkbox.style.cssText = 'position:absolute;left:5px;top:18px;width:16px;height:16px;accent-color:#7181f1;cursor:pointer';
      checkbox.addEventListener('click', (event) => { event.stopPropagation(); });
      wrapper.prepend(checkbox);
    });
    const toolbar = document.createElement('div'); toolbar.dataset.bulkActions = '1'; toolbar.className = 'bulk-actions'; toolbar.innerHTML = '<label><input type="checkbox" data-select-all> Zaznacz wszystkie</label><span data-selected-count>0 zaznaczonych</span><button type="button" class="button" data-bulk-action="archive">Archiwizuj</button><button type="button" class="button" data-bulk-action="restore">Przywróć</button><button type="button" class="button danger" data-bulk-action="delete">Usuń</button>';
    aside.append(toolbar);
    const selected = () => [...aside.querySelectorAll('.conversation-select:checked')];
    const update = () => { const count = selected().length; toolbar.querySelector('[data-selected-count]').textContent = count + ' zaznaczonych'; toolbar.querySelectorAll('[data-bulk-action]').forEach((button) => { button.disabled = count === 0; }); };
    toolbar.querySelector('[data-select-all]').addEventListener('change', (event) => { aside.querySelectorAll('.conversation-select').forEach((checkbox) => { checkbox.checked = event.target.checked; }); update(); });
    aside.addEventListener('change', (event) => { if (event.target.matches('.conversation-select')) update(); });
    toolbar.querySelectorAll('[data-bulk-action]').forEach((button) => button.addEventListener('click', async () => {
      const checked = selected(); if (!checked.length) return;
      const action = button.dataset.bulkAction; if (action === 'delete' && !confirm('Usunąć trwale zaznaczone rozmowy, briefy i analizy?')) return;
      toolbar.querySelectorAll('button').forEach((item) => { item.disabled = true; });
      try { for (const checkbox of checked) { const form = new URLSearchParams({ id: checkbox.dataset.session, action, view: new URL(location.href).searchParams.get('view') || 'active', csrf: document.querySelector('meta[name="admin-csrf"]')?.content || '' }); const response = await fetch(location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: form }); if (!response.ok) throw new Error(await response.text() || 'bulk'); } location.reload(); } catch (error) { alert(error.message || 'Nie udało się wykonać operacji na zaznaczonych rozmowach.'); update(); }
    }));
    update();
  };
  const removeDetailActions = (root) => root.querySelector('.actions')?.remove();
  const keepSelectedRowFrame = () => { const style = document.createElement('style'); style.textContent = '.row.current{outline:1px solid #7181f1;outline-offset:-1px;border-radius:8px}.row.current.status-completed{background:linear-gradient(90deg,#10261b,transparent)!important}.row.current.status-ready{background:linear-gradient(90deg,#18204a,transparent)!important}.row.current.status-active{background:linear-gradient(90deg,#2b250f,transparent)!important}.row.current.status-interrupted{background:linear-gradient(90deg,#32171b,transparent)!important}.row.current .row-title{color:inherit!important}'; document.head.append(style); };
  const rows = () => [...document.querySelectorAll('aside .row')];
  const humaniseQuestionLabel = (root) => root.querySelectorAll('h4').forEach((heading) => { if (heading.textContent.trim() === 'Pytania do klienta') { heading.textContent = 'Pytania klienta do zespołu'; heading.title = 'Przykładowe pytania, które klient może zadać naszemu zespołowi po otrzymaniu oferty.'; } });
  const detail = () => document.querySelectorAll('section.panel')[1];
  // Retry handlers are intentionally idempotent: one status node and one progress node per view.
  const addRetryStable = (root) => {
    const status = [...root.querySelectorAll('.status')].find((node) => node.textContent.trim() === 'Analiza wymaga ponowienia');
    const id = new URL(location.href).searchParams.get('session');
    if (!status || !id || status.dataset.retryAnalysis) return;
    status.dataset.retryAnalysis = '1'; status.style.cursor = 'pointer'; status.title = 'Kliknij, aby uruchomić analizę ponownie'; status.textContent = 'Analiza wymaga ponowienia — kliknij tutaj';
    status.onclick = async () => {
      if (status.dataset.retryBusy) return;
      status.dataset.retryBusy = '1'; status.style.pointerEvents = 'none'; status.textContent = 'Uruchamiamy analizę — może to potrwać…';
      try {
        const response = await fetch('/api/discovery.php?action=retryAnalysis&sessionId=' + encodeURIComponent(id), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' });
        const data = await response.json();
        if (response.ok && data.analysisStatus === 'COMPLETED') { status.textContent = 'Analiza gotowa — odświeżam widok…'; location.reload(); return; }
        status.textContent = 'Analiza nadal wymaga ponowienia — spróbuj później';
      } catch { status.textContent = 'Nie udało się połączyć — spróbuj później'; }
      finally { delete status.dataset.retryBusy; status.style.pointerEvents = 'auto'; }
    };
  };
  const addAnalysisControlsStable = (root) => {
    const id = new URL(location.href).searchParams.get('session'); const head = root.querySelector('.analysis-head');
    if (!id || !head || head.querySelector('[data-analysis-refresh]')) return;
    root.querySelector('[data-main-analysis]')?.remove();
    const button = document.createElement('button'); button.type = 'button'; button.className = 'button'; button.dataset.analysisRefresh = '1'; button.textContent = 'Przeanalizuj brief od nowa'; head.append(button);
    button.onclick = async () => {
      if (button.dataset.busy) return;
      button.dataset.busy = '1'; button.disabled = true; button.textContent = 'Analiza w toku…';
      root.querySelectorAll('[data-analysis-progress]').forEach((node) => node.remove());
      const progress = document.createElement('span'); progress.className = 'muted'; progress.dataset.analysisProgress = '1'; progress.textContent = '  Sprawdzam zakres, ryzyka i wycenę…'; progress.style.cssText = 'display:inline-block;margin-left:10px;color:#e7bd58'; head.append(progress);
      try {
        const response = await fetch('/api/discovery.php?action=retryAnalysis&sessionId=' + encodeURIComponent(id), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}', signal: AbortSignal.timeout(120000) });
        const data = await response.json();
        if (!response.ok || data.analysisStatus !== 'COMPLETED') throw new Error(data.message || 'analysis');
        button.textContent = 'Analiza gotowa — odświeżam…'; location.reload();
      } catch (error) {
        progress.textContent = '  Analiza nie została ukończona. Spróbuj ponownie.'; progress.title = error?.message || ''; progress.style.color = '#ef7c86'; button.textContent = 'Spróbuj ponownie'; button.disabled = false; delete button.dataset.busy;
      }
    };
  };
  const humaniseLabels = (root) => root.querySelectorAll('h3').forEach((heading) => { if (heading.textContent.trim() === 'Flagi ryzyka') heading.textContent = 'Obszary wymagające uwagi'; });
  const addRetry = (root) => { const status=[...root.querySelectorAll('.status')].find((node) => node.textContent.trim() === 'Analiza wymaga ponowienia'); const id=new URL(location.href).searchParams.get('session'); if(!status||!id||status.dataset.retryAnalysis)return; status.dataset.retryAnalysis='1';status.style.cursor='pointer';status.title='Kliknij, aby uruchomić analizę ponownie';status.textContent='Analiza wymaga ponowienia — kliknij tutaj';status.onclick=async()=>{status.style.pointerEvents='none';status.textContent='Uruchamiamy analizę — może to potrwać…';try{const response=await fetch('/api/discovery.php?action=retryAnalysis&sessionId='+encodeURIComponent(id),{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'});const data=await response.json();if(response.ok&&data.analysisStatus==='COMPLETED'){status.textContent='Analiza gotowa — odświeżam widok…';location.reload();return;}status.textContent='Analiza nadal wymaga ponowienia — spróbuj później';}catch{status.textContent='Nie udało się połączyć — spróbuj później';}finally{status.style.pointerEvents='auto';}}; };
  const addMainAnalysisButton = (root) => { const id=new URL(location.href).searchParams.get('session'); const actions=root.querySelector('.actions'); if(!id||!actions||actions.querySelector('[data-main-analysis]'))return; const button=document.createElement('button');button.type='button';button.className='button';button.dataset.mainAnalysis='1';button.textContent='Przeanalizuj brief od nowa';button.onclick=async()=>{button.disabled=true;button.textContent='Analizuję brief…';try{const response=await fetch('/api/discovery.php?action=retryAnalysis&sessionId='+encodeURIComponent(id),{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'});const data=await response.json();if(response.ok&&data.analysisStatus==='COMPLETED'){button.textContent='Analiza gotowa — odświeżam…';location.reload();return;}button.textContent='Analiza wymaga ponowienia';}catch{button.textContent='Spróbuj ponownie';}finally{button.disabled=false;}};actions.prepend(button); };
  const addOfferButton = (root) => {
    const id = new URL(location.href).searchParams.get('session');
    if (!id || root.querySelector('[data-offer-generator]')) return;
    const box = document.createElement('section');
    box.dataset.offerGenerator = '1';
    box.style.cssText = 'margin-top:22px;padding-top:18px;border-top:1px solid #303956';
    const title = document.createElement('h3');
    title.textContent = 'Oferta';
    const hint = document.createElement('p');
    hint.className = 'muted';
    hint.textContent = 'Utwórz roboczą kalkulację na podstawie aktualnego briefu.';
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'button';
    button.textContent = 'Przygotuj przykładową ofertę';
    const result = document.createElement('p');
    result.className = 'muted';
    button.onclick = async () => {
      button.disabled = true;
      button.textContent = 'Przygotowuję ofertę…';
      result.textContent = '';
      try {
        const response = await fetch('/api/offer.php?session=' + encodeURIComponent(id), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'generate' }) });
        const data = await response.json();
        if (!response.ok || !data.offer) throw new Error('offer');
        const offer = data.offer;
        result.textContent = 'Oferta gotowa: ' + offer.net.toLocaleString('pl-PL') + ' zł netto / ' + offer.gross.toLocaleString('pl-PL') + ' zł brutto, ' + offer.hours + ' h.';
        button.textContent = 'Przygotuj ponownie';
      } catch {
        result.textContent = 'Nie udało się przygotować oferty. Spróbuj ponownie później.';
        button.textContent = 'Spróbuj ponownie';
      } finally { button.disabled = false; }
    };
    box.append(title, hint, button, result);
    root.append(box);
  };
  const addBriefAssist = (root) => {
    return;
    const id = new URL(location.href).searchParams.get('session');
    if (!id) return;
    [...root.querySelectorAll('.analysis-item')]
      .filter((item) => item.querySelector('h4')?.textContent.trim() === 'Brakujące informacje')
      .forEach((item) => item.querySelectorAll('li').forEach(async (li) => {
        if (li.dataset.assist) return;
        li.dataset.assist = '1';
        const question = li.textContent.trim();
        li.textContent = '';
        const spinner = document.createElement('span');
        spinner.textContent = '⟳';
        spinner.style.cssText = 'display:inline-block;margin-right:7px;color:#9daaff;animation:spin .8s linear infinite';
        const waiting = document.createElement('span');
        waiting.textContent = 'Generuję propozycję AI…';
        waiting.style.color = '#9daaff';
        li.append(spinner, waiting);
        try {
          const response = await fetch('/api/brief-assist.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ session: id, question }) });
          const data = await response.json();
          const proposal = (data.options || [])[0];
          if (!proposal || !data.aiGenerated) {
            li.textContent = question + ' — propozycja AI nie jest jeszcze gotowa. Kliknij „Przeanalizuj brief od nowa” i spróbuj ponownie.';
            return;
          }
          await fetch('/api/brief-assist.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'save', session: id, question, answer: proposal }) });
          li.textContent = '';
          li.style.cssText = 'margin:.55rem 0;padding:.65rem .75rem;border-radius:.55rem;background:#35151b;border:1px solid #7c303d';
          const dot = document.createElement('button');
          dot.type = 'button'; dot.textContent = '●';
          dot.style.cssText = 'margin-right:7px;border:0;background:transparent;color:#ff6474;cursor:pointer;font-size:16px';
          dot.title = 'Propozycja AI — kliknij po alternatywy';
          const text = document.createElement('span');
          const clean = proposal.replace(/^.*?Propozycja AI\s*[-\u2014]\s*/, '');
          text.textContent = question + ' — ' + clean;
          li.append(dot, text);
          dot.onclick = () => {
            const alternative = prompt(question + '\n\nPropozycja AI: ' + clean + '\n\nWpisz własną odpowiedź lub wybierz inną pozycję:');
            if (!alternative) return;
            fetch('/api/brief-assist.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'save', session: id, question, answer: alternative }) });
            text.textContent = question + ' — ' + alternative;
            dot.style.color = '#7d8795';
            li.style.background = '#151a24';
            li.style.borderColor = '#32415c';
          };
        } catch {
          li.textContent = question + ' — nie udało się przygotować propozycji AI';
        }
      }));
  };
  const addBriefTimestamp = (root) => {
    const timestamp = root.querySelector('.topline .muted');
    if (!timestamp || timestamp.dataset.briefTimestamp) return;
    timestamp.dataset.briefTimestamp = '1';
    timestamp.textContent = timestamp.textContent.replace(/^.*?:\s*/, 'Brief wygenerowany / ostatnio aktualizowany: ');
  };
  const addAnalysisControls = (root) => {
    const id = new URL(location.href).searchParams.get('session');
    const head = root.querySelector('.analysis-head');
    if (!id || !head || head.querySelector('[data-analysis-refresh]')) return;
    root.querySelector('[data-main-analysis]')?.remove();
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'button';
    button.dataset.analysisRefresh = '1';
    button.textContent = 'Przeanalizuj brief od nowa';
    button.onclick = async () => {
      button.disabled = true;
      button.textContent = 'Od\u015bwie\u017cam analiz\u0119\u2026';
      try {
        const response = await fetch('/api/discovery.php?action=retryAnalysis&sessionId=' + encodeURIComponent(id), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' });
        const data = await response.json();
        if (!response.ok || data.analysisStatus !== 'COMPLETED') throw new Error('analysis');
        button.textContent = 'Analiza gotowa \u2014 od\u015bwie\u017cam\u2026';
        location.reload();
      } catch {
        button.textContent = 'Spr\u00f3buj ponownie';
        button.disabled = false;
      }
    };
    button.onclick = async () => {
      button.disabled = true; button.textContent = 'Analiza w toku…';
      const progress = document.createElement('span'); progress.className = 'muted'; progress.textContent = '  Sprawdzam zakres, ryzyka i wycenę…'; progress.style.cssText = 'display:inline-block;margin-left:10px;color:#e7bd58'; head.append(progress);
      try { const response = await fetch('/api/discovery.php?action=retryAnalysis&sessionId=' + encodeURIComponent(id), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}', signal: AbortSignal.timeout(120000) }); const data = await response.json(); if (!response.ok || data.analysisStatus !== 'COMPLETED') throw new Error('analysis'); button.textContent = 'Analiza gotowa — odświeżam…'; location.reload(); } catch { progress.textContent = '  Analiza nie została ukończona. Spróbuj ponownie.'; progress.style.color = '#ef7c86'; button.textContent = 'Spróbuj ponownie'; button.disabled = false; }
    };
    head.append(button);
  };
  const generateOfferWithStatus = async (sessionId, container) => {
    const root = container.closest('[data-visible-offer]') || container;
    if (root.dataset.generatingOffer === '1') throw new Error('Oferta jest już generowana. Poczekaj na zakończenie.');
    root.dataset.generatingOffer = '1';
    let status = root.querySelector('[data-generation-status]');
    if (!status) { status = document.createElement('div'); status.dataset.generationStatus = '1'; status.setAttribute('role', 'status'); status.setAttribute('aria-live', 'polite'); root.prepend(status); }
    status.style.cssText = 'position:sticky;top:12px;z-index:10;padding:16px;margin:12px 0;border:1px solid #7184ff;border-radius:10px;background:#18213d;color:#e4eaff;font-weight:600';
    const started = Date.now();
    const update = () => { const elapsed = Math.floor((Date.now() - started) / 1000); status.textContent = '⏳ Generowanie oferty — ' + elapsed + ' s. ' + (elapsed < 40 ? 'AI opracowuje zakres na podstawie zatwierdzonych odpowiedzi.' : 'Nadal czekamy na odpowiedź AI. Może to potrwać do około 2 minut.'); };
    update(); status.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    const timer = setInterval(update, 1000);
    try {
      const response = await fetch('/api/offer.php?session=' + encodeURIComponent(sessionId), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'generate' }), signal: AbortSignal.timeout(130000) });
      const data = await response.json().catch(() => { throw new Error('Serwer zwrócił nieprawidłową odpowiedź (HTTP ' + response.status + '). Odśwież panel, aby sprawdzić zapisaną wersję.'); });
      if (!response.ok || !data.offer) throw new Error(data.message || 'Generowanie nie powiodło się (HTTP ' + response.status + ').');
      status.style.background = '#123322'; status.style.borderColor = '#3eaf73';
      status.textContent = '✓ Oferta odświeżona. Wersja ' + data.offer.version + ' jest gotowa do sprawdzenia — zweryfikuj zakres i cenę.';
      return data;
    } catch (error) {
      status.style.background = '#35151b'; status.style.borderColor = '#ef7c86';
      const message = error.name === 'TimeoutError' ? 'Przekroczono czas oczekiwania. Odśwież panel przed ponowieniem, aby sprawdzić, czy oferta została zapisana.' : error.message;
      status.textContent = '✕ ' + message; root.querySelectorAll('[data-offer-retry]').forEach((button) => { button.style.display = 'inline-block'; }); throw new Error(message);
    } finally { clearInterval(timer); delete root.dataset.generatingOffer; }
  };
  const renderOfferPreview = (container, offer, sessionId) => {
    const generationButton = container.parentElement?.querySelector('[data-offer-generate]');
    if (generationButton) generationButton.dataset.needsGeneration = !offer || offer.status === 'OUTDATED' ? '1' : '0';
    container.textContent = '';
    if (!offer) return;
    const documentBox = document.createElement('article');
    documentBox.dataset.offerDocument = '1';
    const aiNeedsReview = offer.verification === 'AI_CHECKED' && offer.status !== 'REVIEWED' && offer.status !== 'SENT';
    documentBox.style.cssText = 'margin-top:16px;padding:20px;border:2px solid ' + (offer.status === 'OUTDATED' || aiNeedsReview ? '#d7a83e' : '#5368c7') + ';border-radius:14px;background:' + (offer.status === 'OUTDATED' || aiNeedsReview ? 'linear-gradient(135deg,#2a2412,#17140d)' : 'linear-gradient(135deg,#111831,#10141e)');
    const title = document.createElement('h3'); title.textContent = offer.title || 'Wstępna oferta'; title.style.margin = '0';
    const created = document.createElement('p'); created.className = 'muted'; created.textContent = 'Ostatnie odświeżenie: ' + new Date((offer.updatedAt || offer.createdAt || Date.now() / 1000) * 1000).toLocaleString('pl-PL');
    const versionMeta = document.createElement('span'); versionMeta.className = 'status'; versionMeta.textContent = 'Wersja ' + Number(offer.version || 1) + (offer.offerId ? ' · ' + offer.offerId : ''); versionMeta.style.cssText = 'display:inline-flex;margin-bottom:8px';
    const project = document.createElement('h4'); project.textContent = offer.project || 'Projekt'; project.style.cssText = 'margin:18px 0 8px;font-size:1.05rem;line-height:1.45;font-weight:600;color:#eef1ff';
    const summary = document.createElement('p'); summary.textContent = offer.summary || ''; summary.style.cssText = 'margin:0 0 16px;color:#cbd4e9;line-height:1.6';
    const status = document.createElement('p'); status.className = 'muted'; status.textContent = offer.status === 'OUTDATED' ? 'Status: oferta nieaktualna — analiza briefu została ponowiona. Wygeneruj nową wersję.' : (aiNeedsReview ? 'Status: oferta przygotowana po zmianach AI — wymaga weryfikacji.' : (offer.status === 'REVIEWED' || offer.status === 'SENT' ? (offer.verification === 'AI_CHECKED' ? 'Status: oferta sprawdzona automatycznie przez AI.' : 'Status: oferta zweryfikowana przez zespół.') : 'Status: wersja robocza — wymaga weryfikacji przed wysłaniem.'));
    const contact = offer.contact || {}; const recipient = document.createElement('p'); recipient.className = 'muted'; recipient.textContent = 'Kontakt klienta: ' + [contact.name, contact.phone, contact.email].filter(Boolean).join(' · ');
    documentBox.append(title, versionMeta, created, status, recipient, project, summary);
    if (offer.sentAt) { const sent = document.createElement('p'); sent.className = 'muted'; sent.textContent = 'Wysłano klientowi: ' + (offer.sentTo || '') + ' · ' + new Date(offer.sentAt * 1000).toLocaleString('pl-PL'); documentBox.append(sent); }
    const currentQuestions = new Set([...document.querySelectorAll('.analysis-grid .analysis-item:nth-child(2) li[data-question]')].map((li) => li.dataset.question.trim()));
    const seenChanges = new Set();
    const changeLog = Array.isArray(offer.changeLog) ? offer.changeLog : [];
    const pendingChanges = changeLog.filter((change) => !change.confirmed).length;
    if (pendingChanges) { const notice = document.createElement('p'); notice.className = 'muted'; notice.textContent = 'Zmienione fragmenty: ' + pendingChanges + '. Rozwiń poprzednią treść, zatwierdź zmianę lub przywróć poprzednią. Przywrócenie dotyczy oferty, nie zmienia ustaleń analizy.'; documentBox.append(notice); }
    (offer.sections || []).filter((section) => !/zatwierdzone ustalenia|zmiany do potwierdzenia/i.test(section.title || '')).forEach((section) => { const part = document.createElement('section'); part.dataset.offerSection = '1'; const heading = document.createElement('h4'); heading.textContent = section.title; heading.style.margin = '15px 0 5px'; const list = document.createElement('ul'); (section.items || []).filter(Boolean).forEach((item) => { const li = document.createElement('li'); li.textContent = String(item).replace(/ponieważ budżet do ([^,.;]+), w ramach budżetu do \1/iu, 'Przy budżecie do $1'); list.append(li); }); if (!list.children.length) { const li = document.createElement('li'); li.textContent = 'Do ustalenia'; list.append(li); } part.append(heading, list); documentBox.append(part); });
    changeLog.forEach((change) => {
      if (change.confirmed === true || change.restored === true) return;
      const path = change.path || [];
      let target = path[0] === 'summary' ? summary : path[0] === 'project' ? project : null;
      if (path[0] === 'sections') {
        const visibleSections = (offer.sections || []).filter((section) => !/zatwierdzone ustalenia|zmiany do potwierdzenia/i.test(section.title || ''));
        const section = [...documentBox.querySelectorAll('[data-offer-section]')][visibleSections.indexOf(offer.sections[path[1]])];
        if (section) target = path[2] === 'title' ? section.querySelector('h4') : (change.currentValue === null ? section.querySelector('ul') : [...(section.querySelector('ul')?.children || [])].filter((item) => !item.dataset.changeControl)[path[3]]);
      }
      const details = document.createElement('details'); details.dataset.offerChange = '1';
      const resolved = change.confirmed === true;
      details.style.cssText = 'margin:8px 0 14px;padding:10px;border:1px solid ' + (resolved ? '#397957' : '#d7a83e') + ';border-radius:8px;background:' + (resolved ? '#123322' : '#30280e');
      const label = document.createElement('summary'); label.style.cursor = 'pointer';
      label.textContent = change.restored ? '↶ Przywrócono poprzednią treść' : resolved ? '✓ Zmiana zatwierdzona' : change.currentValue === null ? 'Usunięty punkt — sprawdź zmianę' : 'Zmieniony fragment — sprawdź poprzednią treść';
      const before = document.createElement('p'); before.textContent = 'Poprzednio: ' + (change.previous || '(brak — nowy punkt)');
      const after = document.createElement('p'); after.textContent = 'Propozycja: ' + (change.current || '(punkt usunięty)');
      details.append(label, before, after);
      if (target && !change.restored) { target.title = 'Poprzednio: ' + (change.previous || '(brak)'); target.style.background = resolved ? '#123322' : '#30280e'; target.style.borderLeft = '3px solid ' + (resolved ? '#397957' : '#d7a83e'); target.style.paddingLeft = '8px'; }
      if (!resolved) {
        [['Zatwierdź zmianę', 'confirmChange'], ['Przywróć poprzednią', 'restoreChange']].forEach(([text, action]) => {
          if (action === 'restoreChange' && !path.length) return;
          const button = document.createElement('button'); button.type = 'button'; button.className = 'button'; button.textContent = text; button.style.marginRight = '8px';
          button.onclick = async () => {
            details.querySelectorAll('button').forEach((item) => item.disabled = true);
            try {
              const response = await fetch('/api/offer.php?session=' + encodeURIComponent(sessionId), { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action, key:change.key, version:offer.version}) });
              const data = await response.json(); if (!response.ok || !data.offer) throw new Error(data.message || 'Nie udało się zapisać decyzji.');
              renderOfferPreview(container, data.offer, sessionId);
            } catch (error) { alert(error.message); details.querySelectorAll('button').forEach((item) => item.disabled = false); }
          };
          details.append(button);
        });
      }
      if (target?.tagName === 'LI') { const row = document.createElement('li'); row.dataset.changeControl = '1'; row.style.listStyle = 'none'; row.append(details); target.after(row); }
      else if (target) target.after(details); else documentBox.append(details);
    });
    const pricing = offer.pricing || {}; const payment = offer.payment || {};
    const price = document.createElement('section'); price.style.cssText = 'margin-top:16px;padding:14px;border-radius:10px;background:#171e35';
    price.innerHTML = '<h4 style="margin:0 0 7px">Wycena</h4><strong>' + Number(pricing.net || 0).toLocaleString('pl-PL') + ' zł netto</strong> + VAT ' + Number(pricing.vatRate || 23) + '% = <strong>' + Number(pricing.gross || 0).toLocaleString('pl-PL') + ' zł brutto</strong><br><span class="muted">' + (pricing.manualPrice ? 'Cena ustalona ręcznie. ' : '') + 'Zaliczka: ' + Number(payment.depositRate || 0) + '% (' + Number(payment.deposit || 0).toLocaleString('pl-PL') + ' zł netto).</span>';
    const editPrice = document.createElement('button'); editPrice.type = 'button'; editPrice.className = 'button'; editPrice.textContent = 'Zmień cenę netto'; editPrice.style.marginTop = '10px';
    editPrice.onclick = async () => { const value = prompt('Podaj nową cenę netto w zł:', String(pricing.net || '')); if (!value?.trim()) return; editPrice.disabled = true; try { const response = await fetch('/api/offer.php?session=' + encodeURIComponent(sessionId), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'updatePrice', net: value }) }); const data = await response.json(); if (!response.ok || !data.offer) throw new Error('price'); renderOfferPreview(container, data.offer, sessionId); } catch { editPrice.disabled = false; alert('Nie udało się zapisać nowej ceny.'); } };
    const editText = document.createElement('button'); editText.type = 'button'; editText.className = 'button'; editText.textContent = 'Edytuj opis oferty'; editText.style.marginTop = '10px';
    editText.onclick = async () => { const value = prompt('Możesz poprawić podsumowanie oferty:', String(offer.summary || '')); if (value === null || !value.trim()) return; editText.disabled = true; try { const response = await fetch('/api/offer.php?session=' + encodeURIComponent(sessionId), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'updateText', summary: value.trim() }) }); const data = await response.json(); if (!response.ok || !data.offer) throw new Error('text'); renderOfferPreview(container, data.offer, sessionId); } catch { editText.disabled = false; alert('Nie udało się zapisać zmian.'); } };
    const note = document.createElement('p'); note.className = 'muted'; note.textContent = (offer.note || '') + (Number(offer.version || 1) > 1 ? ' Poprzednie wersje dokumentu pozostają zapisane w historii sesji.' : '');
    const download = document.createElement(offer.status === 'OUTDATED' ? 'button' : 'a'); download.className = 'button'; if (offer.status === 'OUTDATED') download.type = 'button'; else download.href = '/api/offer.php?session=' + encodeURIComponent(sessionId) + '&format=pdf&version=' + Number(offer.version || 1); download.textContent = 'Pobierz ofertę PDF'; download.style.cssText = 'display:inline-block;margin:10px 0 0 7px;text-decoration:none';
    const review = document.createElement('button'); review.type = 'button'; review.className = 'button'; review.textContent = offer.status === 'REVIEWED' || offer.status === 'SENT' ? 'Oferta zweryfikowana' : 'Oznacz jako zweryfikowaną'; review.style.cssText = 'display:inline-block;margin:10px 0 0 7px'; review.disabled = offer.status === 'REVIEWED' || offer.status === 'SENT';
    review.onclick = async () => { review.disabled = true; try { const response = await fetch('/api/offer.php?session=' + encodeURIComponent(sessionId), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'review' }) }); const data = await response.json(); if (!response.ok || !data.offer) throw new Error('review'); renderOfferPreview(container, data.offer, sessionId); } catch { review.disabled = false; alert('Nie udało się oznaczyć oferty jako zweryfikowanej.'); } };
    const send = document.createElement('button'); send.type = 'button'; send.className = 'button'; send.textContent = offer.status === 'SENT' ? 'Wysłano do klienta' : 'Wyślij klientowi'; send.style.cssText = 'display:inline-block;margin:10px 0 0 7px'; send.disabled = offer.status !== 'REVIEWED'; send.title = send.disabled && offer.status !== 'SENT' ? 'Najpierw zweryfikuj ofertę.' : '';
    send.onclick = async () => { if (!confirm('Wysłać ofertę na zapisany adres klienta?')) return; send.disabled = true; try { const response = await fetch('/api/offer.php?session=' + encodeURIComponent(sessionId), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'send' }) }); const data = await response.json(); if (!response.ok || !data.offer) throw new Error(data.message || 'send'); renderOfferPreview(container, data.offer, sessionId); } catch (error) { send.disabled = false; alert(error.message || 'Nie udało się wysłać oferty.'); } };
    const clientMessage = document.createElement('textarea'); clientMessage.placeholder = 'Wiadomość lub uwaga klienta do oferty (opcjonalnie)'; clientMessage.value = String(offer.clientMessage || ''); clientMessage.rows = 3; clientMessage.style.cssText = 'display:block;width:100%;margin:12px 0 6px;padding:9px;border:1px solid #293956;border-radius:8px;background:#0d1119;color:#e7edf9;resize:vertical';
    const saveClientMessage = document.createElement('button'); saveClientMessage.type = 'button'; saveClientMessage.className = 'button'; saveClientMessage.textContent = 'Zapisz wiadomość klienta'; saveClientMessage.style.cssText = 'display:inline-block;margin:4px 0 10px 0'; saveClientMessage.onclick = async () => { saveClientMessage.disabled = true; try { const response = await fetch('/api/offer.php?session=' + encodeURIComponent(sessionId), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'saveClientMessage', clientMessage: clientMessage.value.trim() }) }); const data = await response.json(); if (!response.ok || !data.offer) throw new Error(data.message || 'save'); saveClientMessage.textContent = 'Zapisano'; window.setTimeout(() => { saveClientMessage.textContent = 'Zapisz wiadomość klienta'; }, 1200); } catch (error) { alert(error.message || 'Nie udało się zapisać wiadomości.'); } finally { saveClientMessage.disabled = false; } };
    const accept = document.createElement('button'); accept.type = 'button'; accept.className = 'button'; accept.textContent = offer.status === 'ACCEPTED' ? 'Oferta zaakceptowana — przygotuj umowę' : 'Zaakceptowano ofertę — przygotuj umowę'; accept.style.cssText = 'display:inline-block;margin:10px 0 0 7px'; accept.disabled = offer.status === 'ACCEPTED'; accept.onclick = async () => { accept.disabled = true; try { const response = await fetch('/api/offer.php?session=' + encodeURIComponent(sessionId), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'accept', clientMessage: clientMessage.value.trim() }) }); const data = await response.json(); if (!response.ok || !data.offer) throw new Error(data.message || 'accept'); location.reload(); } catch (error) { accept.disabled = false; alert(error.message || 'Nie udało się zapisać akceptacji.'); } };
    const execute = document.createElement('button'); execute.type = 'button'; execute.className = 'button'; execute.textContent = 'Wykonaj projekt'; execute.disabled = true; execute.title = 'Generator repozytorium będzie dostępny w kolejnym etapie.'; execute.style.cssText = 'display:inline-block;margin:10px 0 0 7px;opacity:.55';
    if (offer.status === 'ACCEPTED') { review.disabled = true; review.textContent = 'Oferta zaakceptowana i zweryfikowana'; review.title = 'Akceptacja klienta obejmuje również weryfikację dokumentu.'; }
    const toolbar = document.createElement('div'); toolbar.style.cssText = 'display:none;position:fixed;z-index:10000;gap:6px;margin:0;padding:8px;border:1px solid #5368c7;border-radius:8px;background:#101522;box-shadow:0 10px 30px #0008';
    [['B', 'bold'], ['I', 'italic'], ['• Lista', 'insertUnorderedList']].forEach(([label, command]) => { const tool = document.createElement('button'); tool.type = 'button'; tool.className = 'button'; tool.textContent = label; tool.onclick = () => document.execCommand(command, false); toolbar.append(tool); });
    documentBox.append(toolbar);
    const getEditableSections = () => [...documentBox.querySelectorAll('[data-offer-section]')];
    const addPoint = document.createElement('button'); addPoint.type = 'button'; addPoint.className = 'button'; addPoint.textContent = 'Dodaj punkt';
    addPoint.onclick = () => { const sections = getEditableSections(); const active = document.activeElement?.closest('section'); const target = active && sections.includes(active) ? active : sections[sections.length - 1]; if (!target) return; const list = target.querySelector('ul') || target.appendChild(document.createElement('ul')); const li = document.createElement('li'); li.textContent = 'Nowy punkt — kliknij, aby edytować'; li.contentEditable = 'true'; li.style.outline = '1px dashed #7184ff'; list.append(li); li.focus(); };
    const addSection = document.createElement('button'); addSection.type = 'button'; addSection.className = 'button'; addSection.textContent = 'Dodaj sekcję';
    addSection.onclick = () => { const section = document.createElement('section'); section.dataset.offerSection = '1'; section.style.marginTop = '15px'; const heading = document.createElement('h4'); heading.textContent = 'Nowa sekcja'; const list = document.createElement('ul'); const li = document.createElement('li'); li.textContent = 'Nowy punkt — kliknij, aby edytować'; list.append(li); section.append(heading, list); const pricingSection = [...documentBox.querySelectorAll('section')].at(-1); documentBox.insertBefore(section, pricingSection || toolbar); [heading, li].forEach((field) => { field.contentEditable = 'true'; field.style.outline = '1px dashed #7184ff'; field.style.padding = '6px'; }); heading.focus(); };
    toolbar.append(addPoint, addSection); toolbar.querySelectorAll('button')[1]?.remove(); toolbar.querySelectorAll('button')[1]?.remove(); toolbar.remove();
    const listObserver = new MutationObserver(() => documentBox.querySelectorAll('li').forEach((li) => { li.style.margin = '.55rem 0'; li.style.padding = '.65rem .75rem'; li.style.borderRadius = '6px'; })); listObserver.observe(documentBox, { childList: true, subtree: true });
    const hideEditorPopup = () => { toolbar.style.display = 'none'; if (toolbar.parentNode) toolbar.remove(); };
    window.addEventListener('scroll', hideEditorPopup, { passive: true });
    document.addEventListener('mousedown', (event) => { if (toolbar.style.display !== 'none' && !toolbar.contains(event.target) && !event.target.closest('[contenteditable="true"]')) hideEditorPopup(); }, true);
    if (Array.isArray(pricing.modules) && pricing.modules.length) { const modules = document.createElement('div'); modules.style.cssText = 'margin-top:10px;color:#cbd4e9;font-size:12px'; modules.textContent = 'Rozbicie zakresu: ' + pricing.modules.map((module) => String(module.name) + ' (' + Number(module.hours || 0) + ' h)').join(' · ') + '. ' + (pricing.rationale || ''); price.append(modules); }
    let editingText = false;
    documentBox.addEventListener('click', (event) => { if (editingText) { const field = event.target.closest('[contenteditable="true"]'); if (field) { documentBox.querySelectorAll('li').forEach((li) => { li.style.margin = '.55rem 0'; li.style.padding = '.65rem .75rem'; li.style.borderRadius = '6px'; }); document.body.append(toolbar); toolbar.style.display = 'flex'; const rect = field.getBoundingClientRect(); toolbar.style.left = Math.max(8, Math.min(window.innerWidth - toolbar.offsetWidth - 8, rect.left)) + 'px'; toolbar.style.top = Math.max(8, rect.bottom + 6) + 'px'; } } });
    editText.onclick = async () => {
      const sections = getEditableSections();
      const fields = [project, summary, ...sections.flatMap((section) => [section.querySelector('h4'), ...section.querySelectorAll('li:not([data-change-control])')].filter(Boolean))];
      if (!editingText) { editingText = true; download.removeAttribute('href'); download.textContent = 'Zapisz zmiany, aby pobrać aktualny PDF'; toolbar.style.display = 'flex'; fields.forEach((field) => { field.contentEditable = 'true'; field.style.outline = '1px dashed #7184ff'; field.style.padding = '6px'; field.style.borderRadius = '6px'; }); editText.textContent = 'Zapisz zmiany'; project.focus(); return; }
      editText.disabled = true;
      const editedSections = sections.map((section) => ({ title: section.querySelector('h4')?.textContent.trim() || 'Sekcja', items: [...section.querySelectorAll('li:not([data-change-control])')].map((item) => item.textContent.trim()).filter(Boolean) }));
      try { const response = await fetch('/api/offer.php?session=' + encodeURIComponent(sessionId), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'updateText', project: project.textContent.trim(), summary: summary.textContent.trim(), sections: editedSections }) }); const data = await response.json(); if (!response.ok || !data.offer) throw new Error('text'); renderOfferPreview(container, data.offer, sessionId); } catch { editText.disabled = false; alert('Nie udało się zapisać zmian.'); }
    };
    const archiveButton = document.createElement('button'); archiveButton.type = 'button'; archiveButton.className = 'button'; archiveButton.textContent = 'Przeglądaj archiwalne oferty'; archiveButton.style.marginTop = '10px';
    const archiveList = document.createElement('div'); archiveList.style.cssText = 'display:none;margin-top:10px;padding:10px;border:1px solid #29313c;border-radius:9px;background:#0d1119';
    const versions = Array.isArray(offer._versions) ? offer._versions : []; versions.slice().reverse().forEach((version) => { const item = document.createElement('button'); item.type = 'button'; item.textContent = 'Wersja ' + Number(version.version || 1) + ' · ' + (version.project || 'Oferta') + ' · ' + new Date((version.updatedAt || version.createdAt || Date.now() / 1000) * 1000).toLocaleString('pl-PL') + ' — Pobierz PDF'; item.style.cssText = 'display:block;width:100%;padding:8px 4px;border:0;border-bottom:1px solid #29313c;background:none;color:#b9c4df;text-align:left;cursor:pointer'; item.onclick = () => window.open('/api/offer.php?session=' + encodeURIComponent(sessionId) + '&format=pdf&version=' + Number(version.version || 1), '_blank'); archiveList.append(item); });
    if (!versions.length) { fetch('/api/offer.php?session=' + encodeURIComponent(sessionId)).then((response) => response.json()).then((data) => (data.offerVersions || []).slice().reverse().forEach((version) => { const item = document.createElement('button'); item.type = 'button'; item.textContent = 'Wersja ' + Number(version.version || 1) + ' · ' + (version.project || 'Oferta') + ' — Pobierz PDF'; item.style.cssText = 'display:block;width:100%;padding:8px 4px;border:0;border-bottom:1px solid #29313c;background:none;color:#b9c4df;text-align:left;cursor:pointer'; item.onclick = () => window.open('/api/offer.php?session=' + encodeURIComponent(sessionId) + '&format=pdf&version=' + Number(version.version || 1), '_blank'); archiveList.append(item); })); }
    archiveButton.onclick = () => { archiveList.style.display = archiveList.style.display === 'none' ? 'block' : 'none'; archiveButton.textContent = archiveList.style.display === 'none' ? 'Przeglądaj archiwalne oferty' : 'Ukryj archiwalne oferty'; };
    const missingPanel = (container.closest('section.panel') || document).querySelectorAll('.analysis-grid .analysis-item:nth-child(2) li[data-question]'); const unresolvedMissing = [...missingPanel].filter((li) => li.dataset.accepted !== '1'); if (unresolvedMissing.length && offer.status !== 'REVIEWED' && offer.status !== 'SENT') { review.disabled = true; review.title = 'Najpierw zatwierdź wszystkie brakujące informacje w Analizie wewnętrznej AI.'; review.textContent = 'Wymaga potwierdzenia brakujących informacji'; }
    if (offer.status === 'ACCEPTED') { review.disabled = true; review.textContent = 'Oferta zaakceptowana i zweryfikowana'; review.title = 'Akceptacja klienta obejmuje również weryfikację dokumentu.'; }
    if (offer.status === 'OUTDATED') {
      review.disabled = true; editPrice.disabled = true; editText.disabled = true;
    download.textContent = 'Ponów aktualizację';
      download.dataset.offerRetry = '1'; download.style.display = 'none';
      const refreshMessage = document.createElement('p'); refreshMessage.setAttribute('role', 'status'); refreshMessage.textContent = 'Oferta jest nieaktualna. Odśwież ją, aby pobrać aktualny PDF. Poprzedni PDF znajdziesz w historii.'; price.append(refreshMessage);
      download.onclick = async () => {
        download.disabled = true; download.textContent = 'Odświeżam ofertę…'; refreshMessage.textContent = '';
        const generator = container.parentElement?.querySelector('[data-offer-generate]');
        if (generator) generator.disabled = true;
        try {
          const data = await generateOfferWithStatus(sessionId, container);
          renderOfferPreview(container, data.offer, sessionId);
          if (generator) generator.style.display = 'none';
        } catch (error) {
          refreshMessage.textContent = error.message || 'Nie udało się połączyć z serwerem. Spróbuj ponownie.';
          refreshMessage.style.color = '#ef7c86';
          download.disabled = false; download.textContent = 'Spróbuj odświeżyć ponownie';
        } finally { if (generator) generator.disabled = false; }
      };
    }
    price.append(editPrice, editText, download, review, send, accept, execute); documentBox.append(price, clientMessage, saveClientMessage, note, archiveButton, archiveList); container.append(documentBox);
    if (offer.status === 'ACCEPTED') { editPrice.disabled = true; editText.disabled = true; editPrice.title = 'Zaakceptowana oferta jest zablokowana.'; editText.title = 'Zaakceptowana oferta jest zablokowana.'; }
    const generator = container.parentElement?.querySelector('[data-offer-generate]'); if (generator && offer.status === 'OUTDATED') { generator.textContent = 'Odśwież ofertę po nowej analizie'; generator.style.display = 'inline-block'; generator.disabled = false; generator.dataset.offerLoaded = '1';  }
    if (typeof ensureAgreedBeforeConversation === 'function') ensureAgreedBeforeConversation(container.closest('section.panel') || document);
  };
  const addVisibleOfferButton = (root) => {
    const id = new URL(location.href).searchParams.get('session');
    const brief = root.querySelector('.brief-grid');
    if (!id || !brief || root.querySelector('[data-visible-offer]')) return;
    root.querySelector('[data-offer-generator]')?.remove();
    const box = document.createElement('section');
    box.dataset.visibleOffer = '1';
    box.style.cssText = 'margin:22px 0;padding:18px 0;border-top:1px solid #303956';
    const title = document.createElement('h3'); title.textContent = 'Oferta';
    const hint = document.createElement('p'); hint.className = 'muted'; hint.textContent = 'Po zatwierdzeniu wszystkich odpowiedzi oferta aktualizuje się automatycznie. Sprawdź zmieniony zakres i cenę przed zatwierdzeniem. Cena pozostaje zachowana i wymaga oceny po zmianie zakresu.';
    const button = document.createElement('button'); button.type = 'button'; button.className = 'button'; button.dataset.offerGenerate = '1'; button.textContent = 'Przygotuj przyk\u0142adow\u0105 ofert\u0119'; button.disabled = true; button.style.display = 'none';
    const result = document.createElement('div');
    const feedback = document.createElement('p'); feedback.setAttribute('role', 'alert'); feedback.style.color = '#ef7c86';
    button.onclick = async () => {
      button.disabled = true; button.textContent = 'Odświeżam dokument…'; feedback.textContent = '';
      try {
        const data = await generateOfferWithStatus(id, result);
        const offer = data.offer;
        renderOfferPreview(result, offer, id);
        button.textContent = 'Odśwież dokument z aktualnego briefu'; button.style.display = 'none';
      } catch (error) {
        feedback.textContent = error.message || 'Nie udało się połączyć z serwerem.';
        button.textContent = 'Spr\u00f3buj ponownie';
        button.style.display = 'inline-block';
      } finally { button.disabled = false; }
    };
    box.append(title, hint, button, feedback, result);
    brief.after(box);
    const contractBox = document.createElement('div'); box.before(contractBox);
    fetch('/api/contract.php?session=' + encodeURIComponent(id) + '&format=editor').then(async response => { if (!response.ok) throw new Error('contract'); contractBox.innerHTML = await response.text(); window.contractReview.init(contractBox.querySelector('form.contract-form')); if (location.hash === '#contract-panel') contractBox.scrollIntoView(); }).catch(() => { contractBox.textContent = 'Nie udało się wczytać umowy. Odśwież panel.'; });
    // Enable the offer action immediately when there are no unresolved items.
    refreshOfferEligibility(root);
    button.dataset.offerLoaded = '0';
    fetch('/api/offer.php?session=' + encodeURIComponent(id)).then((response) => response.json()).then((data) => { button.dataset.offerLoaded = '1'; renderOfferPreview(result, data.offer, id); if (data.offer?.status === 'OUTDATED') { button.textContent = 'Odśwież ofertę po nowej analizie'; button.style.display = 'inline-block'; } else if (data.offer) { button.style.display = 'none'; } refreshOfferEligibility(root); }).catch(() => { button.dataset.offerLoaded = '1'; refreshOfferEligibility(root); });
  };
  const addMissingInfoSpinner = async (root) => {
    const id = new URL(location.href).searchParams.get('session');
    const item = root.querySelectorAll('.analysis-item')[1];
    if (!id || !item) return;
    for (const li of item.querySelectorAll('li')) {
      if (li.dataset.logicalProposal || li.textContent.trim() === 'Brak') return;
      li.dataset.logicalProposal = '1';
      const question = li.textContent.trim();
      li.dataset.generating = '1';
      try {
        const response = await fetch('/api/brief-assist.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ session: id, question, autoConfirm: true }), signal: AbortSignal.timeout(15000) });
        const data = await response.json(); const proposal = (data.options || [])[0];
        if (!response.ok || !data.aiGenerated || !proposal) throw new Error('proposal');
        await fetch('/api/brief-assist.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'save', session: id, question, answer: proposal }) });
        delete li.dataset.generating;
        li.dataset.proposalReady = '1';
        li.textContent = question + ' — ' + proposal.replace(/^.*?Propozycja AI\s*[-\u2014]\s*/, '');
        li.style.cssText = 'margin:.55rem 0;padding:.65rem .75rem;border-radius:.55rem;background:#35151b;border:1px solid #7c303d';
      } catch {
        delete li.dataset.generating;
        li.dataset.proposalReady = '1';
        li.textContent = question + ' — propozycja AI nie jest jeszcze gotowa.';
      }
    }
  };
  const refreshOfferEligibility = (root) => {
    const button = root.querySelector('[data-offer-generate]');
    const missing = [...(root.querySelectorAll('.analysis-item')[1]?.querySelectorAll('li') || [])].filter((li) => li.dataset.question && li.textContent.trim() !== 'Brak');
    const review = [...root.querySelectorAll('[data-offer-document] button')].find((item) => item.textContent.includes('Wymaga') || item.textContent.includes('zweryfik'));
    if (!button) return;
    if (root.dataset.missingProcessed !== '1') { button.disabled = true; button.textContent = 'Analizuję brakujące informacje…'; return; }
    const ready = missing.length === 0 || missing.every((li) => li.dataset.accepted === '1');
    if (ready && review && review.textContent.includes('Wymaga')) { review.disabled = false; review.title = ''; review.textContent = 'Oznacz jako zweryfikowaną'; }
    button.disabled = !ready;
    button.title = ready ? '' : 'Najpierw zatwierdź odpowiedzi do brakujących informacji.';
    if (!ready) button.textContent = 'Uzupełnij odpowiedzi przed ofertą';
    else if (button.textContent.includes('Uzupełnij')) button.textContent = 'Przygotuj przykładową ofertę';
    button.style.display = 'none';
    if (ready && button.dataset.offerLoaded === '1' && button.dataset.needsGeneration === '1') {
      if (!button.dataset.autoAttempted) { button.dataset.autoAttempted = '1'; button.click(); }
      else { button.style.display = 'inline-block'; button.textContent = 'Ponów aktualizację oferty'; }
    }
  };
  const processMissingInfo = async (root) => {
    const id = new URL(location.href).searchParams.get('session');
    const panel = root.querySelectorAll('.analysis-item')[1];
    if (!id || !panel) return;
    // Po ponownym wejściu nie blokujemy całego panelu: zapisane odpowiedzi są tylko sprawdzane w tle.
    root.dataset.missingProcessed = '0';
    if (!panel.querySelector('[data-ai-legend]')) {
      const legend = document.createElement('p');
      legend.dataset.aiLegend = '1';
      legend.className = 'muted';
      legend.style.cssText = 'margin:0 0 12px;padding:9px 11px;border:1px solid #303956;border-radius:9px;background:#101522;font-size:.9rem';
      legend.innerHTML = '<span style="color:#e7bd58">Żółte</span> — ustalenie AI &nbsp; <span style="color:#ef7c86">Czerwone</span> — wymaga uwagi &nbsp; <span style="color:#71d69b">Zielone</span> — potwierdzone ręcznie.';
      panel.prepend(legend);
    }
    const render = (li, question, answer, accepted, auto = false) => {
      li.dataset.question = question;
      li.dataset.proposalReady = '1';
      li.dataset.accepted = accepted && !auto ? '1' : '0';
      li.dataset.autoAccepted = auto ? '1' : '0';
      delete li.dataset.generating;
      li.textContent = '';
      li.style.cssText = 'margin:.55rem 0;padding:.65rem .75rem;border-radius:.55rem;border:1px solid ' + (accepted ? (auto ? '#a47d32' : '#277c55') : '#7c303d') + ';background:' + (accepted ? (auto ? '#332a16' : '#123322') : '#35151b');
      const label = document.createElement('span'); label.textContent = question + ': ';
      const value = document.createElement('strong'); value.textContent = answer;
      li.append(label, value);
      if (true) {
        const controls = document.createElement('span'); controls.style.cssText = 'display:inline-flex;gap:6px;margin:9px 0 0 0;vertical-align:middle';
        const confirm = document.createElement('button'); confirm.type = 'button'; confirm.className = 'button'; confirm.textContent = auto ? 'Potwierdź ręcznie' : 'Zatwierdź i zapisz'; confirm.style.cssText = 'border-color:#3eaf73;color:#c8ffe0';
        const alternate = document.createElement('button'); alternate.type = 'button'; alternate.className = 'button'; alternate.textContent = '↻'; alternate.title = 'Wygeneruj inną odpowiedź'; alternate.setAttribute('aria-label', 'Wygeneruj inną odpowiedź');
        const own = document.createElement('button'); own.type = 'button'; own.className = 'button'; own.textContent = 'Własna odpowiedź';
        const setBusy = () => { li.dataset.generating = '1'; value.textContent = 'Generowanie…'; controls.querySelectorAll('button').forEach((button) => button.disabled = true); };
        confirm.onclick = async () => { confirm.disabled = true; try { const response = await fetch('/api/brief-assist.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'confirm', session: id, question, answer }) }); if (!response.ok) throw new Error('Nie udało się zapisać odpowiedzi.'); render(li, question, answer, true, false); await syncOfferCard(); } catch (error) { alert(error.message); confirm.disabled = false; } refreshOfferEligibility(root); };
        alternate.onclick = async () => { setBusy(); try { const response = await fetch('/api/brief-assist.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'regenerate', session: id, question }), signal: AbortSignal.timeout(15000) }); const data = await response.json(); if (!response.ok || !data.proposal) throw new Error('proposal'); render(li, question, data.proposal, false); } catch { render(li, question, 'Nie udało się wygenerować innej propozycji.', false); } refreshOfferEligibility(root); };
        own.onclick = async () => {
          const custom = prompt('Wpisz odpowiedź dla: ' + question, answer);
          if (!custom?.trim()) return;
          const value = custom.trim();
          setBusy();
          try {
            const response = await fetch('/api/brief-assist.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'confirm', session: id, question, answer: value }), signal: AbortSignal.timeout(10000) });
            if (!response.ok) throw new Error('confirm');
            render(li, question, value, true); await syncOfferCard();
          } catch {
            render(li, question, value, false);
          }
          refreshOfferEligibility(root);
        };
        controls.append(confirm, alternate, own); li.append(document.createElement('br'), controls);
      }
    };
    const syncOfferCard = async () => { const generator = root.querySelector('[data-offer-generate]'); if (generator) delete generator.dataset.autoAttempted; const offerRoot = root.querySelector('[data-visible-offer] [data-offer-document]')?.parentElement; if (!offerRoot) return; try { let response = await fetch('/api/offer.php?session=' + encodeURIComponent(id)); let data = await response.json(); const unresolved = [...panel.querySelectorAll('li[data-question]')].some((item) => item.dataset.accepted !== '1'); if (data.offer) renderOfferPreview(offerRoot, data.offer, id); } catch { /* oferta zostanie odświeżona przy kolejnym wejściu */ } };
    for (const li of panel.querySelectorAll('li')) {
      const question = li.dataset.question || li.textContent.trim();
      if (!question || question === 'Brak') continue;
      li.dataset.question = question; li.dataset.proposalReady = '1';
      try {
        const response = await fetch('/api/brief-assist.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ session: id, question, autoConfirm: true }), signal: AbortSignal.timeout(15000) });
        const data = await response.json();
        if (!response.ok || (!data.accepted && !data.proposal)) throw new Error('proposal');
        render(li, question, data.answer || data.proposal, data.accepted === true, data.auto === true);
      } catch { render(li, question, 'Nie udało się przygotować propozycji.', false); }
      refreshOfferEligibility(root);
    }
    root.dataset.missingProcessed = '1';
    refreshOfferEligibility(root);
  };
  const ensureAgreedBeforeConversation = (root) => { const headings = [...root.querySelectorAll('h3')]; const agreed = headings.find((node) => node.textContent.trim() === 'Co ustalono'); const conversation = headings.find((node) => node.textContent.trim() === 'Rozmowa'); if (!agreed || !conversation) return; const grid = agreed.nextElementSibling; const anchor = conversation.parentElement; anchor.parentNode.insertBefore(agreed, anchor); if (grid) anchor.parentNode.insertBefore(grid, anchor); const offer = root.querySelector('[data-visible-offer]'); const analysisSection = root.querySelector('section.analysis'); if (offer && analysisSection && analysisSection.parentNode) analysisSection.parentNode.insertBefore(offer, analysisSection); };
  const ensureRiskFlagsVisible = (root) => { if ([...root.querySelectorAll('h3')].some((node) => node.textContent.trim() === 'Flagi ryzyka')) return; const analysisSection = root.querySelector('section.analysis'); if (!analysisSection || !analysisSection.parentNode) return; const box = document.createElement('section'); box.style.cssText = 'margin:18px 0;padding:14px;border:1px solid #29313c;border-radius:12px;background:#11151d'; const title = document.createElement('h3'); title.textContent = 'Flagi ryzyka'; title.style.margin = '0 0 7px'; const text = document.createElement('p'); text.className = 'muted'; text.textContent = 'Brak wykrytych flag.'; box.append(title, text); analysisSection.parentNode.insertBefore(box, analysisSection); };
  const addWorkflowStatus = (root) => {
    const top = root.querySelector('.topline'); if (!top || top.querySelector('[data-workflow-status]')) return;
    const strip = document.createElement('div'); strip.dataset.workflowStatus = '1'; strip.style.cssText = 'display:flex;gap:7px;flex-wrap:wrap;margin:14px 0 4px';
    const add = (label, state, color) => { const item = document.createElement('span'); item.textContent = label + ': ' + state; item.style.cssText = 'border:1px solid ' + color + ';border-radius:999px;padding:4px 9px;color:' + color + ';font-size:11px'; strip.append(item); };
    const analysis = root.querySelector('section.analysis .status')?.textContent.trim() || 'oczekuje';
    const missing = [...(root.querySelectorAll('.analysis-item')[1]?.querySelectorAll('li[data-question]') || [])];
    const missingState = !missing.length ? 'brak' : missing.every((li) => li.dataset.accepted === '1') ? 'uzupełnione' : 'wymagają uwagi';
    const offerText = [...root.querySelectorAll('[data-offer-document] .muted')].map((node) => node.textContent || '').join(' ');
    const offerState = offerText.includes('nieaktualna') ? 'nieaktualna' : root.querySelector('[data-offer-document]') ? 'gotowa' : 'brak';
    const sentState = offerText.includes('Wysłano') ? 'wysłano' : 'nie wysłano';
    add('Analiza', analysis, analysis.toLowerCase().includes('gotowa') ? '#71d69b' : '#e7bd58'); add('Braki', missingState, missingState === 'uzupełnione' || missingState === 'brak' ? '#71d69b' : '#ef7c86'); add('Oferta', offerState, offerState === 'gotowa' ? '#71d69b' : '#e7bd58'); add('Wysyłka', sentState, sentState === 'wysłano' ? '#71d69b' : '#8995a7');
    top.parentNode.insertBefore(strip, top.nextSibling);
  };
  const refreshRowStatuses = async () => {
    const view = new URL(location.href).searchParams.get('view') || 'active';
    try {
      const response = await fetch(location.pathname + '?view=' + encodeURIComponent(view) + '&_status=' + Date.now(), { headers: { 'X-Requested-With': 'fetch' }, cache: 'no-store' });
      if (!response.ok) return;
      const page = new DOMParser().parseFromString(await response.text(), 'text/html');
      const incoming = new Map([...page.querySelectorAll('aside .row')].map((row) => [new URL(row.href, location.href).searchParams.get('session'), row]));
      rows().forEach((row) => {
        const id = new URL(row.href, location.href).searchParams.get('session'); const next = incoming.get(id); if (!next) return;
        const meta = row.querySelector('.meta'); const nextMeta = next.querySelector('.meta'); if (meta && nextMeta) meta.textContent = nextMeta.textContent;
        row.classList.remove('status-completed', 'status-ready', 'status-interrupted', 'status-active');
        const text = nextMeta?.textContent || ''; row.classList.add(text.includes('Brief przekazany') ? 'status-completed' : text.includes('Gotowe do przekazania') ? 'status-ready' : text.includes('Rozmowa przerwana') ? 'status-interrupted' : 'status-active');
      });
    } catch { /* chwilowy brak odświeżenia nie wpływa na otwartą rozmowę */ }
  };
  const markCurrent = (url) => rows().forEach((row) => row.classList.toggle('current', new URL(row.href).searchParams.get('session') === new URL(url, location.href).searchParams.get('session')));
  const loadDetail = async (url, push = true) => {
    const current = detail();
    if (!current) return location.assign(url);
    markCurrent(url);
    current.classList.add('detail-panel', 'is-loading');
    current.setAttribute('aria-busy', 'true');
    current.innerHTML = '<div class="detail-loader">Ładowanie szczegółów briefu…</div>';
    try {
      const response = await fetch(url, { headers: { 'X-Requested-With': 'fetch' } });
      if (!response.ok) throw new Error('Request failed');
      const page = new DOMParser().parseFromString(await response.text(), 'text/html');
      const next = page.querySelectorAll('section.panel')[1];
      if (!next) throw new Error('Detail missing');
      if (push) history.pushState({}, '', url);
      next.classList.add('detail-panel');
      humaniseLabels(next);
      humaniseQuestionLabel(next);
      addWorkflowStatus(next);
      removeDetailActions(next);
      addRetryStable(next);
      addMainAnalysisButton(next);
      addBriefAssist(next);
      addBriefTimestamp(next);
      addAnalysisControlsStable(next);
      addVisibleOfferButton(next);
      void processMissingInfo(next);
      ensureAgreedBeforeConversation(next);
      ensureRiskFlagsVisible(next);
      current.replaceWith(next);
      markCurrent(url);
    } catch {
      location.assign(url);
    }
  };
  document.addEventListener('click', (event) => { if (event.target.closest('[data-analysis-refresh],[data-main-analysis]')) { const generator = document.querySelector('[data-offer-generate]'); if (generator) { generator.disabled = true; generator.style.display = 'none'; } }
    const row = event.target.closest('aside .row');
    if (!row || new URL(row.href, location.href).pathname === '/api/project-case.php' || event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) return;
    event.preventDefault();
    void loadDetail(row.href);
  });
  window.addEventListener('popstate', () => {
    if (new URL(location.href).searchParams.get('session')) void loadDetail(location.href, false);
  });
  enhanceUi();
  keepSelectedRowFrame();
  removeDetailActions(document);
  addBulkActions();
  markCurrent(location.href);
  humaniseLabels(document);
  humaniseQuestionLabel(document);
  addWorkflowStatus(document);
  addRetryStable(document);
  addMainAnalysisButton(document);
  addBriefAssist(document);
  addBriefTimestamp(document);
  addAnalysisControlsStable(document);
  addVisibleOfferButton(document.documentElement);
  ensureAgreedBeforeConversation(document.documentElement);
  ensureRiskFlagsVisible(document.documentElement);
  void processMissingInfo(document.documentElement);
  // Statusy odświeżamy tylko po świadomej akcji administratora, nie cyklicznie.
  document.addEventListener('click', (event) => { if (event.target.closest('.button,[data-offer-generate],[data-analysis-refresh],[data-main-analysis]')) window.setTimeout(refreshRowStatuses, 1200); });
})();
</script>
HTML;
    $range = (string) ($_GET['range'] ?? 'all');
    $rangeDays = in_array($range, ['7','30','90'], true) ? (int) $range : 0;
    $allForAnalytics = sessions();
    if ($rangeDays > 0) $allForAnalytics = array_values(array_filter($allForAnalytics, static fn($session): bool => (int) ($session['updatedAt'] ?? 0) >= time() - $rangeDays * 86400));
    $analytics = analyticsMetrics($allForAnalytics);
    $rangeLinks = '';
    foreach (['7'=>'7 dni','30'=>'30 dni','90'=>'90 dni','all'=>'Cały okres'] as $value => $label) { $query = ['view' => (string) ($_GET['view'] ?? 'active'), 'range' => $value]; if (!empty($_GET['session'])) $query['session'] = (string) $_GET['session']; $activeClass = $range === $value || ($value === 'all' && $rangeDays === 0) ? ' active' : ''; $rangeLinks .= '<a class="analytics-filter'.$activeClass.'" href="?'.htmlspecialchars(http_build_query($query), ENT_QUOTES, 'UTF-8').'">'.$label.'</a>'; }
    $funnel = [['Rozmowy',$analytics['sessions']],['Briefy przekazane',$analytics['completed']],['Oferty przygotowane',$analytics['offers']],['Oferty zweryfikowane',$analytics['reviewedOffers']],['Oferty wysłane',$analytics['sentOffers']]];
    $funnelMax = max(1, ...array_column($funnel, 1)); $funnelHtml = '';
    foreach ($funnel as [$label, $value]) { $width = (int) round($value / $funnelMax * 100); $funnelHtml .= '<div class="chart-row"><span>'.$label.'</span><div class="chart-track"><i style="width:'.$width.'%"></i></div><b>'.$value.'</b></div>'; }
    $tokenMax = max(1, $analytics['totalInputTokens'], $analytics['totalOutputTokens'], $analytics['totalCachedTokens']);
    $tokenRows = [['Wejściowe',$analytics['totalInputTokens']],['Wyjściowe',$analytics['totalOutputTokens']],['Z cache',$analytics['totalCachedTokens']]]; $tokenHtml = '';
    foreach ($tokenRows as [$label, $value]) { $width = (int) round($value / $tokenMax * 100); $tokenHtml .= '<div class="chart-row"><span>'.$label.'</span><div class="chart-track"><i style="width:'.$width.'%"></i></div><b>'.number_format($value,0,',',' ').'</b></div>'; }
    $analyticsHtml = '<section class="panel analytics-panel" aria-labelledby="analytics-title"><div class="analytics-heading"><div><h2 id="analytics-title">Analityka panelu</h2><p class="muted">Zestawienie wszystkich rozmów zawierających wiadomość klienta.</p></div><time class="muted">Stan na '.date('Y-m-d H:i').'</time></div><div class="analytics-filters" aria-label="Zakres analizy">'.$rangeLinks.'</div><div class="analytics-grid">'
        .'<div class="analytics-card"><b>'.$analytics['sessions'].'</b><span>rozmów</span><small>'.$analytics['active'].' aktywnych</small></div>'
        .'<div class="analytics-card"><b>'.$analytics['avgTokens'].'</b><span>średnio tokenów / rozmowę</span><small>'.$analytics['totalTokens'].' łącznie</small></div>'
        .'<div class="analytics-card"><b>'.$analytics['avgTokensPerTurn'].'</b><span>średnio tokenów / turę</span><small>'.$analytics['totalTurns'].' tur AI</small></div>'
        .'<div class="analytics-card"><b>'.number_format($analytics['avgMessages'],1,',',' ').'</b><span>wiadomości klienta / rozmowę</span><small>'.$analytics['userMessages'].' wiadomości łącznie</small></div>'
        .'<div class="analytics-card"><b>'.$analytics['briefConversion'].'%</b><span>rozmów zakończonych briefem</span><small>'.$analytics['completed'].' przekazanych</small></div>'
        .'<div class="analytics-card"><b>'.$analytics['sentOffers'].'</b><span>ofert wysłanych</span><small>'.$analytics['reviewedOffers'].' zweryfikowanych · '.$analytics['offers'].' przygotowanych</small></div>'
        .'</div><div class="analytics-charts"><div class="chart-card"><h3>Lejek obsługi</h3>'.$funnelHtml.'</div><div class="chart-card"><h3>Zużycie tokenów</h3>'.$tokenHtml.'</div></div><div class="analytics-foot"><span>Gotowych do podsumowania: <strong>'.$analytics['ready'].'</strong></span><span>Konwersja do oferty: <strong>'.$analytics['offerConversion'].'%</strong></span><span>Konwersja do wysyłki: <strong>'.$analytics['sentConversion'].'%</strong></span><span>Tokeny z cache: <strong>'.$analytics['totalCachedTokens'].'</strong></span></div></section>';
    $html = ob_get_clean();
    $contractSettingsActive = (($_GET['view'] ?? '') === 'contract-settings');
    if ($contractSettingsActive) {
        $html = str_replace('<a class="active" href="?view=active">', '<a class="" href="?view=active">', $html);
    }
    $html = str_replace('</nav>', '<a'.($contractSettingsActive ? ' class="active" aria-current="page"' : '').' href="?view=contract-settings">Ustawienia wzorów umów</a></nav>', $html);
    $html = str_replace('<nav class="nav">', $analyticsHtml.'<nav class="nav">', $html);
    if (($_GET['view'] ?? '') === 'contract-settings') {
        $template = contractTemplate();
        $settings = '<section class="panel"><h2>Ustawienia wzorów umów</h2><p class="muted">Baza do przygotowania umów aplikacji i stron. Wersja '.(int)$template['version'].'. Zmiany dotyczą nowych projektów umów; zapisane dokumenty zachowują swoją treść. Treść startowa wymaga uzupełnienia i oceny prawnej dla konkretnej transakcji.</p><form class="contract-form" method="post" action="/api/contract.php"><input type="hidden" name="action" value="save-template"><input type="hidden" name="csrf" value="'.contractEscape(contractToken()).'"><input type="hidden" name="expectedVersion" value="'.(int)$template['version'].'">';
        foreach (contractFields() as $key=>$label) {
            if ($key==='provider' || !array_key_exists($key, contractTemplateDefaults())) continue;
            $settings .= '<label>'.contractEscape($label).'<textarea rows="5" maxlength="20000" name="'.$key.'">'.contractEscape($template[$key]).'</textarea></label>';
        }
        foreach (['defaultTransferTerms'=>'Domyślny moment przeniesienia praw', 'defaultIpPayment'=>'Domyślne wynagrodzenie za prawa IP — w cenie oferty'] as $key=>$label) {
            $settings .= '<label>'.contractEscape($label).'<textarea rows="3" maxlength="2000" required name="'.$key.'">'.contractEscape($template[$key]).'</textarea></label>';
        }
        $settings .= (isset($_GET['saved']) ? '<p role="status">Wzór został zapisany.</p>' : '').'<button class="button">Zapisz wzór</button></form></section>';
        $html = preg_replace('~<div class="layout">.*</main>~s', contractProfileForm().$settings.'</main>', $html);
    }
    $html = str_replace('</head>', '<style>.conversation-contract{margin:22px 0;padding:18px;border:1px solid #5368c7;border-radius:12px;background:#111831}.contract-form{display:grid;gap:14px;margin-top:16px}.contract-form label{display:grid;gap:6px}.contract-form textarea,.contract-form input:not([type="hidden"]),.contract-form select{box-sizing:border-box;width:100%;min-width:0;background:#0d1220;color:#e1e7fa;border:1px solid #465474;border-radius:7px;padding:10px;font:inherit;resize:vertical}.conversation-contract details{margin-top:12px}.conversation-contract summary{cursor:pointer}.contract-form button{margin-top:6px}</style></head>', $html);
    $html = str_replace('</head>', '<style>.contract-form [hidden]{display:none}.contract-form [data-review-field]{padding:14px;border:1px solid #465474;border-radius:10px;background:#101725}.contract-form [data-review-field][data-accepted="1"]{border-color:#3eaf73}.contract-form [data-review-status]{display:inline-block;margin:10px 12px 0 0;color:#d5c48b}.contract-form [data-review-field][data-accepted="1"] [data-review-status]{color:#88d7ab}.contract-form [data-review-field] button{margin-right:8px}.contract-form [data-review-preview]{overflow-wrap:anywhere}</style></head>', $html);
    $html = str_replace('</head>', '<meta name="admin-csrf" content="'.contractEscape(contractToken()).'">'.adminShellStyles().'</head>', $html);
    $html = str_replace('<body>', '<body>'.adminShellHeader('briefs'), $html);
    echo apiRewritePaths(str_replace('</body>', '<script src="/api/contract-review.js"></script>'.$script . '</body>', $html === false ? '' : $html));
});
function statusLabel(string $status,int $updatedAt=0):string{if(in_array($status,['STARTED','DISCOVERY','NEEDS_INFORMATION'],true)&&$updatedAt>0&&$updatedAt<time()-86400)return'Rozmowa przerwana';return match($status){'STARTED'=>'Nowa rozmowa','DISCOVERY'=>'W trakcie rozmowy','NEEDS_INFORMATION'=>'Czekamy na informacje','READY_FOR_SUMMARY'=>'Gotowe do przekazania','COMPLETED'=>'Brief przekazany','CLOSED'=>'Rozmowa zakończona',default=>$status?:'Nowa rozmowa'};}
function riskLabel(string $risk): string { return match($risk){'PERSONAL_DATA'=>'Dane osobowe','SENSITIVE_DATA'=>'Dane wrażliwe','PAYMENTS'=>'Płatności online','MEDICAL'=>'Dane medyczne','FINANCIAL'=>'Dane finansowe','LEGAL'=>'Wymogi prawne','HIGH_SECURITY'=>'Podwyższone wymagania bezpieczeństwa','EXTERNAL_INTEGRATION'=>'Połączenie z zewnętrznymi usługami','DATA_MIGRATION'=>'Przeniesienie danych','LARGE_SCALE'=>'Duża skala rozwiązania','UNCLEAR_SCOPE'=>'Zakres wymaga doprecyzowania','UNREALISTIC_BUDGET'=>'Budżet może wymagać weryfikacji','UNREALISTIC_DEADLINE'=>'Termin może wymagać weryfikacji',default=>$risk}; }
function analysisStatusLabel(string $status): string { return match($status){'COMPLETED'=>'Analiza gotowa','WAITING'=>'Oczekuje na przekazanie briefu','NOT_RUN'=>'Analiza niedostępna','FAILED'=>'Analiza wymaga ponowienia',default=>$status}; }
function readinessLabel(string $readiness): string { return match($readiness){'READY'=>'Gotowy do przygotowania oferty','NEEDS_CLARIFICATION'=>'Wymaga doprecyzowania','NOT_A_FIT'=>'Poza zakresem usług',default=>$readiness?:'Do sprawdzenia'}; }
function analysisForDisplay(array $session):array{if(is_array($session['internalAnalysis']??null))return $session['internalAnalysis'];return['status'=>($session['status']??'')==='COMPLETED'?'NOT_RUN':'WAITING','message'=>($session['status']??'')==='COMPLETED'?'Ten brief został przekazany przed dodaniem analizy wewnętrznej.':'Analiza uruchomi się automatycznie po przekazaniu briefu zespołowi.'];}

if($_SERVER['REQUEST_METHOD']==='POST') {
    if(!hash_equals(contractToken(),(string)($_POST['csrf']??''))) { http_response_code(403); exit('Sesja formularza wygasła. Odśwież panel.'); }
    $id=(string)($_POST['id']??''); $action=(string)($_POST['action']??'');
    if(!preg_match('/^[a-f0-9]{32}$/',$id) || !in_array($action,['archive','restore','delete'],true)) { http_response_code(400); exit('Niepoprawna operacja.'); }
    try {
        if($action==='delete') {
            if(!deleteProjectSession($id)) { http_response_code(404); exit('Nie znaleziono sprawy.'); }
        } else {
            $lock=fopen(__DIR__.'/storage/'.$id.'.lock','c');
            if($lock===false || !flock($lock,LOCK_EX|LOCK_NB)) { if(is_resource($lock)) fclose($lock); throw new DomainException('Sprawa jest teraz przetwarzana.'); }
            try {
                importLegacySessions();
                $session=readSession($id);
                if(!$session) { http_response_code(404); exit('Nie znaleziono sprawy.'); }
                if($action==='archive') $session['archivedAt']=time(); else unset($session['archivedAt']);
                save($session);
            } finally { flock($lock,LOCK_UN); fclose($lock); }
        }
    } catch(DomainException $error) { http_response_code(409); exit(contractEscape($error->getMessage())); }
      catch(Throwable $error) { error_log('Admin case operation: '.$error->getMessage()); http_response_code(500); exit('Nie udało się wykonać operacji.'); }
    $view=(string)($_POST['view']??'active');
    if(!in_array($view,['active','archive','all'],true)) $view='active';
    header('Location: admin.php?view='.urlencode($view),true,303); exit;
}
$requestedView=(string)($_GET['view']??'active');$view=in_array($requestedView,['active','archive','all'],true)?$requestedView:'active';$all=array_values(array_filter(sessions(),'hasClientMessage'));$list=array_values(array_filter($all,static fn($s)=>$view==='all'||($view==='archive'?!empty($s['archivedAt']):empty($s['archivedAt']))));$selectedId=preg_replace('/[^a-f0-9]/','',(string)($_GET['session']??''));$selected=null;foreach($all as $item)if(($item['id']??'')===$selectedId)$selected=$item;$analysis=$selected?analysisForDisplay($selected):null;foreach($list as &$item)$item['status']=statusLabel((string)($item['status']??''),(int)($item['updatedAt']??0));unset($item);if($selected)$selected['status']=statusLabel((string)($selected['status']??''),(int)($selected['updatedAt']??0));echo '<style>.topline{align-items:flex-start}.topline>.status{align-self:flex-start}.analysis-grid>.analysis-item:nth-child(2) li:not([data-proposal-ready])::before{content:"\\21bb  Generuj\\0119 propozycj\\0119 AI\\2026";display:inline-block;margin-right:7px;color:#9daaff;animation:spin .8s linear infinite}.analysis-grid>.analysis-item:nth-child(2) li:not([data-proposal-ready]){color:#bfc9ef}</style>';
echo '<style>.analysis-grid>.analysis-item:nth-child(2) li:not([data-proposal-ready])::before{content:"\\21bb"}.analysis-grid>.analysis-item:nth-child(2) li:not([data-proposal-ready])::after{content:"Generuj\\0119 propozycj\\0119 AI\\2026";position:absolute;z-index:2;left:22px;bottom:calc(100% + 7px);width:max-content;max-width:250px;padding:7px 9px;border:1px solid #4d5fbd;border-radius:8px;background:#151a24;color:#dce3ff;box-shadow:0 10px 24px rgba(0,0,0,.32);opacity:0;pointer-events:none;transform:translateY(3px);transition:opacity .15s ease,transform .15s ease}.analysis-grid>.analysis-item:nth-child(2) li:not([data-proposal-ready]){position:relative;cursor:help}.analysis-grid>.analysis-item:nth-child(2) li:not([data-proposal-ready]):hover::after{opacity:1;transform:translateY(0)}</style>';
echo '<style>.row.status-completed{border-left:3px solid #58c98a;background:linear-gradient(90deg,#10261b,transparent)}.row.status-ready{border-left:3px solid #6f83ff;background:linear-gradient(90deg,#18204a,transparent)}.row.status-active{border-left:3px solid #d7a83e;background:linear-gradient(90deg,#2b250f,transparent)}.row.status-interrupted{border-left:3px solid #ef7c86;background:linear-gradient(90deg,#32171b,transparent)}</style>';
?><!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Panel briefów</title><style>
:root{color-scheme:dark}*{box-sizing:border-box}body{margin:0;background:#08090d;color:#edf0f5;font:15px/1.55 system-ui,sans-serif}main{max-width:1440px;margin:auto;padding:34px 22px}h1{font-size:28px;letter-spacing:-.03em;margin:0}.lead,.muted,.meta{color:#8995a7}.lead{margin:4px 0 22px}.nav,.actions,.tags,.metrics{display:flex;gap:8px;flex-wrap:wrap}.nav{margin-bottom:20px}.nav a,.button{border:1px solid #354052;border-radius:9px;padding:8px 12px;color:#cbd4e9;background:#111622;text-decoration:none;cursor:pointer;font:13px system-ui}.nav a.active{background:#263271;border-color:#7181f1;color:#fff}.layout{display:grid;grid-template-columns:370px minmax(0,1fr);gap:20px}.panel{border:1px solid #29313c;border-radius:16px;background:#0f1218;padding:18px}.row{display:block;border-bottom:1px solid #222936;padding:15px 2px;color:inherit;text-decoration:none;transition:background .16s ease,color .16s ease}.row:last-child{border:0}.row-title{display:block;font-weight:700}.row:hover .row-title,.row.current .row-title{color:#bdc6ff}.row.current{background:linear-gradient(90deg,rgba(74,92,203,.16),transparent)}.meta{display:block;margin-top:6px;font-size:12px}.topline{display:flex;justify-content:space-between;gap:12px}.topline h2{margin:0}.status,.tag{border:1px solid #4d5fbd;border-radius:999px;padding:3px 8px;color:#cbd4ff;font:11px ui-monospace,monospace}.metric{min-width:106px;border:1px solid #29313c;border-radius:10px;padding:9px 11px;background:#11151d}.metric b{display:block;font-size:16px}.metric small{color:#8490a1}.actions{margin:16px 0}.danger{border-color:#884955;color:#ffbdc4}.brief-grid,.analysis-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.brief-section{border:1px solid #29313c;border-radius:12px;background:#11151d;padding:13px}.brief-section h4,.analysis-item h4{margin:0 0 7px;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#aebaff}.brief-section p{margin:4px 0;color:#d6dce6}.analysis{margin-top:22px;border:1px solid #4050a5;border-radius:14px;background:linear-gradient(135deg,#11162b,#0f131b);padding:17px}.analysis-head{display:flex;justify-content:space-between;gap:10px}.analysis-head h3{margin:0}.analysis-summary{color:#d8dff1}.analysis-item{border-top:1px solid #303956;padding-top:10px}.analysis-item ul{margin:0;padding-left:18px;color:#d3d9e4}.message{border-left:3px solid #5e70e9;padding:11px 13px;margin:10px 0;background:#141925;border-radius:0 10px 10px 0}.message.user{border-color:#7484ff;background:#19214a}.message b{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#bec8ff}.detail-panel{transition:opacity .16s ease,transform .16s ease}.detail-panel.is-loading{opacity:.58;transform:translateY(2px);pointer-events:none}.detail-loader{display:flex;align-items:center;gap:10px;min-height:180px;color:#aebaff}.detail-loader::before{content:'';width:18px;height:18px;border:2px solid #3c4a87;border-top-color:#aebaff;border-radius:50%;animation:spin .7s linear infinite}@keyframes spin{to{transform:rotate(1turn)}}@media(max-width:900px){.layout,.brief-grid,.analysis-grid{grid-template-columns:1fr}}@media(max-width:560px){main{padding:24px 14px}.topline{display:block}.status{display:inline-block;margin-top:8px}}
</style></head><body><main><h1>AI Analityk — panel briefów</h1><p class="lead">Rozmowy klientów, uporządkowane briefy i analiza wewnętrzna dla zespołu.</p><nav class="nav"><a class="<?= $view==='active'?'active':'' ?>" href="?view=active">Aktywne</a><a class="<?= $view==='archive'?'active':'' ?>" href="?view=archive">Archiwum</a><a class="<?= $view==='all'?'active':'' ?>" href="?view=all">Wszystkie</a></nav><div class="layout"><aside class="panel"><strong>Rozmowy (<?=count($list)?>)</strong><?php foreach($list as $s):$m=$s['metrics']??[];$title=(string)($s['projectState']['businessProblem']??'Nieustalony problem');?><a class="row" href="?view=<?=esc($view)?>&session=<?=esc($s['id'])?>"><span class="row-title"><?=esc(mb_strimwidth($title,0,90,'…'))?></span><span class="meta"><?=esc($s['status']??'STARTED')?> · <?=date('Y-m-d H:i',$s['updatedAt']??time())?> · <?=(int)($m['inputTokens']??0)+(int)($m['outputTokens']??0)?> tokenów</span></a><?php endforeach?></aside><section class="panel"><?php if(!$selected):?><p class="muted">Wybierz rozmowę po lewej stronie.</p><?php else:$m=$selected['metrics']??[];$sections=$selected['summary']['sections']??[];?><div class="topline"><div><h2>Brief i szczegóły</h2><p class="muted">Ostatnia aktualizacja: <?=date('Y-m-d H:i',$selected['updatedAt']??time())?></p></div><span class="status"><?=esc($selected['status']??'')?></span></div><div class="metrics"><span class="metric"><b><?=(int)($m['turns']??0)?></b><small>tury AI</small></span><span class="metric"><b><?=(int)($m['inputTokens']??0)?></b><small>tokeny wejściowe</small></span><span class="metric"><b><?=(int)($m['outputTokens']??0)?></b><small>tokeny wyjściowe</small></span></div><div class="actions"><form method="post"><input type="hidden" name="id" value="<?=esc($selected['id'])?>"><input type="hidden" name="view" value="<?=esc($view)?>"><input type="hidden" name="csrf" value="<?=esc(contractToken())?>"><button class="button" name="action" value="<?=empty($selected['archivedAt'])?'archive':'restore'?>"><?=empty($selected['archivedAt'])?'Archiwizuj':'Przywróć'?></button></form><form method="post" onsubmit="return confirm('Usunąć trwale rozmowę, brief i analizę?');"><input type="hidden" name="id" value="<?=esc($selected['id'])?>"><input type="hidden" name="view" value="<?=esc($view)?>"><input type="hidden" name="csrf" value="<?=esc(contractToken())?>"><button class="button danger" name="action" value="delete">Usuń trwale</button></form></div><h3>Co ustalono</h3><div class="brief-grid"><?php foreach($sections as $section):$items=is_array($section['content']??null)?$section['content']:[];?><article class="brief-section"><h4><?=esc($section['title']??'Informacja')?></h4><?php foreach($items as $item):?><p><?=esc($item)?></p><?php endforeach;if(!$items):?><p class="muted">Do ustalenia</p><?php endif?></article><?php endforeach?></div><h3 style="margin-top:22px">Flagi ryzyka</h3><div class="tags"><?php foreach(($selected['riskFlags']??[])as $flag):?><span class="tag"><?=esc($flag)?></span><?php endforeach;if(empty($selected['riskFlags'])):?><span class="muted">Brak wykrytych flag.</span><?php endif?></div><section class="analysis"><div class="analysis-head"><h3>Analiza wewnętrzna AI</h3><span class="status"><?=esc($analysis['status']??'WAITING')?></span></div><?php if(isset($analysis['message'])):?><p class="analysis-summary"><?=esc($analysis['message'])?></p><?php else:?><p class="analysis-summary"><?=esc($analysis['summary']??'')?></p><div class="analysis-grid"><div class="analysis-item"><h4>Gotowość briefu</h4><p><?=esc($analysis['readiness']??'—')?></p></div><?php foreach(['missingInformation'=>'Brakujące informacje','risks'=>'Ryzyka','recommendedScope'=>'Rekomendowany zakres','optionalScope'=>'Opcjonalnie później','questionsForClient'=>'Pytania do klienta']as $key=>$label):?><div class="analysis-item"><h4><?=$label?></h4><ul><?php foreach(($analysis[$key]??[])as $item):?><li><?=esc($item)?></li><?php endforeach;if(empty($analysis[$key])):?><li>Brak</li><?php endif?></ul></div><?php endforeach?><div class="analysis-item"><h4>Następny krok</h4><p><?=esc($analysis['nextStep']??'Do ustalenia')?></p></div></div><?php endif?></section><section><h3 style="margin-top:22px">Rozmowa</h3><?php foreach(($selected['messages']??[])as $msg):?><div class="message <?=esc($msg['role']??'')?>"><b><?=($msg['role']??'')==='user'?'Klient':'AI Analityk'?></b><br><?=nl2br(esc($msg['content']??''))?></div><?php endforeach?></section><?php endif?></section></div></main></body></html>
