(() => {
  const id = window.PROJECT_SESSION;
  const apiBase = location.pathname.startsWith("/api/") ? "/api" : "";
  const apiPath = (path) => `${apiBase}/${String(path).replace(/^\/+/, "")}`;
  const endpoint = `${apiPath("project-api.php")}?session=${encodeURIComponent(id)}`;
  const labels = { cancelled: "Anulowano", locked: "Niedostępny", ready: "Gotowy do startu", queued: "W kolejce", working: "Pracuje", needs_you: "Twoja decyzja", waiting_client: "Czeka na klienta", review: "Do przeglądu", done: "Zakończony", error: "Błąd" };
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
  miniStyle.textContent = '.map-wrap{position:relative}.node.review{border-color:#c7a477;background:#3d322b}.map-hint{position:absolute;top:12px;right:13px;z-index:2;padding:6px 9px;border:1px solid #354969;border-radius:8px;background:#0b1524df;color:#aebfda;font-size:11px;pointer-events:none}.minimap{position:absolute;right:13px;bottom:13px;width:235px;height:72px;padding:5px;border:1px solid #52709d;border-radius:10px;background:#0b1524ed;box-shadow:0 8px 26px #0009;cursor:crosshair}.minimap svg{width:100%;height:100%}.minimap:hover,.minimap:focus-visible{border-color:#afc7ff;outline:0}.feedback-image-open{display:block;max-width:100%;padding:0;border:0;background:transparent;color:#abc1e7;text-align:left}.feedback-image-open:focus-visible{outline:2px solid #86a9ff;outline-offset:3px;border-radius:8px}.feedback-image-hint{font-size:11px}.feedback-dispatch{margin:14px 0;padding:14px;border:1px solid #526b99;border-radius:12px;background:#14243a}.feedback-dispatch p{color:#b8c9e4;font-size:12px}.feedback-dispatch button:disabled{background:#39465a!important;color:#a5afbf;cursor:not-allowed;opacity:.8}.feedback-closed,.feedback-approved{padding:10px;border:1px solid #287e57;border-radius:9px;background:#123d32;color:#b7f0d2}.feedback-open{padding:10px;border:1px solid #927039;border-radius:9px;background:#40331b;color:#f4d391}.feedback-accept{background:#168455!important}.feedback-reject{background:#9e343e!important;color:#fff;border:0;border-radius:8px;padding:10px 14px;font:600 14px system-ui;cursor:pointer}.feedback-rejected-card{border:1px solid #963d47!important;background:#351c27!important}.feedback-accepted-card{border:1px solid #287e57!important;background:#142d2b!important}.feedback-number{display:block;margin-bottom:6px;color:#c8d8f3;font-size:14px}.feedback-review{display:grid;gap:10px;margin:14px 0;padding:14px;border:1px solid #526b99;border-radius:12px;background:#182640}.feedback-review>strong{font-size:14px}.feedback-review>p{margin:0;color:#b8c9e4;font-size:12px;line-height:1.5}.feedback-lightbox{position:fixed;inset:0;z-index:100;background:#030711ed;display:flex;align-items:center;justify-content:center;padding:60px 24px 24px}.feedback-lightbox[hidden]{display:none}.feedback-lightbox-image-wrap{position:relative;max-width:min(96vw,1800px);max-height:calc(100vh - 90px)}.feedback-lightbox img{display:block;max-width:100%;max-height:calc(100vh - 90px);object-fit:contain;border:1px solid #53698c;border-radius:8px;background:#fff;box-shadow:0 22px 80px #000b}.feedback-lightbox-selection{position:absolute;background:#3284df55;border:3px solid #1670ce;box-sizing:border-box;pointer-events:none}.feedback-lightbox button{position:absolute;top:16px;right:20px;padding:9px 14px;border:1px solid #60749a;border-radius:10px;background:#17233a;color:#fff;font:inherit;cursor:pointer}@media(max-width:600px){.minimap{width:160px;height:53px}.map-hint{font-size:10px;right:9px;top:9px}}';
  miniStyle.textContent += ".toolbar #close-project{border-color:#85404a;color:#ffb6bd}.toolbar #close-project:hover{background:#45212a}.node.cancelled{border-color:#64748b;background:#202938;color:#b5c0ce}.dot.cancelled{background:#64748b}.invoice-register{margin-top:16px}.invoice-entry{margin:12px 0;padding:12px;border:1px solid #334665;border-radius:10px;background:#101827}.invoice-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:10px}.invoice-form .wide{grid-column:1/-1}.invoice-register h4{margin:18px 0 8px}.invoice-form button{grid-column:1/-1}@media(max-width:700px){.invoice-form{grid-template-columns:1fr}.invoice-form .wide{grid-column:auto}}";
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
    if (project.case.closedAt) return { stage: project.stages.at(-1), state: "closed", title: "Projekt zamkni\u0119ty - agenci zatrzymani", detail: `${project.case.closeReason || "Zako\u0144czono dzia\u0142ania."}\nData: ${date(project.case.closedAt)}` };
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
    const titles = { closed: "Projekt zamkni\u0119ty - agenci zatrzymani", error: "Praca zatrzymana · błąd", needs_you: "Oczekuje na Twoją decyzję", working: "Projekt jest realizowany", queued: "Następny etap czeka w kolejce", review: "Wynik wymaga przeglądu", ready: "Gotowy do rozpoczęcia", waiting_client: "Oczekuje na klienta", done: "Projekt zakończony", locked: "Projekt jeszcze się nie rozpoczął" };
    return { stage, state, title: titles[state] || "Projekt oczekuje", detail };
  }
  function phaseLabel(phase) {
    return ({ waiting_merge: "PR czeka na scalenie", reviewing: "trwa niezależny przegląd", waiting_merge_auto: "PR czeka na CI i scalenie", waiting_image: "oczekuje na obraz z CI" })[phase] || "agent pracuje";
  }

  function render() {
    $("#side-name").textContent = project.name;
    $("#side-client").textContent = project.client;
    $("#summary").textContent = `${project.client} · aktualizacja ${date(project.updatedAt)}`;
    $("#close-project").hidden = Boolean(project.case.closedAt);
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

  function documentSources(doc = {}) {
    const names = { brief: "Brief", analysis: "Analiza", offer: "Oferta", preparation: "Opracowanie oferty" };
    const base = `${apiPath("document-history.php")}?session=${encodeURIComponent(id)}`;
    const links = Object.entries(doc.sourceRefs || {}).filter(([kind]) => names[kind]).map(([kind, ref]) => `<a target="_blank" rel="noopener" href="${base}&ref=${encodeURIComponent(ref)}">${names[kind]} — zapisana wersja ↗</a>`);
    return section("Na podstawie", `<p>${links.length ? links.join(" · ") : "Brak zapisanych powiązań dla tej historycznej wersji."}</p><p><a target="_blank" rel="noopener" href="${base}">Archiwum dokumentów i wersji ↗</a></p>`, true);
  }

  function stageOutput(stageId) {
    const data = project;
    if (stageId === "brief") {
      const blocks = (data.brief?.sections || []).map((entry) => `<h4>${escapeHtml(entry.title)}</h4>${lines(entry.content)}`).join("");
      const chat = (data.messages || []).map((message) => `<div class="event"><strong>${message.role === "user" ? "Klient" : "Agent rozmowy"}</strong><p>${escapeHtml(message.content)}</p></div>`).join("");
      const assets = data.assets || [];
      const assetCards = assets.map((asset) => {
        const url = `${apiPath("project-assets.php")}?session=${encodeURIComponent(id)}&asset=${encodeURIComponent(asset.asset_key)}`;
        const isImage = ["image/jpeg", "image/png", "image/webp", "image/gif"].includes(asset.mime);
        const image = isImage ? `<a href="${url}&inline=1" target="_blank" rel="noopener"><img class="project-asset-thumb" src="${url}&inline=1" alt="Podgl\u0105d ${escapeHtml(asset.filename)}"></a>` : "";
        const labels = { logo: "Logo / identyfikacja", photo: "Zdj\u0119cia realizacji", text: "Teksty / dokumenty", other: "Inne materia\u0142y" };
        const statuses = [["received", "Do sprawdzenia"], ["approved", "Zatwierdzony"], ["needs_changes", "Potrzebna informacja"], ["rejected", "Odrzucony"]].map(([value, label]) => `<option value="${value}"${asset.status === value ? " selected" : ""}>${label}</option>`).join("");
        return `<article class="project-asset-card">${image}<div class="project-asset-info"><strong>${escapeHtml(asset.filename)}</strong><small>${labels[asset.category] || "Materia\u0142"} · ${(asset.size / 1048576).toFixed(2)} MB · dodano ${date(asset.created_at)}</small><p>Pochodzenie / zgoda: ${escapeHtml(asset.source_note || "do potwierdzenia")}</p>${asset.admin_note ? `<p>Notatka zespo\u0142u: ${escapeHtml(asset.admin_note)}</p>` : ""}<form class="form project-asset-review" data-action="asset_review"><input type="hidden" name="asset_id" value="${asset.id}"><label>Ocena<select name="status">${statuses}</select></label><label>Notatka zespo\u0142u / pytanie do klienta<textarea name="admin_note" rows="2" maxlength="1000">${escapeHtml(asset.admin_note || "")}</textarea></label><button class="tool">Zapisz ocen\u0119</button></form><a href="${url}">Pobierz plik</a></div></article>`;
      }).join("");
      const upload = data.case.closedAt ? "<p>Sprawa jest zamkni\u0119ta · dodawanie materia\u0142\u00f3w wy\u0142\u0105czone.</p>" : `<form class="form" data-action="asset_upload" enctype="multipart/form-data"><label>Pliki klienta<input type="file" name="files[]" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.txt,.md,.docx" multiple required></label><label>Rodzaj materia\u0142u<select name="category"><option value="logo">Logo / identyfikacja</option><option value="photo">Zdj\u0119cia realizacji</option><option value="text">Teksty / dokumenty</option><option value="other">Inne materia\u0142y</option></select></label><label>Pochodzenie i zgoda na wykorzystanie<textarea name="source_note" rows="2" maxlength="1000" placeholder="Np. zdj\u0119cia przekazane przez klienta; zgoda na u\u017cycie na stronie"></textarea></label><small>JPG, PNG, WebP, GIF, PDF, TXT, MD, DOCX. Maks. 12 MB na plik, 100 MB na spraw\u0119.</small><button class="primary">Dodaj materia\u0142y do sprawy</button></form>`;
      const assetSection = section("Materia\u0142y od klienta", `<p>Pliki s\u0105 prywatne. Agenci dostaj\u0105 wy\u0142\u0105cznie materia\u0142y oznaczone jako zatwierdzone; pliki dodane w trakcie zadania trafi\u0105 do agent\u00f3w przy nast\u0119pnym zadaniu.</p>${upload}${assetCards || "<p>Klient nie przekaza\u0142 jeszcze plik\u00f3w.</p>"}`, true);
      return section("Przekazany brief", blocks || "<p>Brak sekcji briefu.</p>", true) + section("Rozmowa", chat || "<p>Brak wiadomo\u015bci.</p>", true) + assetSection;
    }
    if (stageId === "analysis") {
      const a = data.analysis || {};
      return documentSources(a) + section("Podsumowanie", `<p>${escapeHtml(a.summary || a.message || "Analiza jeszcze nie powstała.")}</p>`, true) + section("Brakujące informacje", lines(a.missingInformation)) + section("Ryzyka", lines(a.risks)) + section("Zalecany zakres", lines(a.recommendedScope)) + section("Pytania do klienta", lines(a.questionsForClient)) + section("Pełna analiza", adminLink(), true);
    }
    if (stageId === "offer") return documentSources(data.offer) + section("Oferta", `<p>Stan: ${escapeHtml(data.offer.status || "brak")} · wersja ${escapeHtml(data.offer.version || "—")}</p>${adminLink("#offer-panel")}`, true);
    if (stageId === "contract") {
      const c = data.contract;
      const signed = Number(data.case.contractSignedVersion || 0) === Number(c.version || 0) && Number(c.version || 0) > 0;
      let html = documentSources(c) + section("Dokument", `<p>Numer: ${escapeHtml(c.number || "—")}; wersja: ${escapeHtml(c.version || "—")}; stan wysyłki: ${escapeHtml(c.status || "brak")}.</p><p>Potwierdzenie zawarcia: ${signed ? "tak" : "nie"}. Wysłanie dokumentu nie potwierdza zawarcia umowy.</p>${adminLink("#contract-panel")}`, true);
      if(c.hasPdf) {
        const freshLayout=!signed && c.status==="DRAFT" && Number(c.pdfLayoutVersion || 0)<3;
        const url=`${apiPath("contract.php")}?session=${encodeURIComponent(id)}&format=${freshLayout ? "layout-preview" : "pdf"}&inline=1&layout=3`;
        html+=section("Podgląd PDF umowy", `${freshLayout ? `<p>Aktualny układ szkicu. Nowy wygląd zapiszesz, przygotowując PDF w edycji umowy.</p>` : ""}<iframe title="Podgląd PDF umowy" src="${escapeHtml(url)}" style="width:100%;height:75vh;border:0;border-radius:8px;background:white"></iframe><p><a href="${escapeHtml(url)}" target="_blank" rel="noopener">Otwórz PDF w osobnej karcie ↗</a></p>`,true);
      }
      if(c.package) html += section("Pakiet umowny", `<p>Weryfikacja prawna: ${c.package.approved ? "potwierdzona dla tej wersji" : "wymagana przed wysyłką i potwierdzeniem umowy"}.</p><p>Dowód otrzymania przez klienta: ${c.package.hasReceipt ? "zapisany" : "brak"}.</p>${lines(c.package.documents)}${c.package.earliestStart ? `<p>Najwcześniejszy start według zapisanych potwierdzeń: ${escapeHtml(c.package.earliestStart)}.</p>` : ""}${adminLink("#contract-panel")}`, true);
      if (c.hasPdf && !signed && (!c.package || c.package.approved)) html += section("Potwierdź zawarcie", `<form class="form" data-action="confirm_contract"><label>Podstawa potwierdzenia, data i sposób podpisania<textarea name="evidence" rows="3" required minlength="8" maxlength="500" placeholder="Np. podpisana umowa v2 z dnia …, dokument sprawdzony …"></textarea></label><button class="primary">Potwierdź aktualną wersję</button></form>`, true);
      if (signed) html += section("Zapisane potwierdzenie", `<p>${escapeHtml(data.case.contractEvidence || "")}</p><p>${date(data.case.contractConfirmedAt)}</p>`, true);
      return html;
    }
    if (stageId === "kickoff") {
      if (data.case.startedAt) return section("Zatwierdzony start", `<p>Data: ${date(data.case.startedAt)}</p><p>Opiekun: ${escapeHtml(data.case.owner)}</p><p>Limit kosztów usług: ${escapeHtml(data.case.budgetPln)} PLN</p><p>Zakres: ${escapeHtml(data.case.scope)}</p>`, true);
      if (data.status.kickoff === "needs_you") return section("Rozpocznij realizację", `${data.contract.package?.earliestStart ? `<p>Najwcześniejszy start: ${escapeHtml(data.contract.package.earliestStart)}. Oświadczenia klienta nie są domniemywane.</p>` : ""}<form class="form" data-action="start"><label>Zatwierdzony zakres<textarea name="scope" rows="4" minlength="10" maxlength="3000" required></textarea></label><label>Osoba odpowiedzialna<input name="owner" maxlength="120" required></label><label>Limit kosztów usług w PLN<input name="budget" type="number" min="0" max="1000000" step="0.01" value="${escapeHtml(defaults.costLimitPln || "")}" required></label><button class="primary">Zatwierdź i zleć utworzenie repozytorium</button></form>`, true);
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
      const latestPreview = data.jobs.find((item) => /^publish_preview(?:_[a-f0-9]{12})?$/.test(item.kind));
      const digest = parseResult(latestPreview)?.imageDigest || data.case.feedbackEnabledDigest || data.case.previewSentDigest || "";
      const currentNotes = notes.filter((note) => note.image_digest === digest);
      const closed = Boolean(digest && data.case.feedbackClosedDigest === digest);
      const undecided = currentNotes.filter((note) => ["new", "triaged"].includes(note.state));
      const approved = currentNotes.filter((note) => note.state === "approved");
      const alreadySent = currentNotes.some((note) => ["in_fix", "fixed_pending_client"].includes(note.state));
      const dispatchDisabled = !closed || undecided.length > 0 || approved.length === 0 || alreadySent;
      const dispatchReason = !digest ? "Najpierw wyslij klientowi aktualny podgl\u0105d." : !closed ? "Przekazanie odblokuje si\u0119, gdy klient potwierdzi w podgl\u0105dzie, \u017ce wys\u0142a\u0142 ju\u017c wszystkie uwagi." : undecided.length ? "Najpierw zaakceptuj albo odrzu\u0107 ka\u017cd\u0105 uwag\u0119 z tej wersji." : approved.length === 0 ? "Brak zaakceptowanych uwag do przekazania." : alreadySent ? "Pakiet uwag zosta\u0142 ju\u017c przekazany agentom." : "";
      const noFixes = closed && undecided.length === 0 && approved.length === 0 && !alreadySent;
      const dispatch = noFixes ? `<section class="feedback-dispatch"><strong>Brak poprawek do przekazania</strong><p>Klient zamkn\u0105\u0142 list\u0119 uwag i nie ma zaakceptowanych zmian dla agent\u00f3w. Klient mo\u017ce teraz zaakceptowa\u0107 w pe\u0142ni t\u0119 wersj\u0119. Po akceptacji b\u0119dzie czeka\u0107 na Twoj\u0105 zgod\u0119 na publikacj\u0119.</p></section>` : `<section class="feedback-dispatch"><strong>Przekazanie uwag agentom</strong><p>${escapeHtml(dispatchReason || `${approved.length} zaakceptowanych uwag gotowych do przekazania.`)}</p><form class="form" data-action="dispatch_feedback_fixes"><button class="primary" ${dispatchDisabled ? "disabled" : ""}>Przeka\u017c zaakceptowane uwagi do poprawek</button></form></section>`;
      const closureEvent = data.events.find((event) => event.stage === "feedback" && event.kind === "client_closed" && String(event.details).includes(digest));
      const closedAt = Number(data.case.feedbackClosedAt) || Number(closureEvent?.created_at) || 0;
      const closure = closed ? `<p class="feedback-closed">Klient potwierdzi\u0142 komplet uwag do tej wersji${closedAt ? `: ${date(closedAt)}.` : "."}</p>` : `<p class="feedback-open">Oczekujemy na potwierdzenie klienta, \u017ce lista uwag jest kompletna.</p>`;
      const rendered = notes.length ? notes.map((note) => {
        const areas = Array.isArray(note.annotation?.areas) ? note.annotation.areas : (note.annotation?.rect ? [note.annotation] : []);
        const annotationMarkup = areas.length ? `<div class="feedback-annotation"><strong>${areas.length} zaznaczonych obszar\u00f3w</strong><div class="feedback-areas">${areas.map((area, index) => {
          const rect = area.rect || {};
          const context = area.context || { x: 0, y: 0, width: 1, height: 1 };
          const vw = Math.max(1, Number(area.viewport?.width) || 16);
          const vh = Math.max(1, Number(area.viewport?.height) || 9);
          const width = Math.max(1, context.width * vw), height = Math.max(1, context.height * vh);
          const scale = Math.min(360 / width, 260 / height), w = width * scale, h = height * scale;
          const has = typeof area.screenshot === "string" && area.screenshot.startsWith("data:image/jpeg;base64,");
          const image = has ? `<img alt="Zrzut strony wok\u00f3\u0142 zaznaczenia ${index + 1}" src="${escapeHtml(area.screenshot)}" style="position:absolute;inset:0;width:100%;height:100%;object-fit:fill">` : typeof area.snapshot === "string" && area.snapshot.length ? `<iframe title="Zapisany widok strony" sandbox="" referrerpolicy="no-referrer" loading="lazy" srcdoc="${escapeHtml(area.snapshot)}" style="position:absolute;left:${-context.x * vw * scale}px;top:${-context.y * vh * scale}px;width:${vw}px;height:${vh}px;transform:scale(${scale});transform-origin:top left;pointer-events:none;border:0;background:#fff"></iframe>` : "";
          const left = Math.max(0, Math.min(100, (Number(rect.x) - context.x) / context.width * 100));
          const top = Math.max(0, Math.min(100, (Number(rect.y) - context.y) / context.height * 100));
          const mw = Math.max(0, Math.min(100, Number(rect.width) / context.width * 100));
          const mh = Math.max(0, Math.min(100, Number(rect.height) / context.height * 100));
          const crop = `<div style="position:relative;overflow:hidden;width:${w}px;height:${h}px;max-width:100%;background:#e9eef5;border:1px solid #9aaabd;border-radius:6px;margin:8px 0">${image}<span style="position:absolute;left:${left}%;top:${top}%;width:${mw}%;height:${mh}%;background:#3284df55;border:2px solid #1670ce;box-sizing:border-box"></span></div>`;
          const viewer = has ? `<button class="feedback-image-open" type="button" aria-label="Powi\u0119ksz obraz obszaru ${index + 1}">${crop}<span class="feedback-image-hint">Kliknij, aby powi\u0119kszy\u0107</span></button>` : crop;
          return `<div class="feedback-area"><strong>Obszar ${index + 1}</strong>${viewer}${area.note ? `<p><strong>Notatka klienta:</strong> ${escapeHtml(area.note)}</p>` : ""}<small>${area.scroll?.y ? `Przewini\u0119cie strony: ${escapeHtml(area.scroll.y)} px<br>` : ""}${area.element?.id ? `#${escapeHtml(area.element.id)} ` : ""}${area.element?.classes ? ` - ${escapeHtml(area.element.classes)}` : ""}</small></div>`;
        }).join("")}</div></div>` : "";
        const isPending = ["new", "triaged"].includes(note.state);
        const canReview = isPending || note.state === "approved";
        const editor = canReview ? `<div class="feedback-review"><strong>Twoja decyzja</strong><p>Orygina\u0142 klienta pozostaje zapisany. Nadpisanie administratora ma priorytet i trafi do agentow razem ze zrzutami i kontekstem.</p><form class="form" data-action="edit_feedback"><input type="hidden" name="feedbackId" value="${escapeHtml(note.id)}"><label>Twoja tre\u015b\u0107 dla agent\u00f3w (opcjonalnie, najwy\u017cszy priorytet)<textarea name="message" rows="3" maxlength="4000" placeholder="Puste pole oznacza, \u017ce agent wykona oryginaln\u0105 uwag\u0119 klienta.">${escapeHtml(note.admin_message || "")}</textarea></label><button class="tool">Zapisz nadpisanie</button>${isPending ? `<button class="primary feedback-accept" data-action="approve_feedback">Akceptuj</button>` : `<p class="feedback-approved">Zaakceptowana - czeka na przekazanie ca\u0142ego pakietu.</p>`}${isPending ? `<button class="feedback-reject" data-action="reject_feedback">Odrzu\u0107</button>` : `<button class="feedback-reject" data-action="reject_feedback">Cofnij akceptacj\u0119 i odrzu\u0107</button>`}</form></div>` : note.state === "in_fix" || note.state === "fixed_pending_client" ? `<p class="feedback-approved"><strong>Przekazano agentom.</strong> Priorytet: ${escapeHtml(note.approved_message || note.admin_message || note.message)}</p>` : note.admin_decision === "rejected" ? `<p class="feedback-rejected">Odrzucona przez administratora.</p>` : `<p>Stan: ${escapeHtml(note.state)}</p>`;
        const stateClass = note.admin_decision === "rejected" ? "feedback-rejected-card" : note.admin_decision === "accepted" ? "feedback-accepted-card" : "";
        return `<article class="event feedback-entry ${stateClass}"><strong class="feedback-number">Uwaga #${escapeHtml(note.id)}</strong><time>${date(note.created_at)} - wersja ${escapeHtml(note.image_digest)}</time><p><strong>Oryginalna tre\u015b\u0107 klienta:</strong> ${escapeHtml(note.message)}</p>${note.admin_message ? `<p><strong>Priorytet administratora:</strong> ${escapeHtml(note.admin_message)}</p>` : ""}${note.page_url ? `<p><strong>Podstrona (informacja wewn\u0119trzna):</strong> <code>${escapeHtml(note.page_url)}</code></p>` : ""}${annotationMarkup}<p>Stan: ${escapeHtml(note.state)}</p>${editor}</article>`;
      }).join("") : "<p>Nie ma jeszcze uwag do wys\u0142anej wersji.</p>";
      return section("Uwagi klienta", `${digest ? closure : "<p>Najpierw wy\u015blij klientowi podgl\u0105d.</p>"}${dispatch}${rendered}`, true);
    }
    if (stageId === "release") {
      const job = data.jobs.find((item) => item.kind === "publish_production");
      const result = parseResult(job);
      const previewJob = data.jobs.find((item) => /^publish_preview(?:_[a-f0-9]{12})?$/.test(item.kind));
      const previewResult = parseResult(previewJob);
      const digest = previewResult?.imageDigest || "";
      const accepted = Boolean(digest && data.case.previewAcceptedDigest === digest && data.case.previewAcceptedAt);
      const openFeedback = data.feedback.some((note) => note.state !== "resolved");
      const handoff = data.case.clientHandoff || {};
      const publicationPlan = data.contract.publicationPlan;
      const prepared = Boolean(data.case.clientHandoffPreparedAt);
      let body;
      if (prepared) {
        body = `<div class="feedback-closed"><strong>Etap publikacji zako\u0144czony: wdro\u017cenie po stronie klienta</strong><p>Nie wdro\u017cono produkcji na infrastrukturze Grzywniak. Nast\u0119pny krok to przekazanie klientowi uzgodnionego pakietu.</p></div><p>Domena: <code>${escapeHtml(handoff.domain)}</code> &middot; hosting: ${escapeHtml(handoff.hostingProvider)}</p><p>Weryfikacja domeny: ${escapeHtml(handoff.verificationEvidence)}</p><p>Cel wdro\u017cenia: ${escapeHtml(handoff.serverTarget)}</p>`;
      } else if (result) {
        body = `<div class="detail-section"><h3>Wdro\u017cenie na naszej infrastrukturze</h3><p>Produkcja: <a href="${escapeHtml(result.url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(result.url)} &nearr;</a></p><p>Commit: ${escapeHtml(result.commitSha)}</p><p>Obraz: ${escapeHtml(result.imageDigest)}</p></div>`;
      } else if (job) {
        body = `<p>Wdro\u017cenie na naszej infrastrukturze: ${escapeHtml(labels[data.status.release])}.</p>`;
      } else if (!digest) {
        body = "<p>Najpierw przygotuj aktualny podgl\u0105d dla klienta.</p>";
      } else if (!accepted) {
        body = "<p>Oczekujemy na pe\u0142n\u0105 akceptacj\u0119 klienta dla tej wersji podgl\u0105du. Po akceptacji wybierzesz spos\u00f3b publikacji.</p>";
      } else if (openFeedback) {
        body = "<p>Przed wyborem publikacji rozstrzygnij wszystkie uwagi klienta w etapie 13. Otwarta uwaga blokuje publikacje i przekazanie.</p>";
      } else if (!publicationPlan) {
        body = `<p>Aktualna podpisana umowa nie zawiera wybranej ścieżki publikacji. Uzupełnij w umowie sposób publikacji i warunki domeny oraz hostingu, przygotuj nową wersję do podpisu i potwierdź jej zawarcie.</p>${adminLink("#contract-panel")}`;
      } else if (publicationPlan.destination === "agency_purchase") {
        const terms=publicationPlan.domainProcurement || {};
        body=`<p>Zakup domeny dla klienta i hosting u nas — ustalenia z podpisanej umowy v${escapeHtml(publicationPlan.contractVersion)}.</p><p>Domena: <code>${escapeHtml(publicationPlan.domain)}</code></p><p>Rejestrator: ${escapeHtml(publicationPlan.registrar || "Do uzgodnienia przed zakupem")}</p>${Object.entries(terms).map(([key,value])=>`<p><strong>${escapeHtml(({domainRegistrant:"Abonent i upoważnienie",domainPurchaseTerms:"Zakup i koszt",domainAvailabilityTerms:"Dostępność",domainRenewalTerms:"Odnowienie",domainHandoverTerms:"Przekazanie domeny",hostingFee:"Hosting i opłaty",hostingPeriod:"Okres hostingu",hostingBackup:"Kopie",hostingExit:"Zakończenie hostingu",hostingDns:"DNS i TLS"})[key] || key)}</strong><br>${escapeHtml(value)}</p>`).join("")}
          <div class="feedback-closed"><strong>Wymagana obsługa domeny przed publikacją</strong><p>Sprawdź dostępność nazwy i zatwierdzony koszt, zarejestruj domenę na uzgodnionego abonenta i przygotuj DNS oraz HTTPS. Panel nie ma jeszcze integracji zakupu domen ani podłączania zewnętrznych domen do VPS. W tym wariancie automatyczna publikacja jest zablokowana, aby nie opublikować strony pod inną domeną. Podgląd klienta działa bez zmian.</p></div>`;
      } else if (publicationPlan.destination === "client_handoff") {
        body = `<p>Podgląd zaakceptowano ${date(data.case.previewAcceptedAt)}. Ustalenia pobrano z podpisanej umowy ${escapeHtml(data.contract.number || "")} v${escapeHtml(publicationPlan.contractVersion)}.</p>
          ${section("Dane z umowy", `<p>Domena: <code>${escapeHtml(publicationPlan.domain)}</code></p><p>Rejestrator / DNS: ${escapeHtml(publicationPlan.registrar || "Nie wskazano w umowie")}</p><p>Hosting: ${escapeHtml(publicationPlan.hostingProvider)}</p><p>Sposób uruchomienia: ${escapeHtml(publicationPlan.serverTarget || "Klient uruchamia przekazany pakiet samodzielnie")}</p><p>Prawo do domeny: ${escapeHtml(publicationPlan.verificationEvidence)}</p><p>DNS i TLS: ${escapeHtml(publicationPlan.dnsTlsPlan)}</p><p>Kopie zapasowe: ${escapeHtml(publicationPlan.backupPlan)}</p><p>Przekazanie: ${escapeHtml(publicationPlan.deliverables)}</p><p>Odpowiedzialność: ${escapeHtml(publicationPlan.responsibilities)}</p><p>Wsparcie: ${escapeHtml(publicationPlan.supportPlan)}</p><p>Ustalenia z umowy v${escapeHtml(publicationPlan.contractVersion)}</p>`, true)}
          <div class="detail-section wide"><h3>Potwierdzenie gotowości</h3><p>Nie przepisujesz ponownie danych z umowy. Sprawdź, że domena istnieje i klient kontroluje jej DNS. Potem zamknij publikację i przejdź do przekazania pakietu.</p><form class="form" data-action="prepare_client_handover"><label><input name="confirmContractPlan" type="checkbox" required> Potwierdzam, że domena z umowy istnieje, a klient kontroluje jej DNS.</label><button class="primary">Zamknij publikację i przejdź do przekazania</button></form></div>`;
      } else {
        body = `<p>Podgląd zaakceptowano ${date(data.case.previewAcceptedAt)}. Umowa ${escapeHtml(data.contract.number || "")} v${escapeHtml(publicationPlan.contractVersion)} określa publikację na infrastrukturze Grzywniak.</p>
          <form class="form" data-action="approve_production"><label>Podstawa zgody na publikację<textarea name="evidence" rows="3" minlength="8" maxlength="1000" required></textarea></label><button class="primary">Zatwierdź publikację na naszej infrastrukturze</button></form>`;
      }
      if (job?.error) body += `<p class="error-text">${escapeHtml(job.error)}</p><form class="form" data-action="retry_job"><input type="hidden" name="kind" value="publish_production"><button class="primary">Pon\u00f3w wdro\u017cenie produkcyjne</button></form>`;
      return section("Publikacja produkcyjna", body, true);
    }
    if (stageId === "handover") {
      const handoff = data.case.clientHandoff || {};
      const clientRoute = data.case.publicationDestination === "client_handoff";
      const details = clientRoute ? `<div class="detail-section wide"><h3>Uzgodniona publikacja klienta</h3><p>Domena: ${escapeHtml(handoff.domain)} &middot; ${escapeHtml(handoff.registrar)}</p><p>Hosting: ${escapeHtml(handoff.hostingProvider)}</p><p>Serwer i uruchomienie: ${escapeHtml(handoff.serverTarget)}</p><p>DNS i TLS: ${escapeHtml(handoff.dnsTlsPlan)}</p><p>Kopie bezpieczeństwa: ${escapeHtml(handoff.backupPlan)}</p><p>Przekazywane materiały: ${escapeHtml(handoff.deliverables)}</p><p>Odpowiedzialność: ${escapeHtml(handoff.responsibilities)}</p><p>Wsparcie: ${escapeHtml(handoff.supportPlan)}</p></div>` : "";
      const content = data.case.handoverAt ? `<p>${clientRoute ? "Przekazanie przygotowanego pakietu zapisano" : "Przekazano wersję"} ${date(data.case.handoverAt)} &middot; ${escapeHtml(data.case.handoverDigest)}.</p><p>${escapeHtml(data.case.handoverNote)}</p>${details}` : data.status.handover === "needs_you" ? `${details}<form class="form" data-action="complete_handover"><label>${clientRoute ? "Potwierdzenie przekazania uzgodnionych materiałów klientowi (bez haseł i kluczy)" : "Jak przekazano dostęp, dokumentację i zasady wsparcia?"}<textarea name="note" rows="4" minlength="15" maxlength="2000" required></textarea></label><button class="primary">${clientRoute ? "Zapisz kompletne przekazanie klientowi" : "Zapisz przekazanie projektu"}</button></form>` : "<p>Najpierw zakończ publikację. Dla serwera klienta trzeba potwierdzić domenę, hosting, dostęp, zakres przekazania i wsparcie.</p>";
      const invoices = Array.isArray(data.case.invoiceInfo) ? data.case.invoiceInfo : [];
      const statuses = [["planned", "Planowana"], ["issued", "Wystawiona"], ["paid", "Opłacona"], ["cancelled", "Anulowana"]];
      const invoiceForm = (invoice = {}) => `<form class="form invoice-form" data-action="save_invoice_info"><input type="hidden" name="invoiceId" value="${escapeHtml(invoice.id || "")}"><label>Numer faktury<input name="number" maxlength="100" value="${escapeHtml(invoice.number || "")}"></label><label>Kwota brutto (PLN)<input name="amountGross" type="number" min="0" max="10000000" step="0.01" value="${escapeHtml(invoice.amountGross ?? "0.00")}" required></label><label>Data wystawienia<input name="issueDate" type="date" value="${escapeHtml(invoice.issueDate || "")}"></label><label>Termin p\u0142atno\u015bci<input name="dueDate" type="date" value="${escapeHtml(invoice.dueDate || "")}"></label><label>Data zap\u0142aty<input name="paidDate" type="date" value="${escapeHtml(invoice.paidDate || "")}"></label><label>Status<select name="status">${statuses.map(([value, title]) => `<option value="${value}"${(invoice.status || "planned") === value ? " selected" : ""}>${title}</option>`).join("")}</select></label><label class="wide">Notatka<textarea name="note" rows="2" maxlength="1000">${escapeHtml(invoice.note || "")}</textarea></label><button class="primary">${invoice.id ? "Zapisz zmiany" : "Dodaj wpis faktury"}</button></form>`;
      const invoiceContent = `<div class="detail-section wide invoice-register"><h3>Faktury - ewidencja informacyjna</h3><p>Panel zapisuje wyłącznie informacje. Nie tworzy faktury, nie wysyła jej klientowi i nie łączy się z księgowością.</p>${invoices.length ? invoices.map((invoice, index) => `<div class="invoice-entry"><strong>Wpis ${index + 1}${invoice.number ? ` - ${escapeHtml(invoice.number)}` : ""}</strong>${invoiceForm(invoice)}</div>`).join("") : "<p>Brak zapisanych wpisów.</p>"}<h4>Nowy wpis</h4>${invoiceForm()}</div>`;
      return section("Przekazanie i wsparcie", `${content}${invoiceContent}`, true);
    }
    return section("Wynik etapu", `<p>${data.status[stageId] === "locked" ? "Ten etap nie został jeszcze uruchomiony. Jego zadania i artefakty pojawią się tutaj po spełnieniu zależności." : "Etap jest gotowy. Koordynator utworzy zadania i przypisze je właściwym agentom."}</p>`, true);
  }

  function openCloseProjectDialog() {
    if (!project || project.case.closedAt) return;
    currentStage = null;
    previousFocus = document.activeElement;
    $("#dialog-agent").textContent = "ZAKO\u0143CZENIE PROJEKTU";
    $("#dialog-title").textContent = "Zatrzymaj wszystkie dzia\u0142ania";
    $("#dialog-content").innerHTML = `<div class="detail-grid"><section class="detail-section wide"><h3>Co zostanie zatrzymane</h3><p>Projekt zostanie trwale zamkni\u0119ty. Oczekuj\u0105ce zadania zostan\u0105 anulowane, system spr\u00f3buje zatrzyma\u0107 aktywne zadania runnera i zablokuje uruchamianie kolejnych agent\u00f3w oraz przyjmowanie uwag do tego projektu.</p><p>Je\u015bli runner nie potwierdzi zatrzymania lub kosztu, panel zachowa rezerwacj\u0119 do r\u0119cznego rozliczenia.</p><form class="form" data-action="close_project"><label>Pow\u00f3d zako\u0144czenia<textarea name="reason" rows="4" minlength="10" maxlength="1000" required></textarea></label><label><input name="confirmStop" type="checkbox" required> Potwierdzam trwa\u0142e zako\u0144czenie projektu i zatrzymanie pracy agent\u00f3w.</label><button class="primary">Zako\u0144cz projekt i zatrzymaj agent\u00f3w</button></form></section></div>`;
    $("#overlay").hidden = false;
    document.body.style.overflow = "hidden";
    $("#dialog-content").querySelector("textarea")?.focus();
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

  async function submitAction(form, submitter) {
    const button = submitter || form.querySelector("button");
    if (form.dataset.action === "asset_upload" || form.dataset.action === "asset_review") {
      button.disabled = true;
      try {
        const body = new FormData(form); body.set("action", form.dataset.action === "asset_upload" ? "upload" : "review");
        const response = await fetch(`${apiPath("project-assets.php")}?session=${encodeURIComponent(id)}`, { method: "POST", credentials: "same-origin", headers: { "X-CSRF-Token": csrf }, body });
        const result = await response.json(); if (!response.ok) throw new Error(result.message || "Nie uda\u0142o si\u0119 zapisa\u0107 materia\u0142u.");
        await load(false); if (currentStage) openStage(currentStage, false);
      } catch (error) { const message = document.createElement("p"); message.className = "error-text"; message.setAttribute("role", "alert"); message.textContent = error.message; form.append(message); button.disabled = false; }
      return;
    }
    const data = Object.fromEntries(new FormData(form).entries());
    data.action = submitter?.dataset.action || form.dataset.action;
    button.disabled = true;
    try {
      const response = await fetch(endpoint, { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json", "X-CSRF-Token": csrf }, body: JSON.stringify(data) });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || "Nie udało się zapisać.");
      if (data.action === "close_project") { closeStage(); await load(false); }
      else if (data.action === "save_invoice_info") { const stage = currentStage; await load(false); if (stage) openStage(stage, false); }
      else await load(true);
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
    submitAction(form, event.submitter);
  });
  let lightboxTrigger;
  function closeFeedbackLightbox() {
    const lightbox = $(".feedback-lightbox");
    if (!lightbox) return;
    lightbox.remove();
    lightboxTrigger?.focus?.();
    lightboxTrigger = null;
  }
  $("#dialog-content").addEventListener("click", (event) => {
    const trigger = event.target.closest(".feedback-image-open");
    if (!trigger) return;
    const source = trigger.querySelector("img")?.currentSrc;
    if (!source) return;
    closeFeedbackLightbox();
    lightboxTrigger = trigger;
    const lightbox = document.createElement("div");
    lightbox.className = "feedback-lightbox";
    lightbox.setAttribute("role", "dialog");
    lightbox.setAttribute("aria-modal", "true");
    lightbox.setAttribute("aria-label", "Powiększony obraz zaznaczenia klienta");
    const close = document.createElement("button");
    close.type = "button";
    close.textContent = "Zamknij ✕";
    close.setAttribute("aria-label", "Zamknij powiększony obraz");
    close.addEventListener("click", closeFeedbackLightbox);
    const imageWrap = document.createElement("div");
    imageWrap.className = "feedback-lightbox-image-wrap";
    const image = document.createElement("img");
    image.src = source;
    image.alt = trigger.querySelector("img")?.alt || "Zrzut strony z zaznaczeniem klienta";
    const selection = document.createElement("span");
    selection.className = "feedback-lightbox-selection";
    selection.style.left = `${trigger.dataset.markLeft}%`;
    selection.style.top = `${trigger.dataset.markTop}%`;
    selection.style.width = `${trigger.dataset.markWidth}%`;
    selection.style.height = `${trigger.dataset.markHeight}%`;
    imageWrap.append(image, selection);
    lightbox.addEventListener("click", (closeEvent) => { if (closeEvent.target === lightbox) closeFeedbackLightbox(); });
    lightbox.append(close, imageWrap);
    document.body.append(lightbox);
    close.focus();
  });
  $("#close").addEventListener("click", closeStage);
  $("#close-project").addEventListener("click", openCloseProjectDialog);
  $("#overlay").addEventListener("click", (event) => { if (event.target.id === "overlay") closeStage(); });
  document.addEventListener("keydown", (event) => { if (event.key === "Escape" && $(".feedback-lightbox")) { closeFeedbackLightbox(); return; } if (event.key === "Escape" && !$("#overlay").hidden) closeStage(); });
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
    if (document.hidden || $("#dialog-content form :focus") || $(".feedback-lightbox")) return;
    load(Boolean(currentStage)).catch(showError);
  }, 5000);
})();
