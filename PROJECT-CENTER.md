# Centrum projektów — stan wdrożenia

## Dostępne lokalnie

- `/api/project-portfolio.php` — lista przekazanych briefów, filtry i kolejka decyzji.
- `/api/project-case.php?session=<id>` — mapa 15 etapów, szczegóły i historia sprawy. Przekazane briefy z listy starego panelu prowadzą do mapy; trwające rozmowy pozostają w dotychczasowym widoku.
- `/api/project-api.php` — chronione API odczytu i dwóch bramek: potwierdzenia zawarcia aktualnej wersji umowy oraz zatwierdzenia startu z zakresem, opiekunem i limitem kosztów. Każda operacja zapisuje zdarzenie.
- `/api/project-worker.php` — wykonawca kolejki. Po starcie projektu agent planowania przygotowuje architekturę, kamienie milowe i zadania, a agent infrastruktury tworzy prywatne repozytorium z szablonu, sprawdza workflow CI i konfiguruje ochronę `main`. Ponowienie używa tej samej nazwy repozytorium. `PROJECT_AI_MOCK=true` włącza deterministyczny plan do testów.
- Po utworzeniu repozytorium worker zleca przygotowanie środowiska. Adapter `/api/project-provision.php` prosi prywatne API VPS o projekt, a potem tworzy lub sprawdza rekord CNAME w Cloudflare. Rekordu o innej zawartości nie nadpisuje. Brak konfiguracji VPS/Cloudflare kończy zadanie czytelnym błędem do ponowienia.
- `templates/web-vite/` — minimalny szablon React + Vite z Dockerfile oraz GitHub Actions (`validate` dla PR, publikacja obrazu GHCR po połączeniu do `main`).

## Dobór i rozwój szablonów

Planista otrzymuje katalog aktywnych szablonów i wybiera jeden tylko wtedy, gdy pasuje do zakresu oraz technologii. Tworzenie repozytorium jest zlecane dopiero po planie. Jeśli projekt znacząco się różni, planista zapisuje propozycję nowego szablonu z uzasadnieniem, technologią i powiązaniem ze sprawą; widać ją w `/api/project-template-catalog.php` oraz na mapie projektu. Dla takiego projektu powstaje osobne puste prywatne repozytorium z ochroną `main`. CI i wdrożenie czekają na zbudowanie stosu przez agentów. Projekt nie jest automatycznie zakładany na szablonie Vite.

Osobny agent Codex ma na podstawie zrealizowanego projektu przygotować ogólny szablon w osobnym prywatnym repozytorium organizacji, usuwając dane klienta, sekrety i elementy objęte jego umową. Repozytorium musi mieć workflow `.github/workflows/ci.yml` i być oznaczone jako GitHub template. Na serwerze infrastruktury `php api/project-worker.php --activate-template=<id>` sprawdza te warunki przez GitHub App i aktywuje szablon. Nowy szablon staje się później dostępny dla kolejnych projektów. Automatyczne napisanie plików szablonu przez osobnego agenta Codex wymaga jeszcze podłączenia jego runnera; obecnie powstaje propozycja i działa kontrolowany etap aktywacji.

## Podłączenie GitHub

Ustawienia usług są dostępne w panelu: `/api/project-settings.php` (link **Ustawienia systemu**). Można tam zapisać organizację i GitHub App, Cloudflare, domenę podglądową, API VPS, model planisty, dane przyszłego runnera Codex oraz limity. Worker odczytuje zapisane ustawienia przy każdym zadaniu; brakujące wartości bierze z `.env`. Sekrety są szyfrowane lokalnym kluczem w `api/storage/project-settings.key`, nie wracają do formularza i muszą być kopiowane razem z bazą przy przenoszeniu serwera. Strona wymaga konta administratora i tokenu formularza.

Adres i token runnera Codex oraz limity sterują kolejką zadań. Kod usługi runnera jest w `services/codex-runner/`; trzeba go uruchomić na osobnym koncie i hoście. Domyślny limit projektu jest używany jako wartość startowa w formularzu zatwierdzania realizacji.

Moje środowisko nie potrzebuje dostępu do GitHub. Uprawnienia produkcyjne należą do osobnych tożsamości uruchomionych na docelowym serwerze:

