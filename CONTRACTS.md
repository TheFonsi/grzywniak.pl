# Umowy aplikacji i stron — ustalenia PL / UE

Przegląd źródeł i biblioteki: 03.10.2026. Panel zawiera własne wzory z konkretnymi klauzulami, przeznaczone do sprawdzenia i zatwierdzenia dla danej transakcji. Nie jest to opinia prawna. Przed użyciem w działalności należy zatwierdzić bibliotekę z prawnikiem, w szczególności warunki praw autorskich i załączniki konsumenckie.

## Biblioteka i wygląd dokumentów

- **Strona internetowa:** wizytówka, one page, landing page i serwis firmowy.
- **Aplikacja webowa:** scenariusze użytkowników, role, dane i integracje.
- **Sklep internetowy:** ścieżka zamówienia, płatności i obowiązki operatorów.

Dobór odbywa się na podstawie zakresu zaakceptowanej oferty. Sprzedaż internetowa ma pierwszeństwo przed funkcjami aplikacji, a pozostałe projekty otrzymują wzór strony. Administrator widzi rekomendację i jej przyczynę. Technologia projektu nie jest narzucona przez wzór.

W panelu briefów → **Ustawienia wzorów umów** dostępne są trzy osobno wersjonowane wzory oraz wcześniejszy wzór wspólny. Każdy ma przycisk pobrania przykładowego PDF z fikcyjnymi danymi. W formularzu umowy **Wczytaj wybrany wzór** zastępuje siedem klauzul (odbiór, publikację, prawa, licencje zewnętrzne, wsparcie, zmiany i postanowienia końcowe), wymaga ponownej akceptacji i zachowuje dane stron, cenę, zakres oraz terminy. Samo wybranie pozycji nie zmienia dokumentu.

Nowe umowy zapisują typ, nazwę, rewizję biblioteki, wersję ustawień i kopię klauzul źródłowych. Zmiana biblioteki nie zmienia zapisanych umów ani archiwalnych PDF-ów. Wcześniejsze dokumenty bez identyfikatora wzoru zachowują dawną treść; przejście na nowy wzór jest jawne.

PDF ma format A4, proporcjonalną czcionkę, polskie znaki, wyszukiwalny tekst, numerowane paragrafy, nagłówek z numerem i wersją, stopki z numeracją stron oraz podpisy. Dane domeny, licencji i klienta są umieszczane w odpowiednich częściach dokumentu. Nie jest wymagany Word, zewnętrzny konwerter ani dodatkowa biblioteka PHP. Edycja odbywa się w panelu; eksport DOCX nie jest częścią tej zmiany.

