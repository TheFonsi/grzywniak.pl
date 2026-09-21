# Umowy aplikacji i stron — ustalenia PL / UE

Przegląd źródeł: 15.09.2026. To wskazówki do przygotowania wzoru, a nie opinia prawna ani gotowa umowa dla każdej transakcji. Wzór startowy w panelu zawiera instrukcje do uzupełnienia; przed użyciem należy opracować konkretne klauzule i zweryfikować je z prawnikiem.

## Prawa do projektu

„Sprzedaż aplikacji” może oznaczać wykonanie projektu, przeniesienie autorskich praw majątkowych albo licencję. Umowa powinna identyfikować utwory, pola eksploatacji, wynagrodzenie i moment przejścia praw. Samo wydanie plików nie przenosi praw. Nie sprzedaje się praw osobistych. Dla kodu należy uwzględnić przepisy szczególne; grafika i teksty wymagają właściwych postanowień o korzystaniu i opracowaniach. Trzeba sprawdzić prawa od pracowników i podwykonawców, a także licencje materiałów zewnętrznych. Podstawa: art. 16, 41, 46, 50, 52–53, 74–77 [ustawy o prawie autorskim](https://api.sejm.gov.pl/eli/acts/DU/2025/24/text.pdf) i [dyrektywa 2009/24/WE](https://eur-lex.europa.eu/legal-content/pl/TXT/?uri=CELEX%3A32009L0024).

Przeniesienie praw wymaga formy pisemnej pod rygorem nieważności. Zwykły e-mail, skan podpisu ani przycisk akceptacji nie zastępują tej formy. Elektronicznie należy zastosować kwalifikowane podpisy stron, równoważne podpisom własnoręcznym — [eIDAS, art. 25 ust. 2](https://eur-lex.europa.eu/legal-content/PL/TXT/PDF/?uri=CELEX%3A02014R0910-20240520). Panel nie weryfikuje podpisów; status wysyłki nie oznacza zawarcia umowy.

## Proponowany układ dokumentu

1. Strony i reprezentacja, przedmiot oraz załącznik opisujący zakres.
2. Etapy, terminy, zależności od materiałów klienta, kryteria odbioru i poprawki.
3. Cena, VAT właściwy dla transakcji, płatności i wynagrodzenie za prawa.
4. Prawa IP lub licencja, przekazanie repozytorium, dokumentacji i dostępów.
5. Wykaz bibliotek, SDK, zdjęć, fontów, SaaS i komponentów pozostających własnością wykonawcy lub osób trzecich.
6. Usuwanie wad; oddzielnie płatne wsparcie, godziny obsługi, reakcja, naprawa i zasady wypowiedzenia.
7. Dodatkowe zamówienia: zakres, akceptacja ceny lub stawki z limitem oraz wpływ na termin. Osobno koszty hostingu, API, domen i sklepów.
8. Odpowiedzialność, poufność, rozwiązanie i rozliczenia; załączniki zależne od projektu.

To rekomendowana struktura wdrożeniowa, nie ustawowy formularz. Dla aplikacji mobilnej warto ustalić właściciela kont sklepów, podpisywania wydań i odpowiedzialność za publikację. Dla strony: domenę, hosting, CMS i zasady aktualizacji.

## Konsumenci i dane osobowe

Nie należy automatycznie stosować wzoru B2B do konsumenta ani przedsiębiorcy korzystającego z odpowiedniej ochrony konsumenckiej. Obowiązki dotyczące zgodności treści/usług cyfrowych, aktualizacji, informacji i odstąpienia mogą obowiązywać niezależnie od płatnego wsparcia. Rozpoczęcie realizacji nie usuwa automatycznie prawa odstąpienia: [UOKiK — informacje przed zawarciem umowy](https://prawakonsumenta.uokik.gov.pl/prawo-do-informacji/sprzedaz-poza-lokalem-i-na-odleglosc/), [UOKiK — umowy szczególne](https://prawakonsumenta.uokik.gov.pl/prawo-odstapienia-od-umowy/umowy-szczegolne/), [UOKiK — zmiany konsumenckie](https://prawakonsumenta.uokik.gov.pl/zmiany-2023/).

Jeżeli wykonawca przetwarza dane w imieniu klienta, trzeba ustalić role i warunki powierzenia, w tym podwykonawców, bezpieczeństwo i zakończenie przetwarzania — [RODO, art. 28](https://eur-lex.europa.eu/legal-content/pl/TXT/?uri=CELEX%3A32016R0679). Sam transfer IP nie zastępuje tych ustaleń.

## Obsługa w panelu

Umowa znajduje się nad ofertą wybranej rozmowy. Edytor jest dostępny po akceptacji oferty. Ustawienia wzoru to wspólna baza dla nowych umów; dokument zapisuje własną treść i numer wersji wzoru. Kolejne zapisy archiwizują poprzedni dokument; edycja wymaga ponownego wygenerowania PDF. PDF jest załączany do wiadomości. `SENT` oznacza przekazanie wiadomości lokalnemu systemowi pocztowemu, nie potwierdzenie doręczenia; `MOCK_SENT` oznacza test bez wysyłki.

### Dane wykonawcy i uzupełnianie AI

W „Ustawieniach wzorów umów” sekcja „Moje dane do umów” zapisuje pełną nazwę, adres, NIP (jeśli dotyczy), reprezentację, kontakt, rachunek oraz zasady płatności. Dla JDG pełna firma powinna obejmować imię i nazwisko. Dane kontaktowe i identyfikujące są szczególnie istotne przy obowiązkach informacyjnych wobec konsumenta — [UOKiK](https://prawakonsumenta.uokik.gov.pl/pytania-i-odpowiedzi/prawo-do-informacji/). Wymagalność pól w panelu jest regułą kompletności projektu, a nie twierdzeniem, że brak każdego z nich unieważnia umowę. NIP, KRS, PESEL i rachunek bankowy nie są uniwersalnymi warunkami ważności wszystkich umów.

W formularzu ustala się status klienta, rodzaj praw, warunki ich przejścia/licencji, wynagrodzenie za IP, sposób podpisania, datę, dane klienta i rolę w przetwarzaniu danych. Także licencja wyłączna wymaga pisemnej formy pod rygorem nieważności (art. 67 ust. 5 ustawy o prawie autorskim).

„Wypełnij umowę AI” przygotowuje edytowalny projekt z zaakceptowanej oferty, bieżącego formularza i wzoru. Znane dane stron, rachunek, cenę, zaliczkę i termin system kopiuje z formularza. AI przygotowuje konkretne propozycje brakujących warunków handlowych, np. terminu, zasad odbioru i okresu wsparcia. Identyfikatory i fakty dotyczące stron nie mogą być wymyślane. Do modelu przekazywane są klauzule, warunki handlowe i wybrane ustalenia, bez osobnych pól danych stron i rachunku; należy pamiętać, że dane osobowe mogą nadal znajdować się w swobodnym opisie zakresu. Wykorzystano Responses API z `store=false` i [Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs); odpowiedź jest dodatkowo walidowana na serwerze.

Każde pole ma przyciski „Akceptuj” i „Zmień”. Akceptacja dotyczy projektu administratora, nie zgody klienta ani podpisania umowy. Zapis projektu utrwala treść i stan akceptacji. Edycja wartości cofa jej akceptację. Ponowne uruchomienie AI zachowuje zaakceptowane pola i otrzymuje je jako ustalenia do dopasowania reszty dokumentu. Domyślne warunki IP pozostają w ustawieniach; czas i zakres licencji są widoczne przy licencji.

PDF wymaga zatwierdzenia propozycji i uzupełnienia faktycznych braków. Brakujący adres, identyfikator albo reprezentacja nie są generowane losowo. Znacznik `[DO UZUPEŁNIENIA: ...]` oznacza konieczność wpisania rzeczywistych danych; brakujący warunek negocjowalny powinien otrzymać konkretną propozycję, bez znacznika do ręcznego usuwania. Kontrole nie zastępują oceny prawnej. AI nie korzysta z wyszukiwania aktualnego prawa podczas generowania.

Nowy agent nie jest potrzebny. Integracja korzysta z `OPENAI_API_KEY` i `OPENAI_MODEL`; opcjonalnie `OPENAI_CONTRACT_MODEL` pozwala ustawić model tylko do umów. `CONTRACT_AI_MOCK=true` służy wyłącznie do testów. AI uzupełnia formularz bez zapisu i bez wysyłki; decyzję o zapisie, PDF i wysyłce podejmuje użytkownik. Zapisana umowa zachowuje własną treść i wersję profilu wykonawcy.

Walidacja: `php tests/contract-workflow.php`, `python tests/contract-http.py`, `npm run typecheck`, `npm run build`. Test HTTP używa odrębnej kopii API i bazy w `tmp/contract-http` oraz atrap wysyłki.
