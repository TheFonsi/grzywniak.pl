# Wykonawca VPS

Ta usługa realizuje kontrakt `/v1/projects`, `/v1/deployments` i rollback używany przez centrum projektów. Przeznaczona jest dla osobnego VPS z Linuksem, Docker Engine i Compose. Nie uruchamiaj jej na serwerze WWW z danymi klientów.

## Uruchomienie

1. Skopiuj `.env.example` do `.env` na VPS. Ustaw silny `VPS_CONTROL_TOKEN` (minimum 32 znaki), adres `VPS_CONTROL_HOST`, adres e-mail ACME i token Cloudflare ograniczony do DNS odpowiedniej strefy.
2. Utwórz katalogi `data/state`, `data/dynamic`, `data/acme` i `data/docker-config`. Nadaj `data` prawa tylko operatorowi usługi. Przygotuj `data/acme/acme.json` z uprawnieniami `600`.
3. Zaloguj Docker do `ghcr.io` przy użyciu osobnej tożsamości z prawem **odczytu** pakietów prywatnych repozytoriów. Konfigurację Docker zapisz w `data/docker-config/config.json`. Nie umieszczaj tokenu rejestru w `.env` projektu ani w obrazach aplikacji.
4. Skieruj `VPS_CONTROL_HOST` na VPS. W Cloudflare ustaw tryb **Full (strict)**. Uruchom `docker compose up -d --build` w tym katalogu. Kontroler jest dostępny wyłącznie przez Traefik i wymaga Bearer tokenu. W zaporze hosta udostępnij tylko porty 80 i 443.
5. W panelu administratora ustaw `VPS_CONTROL_URL=https://<VPS_CONTROL_HOST>`, `VPS_CONTROL_TOKEN`, domeny podglądu i produkcji oraz host origin Cloudflare. Host origin CNAME powinien wskazywać na adres VPS.

Kontroler montuje Docker socket, więc ma uprawnienia administracyjne do kontenerów na tym VPS. Nie udostępniaj jego tokenu agentom piszącym kod. Kopiuj `data/state` i `data/acme` w ramach kopii zapasowej. Każdy nowy obraz aplikacji działa w osobnym kontenerze z limitem pamięci, CPU i procesów. Poprzedni kontener jest zachowany do cofnięcia wdrożenia.

Repozytorium aplikacji musi publikować obraz `ghcr.io/<organizacja>/<repo>@sha256:...`. Zadanie QA zwraca `appPort` i `healthPath`; kontroler sprawdza tę ścieżkę przed przełączeniem Traefik. Publiczny adres udostępnia `/.well-known/grzywniak/deployment`, a centrala dodatkowo sprawdza HTTPS i ścieżkę zdrowia po wdrożeniu. Obrazy wymagające bazy danych, wolumenów lub migracji trzeba rozszerzyć o jawny manifest zasobów przed realizacją takiego projektu.

Test lokalny bez Docker: `node ../../tests/vps-control.mjs`.
