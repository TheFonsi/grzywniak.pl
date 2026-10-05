# Aktualność i ręczna edycja ofert

- Numer oferty i numer analizy są niezależne. Oferta v7 może nadal pochodzić z analizy v2.
- Identyfikatory archiwum nie zmieniają aktualności dokumentu. Starszy hash jest uzgadniany z zapisanymi źródłami; rzeczywista zmiana briefu, analizy lub decyzji nadal oznacza ofertę jako nieaktualną.
- Nazwę projektu, podsumowanie, nagłówki sekcji i punkty można edytować po kliknięciu. „Zapisz zmiany” tworzy nową wersję roboczą. „Anuluj edycję” przywraca wyświetlony dokument.
- Edycja zaakceptowanej oferty wymaga potwierdzenia i ponownej akceptacji nowej wersji. Podpisana umowa nie jest automatycznie zmieniana.
- Nieaktualny szkic można edytować i przenosić pomysły do zakresu. Zachowuje powiązanie ze starymi źródłami i wymaga odświeżenia z bieżącej analizy przed akceptacją.
- Zapis z panelu sprawdza token formularza i numer edytowanej wersji. Konflikt nie kasuje wpisanego tekstu.
- Zmiany pomysłów poza zakresem mają neutralną informację i nie wymagają osobnej akceptacji punktów. Pozostają w historii zmian. Żółte porównanie z przyciskami dotyczy zmian właściwego zakresu i innych warunków oferty; przeniesienie pomysłu do realizacji pokazuje zmianę zakresu.
- Samo uzupełnienie opcjonalnych pomysłów nie resetuje potwierdzeń warunków. Zmiana zamówionego zakresu, ceny albo źródeł nadal wymaga ich sprawdzenia. Wcześniejsze wymagane potwierdzenia nie są automatycznie usuwane.
- Przy brakach formularz warunków rozwija się automatycznie i wskazuje niepotwierdzone warunki oraz nieprzypisane ustalenia. Po sprawdzeniu trzeba zapisać formularz, a następnie zweryfikować ofertę. Wypełniony tekst i przypisanie ustalenia do miejsca w ofercie są osobnymi wymaganiami.

Weryfikacja: `tests/offer-source.php`, `tests/offer-scope-http.py`, `tests/offer-click-edit-ui.mjs`, `tests/offer-workflow.php`, `tests/offer-scope-ui.mjs`, `tests/document-flow-http.py`, `tests/project-gates-http.py`. Testy HTTP korzystają z osobnych baz i fikcyjnych danych.
