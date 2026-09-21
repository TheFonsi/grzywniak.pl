import { FormEvent, useEffect, useRef, useState } from "react";
import { languages, useLanguage } from "./i18n";
import discoveryConsultant from "./assets/discovery-consultant.jpg";

type Role = "assistant" | "user";
type Message = { id: string; role: Role; content: string };
type Summary = { title?: string; sections?: { title: string; content: string[] }[] };
type Session = {
  id: string;
  status: string;
  messages: Message[];
  readyForSummary: boolean;
  summary?: Summary;
  progress?: number;
  showContactForm?: boolean;
  suggestedAnswers?: string[];
  draftAnalysis?: { status?: string; missingInformation?: string[] };
  followUpQuestionIndex?: number;
};

function openingMessage(language: string): Message {
  return {
    id: "welcome",
    role: "assistant",
    content:
      language === "en"
        ? "Briefly tell me what system or solution you would like to create. You do not need to know the technology or prepare a specification — start with the problem you want to solve, and I will help organise the requirements."
        : "Opowiedz mi krótko, jaki system lub rozwiązanie chcesz stworzyć. Nie musisz znać technologii ani przygotowywać specyfikacji — zacznij od problemu, który chcesz rozwiązać, a ja pomogę uporządkować wymagania.",
  };
}

function filterMismatchedSuggestions(values: string[], question: string, isEnglish: boolean): string[] {
  const explicitTimeline = isEnglish
    ? /(when|deadline|launch|go live|timeline|by what date|target date)/i
    : /(na kiedy|do kiedy|jaki(?: jest)? termin|orientacyjny termin|kiedy planujesz|kiedy ma by[cć]|kiedy powin(?:no|na|ien)|w jakim terminie|deadline|data uruchomienia|termin uruchomienia)/i;
  const timelineValues = /(jak najszybciej|w ciągu miesiąca|w ci.{0,2}gu miesiąca|\bmiesiąc|termin elastyczny|as soon as possible|within a month|months?)/i;
  if (timelineValues.test(values.join(" ")) && !explicitTimeline.test(question)) return [];
  const timeline = isEnglish ? /(as soon as possible|within a month|months?|flexible timing)/i : /(jak najszybciej|ciągu miesiąca|miesiące|termin elastyczny)/i;
  const budget = isEnglish ? /(up to PLN|PLN \d|over PLN)/i : /(tys\. zł|zł|budżet)/i;
  const sections = isEnglish ? /(sections?|pages?)/i : /(sekcj|podstron)/i;
  if (timeline.test(values.join(" ")) && !/(when|deadline|launch|go live|timeline)/i.test(question)) return [];
  if (budget.test(values.join(" ")) && !/(budget|price|cost)/i.test(question)) return [];
  if (sections.test(values.join(" ")) && !/(subpage|sections?|number of pages|how many pages)/i.test(question)) return [];
  return values;
}

