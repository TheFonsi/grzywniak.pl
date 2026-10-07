# Własne postanowienia i archiwum umów

## Obsługa w panelu

W zakładce „Prawa i dane” pole „Dodatkowe postanowienia uzgodnione z klientem” pozwala dodawać, edytować i usuwać pozycje z tytułem i treścią. Puste pozycje blokują PDF. Treść jest zatwierdzana razem z zakładką i trafia do osobnego załącznika w tym samym PDF, uwzględnionego w manifeście pakietu. Zmiana treści unieważnia jej wcześniejsze zatwierdzenie. Postanowienia nie zmieniają automatycznie zakresu, wyceny ani przyjętej oferty; takie zmiany należy najpierw uzgodnić w ofercie.

Poniżej edytora „Archiwum umów i podpisanych dokumentów” zawiera wygenerowane PDF poszczególnych wersji oraz formularz wgrywania podpisanego obustronnie PDF lub dokumentu dodatkowego. Przy wgrywaniu administrator wskazuje konkretną wersję. Archiwum zachowuje oryginalne bajty, sumę SHA-256, rodzaj, nazwę pliku, czas, użytkownika oraz sumę oryginalnego wygenerowanego PDF. Nie przetwarza ani nie modyfikuje podpisanych dokumentów. Sam upload nie potwierdza autentyczności podpisów i nie uruchamia projektu.

Ponowienie identycznego uploadu dla tej samej wersji i rodzaju zwraca istniejący wpis. Pobrane pliki wymagają uwierzytelnienia administratora i zgodności identyfikatora sprawy. Nie są dostępne pod publicznymi adresami storage.

## Wdrożenie i przechowywanie

Tabela `contract_files` powstaje automatycznie w istniejącej bazie SQLite. Kopie bazy powinny obejmować również tę tabelę, ponieważ przechowuje pliki. Limit aplikacji wynosi 10 MB na PDF; PHP i reverse proxy muszą pozwalać na taki upload (`upload_max_filesize` co najmniej 10M, `post_max_size` większe, np. 12M). Większa baza wymaga odpowiedniego miejsca na dysku i w kopiach zapasowych.

Weryfikacja: testy HTTP (upload, błędny plik, CSRF, przypisanie wersji, powtórzenie, pobranie dokładnych bajtów, izolacja spraw), test edytora własnych postanowień i test pakietu/PDF z dwoma dodatkowymi postanowieniami. Układ załącznika sprawdzono po wyrenderowaniu PDF.
