# 6. Workflow pracy

> Przewodnik dewelopera, część 6 z 7. [Spis](README.md) ·
> [English](../../en/guide/06-workflow.md)

## Kolejność, której się trzyma

```
make check-env  →  make install  →  praca  →  make qa  →  [pomiar]  →  dokumentacja
```

Ostatni krok nie jest ozdobnikiem i nie odkłada się go na później: **krok, który
zmienia klawisz, ustawienie, komendę, kwerendę albo moduł, aktualizuje
podręcznik i przewodnik w tym samym kroku.** Dług dokumentacyjny bez właściciela
jest długiem, którego nikt nie spłaci.

## Trzy reguły, które w tym projekcie są twarde

1. **Wejściem do procesu jest cel `make`.** `make` bez argumentów wypisuje spis;
   cel, którego w spisie nie ma, nie istnieje. Tam, gdzie projekt ma **własne
   narzędzie** (`bin/render-bench`, `bin/terminal-probe`), używa się jego,
   zamiast dorabiać zastępnik doraźnie. Zawężenie przebiegu wolno wołać wprost —
   pojedynczy test filtrem, jedna oś pomiaru; **zakazana jest równoległa droga
   do procesu, który wejście już ma**.
2. **Przed pomiarem i przed oglądaniem klatki prosi się o zwolnienie maszyny** —
   i czeka na potwierdzenie. Obciążony host daje rozrzut, przy którym `--save`
   odmawia zapisu wzorca, a liczby z takiego przebiegu nie nadają się na punkt
   odniesienia.
3. **Złote klatki odnawia się dopiero po przeczytaniu różnicy.** Plik
   regenerowany automatem przestaje być testem.

## Środowisko i instalacja

```bash
make check-env   # czy ta maszyna udźwignie projekt — działa przed instalacją
make install     # composer install; powtórzony nie robi nic
```

`make check-env` rozróżnia **twarde** wymogi (kończą się kodem błędu),
**ostrzeżenia** (brak kodera `SIXEL`) i **informacje** (`glfw`, `intl`,
`xterm`). Jednej rzeczy sprawdzić nie potrafi i mówi to wprost: czy sam terminal
umie Sixel — od tego jest `make probe`.

## Bramka jakości

```bash
make qa            # cs-check → stan → test, stop na pierwszym błędzie
make qa-full       # to samo do końca, ze zbiorczym podsumowaniem
make cs-check      # PHP-CS-Fixer — podgląd zmian, bez zapisu
make cs            # PHP-CS-Fixer — zapis poprawek
make stan          # PHPStan (poziom max)
make test          # PHPUnit — obie grupy naraz
make coverage      # pokrycie do build/coverage/ (wymaga Xdebuga albo PCOV-u)
```

Definicje tych poleceń mieszkają w `composer.json` (`cs`, `cs:check`, `stan`,
`test`) — cele `make` je **wołają, a nie powtarzają**. Makefile jest cienką
warstwą, nie drugim źródłem prawdy.

**PHPStan chodzi na poziomie `max` od startu projektu.** Zamiast obniżać poziom
globalnie stosuje się punktowe, uzasadnione `@phpstan-ignore`. Analiza obejmuje
`src`, `tests`, `lang` i `examples`.

## Testy

```bash
make test-unit                             # klasy
make test-functional                       # przebiegi użytkownika
make test ARGS='--filter TreeStateTest'    # zawężenie idzie tą samą drogą
```

**Przebieg funkcjonalny** to nazwana sekwencja klawiszy przez `ScreenFixture` —
komplet ekranów i **prawdziwych modułów** bez systemu plików, terminala
i Imagicka — z asercjami w punktach kontrolnych. Start aplikacji i zmiana
rozmiaru okna idą dodatkowo przez `GameLoop` ze `ScriptedTerminal`, bo taktu bez
pętli sprawdzić się nie da.

Katalog `tests/Functional/` jest **spisem zachowań**, a nie zbiorem skutków
ubocznych kolejnych kroków: **brak przebiegu w spisie jest luką do uzupełnienia,
a nie stanem naturalnym**.

**Żaden test nie wywołuje prawdziwego `ssh`, `sftp`, `kubectl` ani `docker`** —
i to jest kryterium, nie wygoda. Porty tych rzeczy mają atrapy w
`tests/Support/`.