function suggestedAnswersFor(session: Session | null, isEnglish: boolean): string[] {
  const lastAssistant = [...(session?.messages ?? [])].reverse().find((message) => message.role === "assistant");
  const rawMessage = lastAssistant?.content ?? "";
  const sentenceStart = Math.max(rawMessage.lastIndexOf("."), rawMessage.lastIndexOf("\n"));
  const question = rawMessage.slice(sentenceStart + 1).replace(/\*\*/g, "").trim().toLocaleLowerCase(isEnglish ? "en-US" : "pl-PL");
  // Timeline choices are reserved for explicit deadline/start-date questions.
  // A question mentioning “wdrożenie” can still be about features or process.
  const explicitTimelineQuestion = isEnglish
    ? /(when|deadline|launch|go live|timeline|by what date|target date)/.test(question)
    : /(na kiedy|do kiedy|jaki(?: jest)? termin|orientacyjny termin|kiedy planujesz|kiedy ma by[cć]|kiedy powin(?:no|na|ien)|w jakim terminie|deadline|data uruchomienia|termin uruchomienia)/.test(question);
  if (!explicitTimelineQuestion && /(?:wdro|realizac|uruchom|start)/.test(question)) {
    const current = session?.suggestedAnswers ?? [];
    if (/(jak najszybciej|w ciągu miesiąca|w ci.{0,2}gu miesiąca|\bmiesiąc|termin elastyczny|as soon as possible|within a month|months?)/i.test(current.join(" "))) return [];
    return filterMismatchedSuggestions(current, question, isEnglish);
  }
  if (isEnglish) {
    if (/(subpage|sections?|number of pages|how many pages)/.test(question)) return ["Offer, projects, about us and contact", "Offer, projects and contact", "Expanded portfolio with references", "I’m not sure yet"];
    if (/(budget|price|cost)/.test(question)) return ["Up to PLN 1k", "PLN 1–2k", "PLN 2–5k", "PLN 5–10k", "PLN 10–20k", "Over PLN 30k"];
    if (/(when|deadline|launch|go live|timeline)/.test(question)) return ["As soon as possible", "Within a month", "1–3 months", "3–6 months", "Flexible timing"];
    return filterMismatchedSuggestions(session?.suggestedAnswers ?? [], question, true);
  }
  if (/(podstron|sekcj|ile stron|liczb[aeęy] stron)/.test(question)) return ["Oferta, realizacje, o firmie i kontakt", "Oferta, realizacje i kontakt", "Rozbudowane portfolio z referencjami", "Nie wiem jeszcze"];
  if (/(budżet|budzec|koszt|wycen)/.test(question)) return ["do 1 tys. zł", "1–2 tys. zł", "2–5 tys. zł", "5–10 tys. zł", "10–20 tys. zł", "powyżej 30 tys. zł"];
  if (/(na kiedy|do kiedy|termin|uruchom|wdroż|wdroz|start)/.test(question)) return ["Jak najszybciej", "W ciągu miesiąca", "1–3 miesiące", "3–6 miesięcy", "Termin elastyczny"];
  return filterMismatchedSuggestions(session?.suggestedAnswers ?? [], question, false);
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const endpoint = import.meta.env.PROD
    ? "https://api.grzywniak.pl/discovery.php"
    : "/api/discovery.php";
  const response = await fetch(`${endpoint}${path}`, {
    headers: { "Content-Type": "application/json", ...(init?.headers ?? {}) },
    ...init,
  });
  const raw = await response.text();
  let data: (T & { message?: string }) | null = null;
  try {
    data = JSON.parse(raw) as T & { message?: string };
  } catch {
    throw new Error("Serwer zwrócił nieprawidłową odpowiedź. Odśwież stronę i spróbuj ponownie.");
  }
  if (!response.ok) throw new Error(data.message ?? "Nie udało się połączyć z analitykiem.");
  return data;
}