- **Agent infrastruktury:** GitHub App używana wyłącznie do publikacji szablonu, tworzenia prywatnych repozytoriów i ustawień CI/ochrony gałęzi. Nie dostaje dostępu do konta Codex używanego do pisania kodu.
- **Agenci kodujący:** osobne konto i środowisko Codex z dostępem do wybranych repozytoriów. Pracują na gałęziach roboczych i otwierają pull requesty; nie mają tokenu Cloudflare ani uprawnień administracyjnych VPS.
- **Centrala:** zleca zadania i odczytuje wyniki. Nie przejmuje poświadczeń osobistego konta Codex ani nie przekazuje ich do promptów.

1. Utwórz GitHub App w organizacji `Grzywniak` z uprawnieniami do tworzenia i konfigurowania prywatnych repozytoriów. Zainstaluj ją dla wszystkich repozytoriów organizacji, aby nowo utworzone projekty również były dostępne. Wymagane są uprawnienia repozytorium `Administration: write`, `Contents: write` (publikacja plików szablonu) i `Checks: read` (weryfikacja CI przed wdrożeniem).
2. Ustaw `GITHUB_APP_ID`, `GITHUB_INSTALLATION_ID`, `GITHUB_APP_PRIVATE_KEY_FILE`, `GITHUB_ORG=Grzywniak`, `GITHUB_TEMPLATE_OWNER=Grzywniak` i `GITHUB_TEMPLATE_REPO=web-vite-template` **na serwerze wykonawcy infrastruktury**, nie na moim koncie Codex. Plik klucza musi być poza katalogiem publikowanym przez serwer WWW.
3. Na serwerze wykonawcy uruchom `php api/project-worker.php --publish-template`. Komenda utworzy prywatne repozytorium `Grzywniak/web-vite-template`, zsynchronizuje pliki lokalnego szablonu i oznaczy repozytorium jako template. Zmienione istniejące pliki aktualizuje z kontrolą ich SHA; operacja jest przeznaczona dla zarządzanego repozytorium szablonu.
4. Uruchom `php api/project-worker.php` jako nadzorowaną usługę w tle. Do przetworzenia jednego zadania służy `php api/project-worker.php --once`.

## Runner agentów i kontrola podglądu

Po utworzeniu repozytorium plan jest zapisywany jako osobne zadania agentów. Worker uruchamia zadania po zakończeniu ich zależności. Przed zleceniem rezerwuje limit jednego zadania (`AGENT_TASK_COST_LIMIT_PLN`) i sprawdza limit projektu, miesięczny limit runnera oraz `AGENT_MAX_CONCURRENCY`. Nie zleca pracy, gdy rezerwacja przekroczyłaby którykolwiek limit. Runner działa na osobnym koncie Codex i sam posiada dostęp do wybranego repozytorium; centrala przekazuje jedynie zakres, rolę, repozytorium, kryteria odbioru, limit kosztu i czasu.

Planista i agent uwag używają tego samego limitu projektu, miesiąca, zadania i równoległości. Przed wywołaniem modelu centrala rezerwuje szacowany górny koszt z rozmiaru żądania, limitu tokenów odpowiedzi i stawek `OPENAI_INPUT_PLN_PER_MILLION` oraz `OPENAI_OUTPUT_PLN_PER_MILLION` ustawionych w panelu. Po odpowiedzi rozlicza liczbę tokenów podaną przez dostawcę. Brak rozliczenia pozostawia rezerwację; administrator może ją rozliczyć po sprawdzeniu dostawcy. Stawki trzeba ustawić zgodnie z używanym modelem przed pierwszym wywołaniem.

Gdy plan wymaga nowego stosu zamiast istniejącego szablonu, system dodaje zadanie `scaffold` przed pozostałymi zadaniami. Agent architekt przygotowuje kod startowy, workflow CI/CD, Dockerfile i ścieżkę zdrowia. Po połączeniu tej pracy do `main` centrala sprawdza obecność plików, włącza wymaganą kontrolę `validate` i dopiero wtedy zleca środowisko VPS/Cloudflare. Propozycja wielokrotnego szablonu pozostaje osobną decyzją administratora.

Runner musi obsługiwać idempotentne `POST /v1/tasks` z `Idempotency-Key` i zwracać to samo `id`, które dostał w żądaniu. `GET /v1/tasks/{id}` zwraca `id`, `state` (`queued`, `running`, `done`, `failed`), `costPln` dla stanu końcowego oraz `result.summary` dla zakończonego zadania. `POST /v1/tasks/{id}/cancel` musi zwrócić stan `failed` i rozliczony `costPln`. Przekroczenie zadeklarowanego kosztu blokuje rozliczenie i wymaga wyjaśnienia. Zadanie QA zwraca dodatkowo `result.qaPassed=true`, `result.commitSha` (40 znaków hex), `result.imageDigest` (`sha256:` z 64 znakami hex), `result.appPort` i `result.healthPath`. Runner nie może sam publikować produkcji.

