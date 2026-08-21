# 8. Procesy projektu (od kroku 39)

> Rozdział 8 dokumentu źródłowego. Spis rozdziałów: [docs/architecture.md](../architecture.md).

Wejściem do każdego procesu jest **cel `make`**. `make` bez argumentów wypisuje
spis wszystkich celów z opisami; cel, którego w tym spisie nie ma, nie istnieje.

| Proces | Wejście | Co się pod tym kryje |
|---|---|---|
| Sprawdzenie środowiska | `make check-env` | Powłoka, **przed** instalacją zależności: PHP `^8.3`, `ext-imagick`, `ext-pcntl`, `stty`, Composer 2.x jako wymogi twarde; koder `SIXEL` jako ostrzeżenie (bez niego tryb tekstowy); `glfw`, `intl`, `xterm` jako informacja |
| Instalacja | `make install` | `composer install`; cel plikowy — powtórzony nie robi nic. Obejście SIGSEGV Composera: `make install-safe COMPOSER_INI_SCAN_DIR=…` |
| Jakość | `make qa` | `cs-check` → `stan` → `test`, **stop na pierwszym błędzie**. `make qa-full` przechodzi całość ze zbiorczym podsumowaniem; osobno `make cs`, `cs-check`, `stan` |
| Testy | `make test` | Obie grupy naraz; `make test-unit` i `make test-functional` — grupy z `phpunit.xml.dist` osobno. Zawężenie: `make test ARGS='--filter …'` |
| Pokrycie | `make coverage` | Raport HTML do `build/coverage/`; bez Xdebuga albo PCOV-u cel **czytelnie odmawia** — żadne z nich nie jest wymaganiem projektu |
| Pomiar wydajności | `make bench`, `bench-window`, `bench-text`, `bench-loop`, `bench-xterm` | `bin/render-bench` i jego cztery tory; `bench-xterm` idzie przez `bin/run-render-bench.sh`, bo faza przesyłu wymaga prawdziwego XTerma. **Poza bramką jakości** — krok 16 odrzucił bramkę wydajności i to nie zmieniło się |
| Budowa | `make build` | `bin/build-phar`: `build/light-manager-<wersja>.phar` (wersja z `composer.json`) plus `assets/` **obok** archiwum. Kończy się sprawdzeniem, że wynik się ładuje |
| Uruchomienie | `make run`, `run-window`, `run-xterm` | `bin/light-manager`, ten sam z `--window`, `bin/run.sh` (XTerm z zasobami trybu graficznego) |
| Podgląd wejścia terminala | `make probe`, `probe-xterm` | `bin/terminal-probe` — **jedyna** droga do sprawdzenia odpowiedzi DA1, bo wymaga interaktywnego terminala w trybie surowym |
| Klaster do sprawdzeń modułu `k8s` | `make minikube-start`, `minikube-stop`, `minikube-status` | `minikube` (od kroku 52). Klaster jest **obciążeniem maszyny, nie częścią aplikacji**: moduł działa bez niego, a przed każdym `make bench*` węzeł ma być **zatrzymany** (reguła 17). `minikube-status` wolno wołać zawsze — nie podnosi klastra ani go nie kładzie, a kod wyjścia narzędzia (7 przy zatrzymanym, 85 przy nieistniejącym) jest tu odpowiedzią, nie awarią |
| Sprzątanie | `make clean`, `dist-clean` | `clean` usuwa wytwory narzędzi i `build/`; `dist-clean` dokłada `vendor/`. Żaden nie tyka `docs/pomiary/` (wzorce są w repozytorium celowo, D33) ani konfiguracji w `HOME` |

## Reguła pierwszeństwa (D63)

Reguła ma dwie połowy i **druga jest ważniejsza**, bo to ona zapobiega
faktycznej stracie pracy:

1. **Wejściem do procesu jest cel `make`.** Bramki, instalacji, budowy, testów
   i pomiaru nie składa się z pamięci.
2. **Narzędzie repozytorium ma pierwszeństwo przed doraźnym zastępnikiem.**
   Pomiar wydajności robi `bin/render-bench` — z jego fazami, wzorcami
   i metryczką środowiska — a nie własna pętla `microtime()`; wejście terminala
   sprawdza `bin/terminal-probe`, a nie `read` w powłoce; scenariusz pomiarowy
   dokłada się **do** `ScenarioFactory`, a nie obok niej. Doraźny zastępnik nie
   jest szybszy — jest **niepodpięty do niczego**: nie porówna się z wzorcem,
   nie zostawi śladu i nie odpowie następnym razem.

