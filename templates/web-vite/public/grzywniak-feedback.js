(() => {
  if (window.__grzywniakFeedbackLoaded) return;
  window.__grzywniakFeedbackLoaded = true;

  const style = document.createElement("style");
  style.textContent = `
    #gw-feedback-launcher{position:fixed;z-index:2147483000;right:22px;bottom:22px;border:0;border-radius:999px;padding:13px 18px;background:#183b63;color:#fff;font:600 14px system-ui;box-shadow:0 8px 32px #0004;cursor:pointer}
    #gw-feedback-panel{position:fixed;z-index:2147483001;right:22px;bottom:80px;width:min(460px,calc(100vw - 28px));max-height:min(78vh,700px);overflow:auto;padding:18px;background:#fff;color:#182230;border:1px solid #cfdae5;border-radius:16px;box-shadow:0 16px 50px #0004;font:14px/1.45 system-ui;display:none}
    #gw-feedback-panel.gw-open{display:block}#gw-feedback-panel *{box-sizing:border-box}#gw-feedback-panel h2{font-size:18px;margin:0 38px 6px 0}#gw-feedback-panel p{margin:6px 0 14px;color:#536273}
    #gw-feedback-panel textarea{width:100%;min-height:105px;resize:vertical;border:1px solid #aab7c5;border-radius:9px;padding:10px;font:inherit;color:#17212c}
    #gw-feedback-panel button{border:0;border-radius:8px;padding:10px 13px;font:600 14px system-ui;cursor:pointer}#gw-feedback-panel button:disabled{opacity:.55;cursor:wait}#gw-feedback-panel .gw-primary{background:#245a91;color:#fff;width:100%;margin-top:10px}#gw-feedback-panel .gw-select{background:#e8f0f8;color:#173b5d}#gw-feedback-panel .gw-close{position:absolute;right:12px;top:12px;background:#edf1f5;color:#263747;padding:6px 10px}
    #gw-feedback-panel .gw-note{font-size:12px}#gw-feedback-panel .gw-message{min-height:20px;margin:10px 0 0;color:#245a36}#gw-feedback-areas{display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:9px;margin:12px 0}#gw-feedback-areas:empty{display:none}
    #gw-feedback-panel .gw-area-card{position:relative;min-width:0;padding:8px;border:1px solid #d5deea;border-radius:10px;background:#f7f9fc}#gw-feedback-panel .gw-area-card strong{display:block;margin-bottom:6px;font-size:12px;color:#35465b}#gw-feedback-panel .gw-thumb{position:relative;display:block;overflow:hidden;max-width:100%;padding:0;background:#e8edf4;border:1px solid #cbd5e1;border-radius:6px;cursor:zoom-in}#gw-feedback-panel .gw-thumb iframe{position:absolute;top:0;left:0;border:0;transform-origin:top left;pointer-events:none;background:#fff}#gw-feedback-panel .gw-thumb-selection{position:absolute;border:2px solid #e24646;border-radius:5px;box-shadow:inset 0 0 0 999px #e2464614;pointer-events:none}#gw-feedback-panel .gw-area-note{display:block;width:100%;min-height:54px;margin-top:8px;resize:vertical;border:1px solid #c7d1df;border-radius:7px;padding:7px;font:12px/1.4 system-ui;color:#17212c;background:#fff}#gw-feedback-panel .gw-area-card .gw-remove{position:absolute;right:6px;top:5px;padding:3px 7px;background:#e9edf3;color:#344458;font-size:12px}
    #gw-feedback-viewer{position:fixed;inset:0;z-index:2147483004;display:none;align-items:center;justify-content:center;padding:24px;background:#07111de8;color:#fff;font:14px/1.45 system-ui}#gw-feedback-viewer.gw-open{display:flex}#gw-feedback-viewer .gw-viewer-dialog{position:relative;display:flex;flex-direction:column;gap:12px;max-width:100%;max-height:100%}#gw-feedback-viewer .gw-viewer-title{padding-right:52px;font-size:16px}#gw-feedback-viewer .gw-viewer-close{position:absolute;right:0;top:-6px;border:0;border-radius:8px;background:#fff;color:#142033;font-size:24px;line-height:1;padding:7px 12px;cursor:pointer}#gw-feedback-viewer .gw-viewer-crop{position:relative;overflow:hidden;max-width:calc(100vw - 48px);max-height:calc(100vh - 100px);background:#fff;border:2px solid #fff;border-radius:8px}#gw-feedback-viewer iframe{position:absolute;top:0;left:0;border:0;transform-origin:top left;pointer-events:none;background:#fff}#gw-feedback-viewer .gw-viewer-selection{position:absolute;border:3px solid #e24646;box-shadow:inset 0 0 0 999px #e2464614;pointer-events:none}
    #gw-feedback-panel .gw-thumb:focus-visible{outline:3px solid #245a91;outline-offset:2px}
    #gw-feedback-selection{position:fixed;inset:0;z-index:2147483002;cursor:crosshair;touch-action:none;background:#12395c10;display:none}
    #gw-feedback-selection.gw-selecting{display:block}#gw-feedback-selection:after{content:"Przeciągnij, aby zaznaczyć obszar · Esc anuluje";position:fixed;top:14px;left:50%;transform:translateX(-50%);background:#12395c;color:#fff;padding:9px 14px;border-radius:999px;font:13px system-ui;white-space:nowrap}
    #gw-feedback-rect{position:fixed;z-index:2147483003;border:2px solid #2484e8;background:#2484e82b;pointer-events:none;display:none}
    @media(max-width:520px){#gw-feedback-launcher{right:14px;bottom:14px}#gw-feedback-panel{right:14px;bottom:72px;width:calc(100vw - 28px);max-height:80vh}#gw-feedback-areas{grid-template-columns:1fr 1fr}}
  `;
  document.head.append(style);

  const launcher = document.createElement("button");
  launcher.id = "gw-feedback-launcher";
  launcher.type = "button";
  launcher.textContent = "Zgłoś uwagę";
  const panel = document.createElement("section");
  panel.id = "gw-feedback-panel";
  panel.setAttribute("aria-label", "Uwagi do podglądu");
  panel.innerHTML = '<button class="gw-close" type="button" aria-label="Zamknij">×</button><h2>Uwagi do podglądu</h2><p class="gw-note">Możesz wskazać kilka miejsc, a potem opisać je jednym zgłoszeniem.</p><button class="gw-select" type="button">Zaznacz obszar na stronie</button><div id="gw-feedback-areas" aria-live="polite"></div><form><textarea required minlength="10" maxlength="4000" placeholder="Opisz problem lub zmianę (min. 10 znaków)"></textarea><button class="gw-primary" type="submit">Wyślij uwagę</button></form><p class="gw-message" role="status" aria-live="polite"></p>';
  const selection = document.createElement("div");
  selection.id = "gw-feedback-selection";
  const rectEl = document.createElement("div");
  rectEl.id = "gw-feedback-rect";
  const viewer = document.createElement("div");
  viewer.id = "gw-feedback-viewer";
  viewer.setAttribute("role", "dialog");
  viewer.setAttribute("aria-modal", "true");
  viewer.setAttribute("aria-label", "Powiększony podgląd zaznaczonego obszaru");
  viewer.innerHTML = '<div class="gw-viewer-dialog"><strong class="gw-viewer-title"></strong><button class="gw-viewer-close" type="button" aria-label="Zamknij powiększony podgląd">×</button><div class="gw-viewer-crop"></div></div>';
  document.body.append(launcher, panel, selection, rectEl, viewer);

  const close = panel.querySelector(".gw-close");
  const select = panel.querySelector(".gw-select");
  const areasEl = panel.querySelector("#gw-feedback-areas");
  const message = panel.querySelector(".gw-message");
  const form = panel.querySelector("form");
  const viewerCrop = viewer.querySelector(".gw-viewer-crop");
  const viewerTitle = viewer.querySelector(".gw-viewer-title");
  const closeViewer = viewer.querySelector(".gw-viewer-close");
  let config = null;
  let annotations = [];
  let start = null;
  let viewerReturnTarget = null;

  const hideViewer = () => {
    viewer.classList.remove("gw-open");
    viewerCrop.replaceChildren();
    if (viewerReturnTarget?.isConnected) viewerReturnTarget.focus();
  };
  const showViewer = (annotation, index, returnTarget) => {
    viewerReturnTarget = returnTarget;
    const rect = annotation.rect;
    const areaWidth = Math.max(1, rect.width * annotation.viewport.width);
    const areaHeight = Math.max(1, rect.height * annotation.viewport.height);
    const scale = Math.min((innerWidth - 64) / areaWidth, (innerHeight - 130) / areaHeight);
    const cropWidth = areaWidth * scale;
    const cropHeight = areaHeight * scale;
    const frame = document.createElement("iframe");
    frame.title = `Powiększony obszar ${index + 1}`;
    frame.style.width = `${annotation.viewport.width}px`;
    frame.style.height = `${annotation.viewport.height}px`;
    frame.style.left = `${-rect.x * annotation.viewport.width * scale}px`;
    frame.style.top = `${-rect.y * annotation.viewport.height * scale}px`;
    frame.style.transform = `scale(${scale})`;
    frame.srcdoc = annotation.snapshot;
    const highlight = document.createElement("span");
    highlight.className = "gw-viewer-selection";
    highlight.style.inset = "0";
    viewerTitle.textContent = `Powiększony podgląd · obszar ${index + 1}`;
    viewerCrop.style.width = `${cropWidth}px`;
    viewerCrop.style.height = `${cropHeight}px`;
    viewerCrop.replaceChildren(frame, highlight);
    viewer.classList.add("gw-open");
    closeViewer.focus();
  };

  launcher.addEventListener("click", () => panel.classList.toggle("gw-open"));
  close.addEventListener("click", () => panel.classList.remove("gw-open"));
  closeViewer.addEventListener("click", hideViewer);
  viewer.addEventListener("click", (event) => { if (event.target === viewer) hideViewer(); });
  select.addEventListener("click", () => {
    if (annotations.length >= 8) { message.textContent = "Możesz dodać maksymalnie 8 obszarów do jednego zgłoszenia."; return; }
    message.textContent = "";
    panel.classList.remove("gw-open");
    selection.classList.add("gw-selecting");
  });

  const point = (event) => ({ x: Math.max(0, Math.min(innerWidth, event.clientX)), y: Math.max(0, Math.min(innerHeight, event.clientY)) });
  const captureSnapshot = (scroll) => {
    const clone = document.documentElement.cloneNode(true);
    clone.querySelectorAll("script,iframe,object,embed,#gw-feedback-launcher,#gw-feedback-panel,#gw-feedback-selection,#gw-feedback-rect").forEach((node) => node.remove());
    clone.querySelectorAll("*").forEach((node) => {
      [...node.attributes].forEach((attribute) => {
        if (/^on/i.test(attribute.name) || (/^(href|src|action|formaction)$/i.test(attribute.name) && /^\s*javascript:/i.test(attribute.value))) node.removeAttribute(attribute.name);
      });
    });
    const body = clone.querySelector("body");
    if (body) { body.style.position = "relative"; body.style.left = `${-scroll.x}px`; body.style.top = `${-scroll.y}px`; }
    const head = clone.querySelector("head");
    if (head && !head.querySelector("base")) {
      const base = document.createElement("base");
      base.href = location.href;
      head.prepend(base);
    }
    return "<!doctype html>" + clone.outerHTML;
  };
  const addAreaCard = (annotation, index, snapshot) => {
    const rect = annotation.rect;
    const width = Math.max(1, rect.width * annotation.viewport.width);
    const height = Math.max(1, rect.height * annotation.viewport.height);
    const frame = document.createElement("iframe");
    frame.title = `Podgląd zaznaczonego obszaru ${index + 1}`;
    frame.setAttribute("aria-hidden", "true");
    frame.style.width = `${annotation.viewport.width}px`;
    frame.style.height = `${annotation.viewport.height}px`;
    frame.srcdoc = snapshot;
    const card = document.createElement("div");
    card.className = "gw-area-card";
    const title = document.createElement("strong");
    title.textContent = `Obszar ${index + 1}`;
    const remove = document.createElement("button");
    remove.className = "gw-remove";
    remove.type = "button";
    remove.textContent = "Usuń";
    remove.setAttribute("aria-label", `Usuń obszar ${index + 1}`);
    remove.addEventListener("click", () => { annotations.splice(index, 1); renderAreas(); });
    const thumb = document.createElement("div");
    thumb.className = "gw-thumb";
    thumb.setAttribute("role", "button");
    thumb.tabIndex = 0;
    thumb.setAttribute("aria-label", `Powiększ zaznaczony obszar ${index + 1}`);
    thumb.addEventListener("click", () => showViewer(annotation, index, thumb));
    thumb.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") { event.preventDefault(); showViewer(annotation, index, thumb); }
    });
    const highlight = document.createElement("span");
    highlight.className = "gw-thumb-selection";
    highlight.setAttribute("aria-hidden", "true");
    thumb.append(frame, highlight);
    const note = document.createElement("textarea");
    note.className = "gw-area-note";
    note.maxLength = 1000;
    note.placeholder = "Notatka do tego obszaru (opcjonalnie)";
    note.setAttribute("aria-label", `Notatka do obszaru ${index + 1}`);
    note.value = annotation.note || "";
    note.addEventListener("input", () => { annotation.note = note.value; });
    card.append(title, remove, thumb, note);
    areasEl.append(card);
    const ratio = width / height;
    const thumbWidth = Math.min(320, areasEl.clientWidth, 110 * ratio);
    const thumbHeight = thumbWidth / ratio;
    thumb.style.width = `${thumbWidth}px`;
    thumb.style.height = `${thumbHeight}px`;
    const scale = Math.min(thumb.clientWidth / width, thumb.clientHeight / height);
    const visibleWidth = width * scale;
    const visibleHeight = height * scale;
    const offsetX = (thumb.clientWidth - visibleWidth) / 2;
    const offsetY = (thumb.clientHeight - visibleHeight) / 2;
    frame.style.left = `${offsetX - rect.x * annotation.viewport.width * scale}px`;
    frame.style.top = `${offsetY - rect.y * annotation.viewport.height * scale}px`;
    frame.style.transform = `scale(${scale})`;
    highlight.style.left = `${offsetX}px`;
    highlight.style.top = `${offsetY}px`;
    highlight.style.width = `${visibleWidth}px`;
    highlight.style.height = `${visibleHeight}px`;
  };
  const renderAreas = () => {
    areasEl.replaceChildren();
    annotations.forEach((annotation, index) => addAreaCard(annotation, index, annotation.snapshot));
    select.textContent = annotations.length ? `Zaznacz kolejny obszar (${annotations.length}/8)` : "Zaznacz obszar na stronie";
  };

  selection.addEventListener("pointerdown", (event) => {
    event.preventDefault();
    start = point(event);
    selection.setPointerCapture(event.pointerId);
    rectEl.style.display = "block";
    rectEl.style.left = `${start.x}px`;
    rectEl.style.top = `${start.y}px`;
    rectEl.style.width = "0px";
    rectEl.style.height = "0px";
  });
  selection.addEventListener("pointermove", (event) => {
    if (!start) return;
    const current = point(event);
    const x = Math.min(start.x, current.x);
    const y = Math.min(start.y, current.y);
    rectEl.style.left = `${x}px`;
    rectEl.style.top = `${y}px`;
    rectEl.style.width = `${Math.abs(current.x - start.x)}px`;
    rectEl.style.height = `${Math.abs(current.y - start.y)}px`;
  });
  selection.addEventListener("pointerup", (event) => {
    if (!start) return;
    const end = point(event);
    const x = Math.min(start.x, end.x);
    const y = Math.min(start.y, end.y);
    const width = Math.abs(end.x - start.x);
    const height = Math.abs(end.y - start.y);
    selection.classList.remove("gw-selecting");
    rectEl.style.display = "none";
    if (width < 8 || height < 8) { start = null; return; }
    selection.style.display = "none";
    const target = document.elementFromPoint(x + width / 2, y + height / 2);
    selection.style.display = "";
    start = null;
    const element = target && !panel.contains(target) ? target : document.body;
    const scroll = { x: scrollX, y: scrollY };
    const snapshot = captureSnapshot(scroll);
    annotations.push({
      rect: { x: x / innerWidth, y: y / innerHeight, width: width / innerWidth, height: height / innerHeight },
      viewport: { width: innerWidth, height: innerHeight },
      scroll,
      element: { tag: element.tagName.toLowerCase(), id: element.id || "", classes: typeof element.className === "string" ? element.className.slice(0, 240) : "", text: (element.innerText || "").trim().slice(0, 240) },
      note: "",
      snapshot,
    });
    panel.classList.add("gw-open");
    renderAreas();
  });
  document.addEventListener("keydown", (event) => {
    if (viewer.classList.contains("gw-open") && event.key === "Escape") { hideViewer(); return; }
    if (viewer.classList.contains("gw-open") && event.key === "Tab") { event.preventDefault(); closeViewer.focus(); return; }
    if (event.key === "Escape") { start = null; selection.classList.remove("gw-selecting"); rectEl.style.display = "none"; panel.classList.add("gw-open"); }
  });

  fetch("/.well-known/grzywniak/feedback-config", { credentials: "same-origin", cache: "no-store" })
    .then((response) => { if (!response.ok) throw new Error("Uwagi nie są jeszcze aktywne."); return response.json(); })
    .then((value) => { config = value; })
    .catch(() => { launcher.title = "Formularz uwag zostanie włączony po zatwierdzeniu podglądu."; launcher.disabled = true; launcher.textContent = "Uwagi niedostępne"; });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    message.textContent = "";
    if (!config) { message.textContent = "Formularz uwag nie jest aktywny dla tej wersji."; return; }
    if (!annotations.length) { message.textContent = "Najpierw zaznacz co najmniej jeden obszar strony."; return; }
    const submit = form.querySelector("button[type=submit]");
    submit.disabled = true;
    submit.textContent = "Wysyłanie…";
    try {
      const response = await fetch("/.well-known/grzywniak/feedback-submit", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ message: form.querySelector("textarea").value.trim(), page_url: location.href, annotation: annotations.length === 1 ? (({ snapshot, ...annotation }) => annotation)(annotations[0]) : { areas: annotations.map(({ snapshot, ...annotation }) => annotation) } }),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || `Serwer odrzucił zgłoszenie (HTTP ${response.status}).`);
      message.textContent = result.message || "Uwaga została zapisana.";
      form.reset();
      annotations = [];
      renderAreas();
    } catch (error) {
      message.textContent = error.message === "Failed to fetch" ? "Nie udało się połączyć z formularzem. Twoja treść pozostała na stronie — spróbuj ponownie." : (error.message || "Błąd połączenia.");
    } finally {
      submit.disabled = false;
      submit.textContent = "Wyślij uwagę";
    }
  });
})();