Podgląd jest osobnym zadaniem po ukończeniu wszystkich zadań i przygotowaniu infrastruktury. Centrala sprawdza na GitHub pozytywną kontrolę `validate` dla commita, zleca VPS wdrożenie dokładnego obrazu przez `POST /v1/deployments`, a następnie sprawdza publiczny adres HTTPS `/.well-known/grzywniak/deployment`. Odpowiedź 200 JSON musi zawierać `projectId` oraz dokładne `imageDigest`. Dopiero taki wynik pokazuje podgląd jako gotowy. Wysłanie linku klientowi wymaga osobnego kliknięcia administratora. Wiadomość zawiera link do formularza uwag; każda uwaga zapisuje identyfikator obrazu. Formularz ogranicza liczbę zgłoszeń i nie ujawnia tokenu w odpowiedzi panelu. Administrator zapisuje rozstrzygnięcia uwag, po czym może zatwierdzić produkcję dokładnie tej wersji podglądu. Produkcja używa osobnej subdomeny i wymaga ponownej kontroli HTTPS. Przy niepowodzeniu kontroli centrala wywołuje rollback VPS. Po udanej publikacji administrator zapisuje przekazanie projektu.

## Pozostałe prace przed automatycznym wdrożeniem A–Z

Obecna mapa pokazuje dalsze etapy jako niedostępne, ponieważ nie ma jeszcze podłączonego VPS ani procesów agentów budujących kod. Nie należy oznaczać ich jako wykonanych na podstawie samego schematu.

1. Usługa VPS jest przygotowana w `services/vps-control/` dla Linux + Docker + Traefik. Obsługuje idempotentne tworzenie środowiska, routing, TLS, uruchamianie obrazu po digest, kontrolę zdrowia, poprzednią wersję i rollback. Trzeba ją zainstalować oraz sprawdzić na rzeczywistym VPS; lokalny test obejmuje uwierzytelnienie i walidację API, ale nie wykonuje Dockera ani Cloudflare. Instrukcja uruchomienia jest w `services/vps-control/README.md`.
2. Uruchomić `services/codex-runner/` na osobnym koncie Codex i nadać mu dostęp wyłącznie do właściwych repozytoriów. Runner ma drugi przebieg Codex do niezależnego przeglądu i opcjonalny token osobnej tożsamości GitHub do zatwierdzania i scalania PR. Lokalny test potwierdza autoryzację i walidację kontraktu; praca Codex, zatwierdzanie PR oraz GHCR wymagają sprawdzenia na kontach agentów.
3. Uzupełnić kontrolę obrazu w rejestrze, webhooki GitHub i historię CI. Obecnie centrala sprawdza `validate` na GitHub, a dokładny obraz musi potwierdzić VPS i publiczny adres.
4. Uwagi klienta trafiają do kolejki klasyfikacji: błąd w zakresie, zmiana zakresu, pytanie lub inna uwaga. Panel pokazuje ocenę agenta i wpływ na zakres oraz termin. Administrator może zlecić poprawkę tylko dla błędu w zatwierdzonym zakresie: powstaje zadanie budowy i zależne QA, a po ich zakończeniu nowa wersja podglądu. Zmiana zakresu wymaga odrębnej decyzji i aktualizacji ustaleń. Poprawkę trzeba ponownie pokazać klientowi i zapisać jej rozstrzygnięcie przed produkcją. Produkcja, cofanie po błędzie kontroli publicznej oraz przekazanie mają bramki w centrali; wymagają wykonawcy VPS.
5. Przenieść dane spraw i kolejki z obecnej SQLite do PostgreSQL oraz zastąpić Basic Auth kontami administratorów z MFA. Do czasu tej zmiany panel powinien być dostępny wyłącznie przez HTTPS i ograniczoną sieć.

Operacje zewnętrzne nie zostały uruchomione, ponieważ lokalna konfiguracja nie zawiera kompletu poświadczeń GitHub App, Cloudflare, VPS i runnera. Nie publikuj `.env` ani klucza GitHub App w repozytorium.
