(() => {
  if (window.__grzywniakFeedbackLoaded) return;
  window.__grzywniakFeedbackLoaded = true;
  const style = document.createElement("style");
  style.textContent = `
    #gw-feedback-launcher{position:fixed;z-index:2147483000;right:22px;bottom:22px;border:0;border-radius:999px;padding:13px 18px;background:#183b63;color:#fff;font:600 14px system-ui;box-shadow:0 8px 32px #0004;cursor:pointer}
    #gw-feedback-panel{position:fixed;z-index:2147483001;right:22px;bottom:80px;width:min(380px,calc(100vw - 28px));padding:18px;background:#fff;color:#182230;border:1px solid #cfdae5;border-radius:16px;box-shadow:0 16px 50px #0004;font:14px/1.45 system-ui;display:none}
    #gw-feedback-panel.gw-open{display:block}#gw-feedback-panel *{box-sizing:border-box}#gw-feedback-panel h2{font-size:18px;margin:0 38px 6px 0}#gw-feedback-panel p{margin:6px 0 14px;color:#536273}
    #gw-feedback-panel textarea{width:100%;min-height:105px;resize:vertical;border:1px solid #aab7c5;border-radius:9px;padding:10px;font:inherit;color:#17212c}
    #gw-feedback-panel button{border:0;border-radius:8px;padding:10px 13px;font:600 14px system-ui;cursor:pointer}#gw-feedback-panel .gw-primary{background:#245a91;color:#fff;width:100%;margin-top:10px}#gw-feedback-panel .gw-select{background:#e8f0f8;color:#173b5d}#gw-feedback-panel .gw-close{position:absolute;right:12px;top:12px;background:#edf1f5;color:#263747;padding:6px 10px}
    #gw-feedback-panel .gw-note{font-size:12px}#gw-feedback-panel .gw-message{min-height:20px;margin:10px 0 0;color:#245a36}
    #gw-feedback-selection{position:fixed;inset:0;z-index:2147483002;cursor:crosshair;touch-action:none;background:#12395c10;display:none}
    #gw-feedback-selection.gw-selecting{display:block}#gw-feedback-selection:after{content:"Przeciągnij, aby zaznaczyć obszar · Esc anuluje";position:fixed;top:14px;left:50%;transform:translateX(-50%);background:#12395c;color:#fff;padding:9px 14px;border-radius:999px;font:13px system-ui;white-space:nowrap}
    #gw-feedback-rect{position:fixed;z-index:2147483003;border:2px solid #2484e8;background:#2484e82b;pointer-events:none;display:none}
    @media(max-width:520px){#gw-feedback-launcher{right:14px;bottom:14px}#gw-feedback-panel{right:14px;bottom:72px}}
  `;
  document.head.append(style);
  const launcher = document.createElement("button");
  launcher.id = "gw-feedback-launcher"; launcher.type = "button"; launcher.textContent = "Zgłoś uwagę";
  const panel = document.createElement("section"); panel.id = "gw-feedback-panel"; panel.setAttribute("aria-label", "Uwagi do podglądu");
  panel.innerHTML = '<button class="gw-close" type="button" aria-label="Zamknij">×</button><h2>Uwagi do podglądu</h2><p class="gw-note">Wybierz fragment strony, którego dotyczy zgłoszenie.</p><button class="gw-select" type="button">Zaznacz obszar na stronie</button><p class="gw-note gw-area">Nie wybrano obszaru.</p><form><textarea required minlength="10" maxlength="4000" placeholder="Opisz problem lub zmianę (min. 10 znaków)"></textarea><button class="gw-primary" type="submit">Wyślij uwagę</button></form><p class="gw-message" role="status" aria-live="polite"></p>';
  const selection = document.createElement("div"); selection.id = "gw-feedback-selection";
  const rectEl = document.createElement("div"); rectEl.id = "gw-feedback-rect";
  document.body.append(launcher, panel, selection, rectEl);
  const close = panel.querySelector(".gw-close"); const select = panel.querySelector(".gw-select"); const areaLabel = panel.querySelector(".gw-area"); const message = panel.querySelector(".gw-message"); const form = panel.querySelector("form");
  let config = null; let annotation = null; let start = null;
  launcher.addEventListener("click", () => panel.classList.toggle("gw-open"));
  close.addEventListener("click", () => panel.classList.remove("gw-open"));
  select.addEventListener("click", () => { panel.classList.remove("gw-open"); annotation = null; selection.classList.add("gw-selecting"); });
  const point = (event) => ({ x: Math.max(0, Math.min(innerWidth, event.clientX)), y: Math.max(0, Math.min(innerHeight, event.clientY)) });
  selection.addEventListener("pointerdown", (event) => { event.preventDefault(); start = point(event); selection.setPointerCapture(event.pointerId); rectEl.style.display = "block"; rectEl.style.left = `${start.x}px`; rectEl.style.top = `${start.y}px`; rectEl.style.width = "0px"; rectEl.style.height = "0px"; });
  selection.addEventListener("pointermove", (event) => { if (!start) return; const p = point(event); const x = Math.min(start.x, p.x); const y = Math.min(start.y, p.y); rectEl.style.left = `${x}px`; rectEl.style.top = `${y}px`; rectEl.style.width = `${Math.abs(p.x - start.x)}px`; rectEl.style.height = `${Math.abs(p.y - start.y)}px`; });
  selection.addEventListener("pointerup", (event) => {
    if (!start) return;
    const end = point(event); const x = Math.min(start.x, end.x); const y = Math.min(start.y, end.y); const width = Math.abs(end.x - start.x); const height = Math.abs(end.y - start.y);
    selection.classList.remove("gw-selecting"); rectEl.style.display = "none";
    if (width < 8 || height < 8) { start = null; return; }
    const oldDisplay = selection.style.display; selection.style.display = "none"; const target = document.elementFromPoint(x + width / 2, y + height / 2); selection.style.display = oldDisplay; start = null;
    const el = target && !panel.contains(target) ? target : document.body;
    annotation = { rect: { x: x / innerWidth, y: y / innerHeight, width: width / innerWidth, height: height / innerHeight }, viewport: { width: innerWidth, height: innerHeight }, scroll: { x: scrollX, y: scrollY }, element: { tag: el.tagName.toLowerCase(), id: el.id || "", classes: typeof el.className === "string" ? el.className.slice(0, 240) : "", text: (el.innerText || "").trim().slice(0, 240) } };
    areaLabel.textContent = `Zaznaczono obszar ${Math.round(width)} × ${Math.round(height)} px · ${annotation.element.tag}${annotation.element.id ? `#${annotation.element.id}` : ""}`;
    panel.classList.add("gw-open");
  });
  document.addEventListener("keydown", (event) => { if (event.key === "Escape") { start = null; selection.classList.remove("gw-selecting"); rectEl.style.display = "none"; } });
  fetch("/.well-known/grzywniak/feedback-config", { credentials: "same-origin", cache: "no-store" }).then((response) => { if (!response.ok) throw new Error("Uwagi nie są jeszcze aktywne."); return response.json(); }).then((value) => { config = value; }).catch(() => { launcher.title = "Formularz uwag zostanie włączony po wysłaniu podglądu klientowi."; launcher.disabled = true; launcher.textContent = "Uwagi niedostępne"; });
  form.addEventListener("submit", async (event) => {
    event.preventDefault(); message.textContent = "";
    if (!config || !annotation) { message.textContent = "Najpierw zaznacz obszar strony."; return; }
    const submit = form.querySelector("button[type=submit]"); submit.disabled = true; submit.textContent = "Wysyłanie…";
    try {
      const endpoint = new URL(config.feedbackUrl); const response = await fetch(`${endpoint.origin}${endpoint.pathname}?project=${encodeURIComponent(config.projectId)}`, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ project: config.projectId, token: config.feedbackToken, message: form.querySelector("textarea").value.trim(), page_url: location.href, annotation }) });
      const result = await response.json(); if (!response.ok) throw new Error(result.message || "Nie udało się wysłać uwagi.");
      message.textContent = result.message; form.reset(); annotation = null; areaLabel.textContent = "Nie wybrano obszaru.";
    } catch (error) { message.textContent = error.message || "Błąd połączenia."; }
    finally { submit.disabled = false; submit.textContent = "Wyślij uwagę"; }
  });
})();
