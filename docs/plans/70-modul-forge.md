# Krok 70 — Moduł `forge`: PR-y, zgłoszenia i przeglądy zespołu

> **Skąd ten krok.** Powstał 2026-08-16 jako pierwszy krok **Fazy XXIII**
> ([00-decyzje.md](00-decyzje.md), D98). Jest **zarysem, a nie planem**;
> z całej propozycji ma **najwięcej pytań otwartych** i to one, a nie rozmiar
> kodu, przesądzą o jego kształcie.

## Status

**Nie rozpoczęty — zarys.** Rozstrzygnięcia startowe **nie powstały**. Dwa
pytania są przy tym warunkiem wstępnym całej Fazy XXIII — droga do cudzego
serwisu i miejsce na sekret; patrz sekcja „Pytania do rozstrzygnięcia".

## Cel

Aplikacja pokazuje **pracę zespołu wokół repozytorium**: zgłoszenia, prośby
o scalenie, kolejkę przeglądów i stan potoku. To jest ten moduł, który
odpowiada na oś „zespół deweloperski" — pozostałe odpowiadają na aplikacje
i projekty.

Miarą powodzenia jest zdanie: **lista „czeka na mój przegląd" jest w aplikacji
i zgadza się z tym, co widać w przeglądarce internetowej.**

## Trudność — rozmówca, którego aplikacja nie miała

Aplikacja rozmawiała dotąd z gniazdem lokalnym (Docker), z procesem potomnym
(`kubectl`, `ssh`) i — od kroku 61 — z rejestrem obrazów po HTTP. **Serwis
zespołowy jest czwartym rodzajem rozmówcy**: sieć rozległa, uwierzytelnienie
tokenem, limity zapytań i odpowiedzi stronicowane. Reguła nadrzędna zostaje ta
sama, co w Fazie XVII: **żadne wywołanie sieciowe nie pada w rysowaniu klatki.**

Druga trudność jest środowiskowa i sprawdzona: **`gh` ani `glab` nie ma na
maszynie**. Droga przez cudzego klienta znaczy więc zależność ogłoszoną
zdolnością `RequiresEnvironment` (precedens kroku 48), a droga własna — rozmowę
HTTP, dla której wzorem jest krok 61, nie krok 51: tam gniazdo jest lokalne
i szybkie, tu odpowiedź idzie przez internet.

## Zarys zakresu

- **Kolejka przeglądów** — co czeka na mnie, co czeka na innych, co stoi
  najdłużej.
- **Prośby o scalenie** — lista, opis, gałąź źródłowa i docelowa, stan potoku.
- **Zgłoszenia** — lista z filtrem po etykiecie i przypisaniu.
- **Różnica** — treść zmiany w `TextView`, wzorem widoku różnicy z kroku 67.
- **Stan potoku** — wynik ostatniego przebiegu i jego logi.
- **Kwerendy** — `forge.reviews`, `forge.pulls`, `forge.issues`, `forge.checks`.

## Czym płaci rdzeń

**Zero zmian** — jedna pozycja w `Bootstrapie`. Sekret ma dom od kroku 54
(`ModuleSetting::secret()`), a plik ustawień tryb `0600`.

## Pytania do rozstrzygnięcia

1. **Droga: cudzy klient czy własne HTTP?** `gh` daje uwierzytelnienie za darmo
   (jak `kubeconfig` w kroku 52), ale nie ma go w środowisku i trzeba go
   ogłosić wymaganiem. Własne HTTP nie ma zależności, ale bierze na siebie
   token, stronicowanie i limity.
2. **Gdzie mieszka token.** Dziś sekret leży **jawnym tekstem w pliku ustawień
   o trybie `0600`** — tak samo jak w `~/.docker/config.json`. Czy to
   wystarcza, czy sekret ma iść przez `secret-tool`/`pass`? **Pytanie dotyczy
   trzech modułów naraz** (ten, `http`, `db`), więc rozstrzyga się raz i przed
   nimi.
3. **Jeden serwis czy książka.** GitHub, GitLab i instancje własne różnią się
   API. Pierwszy krok obsługuje jeden (tego projektu — GitHub) czy od razu
   wprowadza książkę wpisów?
4. **Czy moduł pisze** — komentarz, akceptacja, scalenie. Zapis w cudzym
   systemie w imieniu użytkownika jest osobną klasą ryzyka i osobnym oknem
   potwierdzenia.
5. **Odświeżanie** — na żądanie, czy taktem modułu (`NeedsTick` z kroku 45)?
   Takt znaczy ruch w sieci bez pytania użytkownika i limit zapytań zużywany
   w tle.

## Stan zastany (sprawdzony 2026-08-16)

| Element | Stan |
|---|---|
| `gh`, `glab` | **Nie ma ani jednego** w `PATH`. |
| `curl` | Jest — zarówno jako program, jak i rozszerzenie PHP (`ext-curl`, używane przez moduł Dockera). |
| Repozytorium projektu | `origin` na GitHubie (`git@github.com:sksz/Light-Manager.git`), jedna gałąź, jeden autor. |
| Sekret w ustawieniach | `ModuleSetting::secret()` od kroku 54 (D94 nr 7) — maskuje **w interfejsie**; plik ma tryb `0600`. |

## Zależności

- **Krok 67** — gałąź i zatwierdzenie katalogu, na których stoi użytkownik.
- **Krok 61** — rozmowa HTTP z uwierzytelnieniem jako najbliższy wzór.
- **Krok 48** — zdolność `RequiresEnvironment` i zakaz podawania poświadczeń
  wierszem polecenia.
- **Kroki 20, 26, 27, 28, 29, 30** — moduł, praca tłowa, tabela, pytanie, widok
  tekstu, filtr.
- **Kroki 53 i 54** — kwerendy i sekret w ustawieniach modułu.

## Model i wysiłek (wstępnie)

**Opus / xhigh.** Warunek `Fable` nie zachodzi. Wysiłek trzymają trzy rzeczy
naraz: **nowy rodzaj rozmówcy** (sieć rozległa z tokenem i stronicowaniem),
**rozstrzygnięcie o sekretach**, które obowiąże dwa dalsze kroki, i powierzchnia
czterech widoków.

## Poza zarysem

- Przeglądanie kodu z komentarzem wiersz po wierszu — to jest edytor, nie widok.
- Zarządzanie repozytorium w serwisie (uprawnienia, ustawienia, wydania).
- Powiadomienia wypychane.
- Serwisy inne niż wybrany w rozstrzygnięciu nr 3.

## Dziennik realizacji

*(Krok nie rozpoczęty — wpisy pojawią się przy wykonaniu.)*