**Granica, bez której reguła staje się dogmatem:** zawężenie przebiegu wolno
wołać wprost — pojedynczy test filtrem PHPUnita, jedna oś `bin/render-bench`,
`composer` przy pracy nad zależnościami. Cel `make` jest wejściem do **procesu**,
a nie kagańcem na narzędzie. Zakazane jest dorabianie **równoległej drogi** do
procesu, który wejście już ma.

**Makefile nie jest drugim źródłem prawdy.** Cele wołają `composer`,
`bin/render-bench`, `bin/run*.sh` i `bin/build-phar`; konfiguracji tych narzędzi
nie powtarzają własnymi słowami. Definicje poleceń jakości mieszkają
w `composer.json`, zasoby XTerma — w skryptach `bin/run*.sh`, podział testów —
w `phpunit.xml.dist`.

**Pomiar ma ponadto regułę własną, starszą od tego rozdziału** (reguła 17
Skilla): przed uruchomieniem celu pomiarowego i przed oglądaniem klatki
w prawdziwym terminalu poproś użytkownika o zwolnienie mocy hosta i **poczekaj
na potwierdzenie**. Cele `bench*` nie mają bariery technicznej — mają regułę.

## Co budowa wkłada do archiwum, a czego nie

`bin/build-phar` bierze `src/`, `lang/`, `bin/light-manager`, `composer.json`,
`composer.lock` oraz `vendor/` zainstalowane **bez zależności deweloperskich**,
z autoloaderem z mapy klas. Nie bierze `tests/`, `docs/` ani narzędzi
repozytorium: `bin/render-bench` liczy ścieżki do `docs/pomiary/`
i `tests/Golden/`, a `bin/install-desktop-entry` potrzebuje `realpath()` pliku
wykonywalnego — spod `phar://` żadnego z tych miejsc nie ma.

Dwie rzeczy, które z postaci archiwum wynikają i o których trzeba wiedzieć:

- **Katalogi napisów czytają się spod `phar://` bez zmian** — `Catalog` robi
  `is_file()` i `require`, a ścieżki liczą się z `dirname(__DIR__, …)`. Dotyczy
  to również katalogów modułów (`ModuleInterface::translations()`).
- **`assets/` leżą obok archiwum, nie w nim** — silnik `GL\Audio` jest
  rozszerzeniem C i pliku spod `phar://` nie przeczyta. W zbudowanej aplikacji
  utwór wskazuje się **ścieżką bezwzględną** w ustawieniach modułu audio;
  ścieżka względna liczy się od korzenia projektu, którego dystrybucja nie ma.

## Dokumentacja jest pilnowana tak samo, jak reguły warstw (od kroku 66)

**Spis w dokumentacji jest kopią stanu kodu, a nie opisem zamiaru** — i jest
pilnowany maszynowo. Klawisze, komendy, kwerendy, moduły i pozycje ustawień
stoją w podręczniku i w przewodniku jako tabele objęte znacznikiem
`<!-- spis:… -->`; zestaw `tests/Documentation/` porównuje je z `KeyBinding`ami
i z rejestrami, **w obu językach naraz**, a przy okazji sprawdza odnośniki,
kotwice, wskazania na `examples/` i kształt pary językowej.

Wynika z tego reguła utrzymania, obowiązująca każdy krok planu: **zmiana
klawisza, komendy, kwerendy, modułu albo pozycji ustawień jest niekompletna,
dopóki bramka nie jest zielona.** To ta sama reguła, którą projekt stosuje do
napisów od kroku 15, i ten sam powód: rozjazd jest niewidoczny, bo wiersz, do
którego nic nie dopisano, wygląda tak samo poprawnie jak wiersz, do którego nie
było czego dopisać.

| Proces | Wejście |
|---|---|
| Sprawdzenie samej dokumentacji | `make docs-check` |
| Bramka jakości (woła to samo razem z resztą testów) | `make qa` |

Trzy granice tego pilnowania są nazwane wprost, żeby nikt nie szukał w nich
dziury: **składni diagramów nikt nie renderuje** (test pilnuje zdania
opisowego i domknięcia bloku), **tłumaczy człowiek** (test wykrywa rozjazd
kształtu pary językowej, nie jakość przekładu), a **dziennik decyzji i plany
sprawdzane są wyłącznie co do odnośników** — to dokumenty historyczne, których
się nie przepisuje.

Jak dopisać własny spis pod pilnowanie i co zrobić, gdy test zgodności się
czerwieni: [`docs/pl/przewodnik/08-spisy.md`](../pl/przewodnik/08-spisy.md).
