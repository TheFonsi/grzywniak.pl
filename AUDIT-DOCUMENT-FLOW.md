# Audyt: brief → analiza → oferta → umowa

Data: 5 października 2026, Europe/Warsaw. Badany commit: `34fb8f6`.
Zakres: kompletność przepływu danych, regeneracja, ręczne ustalenia, wzory, PDF i archiwalne źródła. Raport dotyczy zachowania kodu; nie wydaje opinii o ważności umów ani prawnej poprawności konkretnych klauzul.

## Wynik

**Nie wszystkie istotne ustalenia mają dziś zagwarantowane przeniesienie do końcowej umowy.** Pierwszy formularz umowy kopiuje wiele danych prawidłowo, ale kolejne wersje, zmiana wzoru i brak pełnej kontroli zgodności mogą pozostawić starą treść albo usunąć uzgodnione warunki. Przypisanie numeru oferty i jej archiwalnego źródła nie jest dowodem zgodności treści umowy.

Potwierdzono 7 problemów. Nie modyfikowano danych produkcyjnych, nie wysyłano wiadomości klientom i nie korzystano z płatnego AI. Wykonano scenariusze na odrębnej bazie, z atrapami AI i poczty.

## Co działa

| Informacja | Obecny przepływ | Ograniczenie |
| --- | --- | --- |
| Zakres | Rekomendacja analizy → zakres oferty → pole zakresu nowej umowy → PDF i specyfikacja | Brak końcowej kontroli zgodności po zmianach / kolejnej ofercie |
| Cena, VAT, zaliczka | Wycena oferty → wartości początkowe nowej umowy | Swobodny tekst umowy może mieć inną cenę; generator tego nie blokuje |
| Termin i płatności | `deliverySchedule` i `paymentSchedule` → termin i zaliczka nowej umowy | Zapisana umowa zachowuje stare pola; kontrola zgodności ich nie obejmuje |
| Kryteria odbioru i liczba dni | Zaakceptowane `agreement` → `facts` → załącznik specyfikacji | Równość z ofertą jest sprawdzana, lecz same kryteria oferty mogą być stare |
| Materiały i współpraca | Brief / ręczna odpowiedź → oferta → fakty umowy → załącznik | Brak kompletnej ewidencji wszystkich ręcznie uzgodnionych wymagań |
| Publikacja, domena, hosting | Uzgodnione fakty oferty → umowa / załączniki → podpisany plan publikacji | Działa dla obsługiwanych ścieżek; nie zastępuje realnego potwierdzenia domeny i dostępu |
| Wsparcie, prawa, dodatkowe koszty, dane | Tekst oferty dopisywany do klauzul nowego formularza | Zmiana wzoru może go usunąć; brak końcowego porównania |
| Dane stron | Nazwa klienta z oferty; dane wykonawcy z profilu; pozostałe dane uzupełniane ręcznie | E-mail i telefon klienta nie są automatycznie włączane do pola strony umowy |
| Prawa do składników, powierzenie, status klienta | Oddzielne wymagane pola i odpowiednie załączniki | Wymagają rzeczywistych danych i przeglądu; nie są w całości zbierane w briefie |
| Historia | Zachowane wersje, identyfikatory źródeł, dokumenty tylko do odczytu | Historia dokumentuje pochodzenie, nie sprawdza merytorycznej zgodności |

Blokady działają m.in. dla braku wymaganych danych, oznaczonych braków, niezaakceptowanych pól, konfliktu wersji, przeglądu pakietu przed wysyłką i potwierdzenia umowy przed startem agentów.

## 1. Wysoki: stara treść może otrzymać numer nowej oferty

Miejsca: `api/contract-model.php:52`, `api/contract.php:158`, `api/contract.php:168`.

Zapisana umowa jest wczytywana w całości zamiast nowych wartości z oferty. Generator sprawdza część `facts`, a następnie ustawia aktualny `offerVersion`, nawet jeżeli pola `scope`, `price`, `deposit` i `deadline` pozostały stare.

**Potwierdzenie HTTP:** w odrębnej sprawie zaakceptowana oferta v2 miała nowy zakres i cenę 9000 netto / 11070 brutto. Generator przyjął zakres `OLD SCOPE`, cenę `1000 PLN NET OLD PRICE`, termin `OLD DEADLINE` i zwrócił HTTP 200 oraz umowę z `offerVersion=2`. Nie potwierdza to, że konkretny dokument produkcyjny zawiera ten błąd, lecz pokazuje brak skutecznej blokady.

