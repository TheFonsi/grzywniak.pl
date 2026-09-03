import { lazy, Suspense, useEffect, useRef, useState } from "react";
import { I18nProvider, languages, t, useLanguage } from "./i18n";
import aboutPortrait from "./assets/about-consultant.jpg";
import firstConversation from "./assets/first-conversation.jpg";
import contactDesk from "./assets/contact-desk.jpg";
import serverConfiguration from "./assets/server-configuration.jpg";
import workflowPlanning from "./assets/workflow-planning.jpg";
import oneContact from "./assets/one-contact.jpg";
import manualProcess from "./assets/manual-process.jpg";
import oneContactMobile from "./assets/responsive/one-contact.webp";
import manualProcessMobile from "./assets/responsive/manual-process.webp";
import workflowPlanningMobile from "./assets/responsive/workflow-planning.webp";
import serverConfigurationMobile from "./assets/responsive/server-configuration.webp";
import firstConversationMobile from "./assets/responsive/first-conversation.webp";
import aboutPortraitMobile from "./assets/responsive/about-consultant.webp";
import contactDeskMobile from "./assets/responsive/contact-desk.webp";
import oneContactRetina from "./assets/responsive/retina/one-contact.webp";
import workflowPlanningRetina from "./assets/responsive/retina/workflow-planning.webp";
import serverConfigurationRetina from "./assets/responsive/retina/server-configuration.webp";
import firstConversationRetina from "./assets/responsive/retina/first-conversation.webp";
import contactDeskRetina from "./assets/responsive/retina/contact-desk.webp";

const HeroCanvas = lazy(() => import("./HeroCanvas"));

function useMobilePerformanceProfile() {
  const [isMobile, setIsMobile] = useState(() => typeof window !== "undefined" && window.matchMedia("(max-width: 767px)").matches);

  useEffect(() => {
    const query = window.matchMedia("(max-width: 767px)");
    const update = () => setIsMobile(query.matches);
    update();
    query.addEventListener("change", update);
    return () => query.removeEventListener("change", update);
  }, []);

  return isMobile;
}

// ─── Custom Cursor ───────────────────────────────────────────────────────────
function CustomCursor() {
  const glowRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const isMobile = window.matchMedia("(max-width: 768px)").matches;
    if (isMobile) return;

    const onMove = (e: MouseEvent) => {
      const target = e.target as HTMLElement;
      const isInteractive = target.closest("a, button, [role=button], input, textarea, select");
      if (glowRef.current) {
        const size = isInteractive ? 220 : 150;
        // Keep the glow's centre attached to the pointer while its size changes.
        // Negative margins animate independently from width/height and briefly make
        // the glow appear to jump towards a corner.
        glowRef.current.style.transform = `translate3d(${e.clientX}px, ${e.clientY}px, 0) translate(-50%, -50%)`;
        glowRef.current.style.opacity = isInteractive ? "0.9" : "0.52";
        glowRef.current.style.width = `${size}px`;
        glowRef.current.style.height = `${size}px`;
      }
    };

    window.addEventListener("mousemove", onMove);
    return () => window.removeEventListener("mousemove", onMove);
  }, []);

  return (
    <>
      <div
        ref={glowRef}
        className="fixed z-[9995] pointer-events-none top-0 left-0"
        style={{ width: 150, height: 150, opacity: 0, borderRadius: "50%", background: "radial-gradient(circle, rgba(91,110,245,0.16) 0%, rgba(91,110,245,0.06) 35%, transparent 72%)", transition: "width 0.3s ease, height 0.3s ease, opacity 0.3s ease", willChange: "transform, width, height, opacity" }}
      />
    </>
  );
}

function ClickFeedback() {
  const [pulses, setPulses] = useState<{ id: number; x: number; y: number }[]>([]);
  const isMobile = useMobilePerformanceProfile();

  useEffect(() => {
    let id = 0;
    const onPointerDown = (event: PointerEvent) => {
      if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;

      const pulse = { id: id++, x: event.clientX, y: event.clientY };
      setPulses((current) => isMobile ? [pulse] : [...current, pulse]);
      window.setTimeout(() => setPulses((current) => current.filter((item) => item.id !== pulse.id)), isMobile ? 620 : 900);
    };

    window.addEventListener("pointerdown", onPointerDown, { passive: true });
    return () => window.removeEventListener("pointerdown", onPointerDown);
  }, [isMobile]);

  return (
    <div className="fixed inset-0 z-[9993] pointer-events-none overflow-hidden" aria-hidden="true">
      {pulses.map((pulse) => (
        <span key={pulse.id} className="click-seismic" style={{ left: pulse.x, top: pulse.y }}>
          <i />
          <i />
        </span>
      ))}
    </div>
  );
}

// ─── Scroll Progress ─────────────────────────────────────────────────────────
function ScrollProgress() {
  const barRef = useRef<HTMLDivElement>(null);
  useEffect(() => {
    let frame = 0;
    const onScroll = () => {
      if (frame) return;
      frame = requestAnimationFrame(() => {
        const max = document.documentElement.scrollHeight - window.innerHeight;
        const pct = max > 0 ? (window.scrollY / max) * 100 : 0;
        if (barRef.current) barRef.current.style.transform = `scaleX(${pct / 100})`;
        frame = 0;
      });
    };
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
    return () => { window.removeEventListener("scroll", onScroll); cancelAnimationFrame(frame); };
  }, []);
  return (
    <div className="fixed top-0 left-0 right-0 h-px z-[9990]">
      <div ref={barRef} className="h-full" style={{ background: "linear-gradient(90deg, #5b6ef5, #7c8dff)", transform: "scaleX(0)", transformOrigin: "left" }} />
    </div>
  );
}

function ScrollReveal({ children }: { children: React.ReactNode }) {
  const ref = useRef<HTMLDivElement>(null);
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const element = ref.current;
    if (!element) return;

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setVisible(true);
          observer.unobserve(entry.target);
        }
      },
      { threshold: 0.08, rootMargin: "0px 0px -6% 0px" },
    );

    observer.observe(element);
    return () => observer.disconnect();
  }, []);

  return <div ref={ref} className={`scroll-reveal${visible ? " is-visible" : ""}`}>{children}</div>;
}

function AmbientSignals() {
  const [signals, setSignals] = useState<{ id: number; side: "left" | "right"; top: number }[]>([]);
  const isMobile = useMobilePerformanceProfile();

  useEffect(() => {
    let timeout = 0;
    let id = 0;
    const spawn = () => {
      const signal = {
        id: id++,
        side: Math.random() > 0.5 ? "left" as const : "right" as const,
        top: window.scrollY + window.innerHeight * (0.18 + Math.random() * 0.64),
      };
      setSignals((current) => [...current, signal]);
      window.setTimeout(() => setSignals((current) => current.filter((item) => item.id !== signal.id)), 2200);
      timeout = window.setTimeout(spawn, isMobile ? 9000 + Math.random() * 5000 : 3500 + Math.random() * 5000);
    };
    timeout = window.setTimeout(spawn, isMobile ? 5000 : 1800);
    return () => window.clearTimeout(timeout);
  }, [isMobile]);

  return (
    <div className="absolute inset-0 z-20 pointer-events-none overflow-hidden" aria-hidden="true">
      {signals.map((signal) => (
        <div key={signal.id} className={`signal-wave signal-wave--${signal.side}`} style={{ top: `${signal.top}px` }}>
          <svg viewBox="0 0 360 160" preserveAspectRatio="none" fill="none" aria-hidden="true">
            <defs>
              <linearGradient id={`signal-gradient-${signal.id}`} x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" stopColor="#7c8dff" stopOpacity="0.95" />
                <stop offset="34%" stopColor="#7c8dff" stopOpacity="0.24" />
                <stop offset="50%" stopColor="#7c8dff" stopOpacity="0.06" />
                <stop offset="66%" stopColor="#7c8dff" stopOpacity="0.24" />
                <stop offset="100%" stopColor="#7c8dff" stopOpacity="0.95" />
              </linearGradient>
            </defs>
            <path className="signal-path signal-path--one" stroke={`url(#signal-gradient-${signal.id})`} d="M0 88 C52 16 104 16 156 88 S260 160 360 74" />
            {!isMobile && <><path className="signal-path signal-path--two" stroke={`url(#signal-gradient-${signal.id})`} d="M0 112 C58 38 116 38 174 112 S280 170 360 96" /><path className="signal-path signal-path--three" stroke={`url(#signal-gradient-${signal.id})`} d="M0 62 C48 -4 96 -4 144 62 S246 132 360 48" /></>}
          </svg>
        </div>
      ))}
    </div>
  );
}

// ─── Navigation ──────────────────────────────────────────────────────────────
function Nav() {
  const [scrolled, setScrolled] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
  const { language, setLanguage } = useLanguage();
  const isMobile = useMobilePerformanceProfile();

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 60);
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  const links = [
    { label: t("Jak pomagam"), href: "#capabilities" },
    { label: t("Jak działam"), href: "#process" },
    { label: t("O mnie"), href: "#about" },
    { label: t("Kontakt"), href: "#contact" },
  ];

  return (
    <nav
      className="fixed top-0 left-0 right-0 z-[999]"
      style={{
        background: scrolled ? "rgba(7,8,9,0.9)" : "transparent",
        backdropFilter: scrolled && !isMobile ? "blur(20px)" : "none",
        borderBottom: scrolled ? "1px solid #1a1d22" : "1px solid transparent",
        transition: "background 0.4s, border-color 0.4s, backdrop-filter 0.4s",
      }}
    >
      <div className="max-w-[1400px] mx-auto px-5 md:px-12 flex items-center justify-between transition-all duration-300" style={{ paddingTop: scrolled ? "0.75rem" : "1.25rem", paddingBottom: scrolled ? "0.75rem" : "1.25rem" }}>
        <a href="#" className="group flex flex-col text-sm font-semibold tracking-widest text-white uppercase" style={{ letterSpacing: "0.12em" }}>
          <span className="flex items-center gap-2.5"><i className="h-1.5 w-1.5 rounded-full bg-[#5b6ef5] shadow-[0_0_12px_#5b6ef5]" />Dawid Grzywniak</span>
          <span className="flex items-start gap-1.5 mt-1.5 normal-case tracking-normal" style={{ color: "#8b8f98" }}>
            <span aria-hidden="true" className="font-serif text-base md:text-lg leading-[0.8]" style={{ color: "#5b6ef5" }}>&ldquo;</span>
            <span className="font-sans text-[8px] md:text-[9px] italic font-medium leading-[1.35]">
              {t("Niemożliwe nie istnieje,")}<br />{t("ogranicza nas tylko kreatywność.")}
            </span>
            <span aria-hidden="true" className="self-end font-serif text-base md:text-lg leading-[0.65]" style={{ color: "#5b6ef5" }}>&rdquo;</span>
          </span>
        </a>

        <ul className="hidden lg:flex items-center gap-7">
          {links.map((l) => (
            <li key={l.label}>
              <a
                href={l.href}
                className="block py-2 text-[10px] tracking-widest font-mono uppercase"
                style={{ color: "#8b8f98", letterSpacing: "0.1em", transition: "color 0.2s, text-shadow 0.2s" }}
                onMouseEnter={(e) => { e.currentTarget.style.color = "#f5f5f5"; e.currentTarget.style.textShadow = "0 0 14px rgba(124,141,255,0.5)"; }}
                onMouseLeave={(e) => { e.currentTarget.style.color = "#8b8f98"; e.currentTarget.style.textShadow = "none"; }}
              >
                {l.label}
              </a>
            </li>
          ))}
        </ul>

        <a
          href="#contact"
          className="nav-liquid-cta hidden md:flex items-center gap-2 text-[10px] font-mono tracking-widest uppercase px-5 py-3"
          style={{ letterSpacing: "0.1em" }}
        >
          {t("Porozmawiajmy →")}
        </a>

        <div className="flex shrink-0 items-center gap-1" role="group" aria-label={t("Wybór języka")}>
          {languages.map((option) => (
            <button
              key={option.code}
              type="button"
              onClick={() => setLanguage(option.code)}
              aria-pressed={language === option.code}
              className="min-h-9 min-w-9 px-2 py-1 font-mono text-[9px] tracking-widest md:px-2.5"
              style={{ color: language === option.code ? "#f5f5f5" : "#7d8795", borderBottom: language === option.code ? "1px solid #5b6ef5" : "1px solid transparent", cursor: "pointer", transition: "color 0.2s, border-color 0.2s" }}
            >
              {option.label}
            </button>
          ))}
        </div>

        <button
          className="nav-liquid-menu md:hidden text-white p-3"
          type="button"
          aria-label={menuOpen ? t("Zamknij menu") : t("Otwórz menu")}
          aria-expanded={menuOpen}
          aria-controls="mobile-navigation"
          onClick={() => setMenuOpen(!menuOpen)}
        >
          <div className="w-5 h-px bg-white mb-1.5" style={{ transform: menuOpen ? "rotate(45deg) translate(1px, 1px)" : "" }} />
          <div className="w-5 h-px bg-white" style={{ transform: menuOpen ? "rotate(-45deg)" : "" }} />
        </button>
      </div>

      {menuOpen && (
        <div id="mobile-navigation" className="md:hidden px-6 pb-8 pt-2" style={{ background: "rgba(7,8,9,0.96)", borderBottom: "1px solid #1a1d22" }}>
          {links.map((l) => (
            <a key={l.label} href={l.href} className="block py-3 text-sm font-mono tracking-widest uppercase" style={{ color: "#8b8f98", letterSpacing: "0.1em" }} onClick={() => setMenuOpen(false)}>
              {l.label}
            </a>
          ))}
          <a href="#contact" className="block mt-4 py-3 text-center text-sm font-mono tracking-widest uppercase" style={{ border: "1px solid #1a1d22", color: "#f5f5f5", letterSpacing: "0.1em" }} onClick={() => setMenuOpen(false)}>
            {t("Porozmawiajmy →")}
          </a>
        </div>
      )}
    </nav>
  );
}

