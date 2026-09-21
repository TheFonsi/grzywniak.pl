# Audyt przepływu AI Analityka Projektowego

## Aktualizacja: centrum realizacji projektów (19.09.2026)

Poniższe ustalenia dotyczą obecnego kodu. Starsze sekcje tego dokumentu opisują wcześniejszy etap aplikacji.

| Priorytet | Ustalenie | Zalecane działanie |
|---|---|---|
| P0 | Nie zweryfikowano pełnego przebiegu na rzeczywistych kontach GitHub/Codex, Cloudflare i VPS. Testy runnera i kontrolera VPS sprawdzają kontrakt HTTP, bez tworzenia repozytorium, obrazu, rekordu DNS i uruchomienia kontenera. | Skonfigurować osobne konta i poświadczenia, uruchomić fikcyjny projekt od repozytorium do produkcji oraz sprawdzić awarie i ponowienia. |
| P0 | Kontroler VPS uruchamia pojedynczy kontener aplikacji. Nie ma manifestu bazy danych, wolumenów, migracji i kopii zapasowych dla aplikacji wymagających trwałych danych. | Dodać deklaratywny manifest zasobów, migracje i test przywrócenia danych przed obsługą takich projektów. |
| P1 | Planista i agent uwag są objęci wspólnym limitem projektu, miesiąca i równoległości, ale starsze wywołania AI w rozmowie, analizie, ofercie i umowie mają oddzielną ewidencję. | Ujednolicić koszty całego procesu od pierwszej rozmowy, jeśli limit ma obejmować także etap przed zawarciem umowy. |
| P1 | Sprawy, zdarzenia i ustawienia są w SQLite, a panel chroni Basic Auth. Docelowy PostgreSQL, konta administratorów, role i MFA nie powstały. | Zaplanować migrację danych z kontrolą kompletności, role i MFA przed szerszym dostępem zespołu. |
| P1 | Wysyłka linku do podglądu używa PHP `mail()` w trakcie transakcji bazy. Przy niepowodzeniu zapisu po przyjęciu wiadomości przez serwer pocztowy historia może nie odzwierciedlać wysyłki. | Wprowadzić kolejkę wysyłkową z idempotencją, statusem dostarczenia i ponowieniem. |
| P2 | Klasyfikacja uwag rozróżnia błąd, zmianę zakresu i pytanie, ale formalna zmiana zakresu nie ma osobnej ścieżki nowej oferty, umowy i ponownej zgody. | Dodać wersjonowane ustalenia i bramkę zatwierdzenia zmian przed tworzeniem zadań. |

Testy lokalne: `php tests/project-feedback-agent.php`, `php tests/project-execution.php`, `php tests/project-plan.php`, `php tests/project-templates.php`, `php tests/project-map-review.php`, `node tests/codex-runner.mjs`, `node tests/vps-control.mjs` oraz `npm run build` przechodzą. Wynik nie potwierdza działania usług zewnętrznych.

## Aktualny przepływ

1. `api/discovery.php` prowadzi krótką rozmowę i zapisuje stan projektu w sesji JSON.
2. Agent zbiera problem, cel, użytkowników, zakres, budżet, termin i dane kontaktowe.
3. Po zakończeniu rozmowy generowany jest czytelny brief oraz analiza wewnętrzna.
4. Panel administracyjny generuje propozycje dla brakujących informacji. Każda propozycja wymaga ręcznego zatwierdzenia.
5. Po uzupełnieniu braków administrator przygotowuje dokument oferty.
6. Oferta jest najpierw robocza, następnie może zostać zweryfikowana, pobrana jako PDF i wysłana na zapisany adres klienta.

## Kontrole jakości

- Historia użytkownika jest ograniczana do ostatnich wiadomości przekazywanych do modelu, ale stan projektu pozostaje zapisany w sesji.
- Dane kontaktowe są ponownie odczytywane z wcześniejszych wiadomości, aby nie pytać o nie drugi raz.
- Do przekazania briefu wymagane są: nazwa osoby lub firmy, telefon i e-mail.
- Agent nie powinien przedstawiać wyceny jako ostatecznej; oferta jest oznaczona jako wstępna.
- Brakujące informacje są generowane pojedynczo, a odpowiedzi są oznaczone jako niezatwierdzone do czasu decyzji administratora.
- Przycisk przygotowania oferty pozostaje nieaktywny, dopóki wszystkie propozycje nie zostaną zatwierdzone.
- Zmiana ceny netto jest zapisywana jako ręczna i nie jest nadpisywana przy odświeżeniu dokumentu.
- Oferta ma status `DRAFT`, `REVIEWED` albo `SENT`; wysyłka jest możliwa dopiero po ręcznym zatwierdzeniu.
- PDF zawiera datę, zakres, podsumowanie, wycenę, zaliczkę i dane kontaktowe.

