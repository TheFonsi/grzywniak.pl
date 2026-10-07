# Audyt obsługi umowy przez administratora

Zakres: przegląd kodu formularza, synchronizacji oferty, akcji przygotowania, walidacji oraz testy integracyjne na fikcyjnych danych. Nie jest to audyt danych konkretnej sprawy na produkcji ani opinia prawna.

## Znalezione problemy i poprawki

1. **Dwa różne stany opisane jednym komunikatem.** Zapisana umowa może mieć poprzedni pakiet oferty, podczas gdy formularz został już przygotowany z aktualnego. Komunikat teraz wskazuje numer i wersję oraz konkretne działanie. Po przygotowaniu potwierdza aktualność formularza, ale nie udaje zapisania nowego PDF.
2. **Kilka konkurujących ścieżek.** „Wczytaj wzór”, „wczytaj ofertę”, AI, brief, przygotowanie oraz dwie kopie przycisków zatwierdzenia utrudniały wybór. Główna ścieżka ma jeden przycisk automatycznego przygotowania, jedno zatwierdzenie z PDF i zapis szkicu. Pozostałe operacje są w zaawansowanych.
3. **Długi formularz.** Dodano wewnętrzne sekcje: Podsumowanie, Dane stron, Zakres i odbiór, Publikacja, Prawa i dane. Przełączanie zachowuje wartości i obsługę formularzy. Ukryte sekcje nadal przesyłają dane; niewłaściwe pole wymagane przez przeglądarkę odsłania swoją sekcję.
4. **Wrażenie konieczności wielu akceptacji.** Podsumowanie wyjaśnia zbiorczą akceptację i pokazuje liczbę braków przy sekcjach. Pasek głównych działań pozostaje dostępny podczas przewijania.

## Docelowa ścieżka

Przygotowanie automatyczne zapisuje osobny `contractWorkingDraft`, powiązany z wersją umowy i hashem oferty. Odświeżenie odtwarza przygotowane pola i wzór, nie podmienia zapisanego PDF ani jego akceptacji. Po zmianie oferty lub wersji dokumentu szkic nie jest wczytywany. Zapis umowy usuwa szkic roboczy. Akceptacje zakładek i ręczne zmiany formularza wymagają zapisania projektu lub przygotowania PDF.

Aktualizacja: akceptacja jest teraz osobna dla każdej zakładki. „Przejrzałem — zatwierdź” akceptuje wyłącznie kompletne pola otwartej sekcji. „Przygotuj PDF” nie akceptuje danych automatycznie: pokazuje czerwoną listę braków i niezatwierdzonych pól z odnośnikami do sekcji. Kontrola na serwerze pozostaje wymagana.

Poprawka przygotowania ponownego: pola wykazów bez unikalnych nazw powodowały fałszywy komunikat o edycji podczas żądania. Porównanie śledzi teraz konkretne elementy, ich wartości i zaznaczenie. Status nad formularzem pokazuje wynik lub błąd, a przycisk blokuje podwójne żądania. Test `tests/contract-reprepare-ui.mjs` odtwarza problem i sprawdza powodzenie, błąd serwera oraz zachowanie ręcznych zmian podczas oczekiwania.

Zaakceptowana oferta → przygotowanie projektu z aktualnej oferty → przegląd sekcji i uzupełnienie rzeczywistych braków → jedno zatwierdzenie i nowy PDF → podgląd pakietu → odrębne potwierdzenie przeglądu prawnego → wysyłka → potwierdzenie zawarcia.

Zgodność numeru, wersji i hasha pakietu nadal jest sprawdzana na serwerze. Formularz nie może sam uznać starej umowy za aktualną ani nadpisać zaakceptowanej oferty. Przy prawdziwej zmianie warunków potrzebna jest nowa uzgodniona oferta. Otwarcie formularza i przygotowanie propozycji nie zmieniają zapisanej umowy, historii ani dowodów podpisania.

Weryfikacja: `tests/contract-http.py`, `tests/contract-workspace.mjs`, `tests/contract-review-ui.mjs`. Sprawdzono zachowanie wszystkich pól przy przebudowie formularza, pojedyncze główne działania, przypisanie do sekcji, ujawnienie niepoprawnego pola, aktualizację powiązania z ofertą oraz kontrolę wersji i CSRF.
