import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from "react";

export type Language = "pl" | "en";

type LanguageOption = { code: Language; label: string };

export const languages: LanguageOption[] = [
  { code: "pl", label: "PL" },
  { code: "en", label: "EN" },
];

const english: Record<string, string> = {
  "Dostępny dla nowych projektów": "Available for new projects",
  "Twój partner techniczny": "Your technical partner",
  "Niemożliwe nie istnieje,": "Nothing is impossible,",
  "ogranicza nas tylko kreatywność.": "only creativity limits us.",
  "Na górę": "Back to top",
  "© 2026 Dawid Grzywniak — Wszelkie prawa zastrzeżone.":
    "© 2026 Dawid Grzywniak — All rights reserved.",
  "Tworzę rozwiązania IT": "I build digital solutions",
  "od pomysłu": "from idea",
  "do produkcji.": "to launch.",
  "Pomagam firmom projektować, budować i utrzymywać serwisy, aplikacje, integracje oraz automatyzacje. Ty opisujesz cel — ja prowadzę techniczną całość.":
    "I help businesses design, build and maintain websites, applications, integrations and automation. You describe the goal — I lead the technical work end to end.",
  "Ty znasz swój biznes. Ja zajmę się technologią, wdrożeniem i utrzymaniem.":
    "You know your business. I take care of the technology, launch and maintenance.",
  "Porozmawiajmy →": "Let's talk →",
  "Porozmawiajmy o projekcie →": "Let's discuss your project →",
  "Porozmawiajmy o Twoim projekcie →": "Let's discuss your project →",
  "Zobacz, w czym mogę pomóc": "See how I can help",
  "Zobacz ofertę": "See the offer",
  "10+ lat doświadczenia": "10+ years of experience",
  "15+ wdrożeń produkcyjnych": "15+ production launches",
  "Od developmentu po utrzymanie": "From development to maintenance",
  "Strony · Aplikacje · Automatyzacje": "Websites · Applications · Automation",
  "© 2026 Dawid Grzywniak": "© 2026 Dawid Grzywniak",
  ROZMOWA: "CONVERSATION",
  ANALIZA: "ANALYSIS",
  ZAKRES: "SCOPE",
  REALIZACJA: "DELIVERY",
  WDROŻENIE: "LAUNCH",
  ROZWÓJ: "GROWTH",
  "Opisujesz problem lub cel.": "You describe the problem or goal.",
  "Sprawdzam proces i ograniczenia.": "I review the process and constraints.",
  "Ustalamy kierunek i kolejne kroki.": "We agree the direction and next steps.",
  "Buduję etapami, z widocznym postępem.": "I build in stages, with visible progress.",
  "Testy, uruchomienie i monitoring.": "Testing, launch and monitoring.",
  "Utrzymanie i dalsze usprawnienia.": "Maintenance and further improvements.",
  "Doświadczenie i zakres pracy": "Experience and scope of work",
  "10+ lat": "10+ years",
  doświadczenia: "of experience",
  "Full-stack": "Full-stack",
  "frontend · backend · infrastruktura": "frontend · backend · infrastructure",
  Production: "Production",
  "wdrożenie · monitoring · rozwój": "launch · monitoring · growth",
  Development: "Development",
  Integracje: "Integrations",
  Infrastruktura: "Infrastructure",
  "Strony i aplikacje": "Websites and applications",
  "Systemy na miarę": "Tailored systems",
  "Jeden kontakt": "One point of contact",
  "Od pomysłu do wdrożenia": "From idea to launch",
  "Architektura rozwiązania": "Solution architecture",
  "Interaktywna mapa": "Interactive map",
  "Najedź na element, aby zobaczyć jego rolę i powiązania.":
    "Hover over an element to see its role and connections.",
  POMYSŁ: "IDEA",
  "Punkt wyjścia: cel, problem i plan działania.":
    "The starting point: the goal, the problem and a plan of action.",
  STRONA: "WEBSITE",
  "Czytelna obecność firmy i pierwszy punkt kontaktu z klientem.":
    "A clear company presence and the first point of contact for customers.",
  APLIKACJA: "APPLICATION",
  "Narzędzie, które porządkuje codzienną pracę i obsługę klientów.":
    "A tool that organises daily work and customer service.",
  INTEGRACJE: "INTEGRATIONS",
  "Połączenia z usługami, z których firma już korzysta.":
    "Connections to services your business already uses.",
  AUTOMATYZACJA: "AUTOMATION",
  "Powtarzalne zadania wykonują się same, bez ręcznego przepisywania.":
    "Repetitive tasks happen automatically, without manual copying.",
  DANE: "DATA",
  "Wspólne, uporządkowane dane dostępne tam, gdzie są potrzebne.":
    "Shared, organised data available where it is needed.",
  WSPARCIE: "SUPPORT",
  "Pomoc po uruchomieniu i spokojny rozwój rozwiązania.":
    "Support after launch and steady development of your solution.",
  "Całościowe podejście": "End-to-end approach",
  "Dlaczego warto": "Why work with me",
  "Jeden kontakt.": "One point of contact.",
  "Pełna odpowiedzialność.": "Clear ownership.",
  "Jedna osoba prowadzi temat od pierwszej rozmowy po działające rozwiązanie — bez przekazywania go między kolejnymi wykonawcami.":
    "One person leads the work from the first conversation to a working solution — without handing it between different contractors.",
  "Wspólne planowanie rozwiązania": "Planning a solution together",
  "Od pierwszej rozmowy": "From the first conversation",
  "Przejmuję odpowiedzialność za cały projekt — od pierwszej rozmowy przez projekt, kod i bazę danych, aż po serwer i wdrożenie.":
    "I take responsibility for the entire project — from the first conversation through design, code and data to the server and launch.",
  "Nie musisz koordynować kilku wykonawców. Rozmawiasz z jedną osobą, która rozumie zarówno problem, jak i techniczne rozwiązanie.":
    "You do not need to coordinate several contractors. You speak to one person who understands both the problem and the technical solution.",
  Dopasowanie: "A tailored approach",
  "Nie wciskam gotowego rozwiązania. Najpierw poznaję problem, potem proponuję to, co rzeczywiście ma sens.":
    "I do not force a ready-made solution. First I understand the problem, then I propose what truly makes sense.",
  "Czy to brzmi znajomo?": "Does this sound familiar?",
  "Masz problem.": "You have a problem.",
  "Znajdziemy rozwiązanie.": "We'll find a solution.",
  "Ręczne procesy w firmie": "Manual processes in a business",
  "Od problemu zaczynamy": "We start with the problem",
  "Twoja firma traci czas na ręczne zadania?": "Is your company losing time to manual tasks?",
  "Odzyskaj czas →": "Get your time back →",
  "Potrzebujesz systemu, który uporządkuje pracę?": "Do you need a system to organise your work?",
  "Uporządkuj pracę →": "Organise your work →",
  "Masz pomysł na aplikację, ale nie wiesz od czego zacząć?":
    "Do you have an app idea but do not know where to start?",
  "Rozwiń pomysł →": "Develop your idea →",
  "Twoja obecna strona nie spełnia swojej roli?": "Is your current website not doing its job?",
  "Popraw stronę →": "Improve your website →",
  "Kilka systemów nie potrafi ze sobą współpracować?":
    "Are several systems unable to work together?",
  "Połącz narzędzia →": "Connect your tools →",
  "Potrzebujesz rozwiązania stworzonego dokładnie pod Twój biznes?":
    "Do you need a solution built specifically for your business?",
  "Poznaj możliwości →": "Explore the options →",
  "W czym mogę pomóc": "How I can help",
  "Jeden developer.": "One developer.",
  "Cały system.": "The whole system.",
  "Strony internetowe": "Websites",
  "Strony firmowe, sklepy i landing pages, które jasno pokazują ofertę i zachęcają do kontaktu.":
    "Company websites, online stores and landing pages that clearly present your offer and encourage contact.",
  "Efekt: większa widoczność i więcej zapytań": "Result: better visibility and more enquiries",
  "Aplikacje dla firmy": "Business applications",
  "Panele klienta, rezerwacje i narzędzia do codziennej pracy — dokładnie dopasowane do Twojego sposobu działania.":
    "Client portals, booking tools and everyday work tools — tailored to how your business operates.",
  "Efekt: sprawniejsza obsługa klientów i zespołu": "Result: smoother customer and team service",
  "Systemy i integracje": "Systems and integrations",
  "Łączę narzędzia, których już używasz, aby informacje przepływały bez ręcznego przepisywania.":
    "I connect the tools you already use, so information flows without manual copying.",
  "Efekt: mniej błędów i pełniejszy obraz firmy":
    "Result: fewer errors and a clearer view of your business",
  "Optymalizacja procesów": "Process optimisation",
  "Przyglądam się, jak dziś pracuje firma, porządkuję informacje i wskazuję, co można uprościć.":
    "I look at how your company works today, organise information and identify what can be simplified.",
  "Efekt: mniej chaosu, szybsza praca i lepsze decyzje":
    "Result: less chaos, faster work and better decisions",
  "Serwery i konfiguracja": "Servers and configuration",
  "Konfiguruję środowisko, zabezpieczenia i monitoring, aby rozwiązanie działało stabilnie dziś i było gotowe na rozwój.":
    "I configure the environment, security and monitoring so your solution runs reliably today and is ready to grow.",
  "Efekt: spokój i przewidywalne działanie": "Result: peace of mind and predictable operation",
  Automatyzacje: "Automation",
  "Eliminuję powtarzalne zadania i przekazywanie danych między narzędziami.":
    "I eliminate repetitive tasks and manual data handovers between tools.",
  "Efekt: więcej czasu na pracę, która ma znaczenie": "Result: more time for work that matters",
  "Nie widzisz tutaj dokładnie tego, czego szukasz?":
    "Do not see exactly what you are looking for?",
  "Możliwe, że i tak mogę pomóc. Nie zaczynam od katalogu usług — zaczynam od zrozumienia Twojej sytuacji.":
    "I may still be able to help. I do not start with a service catalogue — I start by understanding your situation.",
  "Opowiedz o problemie →": "Tell me about the problem →",
  "Od problemu do rozwiązania": "From problem to solution",
  "Dobre rozwiązanie": "A good solution",
  "widać w codziennej pracy.": "shows in everyday work.",
  "Najpierw porządkujemy proces. Potem dbam, żeby całość działała stabilnie w tle.":
    "First we organise the process. Then I make sure the whole solution runs reliably in the background.",
  "01 / Najpierw zrozumienie": "01 / Understanding first",
  "Mniej zgadywania.": "Less guesswork.",
  "Więcej jasności.": "More clarity.",
  "Wspólnie układamy problem, cele i sensowne kolejne kroki.":
    "Together we define the problem, goals and sensible next steps.",
  "02 / Stabilne zaplecze": "02 / Reliable foundation",
  "Technologia, która": "Technology that",
  "po prostu działa.": "simply works.",
  "Konfiguracja, bezpieczeństwo i monitoring dopasowane do skali Twojego biznesu.":
    "Configuration, security and monitoring matched to the scale of your business.",
  "Planowanie procesu pracy przy biurku": "Planning a work process at a desk",
  "Skonfigurowana infrastruktura serwerowa": "Configured server infrastructure",
  "Pierwszy kontakt": "First contact",
  "Napisz, co chcesz": "Tell me what you want",
  "osiągnąć.": "to achieve.",
  "Kilka zdań o sytuacji i celu wystarczy. Nie musisz przygotowywać briefu ani znać technologii.":
    "A few sentences about your situation and goal are enough. You do not need a brief or technical knowledge.",
  "Odpowiem konkretnie: co warto zrobić dalej i czy mogę realnie pomóc.":
    "I will respond clearly: what is worth doing next and whether I can genuinely help.",
  "Napisz wiadomość →": "Write a message →",
  "Bez formalności": "No formalities",
  "Krótka wiadomość wystarczy, aby zacząć. Resztę wspólnie uporządkujemy.":
    "A short message is enough to start. We will organise the rest together.",
  "Pierwsza rozmowa o projekcie": "First conversation about a project",
  "Jak działam": "How I work",
  Przejrzysty: "A clear",
  "proces.": "process.",
  "Poznaję Twój cel, kontekst i to, co dziś nie działa. Bez technicznego żargonu.":
    "I learn your goal, context and what is not working today. No technical jargon.",
  PLAN: "PLAN",
  "Przedstawiam prosty plan, zakres prac i kolejne kroki, zanim podejmiemy decyzję.":
    "I present a clear plan, scope and next steps before we make a decision.",
  "Tworzę rozwiązanie etapami. Masz regularny kontakt i widzisz postępy.":
    "I build the solution in stages. You stay in touch and see the progress.",
  SPRAWDZENIE: "REVIEW",
  "Wspólnie upewniamy się, że wszystko działa tak, jak potrzebujesz.":
    "Together we make sure everything works as you need it to.",
  "Uruchamiam gotowe rozwiązanie i dbam o bezpieczne przejście do codziennej pracy.":
    "I launch the finished solution and ensure a safe transition to everyday work.",
  "Po wdrożeniu nadal możesz liczyć na pomoc, rozwój i spokojną rozmowę.":
    "After launch, you can still count on support, further development and a direct conversation.",
  "O mnie": "About me",
  "Rozmawiasz bezpośrednio": "You speak directly",
  "z osobą, która zajmie się Twoim projektem.": "to the person who will handle your project.",
  "Od planu po działające rozwiązanie.": "From plan to a working solution.",
  "Konsultant IT pracujący nad rozwiązaniem": "IT consultant working on a solution",
  "Jestem Dawid — developer i twórca systemów z szerokim zakresem kompetencji. Buduję rozwiązania IT od A do Z: od pierwszej rozmowy o problemie, przez projekt i kod, aż po serwer i wdrożenie produkcyjne.":
    "I'm Dawid — a developer and systems builder with broad expertise. I build digital solutions end to end: from the first conversation about a problem, through design and code, to servers and production launch.",
  "Nie musisz koordynować kilku osób ani powtarzać tej samej historii. Masz jeden kontakt i jasną odpowiedzialność za cały projekt.":
    "You do not need to coordinate several people or repeat the same story. You have one contact and clear ownership of the whole project.",
  "Dobieram technologię do problemu, nie odwrotnie. Dzięki temu możesz skupić się na swoim biznesie, a nie na technicznych decyzjach.":
    "I choose technology for the problem, not the other way around. This lets you focus on your business rather than technical decisions.",
  "Zacznijmy rozmowę": "Let's start a conversation",
  "Masz pomysł.": "You have an idea.",
  Zbudujmy: "Let's build",
  "rozwiązanie.": "a solution.",
  "Napisz, jaki rezultat chcesz osiągnąć i co dziś utrudnia pracę. Im więcej kontekstu podasz, tym trafniej przygotuję odpowiedź.":
    "Tell me what result you want to achieve and what makes work difficult today. The more context you share, the more useful my response will be.",
  "Najpierw sprawdzę temat i wrócę z konkretną odpowiedzią.":
    "I will first review the topic and come back with a concrete answer.",
  "Telefon: +48 664 870 311": "Phone: +48 664 870 311",
  "Odpowiadam osobiście": "I reply personally",
  "Spokojne miejsce pracy": "A calm place to work",
  "Interaktywna wizualizacja infrastruktury": "Interactive infrastructure visualisation",
  "Wiadomość wysłana.": "Message sent.",
  "Wiadomość dotarła": "Message received",
  "Dziękuję za kontakt.": "Thank you for getting in touch.",
  "Odpowiem w ciągu 1–2 dni roboczych.": "I will reply within 1–2 business days.",
  "Możesz spokojnie zamknąć tę stronę — wiadomość jest już u mnie.":
    "You can safely close this page — your message is already with me.",
  "Wyślij kolejną wiadomość": "Send another message",
  "Nie udało się wysłać wiadomości. Spróbuj ponownie lub napisz bezpośrednio na dawid@grzywniak.pl.":
    "The message could not be sent. Please try again or email dawid@grzywniak.pl directly.",
  "Imię / Firma": "Name / company",
  "Jak mam się do Ciebie zwracać?": "How should I address you?",
  Email: "Email",
  "Telefon (opcjonalnie)": "Phone (optional)",
  "Np. +48 600 000 000": "e.g. +48 600 000 000",
  "Orientacyjny budżet (opcjonalnie)": "Estimated budget (optional)",
  "Wybierz, jeśli chcesz podać": "Select if you wish to share it",
  "Wybierz przedział budżetowy": "Choose a budget range",
  "Pomoże mi lepiej przygotować pierwszą odpowiedź.":
    "It helps me prepare a more useful first response.",
  "Do 1 tys. zł": "Up to PLN 1k",
  "1–2 tys. zł": "PLN 1k–2k",
  "2–5 tys. zł": "PLN 2k–5k",
  "5–10 tys. zł": "PLN 5k–10k",
  "10–20 tys. zł": "PLN 10k–20k",
  "20–50 tys. zł": "PLN 20k–50k",
  "50–100 tys. zł": "PLN 50k–100k",
  "100 tys. zł+": "PLN 100k+",
  "Jeszcze nie wiem": "Not sure yet",
  "Na jaki adres mam odpisać?": "Which email address should I reply to?",
  "Co chcesz osiągnąć?": "What do you want to achieve?",
  "Co dziś nie działa lub co chcesz usprawnić?":
    "What is not working today, or what would you like to improve?",
  "Nie musisz znać technologii ani mieć gotowego rozwiązania. Wystarczy, że opiszesz problem lub cel biznesowy.":
    "You do not need to know the technology or have a finished solution. Describing the problem or business goal is enough.",
  "Serwis działa wolno albo niestabilnie?": "Is your website slow or unreliable?",
  "Uporządkujmy to →": "Let's sort it out →",
  "Systemy nie wymieniają między sobą danych?": "Do your systems fail to exchange data?",
  "Zespół ręcznie wykonuje zadania, które można zautomatyzować?":
    "Does your team do tasks manually that could be automated?",
  "Potrzebujesz aplikacji, ale nie wiesz, jak ją zaprojektować?":
    "Do you need an application but are not sure how to design it?",
  "Zaplanujmy ją →": "Let's plan it →",
  "Istniejący system trzeba rozwinąć albo uporządkować?":
    "Does an existing system need development or organising?",
  "Sprawdźmy zakres →": "Let's define the scope →",
  "Projekt został niedokończony i potrzebuje odpowiedzialnego przejęcia?":
    "Was a project left unfinished and needs responsible takeover?",
  "Serwisy i platformy webowe": "Websites and web platforms",
  "Serwisy firmowe i platformy, które porządkują ofertę, obsługę klienta oraz proces pozyskiwania zapytań.":
    "Business websites and platforms that organise your offer, customer service and enquiry flow.",
  "Efekt: czytelny produkt i lepszy pierwszy kontakt":
    "Result: a clearer product and a better first contact",
  "Optymalizacja serwisów": "Website optimisation",
  "Porządkuję wolne, niestabilne lub trudne w rozwoju serwisy oraz ich krytyczne procesy.":
    "I improve slow, unreliable or difficult-to-develop websites and their critical processes.",
  "Efekt: stabilniejsza praca i mniej blokad": "Result: more stable operations and fewer blockers",
  ROZMAWIAMY: "WE TALK",
  ANALIZUJĘ: "I ANALYSE",
  "PROPONUJĘ ROZWIĄZANIE": "I PROPOSE A SOLUTION",
  BUDUJĘ: "I BUILD",
  WDRAŻAM: "I LAUNCH",
  ROZWIJAMY: "WE DEVELOP",
  "Wysyłanie…": "Sending…",
  "Wyślij wiadomość →": "Send message →",
  "Wiadomość zostanie wysłana bezpośrednio z formularza. Odpiszę w ciągu 1–2 dni.":
    "Your message will be sent directly from this form. I will reply within 1–2 days.",
  "Strony, aplikacje i automatyzacje": "Websites, applications and automation",
  "Otwórz menu": "Open menu",
  "Zamknij menu": "Close menu",
  "Wybór języka": "Language selector",
  "Jak pomagam": "How I help",
  Kontakt: "Contact",
};

const LanguageContext = createContext<{
  language: Language;
  setLanguage: (language: Language) => void;
} | null>(null);

let activeLanguage: Language = "pl";

function preventPolishOrphans(value: string): string {
  return value.replace(/(^|\s)([AaIiOoUuWwZz])\s+/g, "$1$2\u00a0");
}

export function t(value: string): string {
  const translated = activeLanguage === "en" ? (english[value] ?? value) : value;
  return activeLanguage === "pl" ? preventPolishOrphans(translated) : translated;
}

export function I18nProvider({ children }: { children: ReactNode }) {
  const [language, setLanguage] = useState<Language>(() => {
    const saved = window.localStorage.getItem("site-language");
    return saved === "en" ? "en" : "pl";
  });

  activeLanguage = language;

  useEffect(() => {
    window.localStorage.setItem("site-language", language);
    document.documentElement.lang = language;
  }, [language]);

  const value = useMemo(() => ({ language, setLanguage }), [language]);
  return <LanguageContext.Provider value={value}>{children}</LanguageContext.Provider>;
}

export function useLanguage() {
  const context = useContext(LanguageContext);
  if (!context) throw new Error("useLanguage must be used inside I18nProvider");
  return context;
}
