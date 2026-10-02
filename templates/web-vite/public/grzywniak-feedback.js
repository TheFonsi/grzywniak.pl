(() => {
  if (window.__grzywniakFeedbackLoaded) return;
  window.__grzywniakFeedbackLoaded = true;

  const style = document.createElement("style");
  style.textContent = `
    #gw-feedback-launcher{position:fixed;z-index:2147483000;right:22px;bottom:22px;border:0;border-radius:999px;padding:13px 18px;background:#183b63;color:#fff;font:600 14px system-ui;box-shadow:0 8px 32px #0004;cursor:pointer}
    #gw-feedback-panel{position:fixed;z-index:2147483001;right:22px;bottom:80px;width:min(460px,calc(100vw - 28px));max-height:min(78vh,700px);overflow:auto;padding:18px;background:#fff;color:#182230;border:1px solid #cfdae5;border-radius:16px;box-shadow:0 16px 50px #0004;font:14px/1.45 system-ui;display:none}
    #gw-feedback-panel.gw-open{display:block}#gw-feedback-panel *{box-sizing:border-box}#gw-feedback-panel h2{font-size:18px;margin:0 38px 6px 0}#gw-feedback-panel p{margin:6px 0 14px;color:#536273}
    #gw-feedback-panel textarea{width:100%;min-height:105px;resize:vertical;border:1px solid #aab7c5;border-radius:9px;padding:10px;font:inherit;color:#17212c}
    #gw-feedback-panel button{border:0;border-radius:8px;padding:10px 13px;font:600 14px system-ui;cursor:pointer}#gw-feedback-panel button:disabled{opacity:.55;cursor:default!important}#gw-feedback-panel .gw-primary{background:#245a91;color:#fff;width:100%;margin-top:10px;cursor:pointer!important}#gw-feedback-panel .gw-primary:disabled{cursor:default!important}#gw-feedback-panel .gw-primary[aria-busy="true"]{cursor:default!important;opacity:.78}#gw-feedback-panel .gw-finish{width:100%;margin-top:10px;background:#e8f0f8;color:#173b5d}#gw-feedback-panel.gw-closed .gw-finish{display:none}#gw-feedback-panel.gw-closed .gw-primary{display:none}#gw-feedback-panel.gw-closed textarea,#gw-feedback-panel.gw-closed .gw-select,#gw-feedback-panel.gw-closed .gw-remove{display:none}#gw-feedback-panel .gw-select{background:#e8f0f8;color:#173b5d}#gw-feedback-panel .gw-close{position:absolute;right:12px;top:12px;background:#edf1f5;color:#263747;padding:6px 10px}#gw-feedback-panel .gw-finish-note{margin:12px 0 4px;padding:10px;border-radius:8px;background:#f3f6fa;color:#35465b;font-size:12px}#gw-feedback-panel .gw-completion{margin-top:12px;padding:12px;border:1px solid #8dc7a0;border-radius:9px;background:#edf8f0;color:#17552b;font-weight:600}#gw-feedback-panel .gw-accept{margin-top:12px;padding:12px;border:1px solid #9ab7da;border-radius:9px;background:#f0f5fb}#gw-feedback-panel .gw-accept p{margin:7px 0;color:#35465b;font-size:12px}#gw-feedback-panel .gw-accept-button{width:100%;background:#168455;color:#fff}#gw-feedback-panel .gw-accept-button:disabled{opacity:.65;cursor:default!important}#gw-feedback-panel .gw-accept[hidden]{display:none}
    #gw-feedback-panel .gw-note{font-size:12px}#gw-feedback-panel .gw-message{min-height:20px;margin:10px 0 0;color:#245a36}#gw-feedback-areas{display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:9px;margin:12px 0}#gw-feedback-areas:empty{display:none}
    #gw-feedback-panel .gw-area-card{position:relative;min-width:0;padding:8px;border:1px solid #d5deea;border-radius:10px;background:#f7f9fc}#gw-feedback-panel .gw-area-card strong{display:block;margin-bottom:6px;font-size:12px;color:#35465b}#gw-feedback-panel .gw-thumb{position:relative;display:block;overflow:hidden;max-width:100%;padding:0;background:#e8edf4;border:1px solid #cbd5e1;border-radius:6px;cursor:zoom-in}#gw-feedback-panel .gw-thumb iframe,#gw-feedback-panel .gw-thumb img{position:absolute;top:0;left:0;border:0;transform-origin:top left;pointer-events:none;background:#fff}#gw-feedback-panel .gw-thumb-selection{position:absolute;border:2px solid #e24646;border-radius:5px;box-shadow:inset 0 0 0 999px #e2464614;pointer-events:none}#gw-feedback-panel .gw-area-note{display:block;width:100%;min-height:54px;margin-top:8px;resize:vertical;border:1px solid #c7d1df;border-radius:7px;padding:7px;font:12px/1.4 system-ui;color:#17212c;background:#fff}#gw-feedback-panel .gw-area-card .gw-remove{position:absolute;right:6px;top:5px;padding:3px 7px;background:#e9edf3;color:#344458;font-size:12px}
    #gw-feedback-viewer{position:fixed;inset:0;z-index:2147483004;display:none;align-items:center;justify-content:center;padding:24px;background:#07111de8;color:#fff;font:14px/1.45 system-ui}#gw-feedback-viewer.gw-open{display:flex}#gw-feedback-viewer .gw-viewer-dialog{position:relative;display:flex;flex-direction:column;gap:12px;max-width:100%;max-height:100%}#gw-feedback-viewer .gw-viewer-title{padding-right:52px;font-size:16px}#gw-feedback-viewer .gw-viewer-close{position:absolute;right:0;top:-6px;border:0;border-radius:8px;background:#fff;color:#142033;font-size:24px;line-height:1;padding:7px 12px;cursor:pointer}#gw-feedback-viewer .gw-viewer-crop{position:relative;overflow:hidden;max-width:calc(100vw - 48px);max-height:calc(100vh - 100px);background:#fff;border:2px solid #fff;border-radius:8px}#gw-feedback-viewer iframe,#gw-feedback-viewer img{position:absolute;top:0;left:0;border:0;transform-origin:top left;pointer-events:none;background:#fff}#gw-feedback-viewer .gw-viewer-selection{position:absolute;border:3px solid #e24646;box-shadow:inset 0 0 0 999px #e2464614;pointer-events:none}
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
  panel.innerHTML = '<button class="gw-close" type="button" aria-label="Zamknij">\u00d7</button><h2>Uwagi do podgl\u0105du</h2><p class="gw-note">Mo\u017cesz wskaza\u0107 kilka miejsc, a potem opisa\u0107 je jednym zg\u0142oszeniem.</p><button class="gw-select" type="button">Zaznacz obszar na stronie</button><div id="gw-feedback-areas" aria-live="polite"></div><form><textarea required minlength="10" maxlength="4000" placeholder="Opisz problem lub zmian\u0119 (min. 10 znak\u00f3w)"></textarea><button class="gw-primary" type="submit">Wy\u015blij uwag\u0119</button></form><p class="gw-finish-note">Gdy wy\u015blesz wszystkie uwagi, kliknij poni\u017cej. Po zamkni\u0119ciu nie b\u0119dzie mo\u017cna doda\u0107 kolejnych do tej wersji.</p><button class="gw-finish" type="button">Zako\u0144cz zg\u0142aszanie uwag</button><section class="gw-accept" hidden><strong>Akceptacja wersji</strong><p class="gw-accept-message"></p><button class="gw-accept-button" type="button">Akceptuj w pe\u0142ni t\u0119 wersj\u0119 do publikacji</button></section><div class="gw-completion" role="status" aria-live="polite" hidden></div><p class="gw-message" role="status" aria-live="polite"></p>';
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
  const finish = panel.querySelector(".gw-finish");
  const acceptSection = panel.querySelector(".gw-accept");
  const acceptButton = panel.querySelector(".gw-accept-button");
  const acceptMessage = panel.querySelector(".gw-accept-message");
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
  const setClosed = () => { config = config || {}; config.feedbackClosed = true; panel.classList.add("gw-closed"); launcher.disabled = false; launcher.textContent = "Uwagi zako\u0144czone"; launcher.title = "Zg\u0142aszanie uwag do tej wersji jest zako\u0144czone. Kliknij, aby zobaczy\u0107 potwierdzenie."; panel.querySelector(".gw-note").textContent = "Zg\u0142aszanie uwag do tej wersji zosta\u0142o zako\u0144czone."; const completion = panel.querySelector(".gw-completion"); completion.hidden = false; const closedAt = Number(config.feedbackClosedAt); const closedLabel = closedAt ? ` ${new Date(closedAt * 1000).toLocaleString("pl-PL")}.` : ""; completion.textContent = `Lista uwag zamkni\u0119ta${closedLabel}`; panel.querySelector(".gw-finish-note").hidden = true; form.querySelectorAll("button,textarea").forEach((control) => { control.disabled = true; }); select.disabled = true; renderAcceptance(); };
  const renderAcceptance = () => { if (!config?.feedbackClosed) { acceptSection.hidden = true; return; } acceptSection.hidden = false; const acceptedAt = Number(config.previewAcceptedAt); acceptMessage.textContent = config.previewAccepted ? `Dzi\u0119kujemy. Zaakceptowano w pe\u0142ni t\u0119 wersj\u0119 ${acceptedAt ? new Date(acceptedAt * 1000).toLocaleString("pl-PL") : ""}. Administrator podejmie decyzj\u0119 o publikacji.` : (config.acceptanceMessage || "Po zako\u0144czeniu i rozpatrzeniu wszystkich uwag mo\u017cesz zaakceptowa\u0107 t\u0119 wersj\u0119."); acceptButton.hidden = Boolean(config.previewAccepted); acceptButton.disabled = !config.canAccept; };

  const hideViewer = () => {
    viewer.classList.remove("gw-open");
    viewerCrop.replaceChildren();
    if (viewerReturnTarget?.isConnected) viewerReturnTarget.focus();
  };
  const showViewer = (annotation, index, returnTarget) => {
    viewerReturnTarget = returnTarget;
    const rect = annotation.rect;
    const context = annotation.context || { x: 0, y: 0, width: 1, height: 1 };
    const areaWidth = Math.max(1, context.width * annotation.viewport.width);
    const areaHeight = Math.max(1, context.height * annotation.viewport.height);
    const scale = Math.min((innerWidth - 64) / areaWidth, (innerHeight - 130) / areaHeight);
    const cropWidth = areaWidth * scale;
    const cropHeight = areaHeight * scale;
    let visual;
    if (annotation.screenshot) {
      visual = document.createElement("img");
      visual.alt = `Zrzut strony wokół obszaru ${index + 1}`;
      visual.src = annotation.screenshot;
      visual.style.cssText = `position:absolute;left:0;top:0;width:${cropWidth}px;height:${cropHeight}px;pointer-events:none`;
    } else {
      visual = document.createElement("iframe");
      visual.title = `Powiększony obszar ${index + 1}`;
      visual.style.width = `${annotation.viewport.width}px`;
      visual.style.height = `${annotation.viewport.height}px`;
      visual.style.left = `${-context.x * annotation.viewport.width * scale}px`;
      visual.style.top = `${-context.y * annotation.viewport.height * scale}px`;
      visual.style.transform = `scale(${scale})`;
      visual.srcdoc = annotation.snapshot;
    }
    const highlight = document.createElement("span");
    highlight.className = "gw-viewer-selection";
    highlight.style.left = `${(rect.x - context.x) * annotation.viewport.width * scale}px`;
    highlight.style.top = `${(rect.y - context.y) * annotation.viewport.height * scale}px`;
    highlight.style.width = `${rect.width * annotation.viewport.width * scale}px`;
    highlight.style.height = `${rect.height * annotation.viewport.height * scale}px`;
    viewerTitle.textContent = `Powiększony podgląd · obszar ${index + 1}`;
    viewerCrop.style.width = `${cropWidth}px`;
    viewerCrop.style.height = `${cropHeight}px`;
    viewerCrop.replaceChildren(visual, highlight);
    viewer.classList.add("gw-open");
    closeViewer.focus();
  };

  launcher.addEventListener("click", () => panel.classList.toggle("gw-open"));
  close.addEventListener("click", () => panel.classList.remove("gw-open"));
  closeViewer.addEventListener("click", hideViewer);
  viewer.addEventListener("click", (event) => { if (event.target === viewer) hideViewer(); });
  select.addEventListener("click", () => {
    if (config?.feedbackClosed) return;
    if (annotations.length >= 8) { message.textContent = "Możesz dodać maksymalnie 8 obszarów do jednego zgłoszenia."; return; }
    message.textContent = "";
    panel.classList.remove("gw-open");
    selection.classList.add("gw-selecting");
  });

  const point = (event) => ({ x: Math.max(0, Math.min(innerWidth, event.clientX)), y: Math.max(0, Math.min(innerHeight, event.clientY)) });
  const captureScreenshot = async (rect) => {
    if (typeof window.html2canvas !== "function") throw new Error("Nie załadował się moduł zrzutu obrazu. Odśwież podgląd i spróbuj ponownie.");
    const viewport = await window.html2canvas(document.documentElement, {
      x: scrollX, y: scrollY, width: innerWidth, height: innerHeight,
      windowWidth: innerWidth, windowHeight: innerHeight, scrollX, scrollY,
      scale: Math.min(1, 1200 / innerWidth), useCORS: true, allowTaint: false,
      backgroundColor: "#fff", logging: false,
      ignoreElements: (node) => /^gw-feedback-/.test(node.id || ""),
      onclone: (documentCopy) => {
        documentCopy.querySelectorAll('[id^="gw-feedback-"]').forEach((node) => node.remove());
        documentCopy.querySelectorAll("input,textarea,select").forEach((node) => { node.value = ""; node.removeAttribute("value"); node.removeAttribute("checked"); node.removeAttribute("selected"); if (node.tagName === "TEXTAREA") node.textContent = ""; });
      },
    });
    const pad = Math.min(120, Math.max(32, Math.max(rect.width * innerWidth, rect.height * innerHeight) * 0.35));
    const left = Math.max(0, rect.x * innerWidth - pad);
    const top = Math.max(0, rect.y * innerHeight - pad);
    const right = Math.min(innerWidth, (rect.x + rect.width) * innerWidth + pad);
    const bottom = Math.min(innerHeight, (rect.y + rect.height) * innerHeight + pad);
    const width = Math.max(1, right - left);
    const height = Math.max(1, bottom - top);
    const sx = viewport.width / innerWidth;
    const sy = viewport.height / innerHeight;
    let image = "";
    for (const edge of [840, 640, 460, 320]) {
      const scale = Math.min(1, edge / Math.max(width, height));
      const output = document.createElement("canvas");
      output.width = Math.max(1, Math.round(width * scale));
      output.height = Math.max(1, Math.round(height * scale));
      output.getContext("2d").drawImage(viewport, left * sx, top * sy, width * sx, height * sy, 0, 0, output.width, output.height);
      for (const quality of [0.64, 0.45, 0.3]) {
        image = output.toDataURL("image/jpeg", quality);
        if (image.length <= 68000) break;
      }
      if (image.length <= 68000) break;
    }
    if (image.length > 68000) throw new Error("Nie udało się zmniejszyć obrazu zaznaczenia. Zaznacz mniejszy obszar i spróbuj ponownie.");
    return { screenshot: image, context: { x: left / innerWidth, y: top / innerHeight, width: width / innerWidth, height: height / innerHeight } };
  };
  const addAreaCard = (annotation, index) => {
    const rect = annotation.rect;
    const context = annotation.context || { x: 0, y: 0, width: 1, height: 1 };
    const width = Math.max(1, context.width * annotation.viewport.width);
    const height = Math.max(1, context.height * annotation.viewport.height);
    let visual;
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
    thumb.append(highlight);
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
    if (annotation.screenshot) {
      visual = document.createElement("img");
      visual.alt = `Zrzut strony wokół obszaru ${index + 1}`;
      visual.src = annotation.screenshot;
      visual.style.cssText = `position:absolute;left:${offsetX}px;top:${offsetY}px;width:${visibleWidth}px;height:${visibleHeight}px;pointer-events:none`;
    } else {
      visual = document.createElement("iframe");
      visual.title = `Podgląd zaznaczonego obszaru ${index + 1}`;
      visual.setAttribute("aria-hidden", "true");
      visual.style.width = `${annotation.viewport.width}px`;
      visual.style.height = `${annotation.viewport.height}px`;
      visual.srcdoc = annotation.snapshot;
      visual.style.left = `${offsetX - context.x * annotation.viewport.width * scale}px`;
      visual.style.top = `${offsetY - context.y * annotation.viewport.height * scale}px`;
      visual.style.transform = `scale(${scale})`;
    }
    thumb.prepend(visual);
    const markX = (rect.x - context.x) / context.width;
    const markY = (rect.y - context.y) / context.height;
    highlight.style.left = `${offsetX + markX * visibleWidth}px`;
    highlight.style.top = `${offsetY + markY * visibleHeight}px`;
    highlight.style.width = `${rect.width / context.width * visibleWidth}px`;
    highlight.style.height = `${rect.height / context.height * visibleHeight}px`;
  };
  const renderAreas = () => {
    areasEl.replaceChildren();
    annotations.forEach((annotation, index) => addAreaCard(annotation, index));
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
  selection.addEventListener("pointerup", async (event) => {
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
    const rect = { x: x / innerWidth, y: y / innerHeight, width: width / innerWidth, height: height / innerHeight };
    const scroll = { x: scrollX, y: scrollY };
    try {
      const capture = await captureScreenshot(rect);
      annotations.push({
        rect, context: capture.context,
        viewport: { width: innerWidth, height: innerHeight },
        scroll,
        element: { tag: element.tagName.toLowerCase(), id: element.id || "", classes: typeof element.className === "string" ? element.className.slice(0, 240) : "", text: (element.innerText || "").trim().slice(0, 240) },
        note: "", screenshot: capture.screenshot,
      });
      panel.classList.add("gw-open");
      message.textContent = "Zrzut obrazu został dodany. Możesz dodać kolejne miejsce.";
      renderAreas();
    } catch (error) {
      panel.classList.add("gw-open");
      message.textContent = error.message || "Nie udało się utworzyć zrzutu obszaru. Spróbuj ponownie.";
    }
  });
  document.addEventListener("keydown", (event) => {
    if (viewer.classList.contains("gw-open") && event.key === "Escape") { hideViewer(); return; }
    if (viewer.classList.contains("gw-open") && event.key === "Tab") { event.preventDefault(); closeViewer.focus(); return; }
    if (event.key === "Escape") { start = null; selection.classList.remove("gw-selecting"); rectEl.style.display = "none"; panel.classList.add("gw-open"); }
  });

  fetch("/.well-known/grzywniak/feedback-config", { credentials: "same-origin", cache: "no-store" })
    .then((response) => { if (!response.ok) throw new Error("Uwagi nie są jeszcze aktywne."); return response.json(); })
    .then((value) => { config = value; if (value.feedbackClosed) setClosed(); else renderAcceptance(); })
    .catch(() => { launcher.title = "Formularz uwag zostanie włączony po zatwierdzeniu podglądu."; launcher.disabled = true; launcher.textContent = "Uwagi niedostępne"; });

  acceptButton.addEventListener("click", async () => {
    if (!config?.canAccept || config.previewAccepted) return;
    if (!confirm("Czy w pe\u0142ni akceptujesz t\u0119 konkretn\u0105 wersj\u0119 podgl\u0105du do publikacji? Po akceptacji uwagi do niej zostan\u0105 zamkni\u0119te; zmiany b\u0119d\u0105 wymaga\u0142y nowej wersji.")) return;
    acceptButton.disabled = true;
    acceptMessage.textContent = "Zapisywanie akceptacji wersji...";
    try {
      const response = await fetch("/.well-known/grzywniak/feedback-submit", { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ action: "accept" }) });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || "Nie uda\u0142o si\u0119 zapisa\u0107 akceptacji.");
      config.previewAccepted = true;
      config.previewAcceptedAt = Number(result.acceptedAt) || Math.floor(Date.now() / 1000);
      renderAcceptance();
    } catch (error) {
      acceptButton.disabled = false;
      acceptMessage.textContent = error.message || "Nie uda\u0142o si\u0119 zapisa\u0107 akceptacji.";
    }
  });

  finish.addEventListener("click", async () => {
    if (!config || config.feedbackClosed) return;
    if (annotations.length || form.querySelector("textarea").value.trim()) { message.textContent = "Wy\u015blij albo usu\u0144 bie\u017c\u0105c\u0105, niewys\u0142an\u0105 uwag\u0119 przed zako\u0144czeniem."; return; }
    if (!confirm("Czy wys\u0142a\u0142e\u015b ju\u017c wszystkie uwagi do tej wersji? Po zamkni\u0119ciu nie b\u0119dzie mo\u017cna doda\u0107 kolejnych.")) return;
    finish.disabled = true;
    message.textContent = "Zapisywanie zako\u0144czenia zg\u0142osze\u0144...";
    try {
      const response = await fetch("/.well-known/grzywniak/feedback-submit", { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ action: "close" }) });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || "Nie uda\u0142o si\u0119 zamkn\u0105\u0107 listy uwag.");
      config.feedbackClosedAt = Number(result.closedAt) || Math.floor(Date.now() / 1000);
      try { const statusResponse = await fetch("/.well-known/grzywniak/feedback-config", { credentials: "same-origin", cache: "no-store" }); if (statusResponse.ok) Object.assign(config, await statusResponse.json()); } catch {}
      setClosed();
      message.textContent = result.message || "Zg\u0142aszanie uwag zosta\u0142o zako\u0144czone.";
    } catch (error) {
      finish.disabled = false;
      message.textContent = error.message || "Nie uda\u0142o si\u0119 zapisa\u0107 zako\u0144czenia zg\u0142osze\u0144.";
    }
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    message.textContent = "";
    if (config?.feedbackClosed) { message.textContent = "Zg\u0142aszanie uwag do tej wersji zosta\u0142o zako\u0144czone."; return; }
    if (!config) { message.textContent = "Formularz uwag nie jest aktywny dla tej wersji."; return; }
    if (!annotations.length) { message.textContent = "Najpierw zaznacz co najmniej jeden obszar strony."; return; }
    const submit = form.querySelector("button[type=submit]");
    submit.disabled = true;
    submit.setAttribute("aria-busy", "true");
    submit.textContent = "Zapisywanie uwagi\u2026";
    try {
      const response = await fetch("/.well-known/grzywniak/feedback-submit", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ message: form.querySelector("textarea").value.trim(), page_url: `${location.origin}${location.pathname}${location.hash}`.slice(0, 1000), annotation: { areas: annotations } }),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(`${result.message || `Serwer odrzucił zgłoszenie (HTTP ${response.status}).`}${result.reference ? ` (ID: ${result.reference})` : ""}`);
      message.textContent = result.message || "Uwaga została zapisana.";
      form.reset();
      annotations = [];
      renderAreas();
    } catch (error) {
      message.textContent = error.message === "Failed to fetch" ? "Nie udało się połączyć z formularzem. Twoja treść pozostała na stronie — spróbuj ponownie." : (error.message || "Błąd połączenia.");
    } finally {
      submit.disabled = Boolean(config?.feedbackClosed);
      submit.removeAttribute("aria-busy");
      submit.textContent = "Wy\u015blij uwag\u0119";
    }
  });
})();
