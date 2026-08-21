# 6. Ustawienia i konfiguracja

> Podręcznik użytkownika, część 6 z 7. [Spis](README.md) ·
> [English](../../en/manual/06-settings.md)

## Ekran ustawień (`F2`)

`F2` otwiera ustawienia w miejscu listy plików. W górnym pasie stoi **położenie
pliku konfiguracyjnego** — jedyna rzecz, której nie da się z tego ekranu
wyczytać.

Zakładek jest tyle, ile wnosi ta wersja: dwie rdzeniowe (**Wygląd**,
**Grafika**), **Zasoby**, spis **Moduły** i po jednej na każdy moduł, który
wnosi własne ustawienia.

Kursor zaczyna **na pasku zakładek**: `←`/`→` przełączają wtedy zakładkę,
a `↓` wchodzi w pozycje. Na pozycji `←`/`→` zmieniają wartość, `↑`/`↓` chodzą po
liście, `Esc` wraca do plików. Zakładka dłuższa od okna przewija się —
`PgUp`/`PgDn` skaczą o stronę, `Home`/`End` na pierwszą i ostatnią pozycję,
a pasek zakładek zostaje przy tym nieruchomy, bo jest jedynym wskaźnikiem tego,
gdzie stoisz.

Pozycja **tekstowa** zachowuje się inaczej: `Enter` wchodzi w nią i zatwierdza
wpisaną wartość, `Esc` porzuca zmianę. Wartość niezgodna z wymaganiami pozycji
**nie nadpisuje poprzedniej** — powód staje w pasku stanu.

Pod pozycjami zakładek rdzenia stoi przycisk **Przywróć ustawienia domyślne**.
`Enter` na nim **nie kasuje niczego od razu**: otwiera pytanie, w którym
odpowiedź startuje na „Nie". To jedyne miejsce w aplikacji, w którym pomyłka
kosztuje dane — i jedyne, które pyta.

**Każda zmiana działa natychmiast** i od razu ląduje w pliku, więc przeżywa
nawet zabicie procesu sygnałem. Dwa wyjątki, o których ekran mówi wprost:
**przełącznik modułu** i **moduł otwierany przy starcie** działają po ponownym
uruchomieniu, bo mapa skrótów i lista zakładek powstają raz.

## Ustawienia rdzenia

<!-- spis:ustawienia:rdzen -->
| Zakładka | Pozycja | Wartości | Domyślnie |
|---|---|---|---|
| Wygląd | Język | Automatyczny, Polski, English | Automatyczny (`auto`) |
| Wygląd | Motyw | grafit, nordyk, papier, indygo | grafit |
| Wygląd | Mysz | tak / nie | **tak** |
| Grafika | Wygładzanie tekstu | tak / nie | nie |
| Grafika | Wygładzanie obrysów | tak / nie | **tak** |
| Grafika | Kolory palety Sixela | 16, 32, 64, 128 | 64 |
| Grafika | Kolumny okna (tryb okienkowy) | 80, 100, 120, 140, 160, 200 | 100 |
| Grafika | Wiersze okna (tryb okienkowy) | 24, 30, 40, 50, 60 | 30 |
| Zasoby | Pamięć na wynik pracy w tle | 64, 256, 1024, 4096, 16384 KiB | 1024 |
| Zasoby | Prace w tle naraz | 1, 2, 4, 8, 16 | 8 |
| Moduły | Moduł otwierany przy starcie | identyfikatory modułów z oknem | `browser` |
| Moduły | *(każdy moduł)* | włączony / wyłączony | włączony |
<!-- /spis -->

Rozmiar okna wolno przy tym ustawić **spoza listy** — przeciągnięciem rogu
w trybie `--window`; przyjmowane jest wszystko od 20×5 do 1000×400 komórek,
a strzałka z wartości spoza listy idzie do najbliższego przystanku.

**Paleta poniżej 64 kolorów**: kwantyzator poświęca wtedy odcień obwódki na
rzecz liczniejszych pikseli tekstu i panele znikają z ekranu, zostawiając same
nawiasy narożne. Ustawienie jest dostępne, ale aplikacja o tym ostrzega.

## Ustawienia modułów

### Przeglądarka plików

<!-- spis:ustawienia:browser -->
| Pozycja | Wartości | Domyślnie |
|---|---|---|
| Pokazuj wpisy ukryte | tak / nie | nie |
| Podział na dwa panele | tak / nie | nie |
| Panele obok siebie | tak / nie | **tak** |
| Kolumny szczegółów (data, prawa) | tak / nie | **tak** |
| Nazwy kolumn nad listą | tak / nie | nie |
| Poziomy drzewa (Ctrl+T) | 2, 3, 4, 5, 6, 8, 12, ∞ | 8 |
| Pytaj przed usunięciem | tak / nie | **tak** |
| Usuwaj do kosza (F8, Delete) | tak / nie | **tak** |
| Katalog kosza (pusty: systemowy) | tekst | *(puste)* |
| Głębokość stosu cofnięć (F3) | 5, 10, 20, 50, 100 | 20 |
| Szerokość lewego panelu (%) | 20–80 | 50 |
<!-- /spis -->