Poprawka: jawne uzgodnienie zapisanej umowy z nową ofertą; różnice w zakresie, cenie, VAT, zaliczce i terminach blokują PDF do czasu rozstrzygnięcia. Nie przepisywać podpisanej wersji. Cena i harmonogram powinny mieć dane strukturalne jako podstawę dokumentu, a nie być weryfikowane wyłącznie przez akceptację dowolnego tekstu.

## 2. Wysoki: kontrola zgodności pomija sześć ustaleń handlowych

Miejsca: `api/contract-brief.php:10`, `api/contract-model.php:46`, `api/offer-agreement.php:9`.

`contractOfferFactDifferences()` analizuje tylko klucze istniejące w `contractFacts()`. Poza tą kontrolą są:

- `deliverySchedule` — harmonogram;
- `paymentSchedule` — płatności;
- `supportPlan` — wsparcie;
- `rightsSummary` — zasady praw i licencji;
- `externalCosts` — dodatkowe koszty;
- `dataPlan` — dane i role stron.

**Potwierdzenie:** kontrola zwróciła pustą listę różnic mimo całkowitego braku tych ustaleń w przekazanych faktach. Test HTTP przyjął umowę bez nowych ustaleń wsparcia i terminu z oferty. Możliwe są też sprzeczne opisy praw w ofercie i formularzu, których ta kontrola nie wykryje.

Poprawka: włączyć wszystkie te dane do utrwalonego załącznika uzgodnień handlowych i kontroli kompletności. Oddzielnie sprawdzać zgodność wybranego sposobu praw / roli danych z opisanymi ustaleniami; sprzeczności kierować do decyzji administratora.

## 3. Wysoki: zmiana wzoru usuwa dopisane ustalenia oferty

Miejsca: `api/contract.php:102`, `api/contract-catalog.php:45`, `api/contract-model.php:46`.

Nowy formularz dopisuje wsparcie, prawa, koszty i dane z oferty do pól `support`, `exclusions`, `extras`, `terms`. „Wczytaj wybrany wzór” zastępuje te całe pola czystym tekstem wzoru. Nie składa ponownie klauzul z ustaleniami zaakceptowanej oferty.

**Potwierdzenie:** odpowiedź `apply-template` zawiera same klauzule wzoru, bez uzgodnionego tekstu wsparcia. Ponowna ręczna akceptacja nie sprawdza, że takie ustalenie zostało utracone.

Poprawka: rozdzielić klauzule wzoru i indywidualne warunki. Wczytanie wzoru zmienia wyłącznie część klauzulową; uzgodnienia oferty pozostają oddzielnym modułem. PDF zawsze zawiera oba moduły.

## 4. Wysoki: ręczne ustalenia znikają po zmianie listy pytań

Miejsca: `api/bootstrap.php:59`, `api/internal-analysis.php:21`, `api/contract-brief.php:33`.

Normalizacja zachowuje `adminDecisions` tylko dla pytań nadal obecnych w `missingInformation`. Nowa analiza ma nie powtarzać rozstrzygniętych pytań. Gdy je usunie, normalizacja usuwa odpowiadające im ręczne decyzje. Późniejsze przygotowanie oferty i uzupełnianie materiałów z odpowiedzi mogą już nie dostać tej informacji. Przepisanie jej do tekstu analizy przez model jest możliwe, ale nie jest gwarancją zachowania danych.

**Potwierdzenie:** ręczna decyzja „Zespół przygotowuje zdjęcia za 200 PLN” została usunięta z `adminDecisions` po normalizacji analizy z pustą listą braków.

Poprawka: trwała ewidencja ustaleń niezależna od listy nierozstrzygniętych pytań. Ustalenia mają identyfikator, autora, wersję, obowiązujący status i miejsce w ofercie / umowie. Zniknięcie pytania nie usuwa odpowiedzi.

## 5. Średni: nowe zakresy mogą zachować stare kryteria i warunki oferty

Miejsca: `api/offer-agreement.php:24`, `api/offer.php:288` i dalsze przygotowanie `agreement`.

`array_replace($source,$previous)` daje pierwszeństwo poprzednim warunkom. Chroni ręczne poprawki, ale zachowuje również kryteria odbioru, terminy i materiały po zmianie zakresu, bez oznaczenia ich jako zależnych danych do ponownego sprawdzenia. Poprawnie kopiowana umowa może wówczas przejąć niespójność już istniejącą w ofercie.