export default function DiscoveryPage({ onClose }: { onClose: () => void }) {
  const { language, setLanguage } = useLanguage();
  const isEnglish = language === "en";
  const opening = openingMessage(language);
  const labels = isEnglish
    ? {
        offTopic: "For another matter, you can contact us directly through the form.",
        contactForm: "Open the email form",
        closed: "The conversation was closed without sending the brief to the team.",
        ready:
          "We have enough information to begin. Send the brief now, or add details that will help us prepare.",
        resume: "Add more details",
        sendBrief: "Send brief to team",
        safety: "For your security, do not enter passwords or card details.",
        stage: "Conversation stage",
        delivered: "Brief sent",
        ended: "Conversation closed",
        readyToSend: "Ready to send",
        start: "Let's begin",
        collecting: "Collecting key information",
        fields: "Problem · users · scope · budget · timeline",
        endConversation: "End conversation",
        newConversation: "Start a new conversation",
      }
    : {
        offTopic: "W innej sprawie możesz napisać bezpośrednio przez formularz.",
        contactForm: "Przejdź do formularza e-mail",
        closed: "Rozmowa została zakończona bez przekazywania briefu zespołowi.",
        ready:
          "Mamy wystarczająco informacji na start. Możesz przekazać brief teraz albo dodać szczegóły, które ułatwią zespołowi przygotowanie propozycji.",
        resume: "Dodaj więcej szczegółów",
        sendBrief: "Przekaż brief zespołowi",
        safety: "Dla bezpieczeństwa nie wpisuj haseł ani danych karty.",
        stage: "Etap rozmowy",
        delivered: "Brief przekazany",
        ended: "Rozmowa zakończona",
        readyToSend: "Gotowe do przekazania",
        start: "Zacznijmy rozmowę",
        collecting: "Zbieramy najważniejsze informacje",
        fields: "Problem · użytkownicy · zakres · budżet · termin",
        newConversation: "Rozpocznij nową rozmowę",
      };
  const [session, setSession] = useState<Session | null>(null);
  const [text, setText] = useState("");
  const [sending, setSending] = useState(false);
  const [busyAction, setBusyAction] = useState<"reply" | "delivering" | null>(null);
  const [preparingBrief, setPreparingBrief] = useState(false);
  const [error, setError] = useState("");
  const endRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLTextAreaElement>(null);
  const prepareBriefPromiseRef = useRef<Promise<void> | null>(null);

  const createSession = async () => {
    // The welcome question is static, so show it immediately instead of a
    // misleading "Analizuje..." indicator while the session is being created.
    setSession(null);
    setError("");
    try {
      const data = await request<{ session: Session }>("?action=session", {
        method: "POST",
        body: JSON.stringify({ language }),
      });
      setSession(data.session);
    } catch (err) {
      setSession({ id: "", status: "STARTED", messages: [opening], readyForSummary: false });
      setError(err instanceof Error ? err.message : "Wystąpił nieoczekiwany błąd.");
    }
  };

  const prepareBrief = (): Promise<void> => {
    if (!session?.id || session.draftAnalysis) return Promise.resolve();
    if (prepareBriefPromiseRef.current) return prepareBriefPromiseRef.current;
    setPreparingBrief(true);
    const task = (async () => {
      try {
        const data = await request<{ session: Session }>(
          `?action=prepare&sessionId=${encodeURIComponent(session.id)}`,
          { method: "POST", body: "{}" },
        );
        setSession(data.session);
      } catch (err) {
        setError(err instanceof Error ? err.message : "Nie udało się przygotować briefu.");
      }
    })();
    prepareBriefPromiseRef.current = task.finally(() => {
      prepareBriefPromiseRef.current = null;
      setPreparingBrief(false);
    });
    return prepareBriefPromiseRef.current;
  };

  useEffect(() => {
    void createSession();
  }, []);
  useEffect(() => {
    if (session?.readyForSummary && !session.summary && !session.draftAnalysis) void prepareBrief();
  }, [session?.id, session?.readyForSummary, session?.summary, session?.draftAnalysis]);
  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [session?.messages.length, sending]);
  useEffect(() => {
    if (!sending && session?.id && !session.readyForSummary && session.status !== "COMPLETED")
      inputRef.current?.focus();
  }, [sending, session?.id, session?.readyForSummary, session?.status]);

  const sendMessage = async (content: string) => {
    if (!content || sending || !session?.id) return;
    setText("");
    setSending(true);
    setBusyAction("reply");
    setError("");
    const optimistic: Message = { id: `local-${Date.now()}`, role: "user", content };
    setSession((current) =>
      current ? { ...current, messages: [...current.messages, optimistic] } : current,
    );
    try {
      const data = await request<{ session: Session }>(
        `?action=message&sessionId=${encodeURIComponent(session.id)}`,
        {
          method: "POST",
          body: JSON.stringify({ message: content }),
        },
      );
      setSession(data.session);
    } catch (err) {
      setSession((current) =>
        current
          ? { ...current, messages: current.messages.filter((m) => m.id !== optimistic.id) }
          : current,
      );
      setText(content);
      setError(err instanceof Error ? err.message : "Nie udało się wysłać wiadomości.");
    } finally {
      setSending(false);
      setBusyAction(null);
    }
  };

  const send = (event: FormEvent) => {
    event.preventDefault();
    void sendMessage(text.trim());
  };

  const generateSummary = async () => {
    if (!session?.id || sending) return;
    setSending(true);
    setBusyAction("delivering");
    setError("");
    try {
      if (!session.draftAnalysis) await prepareBrief();
      const data = await request<{ session: Session }>(
        `?action=complete&sessionId=${encodeURIComponent(session.id)}`,
        { method: "POST", body: "{}" },
      );
      setSession(data.session);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Nie udało się przygotować podsumowania.");
    } finally {
      setSending(false);
      setBusyAction(null);
    }
  };

  const addMore = async (withQuestion = false) => {
    if (!session?.id) return;
    setSending(true);
    setError("");
    try {
      const data = await request<{ session: Session }>(
        `?action=reopen&sessionId=${encodeURIComponent(session.id)}`,
        { method: "POST", body: JSON.stringify({ followUp: withQuestion }) },
      );
      setSession(data.session);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Nie udało się otworzyć rozmowy.");
    } finally {
      setSending(false);
    }
  };

  const closeWithoutSending = async () => {
    if (!session?.id) return;
    setSending(true);
    setError("");
    try {
      const data = await request<{ session: Session }>(
        `?action=discard&sessionId=${encodeURIComponent(session.id)}`,
        { method: "POST", body: "{}" },
      );
      setSession(data.session);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Nie udało się zakończyć rozmowy.");
    } finally {
      setSending(false);
    }
  };

  const changeLanguage = async (nextLanguage: "pl" | "en") => {
    setLanguage(nextLanguage);
    if (!session?.id || nextLanguage === language) return;
    if (!session.messages.some((message) => message.role === "user"))
      setSession({ ...session, messages: [openingMessage(nextLanguage)] });
    try {
      const data = await request<{ session: Session }>(
        `?action=language&sessionId=${encodeURIComponent(session.id)}`,
        { method: "POST", body: JSON.stringify({ language: nextLanguage }) },
      );
      setSession(data.session);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Nie udało się zmienić języka rozmowy.");
    }
  };

  const messages = session?.messages?.length ? session.messages : [opening];
  return (
    <main className="discovery-page-enter min-h-screen bg-[#070809] px-4 py-5 text-[#f5f5f5] sm:px-7 sm:py-8">
      <div className="mx-auto max-w-5xl">
        <header className="mb-6 flex items-center justify-between border-b border-[#252a33] pb-5">
          <button
            onClick={onClose}
            className="font-mono text-xs uppercase tracking-[.16em] text-[#aeb7d8] hover:text-white"
          >
            ← {isEnglish ? "Home" : "Strona główna"}
          </button>
          <div className="flex items-center gap-3">
            <span className="hidden rounded-full border border-[#4c5ab8] bg-[#12162c] px-3 py-1 font-mono text-[10px] uppercase tracking-[.15em] text-[#c8ceff] sm:inline">
              {isEnglish ? "AI Discovery" : "Konsultant AI"}
            </span>
            <div
              className="flex items-center gap-1"
              role="group"
              aria-label={isEnglish ? "Language selector" : "Wybór języka"}
            >
              {languages.map((option) => (
                <button
                  key={option.code}
                  type="button"
                  onClick={() => void changeLanguage(option.code)}
                  aria-pressed={language === option.code}
                  className="min-h-8 min-w-8 px-2 font-mono text-xs tracking-widest transition"
                  style={{
                    color: language === option.code ? "#f5f5f5" : "#7d8795",
                    borderBottom:
                      language === option.code ? "1px solid #7484ff" : "1px solid transparent",
                  }}
                >
                  {option.label}
                </button>
              ))}
            </div>
          </div>
        </header>
        <div className="grid gap-5 lg:grid-cols-[1fr_280px]">
          {session?.summary ? (
            <section className="overflow-y-auto rounded-2xl border border-[#5060bd] bg-[#11152a] p-5 shadow-2xl shadow-black/30 sm:p-7">
              <SummaryView summary={session.summary} />
            </section>
          ) : (
          <section className="overflow-hidden rounded-2xl border border-[#2c3340] bg-[#0c0e12] shadow-2xl shadow-black/30">
            <div className="border-b border-[#252a33] px-5 py-5 sm:px-7">
              <p className="font-mono text-xs uppercase tracking-[.16em] text-[#7c8dff]">
                {isEnglish ? "AI Project Consultant" : "AI Analityk Projektowy"}
              </p>
              <h1 className="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">
                {isEnglish ? "Tell us what you need" : "Opowiedz nam, czego potrzebujesz"}
              </h1>
              <p className="mt-2 max-w-xl text-sm text-[#a5acb8]">
                {isEnglish
                  ? "A short conversation will help us understand the problem and prepare a sensible initial scope."
                  : "Krótka rozmowa pomoże nam zrozumieć problem i przygotować sensowny pierwszy zakres."}
              </p>
            </div>
            <div
              className="h-[48vh] min-h-[380px] space-y-5 overflow-y-auto px-5 py-6 sm:px-7"
              aria-live="polite"
            >
              {messages.map((message) => (
                <Bubble key={message.id} message={message} />
              ))}
              {!sending &&
              !session?.readyForSummary &&
              session?.status !== "COMPLETED" &&
              suggestedAnswersFor(session, isEnglish).length ? (
                <div className="flex flex-wrap gap-2">
                  <span className="w-full text-xs text-[#8d97a7]">
                    {isEnglish ? "Suggested answers" : "Sugerowane odpowiedzi"}
                  </span>
                  {suggestedAnswersFor(session, isEnglish).map((answer) => (
                    <button
                      key={answer}
                      type="button"
                      disabled={sending || preparingBrief}
                      onClick={() => void sendMessage(answer)}
                      className="rounded-full border border-[#4d5fbd] bg-[#12182b] px-3 py-1.5 text-sm text-[#dce2ff] transition hover:border-[#8495ff] hover:bg-[#1b2450] disabled:pointer-events-none disabled:opacity-40"
                    >
                      {answer}
                    </button>
                  ))}
                </div>
              ) : null}
              {sending && (
                <div className="flex items-center gap-2 text-sm text-[#a5acb8]">
                  <span className="flex gap-1 rounded-full border border-[#2b313c] bg-[#14171d] px-3 py-2 text-[#8493ff]">
                    <span className="animate-bounce">●</span>
                    <span className="animate-bounce [animation-delay:150ms]">●</span>
                    <span className="animate-bounce [animation-delay:300ms]">●</span>
                  </span>
                  <span>
                    {busyAction === "delivering"
                      ? isEnglish
                        ? "Sending the brief to the team…"
                        : "Przekazujemy brief zespołowi…"
                      : isEnglish
                        ? "The consultant is analysing your response…"
                        : "Analityk analizuje odpowiedź…"}
                  </span>
                </div>
              )}
              <div ref={endRef} />
            </div>
            <form onSubmit={send} className="border-t border-[#252a33] p-4 sm:p-5">
              {error && (
                <p
                  role="alert"
                  className="mb-3 rounded-lg border border-[#8f464e] bg-[#2c151a] px-3 py-2 text-sm text-[#ffc4c8]"
                >
                  {error}
                </p>
              )}
              {session?.showContactForm && (
                <div className="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#3b4352] bg-[#101319] px-4 py-3">
                  <p className="text-sm text-[#c4cad4]">{labels.offTopic}</p>
                  <a
                    href="/#contact"
                    className="rounded-lg border border-[#7283f5] px-3 py-2 text-sm font-medium text-[#e3e7ff] hover:bg-[#1c2350]"
                  >
                    {labels.contactForm}
                  </a>
                </div>
              )}
              {session?.status === "CLOSED" ? (
                <div className="rounded-xl border border-[#3b4352] bg-[#101319] p-4">
                  <p className="text-sm text-[#d4dae5]">{labels.closed}</p>
                </div>
              ) : session?.readyForSummary && !session.summary ? (
                <div className="rounded-xl border border-[#424d75] bg-[#11152a] p-4">
                  <p className="text-sm text-[#e3e6f8]">{labels.ready}</p>
                  <div className="mt-3 rounded-lg border border-[#343e68] bg-[#0d1121] px-3 py-2.5 text-sm text-[#d7ddf6]">
                    <span className="block text-xs uppercase tracking-[.1em] text-[#9daaff]">
                      {isEnglish ? "Additional questions" : "Dodatkowe pytania"}
                    </span>
                    <strong className="mt-1 block font-medium text-white">
                      {isEnglish
                        ? "The consultant will ask the remaining project-specific questions one by one."
                        : "Analityk przejdzie kolejno przez dodatkowe pytania potrzebne do doprecyzowania projektu."}
                    </strong>
                  </div>
                  <div className="mt-3 flex flex-wrap gap-2">
                    <button
                      type="button"
                      disabled={sending || preparingBrief}
                      onClick={() => void addMore(true)}
                      className="rounded-lg border border-[#5d6bcc] px-3 py-2 text-sm text-[#dbe0ff] hover:bg-[#20275a] disabled:pointer-events-none disabled:opacity-40"
                    >
                      {isEnglish ? "Start additional questions" : "Przejdź do dodatkowych pytań"}
                    </button>
                    <button
                      type="button"
                      disabled={sending || preparingBrief}
                      onClick={() => void addMore()}
                      className="rounded-lg border border-[#4a5367] px-3 py-2 text-sm text-[#cbd2de] hover:bg-[#1c212c] disabled:pointer-events-none disabled:opacity-40"
                    >
                      {isEnglish ? "Add your own detail" : "Dodaj własną informację"}
                    </button>
                    <button
                      type="button"
                      disabled={sending || preparingBrief}
                      onClick={() => void generateSummary()}
                      className="rounded-lg bg-[#6477fa] px-3 py-2 text-sm font-medium text-white hover:bg-[#7788ff] disabled:pointer-events-none disabled:opacity-40"
                    >
                      {preparingBrief
                        ? isEnglish
                          ? "Analysing the brief…"
                          : "Analizujemy brief…"
                        : labels.sendBrief}
                    </button>
                  </div>
                </div>
              ) : (
                <div className="flex gap-3">
                  <textarea
                    ref={inputRef}
                    value={text}
                    onChange={(e) => setText(e.target.value)}
                    onKeyDown={(e) => {
                      if (e.key === "Enter" && !e.shiftKey && !e.nativeEvent.isComposing) {
                        e.preventDefault();
                        e.currentTarget.form?.requestSubmit();
                      }
                    }}
                    maxLength={4000}
                    disabled={
                      sending ||
                      !session?.id ||
                      session.readyForSummary ||
                      session.status === "COMPLETED" ||
                      session.status === "CLOSED"
                    }
                    rows={2}
                    placeholder={
                      isEnglish
                        ? "Describe the problem your company is facing…"
                        : "Napisz, z jakim problemem mierzy się Twoja firma…"
                    }
                    className="min-h-14 flex-1 resize-none rounded-xl border border-[#353d4b] bg-[#090b0e] px-4 py-3 text-sm outline-none placeholder:text-[#687080] focus:border-[#7c8dff] disabled:opacity-50"
                  />
                  <button
                    disabled={
                      sending ||
                      !text.trim() ||
                      !session?.id ||
                      session.readyForSummary ||
                      session.status === "COMPLETED" ||
                      session.status === "CLOSED"
                    }
                    className="rounded-xl bg-[#6477fa] px-4 font-medium text-white transition hover:bg-[#7788ff] disabled:cursor-not-allowed disabled:opacity-40"
                  >
                    {isEnglish ? "Send" : "Wyślij"} <span aria-hidden="true">→</span>
                  </button>
                </div>
              )}
              <p className="mt-3 text-xs text-[#737b89]">{labels.safety}</p>
            </form>
          </section>
          )}
          <aside className="space-y-4">
            <div className="rounded-2xl border border-[#2c3340] bg-[#0c0e12] p-5">
              <p className="font-mono text-[11px] uppercase tracking-[.14em] text-[#8791ac]">
                {labels.stage}
              </p>
              <div className="mt-2 flex items-center justify-between gap-3">
                <p className="text-sm text-white">
                  {session?.status === "COMPLETED"
                    ? labels.delivered
                    : session?.status === "CLOSED"
                      ? labels.ended
                      : session?.status === "READY_FOR_SUMMARY"
                        ? labels.readyToSend
                        : (session?.progress ?? 0) === 0
                          ? labels.start
                          : labels.collecting}
                </p>
                <span className="font-mono text-xs text-[#aeb9ff]">{session?.progress ?? 0}%</span>
              </div>
              <div className="mt-4 h-1.5 overflow-hidden rounded-full bg-[#202530]">
                <div
                  className="h-full rounded-full bg-[#7484ff] transition-all duration-500"
                  style={{ width: `${session?.progress ?? 0}%` }}
                />
              </div>
              <p className="mt-2 text-xs text-[#7d8795]">{labels.fields}</p>
            </div>
            <figure className="relative overflow-hidden rounded-2xl border border-[#2c3340] bg-[#0c0e12]">
              <img
                src={discoveryConsultant}
                alt={
                  isEnglish
                    ? "Project consultant listening during a business meeting"
                    : "Konsultant podczas rozmowy o projekcie"
                }
                loading="lazy"
                decoding="async"
                className="h-52 w-full object-cover object-center"
              />
              <figcaption className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-[#090b10] via-[#090b10]/85 to-transparent px-4 pb-4 pt-10 text-sm text-[#e3e6ef]">
                {isEnglish
                  ? "We start by understanding what you need."
                  : "Zaczynamy od zrozumienia Twojej potrzeby."}
              </figcaption>
            </figure>
            {(session?.status === "COMPLETED" || session?.status === "CLOSED") && (
              <button
                onClick={() => void createSession()}
                className="w-full rounded-xl border border-[#353d4b] px-4 py-3 text-sm text-[#c5cad4] hover:border-[#6879e8]"
              >
                {labels.newConversation}
              </button>
            )}
          </aside>
        </div>
      </div>
    </main>
  );
}