### Opis pliku

<!-- spis:ustawienia:file-info -->
| Pozycja | Wartości | Domyślnie |
|---|---|---|
| Limit czasu polecenia (s) | 1, 2, 5, 10 | 2 |
| Dodatkowe argumenty | tekst | *(puste)* |
| Zapis czasu | absolute, relative | absolute |
| Pokazuj i-węzeł i dowiązania | tak / nie | nie |
| Suma kontrolna sha256 | tak / nie | nie |
| Limit rozmiaru sumy (MiB) | 16, 64, 256, 1024 | 256 |
| Zajętość katalogu na dysku (du) | tak / nie | nie |
| Limit czasu pracy w tle (s) | 5, 15, 30, 60 | 15 |
| Podgląd treści plików tekstowych | tak / nie | **tak** |
| Numery wierszy w podglądzie | tak / nie | nie |
| Zawijanie wierszy w podglądzie | tak / nie | **tak** |
| Szerokość panelu opisu (%) | 20–80 | 50 |
<!-- /spis -->

### Dźwięk

<!-- spis:ustawienia:audio -->
| Pozycja | Wartości | Domyślnie |
|---|---|---|
| Po utworze | list, once, repeat | list |
| Głośność (%) | 0–100 co 10 | 50 |
| Graj od uruchomienia | tak / nie | nie |
| Efekty specjalne | tak / nie | **tak** |
| Głośność efektów (%) | 0–100 co 10 | 70 |
| Szerokość panelu efektów (%) | 20–80 | 50 |
<!-- /spis -->

### Sesja zdalna

<!-- spis:ustawienia:ssh -->
| Pozycja | Wartości | Domyślnie |
|---|---|---|
| Limit czasu połączenia (s) | 5, 10, 15, 20, 30, 60 | 10 |
| Sposób uwierzytelnienia | agent, key, password | agent |
| Zapamiętuj odciski nowych hostów | tak / nie | **tak** |
| Pokazuj wpisy ukryte | tak / nie | nie |
<!-- /spis -->

### Docker

<!-- spis:ustawienia:docker -->
| Pozycja | Wartości | Domyślnie |
|---|---|---|
| Wierszy logu w pamięci | 500, 1000, 2000, 5000, 10000 | 2000 |
| Szerokość listy (%) | 20–80 | 50 |
<!-- /spis -->

### Kubernetes

<!-- spis:ustawienia:k8s -->
| Pozycja | Wartości | Domyślnie |
|---|---|---|
| Limit czasu wywołania (s) | 2, 5, 10, 30, 60 | 10 |
| Odświeżanie listy (s) | 10, 30, 60, 300 | 30 |
| Wierszy logu w pamięci | 500, 1000, 2000, 5000 | 1000 |
| Limit czekania na budowę (s) | 60, 300, 600, 1800 | 600 |
| Szerokość drzewa zasobów (%) | 20–80 | 40 |
<!-- /spis -->

### Książka adresowa

<!-- spis:ustawienia:address-book -->
| Pozycja | Wartości | Domyślnie |
|---|---|---|
| Kolejność spisu | added, name | added |
<!-- /spis -->

## Pliki na dysku

Wszystko mieszka w **`~/.light-manager/`**, a katalog powstaje **dopiero przy
pierwszym zapisie** — sam start aplikacji niczego na dysku nie tworzy.

| Plik | Co trzyma | Kiedy powstaje |
|---|---|---|
| `settings.json` | ustawienia rdzenia i modułów | przy pierwszej zmianie ustawienia |
| `state.json` | książka adresowa i sekcje modułów (wybrane środowisko, klaster, katalogi zdalne) | przy pierwszym zapisie któregokolwiek modułu |
| `audio.json` | playlista i przypisania dźwięków do zdarzeń | przy pierwszym dopisaniu utworu |
| `history` | dwadzieścia ostatnich wierszy okna komend | przy pierwszym wywołaniu komendy |
| `ssh.json` | **zapis ze starszej wersji** — przenosi się do `state.json` i zostaje nietknięty | — |

`state.json` ma prawa **`0600`**, bo trzyma tokeny rejestrów. Zasłonięcie
sekretu na ekranie chroni przed spojrzeniem, nie przed odczytem pliku —
**szyfrowania nie ma i aplikacja go nie udaje**.

## `settings.json`

