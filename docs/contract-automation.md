# Automatyczne przygotowanie umowy

Nowy projekt umowy dobiera aktualny wzór do projektu i pobiera warunki z zaakceptowanej oferty. Zapisane starsze dokumenty pozostają w dotychczasowej wersji. Przycisk **Przygotuj automatycznie** wczytuje propozycję do formularza; nie zapisuje dokumentu ani nie wysyła wiadomości.

Po przeglądzie przycisk **Przejrzałem — zatwierdź całość i przygotuj PDF** potwierdza wszystkie przedstawione pola i generuje nową wersję pakietu. Pozostają kontrole kompletności, aktualności oferty, powiązania źródeł i zgodności kwot. Brakujące rzeczywiste dane są wskazywane zamiast wymyślane. Podpis i potwierdzenie zawarcia umowy są osobnymi zdarzeniami.

Zakres, cena, płatności, harmonogram, odbiór, materiały, publikacja, prawa i warunki wsparcia pochodzą z zaakceptowanego pakietu handlowego. Wzór zawiera ponadto zasady odpowiedzialności, poufności, zmian zakresu, zakończenia współpracy i rozliczeń. Wymagane załączniki zależą od wybranego wariantu. Dane rzeczywistych dostawców i okresy przechowywania kontaktów można ustawić raz w profilu wykonawcy. Nie zgadujemy statusu klienta, danych rejestrowych, praw do cudzych materiałów ani podwykonawców przetwarzania.

PDF ma osobną stronę tytułową z podsumowaniem i danymi obu stron, numerowane paragrafy, wcięcia i czytelne odstępy. Panel pokazuje właściwy PDF w osadzonym podglądzie. Oferta stanowi Załącznik 1: dla nowych akceptacji dołączane są dokładne zapisane strony zaakceptowanego pliku. Starszych podpisanych plików nie przepisujemy. Aby zmienić wygląd starszego szkicu, trzeba przygotować i wygenerować nową wersję.

Układ v3 używa szeryfowej typografii Times, z metrykami polskich znaków. Starszy niewysłany i niepodpisany szkic pokazuje aktualny układ przez osobny, tylko do odczytu endpoint `format=layout-preview`. Informacja przy podglądzie rozróżnia go od zapisanego pliku używanego do pobrania i wysyłki. PDF jest również widoczny w etapie „Umowa” na mapie. Podgląd nie zmienia treści, wersji, historii ani zatwierdzenia pakietu. Wysłane, podpisane i archiwalne wersje pokazują oryginalny plik.

Układ v4 zaczyna od danych obu stron. Informacje o projekcie znajdują się po danych stron i nie zawierają ceny. Cały paragraf wynagrodzenia, zaliczki i płatności jest ostatnim paragrafem umowy, bezpośrednio przed podpisami. Kolejność i brak kwoty na okładce sprawdza test renderowania.

Automatyczne uzupełnienie nie stanowi indywidualnej opinii prawnej. Forma podpisu i pola eksploatacji wymagają uwagi przy przeniesieniu praw ([PARP](https://www.parp.gov.pl/component/content/article/83261%3Aumowa-o-przeniesienie-autorskich-praw-majatkowych)); rzeczywiste powierzenie przetwarzania wymaga ustaleń odpowiadających art. 28 [RODO](https://eur-lex.europa.eu/eli/reg/2016/679/oj?locale=PL).

Weryfikacja: `tests/contract-http.py`, `tests/document-flow-http.py`, `tests/contract-package.php`, `tests/contract-review-ui.mjs` oraz render wszystkich stron fikcyjnej umowy z `tests/offer-annex.py`. Testy używają osobnych baz i fikcyjnych danych.