// ─── Hero Canvas ─────────────────────────────────────────────────────────────
/* function HeroCanvas() {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const animRef = useRef<number>(0);
  const activeNodeRef = useRef(-1);
  const [activeNode, setActiveNode] = useState<{ label: string; detail: string } | null>(null);
  const { language } = useLanguage();

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext("2d")!;
    let W = canvas.offsetWidth;
    let H = canvas.offsetHeight;
    canvas.width = W * window.devicePixelRatio;
    canvas.height = H * window.devicePixelRatio;
    ctx.setTransform(window.devicePixelRatio, 0, 0, window.devicePixelRatio, 0, 0);

    const nodes = [
      { label: t("POMYSŁ"), detail: t("Punkt wyjścia: cel, problem i plan działania."), x: W * 0.48, y: H * 0.5, baseX: W * 0.48, baseY: H * 0.5, r: 50, core: true },
      { label: t("STRONA"), detail: t("Czytelna obecność firmy i pierwszy punkt kontaktu z klientem."), x: W * 0.18, y: H * 0.26, baseX: W * 0.18, baseY: H * 0.26, r: 33, core: false },
      { label: t("APLIKACJA"), detail: t("Narzędzie, które porządkuje codzienną pracę i obsługę klientów."), x: W * 0.77, y: H * 0.2, baseX: W * 0.77, baseY: H * 0.2, r: 32, core: false },
      { label: t("INTEGRACJE"), detail: t("Połączenia z usługami, z których firma już korzysta."), x: W * 0.84, y: H * 0.5, baseX: W * 0.84, baseY: H * 0.5, r: 30, core: false },
      { label: t("AUTOMATYZACJA"), detail: t("Powtarzalne zadania wykonują się same, bez ręcznego przepisywania."), x: W * 0.7, y: H * 0.79, baseX: W * 0.7, baseY: H * 0.79, r: 33, core: false },
      { label: t("DANE"), detail: t("Wspólne, uporządkowane dane dostępne tam, gdzie są potrzebne."), x: W * 0.3, y: H * 0.82, baseX: W * 0.3, baseY: H * 0.82, r: 32, core: false },
      { label: t("WSPARCIE"), detail: t("Pomoc po uruchomieniu i spokojny rozwój rozwiązania."), x: W * 0.14, y: H * 0.6, baseX: W * 0.14, baseY: H * 0.6, r: 30, core: false },
    ];
    const connections: [number, number][] = [
      [0, 1], [0, 2], [0, 4], [2, 3], [2, 5], [4, 3], [4, 5], [2, 6], [4, 6],
    ];

    const particles: { x: number; y: number; progress: number; from: number; to: number; speed: number }[] = [];
    for (let i = 0; i < 14; i++) {
      const [from, to] = connections[Math.floor(Math.random() * connections.length)];
      particles.push({ x: nodes[from].x, y: nodes[from].y, progress: Math.random(), from, to, speed: 0.003 + Math.random() * 0.003 });
    }

    let time = 0;
    let shouldAnimate = !document.hidden;
    const draw = () => {
      if (!shouldAnimate) {
        animRef.current = 0;
        return;
      }
      ctx.clearRect(0, 0, W, H);
      time += 0.008;

      nodes.forEach((n, i) => {
        if (n.core) return;
        n.x = n.baseX + Math.sin(time * 0.9 + i * 1.3) * 7;
        n.y = n.baseY + Math.cos(time * 0.7 + i * 0.9) * 6;
        const padding = n.r + 14;
        n.x = Math.max(padding, Math.min(W - padding, n.x));
        n.y = Math.max(padding, Math.min(H - padding, n.y));
      });

      // Each line represents a deliberate relationship in the project flow.
      const activeIndex = activeNodeRef.current;
      connections.forEach(([from, to], index) => {
        const a = nodes[from], b = nodes[to];
        const isRelated = activeIndex === -1 || from === activeIndex || to === activeIndex;
        const alpha = isRelated ? (from === 0 || to === 0 ? 0.3 : 0.19) : 0.035;
        ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y);
        ctx.strokeStyle = `rgba(91,110,245,${alpha + Math.sin(time * 2 + index) * 0.03})`;
        ctx.lineWidth = from === 0 || to === 0 ? 0.8 : 0.55;
        ctx.stroke();
      });

      // Particles
      particles.forEach((p) => {
        p.progress += p.speed;
        if (p.progress > 1) {
          const [from, to] = connections[Math.floor(Math.random() * connections.length)];
          p.progress = 0; p.from = from; p.to = to;
        }
        const from = nodes[p.from], to = nodes[p.to];
        p.x = from.x + (to.x - from.x) * p.progress;
        p.y = from.y + (to.y - from.y) * p.progress;
        ctx.beginPath(); ctx.arc(p.x, p.y, 1.5, 0, Math.PI * 2);
        ctx.fillStyle = `rgba(91,110,245,${0.6 + Math.sin(time * 3 + p.progress * 10) * 0.3})`; ctx.fill();
      });

      // Nodes
      nodes.forEach((n, i) => {
        const isRelated = activeIndex === -1 || i === activeIndex || connections.some(([from, to]) => from === activeIndex && to === i || to === activeIndex && from === i);
        const pulse = Math.sin(time * 1.5 + i * 0.8) * 0.5 + 0.5;
        ctx.globalAlpha = isRelated ? 1 : 0.3;
        if (n.core) {
          const g = ctx.createRadialGradient(n.x, n.y, 0, n.x, n.y, 80);
          g.addColorStop(0, "rgba(91,110,245,0.12)"); g.addColorStop(1, "rgba(91,110,245,0)");
          ctx.beginPath(); ctx.arc(n.x, n.y, 80, 0, Math.PI * 2); ctx.fillStyle = g; ctx.fill();
        }
        ctx.beginPath(); ctx.arc(n.x, n.y, n.r + 4 + pulse * 4, 0, Math.PI * 2);
        ctx.strokeStyle = n.core ? `rgba(91,110,245,${0.15 + pulse * 0.1})` : `rgba(91,110,245,${0.06 + pulse * 0.04})`;
        ctx.lineWidth = 0.5; ctx.stroke();
        ctx.beginPath(); ctx.arc(n.x, n.y, n.r, 0, Math.PI * 2);
        ctx.fillStyle = n.core ? "rgba(10,11,13,0.95)" : "rgba(10,11,13,0.8)"; ctx.fill();
        ctx.strokeStyle = n.core ? "rgba(91,110,245,0.5)" : "rgba(26,29,34,0.8)"; ctx.lineWidth = n.core ? 1 : 0.5; ctx.stroke();
        ctx.fillStyle = n.core ? "#f5f5f5" : "#8b8f98";
        ctx.font = n.core ? "bold 11px 'JetBrains Mono', monospace" : "8px 'JetBrains Mono', monospace";
        ctx.textAlign = "center"; ctx.textBaseline = "middle"; ctx.fillText(n.label, n.x, n.y);
        ctx.globalAlpha = 1;
      });

      animRef.current = requestAnimationFrame(draw);
    };
    if (shouldAnimate) draw();

    const onResize = () => {
      W = canvas.offsetWidth; H = canvas.offsetHeight;
      canvas.width = W * window.devicePixelRatio; canvas.height = H * window.devicePixelRatio;
      ctx.setTransform(window.devicePixelRatio, 0, 0, window.devicePixelRatio, 0, 0);
      const anchors = [[0.48, 0.5], [0.18, 0.26], [0.77, 0.2], [0.84, 0.5], [0.7, 0.79], [0.3, 0.82], [0.14, 0.6]];
      nodes.forEach((node, index) => {
        node.baseX = W * anchors[index][0];
        node.baseY = H * anchors[index][1];
        node.x = node.baseX;
        node.y = node.baseY;
      });
    };
    const onMouseMove = (event: MouseEvent) => {
      const rect = canvas.getBoundingClientRect();
      const x = event.clientX - rect.left;
      const y = event.clientY - rect.top;
      const nextIndex = nodes.findIndex((node) => Math.hypot(node.x - x, node.y - y) <= node.r + 12);
      if (nextIndex === activeNodeRef.current) return;
      activeNodeRef.current = nextIndex;
      canvas.style.cursor = nextIndex === -1 ? "default" : "pointer";
      setActiveNode(nextIndex === -1 ? null : { label: nodes[nextIndex].label, detail: nodes[nextIndex].detail });
    };
    const onMouseLeave = () => {
      activeNodeRef.current = -1;
      canvas.style.cursor = "default";
      setActiveNode(null);
    };
    window.addEventListener("resize", onResize);
    canvas.addEventListener("mousemove", onMouseMove);
    canvas.addEventListener("mouseleave", onMouseLeave);
    const visibilityObserver = new IntersectionObserver(([entry]) => {
      shouldAnimate = entry.isIntersecting && !document.hidden;
      if (shouldAnimate && !animRef.current) draw();
    }, { threshold: 0.05 });
    const onDocumentVisibility = () => {
      shouldAnimate = !document.hidden && canvas.getBoundingClientRect().bottom > 0 && canvas.getBoundingClientRect().top < window.innerHeight;
      if (shouldAnimate && !animRef.current) draw();
    };
    visibilityObserver.observe(canvas);
    document.addEventListener("visibilitychange", onDocumentVisibility);
    return () => {
      cancelAnimationFrame(animRef.current);
      visibilityObserver.disconnect();
      document.removeEventListener("visibilitychange", onDocumentVisibility);
      window.removeEventListener("resize", onResize);
      canvas.removeEventListener("mousemove", onMouseMove);
      canvas.removeEventListener("mouseleave", onMouseLeave);
    };
  }, [language]);

  return (
    <div className="relative w-full h-full">
      <canvas ref={canvasRef} className="w-full h-full" style={{ opacity: 0.9 }} />
      <div className="absolute top-5 left-5 max-w-52 pointer-events-none transition-opacity duration-200" style={{ opacity: activeNode ? 1 : 0.7 }}>
        <div className="font-mono text-[9px] tracking-[0.18em] uppercase mb-2" style={{ color: "#5b6ef5" }}>
          {activeNode ? activeNode.label : t("Interaktywna mapa")}
        </div>
        <p className="text-xs leading-relaxed" style={{ color: "#8b8f98" }}>
          {activeNode ? activeNode.detail : t("Najedź na element, aby zobaczyć jego rolę i powiązania.")}
        </p>
      </div>
    </div>
  );
} */

