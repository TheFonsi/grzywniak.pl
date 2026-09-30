# Szablon projektu webowego Grzywniak

Prywatny szablon startowy dla projektów React + Vite. Zmiany agentów trafiają do gałęzi roboczych i pull requestów. Workflow `CI` uruchamia zadanie `validate` dla pull requestów, a po połączeniu do `main` buduje obraz w GHCR oznaczony SHA commita.

Ten szablon jest punktem startowym. Agent architektury i agenci budowy zastępują przykładowy ekran kodem wynikającym z zatwierdzonego briefu i umowy.

Każdy podgląd klienta musi zachować w `index.html` skrypt `/grzywniak-feedback.js`. VPS chroni podgląd unikalnym hasłem, a nakładka pozwala zaznaczyć obszar, opisać uwagę i przypisać ją do dokładnej wersji obrazu.