function Bubble({ message }: { message: Message }) {
  const blocks = message.content.replace(/\?{2,}/g, "?").split(/\n\s*\n/);
  return (
    <div className={`flex ${message.role === "user" ? "justify-end" : "justify-start"}`}>
      <div
        className={`max-w-[86%] rounded-2xl px-4 py-3 text-sm leading-6 ${message.role === "user" ? "rounded-br-sm bg-[#6072e8] text-white" : "rounded-bl-sm border border-[#292f3a] bg-[#14171d] text-[#e4e7ec]"}`}
      >
        {blocks.map((block, index) => (
          <p key={index} className={index ? "mt-3" : ""}>
            {block.split("\n").map((line, lineIndex) => (
              <MessageLine
                key={lineIndex}
                line={line}
                highlightQuestion={message.role === "assistant"}
              />
            ))}
          </p>
        ))}
      </div>
    </div>
  );
}

function MessageLine({ line, highlightQuestion }: { line: string; highlightQuestion: boolean }) {
  const sentences = line.split(/(?<=[.?!])\s+/);
  return (
    <span className="block">
      {sentences.map((sentence, index) => (
        <span
          key={index}
          className={
            highlightQuestion && sentence.trim().endsWith("?") ? "font-semibold text-white" : ""
          }
        >
          {index ? " " : ""}
          {sentence}
        </span>
      ))}
    </span>
  );
}
function Typing() {
  return (
    <div className="flex items-center gap-2 text-sm text-[#a5acb8]">
      <span className="flex gap-1 rounded-full border border-[#2b313c] bg-[#14171d] px-3 py-2">
        <i className="h-1.5 w-1.5 animate-bounce rounded-full bg-[#8493ff]" />
        <i className="h-1.5 w-1.5 animate-bounce rounded-full bg-[#8493ff] [animation-delay:150ms]" />
        <i className="h-1.5 w-1.5 animate-bounce rounded-full bg-[#8493ff] [animation-delay:300ms]" />
      </span>{" "}
      Analityk analizuje odpowiedź…
    </div>
  );
}
function SummaryView({ summary }: { summary: Summary }) {
  return (
    <div className="rounded-2xl border border-[#5060bd] bg-[#11152a] p-5">
      <p className="font-mono text-[11px] uppercase tracking-[.14em] text-[#aeb9ff]">
        Brief przekazany
      </p>
      <h2 className="mt-2 font-semibold">Dziękujemy — zespół otrzymał ustalenia</h2>
      <p className="mt-2 text-sm text-[#cbd0dc]">
        Wrócimy z propozycją dalszych kroków po weryfikacji zakresu.
      </p>
      <div className="mt-5 space-y-4 border-t border-[#354071] pt-4 text-sm text-[#cbd0dc]">
        {summary.sections?.map((section) => (
          <div key={section.title}>
            <h3 className="text-xs font-semibold uppercase tracking-[.1em] text-white">
              {section.title}
            </h3>
            {section.content.map((line, index) => (
              <p key={index} className="mt-1">
                {line}
              </p>
            ))}
          </div>
        ))}
      </div>
    </div>
  );
}
