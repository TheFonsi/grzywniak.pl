# Aktualność i ręczna edycja ofert

- Numer oferty i numer analizy są niezależne. Oferta v7 może nadal pochodzić z analizy v2.
- Identyfikatory archiwum nie zmieniają aktualności dokumentu. Starszy hash jest uzgadniany z zapisanymi źródłami; rzeczywista zmiana briefu, analizy lub decyzji nadal oznacza ofertę jako nieaktualną.
- Nazwę projektu, podsumowanie, nagłówki sekcji i punkty można edytować po kliknięciu. „Zapisz zmiany” tworzy nową wersję roboczą. „Anuluj edycję” przywraca wyświetlony dokument.
- Edycja zaakceptowanej oferty wymaga potwierdzenia i ponownej akceptacji nowej wersji. Podpisana umowa nie jest automatycznie zmieniana.
- Nieaktualny szkic można edytować i przenosić pomysły do zakresu. Zachowuje powiązanie ze starymi źródłami i wymaga odświeżenia z bieżącej analizy przed akceptacją.
- Zapis z panelu sprawdza token formularza i numer edytowanej wersji. Konflikt nie kasuje wpisanego tekstu.

Weryfikacja: `tests/offer-source.php`, `tests/offer-scope-http.py`, `tests/offer-click-edit-ui.mjs`, `tests/offer-workflow.php`, `tests/offer-scope-ui.mjs`, `tests/document-flow-http.py`, `tests/project-gates-http.py`. Testy HTTP korzystają z osobnych baz i fikcyjnych danych.