// Legacy WebGL experiments retained in source history; the production visual below is intentionally CSS-based.
/*
function InfrastructureOrbitLegacy() {
  const mountRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const mount = mountRef.current;
    if (!mount) return;
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(42, 1, 0.1, 100);
    camera.position.z = 7;
    const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.5));
    mount.appendChild(renderer.domElement);

    const group = new THREE.Group();
    scene.add(group);
    [1.1, 1.65, 2.2].forEach((radius, index) => {
      const ring = new THREE.Mesh(
        new THREE.TorusGeometry(radius, 0.012, 8, 80),
        new THREE.MeshBasicMaterial({ color: index === 1 ? 0x93a0ff : 0x5b6ef5, transparent: true, opacity: 0.7 }),
      );
      ring.rotation.set(index * 0.8, index * 0.55, index * 0.35);
      group.add(ring);
    });
    const core = new THREE.Mesh(
      new THREE.IcosahedronGeometry(0.5, 2),
      new THREE.MeshBasicMaterial({ color: 0x5b6ef5, wireframe: true, transparent: true, opacity: 0.9 }),
    );
    group.add(core);
    const particlePositions: number[] = [];
    for (let i = 0; i < 80; i++) {
      const point = new THREE.Vector3().randomDirection().multiplyScalar(2.5 + Math.random() * 1.8);
      particlePositions.push(point.x, point.y, point.z);
    }
    const particleGeometry = new THREE.BufferGeometry();
    particleGeometry.setAttribute("position", new THREE.Float32BufferAttribute(particlePositions, 3));
    const particles = new THREE.Points(
      particleGeometry,
      new THREE.PointsMaterial({ color: 0x93a0ff, size: 0.035, transparent: true, opacity: 0.8 }),
    );
    group.add(particles);
    const mouse = new THREE.Vector2();
    const onMove = (event: PointerEvent) => {
      const rect = mount.getBoundingClientRect();
      mouse.set((event.clientX - rect.left) / rect.width - 0.5, (event.clientY - rect.top) / rect.height - 0.5);
    };
    mount.addEventListener("pointermove", onMove);
    const resize = () => {
      const { width, height } = mount.getBoundingClientRect();
      if (!width || !height) return;
      camera.aspect = width / height;
      camera.updateProjectionMatrix();
      renderer.setSize(width, height, false);
    };
    const observer = new ResizeObserver(resize);
    observer.observe(mount);
    resize();
    let frame = 0;
    let shouldAnimate = !document.hidden;
    const animate = () => {
      if (!shouldAnimate) {
        frame = 0;
        return;
      }
      frame = requestAnimationFrame(animate);
      group.rotation.y += 0.006;
      group.rotation.x += (mouse.y * 0.8 - group.rotation.x) * 0.03;
      group.rotation.z += (mouse.x * 0.45 - group.rotation.z) * 0.03;
      core.rotation.x += 0.012;
      particles.rotation.y -= 0.002;
      renderer.render(scene, camera);
    };
    const visibilityObserver = new IntersectionObserver(([entry]) => {
      shouldAnimate = entry.isIntersecting && !document.hidden;
      if (shouldAnimate && !frame) animate();
    }, { threshold: 0.1 });
    const onDocumentVisibility = () => {
      shouldAnimate = !document.hidden && mount.getBoundingClientRect().bottom > 0 && mount.getBoundingClientRect().top < window.innerHeight;
      if (shouldAnimate && !frame) animate();
    };
    visibilityObserver.observe(mount);
    document.addEventListener("visibilitychange", onDocumentVisibility);
    if (shouldAnimate) animate();
    return () => {
      cancelAnimationFrame(frame);
      observer.disconnect();
      visibilityObserver.disconnect();
      document.removeEventListener("visibilitychange", onDocumentVisibility);
      mount.removeEventListener("pointermove", onMove);
      group.traverse((object) => {
        const mesh = object as THREE.Mesh;
        mesh.geometry?.dispose();
        if (Array.isArray(mesh.material)) mesh.material.forEach((material) => material.dispose());
        else mesh.material?.dispose();
      });
      particleGeometry.dispose();
      renderer.dispose();
      renderer.domElement.remove();
    };
  }, []);

  return <div ref={mountRef} className="w-full h-full" role="img" aria-label={t("Interaktywna wizualizacja infrastruktury")} />;
}

function SolutionCore3D() {
  const mountRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const mount = mountRef.current;
    if (!mount) return;
    const scene = new THREE.Scene();
    scene.fog = new THREE.FogExp2(0x0a0b0d, 0.12);
    const camera = new THREE.PerspectiveCamera(38, 1, 0.1, 100);
    camera.position.set(0, 0, 8);
    const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.75));
    mount.appendChild(renderer.domElement);

    const group = new THREE.Group();
    scene.add(group);
    const core = new THREE.Mesh(
      new THREE.IcosahedronGeometry(1.15, 2),
      new THREE.MeshBasicMaterial({ color: 0x7c8dff, wireframe: true, transparent: true, opacity: 0.9 }),
    );
    group.add(core);

    const modules = new THREE.Group();
    const moduleGeometry = new THREE.BoxGeometry(0.4, 0.4, 0.4);
    const moduleMaterial = new THREE.MeshBasicMaterial({ color: 0x5b6ef5, transparent: true, opacity: 0.78 });
    for (let i = 0; i < 6; i++) {
      const angle = (Math.PI * 2 * i) / 6;
      const cube = new THREE.Mesh(moduleGeometry, moduleMaterial.clone());
      cube.position.set(Math.cos(angle) * 2.35, Math.sin(angle) * 1.45, i % 2 === 0 ? 0.5 : -0.5);
      cube.rotation.set(angle, angle * 0.6, 0);
      modules.add(cube);
    }
    group.add(modules);

    const orbit = new THREE.Mesh(
      new THREE.TorusGeometry(2.6, 0.014, 8, 100),
      new THREE.MeshBasicMaterial({ color: 0x93a0ff, transparent: true, opacity: 0.65 }),
    );
    orbit.rotation.set(0.9, 0.25, -0.2);
    group.add(orbit);

    const stars: number[] = [];
    for (let i = 0; i < 150; i++) {
      const point = new THREE.Vector3().randomDirection().multiplyScalar(2 + Math.random() * 4);
      stars.push(point.x, point.y, point.z);
    }
    const starsGeometry = new THREE.BufferGeometry();
    starsGeometry.setAttribute("position", new THREE.Float32BufferAttribute(stars, 3));
    const starField = new THREE.Points(starsGeometry, new THREE.PointsMaterial({ color: 0x93a0ff, size: 0.028, transparent: true, opacity: 0.7 }));
    scene.add(starField);

    const pointer = new THREE.Vector2();
    const onMove = (event: PointerEvent) => {
      const rect = mount.getBoundingClientRect();
      pointer.set((event.clientX - rect.left) / rect.width - 0.5, (event.clientY - rect.top) / rect.height - 0.5);
    };
    mount.addEventListener("pointermove", onMove);
    const resize = () => {
      const { width, height } = mount.getBoundingClientRect();
      if (!width || !height) return;
      camera.aspect = width / height;
      camera.updateProjectionMatrix();
      renderer.setSize(width, height, false);
    };
    const resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(mount);
    resize();
    let frame = 0;
    const animate = () => {
      frame = requestAnimationFrame(animate);
      group.rotation.y += 0.004;
      group.rotation.x += (pointer.y * 0.7 - group.rotation.x) * 0.035;
      group.rotation.z += (pointer.x * 0.35 - group.rotation.z) * 0.035;
      modules.rotation.z -= 0.008;
      core.rotation.x += 0.007;
      core.rotation.y -= 0.009;
      starField.rotation.y -= 0.0008;
      renderer.render(scene, camera);
    };
    animate();
    return () => {
      cancelAnimationFrame(frame);
      resizeObserver.disconnect();
      mount.removeEventListener("pointermove", onMove);
      moduleGeometry.dispose();
      moduleMaterial.dispose();
      modules.children.forEach((child) => ((child as THREE.Mesh).material as THREE.Material).dispose());
      core.geometry.dispose();
      (core.material as THREE.Material).dispose();
      orbit.geometry.dispose();
      (orbit.material as THREE.Material).dispose();
      starsGeometry.dispose();
      (starField.material as THREE.Material).dispose();
      renderer.dispose();
      renderer.domElement.remove();
    };
  }, []);

  return <div ref={mountRef} className="w-full h-full" aria-label="Interaktywny model rozwiązania" />;
}

// ─── Hero ─────────────────────────────────────────────────────────────────────
*/

function InfrastructureOrbit() {
  return (
    <div className="infrastructure-orbit w-full h-full" role="img" aria-label={t("Interaktywna wizualizacja infrastruktury")}>
      <span className="infrastructure-orbit__ring infrastructure-orbit__ring--one" />
      <span className="infrastructure-orbit__ring infrastructure-orbit__ring--two" />
      <span className="infrastructure-orbit__ring infrastructure-orbit__ring--three" />
      <span className="infrastructure-orbit__core" />
      <span className="infrastructure-orbit__particle infrastructure-orbit__particle--one" />
      <span className="infrastructure-orbit__particle infrastructure-orbit__particle--two" />
    </div>
  );
}

