# Audyt obsługi umowy przez administratora

Zakres: przegląd kodu formularza, synchronizacji oferty, akcji przygotowania, walidacji oraz testy integracyjne na fikcyjnych danych. Nie jest to audyt danych konkretnej sprawy na produkcji ani opinia prawna.

## Znalezione problemy i poprawki

1. **Dwa różne stany opisane jednym komunikatem.** Zapisana umowa może mieć poprzedni pakiet oferty, podczas gdy formularz został już przygotowany z aktualnego. Komunikat teraz wskazuje numer i wersję oraz konkretne działanie. Po przygotowaniu potwierdza aktualność formularza, ale nie udaje zapisania nowego PDF.
2. **Kilka konkurujących ścieżek.** „Wczytaj wzór”, „wczytaj ofertę”, AI, brief, przygotowanie oraz dwie kopie przycisków zatwierdzenia utrudniały wybór. Główna ścieżka ma jeden przycisk automatycznego przygotowania, jedno zatwierdzenie z PDF i zapis szkicu. Pozostałe operacje są w zaawansowanych.
3. **Długi formularz.** Dodano wewnętrzne sekcje: Podsumowanie, Dane stron, Zakres i odbiór, Publikacja, Prawa i dane. Przełączanie zachowuje wartości i obsługę formularzy. Ukryte sekcje nadal przesyłają dane; niewłaściwe pole wymagane przez przeglądarkę odsłania swoją sekcję.
4. **Wrażenie konieczności wielu akceptacji.** Podsumowanie wyjaśnia zbiorczą akceptację i pokazuje liczbę braków przy sekcjach. Pasek głównych działań pozostaje dostępny podczas przewijania.

## Docelowa ścieżka

Zaakceptowana oferta → przygotowanie projektu z aktualnej oferty → przegląd sekcji i uzupełnienie rzeczywistych braków → jedno zatwierdzenie i nowy PDF → podgląd pakietu → odrębne potwierdzenie przeglądu prawnego → wysyłka → potwierdzenie zawarcia.

Zgodność numeru, wersji i hasha pakietu nadal jest sprawdzana na serwerze. Formularz nie może sam uznać starej umowy za aktualną ani nadpisać zaakceptowanej oferty. Przy prawdziwej zmianie warunków potrzebna jest nowa uzgodniona oferta. Otwarcie formularza i przygotowanie propozycji nie zmieniają zapisanej umowy, historii ani dowodów podpisania.

Weryfikacja: `tests/contract-http.py`, `tests/contract-workspace.mjs`, `tests/contract-review-ui.mjs`. Sprawdzono zachowanie wszystkich pól przy przebudowie formularza, pojedyncze główne działania, przypisanie do sekcji, ujawnienie niepoprawnego pola, aktualizację powiązania z ofertą oraz kontrolę wersji i CSRF.
