# Krok 72 — Moduł `services`: jednostki systemd i ich dziennik

> **Skąd ten krok.** Powstał 2026-08-16 jako trzeci krok **Fazy XXIII**
> ([00-decyzje.md](00-decyzje.md), D98). Jest **zarysem, a nie planem**.

## Status

**Nie rozpoczęty — zarys.** Rozstrzygnięcia startowe **nie powstały**.

## Cel

Moduł pokazuje **jednostki systemd** — systemowe i użytkownika — wraz ze stanem,
pozwala je uruchomić, zatrzymać i przeładować, a ich dziennik czyta strumieniem,
tak jak moduł Dockera czyta logi kontenera.

Miarą powodzenia jest zdanie: **restart usługi i podejrzenie, dlaczego nie
wstała, mieszczą się w jednym ekranie.**

## Dlaczego to pasuje do tej aplikacji

Bo cały wzór jest już napisany dla kontenerów: lista z tonem wiersza,
`ConfirmOverlay` przed czynnością nieodwracalną, logi strumieniem pracy tłowej.
Moduł `services` jest **tym samym kształtem z innym rozmówcą** — a rozmówca jest
lokalny i szybki, więc nie wnosi ani jednego nowego ryzyka sieciowego.

## Zarys zakresu

- **Lista jednostek** — nazwa, stan wczytania, stan aktywności, opis; osobno
  zakres systemowy i użytkownika.
- **Czynności** — start, stop, restart, przeładowanie; każda za pytaniem, bo
  każda jest widoczna poza aplikacją.
- **Dziennik** — `journalctl -u <jednostka> -f` strumieniem, w `TextView`,
  wzorem logów kontenera z kroku 51.
- **Opis jednostki** — `systemctl show`/`cat` w sekcjach z kroku 22.
- **Filtr** — po nazwie i po stanie (tylko padnięte, tylko aktywne).
- **Kwerendy** — `services.units`, `services.unit`.

## Czym płaci rdzeń

**Zero zmian** — jedna pozycja w `Bootstrapie`.

## Pytania do rozstrzygnięcia

1. **Uprawnienia.** Jednostki systemowe wymagają `sudo`, a aplikacja nie ma
   i nie chce mieć drogi do podniesienia uprawnień. Czy pierwszy krok obejmuje
   **wyłącznie zakres użytkownika** (`--user`), a systemowy tylko czyta?
2. **Zakres widoczności** — wszystkie jednostki (na maszynie są ich setki), czy
   spis prowadzony przez użytkownika, jak książka hostów?
3. **Czy moduł odrzuca się bez systemd** — maszyna bez `systemctl` (kontener,
   macOS, WSL bez systemd) ma zobaczyć powód, a nie pusty ekran
   (`RequiresEnvironment`, krok 48).
4. **Host zdalny.** Ten sam ekran dla usług na serwerze jest naturalnym
   życzeniem — ale transport SSH należy do modułu `ssh`, a moduł nie sięga do
   modułu. Powtarza się tu rozstrzygnięcie kroku 58 (droga jest daną wpisu) czy
   pozycja zostaje wyłączona?
5. **Jak często odświeża się lista** — na żądanie, czy taktem modułu?

## Stan zastany (sprawdzony 2026-08-16)

| Element | Stan |
|---|---|
| systemd | Wersja 255 (Ubuntu 24.04). |
| `systemctl --user` | Działa bez podnoszenia uprawnień — jednostki użytkownika są widoczne od ręki. |
| Formaty maszynowe | `list-units --output=json`, `show --property=…` — wejście parsera bez tekstu dla ludzi. |
| `journalctl` | Jest; `-f` daje strumień, czyli dokładnie to, co obsługuje praca tłowa. |

## Zależności

- **Kroki 20, 26, 51** — kontrakt modułu, praca tłowa, kilka prac naraz
  i wzorzec strumienia logów.
- **Kroki 22, 27, 28, 29, 30** — sekcje, tabela, pytanie, widok tekstu, filtr.
- **Krok 48** — `RequiresEnvironment`.
- **Krok 53** — kwerendy.

## Model i wysiłek (wstępnie)

**Opus / high.** Nowej drogi technicznej nie wnosi; ciężar leży w **czynnościach
widocznych poza aplikacją** — zatrzymana usługa to skutek, którego nie cofa
`Ctrl`+`Z` — oraz w rozstrzygnięciu o uprawnieniach.

## Poza zarysem

- Pisanie i edycja plików jednostek.
- Timery, gniazda i inne typy jednostek poza usługami — wchodzą z odbiorcą.
- Podnoszenie uprawnień z aplikacji.
- Menadżery inne niż systemd.

## Dziennik realizacji

*(Krok nie rozpoczęty — wpisy pojawią się przy wykonaniu.)*
