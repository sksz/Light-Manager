# Light Manager

Menadżer plików w terminalu napisany w PHP. Cała klatka ekranu jest budowana
jako jeden obraz przez Imagick i wypychana do terminala protokołem Sixel, w
architekturze pętli głównej znanej z gier. Od kroku 34 aplikacja ma także
**tryb okienkowy** (`--window`): natywne okno z kontekstem OpenGL przez
rozszerzenie [PHP-GLFW](https://phpgl.net), bez dotykania terminala.

## Wymagania

- PHP `^8.3` (weryfikowane: 8.3.11)
- Rozszerzenia PHP: `imagick`, `pcntl`
- Opcjonalnie rozszerzenie `glfw` ([PHP-GLFW](https://phpgl.net), instalowane
  ze źródeł — nie ma go w PECL; weryfikowane: 2.2.0 z GLFW 3.3.8 pod X11) —
  wyłącznie dla trybu okienkowego `--window`; tryby terminalowe działają
  bez niego
- Zewnętrzne polecenie `stty` — stąd założenie **Linux/macOS**; Windows nie
  jest wspierany
- Interaktywny terminal na standardowym wejściu — uruchomienie z potoku lub
  przekierowania z pliku kończy się czytelnym błędem
- ImageMagick z wkompilowanym koderem `SIXEL` — bez niego aplikacja startuje,
  ale zejdzie do trybu tekstowego (fallback, krok 07 planu)
- Terminal obsługujący Sixel (np. XTerm z `-ti vt340`, WezTerm, foot, mlterm) —
  wykrywanie odbywa się w runtime. **gnome-terminal odpada**: VTE nie ma
  Sixela od wersji 0.75.90 (zobacz „Znane ograniczenia”)
- Composer 2.x

## Instalacja

```bash
make check-env   # czy ta maszyna udźwignie projekt — działa przed instalacją
make install     # composer install; powtórzony nie robi nic
```

`make check-env` rozróżnia trzy rodzaje wymogów: **twarde** (PHP, `imagick`,
`pcntl`, `stty`, Composer) kończą się kodem błędu, brak kodera `SIXEL` jest
**ostrzeżeniem** (aplikacja zejdzie do trybu tekstowego), a `glfw`, `intl`
i `xterm` — informacją. Jednego sprawdzić nie potrafi i mówi to wprost:
obsługi Sixela przez sam terminal, bo odpowiedź DA1 wymaga interaktywnej sesji
w trybie surowym — od tego jest `make probe`.

Jeżeli Composer wywraca się naruszeniem ochrony pamięci, zobacz
[Znane ograniczenie środowiska](#znane-ograniczenie-środowiska) i `make install-safe`.

`make` bez argumentów wypisuje spis wszystkich wejść do procesów projektu;
pełny opis: [docs/architecture.md](docs/architecture.md), rozdz. 8.

## Uruchomienie

```bash
make run          # to samo co ./bin/light-manager
make run-window   # tryb okienkowy (--window)
make run-xterm    # XTerm z zasobami trybu graficznego (./bin/run.sh)
```

lub wprost:

```bash
./bin/light-manager
```

lub równoważnie:

```bash
php bin/light-manager
```

lub z XTerm:
```bash
xterm -ti vt340 -fa 'DejaVu Sans Mono' -fs 11 -geometry 100x30 \
  -xrm 'XTerm*maxGraphicSize: 4000x4000' \
  -xrm 'XTerm*disallowedWindowOps: 1,2,3,4,5,6,7,8,9,11,13,18,19,20,21,GetSelection,SetSelection,SetWinLines,SetXprop' \
  -e bash -c 'cd /home/sksz/Projects/light_manager && ./bin/light-manager'
```

lub poprzez alias:
```bash
./bin/run.sh
```

Aplikacja przechodzi na osobny ekran, rysuje klatkę w stałym takcie i czeka na
wejście. Wyjście: klawisz `F10` albo Ctrl+C — w obu przypadkach terminal wraca
do stanu sprzed uruchomienia.

### Tryb okienkowy (od kroku 34)

```bash
./bin/light-manager --window
```

Zamiast rysować w terminalu aplikacja otwiera natywne okno z kontekstem
OpenGL 3.3 core (wymaga rozszerzenia `glfw` — bez niego start kończy się
czytelnym błędem, a **bez flagi rozszerzenie nie jest potrzebne w ogóle**).
Terminal, z którego padło polecenie, pozostaje nietknięty: bez trybu
surowego, bez osobnego ekranu, bez jednej sekwencji sterującej na STDOUT.
Klawiatura działa tym samym słownikiem co w terminalu (strzałki, `F1`–`F12`,
`Ctrl`+litera, `F10` kończy; przycisk zamknięcia okna działa jak Ctrl+C),
a przeciągnięcie rogu okna zmienia siatkę od następnej klatki.

Rozmiar okna ustawia się na ekranie ustawień (pozycje „Kolumny/Wiersze okna”,
domyślnie 100×30 komórek); komórkę wyznaczają metryki systemowego fontu
o stałej szerokości.

**Okno pamięta, jak je ustawiono** (od kroku 37): rozmiar nadany
przeciągnięciem rogu albo maksymalizacją zapisuje się do tych samych dwóch
ustawień, więc następny start zastaje okno takim, jakim je zostawiono. Zapis
następuje pół sekundy po ostatniej zmianie — przeciąganie rogu nie zapisuje
pliku po drodze ani razu — a zmianę porzuconą tuż przed wyjściem dopisuje samo
zamknięcie aplikacji. Rozmiar mierzy się **w komórkach**, więc zmiana fontu
zmieni okno w pikselach, ale zostawi siatkę — i z tego samego powodu okno wraca
czasem o kilkanaście pikseli niższe, niż je zostawiono: resztka, która nie
tworzyła pełnego wiersza, nie ma czego pamiętać.

**Pełny ekran**: `F11` albo komenda `core.fullscreen` w oknie komend. Wyjście
wraca dokładnie do poprzedniego rozmiaru i położenia, a rozmiar narzucony
pełnym ekranem **nie** trafia do ustawień. Obie drogi istnieją wyłącznie
w trybie okienkowym — w terminalu `F11` nie robi nic i nie pojawia się
w spisie klawiszy.

**Ikona na pasku zadań** wymaga jednorazowego założenia wpisu pulpitu:

```bash
./bin/install-desktop-entry
```

Skrypt rysuje ikonę z ról **włączonego motywu** (cztery rozmiary
w `~/.local/share/icons/hicolor/`) i zakłada wpis
`~/.local/share/applications/light-manager.desktop`. Droga jest okrężna, bo
prostej nie ma: rozszerzenie PHP-GLFW nie wystawia `glfwSetWindowIcon`, więc
okno przedstawia się pulpitowi klasą `WM_CLASS`, a ikonę bierze pulpit z wpisu.
Niektóre środowiska graficzne odświeżają spis programów dopiero po ponownym
zalogowaniu.

Zakładka „Aplikacja” w oknie pomocy pokazuje w tym trybie **gęstość
wyświetlacza** (`glfwGetWindowContentScale`). Wartość jest tam po to, żeby ją
zobaczyć: aplikacja niczym jej nie przelicza — patrz „Znane ograniczenia”.

Od kroku 35 okno pokazuje **całą aplikację**: prymitywy rysowane są wprost
wywołaniami OpenGL przez API wektorowe PHP-GLFW, bez Imagicka w ścieżce
klatki. Ta sama treść, ten sam układ i te same role motywu co w trybie
sixelowym — z dwiema różnicami na korzyść okna: kolory idą w pełnej głębi
(nie ma kwantyzacji do palety Sixela), a klatka kosztuje ułamek tego, co
kosztuje w terminalu.

### Sterowanie

| Klawisz | Działanie |
|---|---|
| `↑` / `↓` | zmiana zaznaczenia |
| `Enter` / `→` | wejście do katalogu (na pliku `Enter` nie robi nic) |
| `Backspace` / `←` | katalog wyżej |
| `.` | pokaż lub ukryj wpisy ukryte (ustawienie trwałe, dotyczy obu paneli) |
| `/` | zawężenie listy fragmentem nazwy — pole filtra przy dolnej krawędzi |
| `Spacja` | zaznaczenie wpisu pod kursorem i przejście wiersz niżej |
| `*` | odwrócenie zaznaczenia na widocznej liście |
| `Tab` | przejście do drugiego panelu — tylko przy włączonym podziale |
| `Ctrl`+`T` | panel jako **drzewo** albo z powrotem jako lista |
| `F4` | zmiana nazwy wpisu pod kursorem |
| `F5` | skopiowanie wpisu do wskazanego katalogu |
| `F6` | przeniesienie wpisu do wskazanego katalogu |
| `F7` | nowy katalog w katalogu panelu czynnego |
| `F8` albo `Delete` | usunięcie zaznaczonych wpisów — **nieodwracalnie** |
| `F1` | ekran pomocy — pełna lista klawiszy |
| `F2` | ekran ustawień |
| `F9` | menu kontekstowe — co da się zrobić z zaznaczonym wpisem |
| `F11` | pełny ekran — **tylko w trybie okienkowym** (`--window`) |
| `F12` | okno komend |
| `Ctrl`+litera | okno modułu — `Ctrl+B` przeglądarka plików, `Ctrl+D` opis zaznaczonego pliku |
| `Esc` | zdjęcie filtra, a potem zaznaczenia; powrót do modułu domyślnego z każdego ekranu |
| `F10` | wyjście (działa na każdym ekranie) |

`Enter` jest w całej aplikacji klawiszem **zatwierdzania**: na katalogu wchodzi
do środka, w polu tekstowym zatwierdza wartość, w oknie komend uruchamia
komendę. Na pliku nie ma czego zatwierdzić — opis pliku przeprowadził się do
modułu `file-info` i ma własny skrót `Ctrl+D`.

Wyjście wisi na klawiszu funkcyjnym, a nie na literze, i przestało to być
decyzją na przyszłość: aplikacja **ma** pole tekstowe (okno komend), a klawisz
kończący pracę nie może być znakiem, który użytkownik właśnie wpisuje. Skutek uboczny jest wart tyle, co
sama zmiana — **żadna litera nie jest zarezerwowana**, więc cały alfabet zostaje
wolny dla komend i skrótów modułów.

Pasek stanu u dołu mówi o **miejscu, na którym stoi kursor**: najpierw jego
nazwa i klawisze (`Panel lewy: ↑↓ zaznaczenie · Enter katalog · Backspace
wyżej`), potem klawisze całego ekranu, a na końcu te, które działają wszędzie —
wraz ze skrótami modułów. Przeniesienie ogniska zmienia stopkę **w tej samej
klatce**, a przy zbyt wąskim oknie pozycje ustępują od końca: pierwsze znikają
klawisze globalne, ostatni `F1`, bo bez niego znika droga do pełnego spisu.
Kiedy podpowiedzi nie mieszczą się w jednym wierszu, pasek rośnie do dwóch —
ale nigdy nie zasłania komunikatu i nigdy nie urywa pozycji w połowie słowa.

Pełny spis mieszka dalej na ekranie pomocy (`F1`) i nie jest tam przepisany
ręcznie: jedno i drugie powstaje z tych samych wiązań, które klawisze obsługują.

### Zaznaczenie wielokrotne

Spacja zaznacza wpis pod kursorem i schodzi wiersz niżej, więc ciąg plików
zaznacza się jednym palcem; `*` odwraca zaznaczenie na tym, co widać. Zaznaczone
wiersze mają własny znacznik w kolumnie przed nazwą **i** własny kolor napisu —
widać je więc i wtedy, gdy kursor stoi gdzie indziej, i wtedy, gdy stoi na nich.
Pas ścieżki podsumowuje zbiór: `• 12 z 340 · 4,1 GB`. Katalogi wolno zaznaczyć
na równi z plikami, ale ich rozmiaru nikt nie zna — suma je pomija i mówi o tym
wprost (`bez 2 kat.`).

**Pusty zbiór znaczy „wpis pod kursorem”**, a nie „nic”: bez zaznaczenia każda
czynność działa dokładnie tak, jak przed tą funkcją. Zbiór przeżywa zawężenie
filtrem — wpis, którego filtr nie pokazuje, nadal do niego należy i nadal
policzy się w podsumowaniu — a ginie razem z katalogiem, jak filtr. `Esc`
zdejmuje warstwy po kolei: najpierw filtr, potem zaznaczenie.

Zaznaczenie jest własnością **listy**: panel przełączony na drzewo (`Ctrl`+`T`)
ani go nie pokazuje, ani na nim nie działa — a powrót do listy zastaje zbiór
takim, jaki był. Każdy panel ma przy tym własny zbiór, bo dwa panele otwarte na
tym samym katalogu mają prawo zaznaczać co innego.

Moduł opisu pliku (`Ctrl`+`D`) czyta zbiór z kontekstu sesji i mówi o nim
zamiast o ścieżce — `Zaznaczono 12 wpisów · razem 4,1 GB` — a opis pod spodem
zostaje opisem wpisu pod kursorem.

### Operacje na plikach

Pięć czynności zmienia zawartość dysku. Kopiowanie, przeniesienie i usunięcie
działają na **zaznaczonych wpisach**, a przy pustym zbiorze na wpisie pod
kursorem; zmiana nazwy i nowy katalog zostają jednowpisowe, bo nazwa jest jedna
z definicji. W drzewie każda z nich dotyczy węzła pod kursorem. `F4` otwiera okno z **nazwą bieżącą** w polu,
`F7` z pustym, a `Enter` zatwierdza; `Esc` odmawia i nie dotyka dysku. Nazwa jest
nazwą, nie ścieżką: ukośnik w niej jest błędem, a nie zaproszeniem do utworzenia
katalogu piętro niżej. Nazwa zajęta **nie zamyka okna** — jest co poprawić.

`F8` (albo `Delete`) pyta, zanim usunie, i pyta w wariancie groźnym — czerwoną
oprawą, z ogniskiem na odmowie, więc przytrzymany `Enter` trafia w „nie”. Przy
zaznaczeniu wielokrotnym pytanie mówi **liczbą** („Usunąć 12 zaznaczonych wpisów
bezpowrotnie?”), a nie nazwą pierwszego z nich. Pytanie można wyłączyć pozycją
„Pytaj przed usunięciem” w ustawieniach modułu.

Katalog usuwa się **wraz z zawartością**, ale nie po cichu: aplikacja najpierw
liczy, ile wpisów zniknie, i podaje tę liczbę w pytaniu. Przy dużym drzewie
liczenie i usuwanie **nie zatrzymują aplikacji** — idą po kawałku na klatkę,
a okno pokazuje nazwę, licznik „N z M” i pasek postępu. `Esc` przerywa i mówi
uczciwie, ile już zniknęło: usunięcia połowy drzewa nie da się cofnąć. Kosz
i cofanie przyniesie osobny krok planu.

Po operacji zaznaczone zostaje **to, czego nie dotknęła**: wpisy, które zniknęły,
wypadają ze zbioru, a pominięte przy kolizji i nieudane zostają zaznaczone — to
jedyna droga, którą widać, co się nie udało.

Skutek widać **w tej samej klatce w obu panelach**, jeśli oba patrzą na ten sam
katalog — a panel, któremu usunięto katalog pod nogami, wchodzi do najbliższego
czytelnego wyżej. Zmiana zrobiona spoza aplikacji nadal wymaga wejścia do
katalogu na nowo: aplikacja odświeża listę po **własnej** operacji.

#### Kopiowanie i przenoszenie

`F5` kopiuje, `F6` przenosi — obydwa otwierają okno z **katalogiem docelowym**
wypełnionym katalogiem drugiego panelu; `Enter` zatwierdza, a ścieżkę wolno
poprawić albo wpisać własną, także względną (liczy się od katalogu, w którym
stoisz). Okno działa również przy wyłączonym podziale, więc cel nigdy nie jest
niespodzianką.

Przeniesienie w obrębie **jednego systemu plików** dzieje się natychmiast —
kosztuje jedną zmianę nazwy, niezależnie od tego, ile plików jest w katalogu.
Między systemami plików nie ma innej drogi niż skopiowanie i usunięcie źródła,
a wtedy obowiązuje reguła bez odstępstwa: **źródło znika dopiero po zapisaniu
celu w całości**.

Praca idzie po kawałku na klatkę, jak usuwanie: aplikacja najpierw liczy, ile
bajtów i wpisów przybędzie, a potem kopiuje — pasek postępu mówi więc prawdę od
pierwszego bajtu, a licznik podaje rozmiar i „który plik z ilu”. `Esc` przerywa;
**plik zapisany w połowie znika**, bo plik wyglądający na gotowy jest gorszy niż
brak pliku.

Kiedy w katalogu docelowym coś już stoi pod tą nazwą, aplikacja pyta — sześcioma
odpowiedziami: nadpisz, nadpisz wszystkie, pomiń, pomiń wszystkie, zapisz pod inną
nazwą, przerwij. Katalog o tej samej nazwie **nie jest kolizją**, tylko scaleniem:
wejście do niego niczego nie niszczy. Dowiązanie symboliczne kopiuje się **jako
dowiązanie**, a kopia dostaje prawa dostępu i czas zmiany oryginału; właściciela
nie — na to trzeba uprawnień, których aplikacja nie ma i mieć nie powinna.

Skopiować katalogu do jego własnego wnętrza ani do katalogu, w którym już leży,
nie można — aplikacja odmawia i mówi dlaczego.

Wszystkie czynności poza usunięciem mają drugie wejście — komendy
`browser.rename <nazwa>`, `browser.mkdir <nazwa>`, `browser.copy <ścieżka>`
i `browser.move <ścieżka>` w oknie komend (`F12`); `browser.delete [nazwa]` też
tam jest, ale pytanie stawia zawsze. Nazwa ze spacją idzie w cudzysłowach,
a komenda bez argumentu otwiera to samo okno, co klawisz.

Przeglądarkę można podzielić na **dwa panele** — dwa katalogi, dwa kursory,
niezależne od siebie. Włącza się to w ustawieniach modułu („Podział na dwa
panele”), a `Tab` przenosi ognisko z jednego panelu do drugiego. Panel czynny
poznaje się po akcencie w obwódce i po ścieżce, którą pokazuje górny pas klatki;
katalog panelu nieczynnego widać w etykiecie jego ramki. Panele stają domyślnie
obok siebie — pozycja „Panele obok siebie” przestawia je jeden nad drugi.

Podział **nie powstaje w oknie węższym niż 72 kolumny** (a przy układzie
poziomym — niższym niż 14 wierszy w strefie listy). Poniżej progu widać jeden
panel i klatka wygląda dokładnie tak, jak przed włączeniem. To ta sama zasada,
którą kierował się pas podglądu, dopóki przeglądarka go miała: dwa panele w wąskim
oknie **mieszczą się**
arytmetycznie, ale nazw plików nie da się w nich przeczytać.

Podział jest sprawą modułu, a nie okna: `F1`, `F2` i skrót modułu zastępują cały
ekran razem z podziałem, bo widoczny ekran jest zawsze jeden.

### Drzewo katalogów

`Ctrl`+`T` zamienia panel z ogniskiem w **drzewo** — i z powrotem w listę.
Widok należy do panelu, więc przy podziale wolno mieć drzewo po jednej stronie
i listę po drugiej; przy jednym panelu drzewo zastępuje listę w całości.

| Klawisz | W drzewie |
|---|---|
| `→` | rozwinięcie gałęzi; na gałęzi już rozwiniętej — zejście do pierwszego dziecka |
| `←` | zwinięcie gałęzi; na zwiniętej — skok do rodzica; na pierwszym poziomie — katalog wyżej |
| `Enter` | katalog spod kursora staje się katalogiem panelu |
| `Backspace` | katalog wyżej — znaczy to samo, co w liście |

Gałąź czyta się z dysku **dopiero przy rozwinięciu**, więc drzewo nietknięte nie
kosztuje ani jednego dodatkowego odczytu, a rozwinięcie jednej gałęzi kosztuje
tyle, co wejście do katalogu. Raz przeczytana gałąź zostaje w pamięci: wejście
katalog niżej i powrót przywraca drzewo w tym samym kształcie, bez sięgania na
dysk. Cena tej zamiany jest jedna i warto ją znać — **drzewo pokazuje to, co
przeczytało**, a nie to, co w tej sekundzie leży na dysku.

Ile poziomów wolno rozwinąć, rozstrzyga pozycja „Poziomy drzewa (Ctrl+T)”
w ustawieniach modułu; wartość `∞` znaczy „bez limitu”. Przy limicie osiągniętym
`→` melduje się zdaniem w pasku stanu, zamiast nie robić nic.

Miniatury **przeglądarka nie pokazuje**: pasa podglądu pod panelami nie ma, a jego
wiersze dostaje lista plików. Miniaturę zaznaczonego pliku — wraz z wymiarami,
formatem i podglądem treści tekstowej — pokazuje moduł opisu pliku (`Ctrl`+`D`),
i pokazuje ją w całym panelu, a nie w czterech wierszach nad paskiem stanu. Dwa
miejsca robiące to samo znaczyły dwa razy ten sam odczyt obrazu w klatce.

### Okno komend

`F12` otwiera nad bieżącym ekranem pas z polem wpisywania, a nad nim listę
podpowiedzi. Wzorzec pochodzi z wiersza poleceń `vima`: czynność wywołuje się
**po nazwie**, zamiast szukać dla niej wolnego klawisza.

| Klawisz | Działanie |
|---|---|
| znaki | wpisywanie nazwy; lista filtruje się w locie |
| `Tab` | uzupełnia najdłuższy wspólny przedrostek pasujących nazw |
| `↑` / `↓` | wybór pozycji z listy |
| `Enter` | uruchamia wpisany wiersz albo wskazaną pozycję |
| `←` / `→`, `Home`, `End`, `Delete`, `Backspace` | poprawianie wiersza |
| `Esc` albo `F12` | zamknięcie okna |

Przy pustym polu lista pokazuje **najpierw historię**, a pod nią komplet komend
— dzięki temu powtórzenie ostatniego wywołania nie wymaga osobnego klawisza,
a ten, kto nazw nie zna, widzi je wszystkie od razu.

Komendy nazywają się z przestrzenią właściciela: rdzeń wnosi `core.*`, a każdy
moduł — wyłącznie `<id modułu>.*`. Przedrostka pilnuje rejestr, więc kolizja
między modułami jest niemożliwa z konstrukcji. Dziś dostępne są:

| Komenda | Argument | Działanie |
|---|---|---|
| `core.help` | — | otwiera ekran pomocy |
| `core.settings` | — | otwiera ekran ustawień |
| `core.theme` | nazwa motywu | ustawia motyw graficzny |
| `core.language` | kod języka | ustawia język interfejsu |
| `core.dump` | ścieżka (opcjonalna) | zapisuje następną klatkę: prymitywy i obraz |
| `core.fullscreen` | — | pełny ekran — **wyłącznie w trybie okienkowym** |
| `core.quit` | — | kończy pracę |
| `browser.jump` | ścieżka | przechodzi do wskazanego katalogu |
| `browser.open` | — | wchodzi do **zaznaczonego** katalogu |
| `browser.hidden` | — | pokazuje albo ukrywa wpisy ukryte |
| `browser.tree` | — | przełącza panel między drzewem a listą |
| `file-info.show` | — | otwiera opis zaznaczonego wpisu |
| `audio.music` | — | włącza muzykę albo ją zatrzymuje |
| `audio.volume` | 0–100 co 10 | ustawia głośność muzyki |

`core.fullscreen` jest pierwszą komendą rdzenia, której **obecność zależy od
trybu**: w torze terminalowym nie ma jej w spisie wcale, bo pełny ekran nic tam
nie znaczy, a okno komend pokazuje to, co działa tu i teraz.

Cztery ostatnie nazywają czynności, które przeglądarka i moduł opisu miały
dotąd wyłącznie pod klawiszem (`Enter`, `.`, `Ctrl`+`T`, `Ctrl`+`D`). Nazwa nie
jest drugą implementacją: obie drogi kończą się w tym samym miejscu, a `browser.open`
działa też w widoku drzewa — wchodzi wtedy do węzła pod kursorem.

`browser.jump` podpowiada katalogi **z dysku**, w miarę wpisywania: to
pierwsza w projekcie komenda z podpowiedziami liczonymi na żądanie, a nie
policzonymi przy starcie. Ścieżka względna liczy się od bieżącego miejsca.

Argumenty rozdziela spacja, a wartość ze spacją bierze się w cudzysłów
(`core.theme "moj motyw"`). Brak wymaganego argumentu, nadmiarowa wartość
i nieznana nazwa **zostawiają okno otwarte** wraz z wpisanym wierszem — powód
staje w pasku stanu, więc literówki nie trzeba przepisywać od nowa.

Historia trzyma dwadzieścia ostatnich wierszy wraz z argumentami i przeżywa
ponowne uruchomienie: leży w `~/.light-manager/history`, osobno od konfiguracji,
bo jest śladem pracy, a nie ustawieniem.

### Menu kontekstowe

`F9` otwiera pośrodku klatki listę czynności **dla tego, co jest zaznaczone** —
bez pamiętania klawisza i bez pisania nazwy. Na katalogu widać wejście do niego,
opis wpisu i pięć operacji na plikach (zmiana nazwy, kopiowanie, przeniesienie,
nowy katalog, usunięcie); na pliku znika samo wejście, bo dotyczy wyłącznie
katalogów.

Pozycja, która potrzebuje okna, otwiera je zamiast pytać o nazwę w wierszu:
usunięcie stawia to samo pytanie, co `F8`, zmiana nazwy — to samo pole, co `F4`,
a kopiowanie — to samo okno ze ścieżką, co `F5`. Menu pokazuje przy tym
czynności zmieniające **zawartość miejsca**, a nie
sposób jego oglądania — dlatego są w nim operacje na plikach, a nie ma
przełącznika wpisów ukrytych ani drzewa.

| Klawisz | Działanie |
|---|---|
| `↑` / `↓` | wybór pozycji |
| `Enter` | wykonuje wybraną czynność |
| `Esc` albo `F9` | zamknięcie menu |

Menu jest **drugim wejściem do rejestru komend, a nie drugim zbiorem czynności**:
pozycje pochodzą z tego samego rejestru, co lista w oknie komend, a wybór
pozycji robi dokładnie to, co komenda o tej nazwie. Widać to w wierszu — nazwa
komendy stoi po lewej, jej opis po prawej — i wynika z tego jedna praktyczna
rzecz: komenda dopisana przez moduł pojawia się w menu sama, o ile zadeklaruje,
jakiego zaznaczenia dotyczy.

Te same trzy operacje mają nazwy w oknie komend i **argument jest w nich
opcjonalny**: `browser.rename` bez nazwy otwiera pole z nazwą bieżącą,
`browser.mkdir` bez nazwy — pole puste, a `browser.delete` pyta o zgodę, zanim
cokolwiek usunie. Z nazwą w wierszu (`browser.rename umowa.txt`) działają od
razu, bez okna.

Do menu trafiają wyłącznie czynności **na zaznaczeniu**. `browser.hidden`
i `browser.tree` są w rejestrze, ale w menu ich nie ma: dotyczą panelu, a nie
wpisu, na którym stoi kursor. Gdy dla zaznaczenia nie pasuje ani jedna czynność
— na przykład w pustym katalogu — menu **nie otwiera się wcale** i mówi to
zdaniem w pasku stanu, zamiast prosić o zamknięcie pustego okna.

### Ustawienia

`F2` otwiera ekran ustawień w miejscu listy plików. Pasek stanu u dołu zostaje,
a w górnym pasie ekran ustawień stawia **położenie pliku konfiguracyjnego** —
jedynej rzeczy, której nie da się z niego wyczytać.

Zakładek jest tyle, ile ich wnosi ta wersja: dwie rdzeniowe (**Wygląd**,
**Grafika**), spis **Moduły** i po jednej na każdy moduł, który wnosi własne
ustawienia. Pierwszym wierszem spisu **Moduły** jest **moduł otwierany przy
starcie** — jego wartości to identyfikatory modułów, które naprawdę wnoszą okno,
a lista powstaje przy starcie, nie w kodzie. Kursor zaczyna na pasku zakładek: `←` / `→` przełączają wtedy
zakładkę, a `↓` wchodzi w pozycje. Na pozycji `←` / `→` zmieniają wartość,
`↑` / `↓` chodzą po liście, `Esc` wraca do plików.

Zakładka **dłuższa od okna przewija się**: `PageUp` / `PageDown` skaczą o stronę,
`Home` / `End` na pierwszą i ostatnią pozycję, a przy krawędzi pojawia się suwak.
Pasek zakładek zostaje przy tym nieruchomy, bo jest jedynym wskaźnikiem tego,
gdzie się stoi; każda zakładka pamięta swoje położenie osobno. Do wersji, w której
to powstało, pozycja, która nie mieściła się w oknie, **znikała bez śladu**.

Pozycja tekstowa (np. argumenty polecenia w module `file-info`) zachowuje się
inaczej: `Enter` wchodzi w nią i zatwierdza wpisaną wartość, `Esc` porzuca
zmianę. Wartość niezgodna z wymaganiami pozycji **nie nadpisuje poprzedniej** —
powód staje w pasku stanu.

Pod pozycjami zakładek rdzenia stoi przycisk **Przywróć ustawienia domyślne** —
cofa wszystkie ustawienia naraz, więc po zabłądzeniu w motywach i palecie nie
trzeba kasować pliku konfiguracyjnego. `Enter` na nim **nie kasuje niczego od
razu**: otwiera pytanie, w którym odpowiedź startuje na „Nie”, a `Esc` znaczy
to samo co odmowa. To jedyne miejsce w aplikacji, w którym pomyłka kosztuje
dane — i jedyne, które pyta.

| Zakładka | Pozycja | Wartości | Domyślnie |
|---|---|---|---|
| Wygląd | Język | Automatyczny, Polski, English | Automatyczny |
| Wygląd | Motyw | Grafit, Nordyk, Papier, Indygo | Grafit |
| Grafika | Wygładzanie tekstu | tak / nie | nie |
| Grafika | Wygładzanie obrysów | tak / nie | tak |
| Grafika | Kolory palety Sixela | 16, 32, 64, 128 | 64 |
| Moduły | Moduł otwierany przy starcie | identyfikatory modułów z oknem | `browser` |
| Moduły | *(moduł)* | włączony / wyłączony | włączony |
| Przeglądarka plików | Pokazuj wpisy ukryte | tak / nie | nie |
| Przeglądarka plików | Podział na dwa panele | tak / nie | nie |
| Przeglądarka plików | Panele obok siebie | tak / nie | tak |
| Przeglądarka plików | Kolumny szczegółów (data, prawa) | tak / nie | **tak** |
| Przeglądarka plików | Nazwy kolumn nad listą | tak / nie | nie |
| Opis pliku | Limit czasu polecenia (s) | 1, 2, 5, 10 | 2 |
| Opis pliku | Dodatkowe argumenty | tekst | *(puste)* |
| Opis pliku | Zapis czasu | absolute, relative | absolute |
| Opis pliku | Pokazuj i-węzeł i dowiązania | tak / nie | nie |
| Opis pliku | Suma kontrolna sha256 | tak / nie | **nie** |
| Opis pliku | Limit rozmiaru sumy (MiB) | 16, 64, 256, 1024 | 256 |
| Opis pliku | Zajętość katalogu na dysku (du) | tak / nie | **nie** |
| Opis pliku | Limit czasu pracy w tle (s) | 5, 15, 30, 60 | 15 |
| Opis pliku | Podgląd treści plików tekstowych | tak / nie | **tak** |
| Opis pliku | Numery wierszy w podglądzie | tak / nie | nie |
| Opis pliku | Zawijanie wierszy w podglądzie | tak / nie | **tak** |
| Dźwięk | Utwór | tekst (ścieżka) | plik z `assets/audio/` |
| Dźwięk | Głośność (%) | 0–100 co 10 | 50 |
| Dźwięk | Odtwarzanie w kółko | tak / nie | **tak** |

Każda zmiana działa natychmiast — motyw i jakość rysowania widać w następnej
klatce, bez restartu — i od razu ląduje w pliku, więc przeżywa nawet zabicie
procesu sygnałem. Dwa wyjątki, o których ekran mówi wprost: przełącznik modułu
i moduł otwierany przy starcie działają **po ponownym uruchomieniu**, bo mapa
skrótów, lista ekranów i lista zakładek powstają raz.

**Paleta poniżej 64 kolorów**: kwantyzator poświęca wtedy odcień obwódki na
rzecz liczniejszych pikseli tekstu i panele znikają z ekranu, zostawiając same
nawiasy narożne. Ustawienie jest dostępne, ale aplikacja o tym ostrzega.

**Wartości domyślne wygładzania i palety są tymczasowe.** Pochodzą z doraźnych
pomiarów kroku 13, robionych przez podmienianie stałych w kodzie. Krok 16 planu
daje powtarzalne narzędzie pomiarowe i dopiero po nim zostaną potwierdzone albo
poprawione.

### Moduły

Funkcję dopisuje się do aplikacji **modułem**, nie zmianą w rdzeniu. Moduł ma
pięć punktów zaczepienia i deklaruje tylko te, których naprawdę potrzebuje:

1. własne okno wraz ze skrótem `Ctrl`+litera. Okno to **trzy strefy klatki**:
   górny pas, środkowy panel i pas podglądu — moduł zamawia te, które ma czym
   wypełnić, a rdzeń rysuje ich oprawę i pasek stanu (pasa podglądu nie zamawia
   dziś żaden moduł: opis pliku rysuje miniaturę w swoim własnym panelu),
2. własną zakładkę w oknie konfiguracji, opisaną danymi — rdzeń ją rysuje,
   prowadzi po niej kursor i zapisuje wartości,
3. własną zakładkę w oknie pomocy: część automatyczną rdzeń składa z deklaracji
   (skrót, klawisze okna, pozycje ustawień), a moduł dokłada własne wiersze,
4. własne napisy w `src/Module/<Nazwa>/lang/`, scalane z katalogiem rdzenia,
5. własne komendy w oknie komend.

Dopisanie modułu ze wszystkimi pięcioma punktami kosztuje **jedną zmianę
w rdzeniu**: dopisanie klasy do listy w `Presentation\Cli\Bootstrap`.

Wbudowane są dziś trzy:

- **Przeglądarka plików** (`browser`, `Ctrl+B`) — sam menadżer plików. Nie jest
  rdzeniem z doklejonymi modułami, tylko modułem jak każdy inny: cała domena
  katalogu, nawigacja, ekran i komenda `browser.jump` leżą w `src/Module/Browser/`,
  a w rdzeniu nie została ani jedna klasa wiedząca, czym jest plik.

  Lista ma **cztery kolumny**: nazwa, rozmiar, data zmiany i prawa dostępu.
  W wąskim oknie — na przykład w panelu podziału — kolumny **ustępują po kolei**:
  najpierw prawa, potem data, potem rozmiar, a nazwa nie ustępuje nigdy. Kolumna,
  która nie mieści się w całości, **znika w całości**: przycięta data (`2026-08-…`)
  nie mówi nic, a zabiera znaki nazwie, która by je wykorzystała.

  Listę można **zawęzić fragmentem nazwy** — `/` otwiera pole filtra przy dolnej
  krawędzi, a lista zwęża się przy każdej literze, w tej samej klatce.
  Dopasowany fragment jest **podświetlony**. Dopasowanie to podciąg bez
  rozróżniania wielkości liter (także poza ASCII: `Ł` znajduje `ł`); wzorców ani
  wyrażeń regularnych nie ma. Strzałki w otwartym polu chodzą po zawężonej
  liście, `Enter` zostawia ją zawężoną, a `Esc` zdejmuje filtr i wraca do wpisu
  sprzed jego otwarcia. Filtr dotyczy **panelu z ogniskiem**, widać go
  znacznikiem w pasie ścieżki i znika przy zmianie katalogu.

  Panel pokazuje listę albo **drzewo** (`Ctrl`+`T`, opisane wyżej) — w drzewie
  widać katalogi i pliki naraz, z wcięciem i prowadnicami gałęzi.
- **Opis pliku** (`file-info`, `Ctrl+D`) — **pełny obraz stanu zaznaczonego
  wpisu**, także katalogu: cztery zwijane sekcje po lewej, a po prawej miniatura
  albo **treść pliku tekstowego**.

  | Sekcja | Co pokazuje |
  |---|---|
  | Tożsamość | nazwa, rodzaj z `lstat`, opis od polecenia `file`, cel dowiązania wraz z informacją, czy istnieje, liczba wpisów katalogu |
  | Rozmiar | rozmiar w jednostkach i co do bajta, bloki i-węzła, zajętość katalogu na dysku (`du`), suma kontrolna `sha256` |
  | Uprawnienia | prawa `rwx` i ósemkowo, właściciel, grupa, opcjonalnie i-węzeł i liczba dowiązań |
  | Czasy | zmiana treści, zmiana i-węzła, odczyt — datą albo jako „ile temu” |

  Sekcje zwija się `Enter`em, a `↑`/`↓` chodzą po ich nagłówkach. **Suma kontrolna
  liczy się dopiero po naciśnięciu `s`** i domyślnie jest wyłączona: czyta cały
  plik, więc nie ma prawa startować sama przy przewijaniu listy. Liczy się po
  kawałku na klatkę, pokazuje prawdziwy postęp paskiem i przerywa się natychmiast,
  gdy zaznaczenie się zmieni. Powyżej ustawionego limitu rozmiaru nie startuje
  i mówi dlaczego.

  **Zajętość katalogu na dysku liczy się po naciśnięciu `d`** i też domyślnie jest
  wyłączona. Stoi za nią polecenie `du` uruchomione w tle i doglądane między
  klatkami — pętla w tym czasie nie czeka ani chwili. Wiersz powstaje **tylko dla
  katalogu**: dla zwykłego pliku tę samą liczbę podają stojące obok bloki i-węzła,
  odczytane z `lstat` bez uruchamiania czegokolwiek. Postępu `du` nie zna, więc
  pasek nie udaje, że go zna — jego wypełnienie wędruje tam i z powrotem. Praca
  ma własny limit czasu, osobny i hojniejszy od limitu polecenia `file`, bo
  sekundy spędzone w tle nie kosztują ani jednej klatki. Po zamknięciu aplikacji
  — także `Ctrl+C` — nie zostaje po niej ani jeden proces.

  **Prawy panel pokazuje treść pliku tekstowego** — tam, gdzie wcześniej stał
  napis „(brak podglądu)”. **`Tab` przenosi kursor między opisem a podglądem** —
  panel z ogniskiem poznaje się po akcencie w obwódce. W podglądzie strzałki
  przewijają o **linijkę**, `PgUp`/`PgDn` o panel, `Home` wraca na początek pliku,
  a `End` skacze na jego koniec; w opisie strzałki chodzą po sekcjach, a
  `Home`/`End` skaczą na pierwszą i ostatnią. `Alt`+`Z` przełącza zawijanie
  wierszy niezależnie od ogniska.

  Linijka to **linijka panelu, nie wiersz pliku**, i przy zawijaniu to nie jest
  to samo: strzałka w pliku będącym jedną długą linią przesuwa obraz o jeden
  wiersz ekranu, a `PgDn` o dokładnie tyle linijek, ile było widać. Po `End`
  **numery wierszy znikają** — kotwica staje wtedy po bajcie, a numeru z bajtu
  wyczytać się nie da bez przejścia przez cały plik; `Home` je przywraca.

  Czytany jest **wyłącznie
  widoczny fragment**, więc plik półgigabajtowy otwiera się tak samo szybko, jak
  kilobajtowy i nie zatrzymuje ani jednej klatki; przewinięcie porzuca poprzednie
  wiersze i doczytuje następne, jak w edytorze. Zawijanie łamie **po znaku, a nie
  po słowie**, żeby wcięcia w kodzie zostały wcięciami, i obowiązuje **każdy**
  wiersz: zrzut JSON-a w jednej linijce wypełnia panel od góry do dołu, a `PgDn`
  wchodzi w głąb tego samego wiersza. Zawijanie jest **pozycją w ustawieniach
  modułu** (domyślnie włączone), a `Alt`+`Z` przełącza tę samą pozycję, więc
  wybór przeżywa zamknięcie aplikacji.
  Czy plik jest tekstem, rozstrzyga kaskada: rozszerzenie, potem opis od
  polecenia `file`, a na końcu podejrzenie pierwszych bajtów — dzięki temu
  `README` i `.gitignore` też mają podgląd. Kodowanie rozpoznajemy z nagłówka
  i konwertujemy do UTF-8 — **także UTF-16 i UTF-32**, po znaczniku kolejności
  bajtów albo po wzorcu zer, gdy znacznika nie ma; bajt, którego nie da się
  zdekodować, i znak sterujący dostają widoczny znacznik zamiast psuć klatkę.
  Numery wierszy są **domyślnie
  wyłączone** i włącza się je w ustawieniach modułu, a w wąskim panelu ustępują
  miejsca treści. Podgląd binariów nie powstaje i mówi o tym wprost.
- **Dźwięk** (`audio`, bez skrótu) — muzyka grająca **obok** pracy z plikami.
  Moduł nie wnosi ani ekranu, ani skrótu, ani jednego komponentu: wnosi dwie
  komendy, zakładkę ustawień i zakładkę pomocy. Jest przez to sprawdzianem
  kontraktu modułu z drugiej strony niż przeglądarka — pytaniem, czy kontrakt
  udźwignie moduł, który **nic nie rysuje**.

  Muzykę włącza i zatrzymuje komenda `audio.music`; drugie jej wywołanie
  **pauzuje**, więc trzecie wznawia w tym samym miejscu, a nie zaczyna od nowa.
  `audio.volume <0–100>` zmienia głośność natychmiast, także w trakcie grania,
  i zapisuje ją do konfiguracji; przyjmuje wartości co dziesięć — te same,
  po których chodzi strzałka na zakładce ustawień.

  Utwór wskazuje pozycja „Utwór”: ścieżka względna liczy się od katalogu
  aplikacji, bezwzględna zostaje nietknięta. Domyślnie jest to plik
  z `assets/audio/`. Formaty to **WAV, MP3 i FLAC** — plik MIDI się nie nada
  i mówi o tym wprost, bo silnik audio odtwarza próbki, a nie zapis nutowy.

  **Muzyka nie rusza sama.** Autostartu nie ma i jest to skutek kontraktu
  modułu, a nie przeoczenie: rdzeń nie budzi modułów przy starcie, a dokładanie
  mu takiej zdolności dla jednej funkcji byłoby rozszerzaniem rdzenia dla wygody
  modułu. **Dźwięk gra poza ścieżką klatki** — silnik miksuje we własnym wątku,
  więc pętla główna, renderery i komponenty nie wiedzą, że cokolwiek gra, a koszt
  klatki nie drga w żadnym z trzech torów.

  Bez rozszerzenia `glfw` moduł zachowuje się jak wszystko inne, co od niego
  zależy: aplikacja startuje normalnie, a komendy muzyczne odpowiadają zdaniem
  o niedostępności. Rozszerzenie jest tu **możliwością, nie wymogiem** — i nie
  potrzebuje przy tym okna: silnik audio startuje bez kontekstu OpenGL, więc
  muzyka gra także w obu torach terminalowych.

#### Moduł domyślny

Aplikacja startuje z oknem modułu wskazanego kluczem `startupModule`; domyślnie
jest to przeglądarka. Wskazanie innego uruchamia aplikację z jego oknem jako dnem
— tym, do którego wraca `Esc`.

**Przeglądarka jest modułem ostatniej szansy.** Nie da się jej wyłączyć ani
odrzucić (przy kolizji skrótu odpada ten drugi moduł), a aplikacja wraca do niej
w czterech przypadkach: moduł domyślny jest wyłączony, został odrzucony przy
starcie, nie ma go na liście albo nie wnosi okna. Za każdym razem powód widać
w pasku stanu — bo każdy z nich prowadzi do innej poprawki.

Zasady, które obowiązują moduły:

- **Identyfikator** pasuje do `[a-z][a-z0-9-]*` i jest jeden dla wszystkiego:
  klucz w pliku konfiguracyjnym (`modules.<id>`), przedrostek napisów
  (`module.<id>.`) i przestrzeń nazw komend (`<id>.`).
- **Skrót to `Ctrl` plus litera.** Sześć liter jest zajętych przez terminal:
  `c` i `z` są sygnałami, a `h`, `i`, `j` i `m` przychodzą tym samym bajtem, co
  Backspace, Tab i Enter. Zostaje dwadzieścia.
- **Moduł z zabronioną literą, ze skrótem zajętym przez inny moduł albo
  z powtórzonym identyfikatorem nie zostaje załadowany** — w całości, nie tylko
  jego skrót. Aplikacja startuje, a powód widać w pasku stanu i na zakładce
  „Moduły”. Kolizję łapie test, zanim zobaczy ją użytkownik.
- **Moduły się nie znają.** Moduł dostaje od rdzenia kontekst sesji (ścieżka,
  nazwa zaznaczenia, jego rodzaj) i nic ponadto; do infrastruktury rdzenia sięga
  wyłącznie przez port.
- **Moduł da się wyłączyć** na zakładce „Moduły”. Zmiana zapisuje się od razu,
  ale działa po ponownym uruchomieniu — mapa skrótów i lista zakładek powstają
  raz, przy starcie.

Moduły ładowane z zewnątrz (spoza repozytorium) są **poza zakresem**: kontrakt
ma dojrzeć na modułach wbudowanych, zanim stanie się API dla obcego kodu.

### Język interfejsu

Aplikacja mówi po polsku albo po angielsku. Domyślne ustawienie **Automatyczny**
bierze język ze środowiska — sprawdza `LC_ALL`, `LC_MESSAGES` i `LANG`, w tej
kolejności, i przyjmuje pierwszą wartość z rozpoznawalnym kodem (`pl_PL.UTF-8`
i `pl` znaczą to samo). Gdy żadna nic nie mówi, zostaje angielski.

Wybór zapisany w ustawieniach jest mocniejszy od środowiska i działa
natychmiast, bez restartu. Napisy leżą w `lang/pl.php` i `lang/en.php` —
dopisanie kolejnego języka to nowy plik obok nich i nowa pozycja w
`Application\Dto\Language`.

Komunikaty samych wyjątków są techniczne i zawsze po angielsku: pisze się je dla
osoby czytającej ślad stosu. To, co widzi użytkownik — także przy nieudanym
starcie — przechodzi przez katalog napisów.

### Plik konfiguracyjny

`~/.light-manager/settings.json`. Katalog i plik powstają dopiero przy pierwszej
zmianie ustawienia — sam start aplikacji niczego nie tworzy na dysku.

```json
{
    "language": "auto",
    "theme": "grafit",
    "startupModule": "browser",
    "textAntialias": false,
    "strokeAntialias": true,
    "paletteColors": 64,
    "modules": {
        "browser": { "enabled": true, "showHidden": false },
        "file-info": { "enabled": true, "timeout": 2, "arguments": "",
                       "timeFormat": "absolute", "inode": false,
                       "checksum": false, "checksumLimit": 256,
                       "textPreview": true, "lineNumbers": false }
    }
}
```

Podobiekt `modules` dopisuje się dopiero wtedy, gdy któreś ustawienie modułu
zostanie ruszone. **Ustawienia modułu nieznanego zostają nietknięte** — moduł
wyłączony albo usunięty z listy odzyska swoją konfigurację, gdy wróci.

Ręczna edycja jest możliwa, ale plik jest czytany raz, przy starcie. Zasady
odczytu:

- **Brak pliku** — wartości domyślne, bez słowa. To normalny stan pierwszego
  uruchomienia.
- **Plik nieczytelny albo niepoprawny JSON** — wartości domyślne i ostrzeżenie w
  pasku stanu. Aplikacja startuje i **nie nadpisuje pliku, którego nie
  zrozumiała**; nadpisze go dopiero jawna zmiana ustawienia.
- **Nieznany klucz** — pomijany po cichu (plik z nowszej wersji nie ma prawa
  straszyć).
- **Znany klucz z wartością spoza zakresu** — wartość domyślna dla tego klucza,
  reszta pliku zostaje, plus ostrzeżenie z nazwą pozycji.
- **`startupModule` bez pokrycia w rejestrze** — aplikacja startuje
  z przeglądarką i mówi w pasku stanu, dlaczego. Zakresu tego klucza nie da się
  sprawdzić przy odczycie: znają go dopiero moduły przyjęte w tym uruchomieniu.
- **`showHiddenEntries` z pliku sprzed wersji 0.21** — przepisywany raz do
  `modules.browser.showHidden`, żeby ustawienie przeżyło aktualizację. Ze starego
  miejsca znika przy najbliższym zapisie.

Zapis idzie przez plik tymczasowy i `rename()` w tym samym katalogu, więc
przerwany zapis zostawia poprzednią, poprawną wersję zamiast obciętego JSON-a.

### Zasoby XTerma wymagane w trybie graficznym

Trzy zasoby, każdy z innego powodu — bez nich klatka jest ucięta, pusta albo
przewinięta:

| Zasób | Domyślnie | Dlaczego trzeba zmienić |
|---|---|---|
| `decTerminalID: 340` (albo `-ti vt340`) | `420` | bez tego XTerm nie zgłasza Sixela w odpowiedzi DA1 i aplikacja schodzi do trybu tekstowego |
| `maxGraphicSize: 4000x4000` | `1000x1000` | klatka większa niż limit **nie rysuje się w ogóle**; okno 200×50 już go przekracza |
| `disallowedWindowOps` bez `14` | lista z `14` | XTerm blokuje raport rozmiaru okna (`ESC [ 14 t`), więc aplikacja musi zgadywać rozmiar komórki znakowej |

Ostatni wpis jest celowo węższy niż `allowWindowOps: true`: dopuszcza wyłącznie
raport rozmiaru, a zmiana rozmiaru i pozycji okna oraz raportowanie tytułu
pozostają zablokowane.

Na stałe w `~/.Xresources` (potem `xrdb -merge ~/.Xresources`):

```
XTerm*decTerminalID: 340
XTerm*maxGraphicSize: 4000x4000
XTerm*disallowedWindowOps: 1,2,3,4,5,6,7,8,9,11,13,18,19,20,21,GetSelection,SetSelection,SetWinLines,SetXprop
```

Bez ostatniego zasobu aplikacja nadal działa — przyjmuje wtedy komórkę 6×13 px
(domyślny font XTerma) i rysuje klatkę mniejszą niż okno, zostawiając margines
przy prawej i dolnej krawędzi.

Zmiana rozmiaru okna w trakcie działania jest obsługiwana: następna klatka
rysuje się w nowym rozmiarze, a raport `ESC [ 14 t` jest ponawiany po każdej
zmianie u terminala, który odpowiedział na niego przy starcie.

## Struktura

```
Makefile     wejścia do wszystkich procesów projektu (`make` wypisuje spis)
bin/         skrypty wejściowe CLI (aplikacja, narzędzia diagnostyczne, budowa)
src/         kod aplikacji (PSR-4, namespace LightManager\)
src/Module/  moduły — każdy z własnymi warstwami i własnymi napisami
             (Browser — menadżer plików, FileInfo — opis zaznaczonego pliku)
tests/       testy PHPUnit (namespace LightManager\Tests\)
lang/        katalogi napisów interfejsu (rdzeń)
assets/      zasoby aplikacji (domyślny utwór modułu dźwięku)
docs/        architektura, plany wdrożenia i wzorce pomiarów
build/       wynik `make build` — poza repozytorium (.gitignore)
```

Podział `src/` na warstwy (`Domain`, `Application`, `Infrastructure`,
`Presentation`) wraz z regułą zależności opisuje
[docs/architecture.md](docs/architecture.md).

## Narzędzia deweloperskie

Wszystkie procesy projektu mają wejście w `Makefile` — `make` bez argumentów
wypisuje spis. Reguła, na której to stoi (krok 39): **wejściem do procesu jest
cel `make`, a tam, gdzie projekt ma własne narzędzie, używa się jego zamiast
dorabiać zastępnik doraźnie**. Zawężenie przebiegu wolno wołać wprost —
pojedynczy test filtrem, jedna oś pomiaru, `composer` przy pracy nad
zależnościami.

Podgląd wejścia terminala — sprawdza tryb surowy i rozpoznawanie klawiszy bez
czekania na pętlę główną:

```bash
make probe         # ./bin/terminal-probe
make probe-xterm   # to samo w XTermie z zasobami trybu graficznego
```

Na starcie pokazuje wykryty tryb renderowania (Sixel albo fallback tekstowy),
a potem wypisuje nazwę klawisza i jego bajty, po jednym wierszu na zdarzenie
(sekwencja escape liczy się jako jedno zdarzenie). Wyjście: `F10` albo Ctrl+C —
w obu przypadkach terminal wraca do stanu sprzed uruchomienia.

Bramka jakości i jej części składowe:

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
`test`) — cele `make` je wołają, a nie powtarzają.

Testy dzielą się od kroku 38 na dwie grupy i da się je wywołać osobno:

```bash
make test-unit        # klasy
make test-functional  # przebiegi użytkownika (tests/Functional/)
make test ARGS='--filter TreeStateTest'   # zawężenie idzie tą samą drogą
```

**Przebieg funkcjonalny** to nazwana sekwencja klawiszy przez `ScreenFixture` —
komplet ekranów i prawdziwych modułów bez systemu plików, terminala i Imagicka —
z asercjami w punktach kontrolnych. Start aplikacji i zmiana rozmiaru okna idą
dodatkowo przez `GameLoop` ze `ScriptedTerminal`, bo taktu bez pętli sprawdzić
się nie da. Katalog przebiegów jest **spisem zachowań**: podróż po katalogach,
filtr, dwa panele, okno komend, opis pliku z pracą tłową, podgląd tekstu,
ustawienia z potwierdzeniem, start i zmiana rozmiaru.

### Pomiar wydajności renderowania

`bin/render-bench` mierzy potok renderowania klatki bez uruchamiania aplikacji
i bez edytowania kodu:

```bash
make bench                               # tor sixelowy, konfiguracja domyślna
make bench-window                        # tor okienkowy (OpenGL, okno ukryte)
make bench-text                          # tor tekstowy (ANSI, tryb zapasowy)
make bench-loop                          # takt pętli bez renderera
make bench-xterm                         # pod prawdziwym XTermem — jedyna droga do --transfer
make bench ARGS='--palette=16 --text-aa' # inna konfiguracja, bez ruszania kodu
```

Zawężenie jednej osi wolno wołać wprost — cele opakowują to samo narzędzie:

```bash
./bin/render-bench --help                # pełna lista opcji i scenariuszy
```

**Pomiar wymaga spokojnej maszyny.** Cele `bench*` nie mają bariery
technicznej — mają regułę: przed pomiarem zatrzymaj kompilacje, kontenery
i przeglądarkę. Obciążony host daje rozrzut, przy którym `--save` odmawia
zapisu wzorca.

**Cztery tory, cztery różne pytania.** Sixelowy i okienkowy mierzą potok
rysowania; **tekstowy** (krok 38) domyka parytet, bo tryb zapasowy był jedynym
z trzech tłumaczy słownika prymitywów, o którego koszcie nikt nic nie wiedział;
**takt pętli** (`--loop`) mierzy drogę od klawisza do prymitywów — odczyt
wejścia, aktualizację stanu i złożenie klatki przez `FrameComposer` — czyli to,
co dzieje się **zanim** renderer w ogóle zacznie rysować. Wyniki torów są
nieporównywalne i pilnuje tego podpis konfiguracji.

Oś `--window` mierzy te same scenariusze **rendererem OpenGL** zamiast potoku
Sixela, w oknie ukrytym na czas pomiaru. Fazy są tam inne (rysowanie i zamiana
buforów; kwantyzacji ani bajtów nie ma), a podpis konfiguracji niesie słowo
`window` — dzięki temu `--compare` nie zestawi ze sobą wyników dwóch różnych
torów. `--transfer` i `--png` należą wyłącznie do toru terminalowego i w parze
z `--window` kończą się błędem zamiast cichego pominięcia.

Klatka rozbita jest na trzy fazy — **rysowanie**, **kwantyzację** i **kodowanie
do Sixela** — mierzone osobno, a każdy scenariusz izoluje inny element klatki
(sam tekst, same ramki, zaznaczenie, suwak, miniatura, okienko). Dzięki temu
koszt elementu da się *odjąć*, zamiast zgadywać z sumy.

Dwa scenariusze wyłamują się z tej reguły i robią to celowo. **`background`
rysuje klatkę co do prymitywu równą `chrome-text`, ale przy uruchomionym procesie
potomnym**, doglądanym raz na klatkę tak samo, jak dogląda go aplikacja. Odjęcie
jednego od drugiego daje więc nie koszt elementu interfejsu, lecz cenę pracy
toczącej się obok pętli — a twierdzenie, że praca tłowa nie kosztuje klatki, jest
dzięki temu sprawdzalne, a nie deklarowane. **`columns`** rysuje z kolei tę samą
listę, co `chrome-text`, ale w czterech kolumnach zamiast dwóch — różnica jest
ceną rozdziału szerokości i dwóch dodatkowych napisów w każdym wierszu.
**`text-view`** wypełnia panel treścią pliku o zmiennej długości wierszy: różnica
wobec `chrome-text` jest ceną podglądu tekstu, a osobny scenariusz jest tu
potrzebny dlatego, że wiersze podglądu zmieniają się przy każdym przewinięciu,
więc pamięć podręczna wierszy trafia w nie rzadziej niż w listę plików.
**`highlight`** rysuje tę samą listę, co `columns`, ale z dopasowaniem filtra
w **każdym** wierszu — przypadek najgorszy z możliwych. Rozlicza się go
**w parze z `columns`, nie osobno**: różnica między nimi jest ceną podświetlenia,
a `columns` odpowiada przy okazji na pytanie ważniejsze — czy lista bez filtra
zdrożała. Nie ma prawa.

#### Jak czytać wynik

```
Scenariusz            Rysowanie  Kwantyzacja  Kodowanie     Razem        Rozrzut     Blob
ramki z tekstem     314,7 (77%)   87,5 (21%)   7,7 (2%)  410,8 ms    409,0–475,5  23,1 kB
```

- **mediana**, nie średnia — pojedynczy przebieg zakłócony przez inny proces
  przesuwa średnią i zostaje niewidoczny;
- **procent w nawiasie** to udział fazy w klatce;
- **rozrzut** (min–max) mówi, czy medianie wolno wierzyć; wiersz z „!”
  przekroczył 1,35× i jest oznaczony jako niewiarygodny;
- **Blob** to bajty, które trzeba jeszcze wypchnąć na terminal — konfiguracja
  szybsza w liczeniu, ale dwukrotnie grubsza w zapisie, nie jest szybsza wcale.

Pierwsze przebiegi (domyślnie trzy) są rozgrzewką i nie wchodzą do wyniku:
pierwsza klatka płaci za wybór fontu i pomiar szerokości napisów.

#### Wzorce i porównanie

```bash
./bin/render-bench --save                # zapisz wzorzec do docs/pomiary/
./bin/render-bench --compare             # porównaj z najnowszym wzorcem
```

Przebieg z niestabilnym pomiarem **nie zostanie zapisany** jako wzorzec.
Ograniczenia porównania (przede wszystkim: to samo obciążenie maszyny) opisuje
[docs/pomiary/README.md](docs/pomiary/README.md).

#### Przesył do terminala

Jedyna faza, której nie da się zmierzyć bez prawdziwego terminala — narzędzie
nigdy nie podstawia w jej miejsce zapisu do pliku, bo zmierzyłoby wtedy prędkość
jądra, a nie terminala. Bez terminala mówi wprost, że tej fazy nie zmierzyło.

```bash
./bin/run-render-bench.sh --transfer     # XTerm z zasobami wymaganymi dla Sixela
```

Raportuje rozmiar klatki, czas zapisu, liczbę wywołań `fwrite()` (jeden zapis
rozpada się na kilka), przepustowość oraz **przybliżony** czas do odpowiedzi
terminala na zapytanie DA1 wysłane zaraz po klatce. Ta ostatnia liczba jest
oszacowaniem dolnym: terminal może odpowiedzieć, zanim domaluje obraz.

#### Zrzut klatki do PNG

Liczby nie pokazują wszystkiego — przy 16 i 32 kolorach kwantyzator zjada
obwódki paneli, co jest niewidoczne w czasie ani w rozmiarze bloba:

```bash
./bin/render-bench --png=/tmp/klatka.png --scenario=chrome-text
```

Zrzut powstaje **przed** kwantyzacją, więc pokazuje, co narysował enkoder.
Skutki samej palety ogląda się na terminalu, gdzie naprawdę występują.

#### Regresja wizualna: porównanie zrzutów

Od kroku 38 obraz jest **miarą**, a nie ilustracją: wzorcowe PNG leżą
w `docs/pomiary/wzorce-png/`, a porównanie liczy różniące się piksele (metryka
AE) i przy przekroczeniu progu zapisuje obraz różnicy obok wzorca.

```bash
./bin/render-bench --png-save            # zapisz wzorcowe zrzuty wybranych scenariuszy
./bin/render-bench --png-compare         # porównaj z wzorcami (kod wyjścia 1 przy niezgodności)
./bin/render-bench --png-compare --png-threshold=0.5   # próg w promilach pikseli
./bin/render-bench --window --png-compare              # to samo dla toru okienkowego
```

Tor tekstowy zrzutu w narzędziu nie ma — jego klatka to znaki i atrybuty.
Obraz z niego robi dopiero żywa aplikacja, rasteryzując bufor ANSI.

#### Zrzut klatki z żywej aplikacji

Dwie pomyłki podglądu tekstu z kroku 29 wyszły dopiero na zrzucie prawdziwej
klatki spod XTerma. Komenda `core.dump` daje ten sam dowód bez sprzętu:

```
:core.dump                 # zapisze następną klatkę do katalogu tymczasowego
:core.dump /tmp/klatka     # …albo pod wskazaną nazwą
```

Powstają dwa pliki: `<nazwa>-prymitywy.txt` (co aplikacja kazała narysować)
i `<nazwa>.png` (jak to wyszło). Obraz jest **wierny torowi**: płótno Imagicka
w trybie Sixel, bufor karty w oknie GLFW, rasteryzacja bufora ANSI w trybie
tekstowym. Zapisywana jest **następna** klatka, więc okna komend nie widać na
zrzucie.

#### Złote klatki

Ten sam katalog scenariuszy służy testom: `tests/Golden/<scenariusz>.txt` to
serializacja prymitywów klatki, porównywana niezależnie od renderera.

```bash
./bin/render-bench --golden-save         # odnów złote klatki — PO przeczytaniu różnicy
```

## Budowa

```bash
make build
```

Wynikiem są **dwie rzeczy w katalogu `build/`**: archiwum
`light-manager-<wersja>.phar` (wersja z pola `version` w `composer.json`) oraz
katalog `assets/` **obok** niego. Archiwum niesie `src/`, `lang/`,
`bin/light-manager` i zależności zainstalowane bez deweloperskich, z autoloaderem
z mapy klas; `tests/`, `docs/` i narzędzia repozytorium do niego nie wchodzą.
Budowa kończy się sprawdzeniem, że wynik się ładuje.

```bash
./build/light-manager-0.1.0.phar            # uruchomienie dystrybucji
./build/light-manager-0.1.0.phar --window   # to samo w oknie
```

**Zasoby leżą obok archiwum z powodu, który warto znać**: silnik `GL\Audio` jest
rozszerzeniem C i pliku spod `phar://` nie przeczyta. W zbudowanej aplikacji
utwór wskazuje się więc **ścieżką bezwzględną** (ustawienia → zakładka „Dźwięk”
→ Utwór), np. `/…/build/assets/audio/Deep Purple - Smoke On The Water.mp3`;
ścieżka względna liczy się od korzenia projektu, którego dystrybucja nie ma.
Konfiguracja i historia komend idą do katalogu domowego, więc niezapisywalny
katalog wyniku niczego nie blokuje.

Sprzątanie: `make clean` usuwa `build/` i wytwory narzędzi, `make dist-clean`
dokłada `vendor/`. Żaden nie tyka `docs/pomiary/` ani konfiguracji w `HOME`.

## Dokumentacja

- [docs/architecture.md](docs/architecture.md) — warstwy DDD, wzorzec
  Singleton, standardy PHP (dokument źródłowy)
- [docs/plans/00-index.md](docs/plans/00-index.md) — plan wdrożenia i status
  poszczególnych kroków
- [docs/plans/00-decyzje.md](docs/plans/00-decyzje.md) — dziennik decyzji
  architektonicznych

## Znane ograniczenia

Tryb renderowania jest wykrywany raz, przy starcie: aplikacja pyta terminal
o możliwości (Primary Device Attributes) i czeka na odpowiedź do 300 ms.
Multipleksery (tmux, screen) potrafią tę odpowiedź odfiltrować — aplikacja
zejdzie wtedy do trybu tekstowego mimo terminala obsługującego Sixel.

**gnome-terminal nie nadaje się do trybu graficznego** i nie da się tego
naprawić konfiguracją. VTE usunęło obsługę Sixela z gałęzi stabilnej w wersji
0.75.90 (commit `e264c6e`, 2024-02-10, „SIXEL support is not in a releasable
state”); w 0.76 zostały same zaślepki ABI — `vte_terminal_get_enable_sixel()`
zwraca zaszyte `false`, a setter nic nie zapisuje. Klucz `enable-sixel`
w profilu gnome-terminala jest wobec tego bezczynny.

**Tor okienkowy kończy się naruszeniem ochrony pamięci przy wyjściu** — zarówno
pomiar, jak i sama aplikacja (sprawdzone w kroku 37; wcześniej znane wyłącznie
z pomiaru). Dzieje się to **po** wykonaniu całego sprzątania: wynik pomiaru jest
wypisany, a historia komend i zapamiętany rozmiar okna zapisane — sprawdzone
wyjściem `F10` tuż po zmianie rozmiaru. Usterka siedzi w sprzątaniu GLFW i jest
starsza od kroku 37 (sprawdzone na kodzie sprzed niego, w osobnym drzewie
roboczym). Kod wyjścia procesu jest przez to bezwartościowy — `./bin/render-bench
--window` nie nadaje się do bramki automatycznej, a same wyniki i pliki
pozostają kompletne.

**Gęstość wyświetlacza (HiDPI) jest odczytywana, ale nie stosowana.** Na
wyświetlaczu o skali innej niż 1.0 tekst w oknie będzie odpowiednio mniejszy,
niż być powinien — klatka wypełni okno w całości, bo rozmiar liczy się
z framebuffera, ale komórka nie rośnie razem ze skalą. Maszyna, na której
powstał krok 37, ma skalę 1.0, więc przeliczenia nie dało się na niej rzetelnie
sprawdzić, a kod pisany na ślepo byłby zakładem. Odczytaną wartość pokazuje
zakładka „Aplikacja” w oknie pomocy (`F1`) — jeśli widzisz tam coś innego niż
`1,00 × 1,00`, jest to sytuacja, której ta wersja nie obsługuje.

**Ikona okna nie ustawia się z aplikacji.** Rozszerzenie PHP-GLFW 2.2 nie
wystawia `glfwSetWindowIcon`, więc jedyną drogą jest wpis pulpitu zakładany
przez `./bin/install-desktop-entry` (patrz „Tryb okienkowy”). W środowiskach,
które nie dopasowują okien po `WM_CLASS`, okno zostanie z ikoną zastępczą.

Ostatni wiersz okna zostaje w trybie graficznym pusty. To rezerwa: obraz
sięgający ostatniego wiersza wypycha ekran o wiersz w górę, bo terminal stawia
kursor pod obrazem.

Terminal jest przywracany do stanu sprzed uruchomienia na trzech ścieżkach:
przez obsługę sygnałów (SIGINT, SIGTERM, SIGHUP, SIGQUIT), przez funkcję
zamknięcia procesu (również przy niezłapanym wyjątku) i przez jawne
`restore()`. Jedynym wyjątkiem jest **SIGKILL** (`kill -9`), którego nie da
się przechwycić — po nim terminal zostaje w trybie surowym i trzeba go
naprawić poleceniem `stty sane`.

## Znane ograniczenie środowiska

Composer potrafi zakończyć się naruszeniem ochrony pamięci (SIGSEGV) przy
równoległym pobieraniu wielu paczek, gdy załadowane są rozszerzenia `imagick`
i `openswoole`. Obejście — uruchomienie Composera z ich pominięciem:

```bash
make install-safe COMPOSER_INI_SCAN_DIR=/ścieżka/do/conf.d-bez-imagick
```

Cel robi to samo, co wywołanie ręczne:

```bash
PHP_INI_SCAN_DIR=/ścieżka/do/conf.d-bez-imagick \
  composer update --ignore-platform-req=ext-imagick
```

Dotyczy wyłącznie samego Composera; uruchomienie aplikacji wymaga `imagick`
włączonego normalnie.
