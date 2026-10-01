(() => {
  const id = window.PROJECT_SESSION;
  const apiBase = location.pathname.startsWith("/api/") ? "/api" : "";
  const apiPath = (path) => `${apiBase}/${String(path).replace(/^\/+/, "")}`;
  const endpoint = `${apiPath("project-api.php")}?session=${encodeURIComponent(id)}`;
  const labels = { locked: "Niedostępny", ready: "Gotowy do startu", queued: "W kolejce", working: "Pracuje", needs_you: "Twoja decyzja", waiting_client: "Czeka na klienta", review: "Do przeglądu", done: "Zakończony", error: "Błąd" };
  const $ = (selector) => document.querySelector(selector);
  const escapeHtml = (value) => String(value ?? "").replace(/[&<>"']/g, (character) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[character]);
  const date = (seconds) => seconds ? new Date(Number(seconds) * 1000).toLocaleString("pl-PL") : "—";
  const pln = (value) => new Intl.NumberFormat("pl-PL", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value) || 0);
  let project;
  let csrf;
  let defaults = {};
  let lastSnapshot = "";
  let currentStage;
  let zoom = 1;
  let previousFocus;
  let previousStatus = null;
  let refreshing = false;
  const mapShell = $("#map-shell");
  const mapBoard = $("#map-board");
  const mapHint = document.createElement("div");
  mapHint.className = "map-hint";
  mapHint.textContent = "Kółko myszy przewija mapę poziomo";
  const mapSizer = document.createElement("div");
  mapSizer.style.cssText = "position:relative;width:3390px;height:455px";
  mapBoard.replaceWith(mapSizer);
  mapSizer.append(mapBoard);
  mapBoard.style.position = "absolute";
  const mapWrap = document.createElement("div");
  mapWrap.className = "map-wrap";
  mapShell.replaceWith(mapWrap);
  mapWrap.append(mapShell);
  mapWrap.append(mapHint);
  const minimap = document.createElement("button");
  minimap.type = "button";
  minimap.className = "minimap";
  minimap.setAttribute("aria-label", "Minimapa, kliknij aby przesunąć mapę");
  minimap.innerHTML = '<svg viewBox="0 0 3390 455" preserveAspectRatio="none"><g id="mini-nodes"></g><rect id="mini-viewport" y="0" height="455" fill="#89b0ff33" stroke="#a6c6ff" stroke-width="18"/></svg>';
  mapWrap.append(minimap);
  const miniStyle = document.createElement("style");
  miniStyle.textContent = '.map-wrap{position:relative}.node.review{border-color:#c7a477;background:#3d322b}.map-hint{position:absolute;top:12px;right:13px;z-index:2;padding:6px 9px;border:1px solid #354969;border-radius:8px;background:#0b1524df;color:#aebfda;font-size:11px;pointer-events:none}.minimap{position:absolute;right:13px;bottom:13px;width:235px;height:72px;padding:5px;border:1px solid #52709d;border-radius:10px;background:#0b1524ed;box-shadow:0 8px 26px #0009;cursor:crosshair}.minimap svg{width:100%;height:100%}.minimap:hover,.minimap:focus-visible{border-color:#afc7ff;outline:0}@media(max-width:600px){.minimap{width:160px;height:53px}.map-hint{font-size:10px;right:9px;top:9px}}';
  document.head.append(miniStyle);

  function modalFormKey(form) {
    const identity = [...form.querySelectorAll('input[type="hidden"][name]')]
      .map((input) => `${input.name}=${input.value}`)
      .join("&");
    return `${form.dataset.action || ""}|${identity}`;
  }

  function captureModalForms() {
    return [...document.querySelectorAll("#dialog-content form[data-action]")].map((form) => ({
      key: modalFormKey(form),
      controls: [...form.querySelectorAll("input[name], textarea[name], select[name]")].map((control) => ({
        name: control.name,
        type: control.type,
        value: control.value,
        checked: "checked" in control ? control.checked : undefined,
      })),
    }));
  }

  function restoreModalForms(states) {
    for (const state of states || []) {
      const form = [...document.querySelectorAll("#dialog-content form[data-action]")]
        .find((candidate) => modalFormKey(candidate) === state.key);
      if (!form) continue;
      for (const saved of state.controls) {
        const control = [...form.querySelectorAll("[name]")]
          .find((candidate) => candidate.name === saved.name && candidate.type === saved.type);
        if (!control) continue;
        if (saved.type === "checkbox" || saved.type === "radio") control.checked = Boolean(saved.checked);
        else control.value = saved.value;
      }
    }
  }

  async function load(keepModal = false) {
    if (refreshing) return;
    refreshing = true;
    const formStates = keepModal ? captureModalForms() : [];
    $("#live-state").classList.add("syncing");
    try {
      const response = await fetch(endpoint, { credentials: "same-origin", cache: "no-store" });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || "Nie udało się pobrać sprawy.");
      const nextDefaults = data.defaults || {};
      const snapshot = JSON.stringify({ project: data.project, defaults: nextDefaults });
      const changed = snapshot !== lastSnapshot;
      project = data.project;
      defaults = nextDefaults;
      csrf = data.csrf || csrf;
      if (changed) {
        render();
        lastSnapshot = snapshot;
      }
      $("#last-updated").textContent = `Aktualizacja ${new Date().toLocaleTimeString("pl-PL", { hour: "2-digit", minute: "2-digit", second: "2-digit" })} · odświeżanie co 5 s`;
      if (changed && keepModal && currentStage) {
        openStage(currentStage, false);
        restoreModalForms(formStates);
      }
    } finally {
      refreshing = false;
      $("#live-state").classList.remove("syncing");
    }
  }

  const stageTasks = {
    plan: ["plan"], repository: ["repository"], environment: ["environment"],
    design: ["ux", "ui"], build: ["architect", "frontend", "backend", "integration"],
    qa: ["qa", "security", "performance", "documentation"], preview: ["preview"],
    feedback: ["feedback"], release: ["release"], handover: ["handover"],
  };
  const stageJobs = {
    plan: ["generate_plan"], repository: ["create_repository"], environment: ["provision_preview", "configure_scaffold"],
    preview: ["publish_preview"], release: ["publish_production"],
  };
  function stageIssue(stageId) {
    const job = project.jobs.find((item) => (stageJobs[stageId] || []).some((kind) => item.kind === kind || (kind === "publish_preview" && /^publish_preview_[a-f0-9]{12}$/.test(item.kind))));
    if (job?.error) return job.error;
    const roles = stageTasks[stageId] || [];
    const task = project.agentTasks.find((item) => roles.includes(item.role) && item.error);
    return task?.error || "Sprawdź szczegóły tego etapu.";
  }
  function overallStatus() {
    const order = ["error", "needs_you", "working", "queued", "review", "ready", "waiting_client", "done"];
    let stage = null;
    let state = "locked";
    for (const candidate of order) {
      stage = project.stages.find((item) => project.status[item.id] === candidate);
      if (stage) { state = candidate; break; }
    }
    stage ||= project.stages.at(-1);
    let detail = "";
    if (state === "error") detail = stageIssue(stage.id);
    else if (state === "working") {
      const roles = stageTasks[stage.id] || [];
      const task = project.agentTasks.find((item) => roles.includes(item.role) && item.state === "running");
      detail = task ? `${task.title}${task.runner_phase ? ` · ${phaseLabel(task.runner_phase)}` : ""}` : "Agent wykonuje zadanie. Szczegóły i postęp znajdziesz po kliknięciu etapu.";
    } else if (state === "queued") detail = "Etap czeka w kolejce na rozpoczęcie pracy.";
    else if (state === "needs_you") detail = "Ten etap wymaga Twojej decyzji; inne niezależne zadania mogą nadal trwać.";
    else if (state === "waiting_client") detail = "Dalszy krok należy do klienta.";
    else if (state === "review") detail = "Wynik czeka na sprawdzenie.";
    else if (state === "ready") detail = "Wszystkie zależności są gotowe. Etap może się rozpocząć.";
    else if (state === "done") detail = "Wszystkie widoczne etapy projektu są zakończone.";
    const titles = { error: "Praca zatrzymana · błąd", needs_you: "Oczekuje na Twoją decyzję", working: "Projekt jest realizowany", queued: "Następny etap czeka w kolejce", review: "Wynik wymaga przeglądu", ready: "Gotowy do rozpoczęcia", waiting_client: "Oczekuje na klienta", done: "Projekt zakończony", locked: "Projekt jeszcze się nie rozpoczął" };
    return { stage, state, title: titles[state] || "Projekt oczekuje", detail };
  }
  function phaseLabel(phase) {
    return ({ waiting_merge: "PR czeka na scalenie", reviewing: "trwa niezależny przegląd", waiting_merge_auto: "PR czeka na CI i scalenie", waiting_image: "oczekuje na obraz z CI" })[phase] || "agent pracuje";
  }

  function render() {
    $("#side-name").textContent = project.name;
    $("#side-client").textContent = project.client;
    $("#summary").textContent = `${project.client} · aktualizacja ${date(project.updatedAt)}`;
    const stageList = $("#side-stages");
    stageList.innerHTML = "";
    const nodes = $("#nodes");
    nodes.innerHTML = "";
    const overview = overallStatus();
    const statusBox = $("#overall-status");
    statusBox.dataset.state = overview.state;
    $("#overall-title").textContent = `${overview.title} · ${overview.stage.name}`;
    $("#overall-detail").textContent = overview.detail;
    const byId = Object.fromEntries(project.stages.map((stage) => [stage.id, stage]));
    const paths = [];
    project.stages.forEach((stage, index) => {
      const state = project.status[stage.id] || "locked";
      const side = document.createElement("button");
      side.type = "button";
      side.className = "side-stage";
      side.innerHTML = `<i class="dot ${state}"></i><span>${escapeHtml(stage.name)}</span>`;
      side.addEventListener("click", () => openStage(stage.id));
      stageList.append(side);
      const node = document.createElement("button");
      node.type = "button";
      const changed = previousStatus && previousStatus[stage.id] !== state;
      node.className = `node ${state}${changed ? " changed" : ""}`;
      node.style.left = `${stage.x}px`;
      node.style.top = `${stage.y}px`;
      node.dataset.stage = stage.id;
      node.setAttribute("aria-label", `${stage.name}: ${labels[state]}`);
      node.innerHTML = `<span class="num">ETAP ${String(index + 1).padStart(2, "0")}</span><strong>${escapeHtml(stage.name)}</strong><small>${escapeHtml(stage.agent)}</small><span class="badge">${labels[state]}</span>`;
      node.addEventListener("click", () => openStage(stage.id));
      nodes.append(node);
      stage.next.forEach((targetId) => {
        const target = byId[targetId];
        if (!target) return;
        const x1 = stage.x + 196, y1 = stage.y + 58, x2 = target.x, y2 = target.y + 58;
        const mid = Math.max(30, (x2 - x1) / 2);
        paths.push(`<path d="M${x1} ${y1} C${x1 + mid} ${y1},${x2 - mid} ${y2},${x2} ${y2}" fill="none" stroke="#4e6286" stroke-width="2" marker-end="url(#arrow)"/>`);
      });
    });
    previousStatus = { ...project.status };
    paths.push('<path d="M2770 315 C2780 425,2050 435,2050 390" fill="none" stroke="#b973ad" stroke-width="2" stroke-dasharray="7 7" marker-end="url(#loopArrow)"/>');
    $("#connections").innerHTML = `<defs><marker id="arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto"><path d="M0 0 L10 5 L0 10 Z" fill="#7189b0"/></marker><marker id="loopArrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto"><path d="M0 0 L10 5 L0 10 Z" fill="#b973ad"/></marker></defs>${paths.join("")}`;
    $("#mini-nodes").innerHTML = project.stages.map((stage) => `<rect x="${stage.x}" y="${stage.y}" width="196" height="118" rx="20" fill="${project.status[stage.id] === "done" ? "#46d99a" : project.status[stage.id] === "needs_you" ? "#ffbc63" : "#627da9"}"/>`).join("");
    updateMinimap();
    const active = project.stages.find((stage) => ["error", "needs_you", "working", "queued", "review", "ready"].includes(project.status[stage.id])) || project.stages.at(-1);
    $("#next-action").textContent = active.name;
    $("#next-detail").textContent = labels[project.status[active.id]] || "";
    const repoJob = project.jobs.find((job) => job.kind === "create_repository");
    const repoResult = parseResult(repoJob);
    if (repoResult?.url && /^https:\/\/github\.com\//.test(repoResult.url)) {
      $("#repo-value").innerHTML = `<a href="${escapeHtml(repoResult.url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(repoResult.name || "Otwórz repozytorium")}</a>`;
      $("#repo-detail").textContent = repoResult.branchProtection === "manual_review_required" ? "Prywatne repozytorium. GitHub Free: CI działa, a pull request wymaga ręcznej kontroli i scalenia." : "Prywatne repozytorium projektu.";
    } else {
      $("#repo-value").textContent = repoJob ? labels[project.status.repository] : "Jeszcze nie utworzono";
    }
    const previewJob = project.jobs.find((job) => /^publish_preview(?:_[a-f0-9]{12})?$/.test(job.kind));
    const previewResult = parseResult(previewJob);
    $("#preview-value").textContent = previewResult?.url || "Jeszcze niedostępny";
  }

  function parseResult(job) {
    if (!job?.result) return null;
    try { return JSON.parse(job.result); } catch { return null; }
  }

  function section(title, body, wide = false) {
    return `<section class="detail-section${wide ? " wide" : ""}"><h3>${escapeHtml(title)}</h3>${body}</section>`;
  }
  function lines(items) {
    if (!Array.isArray(items) || !items.length) return '<p>Brak zapisanych informacji.</p>';
    return `<ul>${items.map((item) => `<li>${escapeHtml(item)}</li>`).join("")}</ul>`;
  }
  function adminLink(anchor = "") {
    return `<p><a href="${apiPath("admin.php")}?view=all&amp;session=${encodeURIComponent(id)}${anchor}">Otwórz dokument i edytor w panelu ↗</a></p>`;
  }

  function stageOutput(stageId) {
    const data = project;
    if (stageId === "brief") {
      const blocks = (data.brief?.sections || []).map((entry) => `<h4>${escapeHtml(entry.title)}</h4>${lines(entry.content)}`).join("");
      const chat = (data.messages || []).map((message) => `<div class="event"><strong>${message.role === "user" ? "Klient" : "Agent rozmowy"}</strong><p>${escapeHtml(message.content)}</p></div>`).join("");
      return section("Przekazany brief", blocks || "<p>Brak sekcji briefu.</p>", true) + section("Rozmowa", chat || "<p>Brak wiadomości.</p>", true);
    }
    if (stageId === "analysis") {
      const a = data.analysis || {};
      return section("Podsumowanie", `<p>${escapeHtml(a.summary || a.message || "Analiza jeszcze nie powstała.")}</p>`, true) + section("Brakujące informacje", lines(a.missingInformation)) + section("Ryzyka", lines(a.risks)) + section("Zalecany zakres", lines(a.recommendedScope)) + section("Pytania do klienta", lines(a.questionsForClient)) + section("Pełna analiza", adminLink(), true);
    }
    if (stageId === "offer") return section("Oferta", `<p>Stan: ${escapeHtml(data.offer.status || "brak")} · wersja ${escapeHtml(data.offer.version || "—")}</p>${adminLink("#offer-panel")}`, true);
    if (stageId === "contract") {
      const c = data.contract;
      const signed = Number(data.case.contractSignedVersion || 0) === Number(c.version || 0) && Number(c.version || 0) > 0;
      let html = section("Dokument", `<p>Numer: ${escapeHtml(c.number || "—")}; wersja: ${escapeHtml(c.version || "—")}; stan wysyłki: ${escapeHtml(c.status || "brak")}.</p><p>Potwierdzenie zawarcia: ${signed ? "tak" : "nie"}. Wysłanie dokumentu nie potwierdza zawarcia umowy.</p>${adminLink("#contract-panel")}`, true);
      if (c.hasPdf && !signed) html += section("Potwierdź zawarcie", `<form class="form" data-action="confirm_contract"><label>Podstawa potwierdzenia, data i sposób podpisania<textarea name="evidence" rows="3" required minlength="8" maxlength="500" placeholder="Np. podpisana umowa v2 z dnia …, dokument sprawdzony …"></textarea></label><button class="primary">Potwierdź aktualną wersję</button></form>`, true);
      if (signed) html += section("Zapisane potwierdzenie", `<p>${escapeHtml(data.case.contractEvidence || "")}</p><p>${date(data.case.contractConfirmedAt)}</p>`, true);
      return html;
    }
    if (stageId === "kickoff") {
      if (data.case.startedAt) return section("Zatwierdzony start", `<p>Data: ${date(data.case.startedAt)}</p><p>Opiekun: ${escapeHtml(data.case.owner)}</p><p>Limit kosztów usług: ${escapeHtml(data.case.budgetPln)} PLN</p><p>Zakres: ${escapeHtml(data.case.scope)}</p>`, true);
      if (data.status.kickoff === "needs_you") return section("Rozpocznij realizację", `<form class="form" data-action="start"><label>Zatwierdzony zakres<textarea name="scope" rows="4" minlength="10" maxlength="3000" required></textarea></label><label>Osoba odpowiedzialna<input name="owner" maxlength="120" required></label><label>Limit kosztów usług w PLN<input name="budget" type="number" min="0" max="1000000" step="0.01" value="${escapeHtml(defaults.costLimitPln || "")}" required></label><button class="primary">Zatwierdź i zleć utworzenie repozytorium</button></form>`, true);
      return section("Warunek startu", "<p>Najpierw potwierdź zawarcie aktualnej wersji umowy.</p>", true);
    }
    if (stageId === "plan") {
      const job = data.jobs.find((item) => item.kind === "generate_plan");
      const plan = data.case.plan || parseResult(job);
      let html = plan ? section("Architektura", `<p>${escapeHtml(plan.architecture)}</p><p><strong>Technologie:</strong> ${escapeHtml(plan.stack)}</p>`, true) + section("Kamienie milowe", lines((plan.milestones || []).map((item) => `${item.name}: ${item.outcome}`)), true) + section("Zadania agentów", `<ol>${(plan.tasks || []).map((task) => `<li><strong>${escapeHtml(task.title)}</strong> · ${escapeHtml(task.role)}<br><small>Zależy od: ${escapeHtml((task.dependencies || []).join(", ") || "brak")}</small>${lines(task.acceptance)}</li>`).join("")}</ol>`, true) : section("Plan", `<p>${job ? `Stan zadania: ${escapeHtml(labels[data.status.plan])}.` : "Plan powstanie po zatwierdzeniu startu."}</p>`, true);
      if (plan?.templateDecision) {
        const choice = plan.templateDecision;
        html += section("Dobór szablonu", `<p>${choice.mode === "new" ? `Zaproponowano nowy szablon: <strong>${escapeHtml(choice.proposedName)}</strong>` : `Wybrano szablon: <strong>${escapeHtml(choice.templateId)}</strong>`}.</p><p>${escapeHtml(choice.reason)}</p><p><a href="${apiPath("project-template-catalog.php")}">Otwórz katalog szablonów</a></p>`, true);
      }
      if (job?.error) html += section("Błąd planowania", `<p class="error-text">${escapeHtml(job.error)}</p><form class="form" data-action="retry_job"><input type="hidden" name="kind" value="generate_plan"><button class="primary">Ponów planowanie</button></form>`, true);
      const aiCalls = data.aiCalls || [];
      if (aiCalls.length) html += section("Koszt modeli projektu", aiCalls.map((call) => `<div class="event"><p>${escapeHtml(call.task_kind)} · ${escapeHtml(call.state)} · koszt ${pln(call.spent_pln)} PLN · rezerwacja ${pln(call.reserved_pln)} PLN</p>${call.state === "reserved" && data.jobs.find((item) => Number(item.id) === Number(call.call_key.split(":")[1]))?.state === "failed" ? `<form class="form" data-action="reconcile_ai_call"><input type="hidden" name="callKey" value="${escapeHtml(call.call_key)}"><label>Rzeczywisty koszt w PLN (do ${escapeHtml(call.reserved_pln)})<input name="cost" type="number" min="0" max="${escapeHtml(call.reserved_pln)}" step="0.0001" required></label><label>Podstawa sprawdzenia rozliczenia<textarea name="evidence" rows="2" minlength="8" maxlength="1000" required></textarea></label><button class="primary">Rozlicz po sprawdzeniu dostawcy</button></form>` : ""}</div>`).join(""), true);
      return html;
    }
    if (stageId === "repository" || stageId === "environment") {
      const kind = stageId === "repository" ? "create_repository" : (data.jobs.some((item) => item.kind === "provision_preview") ? "provision_preview" : "configure_scaffold");
      const job = data.jobs.find((item) => item.kind === kind);
      const result = parseResult(job);
      let body = job ? `<p>Stan zadania: ${escapeHtml(labels[data.status[stageId]])}; próby: ${escapeHtml(job.attempts)}; aktualizacja: ${date(job.updated_at)}.</p>` : "<p>Zadanie jeszcze nie zostało zlecone.</p>";
      if (stageId === "repository" && data.case.templateProposalId) body += `<p>Projekt ma własne repozytorium bez narzuconego szablonu Vite. Propozycję szablonu do przyszłego wykorzystania (${escapeHtml(data.case.templateProposalId)}) zapisano w <a href="${apiPath("project-template-catalog.php")}">katalogu</a>. CI i wdrożenie czekają na przygotowanie tego stosu przez agentów.</p>`;
      if (result?.branchProtection === "manual_review_required") body += `<p><strong>Tryb GitHub Free:</strong> workflow CI działa, ale GitHub nie może technicznie chronić gałęzi <code>main</code> w prywatnym repozytorium. Sprawdź pull request i scal go ręcznie przed kolejnym zadaniem.</p>`;
      if (result) body += `<pre>${escapeHtml(JSON.stringify(result, null, 2))}</pre>`;
      if (job?.error) body += `<p class="error-text">${escapeHtml(job.error)}</p><form class="form" data-action="retry_job"><input type="hidden" name="kind" value="${kind}"><button class="primary">Ponów zadanie</button></form>`;
      return section(stageId === "repository" ? "GitHub i CI/CD" : "VPS, Cloudflare i TLS", body, true);
    }
    if (["design", "build", "qa"].includes(stageId)) {
      const roles = stageId === "design" ? ["ux", "ui"] : stageId === "build" ? ["architect", "frontend", "backend", "integration"] : ["qa", "security", "performance", "documentation"];
      const tasks = (data.agentTasks || []).filter((task) => roles.includes(task.role));
      const spent = [...(data.agentTasks || []), ...(data.aiCalls || [])].reduce((sum, task) => sum + Number(task.spent_pln || 0), 0);
      const reserved = [...(data.agentTasks || []), ...(data.aiCalls || [])].reduce((sum, task) => sum + Number(task.reserved_pln || 0), 0);
      let html = section("Koszty agentów", `<p>Rozliczono według stawek runnera i modelu: ${pln(spent)} PLN · zarezerwowano: ${pln(reserved)} PLN · limit projektu: ${pln(data.case.budgetPln || 0)} PLN.</p>`, true);
      const taskCards = tasks.map((task) => {
        const accountingOnly = String(task.runner_id || "").endsWith("-code-unreported");
        const accountingClosed = accountingOnly && Number(task.reserved_pln) <= 0;
        const phase = task.runner_phase === "waiting_merge" ? "PR czeka na przegląd i scalenie" : task.runner_phase === "reviewing" ? "Niezależny agent sprawdza PR" : task.runner_phase === "waiting_merge_auto" ? "Przegląd zaakceptowany, czeka na CI i scalenie" : task.runner_phase === "waiting_image" ? "Czeka na obraz z CI" : task.state;
        let resultDetails = task.result ? `<p>${escapeHtml(task.result.summary || "")}</p>${task.result.reviewSummary ? `<p><strong>Przegląd:</strong> ${escapeHtml(task.result.reviewSummary)}</p>` : ""}${task.result.pullRequestUrl && /^https:\/\/github\.com\//.test(task.result.pullRequestUrl) ? `<p><a href="${escapeHtml(task.result.pullRequestUrl)}" target="_blank" rel="noopener noreferrer">Pull request ↗</a></p>` : ""}` : "";
        if (task.role === "qa" && task.result && "qaPassed" in task.result) resultDetails += `<p>Wynik QA: ${task.result.qaPassed ? "zaakceptowane" : "niezaakceptowane"} · port ${escapeHtml(task.result.appPort ?? "brak")} · ścieżka ${escapeHtml(task.result.healthPath || "brak")}</p>`;
        const error = task.error ? `<p class="${accountingClosed ? "" : "error-text"}">${escapeHtml(accountingClosed ? "Koszt rozliczono; praca została zastąpiona poprawką PR #13." : task.error)}</p>` : "";
        let action = "";
        if (task.error && Number(task.reserved_pln) > 0) action = `<form class="form" data-action="reconcile_agent_task"><input type="hidden" name="task" value="${escapeHtml(task.task_key)}"><label>Rzeczywisty koszt w PLN (do ${escapeHtml(task.reserved_pln)})<input name="cost" type="number" min="0" max="${escapeHtml(task.reserved_pln)}" step="0.01" required></label><label>Podstawa sprawdzenia stanu runnera<textarea name="evidence" rows="2" minlength="8" maxlength="1000" required></textarea></label><button class="primary">Rozlicz rezerwację</button></form>`;
        else if (task.error && !accountingOnly) action = `<form class="form" data-action="retry_agent_task"><input type="hidden" name="task" value="${escapeHtml(task.task_key)}"><button class="primary">Ponów zadanie</button></form>`;
        const state = accountingClosed ? "rozliczono · zastąpiono poprawką PR #13" : phase;
        return `<div class="event"><strong>${escapeHtml(task.title)}</strong><p>${escapeHtml(task.role)} · ${escapeHtml(state)} · koszt ${pln(task.spent_pln)} PLN · zależności: ${escapeHtml(task.dependencies.join(", ") || "brak")}</p>${resultDetails}${error}${action}</div>`;
      });
      html += section("Zadania", taskCards.length ? taskCards.join("") : "<p>Zadania pojawią się po utworzeniu repozytorium.</p>", true);      return html;
    }
    if (stageId === "preview") {
      const job = data.jobs.find((item) => /^publish_preview(?:_[a-f0-9]{12})?$/.test(item.kind));
      const result = parseResult(job);
      const previewAccess = data.previewAccess?.password ? `<p>Login do podglądu: <code>${escapeHtml(data.previewAccess.username)}</code></p><p>Hasło do podglądu: <code>${escapeHtml(data.previewAccess.password)}</code></p>` : "";
      const previewSecurity = `<form class="form" data-action="secure_preview"><p>Odświeżenie unieważni poprzednie dane logowania.</p><button class="primary">Ustaw ponownie login i hasło</button></form>`;
      const feedbackStatus = data.case.feedbackEnabledDigest === result?.imageDigest && data.case.previewSentDigest !== result?.imageDigest ? `<p>Formularz uwag jest aktywny. E-mail nie został wysłany — możesz przekazać klientowi link i dane ręcznie.</p>` : "";
      let body = result ? `<p><a href="${escapeHtml(result.url)}" target="_blank" rel="noopener noreferrer">Otwórz działający podgląd ↗</a></p>${previewAccess}<p>Commit: <code>${escapeHtml(result.commitSha)}</code></p><p>Obraz: <code>${escapeHtml(result.imageDigest)}</code></p><p>Wdrożono: ${date(result.deployedAt)}</p>${previewSecurity}${feedbackStatus}${data.case.previewSentDigest === result.imageDigest ? `<p>Link wysłano klientowi ${date(data.case.previewSentAt)}.</p>` : `<form class="form" data-action="send_preview"><button class="primary">${data.case.feedbackEnabledDigest === result.imageDigest ? "Ponów wysyłkę e-maila" : "Zatwierdź i wyślij podgląd klientowi"}</button></form>`}` : `<p>${job ? `Stan: ${escapeHtml(labels[data.status.preview])}.` : "Podgląd zostanie wdrożony po zadaniach agentów, QA i przygotowaniu środowiska."}</p>`;
      if (job?.error) body += `<p class="error-text">${escapeHtml(job.error)}</p><form class="form" data-action="retry_job"><input type="hidden" name="kind" value="${escapeHtml(job.kind)}"><button class="primary">Ponów wdrożenie</button></form>`;
      return section("Sprawdzona wersja", body, true);
    }
    if (stageId === "feedback") {
      const notes = data.feedback || [];
      const categories = { bug: "Błąd w zakresie", scope_change: "Zmiana zakresu", question: "Pytanie", other: "Inna uwaga" };
      return section("Uwagi klienta", notes.length ? notes.map((note) => {
        const job = data.jobs.find((item) => item.kind === `classify_feedback_${note.id}`);
        const analysis = note.analysis || {};
          const areas = Array.isArray(note.annotation?.areas) ? note.annotation.areas : (note.annotation?.rect ? [note.annotation] : []);
        const annotationMarkup = areas.length ? `<div class="feedback-annotation"><strong>${areas.length} zaznaczonych obszarów</strong><div class="feedback-areas">${areas.map((area, index) => {
            const storedRect = area.rect || {};
            const mark = Array.isArray(storedRect) ? { x: storedRect[0], y: storedRect[1], width: storedRect[2], height: storedRect[3] } : storedRect;
          const target = area.element || {};
          const viewportWidth = Math.max(1, Number(area.viewport?.width) || 16);
          const viewportHeight = Math.max(1, Number(area.viewport?.height) || 9);
          return `<div class="feedback-area"><strong>Obszar ${index + 1}</strong><div aria-label="Mapa zaznaczenia w widoku klienta" style="position:relative;aspect-ratio:${viewportWidth}/${viewportHeight};max-width:360px;background:#e9eef5;border:1px solid #9aaabd;border-radius:6px;margin:8px 0"><span style="position:absolute;left:${Math.max(0, Math.min(100, Number(mark.x) * 100))}%;top:${Math.max(0, Math.min(100, Number(mark.y) * 100))}%;width:${Math.max(0, Math.min(100, Number(mark.width) * 100))}%;height:${Math.max(0, Math.min(100, Number(mark.height) * 100))}%;background:#3284df55;border:2px solid #1670ce;box-sizing:border-box"></span></div>${area.note ? `<p><strong>Notatka klienta:</strong> ${escapeHtml(area.note)}</p>` : ""}<small>Przewinięcie strony: ${escapeHtml(area.scroll?.y || 0)} px<br>${target.id ? `#${escapeHtml(target.id)}` : ""}${target.classes ? ` · ${escapeHtml(target.classes)}` : ""}${target.text ? `<br>${escapeHtml(target.text)}` : ""}</small></div>`;
        }).join("")}</div></div>` : "";
        return `<div class="event"><time>${date(note.created_at)} · wersja ${escapeHtml(note.image_digest)}</time><p>${escapeHtml(note.message)}</p>${note.page_url ? `<p><strong>Podstrona klienta (informacja wewnętrzna):</strong> <code>${escapeHtml(note.page_url)}</code></p>` : ""}${annotationMarkup}<p>Stan: ${escapeHtml(note.state)}${note.category ? ` · ${escapeHtml(categories[note.category] || note.category)}` : ""}</p>${analysis.rationale ? `<p><strong>Ocena agenta:</strong> ${escapeHtml(analysis.rationale)}</p><p><strong>Dalszy krok:</strong> ${escapeHtml(analysis.suggestedAction || "")}</p><p>Zakres: ${escapeHtml(analysis.scopeImpact || "")} · termin: ${escapeHtml(analysis.timelineImpact || "")}</p>` : ""}${note.state === "new" ? `<p>Klasyfikacja: ${escapeHtml(job?.state || "w kolejce")}</p>${job?.error ? `<p class="error-text">${escapeHtml(job.error)}</p><form class="form" data-action="retry_job"><input type="hidden" name="kind" value="${escapeHtml(job.kind)}"><button class="primary">Ponów klasyfikację</button></form>` : ""}` : ""}${note.state === "triaged" && note.category === "bug" ? `<form class="form" data-action="request_fix"><input type="hidden" name="feedbackId" value="${escapeHtml(note.id)}"><button class="primary">Zleć poprawkę agentom</button></form>` : ""}${["triaged", "fixed_pending_client"].includes(note.state) ? `<form class="form" data-action="resolve_feedback"><input type="hidden" name="feedbackId" value="${escapeHtml(note.id)}"><label>Podstawa rozstrzygnięcia lub akceptacji poprawki<textarea name="resolution" rows="2" minlength="8" maxlength="1000" required></textarea></label><button class="primary">Zapisz rozstrzygnięcie</button></form>` : ""}</div>`;
      }).join("") : "<p>Nie ma jeszcze uwag do wysłanej wersji.</p>", true);
    }
    if (stageId === "release") {
      const job = data.jobs.find((item) => item.kind === "publish_production");
      const result = parseResult(job);
      let body = result ? `<p>Produkcja: <a href="${escapeHtml(result.url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(result.url)} ↗</a></p><p>Commit: ${escapeHtml(result.commitSha)}</p><p>Obraz: ${escapeHtml(result.imageDigest)}</p>` : `<p>${job ? `Stan: ${escapeHtml(labels[data.status.release])}.` : "Wymagana jest osobna zgoda na dokładną wersję podglądu."}</p>`;
      if (!job && data.case.previewSentAt) body += `<form class="form" data-action="approve_production"><label>Podstawa akceptacji klienta i decyzji o publikacji<textarea name="evidence" rows="3" minlength="8" maxlength="1000" required></textarea></label><button class="primary">Zatwierdź publikację tej wersji</button></form>`;
      if (job?.error) body += `<p class="error-text">${escapeHtml(job.error)}</p><form class="form" data-action="retry_job"><input type="hidden" name="kind" value="publish_production"><button class="primary">Ponów wdrożenie produkcyjne</button></form>`;
      return section("Publikacja produkcyjna", body, true);
    }
    if (stageId === "handover") return section("Przekazanie i wsparcie", data.case.handoverAt ? `<p>Przekazano ${date(data.case.handoverAt)} wersję ${escapeHtml(data.case.handoverDigest)}.</p><p>${escapeHtml(data.case.handoverNote)}</p>` : data.status.handover === "needs_you" ? `<form class="form" data-action="complete_handover"><label>Jak przekazano dostęp, dokumentację i zasady wsparcia?<textarea name="note" rows="4" minlength="15" maxlength="2000" required></textarea></label><button class="primary">Zapisz przekazanie projektu</button></form>` : "<p>Najpierw opublikuj zatwierdzoną wersję produkcyjną.</p>", true);
    return section("Wynik etapu", `<p>${data.status[stageId] === "locked" ? "Ten etap nie został jeszcze uruchomiony. Jego zadania i artefakty pojawią się tutaj po spełnieniu zależności." : "Etap jest gotowy. Koordynator utworzy zadania i przypisze je właściwym agentom."}</p>`, true);
  }

  function openStage(stageId, moveFocus = true) {
    const stage = project.stages.find((item) => item.id === stageId);
    if (!stage) return;
    currentStage = stageId;
    previousFocus = moveFocus ? document.activeElement : previousFocus;
    $("#dialog-agent").textContent = `${stage.agent} · ${labels[project.status[stageId]]}`;
    $("#dialog-title").textContent = stage.name;
    const relatedEvents = project.events.filter((event) => event.stage === stageId);
    const history = relatedEvents.length ? relatedEvents.map((event) => `<div class="event"><time>${date(event.created_at)} · ${escapeHtml(event.actor)}</time><p>${escapeHtml(event.details)}</p></div>`).join("") : "<p>Nie ma jeszcze zapisanych działań tego etapu.</p>";
    const previous = project.stages.filter((candidate) => candidate.next.includes(stageId)).map((candidate) => candidate.name);
    const output = stage.next.map((next) => project.stages.find((candidate) => candidate.id === next)?.name).filter(Boolean);
    $("#dialog-content").innerHTML = `<div class="detail-grid">${section("Stan", `<p>${labels[project.status[stageId]]}.</p><p>${project.status[stageId] === "locked" ? "Etap czeka na zakończenie poprzednich kroków." : "Stan pochodzi z zapisanej sprawy i zadań."}</p>`)}${section("Powiązania", `<p>Wejście: ${escapeHtml(previous.join(", ") || "początek sprawy")}</p><p>Dalej: ${escapeHtml(output.join(", ") || "zakończenie")}</p>`)}${stageOutput(stageId)}${section("Historia etapu", history, true)}</div>`;
    $("#overlay").hidden = false;
    document.body.style.overflow = "hidden";
    if (moveFocus) $("#close").focus();
  }

  function closeStage() {
    $("#overlay").hidden = true;
    document.body.style.overflow = "";
    currentStage = null;
    previousFocus?.focus?.();
  }

  async function submitAction(form) {
    const button = form.querySelector("button");
    const data = Object.fromEntries(new FormData(form).entries());
    data.action = form.dataset.action;
    button.disabled = true;
    try {
      const response = await fetch(endpoint, { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json", "X-CSRF-Token": csrf }, body: JSON.stringify(data) });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || "Nie udało się zapisać.");
      await load(true);
    } catch (error) {
      const message = document.createElement("p");
      message.className = "error-text";
      message.setAttribute("role", "alert");
      message.textContent = error.message;
      form.append(message);
      button.disabled = false;
    }
  }

  $("#dialog-content").addEventListener("submit", (event) => {
    const form = event.target.closest("form[data-action]");
    if (!form) return;
    event.preventDefault();
    submitAction(form);
  });
  $("#close").addEventListener("click", closeStage);
  $("#overlay").addEventListener("click", (event) => { if (event.target.id === "overlay") closeStage(); });
  document.addEventListener("keydown", (event) => { if (event.key === "Escape" && !$("#overlay").hidden) closeStage(); });
  $("#refresh").addEventListener("click", () => load(Boolean(currentStage)).catch(showError));
  $("#zoom-in").addEventListener("click", () => changeZoom(0.15));
  $("#zoom-out").addEventListener("click", () => changeZoom(-0.15));
  $("#focus").addEventListener("click", focusCurrent);
  function changeZoom(delta) { zoom = Math.max(0.55, Math.min(1.4, Math.round((zoom + delta) * 100) / 100)); mapBoard.style.transform = `scale(${zoom})`; mapSizer.style.width = `${3390 * zoom}px`; mapSizer.style.height = `${455 * zoom}px`; updateMinimap(); }
  function focusCurrent() { if (!project) return; const stage = project.stages.find((item) => ["needs_you", "error", "working", "queued", "ready"].includes(project.status[item.id])) || project.stages.at(-1); $("#map-shell").scrollTo({ left: Math.max(0, stage.x * zoom - $("#map-shell").clientWidth / 2 + 100), top: 0, behavior: "smooth" }); }
  function updateMinimap() { const viewport = $("#mini-viewport"); viewport.setAttribute("x", String(mapShell.scrollLeft / zoom)); viewport.setAttribute("width", String(Math.min(3390, mapShell.clientWidth / zoom))); }
  mapShell.addEventListener("scroll", updateMinimap);
  mapShell.addEventListener("wheel", (event) => {
    if (event.ctrlKey || mapShell.scrollWidth <= mapShell.clientWidth) return;
    const delta = event.deltaX || event.deltaY;
    if (!delta) return;
    const next = Math.max(0, Math.min(mapShell.scrollWidth - mapShell.clientWidth, mapShell.scrollLeft + delta));
    if (next === mapShell.scrollLeft) return;
    event.preventDefault();
    mapShell.scrollLeft = next;
  }, { passive: false });
  minimap.addEventListener("click", (event) => { const rect = minimap.getBoundingClientRect(); const ratio = Math.max(0, Math.min(1, (event.clientX - rect.left - 5) / Math.max(1, rect.width - 10))); mapShell.scrollTo({ left: ratio * 3390 * zoom - mapShell.clientWidth / 2, behavior: "smooth" }); });
  function showError(error) {
    $("#summary").textContent = error.message || "Nie udało się pobrać sprawy.";
    $("#last-updated").textContent = "Nie można pobrać aktualnego stanu";
    $("#live-state").classList.remove("syncing");
  }
  load().then(focusCurrent).catch(showError);
  setInterval(() => {
    if (document.hidden || $("#dialog-content form :focus")) return;
    load(Boolean(currentStage)).catch(showError);
  }, 5000);
})();