function Hero() {
  const isMobile = useMobilePerformanceProfile();

  return (
    <section className="relative min-h-screen flex items-center overflow-hidden" style={{ background: "radial-gradient(ellipse 55% 55% at 78% 42%, rgba(44,54,135,0.12), transparent 72%), #070809" }}>
      <div className="max-w-[1400px] mx-auto px-6 md:px-12 w-full pt-28 pb-16">
        <div className="grid md:grid-cols-2 gap-16 items-center min-h-[80vh]">
          {/* Left */}
          <div className="flex flex-col justify-center">
            <div className="flex items-center gap-3 mb-10">
              <span className="animate-pulse-dot inline-block w-1.5 h-1.5 rounded-full" style={{ background: "#5b6ef5" }} />
              <span className="font-mono text-[10px] tracking-[0.2em] uppercase" style={{ color: "#5b6ef5" }}>
                {t("Dostępny dla nowych projektów")}
              </span>
            </div>

            <h1
              className="font-sans font-bold leading-none mb-6"
              style={{ fontSize: "clamp(38px, 5.2vw, 76px)", letterSpacing: "-0.03em", color: "#f5f5f5" }}
            >
              {t("Tworzę rozwiązania IT")}
              <br />
              <span>{t("od pomysłu")}</span>
              <br />
              <span style={{ background: "linear-gradient(135deg, #5b6ef5, #7c8dff)", WebkitBackgroundClip: "text", WebkitTextFillColor: "transparent" }}>
                {t("do produkcji.")}
              </span>
            </h1>

            <p className="text-base leading-relaxed mb-4 max-w-md" style={{ color: "#8b8f98", lineHeight: 1.75 }}>
              {t("Pomagam firmom projektować, budować i utrzymywać serwisy, aplikacje, integracje oraz automatyzacje. Ty opisujesz cel — ja prowadzę techniczną całość.")}
            </p>

            <p className="font-mono text-[10px] tracking-widest mb-10" style={{ color: "#7d8795" }}>
              {t("Ty znasz swój biznes. Ja zajmę się technologią, wdrożeniem i utrzymaniem.")}
            </p>

            <div className="flex flex-wrap gap-4">
              <a
                href="#contact"
                className="liquid-button liquid-button--primary px-7 py-3.5 text-sm font-sans font-medium"
                style={{ letterSpacing: "-0.01em" }}
              >
                {t("Porozmawiajmy o projekcie →")}
              </a>
              <a
                href="#capabilities"
                className="liquid-button liquid-button--secondary px-7 py-3.5 text-sm font-sans"
              >
                {t("Zobacz ofertę")}
              </a>
            </div>

            {/* Subtle credibility bar — intentionally contains no unverified figures. */}
            <div className="mt-14 pt-8" style={{ borderTop: "1px solid #1a1d22" }}>
              <div className="flex flex-wrap gap-6">
                {["Development", "Integracje", "Automatyzacje", "Infrastruktura"].map((tag) => (
                  <span key={tag} className="font-mono text-[9px] tracking-[0.15em] uppercase" style={{ color: "#8b8f98" }}>
                    {t(tag)}
                  </span>
                ))}
              </div>
            </div>
          </div>

          {/* Right: Canvas */}
          <div className="relative hidden md:block overflow-hidden" style={{ height: "650px" }}>
            {!isMobile && (
              <Suspense fallback={null}>
                <HeroCanvas />
              </Suspense>
            )}
            <div className="absolute bottom-4 right-4 font-mono text-[9px] tracking-widest uppercase" style={{ color: "#7d8795" }}>
              {t("Architektura rozwiązania")}
            </div>
          </div>
        </div>
      </div>
      <div className="absolute bottom-0 left-0 right-0 h-32 pointer-events-none" style={{ background: "linear-gradient(to bottom, transparent, #070809)" }} />
    </section>
  );
}

