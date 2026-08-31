import { useEffect, useRef, useState } from "react";
import { t, useLanguage } from "./i18n";

export default function HeroCanvas() {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const animRef = useRef<number>(0);
  const activeNodeRef = useRef(-1);
  const [activeNode, setActiveNode] = useState<{ label: string; detail: string } | null>(null);
  const { language } = useLanguage();

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext("2d")!;
    let width = canvas.offsetWidth;
    let height = canvas.offsetHeight;
    const resize = () => {
      width = canvas.offsetWidth;
      height = canvas.offsetHeight;
      canvas.width = width * window.devicePixelRatio;
      canvas.height = height * window.devicePixelRatio;
      ctx.setTransform(window.devicePixelRatio, 0, 0, window.devicePixelRatio, 0, 0);
    };
    resize();

    const nodes = [
      { label: t("POMYSŁ"), detail: t("Punkt wyjścia: cel, problem i plan działania."), x: width * 0.48, y: height * 0.5, baseX: width * 0.48, baseY: height * 0.5, r: 50, core: true },
      { label: t("STRONA"), detail: t("Czytelna obecność firmy i pierwszy punkt kontaktu z klientem."), x: width * 0.18, y: height * 0.26, baseX: width * 0.18, baseY: height * 0.26, r: 33, core: false },
      { label: t("APLIKACJA"), detail: t("Narzędzie, które porządkuje codzienną pracę i obsługę klientów."), x: width * 0.77, y: height * 0.2, baseX: width * 0.77, baseY: height * 0.2, r: 32, core: false },
      { label: t("INTEGRACJE"), detail: t("Połączenia z usługami, z których firma już korzysta."), x: width * 0.84, y: height * 0.5, baseX: width * 0.84, baseY: height * 0.5, r: 30, core: false },
      { label: t("AUTOMATYZACJA"), detail: t("Powtarzalne zadania wykonują się same, bez ręcznego przepisywania."), x: width * 0.7, y: height * 0.79, baseX: width * 0.7, baseY: height * 0.79, r: 33, core: false },
      { label: t("DANE"), detail: t("Wspólne, uporządkowane dane dostępne tam, gdzie są potrzebne."), x: width * 0.3, y: height * 0.82, baseX: width * 0.3, baseY: height * 0.82, r: 32, core: false },
      { label: t("WSPARCIE"), detail: t("Pomoc po uruchomieniu i spokojny rozwój rozwiązania."), x: width * 0.14, y: height * 0.6, baseX: width * 0.14, baseY: height * 0.6, r: 30, core: false },
    ];
    const connections: [number, number][] = [[0, 1], [0, 2], [0, 4], [2, 3], [2, 5], [4, 3], [4, 5], [2, 6], [4, 6]];
    const particles = Array.from({ length: 14 }, () => {
      const [from, to] = connections[Math.floor(Math.random() * connections.length)];
      return { x: nodes[from].x, y: nodes[from].y, progress: Math.random(), from, to, speed: 0.003 + Math.random() * 0.003 };
    });

    let time = 0;
    let shouldAnimate = !document.hidden;
    const draw = () => {
      if (!shouldAnimate) {
        animRef.current = 0;
        return;
      }
      ctx.clearRect(0, 0, width, height);
      time += 0.008;
      nodes.forEach((node, index) => {
        if (node.core) return;
        const padding = node.r + 14;
        node.x = Math.max(padding, Math.min(width - padding, node.baseX + Math.sin(time * 0.9 + index * 1.3) * 7));
        node.y = Math.max(padding, Math.min(height - padding, node.baseY + Math.cos(time * 0.7 + index * 0.9) * 6));
      });
      const activeIndex = activeNodeRef.current;
      connections.forEach(([from, to], index) => {
        const a = nodes[from], b = nodes[to];
        const related = activeIndex === -1 || from === activeIndex || to === activeIndex;
        ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y);
        ctx.strokeStyle = `rgba(91,110,245,${(related ? (from === 0 || to === 0 ? 0.3 : 0.19) : 0.035) + Math.sin(time * 2 + index) * 0.03})`;
        ctx.lineWidth = from === 0 || to === 0 ? 0.8 : 0.55;
        ctx.stroke();
      });
      particles.forEach((particle) => {
        particle.progress += particle.speed;
        if (particle.progress > 1) {
          const [from, to] = connections[Math.floor(Math.random() * connections.length)];
          particle.progress = 0; particle.from = from; particle.to = to;
        }
        const from = nodes[particle.from], to = nodes[particle.to];
        particle.x = from.x + (to.x - from.x) * particle.progress;
        particle.y = from.y + (to.y - from.y) * particle.progress;
        ctx.beginPath(); ctx.arc(particle.x, particle.y, 1.5, 0, Math.PI * 2);
        ctx.fillStyle = `rgba(91,110,245,${0.6 + Math.sin(time * 3 + particle.progress * 10) * 0.3})`; ctx.fill();
      });
      nodes.forEach((node, index) => {
        const related = activeIndex === -1 || index === activeIndex || connections.some(([from, to]) => (from === activeIndex && to === index) || (to === activeIndex && from === index));
        const pulse = Math.sin(time * 1.5 + index * 0.8) * 0.5 + 0.5;
        ctx.globalAlpha = related ? 1 : 0.3;
        if (node.core) {
          const glow = ctx.createRadialGradient(node.x, node.y, 0, node.x, node.y, 80);
          glow.addColorStop(0, "rgba(91,110,245,0.12)"); glow.addColorStop(1, "rgba(91,110,245,0)");
          ctx.beginPath(); ctx.arc(node.x, node.y, 80, 0, Math.PI * 2); ctx.fillStyle = glow; ctx.fill();
        }
        ctx.beginPath(); ctx.arc(node.x, node.y, node.r + 4 + pulse * 4, 0, Math.PI * 2);
        ctx.strokeStyle = node.core ? `rgba(91,110,245,${0.15 + pulse * 0.1})` : `rgba(91,110,245,${0.06 + pulse * 0.04})`; ctx.lineWidth = 0.5; ctx.stroke();
        ctx.beginPath(); ctx.arc(node.x, node.y, node.r, 0, Math.PI * 2);
        ctx.fillStyle = node.core ? "rgba(10,11,13,0.95)" : "rgba(10,11,13,0.8)"; ctx.fill();
        ctx.strokeStyle = node.core ? "rgba(91,110,245,0.5)" : "rgba(26,29,34,0.8)"; ctx.lineWidth = node.core ? 1 : 0.5; ctx.stroke();
        ctx.fillStyle = node.core ? "#f5f5f5" : "#8b8f98"; ctx.font = node.core ? "bold 11px 'JetBrains Mono', monospace" : "8px 'JetBrains Mono', monospace";
        ctx.textAlign = "center"; ctx.textBaseline = "middle"; ctx.fillText(node.label, node.x, node.y); ctx.globalAlpha = 1;
      });
      animRef.current = requestAnimationFrame(draw);
    };
    const onResize = () => {
      resize();
      const anchors = [[0.48, 0.5], [0.18, 0.26], [0.77, 0.2], [0.84, 0.5], [0.7, 0.79], [0.3, 0.82], [0.14, 0.6]];
      nodes.forEach((node, index) => {
        node.baseX = width * anchors[index][0]; node.baseY = height * anchors[index][1]; node.x = node.baseX; node.y = node.baseY;
      });
    };
    const onMouseMove = (event: MouseEvent) => {
      const rect = canvas.getBoundingClientRect();
      const x = event.clientX - rect.left, y = event.clientY - rect.top;
      const nextIndex = nodes.findIndex((node) => Math.hypot(node.x - x, node.y - y) <= node.r + 12);
      if (nextIndex === activeNodeRef.current) return;
      activeNodeRef.current = nextIndex; canvas.style.cursor = nextIndex === -1 ? "default" : "pointer";
      setActiveNode(nextIndex === -1 ? null : { label: nodes[nextIndex].label, detail: nodes[nextIndex].detail });
    };
    const onMouseLeave = () => { activeNodeRef.current = -1; canvas.style.cursor = "default"; setActiveNode(null); };
    const visibilityObserver = new IntersectionObserver(([entry]) => {
      shouldAnimate = entry.isIntersecting && !document.hidden;
      if (shouldAnimate && !animRef.current) draw();
    }, { threshold: 0.05 });
    const onDocumentVisibility = () => {
      shouldAnimate = !document.hidden && canvas.getBoundingClientRect().bottom > 0 && canvas.getBoundingClientRect().top < window.innerHeight;
      if (shouldAnimate && !animRef.current) draw();
    };
    window.addEventListener("resize", onResize);
    canvas.addEventListener("mousemove", onMouseMove);
    canvas.addEventListener("mouseleave", onMouseLeave);
    visibilityObserver.observe(canvas);
    document.addEventListener("visibilitychange", onDocumentVisibility);
    if (shouldAnimate) draw();
    return () => {
      cancelAnimationFrame(animRef.current); visibilityObserver.disconnect(); document.removeEventListener("visibilitychange", onDocumentVisibility);
      window.removeEventListener("resize", onResize); canvas.removeEventListener("mousemove", onMouseMove); canvas.removeEventListener("mouseleave", onMouseLeave);
    };
  }, [language]);

  return <div className="relative w-full h-full"><canvas ref={canvasRef} className="w-full h-full" style={{ opacity: 0.9 }} /><div className="absolute top-5 left-5 max-w-52 pointer-events-none transition-opacity duration-200" style={{ opacity: activeNode ? 1 : 0.7 }}><div className="font-mono text-[9px] tracking-[0.18em] uppercase mb-2" style={{ color: "#5b6ef5" }}>{activeNode ? activeNode.label : t("Interaktywna mapa")}</div><p className="text-xs leading-relaxed" style={{ color: "#8b8f98" }}>{activeNode ? activeNode.detail : t("Najedź na element, aby zobaczyć jego rolę i powiązania.")}</p></div></div>;
}
