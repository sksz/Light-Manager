# 1. Środowisko

> Onboarding, przystanek 1 z 5 · **5 minut**. [Spis](README.md) ·
> [English](../../en/onboarding/01-environment.md)

## Co robisz

```bash
git clone <adres-repozytorium> lm
cd lm
make check-env
make install
```

`make` bez argumentów wypisuje spis wszystkich wejść do procesów projektu —
i jest to jedyny spis, który musisz znać na tym etapie. **Każdy proces tego
projektu ma cel `make`**; jeśli szukasz polecenia, którego tam nie ma, to
prawie na pewno szukasz źle.

## Co zobaczysz

`make check-env` dzieli wymogi na cztery grupy i **to podział jest tu
informacją**, nie sam wynik:

```
Required:      brak którejkolwiek → aplikacja nie ruszy
Recommended:   brak → aplikacja ruszy inaczej, niż wygląda na opisach
Optional:      brak → zniknie część funkcji, reszta działa
Not checkable: jedna rzecz, której make sprawdzić nie może
```

Ostatnia grupa mówi wprost, czego nie wie: **czy sam terminal umie Sixel**.
Odpowiedź na to pytanie wymaga interaktywnej sesji w trybie surowym, a `make`
takiej nie ma. Od tego jest `make probe` — i przyda się na przystanku drugim.

## Mapa środowiska — co jest opcjonalne i co się stanie bez tego

**Brak rzeczy opcjonalnej jest tu degradacją, nie awarią.** To jest zdanie,
którego nowa osoba nie ma skąd znać, a które przesądza, czy uzna aplikację za
zepsutą. Aplikacja startuje, mówi w pasku stanu, czego zabrakło, i działa dalej
bez tej części.

| Składnik | Wymagany? | Co się dzieje bez niego |
|---|---|---|
| PHP `^8.3` | **tak** | aplikacja nie startuje |
| `ext-imagick` | **tak** | aplikacja nie startuje (sprawdzenie jest w `bin/light-manager`, przed czymkolwiek innym) |
| `ext-pcntl` | **tak** | aplikacja nie startuje |
| `stty` | **tak** | terminala nie da się przełączyć w tryb surowy — stąd Linux albo macOS, nie Windows |
| Composer 2.x | **tak** | nie ma czym zainstalować zależności |
| ImageMagick z koderem `SIXEL` | zalecany | **tor tekstowy**: zamiast obrazu widzisz znaki |
| Terminal umiejący Sixel | zalecany | jak wyżej |
| `ext-glfw` | opcjonalny | brak trybu `--window`, a moduł dźwięku odpowiada zdaniem o niedostępności |
| Klient OpenSSH | opcjonalny | moduł sesji zdalnej **znika ze spisu wraz z powodem** |
| `ext-curl` | opcjonalny | moduł Dockera znika ze spisu wraz z powodem |
| `kubectl` | opcjonalny | moduł Kubernetesa jest, ale nie ma z kim rozmawiać |
| `ext-intl` | opcjonalny | gorsze sortowanie i formatowanie liczb |
| `xterm` | opcjonalny | nie zadziała `make run-xterm` ani `make bench-xterm` |

Trzy z tych braków zobaczysz w aplikacji jako **moduł nieobecny wraz z podanym
powodem**. To jest zachowanie zamierzone: moduł, który nie ma z czym rozmawiać,
znika ze spisu, zamiast zostawać w nim i odmawiać przy każdym kliknięciu.

## Gdy `make install` się wywraca

Jedna usterka środowiskowa zdarza się na tyle często, że ma własny cel: Composer
kończący się **naruszeniem ochrony pamięci** przy załadowanym `imagick`. Wtedy:

```bash
make install-safe COMPOSER_INI_SCAN_DIR=/ścieżka/do/conf.d-bez-imagick
```

Szerzej — [podręcznik, „Gdy coś nie działa”](../podrecznik/02-instalacja.md#gdy-coś-nie-działa).

## Skąd wiesz, że skończyłeś

`make check-env` kończy się zdaniem **`Environment is ready.`** — albo zgłasza
braki, a ty **umiesz nazwać każdy z nich** jako opcjonalny wraz z jego skutkiem
z tabeli powyżej. Brak w grupie `Required` jest jedynym, który zatrzymuje
ścieżkę; wszystkie pozostałe przepuszczają cię dalej.

Dalej: [2. Uruchomienie](02-uruchomienie.md).