// ─── Value Proposition ────────────────────────────────────────────────────────
function ValueProposition() {
  const ref = useRef<HTMLDivElement>(null);
  const [visible, setVisible] = useState(false);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const obs = new IntersectionObserver(([e]) => { if (e.isIntersecting) setVisible(true); }, { threshold: 0.2 });
    obs.observe(el);
    return () => obs.disconnect();
  }, []);

  const props = [
    {
      n: "01",
      title: "Całościowe podejście",
      desc: "Od analizy problemu, przez development i integracje, po wdrożenie oraz spokojne utrzymanie produkcyjne.",
    },
    {
      n: "02",
      title: "Jeden kontakt",
      desc: "Bez handlowców pomiędzy Tobą a osobą, która buduje rozwiązanie. Mniej przekazywania informacji i szybsze decyzje.",
    },
    {
      n: "03",
      title: "Dopasowanie",
      desc: "Najpierw poznaję proces, obecne systemy i ograniczenia. Dopiero potem proponuję rozwiązanie, które ma sens biznesowo.",
    },
  ];

  return (
    <section className="relative overflow-hidden py-24 md:py-28 px-6 md:px-12" style={{ background: "#0a0b0d" }}>
      <div className="max-w-[1400px] mx-auto" ref={ref}>
        <div className="grid lg:grid-cols-[0.95fr_1.05fr] gap-10 lg:gap-20 items-end mb-14 md:mb-16">
          <div>
            <div className="font-mono text-[10px] tracking-[0.2em] uppercase mb-5" style={{ color: "#5b6ef5" }}>
              {t("Dlaczego warto")}
            </div>
            <h2 className="font-sans font-bold leading-none" style={{ fontSize: "clamp(34px, 4.4vw, 64px)", letterSpacing: "-0.04em", color: "#f5f5f5" }}>
              {t("Jeden kontakt.")}
              <br />
              <span style={{ color: "#8b8f98" }}>{t("Pełna odpowiedzialność.")}</span>
            </h2>
          </div>
          <div className="relative min-h-[180px] overflow-hidden" style={{ border: "1px solid #303844" }}>
            <picture className="absolute inset-0 block">
              <source media="(max-width: 767px)" srcSet={oneContactMobile} type="image/webp" />
              <img src={oneContact} srcSet={`${oneContactRetina} 768w, ${oneContact} 1536w`} sizes="50vw" alt={t("Wspólne planowanie rozwiązania")} loading="lazy" decoding="async" className="w-full h-full object-cover" style={{ objectPosition: "65% center" }} />
            </picture>
            <div className="absolute inset-0" style={{ background: "linear-gradient(90deg, rgba(7,8,9,0.9) 0%, rgba(7,8,9,0.42) 72%, rgba(7,8,9,0.18) 100%)" }} />
            <div className="absolute inset-x-0 bottom-0 p-6">
              <div className="font-mono text-[9px] tracking-[0.18em] uppercase mb-2" style={{ color: "#a9b5ff" }}>{t("Od pierwszej rozmowy")}</div>
              <p className="max-w-lg text-sm leading-relaxed" style={{ color: "#e0e3ec", lineHeight: 1.7 }}>
                {t("Jedna osoba prowadzi temat od pierwszej rozmowy po działające rozwiązanie — bez przekazywania go między kolejnymi wykonawcami.")}
              </p>
            </div>
          </div>
        </div>
        <div
          className="grid md:grid-cols-3 gap-4"
          style={{
            opacity: visible ? 1 : 0,
            transform: visible ? "translateY(0)" : "translateY(32px)",
            transition: "opacity 0.9s ease, transform 0.9s cubic-bezier(0.16,1,0.3,1)",
          }}
        >
          {props.map((p, i) => (
            <div
              key={p.n}
              className="relative min-h-[255px] p-8 md:p-10 flex flex-col overflow-hidden group"
              style={{ background: "linear-gradient(145deg, rgba(18,20,26,0.92), rgba(10,11,13,0.98))", border: "1px solid #252a33", transition: "border-color 0.3s, transform 0.3s, background 0.3s" }}
              onMouseEnter={(e) => { const card = e.currentTarget; card.style.borderColor = "rgba(91,110,245,0.42)"; card.style.transform = "translateY(-4px)"; card.style.background = "linear-gradient(145deg, rgba(26,30,42,0.96), rgba(10,11,13,1))"; }}
              onMouseLeave={(e) => { const card = e.currentTarget; card.style.borderColor = "#252a33"; card.style.transform = "translateY(0)"; card.style.background = "linear-gradient(145deg, rgba(18,20,26,0.92), rgba(10,11,13,0.98))"; }}
            >
              <div className="flex items-center gap-3 mb-10">
                <span className="w-1.5 h-1.5 rounded-full" style={{ background: "#5b6ef5", boxShadow: "0 0 12px rgba(91,110,245,0.9)" }} />
                <div className="h-px flex-1" style={{ background: "linear-gradient(90deg, rgba(91,110,245,0.5), transparent)" }} />
                <span className="font-mono text-[9px] tracking-widest" style={{ color: "#7d8795" }}>{p.n}</span>
              </div>
              <h3
                className="font-sans font-bold mb-4 tracking-tight"
                style={{ fontSize: "clamp(18px, 1.8vw, 24px)", letterSpacing: "-0.03em", color: "#f5f5f5" }}
              >
                {t(p.title)}
              </h3>
              <p className="text-sm leading-relaxed mt-auto" style={{ color: "#9ba1ac", lineHeight: 1.78 }}>
                {t(p.desc)}
              </p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

// ─── Problem First ────────────────────────────────────────────────────────────
const problems = [
  { text: "Serwis działa wolno albo niestabilnie?", cta: "Uporządkujmy to →" },
  { text: "Systemy nie wymieniają między sobą danych?", cta: "Połącz narzędzia →" },
  { text: "Zespół ręcznie wykonuje zadania, które można zautomatyzować?", cta: "Odzyskaj czas →" },
  { text: "Potrzebujesz aplikacji, ale nie wiesz, jak ją zaprojektować?", cta: "Zaplanujmy ją →" },
  { text: "Istniejący system trzeba rozwinąć albo uporządkować?", cta: "Sprawdźmy zakres →" },
  { text: "Projekt został niedokończony i potrzebuje odpowiedzialnego przejęcia?", cta: "Porozmawiajmy →" },
];

function ProblemFirst() {
  const ref = useRef<HTMLDivElement>(null);
  const [visible, setVisible] = useState(false);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const obs = new IntersectionObserver(([e]) => { if (e.isIntersecting) setVisible(true); }, { threshold: 0.15 });
    obs.observe(el);
    return () => obs.disconnect();
  }, []);

  return (
    <section className="py-32 px-6 md:px-12" style={{ background: "#070809" }}>
      <div className="max-w-[1400px] mx-auto" ref={ref}>
        <div className="grid md:grid-cols-2 gap-20 items-start">
          <article className="relative min-h-[510px] overflow-hidden" style={{ border: "1px solid #303844", boxShadow: "18px 18px 0 rgba(91,110,245,0.06)" }}>
            <img src={manualProcess} srcSet={`${manualProcessMobile} 480w, ${manualProcess} 727w`} sizes="(max-width: 767px) 100vw, 50vw" alt={t("Ręczne procesy w firmie")} loading="lazy" decoding="async" className="absolute inset-0 w-full h-full object-cover" style={{ objectPosition: "left center" }} />
            <div className="absolute inset-0" style={{ background: "linear-gradient(180deg, rgba(7,8,9,0.18) 0%, rgba(7,8,9,0.48) 38%, rgba(7,8,9,0.95) 100%), linear-gradient(90deg, rgba(7,8,9,0.65), transparent 75%)" }} />
            <div className="relative h-full min-h-[510px] p-8 md:p-10 flex flex-col justify-end">
              <div className="font-mono text-[10px] tracking-[0.2em] uppercase mb-6" style={{ color: "#aebaff" }}>
                {t("Czy to brzmi znajomo?")}
              </div>
              <h2
                className="font-sans font-bold leading-tight"
                style={{ fontSize: "clamp(34px, 4.5vw, 64px)", letterSpacing: "-0.04em", color: "#f5f5f5" }}
              >
                {t("Masz problem.")}
                <br />
                <span style={{ color: "#c0c4cf" }}>{t("Znajdziemy rozwiązanie.")}</span>
              </h2>
              <div className="mt-8 pt-5 flex items-center gap-3" style={{ borderTop: "1px solid rgba(255,255,255,0.14)" }}>
                <span className="w-1.5 h-1.5 rounded-full" style={{ background: "#5b6ef5", boxShadow: "0 0 12px #5b6ef5" }} />
                <span className="font-mono text-[9px] tracking-[0.18em] uppercase" style={{ color: "#c8d0ff" }}>{t("Od problemu zaczynamy")}</span>
              </div>
            </div>
          </article>

          <div className="space-y-0">
            <p className="mb-6 max-w-lg text-sm leading-relaxed" style={{ color: "#8b8f98" }}>
              {t("Nie musisz znać technologii ani mieć gotowego rozwiązania. Wystarczy, że opiszesz problem lub cel biznesowy.")}
            </p>
            {problems.map((problem, i) => (
              <div
                key={i}
                className="group flex items-center justify-between py-5"
                style={{
                  borderBottom: "1px solid #1a1d22",
                  opacity: visible ? 1 : 0,
                  transform: visible ? "translateX(0)" : "translateX(20px)",
                  transition: `opacity 0.6s ease ${i * 80}ms, transform 0.6s cubic-bezier(0.16,1,0.3,1) ${i * 80}ms`,
                }}
              >
                <p
                  className="text-sm leading-relaxed flex-1 pr-4"
                  style={{ color: "#8b8f98", transition: "color 0.2s" }}
                  onMouseEnter={(e) => ((e.target as HTMLElement).style.color = "#f5f5f5")}
                  onMouseLeave={(e) => ((e.target as HTMLElement).style.color = "#8b8f98")}
                >
                  {t(problem.text)}
                </p>
                <a
                  href="#contact"
                  className="font-mono text-[9px] tracking-widest uppercase whitespace-nowrap flex-shrink-0"
                  style={{ color: "#7d8795", transition: "color 0.2s" }}
                  onMouseEnter={(e) => ((e.target as HTMLElement).style.color = "#5b6ef5")}
                  onMouseLeave={(e) => ((e.target as HTMLElement).style.color = "#7d8795")}
                >
                  {t(problem.cta)}
                </a>
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}

// ─── "Nie wiem czego potrzebuję" ─────────────────────────────────────────────
function DontKnowSection() {
  const ref = useRef<HTMLDivElement>(null);
  const [visible, setVisible] = useState(false);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const obs = new IntersectionObserver(([e]) => { if (e.isIntersecting) setVisible(true); }, { threshold: 0.3 });
    obs.observe(el);
    return () => obs.disconnect();
  }, []);

  return (
    <section className="py-40 px-6 md:px-12" style={{ background: "#0a0b0d" }}>
      <div
        className="max-w-[1400px] mx-auto"
        ref={ref}
        style={{
          opacity: visible ? 1 : 0,
          transform: visible ? "translateY(0)" : "translateY(40px)",
          transition: "opacity 1s ease, transform 1s cubic-bezier(0.16,1,0.3,1)",
        }}
      >
        <div className="grid md:grid-cols-2 gap-16 lg:gap-24 items-center">
          <div className="max-w-xl">
          <div className="font-mono text-[10px] tracking-[0.2em] uppercase mb-8" style={{ color: "#5b6ef5" }}>
            Nie wiesz jeszcze, czego potrzebujesz?
          </div>
          <h2
            className="font-sans font-bold leading-none mb-8"
            style={{ fontSize: "clamp(40px, 6vw, 96px)", letterSpacing: "-0.04em", color: "#f5f5f5", lineHeight: 0.95 }}
          >
            To żaden
            <br />
            problem.
          </h2>
          <p className="text-base leading-relaxed mb-4 max-w-xl" style={{ color: "#8b8f98", lineHeight: 1.8 }}>
            Opowiedz mi, co chcesz osiągnąć. Pomogę określić, co najlepiej sprawdzi się w Twojej sytuacji.
          </p>
          <p className="text-sm mb-12" style={{ color: "#8b8f98" }}>
            Nie musisz mieć gotowej specyfikacji. Na początku wystarczy krótka rozmowa.
          </p>

          <div className="flex flex-wrap gap-4">
            <a
              href="#contact"
              className="px-7 py-3.5 text-sm font-sans font-medium"
              style={{ background: "#5b6ef5", color: "#fff", letterSpacing: "-0.01em", transition: "background 0.2s, transform 0.2s" }}
              onMouseEnter={(e) => { (e.currentTarget as HTMLElement).style.background = "#6b7ef8"; (e.currentTarget as HTMLElement).style.transform = "translateY(-1px)"; }}
              onMouseLeave={(e) => { (e.currentTarget as HTMLElement).style.background = "#5b6ef5"; (e.currentTarget as HTMLElement).style.transform = "translateY(0)"; }}
            >
              Potrzebuję pomocy →
            </a>
            <a
              href="#contact"
              className="px-7 py-3.5 text-sm font-sans"
              style={{ border: "1px solid #1a1d22", color: "#8b8f98", transition: "border-color 0.2s, color 0.2s" }}
              onMouseEnter={(e) => { (e.currentTarget as HTMLElement).style.borderColor = "#8b8f98"; (e.currentTarget as HTMLElement).style.color = "#f5f5f5"; }}
              onMouseLeave={(e) => { (e.currentTarget as HTMLElement).style.borderColor = "#1a1d22"; (e.currentTarget as HTMLElement).style.color = "#8b8f98"; }}
            >
              Opowiedz o pomyśle →
            </a>
          </div>
          </div>
          <div className="relative min-h-[360px] overflow-hidden" style={{ border: "1px solid #303844", boxShadow: "20px 20px 0 rgba(91,110,245,0.07)" }}>
            <img src={workflowPlanning} srcSet={`${workflowPlanningMobile} 480w, ${workflowPlanningRetina} 768w, ${workflowPlanning} 1536w`} sizes="(max-width: 767px) calc(100vw - 3rem), 50vw" alt="Wspólne planowanie rozwiązania" loading="lazy" decoding="async" className="absolute inset-0 w-full h-full object-cover" style={{ objectPosition: "70% center" }} />
            <div className="absolute inset-0" style={{ background: "linear-gradient(180deg, rgba(7,8,9,0.08), rgba(7,8,9,0.78))" }} />
            <div className="absolute bottom-0 left-0 right-0 p-7">
              <div className="font-mono text-[9px] tracking-[0.18em] uppercase mb-3" style={{ color: "#93a0ff" }}>Od krótkiej rozmowy</div>
              <p className="text-sm leading-relaxed max-w-xs" style={{ color: "#f5f5f5" }}>Wystarczy opisać sytuację. Wspólnie zamienimy ją w klarowny plan działania.</p>
            </div>
          </div>
        </div>

        {/* Trust signals */}
        <div className="mt-20 pt-10 grid md:grid-cols-3 gap-px" style={{ borderTop: "1px solid #1a1d22" }}>
          {[
            { label: "Nie wymagam specyfikacji", desc: "Na początku wystarczy opis problemu." },
            { label: "Najpierw poznajemy problem", desc: "Nie zaczynam od kodowania. Najpierw ustalamy, co ma sens." },
            { label: "Bez zobowiązań", desc: "Najpierw sprawdzimy, czy możemy pomóc." },
          ].map((t) => (
            <div key={t.label} className="py-6 pr-8">
              <div className="font-mono text-[9px] tracking-widest uppercase mb-2" style={{ color: "#5b6ef5" }}>
                {t.label}
              </div>
              <p className="text-xs leading-relaxed" style={{ color: "#8b8f98" }}>
                {t.desc}
              </p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

// ─── Capabilities ─────────────────────────────────────────────────────────────
const caps = [
  { n: "01", title: "Serwisy i platformy webowe", desc: "Serwisy firmowe i platformy, które porządkują ofertę, obsługę klienta oraz proces pozyskiwania zapytań.", result: "Efekt: czytelny produkt i lepszy pierwszy kontakt" },
  { n: "02", title: "Aplikacje dla firm", desc: "Narzędzia dopasowane do pracy zespołu: panele, procesy, rezerwacje i obsługa klienta.", result: "Efekt: sprawniejsza codzienna praca" },
  { n: "03", title: "Systemy i integracje", desc: "Łączę istniejące narzędzia, żeby dane przepływały bez ręcznego przepisywania i rozbieżności.", result: "Efekt: mniej błędów i pełniejszy obraz firmy" },
  { n: "04", title: "Optymalizacja serwisów", desc: "Porządkuję wolne, niestabilne lub trudne w rozwoju serwisy oraz ich krytyczne procesy.", result: "Efekt: stabilniejsza praca i mniej blokad" },
  { n: "05", title: "Serwery i konfiguracja", desc: "Konfiguruję środowisko, zabezpieczenia i monitoring, aby rozwiązanie działało stabilnie dziś i było gotowe na rozwój.", result: "Efekt: spokój i przewidywalne działanie" },
  { n: "06", title: "Automatyzacje", desc: "Eliminuję powtarzalne zadania i przekazywanie danych między narzędziami.", result: "Efekt: więcej czasu na pracę, która ma znaczenie" },
];

function CapabilityCard({ cap, delay }: { cap: typeof caps[0]; delay: number }) {
  const ref = useRef<HTMLDivElement>(null);
  const [visible, setVisible] = useState(false);
  const [hovered, setHovered] = useState(false);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const obs = new IntersectionObserver(([e]) => { if (e.isIntersecting) setVisible(true); }, { threshold: 0.1 });
    obs.observe(el);
    return () => obs.disconnect();
  }, []);

  return (
    <div
      ref={ref}
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
      className="p-8 relative overflow-hidden"
      style={{
        background: hovered ? "rgba(10,11,13,1)" : "rgba(10,11,13,0.5)",
        border: "1px solid",
        borderColor: hovered ? "rgba(91,110,245,0.3)" : "#1a1d22",
        opacity: visible ? 1 : 0,
        transform: visible ? hovered ? "translateY(-4px)" : "translateY(0)" : "translateY(24px)",
        transition: `opacity 0.7s ease ${delay}ms, transform 0.4s cubic-bezier(0.16,1,0.3,1), border-color 0.3s, background 0.3s`,
        boxShadow: hovered ? "0 20px 60px rgba(91,110,245,0.08)" : "none",
      }}
    >
      <div className="font-mono text-[9px] tracking-[0.2em] mb-6" style={{ color: hovered ? "#5b6ef5" : "#7d8795", transition: "color 0.3s" }}>
      {cap.n}
      </div>
      <h3 className="font-sans font-bold tracking-tight mb-3" style={{ fontSize: "clamp(18px, 1.8vw, 24px)", letterSpacing: "-0.03em", color: "#f5f5f5" }}>
        {t(cap.title)}
      </h3>
      <p className="text-sm leading-relaxed mb-6" style={{ color: "#8b8f98", lineHeight: 1.7 }}>
        {t(cap.desc)}
      </p>
      <div className="font-mono text-[9px] tracking-[0.12em] leading-relaxed" style={{ color: hovered ? "#93a0ff" : "#7d8795", transition: "color 0.3s" }}>
        {t(cap.result)}
      </div>
      {hovered && (
        <div className="absolute top-0 right-0 w-24 h-24 pointer-events-none" style={{ background: "radial-gradient(circle at top right, rgba(91,110,245,0.08), transparent 70%)" }} />
      )}
    </div>
  );
}

function Capabilities() {
  return (
    <section id="capabilities" className="py-32 px-6 md:px-12" style={{ background: "#070809" }}>
      <div className="max-w-[1400px] mx-auto">
        <div className="mb-20">
          <div className="font-mono text-[10px] tracking-[0.2em] uppercase mb-6" style={{ color: "#8b8f98" }}>
            {t("W czym mogę pomóc")}
          </div>
          <h2
            className="font-sans font-bold leading-none"
            style={{ fontSize: "clamp(36px, 5vw, 72px)", letterSpacing: "-0.04em", color: "#f5f5f5" }}
          >
            {t("Jeden developer.")}
            <br />
            <span style={{ color: "#8b8f98" }}>{t("Cały system.")}</span>
          </h2>
        </div>

        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-px" style={{ background: "#1a1d22" }}>
          {caps.map((c, i) => <CapabilityCard key={c.n} cap={c} delay={i * 80} />)}
        </div>

        {/* After-capabilities CTA */}
        <div
          className="mt-12 p-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-6"
          style={{ border: "1px solid #1a1d22" }}
        >
          <div>
            <p className="text-sm font-sans mb-1" style={{ color: "#f5f5f5" }}>
              {t("Nie widzisz tutaj dokładnie tego, czego szukasz?")}
            </p>
            <p className="text-sm" style={{ color: "#8b8f98" }}>
              {t("Możliwe, że i tak mogę pomóc. Nie zaczynam od katalogu usług — zaczynam od zrozumienia Twojej sytuacji.")}
            </p>
          </div>
          <a
            href="#contact"
            className="liquid-button liquid-button--secondary flex-shrink-0 px-6 py-3 text-xs font-mono tracking-widest uppercase"
          >
            {t("Opowiedz o problemie →")}
          </a>
        </div>
      </div>
    </section>
  );
}

// ─── Start From Problem ───────────────────────────────────────────────────────
function VisualHighlights() {
  return (
    <section className="relative overflow-hidden py-32 px-6 md:px-12" style={{ background: "#0a0b0d" }}>
      <div className="blue-flare blue-flare--right" aria-hidden="true" />
      <div className="max-w-[1400px] mx-auto relative z-10">
        <div className="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-14">
          <div>
            <div className="font-mono text-[10px] tracking-[0.2em] uppercase mb-5" style={{ color: "#5b6ef5" }}>{t("Od problemu do rozwiązania")}</div>
            <h2 className="font-sans font-bold leading-none" style={{ fontSize: "clamp(34px, 4.5vw, 64px)", letterSpacing: "-0.04em", color: "#f5f5f5" }}>
              {t("Dobre rozwiązanie")}<br /><span style={{ color: "#8b8f98" }}>{t("widać w codziennej pracy.")}</span>
            </h2>
          </div>
          <p className="max-w-sm text-sm leading-relaxed" style={{ color: "#8b8f98" }}>{t("Najpierw porządkujemy proces. Potem dbam, żeby całość działała stabilnie w tle.")}</p>
        </div>
        <div className="grid md:grid-cols-2 gap-6">
          <article className="relative min-h-[420px] overflow-hidden" style={{ border: "1px solid #303844" }}>
            <img src={workflowPlanning} srcSet={`${workflowPlanningMobile} 480w, ${workflowPlanningRetina} 768w, ${workflowPlanning} 1536w`} sizes="(max-width: 767px) calc(100vw - 3rem), 50vw" alt={t("Planowanie procesu pracy przy biurku")} loading="lazy" decoding="async" className="absolute inset-0 w-full h-full object-cover" />
            <div className="absolute inset-0" style={{ background: "linear-gradient(90deg, rgba(7,8,9,0.86) 0%, rgba(7,8,9,0.3) 72%)" }} />
            <div className="relative h-full p-8 flex flex-col justify-end max-w-sm">
              <div className="font-mono text-[10px] tracking-[0.18em] uppercase mb-4" style={{ color: "#93a0ff" }}>{t("01 / Najpierw zrozumienie")}</div>
              <h3 className="text-2xl font-bold mb-3" style={{ color: "#f5f5f5" }}>{t("Mniej zgadywania.")}<br />{t("Więcej jasności.")}</h3>
              <p className="text-sm leading-relaxed" style={{ color: "#d0d4db" }}>{t("Wspólnie układamy problem, cele i sensowne kolejne kroki.")}</p>
            </div>
          </article>
          <article className="relative min-h-[420px] overflow-hidden" style={{ border: "1px solid #303844" }}>
            <img src={serverConfiguration} srcSet={`${serverConfigurationMobile} 480w, ${serverConfigurationRetina} 768w, ${serverConfiguration} 1536w`} sizes="(max-width: 767px) calc(100vw - 3rem), 50vw" alt={t("Skonfigurowana infrastruktura serwerowa")} loading="lazy" decoding="async" className="absolute inset-0 w-full h-full object-cover" />
            <div className="absolute inset-0" style={{ background: "linear-gradient(90deg, rgba(7,8,9,0.84) 0%, rgba(7,8,9,0.22) 75%)" }} />
            <div className="absolute top-5 right-5 w-40 h-40" style={{ background: "radial-gradient(circle, rgba(7,8,9,0.5), transparent 70%)" }}><InfrastructureOrbit /></div>
            <div className="relative h-full p-8 flex flex-col justify-end max-w-sm">
              <div className="font-mono text-[10px] tracking-[0.18em] uppercase mb-4" style={{ color: "#93a0ff" }}>{t("02 / Stabilne zaplecze")}</div>
              <h3 className="text-2xl font-bold mb-3" style={{ color: "#f5f5f5" }}>{t("Technologia, która")}<br />{t("po prostu działa.")}</h3>
              <p className="text-sm leading-relaxed" style={{ color: "#d0d4db" }}>{t("Konfiguracja, bezpieczeństwo i monitoring dopasowane do skali Twojego biznesu.")}</p>
            </div>
          </article>
        </div>
      </div>
    </section>
  );
}

function StartFromProblem() {
  const ref = useRef<HTMLDivElement>(null);
  const [visible, setVisible] = useState(false);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const obs = new IntersectionObserver(([e]) => { if (e.isIntersecting) setVisible(true); }, { threshold: 0.3 });
    obs.observe(el);
    return () => obs.disconnect();
  }, []);

  return (
    <section className="py-40 px-6 md:px-12" style={{ background: "#070809" }}>
      <div className="max-w-[1400px] mx-auto">
        <div
          className="grid md:grid-cols-2 gap-20 items-center"
          ref={ref}
          style={{
            opacity: visible ? 1 : 0,
            transform: visible ? "translateY(0)" : "translateY(40px)",
            transition: "opacity 1s ease, transform 1s cubic-bezier(0.16,1,0.3,1)",
          }}
        >
          <div>
            <div className="font-mono text-[10px] tracking-[0.2em] uppercase mb-8" style={{ color: "#8b8f98" }}>
              {t("Pierwszy kontakt")}
            </div>
            <h2
              className="font-sans font-bold leading-none mb-8"
              style={{ fontSize: "clamp(36px, 5vw, 72px)", letterSpacing: "-0.04em", color: "#f5f5f5", lineHeight: 0.95 }}
            >
              {t("Napisz, co chcesz")}
              <br />
              {t("osiągnąć.")}
            </h2>
            <p className="text-base leading-relaxed mb-4 max-w-md" style={{ color: "#8b8f98", lineHeight: 1.8 }}>
              {t("Kilka zdań o sytuacji i celu wystarczy. Nie musisz przygotowywać briefu ani znać technologii.")}
            </p>
            <p className="text-sm mb-10" style={{ color: "#8b8f98" }}>
              {t("Odpowiem konkretnie: co warto zrobić dalej i czy mogę realnie pomóc.")}
            </p>
            <a
              href="#contact"
              className="liquid-button liquid-button--primary inline-flex items-center gap-2 px-7 py-3.5 text-sm font-sans font-medium"
              style={{ letterSpacing: "-0.01em" }}
            >
              {t("Napisz wiadomość →")}
            </a>
          </div>

          <div className="relative min-h-[400px] overflow-hidden" style={{ border: "1px solid #303844", boxShadow: "20px 20px 0 rgba(91,110,245,0.07)" }}>
            <img src={firstConversation} srcSet={`${firstConversationMobile} 480w, ${firstConversationRetina} 768w, ${firstConversation} 1536w`} sizes="(max-width: 767px) calc(100vw - 3rem), 50vw" alt={t("Pierwsza rozmowa o projekcie")} loading="lazy" decoding="async" className="absolute inset-0 w-full h-full object-cover" style={{ objectPosition: "70% center" }} />
            <div className="absolute inset-0" style={{ background: "linear-gradient(180deg, rgba(7,8,9,0.08), rgba(7,8,9,0.82))" }} />
            <div className="absolute bottom-0 left-0 right-0 p-8">
              <div className="font-mono text-[10px] tracking-[0.18em] uppercase mb-4" style={{ color: "#93a0ff" }}>{t("Bez formalności")}</div>
              <p className="text-base leading-relaxed max-w-sm" style={{ color: "#f5f5f5" }}>{t("Krótka wiadomość wystarczy, aby zacząć. Resztę wspólnie uporządkujemy.")}</p>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

// ─── Process ──────────────────────────────────────────────────────────────────
const steps = [
  { n: "01", title: "ROZMAWIAMY", desc: "Opisujesz problem lub cel. Bez potrzeby przygotowywania technicznego briefu." },
  { n: "02", title: "ANALIZUJĘ", desc: "Sprawdzam proces, istniejące systemy oraz ograniczenia, które warto uwzględnić." },
  { n: "03", title: "PROPONUJĘ ROZWIĄZANIE", desc: "Ustalamy zakres, sposób realizacji, koszt i kolejne kroki przed rozpoczęciem prac." },
  { n: "04", title: "BUDUJĘ", desc: "Realizuję rozwiązanie etapami, z bezpośrednim kontaktem i widocznym postępem." },
  { n: "05", title: "WDRAŻAM", desc: "Testy, konfiguracja produkcyjna, uruchomienie i monitoring krytycznych elementów." },
  { n: "06", title: "ROZWIJAMY", desc: "Po starcie możliwe jest dalsze utrzymanie, rozwój i spokojne porządkowanie kolejnych potrzeb." },
];

function Process() {
  return (
    <section id="process" className="py-32 px-6 md:px-12" style={{ background: "#0a0b0d" }}>
      <div className="max-w-[1400px] mx-auto">
        <div className="mb-20">
          <div className="font-mono text-[10px] tracking-[0.2em] uppercase mb-6" style={{ color: "#8b8f98" }}>
            {t("Jak działam")}
          </div>
          <h2
            className="font-sans font-bold leading-none"
            style={{ fontSize: "clamp(36px, 5vw, 72px)", letterSpacing: "-0.04em", color: "#f5f5f5" }}
          >
            {t("Przejrzysty")}
            <br />
            <span style={{ color: "#8b8f98" }}>{t("proces.")}</span>
          </h2>
        </div>

        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-px" style={{ background: "#1a1d22" }}>
          {steps.map((step) => (
            <div
              key={step.n}
              className="p-8"
              style={{ background: "#0a0b0d", transition: "background 0.3s" }}
              onMouseEnter={(e) => ((e.currentTarget as HTMLElement).style.background = "#070809")}
              onMouseLeave={(e) => ((e.currentTarget as HTMLElement).style.background = "#0a0b0d")}
            >
              <div className="font-mono text-[9px] tracking-widest mb-8" style={{ color: "#5b6ef5" }}>{step.n}</div>
              <h3 className="font-mono text-xs tracking-[0.15em] mb-3" style={{ color: "#f5f5f5" }}>{t(step.title)}</h3>
              <p className="text-sm leading-relaxed" style={{ color: "#8b8f98", lineHeight: 1.7 }}>{t(step.desc)}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

// ─── About ────────────────────────────────────────────────────────────────────
function About() {
  return (
    <section id="about" className="py-32 px-6 md:px-12" style={{ background: "#070809" }}>
      <div className="max-w-[1400px] mx-auto">
        <div className="grid md:grid-cols-2 gap-20 items-start">
          {/* Portrait */}
          <div className="relative">
            <div className="aspect-[3/4] max-w-sm relative overflow-hidden" style={{ border: "1px solid #252a33", boxShadow: "24px 24px 0 rgba(91,110,245,0.08)" }}>
              <img
                src={aboutPortrait}
                srcSet={`${aboutPortraitMobile} 480w, ${aboutPortrait} 768w`}
                sizes="(max-width: 767px) 85vw, 24rem"
                alt={t("Konsultant IT pracujący nad rozwiązaniem")}
                loading="lazy"
                decoding="async"
                className="absolute inset-0 w-full h-full object-cover"
                style={{ objectPosition: "64% center", filter: "saturate(0.82) contrast(1.06)" }}
              />
              <div className="absolute inset-0" style={{ background: "linear-gradient(180deg, rgba(7,8,9,0.12) 30%, rgba(7,8,9,0.78) 100%)" }} />
              <div className="absolute top-4 left-4 flex items-center gap-2 font-mono text-[9px] tracking-widest uppercase" style={{ color: "#d9ddff" }}>
                <span className="w-1.5 h-1.5 rounded-full" style={{ background: "#5b6ef5", boxShadow: "0 0 12px #5b6ef5" }} />
                {t("Od pomysłu do wdrożenia")}
              </div>
              <div className="absolute bottom-4 left-4 right-4 p-4" style={{ background: "rgba(7,8,9,0.68)", border: "1px solid rgba(255,255,255,0.12)", backdropFilter: "blur(10px)" }}>
                <div className="font-mono text-[9px] tracking-[0.18em] uppercase mb-2" style={{ color: "#8b8f98" }}>{t("Twój partner techniczny")}</div>
                <div className="text-sm font-medium" style={{ color: "#f5f5f5" }}>{t("Od planu po działające rozwiązanie.")}</div>
              </div>
            </div>
          </div>

          {/* Bio */}
          <div>
            <div className="font-mono text-[10px] tracking-[0.2em] uppercase mb-6" style={{ color: "#8b8f98" }}>{t("O mnie")}</div>
            <h2
              className="font-sans font-bold leading-tight mb-10"
              style={{ fontSize: "clamp(30px, 4vw, 52px)", letterSpacing: "-0.04em", color: "#f5f5f5" }}
            >
              {t("Rozmawiasz bezpośrednio")}
              <br />
              <span style={{ color: "#8b8f98" }}>{t("z osobą, która zajmie się Twoim projektem.")}</span>
            </h2>

            <p className="text-base leading-relaxed mb-5" style={{ color: "#8b8f98", lineHeight: 1.8 }}>
              {t("Jestem Dawid — developer i twórca systemów z szerokim zakresem kompetencji. Buduję rozwiązania IT od A do Z: od pierwszej rozmowy o problemie, przez projekt i kod, aż po serwer i wdrożenie produkcyjne.")}
            </p>
            <p className="text-base leading-relaxed mb-10" style={{ color: "#8b8f98", lineHeight: 1.8 }}>
              {t("Nie musisz koordynować kilku osób ani powtarzać tej samej historii. Masz jeden kontakt i jasną odpowiedzialność za cały projekt — także po wdrożeniu, gdy system działa już produkcyjnie.")}
            </p>

            <div
              className="p-6 mb-8"
              style={{ border: "1px solid rgba(91,110,245,0.2)", background: "rgba(91,110,245,0.03)" }}
            >
              <p className="text-sm leading-relaxed" style={{ color: "#8b8f98", fontStyle: "italic" }}>
                &ldquo;{t("Dobieram technologię do problemu, nie odwrotnie. Dzięki temu możesz skupić się na swoim biznesie, a nie na technicznych decyzjach.")}&rdquo;
              </p>
            </div>

            <a
              href="#contact"
              className="inline-flex items-center gap-2 text-sm font-mono tracking-widest uppercase"
              style={{ color: "#5b6ef5", transition: "opacity 0.2s" }}
              onMouseEnter={(e) => ((e.currentTarget as HTMLElement).style.opacity = "0.7")}
              onMouseLeave={(e) => ((e.currentTarget as HTMLElement).style.opacity = "1")}
            >
              {t("Porozmawiajmy o Twoim projekcie →")}
            </a>
          </div>
        </div>
      </div>
    </section>
  );
}

// ─── Contact ──────────────────────────────────────────────────────────────────
function Contact() {
  const { language } = useLanguage();
  const [form, setForm] = useState({ name: "", email: "", phone: "", budget: "", message: "", website: "" });
  const [status, setStatus] = useState<"idle" | "sending" | "sent" | "error">("idle");
  const [budgetOpen, setBudgetOpen] = useState(false);
  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setStatus("sending");
    try {
      const endpoint = import.meta.env.PROD
        ? "https://api.grzywniak.pl/contact.php"
        : "/api/contact.php";
      const response = await fetch(endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ...form, language }),
      });
      const result = await response.json().catch(() => null);
      if (!response.ok || !result?.ok) throw new Error("Message delivery failed");
      setForm({ name: "", email: "", phone: "", budget: "", message: "", website: "" });
      setStatus("sent");
    } catch {
      setStatus("error");
    }
  };

  const inputStyle = {
    background: "transparent",
    border: "1px solid #1a1d22",
    color: "#f5f5f5",
    padding: "14px 16px",
    fontSize: "14px",
    fontFamily: "inherit",
    outline: "none",
    width: "100%",
    transition: "border-color 0.2s",
  };

  return (
    <section id="contact" className="relative overflow-hidden py-40 px-6 md:px-12" style={{ background: "#0a0b0d" }}>
      <div className="blue-flare blue-flare--left" aria-hidden="true" />
      <div className="blue-flare blue-flare--right" aria-hidden="true" />
      <div className="max-w-[1400px] mx-auto relative z-10">
        <div className="grid md:grid-cols-2 gap-20">
          {/* Left */}
          <div>
            <div className="font-mono text-[10px] tracking-[0.2em] uppercase mb-8" style={{ color: "#8b8f98" }}>
              {t("Zacznijmy rozmowę")}
            </div>
            <h2
              className="font-sans font-bold leading-tight mb-6"
              style={{ fontSize: "clamp(36px, 5vw, 72px)", letterSpacing: "-0.04em", color: "#f5f5f5", lineHeight: 0.95 }}
            >
              {t("Masz pomysł.")}
              <br />
              <span style={{ color: "#8b8f98" }}>{t("Zbudujmy")}</span>
              <br />
              {t("rozwiązanie.")}
            </h2>

            <p className="text-base leading-relaxed mt-8 mb-6 max-w-sm" style={{ color: "#8b8f98", lineHeight: 1.75 }}>
              {t("Napisz, jaki rezultat chcesz osiągnąć i co dziś utrudnia pracę. Im więcej kontekstu podasz, tym trafniej przygotuję odpowiedź.")}
            </p>
            <p className="text-sm mb-12" style={{ color: "#8b8f98" }}>
              {t("Najpierw sprawdzę temat i wrócę z konkretną odpowiedzią.")}
            </p>

            <div className="space-y-4">
              <a
                href="mailto:dawid@grzywniak.pl"
                className="flex items-center gap-3 text-sm font-mono"
                style={{ color: "#8b8f98", transition: "color 0.2s" }}
                onMouseEnter={(e) => ((e.currentTarget as HTMLElement).style.color = "#f5f5f5")}
                onMouseLeave={(e) => ((e.currentTarget as HTMLElement).style.color = "#8b8f98")}
              >
                <span style={{ color: "#5b6ef5" }}>→</span>
                dawid@grzywniak.pl
              </a>
              <a
                href="tel:+48664870311"
                className="flex items-center gap-3 text-sm font-mono"
                style={{ color: "#8b8f98", transition: "color 0.2s" }}
                onMouseEnter={(e) => ((e.currentTarget as HTMLElement).style.color = "#f5f5f5")}
                onMouseLeave={(e) => ((e.currentTarget as HTMLElement).style.color = "#8b8f98")}
              >
                <span style={{ color: "#5b6ef5" }}>→</span>
                {t("Telefon: +48 664 870 311")}
              </a>
            </div>

            <div className="relative h-64 mt-12 overflow-hidden" style={{ border: "1px solid #303844" }}>
              <img src={contactDesk} srcSet={`${contactDeskMobile} 480w, ${contactDeskRetina} 640w, ${contactDesk} 1280w`} sizes="(max-width: 767px) calc(100vw - 3rem), 32rem" alt={t("Spokojne miejsce pracy")} loading="lazy" decoding="async" className="absolute inset-0 w-full h-full object-cover" />
              <div className="absolute inset-0" style={{ background: "linear-gradient(180deg, transparent, rgba(7,8,9,0.78))" }} />
              <div className="absolute bottom-5 left-5 font-mono text-[9px] tracking-[0.16em] uppercase" style={{ color: "#d9ddff" }}>{t("Odpowiadam osobiście")}</div>
            </div>
          </div>

          {/* Form */}
          <div className="p-6 md:p-10" style={{ border: "1px solid #303844", background: "linear-gradient(145deg, rgba(21,24,31,0.9), rgba(10,11,13,0.88))", boxShadow: "20px 20px 0 rgba(91,110,245,0.06)" }}>
            {status === "sent" ? (
              <div className="relative isolate min-h-[360px] overflow-hidden p-8 md:p-10 flex flex-col justify-center" role="status" aria-live="polite" style={{ border: "1px solid rgba(91,110,245,0.28)", background: "radial-gradient(circle at 88% 12%, rgba(91,110,245,0.16), transparent 36%), linear-gradient(145deg, rgba(30,35,58,0.68), rgba(10,11,13,0.82))" }}>
                <div className="absolute -right-16 -top-20 h-52 w-52 rounded-full opacity-60" aria-hidden="true" style={{ border: "1px solid rgba(147,160,255,0.22)", boxShadow: "0 0 0 24px rgba(91,110,245,0.035), 0 0 0 48px rgba(91,110,245,0.02)" }} />
                <div className="relative z-10 max-w-md">
                  <div className="mb-7 flex h-12 w-12 items-center justify-center rounded-full" style={{ border: "1px solid rgba(147,160,255,0.54)", background: "rgba(91,110,245,0.14)", boxShadow: "0 0 30px rgba(91,110,245,0.18)" }}>
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                      <path d="m5 12 4.2 4.2L19.5 6" stroke="#d9ddff" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                  </div>
                  <p className="mb-4 font-mono text-[10px] tracking-[0.2em] uppercase" style={{ color: "#93a0ff" }}>{t("Wiadomość dotarła")}</p>
                  <h3 className="font-sans font-bold text-3xl md:text-4xl mb-4 tracking-tight" style={{ letterSpacing: "-0.04em", color: "#f5f5f5" }}>
                    {t("Dziękuję za kontakt.")}
                  </h3>
                  <p className="text-sm leading-relaxed" style={{ color: "#b5bbc6" }}>{t("Odpowiem w ciągu 1–2 dni roboczych.")}</p>
                  <div className="my-7 h-px w-full" style={{ background: "linear-gradient(90deg, rgba(147,160,255,0.42), rgba(147,160,255,0))" }} />
                  <p className="mb-6 text-xs leading-relaxed" style={{ color: "#8b8f98" }}>{t("Możesz spokojnie zamknąć tę stronę — wiadomość jest już u mnie.")}</p>
                  <button type="button" onClick={() => setStatus("idle")} className="liquid-button liquid-button--secondary inline-flex w-fit items-center gap-2 px-5 py-3 font-mono text-[9px] tracking-[0.16em] uppercase" style={{ color: "#d9ddff" }}>
                    <span aria-hidden="true">+</span>{t("Wyślij kolejną wiadomość")}
                  </button>
                </div>
              </div>
            ) : (
            <form onSubmit={handleSubmit} className="space-y-4">
              <div aria-hidden="true" className="absolute -left-[10000px] h-px w-px overflow-hidden">
                <label htmlFor="contact-website">Website</label>
                <input
                  id="contact-website"
                  name="website"
                  type="text"
                  tabIndex={-1}
                  autoComplete="off"
                  value={form.website}
                  onChange={(e) => setForm((current) => ({ ...current, website: e.target.value }))}
                />
              </div>
                {status === "error" && (
                  <p className="p-4 text-sm" role="alert" style={{ color: "#ffb4b4", border: "1px solid rgba(255, 120, 120, 0.35)", background: "rgba(255, 120, 120, 0.06)" }}>
                    {t("Nie udało się wysłać wiadomości. Spróbuj ponownie lub napisz bezpośrednio na dawid@grzywniak.pl.")}
                  </p>
                )}
                <div>
                  <label htmlFor="contact-name" className="block font-mono text-[9px] tracking-[0.2em] uppercase mb-2" style={{ color: "#8b8f98" }}>{t("Imię / Firma")}</label>
                  <input
                    id="contact-name" name="name" type="text" required maxLength={120} autoComplete="name" value={form.name}
                    onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                    placeholder={t("Jak mam się do Ciebie zwracać?")}
                    style={{ ...inputStyle }}
                    onFocus={(e) => ((e.target as HTMLInputElement).style.borderColor = "#5b6ef5")}
                    onBlur={(e) => ((e.target as HTMLInputElement).style.borderColor = "#1a1d22")}
                  />
                </div>
                <div>
                  <label htmlFor="contact-email" className="block font-mono text-[9px] tracking-[0.2em] uppercase mb-2" style={{ color: "#8b8f98" }}>{t("Email")}</label>
                  <input
                    id="contact-email" name="email" type="email" required maxLength={254} autoComplete="email" value={form.email}
                    onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
                    placeholder={t("Na jaki adres mam odpisać?")}
                    style={{ ...inputStyle }}
                    onFocus={(e) => ((e.target as HTMLInputElement).style.borderColor = "#5b6ef5")}
                    onBlur={(e) => ((e.target as HTMLInputElement).style.borderColor = "#1a1d22")}
                  />
                </div>
                <div>
                  <label htmlFor="contact-phone" className="block font-mono text-[9px] tracking-[0.2em] uppercase mb-2" style={{ color: "#8b8f98" }}>{t("Telefon (opcjonalnie)")}</label>
                  <input
                    id="contact-phone" name="phone" type="tel" inputMode="tel" maxLength={40} autoComplete="tel" value={form.phone}
                    onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
                    placeholder={t("Np. +48 600 000 000")}
                    style={{ ...inputStyle }}
                    onFocus={(e) => ((e.target as HTMLInputElement).style.borderColor = "#5b6ef5")}
                    onBlur={(e) => ((e.target as HTMLInputElement).style.borderColor = "#1a1d22")}
                  />
                </div>
                <fieldset className="relative">
                  <legend className="font-mono text-[9px] tracking-[0.2em] uppercase mb-2" style={{ color: "#8b8f98" }}>{t("Orientacyjny budżet (opcjonalnie)")}</legend>
                  <p id="budget-help" className="text-xs mb-3" style={{ color: "#68707d" }}>{t("Pomoże mi lepiej przygotować pierwszą odpowiedź.")}</p>
                  <button
                    type="button"
                    aria-expanded={budgetOpen}
                    aria-controls="budget-options"
                    onClick={() => setBudgetOpen((open) => !open)}
                    className="flex min-h-12 w-full items-center justify-between gap-4 px-4 text-left font-mono text-[10px] tracking-wide"
                    style={{ color: form.budget ? "#dce1ff" : "#8b8f98", border: `1px solid ${budgetOpen ? "rgba(112, 130, 255, 0.72)" : "#1a1d22"}`, background: budgetOpen ? "rgba(91, 110, 245, 0.09)" : "rgba(7, 8, 9, 0.44)", transition: "color 0.2s, border-color 0.2s, background 0.2s" }}
                  >
                    <span>{form.budget ? t(form.budget) : t("Wybierz przedział budżetowy")}</span>
                    <span aria-hidden="true" className="text-base leading-none" style={{ color: "#93a0ff", transform: budgetOpen ? "rotate(180deg)" : "rotate(0deg)", transition: "transform 0.2s" }}>⌄</span>
                  </button>
                  {budgetOpen && (
                    <div id="budget-options" className="budget-dropdown absolute top-full left-0 right-0 z-20 mt-2 grid grid-cols-2 gap-2 p-2" role="group" aria-describedby="budget-help" style={{ border: "1px solid rgba(91, 110, 245, 0.24)", background: "rgba(7, 8, 9, 0.96)", boxShadow: "0 16px 38px rgba(0, 0, 0, 0.36)" }}>
                      {[
                        "Do 1 tys. zł", "1–2 tys. zł", "2–5 tys. zł", "5–10 tys. zł",
                        "10–20 tys. zł", "20–50 tys. zł", "50–100 tys. zł", "100 tys. zł+", "Jeszcze nie wiem",
                      ].map((option) => {
                        const selected = form.budget === option;
                        return (
                          <button
                            key={option}
                            type="button"
                            aria-pressed={selected}
                            onClick={() => {
                              setForm((f) => ({ ...f, budget: f.budget === option ? "" : option }));
                              setBudgetOpen(false);
                            }}
                            className="min-h-11 px-3 text-left font-mono text-[10px] tracking-wide"
                            style={{ color: selected ? "#dce1ff" : "#8b8f98", border: `1px solid ${selected ? "rgba(112, 130, 255, 0.72)" : "#1a1d22"}`, background: selected ? "rgba(91, 110, 245, 0.14)" : "rgba(7, 8, 9, 0.44)", cursor: "pointer", transition: "color 0.2s, border-color 0.2s, background 0.2s" }}
                          >
                            {t(option)}
                          </button>
                        );
                      })}
                    </div>
                  )}
                </fieldset>
                <div>
                  <label htmlFor="contact-message" className="block font-mono text-[9px] tracking-[0.2em] uppercase mb-2" style={{ color: "#8b8f98" }}>
                    {t("Co chcesz osiągnąć?")}
                  </label>
                  <textarea
                    id="contact-message" name="message" required minLength={10} maxLength={5000} rows={6} value={form.message}
                    onChange={(e) => setForm((f) => ({ ...f, message: e.target.value }))}
                    placeholder={t("Co dziś nie działa lub co chcesz usprawnić?")}
                    style={{ ...inputStyle, resize: "none" }}
                    onFocus={(e) => ((e.target as HTMLTextAreaElement).style.borderColor = "#5b6ef5")}
                    onBlur={(e) => ((e.target as HTMLTextAreaElement).style.borderColor = "#1a1d22")}
                  />
                </div>
                <div className="pt-2">
                  <button
                    type="submit"
                    disabled={status === "sending"}
                    className="liquid-button liquid-button--primary w-full py-4 text-sm font-sans font-medium"
                    style={{ letterSpacing: "-0.01em", cursor: status === "sending" ? "wait" : "pointer", opacity: status === "sending" ? 0.65 : 1 }}
                  >
                    {status === "sending" ? t("Wysyłanie…") : t("Wyślij wiadomość →")}
                  </button>
                  <p className="text-center text-xs mt-3" style={{ color: "#7d8795" }}>
                    {t("Wiadomość zostanie wysłana bezpośrednio z formularza. Odpiszę w ciągu 1–2 dni.")}
                  </p>
                </div>
              </form>
            )}
          </div>
        </div>
      </div>
      <ReturnToTop />
    </section>
  );
}

// ─── Return to top & footer ──────────────────────────────────────────────────
function ReturnToTop() {
  return (
    <section className="relative z-10 px-6 pt-20 md:px-12 md:pt-24 pb-10 md:pb-12">
      <div className="max-w-[1400px] mx-auto flex justify-center">
        <div className="return-to-top-wrap">
          <a href="#" className="liquid-button liquid-button--secondary inline-flex items-center gap-3 px-5 py-3 font-mono text-[9px] tracking-[0.18em] uppercase" style={{ color: "#c8ceff" }}>
            <span aria-hidden="true" className="flex h-5 w-5 items-center justify-center rounded-full" style={{ border: "1px solid rgba(183,193,255,0.36)" }}>↑</span>
            {t("Na górę")}
          </a>
        </div>
      </div>
    </section>
  );
}

function Footer() {
  return (
    <footer className="px-6 md:px-12 py-5 md:py-6" style={{ background: "#070809", borderTop: "1px solid #1a1d22" }}>
      <div className="max-w-[1400px] mx-auto text-center font-mono text-[9px] tracking-[0.1em]" style={{ color: "#7d8795" }}>
        {t("© 2026 Dawid Grzywniak — Wszelkie prawa zastrzeżone.")}
      </div>
    </footer>
  );
}

// ─── App ──────────────────────────────────────────────────────────────────────
function AppContent() {
  useLanguage();
  return (
    <div className="noise relative isolate">
      <AmbientSignals />
      <CustomCursor />
      <ScrollProgress />
      <ClickFeedback />
      <Nav />
      <main id="main-content" className="relative z-10 page-content">
        <Hero />
        <ScrollReveal><ValueProposition /></ScrollReveal>
        <ScrollReveal><ProblemFirst /></ScrollReveal>
        <ScrollReveal><Capabilities /></ScrollReveal>
        <ScrollReveal><VisualHighlights /></ScrollReveal>
        <ScrollReveal><StartFromProblem /></ScrollReveal>
        <ScrollReveal><Process /></ScrollReveal>
        <ScrollReveal><About /></ScrollReveal>
        <ScrollReveal><Contact /></ScrollReveal>
      </main>
      <div className="relative z-10 page-footer"><Footer /></div>
    </div>
  );
}

export default function App() {
  return <I18nProvider><AppContent /></I18nProvider>;
}