**Potwierdzenie:** zakres analizy `NEW SCOPE` zachował w regenerowanej ofercie `acceptanceCriteria=OLD CRITERIA`.

Poprawka: po zmianie zakresu zaznaczać powiązane warunki do przeglądu, zachowując tekst administratora. Lista zmian powinna pokazywać wpływ na kryteria, materiały, harmonogram i wycenę. Akceptacja nowej oferty wymaga rozstrzygnięcia tych zależności.

## 6. Średni: wariant „możliwe później” wpływa na rodzaj umowy

Miejsce: `api/contract-catalog.php:12`.

Wybór wzoru przeszukuje wszystkie sekcje oferty, również wyłączone i opcjonalne. Umowa dla prostej strony może otrzymać wzór sklepu, ponieważ sklep wspomniano jako późniejszy etap. Wybrany wzór wpływa na treść odbioru i klauzul.

**Potwierdzenie:** oferta „One page firmy” + „Poza obecnym zakresem / możliwe później: Sklep internetowy” wybrała `ecommerce`.

Poprawka: dobór wzoru tylko z faktycznie zamawianego zakresu. Osobno prezentować wyłączenia i dalsze pomysły, żeby nie stanowiły obowiązków bieżącej umowy.

## 7. Średni: AI umowy nie otrzymuje pełnego pakietu oferty

Miejsce: `api/contract-ai.php:44`.

Kontekst oferty dla AI obejmuje projekt, sekcje, wycenę, płatności i `contractTerms`, ale nie obejmuje `agreement`. Część warunków może być dostępna przez aktualny formularz, jednak jest to niewystarczające przy pustych lub starych polach. AI nie może wtedy rzetelnie zgłosić wszystkich konfliktów względem aktualnej zaakceptowanej oferty. Ochrona istniejących pól dodatkowo zachowuje ich stare wartości.

Poprawka: przekazywać pełne właściwe uzgodnienia handlowe aktualnej oferty oraz raport różnic wobec umowy. AI proponuje sformułowania i wyjaśnia konflikty; kopiowanie podstawowych faktów i blokady zgodności muszą działać deterministycznie poza modelem. Sekrety i zbędne dane osobowe nadal poza kontekstem.

## Zalecana kolejność napraw

1. Zachować trwałe ręczne ustalenia; nie usuwać ich wraz z pytaniami.
2. Utworzyć jeden strukturalny pakiet zaakceptowanych warunków: zakres, wyłączenia, cena/VAT, płatności, terminy, materiały, odbiór, publikacja, hosting, prawa, wsparcie, koszty i dane.
3. Generować specyfikację i część handlową umowy z tego pakietu; oddzielić je od klauzul wzoru.
4. Blokować PDF przy zmianie źródłowej oferty lub niespójnościach; pokazać dokładne różnice do ręcznego uzgodnienia. Zachować wcześniejsze podpisane dokumenty.
5. Po zmianie zakresu wymagać przeglądu zależnych warunków oferty; dobierać wzór z bieżącego zakresu.
6. Uzupełnić kontekst AI i dodać testy regresji dla potwierdzonych scenariuszy.

Docelowo każda istotna decyzja powinna mieć widoczny ślad: **ustalenie → punkt oferty → paragraf / załącznik umowy**. Brak mapowania lub konflikt powinien być pokazany przed wygenerowaniem PDF, a nie dopiero zauważony podczas czytania dokumentu.

## Weryfikacja

Dotychczasowe scenariusze HTTP umów przeszły: autoryzacja, CSRF, wersje, manualny przegląd, wymagane fakty, PDF, mock wysyłki, historie, wzory i start realizacji. Dodatkowe próby wykazały opisane luki mimo przejścia podstawowych testów.

Lokalny, izolowany scenariusz audytu: `tmp/contract-audit-http.py`, przygotowany przez `tmp/prepare-contract-audit.py`. Oba pliki są robocze i ignorowane przez Git. Raport opisuje wejścia i obserwacje pozwalające odtworzyć problemy. Testy audytowe używają odrębnej bazy i fikcyjnych danych, nie kont produkcyjnych.

Ten audyt nie wdraża napraw. Nie zbadano treści konkretnej podpisanej umowy klienta; do ustalenia, czy błąd już wystąpił w rzeczywistej sprawie, należy porównać jej archiwalny PDF z dokładną zaakceptowaną ofertą.