## Najważniejsze ryzyka produkcyjne

- Funkcja `mail()` wymaga konfiguracji serwera pocztowego. Na lokalnym PHP wysyłka może się nie udać — należy skonfigurować SMTP lub zastąpić `mail()` dostawcą transakcyjnym.
- Sesje są przechowywane jako pliki JSON. Przy większej liczbie rozmów warto przenieść je do bazy danych i dodać kopie zapasowe.
- Przed wdrożeniem trzeba ustawić silne dane Basic Auth panelu oraz ograniczyć dostęp do panelu po HTTPS.
- Szacunek godzin jest heurystyczny i powinien być zweryfikowany przez człowieka przed wysłaniem klientowi.
- Wysyłkę oferty należy wykonywać tylko z panelu po sprawdzeniu zakresu, ceny, terminu i odbiorcy.

## Weryfikacja wykonana lokalnie

- `php -l api/discovery.php` — poprawny.
- `php -l api/offer.php` — poprawny.
- `php -l api/admin.php` — poprawny.
- `npm run typecheck` — poprawny.
- `npm run build` — poprawny.
- Endpoint sesji discovery zwrócił HTTP 200.
- Endpoint oferty zwrócił HTTP 200.
- Pobieranie oferty zwróciło `application/pdf`.
- Skrypt panelu administracyjnego przeszedł kontrolę składni Node.js.

## Audyt UI/UX panelu administracyjnego

- Lista rozmów ma wyróżnienie aktywnego elementu, stan hover i przewijanie niezależne od szczegółów.
- Panel szczegółów pokazuje loader podczas zmiany rozmowy, dzięki czemu nie ma wrażenia zawieszenia.
- Status oferty, przyciski generowania, weryfikacji i wysyłki są rozdzielone i mają jasne stany disabled/loading.
- Przyciski i linki mają widoczny focus keyboard oraz stany hover.
- Długi tekst briefu i wiadomości zawija się bez rozszerzania layoutu.
- Na ekranach mobilnych kolumny układają się pionowo, a lista rozmów ma ograniczoną wysokość.
- Archiwizacja, przywracanie i usuwanie są dostępne zbiorczo za listą rozmów; szczegóły rozmowy pozostają skupione na briefie, analizie i ofercie.

## Analityka operacyjna panelu

Panel pokazuje na bieżąco na podstawie zapisanych sesji:

- liczbę wszystkich rozmów i aktywnych rozmów,
- średnią liczbę tokenów na rozmowę oraz turę AI,
- tokeny wejściowe, wyjściowe i z cache,
- średnią liczbę wiadomości klienta,
- liczbę i procent briefów przekazanych,
- liczbę przygotowanych, zweryfikowanych i wysłanych ofert,
- konwersję rozmowa → oferta oraz rozmowa → wysyłka.

Rozmowy bez żadnej wiadomości klienta nie są wliczane, dzięki czemu automatyczne lub porzucone sesje nie zaniżają jakościowych wskaźników.

Dostępne są również filtry zakresu czasu: 7 dni, 30 dni, 90 dni i cały okres. Wykres „Lejek obsługi” pokazuje przejście od rozmów do wysłanych ofert, a wykres „Zużycie tokenów” rozdziela tokeny wejściowe, wyjściowe i obsłużone z cache.

Sugestie odpowiedzi w chatcie są dodatkowo dopasowywane po stronie interfejsu wyłącznie do ostatniego akapitu z pytaniem, a nie do całej wypowiedzi asystenta. Pytania o podstrony pokazują warianty liczby sekcji, pytania o budżet — przedziały cenowe, a pytania o termin — przedziały czasowe. Znane, ale niepasujące zestawy sugestii z API są ukrywane zamiast prezentowania klientowi błędnych odpowiedzi.

## Zalecany proces pracy zespołu

1. Otworzyć brief i sprawdzić analizę wewnętrzną.
2. Zatwierdzić, zmienić albo uzupełnić każdą czerwoną propozycję.
3. Przygotować lub odświeżyć ofertę.
4. Sprawdzić zakres, wyłączenia, termin, liczbę godzin, stawkę, VAT i zaliczkę.
5. Oznaczyć dokument jako zweryfikowany.
6. Pobrać PDF do archiwum, a następnie — jeśli dane kontaktowe są poprawne — wysłać ofertę klientowi.
