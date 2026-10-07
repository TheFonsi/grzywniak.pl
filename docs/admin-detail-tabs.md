# Zakładki szczegółów sprawy

Panel briefów dzieli zawartość wybranej sprawy na: Brief, Analiza i ustalenia, Oferta, Umowa oraz Rozmowa. Dane nagłówka, status sprawy i odnośnik do archiwum są wspólne. Ustalenia administratora są w analizie, statystyki rozmowy w rozmowie, a ryzyka przy briefie.

Zakładki przenoszą istniejące elementy formularzy, zamiast je ponownie renderować: przełączanie nie kasuje wpisów ani obsługi przycisków. Oferta i umowa wczytywane asynchronicznie pozostają w swoich zakładkach. Po wymianie szczegółów przez AJAX zakładki są ponownie inicjalizowane. Starsze funkcje układające zawartość nie przenoszą już elementów między gotowymi zakładkami.

Ostatnia zakładka jest zapamiętywana osobno dla każdej sprawy w sesji przeglądarki. Linki `#brief-panel`, `#analysis-panel`, `#offer-panel`, `#contract-panel` i `#conversation-panel` wybierają właściwą sekcję. Obsługiwane są klawisze strzałek oraz Home/End; na małym ekranie pasek przewija się poziomo. Przy wyłączonym JavaScript pozostaje dotychczasowy pełny widok.

Weryfikacja: `tests/admin-detail-tabs.mjs` (wymaga testowego jsdom w `tmp/contract-ui-tests`), `tests/contract-http.py`.

„Przygotuj umowę” w ofercie natychmiast otwiera zakładkę umowy. Podczas zapisywania akceptacji i wczytywania projektu widoczny jest stan ładowania; błędy mają przycisk ponowienia. Zapisana akceptacja nie jest ponawiana, jeśli zawiodło tylko wczytanie formularza. Wcześniejsze ładowanie umowy w tle jest anulowane, aby stara odpowiedź nie zastąpiła nowego formularza. Otwarcie już załadowanego projektu nie kasuje wpisanych danych.
