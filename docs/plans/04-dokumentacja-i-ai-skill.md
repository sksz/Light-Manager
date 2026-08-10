# Krok 04 — Dokumentacja ustaleń: docs projektu + Claude Code Skill

## Status

Ukończony (weryfikacja widoczności Skilla w nowej sesji — patrz Dziennik
realizacji)

## Zależności

Kroki 01, 02, 03 (potrzebna kompletna, ostateczna treść ustaleń
architektury i stylu przed ich utrwaleniem).

## Model i wysiłek

Sonnet / medium — głównie redakcja i synteza już podjętych decyzji do dwóch
różnych postaci (dokument + Skill); niska niepewność architektoniczna, ale
wciąż na przyjętej podłodze `medium` (zobacz [00-decyzje.md](00-decyzje.md),
D5).

## Cel

Przenieść wytyczne wypracowane w krokach 01–03 do trwałej, odnajdywalnej
postaci: dokumentu w `docs/` dla ludzi oraz Skill dla Claude Code, tak by
były automatycznie stosowane w przyszłych sesjach pracy nad tym projektem
(zobacz [00-decyzje.md](00-decyzje.md), D8).

## Zakres

- `docs/architecture.md` (dokładna nazwa do potwierdzenia w trakcie
  realizacji) — spisane ustalenia z kroków 01–03: warstwy DDD i reguła
  zależności, słownik domenowy, wzorzec Singleton i jego granice
  (kto wolno, kto nie wolno go używać), standardy stylu i narzędzia
  (PSR-12, strict_types, PHPStan, PHP-CS-Fixer, konwencje testów).
- Projektowy Skill dla Claude Code: `.claude/skills/<nazwa>/SKILL.md`
  (np. `light-manager-conventions`) z opisem wyzwalającym go przy pracy nad
  kodem w tym repozytorium. Skill ma zawierać zwięzłe, operacyjne
  streszczenie konwencji (nie kopię całej dokumentacji) z odesłaniem do
  `docs/architecture.md` po szczegóły — priorytet: szybki do wczytania i
  praktyczny w użyciu, a nie wyczerpujący.
- Weryfikacja: nowa sesja Claude Code otwarta w tym repozytorium faktycznie
  widzi ten Skill na liście dostępnych skilli i stosuje go bez konieczności
  ręcznego przypominania.
- Aktualizacja [00-index.md](00-index.md), tak by wskazywał
  `docs/architecture.md` i Skill jako obowiązujące odniesienie dla
  wszystkich kroków 05–12.

## Poza zakresem tego kroku

Dalsze zmiany treści merytorycznej ustaleń (to działo się w krokach 01–03)
— tu wyłącznie utrwalenie i dystrybucja już podjętych decyzji.

## Ryzyka

- Skill zbyt obszerny (kopiujący całą dokumentację) będzie kosztowny do
  wczytania i mniej praktyczny — do pilnowania: Skill ma być skrótem
  operacyjnym, nie duplikatem `docs/architecture.md`.
- Rozjazd między dokumentem a Skillem w miarę ewolucji konwencji w kolejnych
  krokach — do zaadresowania zasadą „każda zmiana konwencji aktualizuje oba
  miejsca jednocześnie”, zapisaną w samym dokumencie/Skillu.

## Kryteria ukończenia

- `docs/architecture.md` zawiera kompletne, spójne ustalenia z kroków
  01–03.
- `.claude/skills/<nazwa>/SKILL.md` istnieje, jest zwięzły i widoczny jako
  dostępny Skill w nowej sesji Claude Code otwartej w tym repozytorium.
- `00-index.md` odsyła do obu miejsc jako obowiązującego odniesienia.

## Specyfikacja

Zamiast wpisywać treść bezpośrednio do tego pliku planu (jak w krokach
01–03), ten krok tworzy docelowe, fizyczne artefakty — to jego właściwy
zakres („utrwalenie i dystrybucja”, nie kolejna specyfikacja robocza):

- [`docs/architecture.md`](../architecture.md) — konsolidacja ustaleń z
  kroków 01–03 w jeden, spójny dokument referencyjny (7 sekcji: warstwy,
  słownik domenowy, Singleton/porty/bootstrap, standardy PHP, nazewnictwo,
  przykłady kodu, odniesienia dalej).
- [`.claude/skills/light-manager-conventions/SKILL.md`](../../.claude/skills/light-manager-conventions/SKILL.md)
  — Skill projektowy z opisem wyzwalającym przy pracy nad kodem w
  `src/`/`tests/` tego repozytorium; treść to 9 twardych reguł + skrót
  nazewnictwa, z odesłaniem do `docs/architecture.md` po szczegóły (nie
  kopia całej dokumentacji — zgodnie z ryzykiem odnotowanym w tym kroku).
- [`CLAUDE.md`](../../CLAUDE.md) — dodatkowe zabezpieczenie ponad dosłowny
  zakres D8, ustalone przy starcie tego kroku (D13): krótki, bezwarunkowo
  ładowany wskaźnik na Skill i `docs/architecture.md`.
- [`00-index.md`](00-index.md) — nowa sekcja „Dokumentacja architektury”
  wskazująca oba miejsca jako obowiązujące odniesienie dla kroków 05–12.

### Weryfikacja widoczności Skilla — ograniczenie

Kryteria ukończenia tego kroku wymagają potwierdzenia, że nowa sesja
Claude Code w tym repozytorium widzi Skill na liście dostępnych skilli.
**Nie da się tego zweryfikować w bieżącej sesji** — lista dostępnych
skilli jest ustalana na starcie sesji (widoczna jako system-reminder na
początku rozmowy), więc Skill utworzony w trakcie tej samej sesji nie
mógł się na niej pojawić z przyczyn technicznych, nie błędu w treści
pliku. Weryfikacja wymaga otwarcia nowej sesji Claude Code w katalogu
tego repozytorium i sprawdzenia, czy `light-manager-conventions` pojawia
się na liście skilli.

## Dziennik realizacji

- **2026-08-07** — Ukończono tworzenie artefaktów (`docs/architecture.md`,
  Skill, `CLAUDE.md`, aktualizacja `00-index.md`). Dodano `CLAUDE.md` jako
  zabezpieczenie ponad dosłowny zakres D8 (decyzja D13, podjęta na starcie
  tego kroku). Weryfikacja widoczności Skilla w nowej sesji **pozostaje do
  wykonania przez użytkownika** — wymaga nowej sesji Claude Code w tym
  repozytorium (patrz sekcja „Weryfikacja widoczności Skilla” wyżej); do
  tego czasu status kroku traktować jako częściowo otwarty mimo ukończenia
  właściwej pracy redakcyjnej.