```json
{
    "language": "auto",
    "theme": "grafit",
    "startupModule": "browser",
    "textAntialias": false,
    "strokeAntialias": true,
    "paletteColors": 64,
    "windowColumns": 100,
    "windowRows": 30,
    "backgroundOutputKib": 1024,
    "backgroundJobs": 8,
    "mouse": true,
    "modules": {
        "browser": { "enabled": true, "showHidden": false, "split": false },
        "file-info": { "enabled": true, "timeout": 2, "textPreview": true },
        "audio": { "enabled": true, "mode": "list", "volume": 50 },
        "ssh": { "enabled": true, "auth": "agent", "timeout": 10 },
        "docker": { "enabled": true, "logLines": 2000 },
        "k8s": { "enabled": true, "timeoutSeconds": 10 },
        "address-book": { "enabled": true, "order": "added" }
    }
}
```

Podobiekt `modules` dopisuje się dopiero wtedy, gdy któreś ustawienie modułu
zostanie ruszone, a **ustawienia modułu nieznanego zostają nietknięte** — moduł
wyłączony albo usunięty z listy odzyska swoją konfigurację, gdy wróci.

Ręczna edycja jest możliwa, ale **plik jest czytany raz, przy starcie**. Zasady
odczytu:

| Sytuacja | Co się dzieje |
|---|---|
| Brak pliku | wartości domyślne, bez słowa — to normalny stan pierwszego uruchomienia |
| Plik nieczytelny albo niepoprawny JSON | wartości domyślne i ostrzeżenie; **aplikacja nie nadpisuje pliku, którego nie zrozumiała** |
| Nieznany klucz | pomijany po cichu — plik z nowszej wersji nie ma prawa straszyć |
| Znany klucz z wartością spoza zakresu | wartość domyślna dla tego klucza, reszta pliku zostaje, plus ostrzeżenie z nazwą pozycji |
| `startupModule` bez pokrycia w rejestrze | start z przeglądarką i powód w pasku stanu |

Zapis idzie przez plik tymczasowy i zmianę nazwy w tym samym katalogu, więc
przerwany zapis zostawia **poprzednią, poprawną wersję** zamiast obciętego
JSON-a.

## Język interfejsu

Aplikacja mówi po polsku albo po angielsku. Domyślne **Automatyczny** bierze
język ze środowiska — sprawdza `LC_ALL`, `LC_MESSAGES` i `LANG`, w tej
kolejności, i przyjmuje pierwszą wartość z rozpoznawalnym kodem (`pl_PL.UTF-8`
i `pl` znaczą to samo). Gdy żadna nic nie mówi, zostaje angielski.

Wybór zapisany w ustawieniach jest **mocniejszy od środowiska** i działa
natychmiast, bez restartu. To samo robi komenda `core.language <kod>`.

Komunikaty samych wyjątków są techniczne i zawsze po angielsku: pisze się je dla
osoby czytającej ślad stosu. To, co widzi użytkownik — także przy nieudanym
starcie — przechodzi przez katalog napisów.

## Okno komend i kwerendy

`F12` otwiera pas z polem wpisywania, a nad nim listę podpowiedzi. Czynność
wywołuje się **po nazwie**, zamiast szukać dla niej wolnego klawisza.

Komendy nazywają się z przestrzenią właściciela: rdzeń wnosi `core.*`, a każdy
moduł wyłącznie `<id modułu>.*`. Przedrostka pilnuje rejestr, więc kolizja
między modułami jest niemożliwa z konstrukcji.

Przy pustym polu lista pokazuje **najpierw historię**, a pod nią komplet komend
— powtórzenie ostatniego wywołania nie wymaga osobnego klawisza, a ten, kto nazw
nie zna, widzi je wszystkie od razu.

Argumenty rozdziela spacja, a wartość ze spacją bierze się w cudzysłów
(`core.theme "moj motyw"`). Brak wymaganego argumentu, nadmiarowa wartość
i nieznana nazwa **zostawiają okno otwarte** wraz z wpisanym wierszem — powód
staje w pasku stanu, więc literówki nie trzeba przepisywać od nowa.

**`Tab` przy pustym wierszu przełącza okno na kwerendy** — pytania, na które
aplikacja odpowiada o samej sobie: co jest zaznaczone, jakie prace idą w tle,
jakie ma moduły, co gra, jakie kontenery widzi demon. Kwerenda **czyta i nie
zmienia**, więc żadna z nich nie może niczego zepsuć. `Alt`+`C` w oknie kwerend
kopiuje całą odpowiedź.

Spis komend i kwerend jest zawsze w samym oknie — i to on jest źródłem prawdy,
bo powstaje z tego samego rejestru, którym aplikacja je wykonuje.

## Dokąd dalej

- [7. Scenariusze](07-scenariusze.md) — osiem dróg od początku do końca
