# Light Manager

Menadżer plików w terminalu (PHP + Imagick + Sixel). Zanim zaczniesz pisać
lub modyfikować kod w `src/`/`tests/`, zastosuj konwencje z:

- `.claude/skills/light-manager-conventions/SKILL.md` — operacyjny skrót
  (warstwy DDD, wzorzec Singleton, standardy PHP).
- `docs/architecture.md` — pełny dokument źródłowy.
- `docs/plans/00-index.md` i `docs/plans/00-decyzje.md` — status realizacji
  i historia decyzji.

Zamiast podejmować decyzje pytaj użytkownika.

**Przed pomiarem wydajności (`bin/render-bench`) oraz przed sprawdzeniem
działania aplikacji w prawdziwym terminalu (zrzuty ekranu, klatka pod XTermem)
poproś użytkownika o zwolnienie mocy obliczeniowej hosta** — zatrzymanie
kompilacji, kontenerów, przeglądarki i innych zadań — i **poczekaj na
potwierdzenie**. Obciążona maszyna daje rozrzut, przy którym `--save` odmawia
zapisu wzorca, a liczby z takiego przebiegu nie nadają się na punkt odniesienia
(zdarzyło się w kroku 22).

Nie odstępuj od tych ustaleń bez jawnej zgody użytkownika — to świadome
decyzje architektoniczne, nie przypadkowe konwencje.