Źródła struktury: [publiczny wzór INTiBS PAN](https://bip.intibs.pl/uploads/files/migration/konkursy/2010/web_new_loks/wzor_umowy_o_wykonanie_serwisu_WWW_NEW_LOKS.pdf) i [wzór meetcosta](https://meetcosta.com/umowa). Ich klauzule nie zostały skopiowane. Własne postanowienia obejmują m.in. odpowiedzialność za wykonanie i narzędzia AI, materiały klienta, odbiór wersji, poufność, prawa, koszty dodatkowe i rozliczenie zakończenia współpracy. Nie narzucają kar ani limitu odpowiedzialności; nie wyłączają szkód umyślnych ([Kodeks cywilny, art. 471–474](https://api.sejm.gov.pl/eli/acts/DU/2025/1071/text.pdf)).

## Prawa do projektu

„Sprzedaż aplikacji” może oznaczać wykonanie projektu, przeniesienie autorskich praw majątkowych albo licencję. Umowa powinna identyfikować utwory, pola eksploatacji, wynagrodzenie i moment przejścia praw. Samo wydanie plików nie przenosi praw. Nie sprzedaje się praw osobistych. Dla kodu należy uwzględnić przepisy szczególne; grafika i teksty wymagają właściwych postanowień o korzystaniu i opracowaniach. Trzeba sprawdzić prawa od pracowników i podwykonawców, a także licencje materiałów zewnętrznych. Podstawa: art. 16, 41, 46, 50, 52–53, 74–77 [ustawy o prawie autorskim](https://api.sejm.gov.pl/eli/acts/DU/2025/24/text.pdf) i [dyrektywa 2009/24/WE](https://eur-lex.europa.eu/legal-content/pl/TXT/?uri=CELEX%3A32009L0024).

Przeniesienie praw wymaga formy pisemnej pod rygorem nieważności. Zwykły e-mail, skan podpisu ani przycisk akceptacji nie zastępują tej formy. Elektronicznie należy zastosować kwalifikowane podpisy stron, równoważne podpisom własnoręcznym — [eIDAS, art. 25 ust. 2](https://eur-lex.europa.eu/legal-content/PL/TXT/PDF/?uri=CELEX%3A02014R0910-20240520). Panel nie weryfikuje podpisów; status wysyłki nie oznacza zawarcia umowy.

## Proponowany układ dokumentu

1. Strony i reprezentacja, przedmiot oraz załącznik opisujący zakres.
2. Etapy, terminy, zależności od materiałów klienta, kryteria odbioru i poprawki.
3. Cena, VAT właściwy dla transakcji, płatności i wynagrodzenie za prawa.
4. Prawa IP lub licencja, przekazanie repozytorium, dokumentacji i dostępów.
5. Wykaz bibliotek, SDK, zdjęć, fontów, SaaS i komponentów pozostających własnością wykonawcy lub osób trzecich.
6. Usuwanie wad; oddzielnie płatne wsparcie, godziny obsługi, reakcja, naprawa i zasady wypowiedzenia.
7. Dodatkowe zamówienia: zakres, akceptacja ceny lub stawki z limitem oraz wpływ na termin. Osobno koszty hostingu, API, domen i sklepów.
8. Odpowiedzialność, poufność, rozwiązanie i rozliczenia; załączniki zależne od projektu.

To rekomendowana struktura wdrożeniowa, nie ustawowy formularz. Dla aplikacji mobilnej warto ustalić właściciela kont sklepów, podpisywania wydań i odpowiedzialność za publikację. Dla strony: domenę, hosting, CMS i zasady aktualizacji.

## Konsumenci i dane osobowe

Nie należy automatycznie stosować wzoru B2B do konsumenta ani przedsiębiorcy korzystającego z odpowiedniej ochrony konsumenckiej. Obowiązki dotyczące zgodności treści/usług cyfrowych, aktualizacji, informacji i odstąpienia mogą obowiązywać niezależnie od płatnego wsparcia. Rozpoczęcie realizacji nie usuwa automatycznie prawa odstąpienia: [UOKiK — informacje przed zawarciem umowy](https://prawakonsumenta.uokik.gov.pl/prawo-do-informacji/sprzedaz-poza-lokalem-i-na-odleglosc/), [UOKiK — umowy szczególne](https://prawakonsumenta.uokik.gov.pl/prawo-odstapienia-od-umowy/umowy-szczegolne/), [UOKiK — zmiany konsumenckie](https://prawakonsumenta.uokik.gov.pl/zmiany-2023/).

Jeżeli wykonawca przetwarza dane w imieniu klienta, trzeba ustalić role i warunki powierzenia, w tym podwykonawców, bezpieczeństwo i zakończenie przetwarzania — [RODO, art. 28](https://eur-lex.europa.eu/legal-content/pl/TXT/?uri=CELEX%3A32016R0679). Sam transfer IP nie zastępuje tych ustaleń.

## Obsługa w panelu

Umowa znajduje się nad ofertą wybranej rozmowy. Edytor jest dostępny po akceptacji oferty. Kolejne zapisy archiwizują poprzedni dokument; edycja wymaga ponownego wygenerowania PDF. PDF jest załączany do wiadomości. `SENT` oznacza przekazanie wiadomości lokalnemu systemowi pocztowemu, nie potwierdzenie doręczenia; `MOCK_SENT` oznacza test bez wysyłki.

### Dane wykonawcy i uzupełnianie AI

W „Ustawieniach wzorów umów” sekcja „Moje dane do umów” zapisuje pełną nazwę, adres, NIP (jeśli dotyczy), reprezentację, kontakt, rachunek oraz zasady płatności. Dla JDG pełna firma powinna obejmować imię i nazwisko. Dane kontaktowe i identyfikujące są szczególnie istotne przy obowiązkach informacyjnych wobec konsumenta — [UOKiK](https://prawakonsumenta.uokik.gov.pl/pytania-i-odpowiedzi/prawo-do-informacji/). Wymagalność pól w panelu jest regułą kompletności projektu, a nie twierdzeniem, że brak każdego z nich unieważnia umowę. NIP, KRS, PESEL i rachunek bankowy nie są uniwersalnymi warunkami ważności wszystkich umów.

W formularzu ustala się status klienta, rodzaj praw, warunki ich przejścia/licencji, wynagrodzenie za IP, sposób podpisania, datę, dane klienta i rolę w przetwarzaniu danych. Także licencja wyłączna wymaga pisemnej formy pod rygorem nieważności (art. 67 ust. 5 ustawy o prawie autorskim).

„Uzupełnij dane projektu AI” opisuje zakres zgodnie z zaakceptowaną ofertą. Klauzule prawne pozostają z wybranego wzoru lub ręcznej edycji, również gdy nie są jeszcze zaakceptowane. Model nie generuje tych klauzul w odpowiedzi. Znane dane stron, rachunek, cenę, zaliczkę i termin system kopiuje z formularza. Brakujących faktów, statusu klienta, rodzaju praw, podpisu, daty i załączników nie ustala samodzielnie. Do modelu nie są przekazywane osobne pola danych stron i rachunku; dane osobowe mogą nadal znajdować się w swobodnym opisie zakresu. Responses API korzysta z `store=false`, a odpowiedź jest walidowana na serwerze.

Każde pole ma przyciski „Akceptuj” i „Zmień”. Akceptacja dotyczy projektu administratora, nie zgody klienta ani podpisania umowy. Zapis projektu utrwala treść i stan akceptacji. Edycja wartości cofa jej akceptację. Ponowne uruchomienie AI zachowuje zaakceptowane pola i otrzymuje je jako ustalenia do dopasowania reszty dokumentu. Domyślne warunki IP pozostają w ustawieniach; czas i zakres licencji są widoczne przy licencji.

PDF wymaga akceptacji odpowiednich pól i kompletnego pakietu opisanego poniżej. Dawne pola nazw załączników są opcjonalnymi dodatkowymi uzgodnieniami; nie zastępują tworzonych dokumentów. Kontrole nie zastępują oceny prawnej. AI nie przegląda aktualnego prawa podczas wypełniania formularza.

Nowy agent nie jest potrzebny. Integracja korzysta z `OPENAI_API_KEY` i `OPENAI_MODEL`; opcjonalnie `OPENAI_CONTRACT_MODEL` pozwala ustawić model tylko do umów. `CONTRACT_AI_MOCK=true` służy wyłącznie do testów. AI uzupełnia formularz bez zapisu i bez wysyłki; decyzję o zapisie, PDF i wysyłce podejmuje użytkownik. Zapisana umowa zachowuje własną treść i wersję profilu wykonawcy.

Walidacja: `php tests/contract-workflow.php`, `python tests/contract-http.py`, `npm run typecheck`, `npm run build`. Test HTTP używa odrębnej kopii API i bazy w `tmp/contract-http` oraz atrap wysyłki.

### Sprawdzenie wyglądu

`php tests/contract-samples.php` tworzy trzy fikcyjne przykłady w `output/pdf/` (katalog nie jest commitowany). Testy HTTP obejmują dobór i zastosowanie wzoru, ochronę pól, wersjonowanie osobnych wzorów, kopie zapisanych klauzul, wymagane akceptacje i załączniki. `node tests/contract-review-ui.mjs` sprawdza zachowanie formularza po akceptacji i zmianie wzoru.

## Pakiety umów — wersja zasad 2026-10-04.1

Implementacja: `api/contract-package.php`. System deterministycznie składa umowę i rzeczywiste załączniki w jeden PDF. Oddziela kompletność danych, ręcznie potwierdzoną weryfikację prawną, przyjęcie wiadomości przez serwer pocztowy, otrzymanie przez klienta i potwierdzenie podpisania. Żaden z wcześniejszych stanów nie zastępuje następnego.

### Dobór dokumentów

| Dokument | Warunek |
| --- | --- |
| Zakres, materiały, kryteria i protokół odbioru | Zawsze |
| Wykaz praw, komponentów, podwykonawców utrzymania i AI | Zawsze |
| Informacja o danych kontaktowych i umownych | Zawsze |
| Hosting, płatności, kopie, DNS/TLS i eksport | Publikacja na infrastrukturze agencji |
| Informacje dla chronionego klienta i formularz odstąpienia | Konsument lub chroniony przedsiębiorca |
| Powierzenie, środki bezpieczeństwa, instrukcje i dalsze powierzenie | Wykonawca jest podmiotem przetwarzającym |

Rodzaj świadczenia (dzieło, usługi, umowa mieszana) wymaga oceny zakresu. Status klienta wymaga uzasadnienia: NIP sam w sobie nie wyklucza ochrony konsumenckiej. AI nie uzupełnia tych ocen, wykazu praw, uzgodnień hostingu ani danych powierzenia. Dane stron i zaakceptowanej oferty nadal są kopiowane automatycznie; znane ustalenia nie są ponownie wymyślane.

Wykaz praw i podwykonawców wypełnia się przez przyciski dodawania pozycji. Wewnętrznie zapisuje się ustrukturyzowaną listę. Każdy składnik ma pochodzenie, autora/dostawcę, podstawę dysponowania prawami, licencję i warunki utrzymania przez inny zespół. Pusty wykaz praw blokuje PDF. W powierzeniu brak podwykonawców wymaga świadomego potwierdzenia, a nie pustego pola. W rzeczywistym projekcie trzeba uwzględnić wszystkich faktycznych dostawców mających dostęp do danych. Informacja administratora dotycząca danych umownych nie zastępuje informacji klienta dla użytkowników aplikacji.

Odbiór ma sprawdzalne kryteria i uzgodniony termin 1–90 dni. Milczenie nie stanowi automatycznego odbioru. Zmiany zakresu wymagają uzgodnienia ceny i terminu. Rozliczenie zakończenia jest dobierane do charakteru świadczenia; hosting ma odrębne zasady dla usług. Wykaz AI nie zapewnia nieistniejących wyłącznych praw do wyników modelu. Wymagana forma przeniesienia praw i licencji wyłącznej pozostaje własnoręczna albo kwalifikowana elektroniczna.

### Obieg i dowody

1. Uzupełnij fakty oraz zaakceptuj pola. Możesz zapisywać niekompletny szkic.
2. Wygeneruj PDF. Umowa, załączniki, kopia wzoru, wersja oferty i profil wykonawcy są zamrożone w tej wersji. Manifest wskazuje dokumenty i sumy SHA-256; zapisuje się także sumę rzeczywistych bajtów PDF.
3. Przekaż cały pakiet do rzeczywistej weryfikacji prawnej. Zapisz w panelu osobę oraz identyfikator/odniesienie do opinii i jej zakres. To ręczne potwierdzenie administratora, nie automatyczna certyfikacja ani sprawdzenie kwalifikacji prawnika przez aplikację. Nie zaznaczaj go bez przeprowadzonej weryfikacji.
4. Wyślij pakiet z manifestem. Podmiana treści, załącznika lub PDF unieważnia dopasowanie zatwierdzenia. Zmiana danych tworzy nową wersję bez dotychczasowych zatwierdzeń i potwierdzeń klienta. Stare PDF-y są dostępne w historii.
5. Zapisz rzeczywisty dowód otrzymania dokumentów. Przyjęcie przez serwer nie jest dowodem doręczenia. Otrzymanie dokumentów nie jest podpisem ani zgodą na wcześniejsze świadczenie.
6. Potwierdź rzeczywiście podpisany pakiet w etapie umowy na mapie. Powiązanie podpisu z wersją i identyfikatorem pakietu jest sprawdzane przed startem agentów.

Planowanie i zadania wykonawcze otrzymują zakres z umowy, mierzalne kryteria odbioru, zasady współpracy, wykaz praw oraz uzgodnione ograniczenia przetwarzania. Pełne dane stron, rachunek i informacje prywatności dotyczące samej umowy nie są dokładane do tego kontekstu. Zlecenia sprawdzają dopasowanie zatwierdzonego pakietu; agenci mają zgłaszać brak materiałów, kolizje licencji i potrzebę nowych usług do decyzji, a nie wymyślać zgody. Przekazanie projektu wymaga rzeczywistego wykazu użytych składników, który może wymagać uzupełnienia pierwotnie uzgodnionego wykazu. Kompletność tego wykazu i faktyczne prawa nadal wymagają sprawdzenia, nie samego zapewnienia modelu.

Próba wysyłki jest zapisywana w `contract_delivery_outbox` przed operacją pocztową. Podczas wywołania poczty nie trzyma się blokady zapisu SQLite ani sesji PHP. Powtórzenie zaakceptowanej lub niepewnej próby nie wysyła duplikatu. Wyraźne odrzucenie przez serwer pozwala ponowić. Przerwanie procesu w trakcie wysyłki pozostawia stan niepewny: po sprawdzeniu dziennika i kolejki po identyfikatorze próby administrator może zapisać wynik w panelu (po 10 minutach). Jeśli umowę edytowano podczas wysyłki, wynik trafia do historii wysłanej wersji, nie do nowego dokumentu.

### Start chronionego klienta

Dla umów na odległość i poza lokalem system nie domniemywa zgód i przyjmuje start po upływie terminu odstąpienia: zwykle 14 dni, a dla wskazanej niezapowiedzianej wizyty domowej / wycieczki 30 dni. Wyliczenie ostrożnie liczy od późniejszego zapisu potwierdzenia zawarcia i otrzymania dokumentów; nie udaje, że data wpisana przez administratora jest datą podpisania przez klienta. Koniec terminu przypadający w sobotę lub dzień ustawowo wolny jest przesuwany. Daty są liczone w `Europe/Warsaw`. Mapa pokazuje najwcześniejszy start; API blokuje zlecenie agentom przed tą datą lub bez dowodu otrzymania pakietu.

**Wcześniejszy start nie jest obsługiwany przez automatyczne checkboxy.** Wymaga odrębnego, zweryfikowanego prawnie dokumentu i faktycznych oświadczeń właściwych dla usług, treści cyfrowych lub świadczeń mieszanych. Ten przepływ nie został zastąpiony pozorną zgodą administratora. Formularz odstąpienia nie stwierdza utraty prawa, a gwarancje ustawowe nie są wyłączane płatnym wsparciem.

### Wdrożenie i utrzymanie biblioteki

Nie potrzeba nowych tokenów ani usług. Nowe pliki PHP i istniejące skrypty admina są publikowane przez dotychczasowy deploy folderu `api/`; tabela outbox powstaje automatycznie. Historyczne dokumenty pozostają zachowane; wysyłka starszego dokumentu bez pakietu wymaga jego ponownego przygotowania w nowym systemie. System nie zastępuje ani nie przepisuje podpisanych dokumentów. Wzory są projektami do weryfikacji, nie gwarancją ważności każdej umowy. Zmiana treści modułów lub wersji zasad wymaga przeglądu nowych pakietów; wersjonowanych, wysłanych PDF-ów nie regeneruje się w tle.

Źródła sprawdzone przy opracowaniu: [Kodeks cywilny — tekst jednolity 2026](https://api.sejm.gov.pl/eli/acts/DU/2026/795/text.pdf), [prawo autorskie](https://api.sejm.gov.pl/eli/acts/DU/2025/24/text.pdf), [ustawa o prawach konsumenta](https://eli.gov.pl/api/acts/DU/2024/1796/text.html), [UOKiK — zasady rozpoczęcia usług](https://prawakonsumenta.uokik.gov.pl/prawo-odstapienia-od-umowy/umowy-szczegolne/), [RODO](https://eur-lex.europa.eu/eli/reg/2016/679/oj?locale=pl). Szczególne regulacje, transfery danych, warunki dostawców i przyszłe zmiany prawa wymagają odrębnej oceny.

Testy: `php tests/contract-package.php`, `php tests/contract-workflow.php`, `python tests/contract-http.py`, `node tests/contract-review-ui.mjs`, `python tests/project-gates-http.py`, `php tests/project-map-review.php`. Testy HTTP korzystają wyłącznie z odrębnych baz i atrap poczty, nie wysyłają klientom wiadomości ani nie tworzą infrastruktury.