**Złote klatki** (`tests/Golden/`) to serializacja prymitywów klatek
`ScenarioFactory`, porównywana niezależnie od renderera. Odnawia je **wyłącznie**
`./bin/render-bench --golden-save`, po przeczytaniu różnicy.

## Podgląd wejścia terminala

```bash
make probe         # ./bin/terminal-probe
make probe-xterm   # to samo w XTermie z zasobami trybu graficznego
```

Na starcie pokazuje wykryty tryb renderowania, a potem wypisuje nazwę klawisza
i jego bajty, po jednym wierszu na zdarzenie. **Zdarzenia myszy widać tak
samo** — i to jest jedyny sposób, żeby sprawdzić, czy niepełna sekwencja czeka
na resztę, czy rozsypuje się na osobne znaki. Wyjście: `q` albo `Ctrl`+`C`.

## Pomiar

Pomiar ma **własne wejścia** i **własny dokument**: osie, wzorce, progi regresji
i spis scenariuszy opisuje [`docs/pomiary/README.md`](../../pomiary/README.md).
Tutaj tylko to, co dotyczy kolejności pracy.

```bash
make bench          # tor sixelowy
make bench-window   # tor okienkowy (OpenGL, okno ukryte)
make bench-text     # tor tekstowy (ANSI)
make bench-loop     # takt pętli — wejście, stan, złożenie klatki, bez renderera
make bench-xterm    # pod prawdziwym XTermem — jedyna droga do --transfer
```

**Kiedy się mierzy:** gdy krok dotyka **ścieżki klatki** — rysowania, prymitywów,
kwantyzacji, kodowania albo taktu pętli. Krok, który dokłada komendę, kwerendę
albo pozycję ustawień, nie ma czego mierzyć i nie udaje, że ma.

**Zanim zmierzysz — poproś o zwolnienie maszyny i poczekaj na potwierdzenie.**
Cele pomiarowe nie mają bariery technicznej; mają tę regułę.

**Luka, którą warto znać:** taktu modułu **nie mierzy żadna z czterech osi**.
Sixelowa, okienkowa i tekstowa mierzą rysowanie, a `--loop` mierzy pętlę
**bez modułów**. Koszt rozmowy z demonem, rejestrem i klastrem rozlicza się dziś
rozumowaniem, nie liczbą — to jest dług pomiarowy projektu, nazwany w kroku 61.

## Budowa dystrybucji

```bash
make build
```

Wynikiem są **dwie rzeczy w katalogu `build/`**: archiwum
`light-manager-<wersja>.phar` (wersja z pola `version` w `composer.json`) oraz
katalog `assets/` **obok** niego. Archiwum niesie `src/`, `lang/`,
`bin/light-manager` i zależności bez deweloperskich, z autoloaderem z mapy klas;
`tests/`, `docs/`, `examples/` i narzędzia repozytorium do niego nie wchodzą.
Budowa kończy się sprawdzeniem, że wynik się ładuje.

**Zasoby leżą obok archiwum z powodu, który warto znać**: silnik `GL\Audio` jest
rozszerzeniem C i pliku spod `phar://` nie przeczyta. W zbudowanej aplikacji
utwór dopisuje się więc do playlisty **ścieżką bezwzględną**.

Sprzątanie: `make clean` usuwa `build/` i wytwory narzędzi, `make dist-clean`
dokłada `vendor/`. Żaden nie tyka `docs/pomiary/` ani konfiguracji w `HOME`.

## Changelog i plan

Krok ukończony dopisuje się do [`CHANGELOG.md`](../../../CHANGELOG.md) wedle
[konwencji changelogu](../../konwencja-changelogu.md) — o ile dał użytkownikowi
cokolwiek widocznego. Numeracja idzie `faza.krok.poprawka`, a **nazwy wydań
i numerów nie wymyśla się samodzielnie**.

Statusy kroków, graf zależności i lista domykająca krok stoją
w [`docs/plans/00-index.md`](../../plans/00-index.md), rozdział „Śledzenie
postępu".

## Dokąd dalej

- [7. Jak czytać dziennik decyzji](07-dziennik-jak-czytac.md)
- [5. Pułapki](05-pulapki.md) — zanim zmierzysz się z pracą tłową
