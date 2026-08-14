# Light Manager

Menadżer plików w terminalu (PHP + Imagick + Sixel). Zanim zaczniesz pisać
lub modyfikować kod w `src/`/`tests/`, zastosuj konwencje z:

- `.claude/skills/light-manager-conventions/SKILL.md` — operacyjny skrót
  (warstwy DDD, wzorzec Singleton, standardy PHP).
- `docs/architecture.md` — pełny dokument źródłowy.
- `docs/plans/00-index.md` i `docs/plans/00-decyzje.md` — status realizacji
  i historia decyzji.

Zamiast podejmować decyzje pytaj użytkownika.

**Procesy projektu uruchamiaj celami `make`** — `make` bez argumentów wypisuje
spis, bramka jakości to `make qa`. **Tam, gdzie projekt ma własne narzędzie
(`bin/render-bench`, `bin/terminal-probe`), używaj jego, zamiast dorabiać
zastępnik doraźnie.** Zawężenie przebiegu wolno wołać wprost (pojedynczy test
filtrem, jedna oś pomiaru); zakazana jest równoległa droga do procesu, który
wejście już ma. Pełny spis „proces → wejście”: `docs/architecture.md`, rozdz. 8.

**Przed pomiarem wydajności (`make bench*`, `bin/render-bench`) oraz przed
sprawdzeniem działania aplikacji w prawdziwym terminalu (zrzuty ekranu, klatka
pod XTermem, `make run*`) poproś użytkownika o zwolnienie mocy obliczeniowej
hosta** — zatrzymanie kompilacji, kontenerów, przeglądarki i innych zadań —
i **poczekaj na potwierdzenie**. Cele pomiarowe nie mają bariery technicznej,
mają tę regułę. Obciążona maszyna daje rozrzut, przy którym `--save` odmawia
zapisu wzorca, a liczby z takiego przebiegu nie nadają się na punkt odniesienia
(zdarzyło się w kroku 22).

Nie odstępuj od tych ustaleń bez jawnej zgody użytkownika — to świadome
decyzje architektoniczne, nie przypadkowe konwencje.
