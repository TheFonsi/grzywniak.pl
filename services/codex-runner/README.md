# Oddzielny runner agentów Codex

Runner przyjmuje zadania z centrum projektów przez `POST /v1/tasks`, udostępnia stan przez `GET /v1/tasks/{id}` i obsługuje anulowanie. Pracuje na **osobnym koncie Codex**, na oddzielnym hoście Linux. Nie używa konta ani poświadczeń autora centrali.

## Wymagania

- Node.js 22+, Git i Codex CLI. Konto Codex zalogowane dla użytkownika o UID `RUNNER_CODEX_UID`.
- Osobny token GitHub zapisany w pliku dostępnym wyłącznie procesowi runnera. Token powinien mieć dostęp tylko do repozytoriów organizacji, które ma obsługiwać. Runner klonuje repozytorium, zleca Codex pracę w sandboxie `workspace-write`, a zmiany zapisuje na gałęzi i otwiera pull request.
- HTTPS przed usługą. Sam proces nasłuchuje tylko na `127.0.0.1:3020`; reverse proxy powinno przekazywać ruch do niego. W panelu wpisz ten publiczny adres jako `CODEX_RUNNER_URL` oraz wspólny token jako `CODEX_RUNNER_TOKEN`.

Przykładowe ustawienia procesu:

```text
RUNNER_PORT=3020
RUNNER_TOKEN=<losowy token, minimum 32 znaki>
RUNNER_WORK_DIR=/var/lib/grzywniak-runner
RUNNER_GITHUB_TOKEN_FILE=/etc/grzywniak-runner/github-token
RUNNER_REVIEW_TOKEN_FILE=/etc/grzywniak-runner/reviewer-token
RUNNER_GITHUB_USERNAME=<nazwa konta GitHub agentów>
GITHUB_ORG=Grzywniak
RUNNER_CODEX_UID=10001
RUNNER_CODEX_GID=10001
RUNNER_CODEX_HOME=/home/codex/.codex
RUNNER_INPUT_PLN_PER_MILLION=<stawka rozliczeniowa>
RUNNER_CACHED_PLN_PER_MILLION=<stawka rozliczeniowa>
RUNNER_OUTPUT_PLN_PER_MILLION=<stawka rozliczeniowa>
```

Uruchom `node server.mjs` jako nadzorowaną usługę systemową z uprawnieniami do odczytu pliku tokenu GitHub i zmiany właściciela katalogów zadań. Proces Codex otrzymuje UID `RUNNER_CODEX_UID` oraz środowisko bez tokenu GitHub i tokenu centrali. Nie umieszczaj pliku tokenu GitHub wewnątrz `RUNNER_WORK_DIR` ani `RUNNER_CODEX_HOME`. Utrzymuj kopię `tasks.json`; katalog `work` zawiera kod klientów.

Runner traktuje koszt PLN jako **wewnętrzne rozliczenie** z konfiguracji stawek i tokenów zgłoszonych przez Codex CLI. Nie jest to potwierdzona kwota faktury OpenAI. Jeśli CLI nie zwróci danych zużycia, centrala zatrzyma rezerwację do ręcznego rozliczenia. Przekroczenie limitu zadania blokuje wysłanie zmian, lecz pojedynczy przebieg modelu może zużyć więcej niż zarezerwowano; centrala zapisze zgłoszony koszt i pokaże błąd.

Zadania kodowe kończą się dopiero po połączeniu pull requestu do `main`. Jeśli ustawisz `RUNNER_REVIEW_TOKEN_FILE`, runner uruchamia **drugi, niezależny przebieg Codex w trybie odczytu**, zapisuje jego werdykt jako review przy PR, sprawdza wynik `validate` i próbuje scalić PR. Token recenzenta musi należeć do innego konta GitHub niż token autora oraz mieć uprawnienia do przeglądu i scalania PR. Bez tego tokenu PR czeka na ręczny przegląd, a panel pokazuje link i stan. Kontrola QA bez zmian w kodzie sprawdza commit `main`, wynik workflow `publish` i digest obrazu w GHCR.

Test kontraktu bez dostępu do Codex i GitHub: `node ../../tests/codex-runner.mjs`.
