# Krok 24 — Podział ekranu: dwa niezależne ekrany naraz

> **Skąd ten krok.** Powstał 2026-08-10, przy otwieraniu kroku o pełnym obrazie
> stanu pliku. Użytkownik dopisał do rozstrzygnięcia o układzie: *„Myślę, że
> podział ekranu (divider) tworzący dwa ekrany z jednego również powinien być
> komponentem w rdzeniu”*, a dopytany o znaczenie wybrał wariant najmocniejszy:
> **dwa niezależne ekrany widoczne naraz, każdy z własnym `handle()`**.

## Status

**Ukończony** (2026-08-10). PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag,
**901 testów** (2098 asercji) zielone, klatka zmierzona i rozliczona „przed i po”,
wzorzec zapisany.

Krok wyszedł **zupełnie inaczej, niż zakładał plan**, i to nie jest odstępstwo
wykonawcze, tylko rozstrzygnięcie użytkownika ze startu: podział nie tworzy dwóch
ekranów, tylko dwa panele **wewnątrz** jednego. Cała lewa kolumna tabeli
„Planowane zmiany w plikach” — `ScreenStack`, `InputHandler`, `LoopState`,
`HudLayout`, klucze rdzenia — została **nietknięta**. Szczegóły niżej i w
[00-decyzje.md](../00-decyzje.md), D45.

## Cel

Sprawić, żeby okno aplikacji mogło pokazywać **dwa ekrany obok siebie**, z których
każdy jest pełnoprawnym `ScreenInterface` — rysuje się sam, ma własne wiązania
klawiszy i własny `handle()` — a klawisz przenosi między nimi ognisko.

Miarą powodzenia jest zdanie: **przeglądarka po lewej i opis pliku po prawej
działają jednocześnie, obie reagują na własne klawisze, i żadna z nich nie wie,
że nie jest sama.**

## Zależności

- **Krok 21** (przeglądarka jako moduł) — twardo i podwójnie. Stamtąd pochodzi
  `ScreenInterface` w kształcie trzech stref, `ScreenStack` z dnem branym
  z konfiguracji i `ModuleContext` jako sposób, w jaki dwa moduły mówią o sobie,
  nie znając się. Krok 21 wykluczał „dwa moduły widoczne naraz” **wprost** — ten
  krok to wykluczenie znosi i musi powiedzieć, co w zamian.
- **Krok 20** (moduły) — rejestr modułów jest jedynym źródłem listy ekranów,
  które da się postawić w drugim panelu.
- **Krok 18** (komponenty i płaszczyzny) — `VStack`, `Slot` i kolejność
  ustępowania; podział poziomy jest ich odpowiednikiem w drugiej osi
  i **prawdopodobnie wymaga `HStack`, którego dziś nie ma**.
- **Krok 13** (motyw i `HudLayout`) — progi ustępowania są dziś liczone dla
  jednej kolumny treści.
- **Krok 19** (okno komend) — okno staje **nad** ekranem; przy dwóch ekranach
  trzeba przesądzić, którego dotyczy `CommandOutcome` wiązany po
  `ScreenInterface::id()`.

Od kroków 22 i 23 nie zależy — sekcja i pasek postępu są komponentami wewnątrz
ekranu, a ten krok dotyczy tego, co **na zewnątrz** ekranu.

## Model i wysiłek

**Opus / xhigh** — na równi z krokami 19 i 21, i z tego samego powodu.

Krok nie jest duży w plikach, tylko **zbieżny**: naraz zmienia się stos ekranów,
ognisko wejścia, `InputHandler`, podział okna, kontrakt ekranu (być może) oraz
sposób, w jaki okno komend i pasek stanu wiedzą, o czym mówią. Każda z tych
rzeczy z osobna jest odwracalna; wszystkie naraz — już nie, bo błąd w jednej
udaje błąd w drugiej. Dokładnie ta diagnoza usprawiedliwiała `xhigh` w kroku 21.

## Stan zastany (sprawdzony w kodzie 2026-08-10, po kroku 21)

| Element | Stan | Co z nim robi ten krok |
|---|---|---|
| `Presentation/Ui/ScreenInterface` | `id()`, `labelKey()`, `header()`, `preview()`, `bindings()`, `handle()`; ekran jest komponentem | Prawdopodobnie nietknięty — rozstrzygnięcie nr 1 |
| `Presentation/Cli/ScreenStack` | Jeden ekran bieżący, dno z konfiguracji, `toggle()`/`close()` | **Dwa ogniska zamiast jednego** — albo dwa stosy |
| `Presentation/Ui/HudLayout` | Cztery strefy pionowe; progi ustępowania na jedną kolumnę treści | Podział poziomy strefy środkowej wraz z własnym progiem |
| `Presentation/Ui/Container/{VStack,Slot}` | Podział pionowy z kolejnością ustępowania | Potrzebny odpowiednik poziomy (`HStack`) |
| `Presentation/Cli/InputHandler` | Klawisz idzie do ekranu bieżącego; `Ctrl`+litera otwiera moduł | Klawisz idzie do ekranu **z ogniskiem**; dochodzi klawisz przenoszący ognisko |
| `Presentation/Cli/FrameComposer` | Rysuje oprawę i pasek stanu; treść stref bierze z ekranu | Oprawa **dwóch** paneli treści; podpowiedzi klawiszy z ekranu z ogniskiem |
| `Application/Module/ModuleContext` | Wydawcą jest ekran, odbiorcą inny ekran | Przy dwóch ekranach naraz publikacja i odbiór dzieją się **w tej samej klatce** — dziś dzieli je przełączenie ekranu |
| `Presentation/Cli/CommandLine` (krok 19) | `CommandOutcome` wiązany po `ScreenInterface::id()` | Musi wiedzieć, **który** z dwóch ekranów jest adresatem |

Wiersz o `ModuleContext` jest najważniejszy i najłatwiejszy do przeoczenia:
dziś publikacja i odczyt kontekstu są rozdzielone przełączeniem ekranu, więc
kolejność nie ma znaczenia. Gdy oba ekrany rysują się w jednej klatce, **kolejność
zaczyna decydować**, czy prawy panel widzi zaznaczenie sprzed klatki, czy z tej.

## Zakres

### 1. Podział jako komponent, ognisko jako stan

Podział rozpada się na dwie rzeczy o różnej naturze i krok ma je rozdzielić, tak
jak krok 18 rozdzielił `ListView` od `ScrollWindow`:

- **`Split`** — komponent rdzenia: dzieli prostokąt na dwa, rysuje granicę,
  oddaje rysowanie dzieciom. Bezstanowy, powstaje co klatkę.
- **Ognisko i układ** — stan między klatkami: czy podział jest włączony, jaki jest
  ułamek podziału, który panel ma ognisko. Właściciel do rozstrzygnięcia
  (rozstrzygnięcie nr 2).

### 2. Ognisko wejścia

Klawisz musi trafić do **jednego** ekranu i to jest cała trudność tego kroku.

- Klawisz przenoszący ognisko (kandydat: `Tab`, ale `Tab` należy dziś do
  uzupełniania w oknie komend — rozstrzygnięcie nr 3).
- Panel bez ogniska ma to **widać**: przygaszona obwódka albo brak nawiasów
  narożnych. Bez tego użytkownik nie wie, dokąd pójdzie strzałka.
- Klawisze globalne (`Ctrl`+litera, `Esc`, wiersz komend) zostają globalne
  i ogniska nie dotyczą.
- Podpowiedzi klawiszy w pasku stanu pochodzą z ekranu **z ogniskiem**.

### 3. Co się dzieje ze stosem ekranów

Dziś stos jest jeden i ma dno. Przy dwóch panelach trzeba przesądzić, czy stosy
są dwa (każdy panel ma własną historię i własne dno), czy jeden stos z dwoma
ogniskami. Rozstrzygnięcie nr 4 — i to ono decyduje o rozmiarze kroku.

Cokolwiek zostanie wybrane, **`Esc` musi zachować dzisiejsze znaczenie**:
zamknięcie tego, co użytkownik otworzył ostatnio.

### 4. Kiedy podział w ogóle powstaje

- **Próg szerokości.** Dwa panele w oknie na 80 kolumn dają po 38 znaków treści.
  `HudLayout` ma dla wysokości progi, które nie wynikają z arytmetyki, tylko
  z tego, co się jeszcze da czytać — poziomy próg ma powstać tak samo, pomiarem
  w prawdziwym terminalu.
- **Włączenie.** Klawisz, ustawienie rdzenia albo jedno i drugie
  (rozstrzygnięcie nr 5).
- **Poniżej progu** podział nie powstaje, a aplikacja zachowuje się dokładnie tak,
  jak dziś.

### 5. Kontekst sesji przy dwóch ekranach

`ModuleContext` publikuje ekran, który wie, gdzie stoi użytkownik. Przy dwóch
panelach publikatorów może być dwóch — i wtedy trzeba powiedzieć, **czyj kontekst
jest kontekstem sesji**. Propozycja: kontekst publikuje panel **z ogniskiem**,
bo to on odpowiada na pytanie „gdzie użytkownik stoi”. Przeniesienie ogniska
zmienia kontekst, a prawy panel przerysowuje się w następnej klatce.

Kolejność w klatce musi być przy tym **jawna i przetestowana**, a nie wynikać
z kolejności wywołań w `FrameComposer`.

### 6. Zgodność wsteczna

Aplikacja bez włączonego podziału ma działać **co do znaku** tak, jak dziś. To
jest ten sam sprawdzian, który krok 21 postawił przeglądarce, i z tego samego
powodu: podział jest dodaniem możliwości, nie przeprojektowaniem.

## Poza zakresem

- **Więcej niż dwa panele.**
- **Podział pionowy** (jeden nad drugim) — jeśli `Split` przyjmie oś jako
  parametr, to za darmo; jeśli nie, to osobna sprawa.
- **Ruchoma granica przeciągana klawiszem** — ułamek podziału jest stały albo
  brany z konfiguracji, ale nie zmienia się w locie.
- **Operacje między panelami** (kopiowanie z lewego do prawego) — to operacje na
  plikach, które mają własne miejsce w „Zakresie poza MVP”.
- **Zagnieżdżony podział** — panel podzielony na kolejne dwa.
- **Trzy piętra stosu ekranów** — nadal wykluczone (krok 21).

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Presentation/Ui/Component/Split.php` | Presentation | Nowy — podział prostokąta, granica, dwoje dzieci. |
| ~~`Presentation/Ui/Container/HStack.php`~~ | Presentation | **Nie powstał** — podział dzieli po połowie, a przy braku miejsca nie powstaje zamiast ustępować. |
| ~~`Presentation/Ui/HudLayout.php`~~ | Presentation | **Nietknięty** — próg należy do `Split`, bo podział należy do ekranu, a nie do okna. |
| ~~`Presentation/Cli/ScreenStack.php`~~ | Presentation | **Nietknięty** — widoczny ekran jest nadal jeden. |
| ~~`Presentation/Cli/InputHandler.php`~~ | Presentation | **Nietknięty** — `Tab` obsługuje ekran, bo ognisko jest jego sprawą. |
| `Presentation/Cli/FrameComposer.php` | Presentation | Jedno pytanie: `DrawsOwnFrame`. Oprawę **oddaje ekran**, rdzeń kładzie ją na płaszczyźnie spodniej. |
| `Presentation/Ui/DrawsOwnFrame.php` | Presentation | **Poza planem** — jedyny sposób, żeby powiedzieć rdzeniowi „nie oprawiaj mnie”. |
| `Presentation/Ui/Component/{Label,Panel}.php` | Presentation | **Poza planem** — `fitEnd()` i `labelRoom()`; wymusił je zrzut klatki. |
| ~~`Presentation/Cli/LoopState.php`~~ | Presentation | **Nietknięty** — stan podziału należy do ekranu. |
| ~~`Application/Dto/{Settings,SettingKey}.php`~~ | Application | **Nietknięte** — ustawienia podziału leżą w podprzestrzeni modułu. |
| `Module/Browser/**` | Moduł | Dwa panele: `BrowserPanes`, `EntryList`, dwie pozycje ustawień, `Tab`. |
| `src/Module/Browser/lang/*` | Napisy | Klawisz ogniska i dwie pozycje ustawień — w katalogu **modułu**, nie rdzenia. |
| `lang/pl.php`, `lang/en.php` | Napisy | Wyłącznie etykieta nowego scenariusza pomiaru. |
| `Infrastructure/Diagnostics/ScenarioFactory.php` | Infrastructure | Scenariusz „klatka podzielona” do `bin/render-bench`. |
| `README.md` | Dokumentacja | Podział ekranu i klawisz ogniska w tabeli sterowania. |
| `docs/architecture.md` | Dokumentacja | Uchylenie zasady „jeden ekran naraz”; ognisko; kontekst przy dwóch publikatorach. |
| `.claude/skills/light-manager-conventions/SKILL.md` | Dokumentacja | To samo w skrócie operacyjnym — **w tym samym kroku**. |
| testy | Testy | Klawisz trafia do ekranu z ogniskiem; przeniesienie ogniska; podział poniżej progu nie powstaje; kontekst publikuje panel z ogniskiem; `Esc` zachowuje znaczenie; aplikacja bez podziału działa jak przed zmianą. |

## Do rozstrzygnięcia na starcie kroku *(rozstrzygnięte — patrz sekcja wyżej)*

1. **Czy `ScreenInterface` musi urosnąć** — np. o pytanie „czy masz ognisko”.
   Byłaby to trzecia zmiana tego kontraktu (po krokach 18 i 21) i trzeba ją
   uzasadnić, a nie przyjąć.
2. **Kto trzyma stan podziału i ogniska** — `LoopState`, `ScreenStack` czy nowa
   klasa. Precedens z kroku 21 (`BrowserState`) mówi: osobny obiekt tam, gdzie
   stan ma więcej niż jedno wejście.
3. **Który klawisz przenosi ognisko.** `Tab` jest naturalny, ale należy dziś do
   uzupełniania ścieżek w oknie komend.
4. **Jeden stos z dwoma ogniskami czy dwa stosy** — to rozstrzygnięcie decyduje
   o rozmiarze kroku bardziej niż wszystkie pozostałe razem.
5. **Jak włącza się podział** — klawiszem, ustawieniem czy jednym i drugim.
6. **Co pokazuje drugi panel przy pierwszym włączeniu** — ten sam moduł, moduł
   domyślny czy wybór z rejestru.
7. **Który ekran jest adresatem `CommandOutcome`** z okna komend (krok 19 wiąże
   po `ScreenInterface::id()`).
8. **Czy pas podglądu i górny pas należą do panelu, czy do okna.** Dwa panele
   z własnymi paskami ścieżki to dwa razy mniej miejsca na treść.

## Kryteria ukończenia

- Dwa ekrany widoczne naraz, każdy rysuje się sam i obsługuje **własne** klawisze.
- Panel z ogniskiem widać bez czytania — wyróżnienie jest w oprawie, nie
  w komunikacie.
- Klawisze globalne działają niezależnie od ogniska; `Esc` zachowuje dzisiejsze
  znaczenie.
- Poniżej progu szerokości podział nie powstaje, a klatka wygląda jak dziś.
- **Aplikacja bez włączonego podziału zachowuje się co do znaku tak, jak przed
  zmianą** — klawisze, napisy i układ klatki. Odstępstwo, jeśli będzie, jest
  w dzienniku wraz z powodem.
- Kontekst sesji ma **jednego** wydawcę i jest nim panel z ogniskiem; kolejność
  publikacji i odczytu w klatce sprawdza test.
- Klatka zmierzona `bin/render-bench` ze scenariuszem podzielonym i rozliczona
  „przed i po” — również wtedy, gdy wynik jest niekorzystny.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.
- `docs/architecture.md`, `SKILL.md` i `README.md` opisują podział, ognisko
  i próg — zgodnie ze sobą.

## Rozstrzygnięcia wykonawcze ze startu kroku (2026-08-10)

Osiem pytań planu, z których **pierwsze przewróciło pozostałe siedem**.

| # | Pytanie | Wybór |
|---|---|---|
| 1 | Co znaczy „dwa ekrany naraz” | **Dwa panele wewnątrz jednego ekranu.** `F1`, `F2` i skrót modułu zastępują całość razem z podziałem |
| 2 | Kto trzyma stan podziału | **Osobna klasa** — `SplitState` w rdzeniu (ognisko) i `BrowserPanes` w module (para katalogów) |
| 3 | Klawisz ogniska | **`Tab`** — wolny, bo należy do uzupełniania **wewnątrz** modalnego okna komend i nigdy nie schodzi do ekranu |
| 4 | Stos ekranów | **Nietknięty.** Pytanie zniknęło razem z rozstrzygnięciem nr 1 |
| 5 | Jak włącza się podział | **Ustawienie modułu**, nie klawisz rdzenia i nie klucz rdzenia |
| 6 | Co jest w drugim panelu | **Druga lista plików** — dwa niezależne katalogi. „Wstęp do operacji na plikach” |
| 7 | Adresat `CommandOutcome` | Pytanie zniknęło: ekran jest jeden, więc adresat też |
| 8 | Strefy skrajne | **Do okna**, treść od panelu z ogniskiem |
| 9 | Oprawa i ognisko | **Dwie osobne ramki**, etykietą każdej jest ścieżka jej katalogu |
| 10 | Oś | **Pionowa domyślnie**, oś jako pozycja w ustawieniach modułu |
| 11 | Próg szerokości | **72 kolumny** (i 14 wierszy dla osi poziomej) |

Rozstrzygnięcie nr 1 padło w odpowiedzi na pytanie „gdzie pojawi się pomoc, gdy
naciśniesz `F1` przy ognisku po prawej”. Odpowiedź — *„zastąpić ekran, na którym
jest podział; `F1` zastępuje całość i rysuje swoją treść ekranu”* — znaczy, że
podział jest **sprawą modułu, nie powłoki**, i to zdanie zdjęło z kroku
dwie trzecie zakresu. Użytkownik dopowiedział regułę ogólniejszą: *„każdy moduł
sam definiuje, jak ma wyglądać jego interfejs z dostępnych komponentów”*.

## Odstępstwa od planu

Cztery, z czego pierwsze obejmuje cały krok.

1. **Rdzeń nie dostał ani ogniska, ani drugiego stosu, ani wiedzy o podziale.**
   Plan zakładał, że podział jest sprawą powłoki: dwa ogniska w `ScreenStack`,
   klawisz przenoszący w `InputHandler`, oprawa dwóch paneli w `FrameComposer`,
   stan podziału w `LoopState`, klucze w `Settings`. Nic z tego nie powstało —
   podział mieszka **wewnątrz ekranu**, więc rdzeń wnosi tylko klocek (`Split`),
   pamięć ogniska (`SplitState`) i jedną furtkę (`DrawsOwnFrame`).
2. **`HStack` nie powstał.** Plan przewidywał go „jeśli podział ma znać kolejność
   ustępowania”. Nie ma jej znać: dwa panele dzielą się po połowie, a przy braku
   miejsca podział **nie powstaje w ogóle** zamiast ustępować. Komponent bez
   użytkownika nie wchodzi do projektu (reguła 13).
3. **Powstał `DrawsOwnFrame`** — poza listą planowanych plików. Wymusiło go
   rozstrzygnięcie nr 9: dwie osobne ramki znaczą, że rdzeń musi **przestać**
   oprawiać strefę środkową, a nie ma innej drogi, żeby mu to powiedzieć.
   Wybrano osobny interfejs zamiast trzeciej zmiany `ScreenInterface` — przez
   analogię do rozstrzygnięcia użytkownika o `NeedsFocus` z pierwszej rundy pytań.
4. **`Label::fitEnd()` i `Panel::labelRoom()`** — dwie metody rdzenia spoza planu,
   obie wymuszone przez zrzut klatki. Pierwsza ucina ścieżkę **od początku**
   (`…/projekty/lm/src` mówi więcej niż `/home/uzytkownik/pro…`), druga liczy, ile
   znaków etykiety mieści się na krawędzi panelu — a nie jest to odejmowanie
   kolumn, bo renderer rysuje napis wpięty w obwódkę **z rozstrzeleniem**.

**Czego nie zrobiono:** `NeedsFocus` z pierwszej rundy pytań **nie powstał**
i został wycofany wraz z uzasadnieniem — ognisko nie przekracza granicy ekranu,
więc rdzeń nie ma powodu o nim wiedzieć. Klawisza przełączającego oś też nie ma:
oś jest wyłącznie pozycją w ustawieniach modułu (rozstrzygnięcie nr 10).

## Pomiar

Wzorce: [2026-08-10-po-kroku-23.json](../../pomiary/2026-08-10-po-kroku-23.json)
i [2026-08-10-po-kroku-24.json](../../pomiary/2026-08-10-po-kroku-24.json).

| Scenariusz | Przed | Po | Zmiana |
|---|---|---|---|
| puste płótno | 6,8 ms | 6,6 ms | −2,9% |
| sam tekst | 11,0 ms | 11,1 ms | +1,1% |
| same ramki | 10,3 ms | 10,3 ms | +0,1% |
| ramki z tekstem | 17,0 ms | 17,2 ms | +1,1% |
| zaznaczenie | 18,7 ms | 19,1 ms | +1,9% |
| suwak | 13,7 ms | 13,6 ms | −0,4% |
| klatka z miniaturą | 26,3 ms | 26,5 ms | +0,8% |
| klatka z okienkiem | 21,5 ms | 22,6 ms | +4,9% |
| okno komend | 26,3 ms | 27,1 ms | +3,0% |
| zwijane sekcje | 15,9 ms | 16,4 ms | +3,3% |
| paski postępu | 23,5 ms | 23,9 ms | +1,5% |
| **klatka podzielona** | — | **25,0 ms** | nowy scenariusz |

**Bez regresji powyżej progu.** Jedenaście starych scenariuszy stoi w miejscu —
i tak ma być, bo żaden z nich nie przechodzi przez nowy kod. Wzorzec zapisał się
za **czwartym** podejściem: przy trzech pierwszych strażnik stabilności
zaprotestował, za każdym razem przy **innym** wierszu (okienko, miniatura, suwak),
przy medianach zgodnych co do procenta. To jest właśnie sytuacja, przed którą
ostrzega reguła 17 — wynik był stabilny, maszyna nie.

### Pierwszy przebieg: 62,2 ms i skąd się wzięły

Pierwsza wersja kroku kładła obwódki obu paneli na płaszczyźnie **treści**,
bo tam rysuje się wszystko, co pochodzi z `draw()` ekranu. Klatka podzielona
kosztowała wtedy **62,2 ms**, z czego 46 ms samo rysowanie — dwa razy więcej niż
najdroższa dotąd klatka i dwa razy więcej niż budżet taktu.

Rozbiór przez usuwanie prymitywów z klatki (12 przebiegów na wariant):

| Co usunięto z klatki | Rysowanie | Koszt elementu |
|---|---|---|
| nic — pełny `split` | 49,0 ms | — |
| obwódki (`RoundRect`) | 22,0 ms | **27 ms za dwie** |
| etykiety wpięte w linię | 37,5 ms | 11,5 ms za dwie |
| zwykły tekst (dwie listy) | 41,3 ms | 7,7 ms |
| nawiasy narożne | 44,6 ms | 4,4 ms |

Obwódka panelu kosztuje więc **około 13 ms**: obrys z wygładzaniem idzie przez
`ImagickDraw::drawImage()`, którego koszt zależy od wielkości płótna. Do tego
kroku nikt tego nie zauważył, bo obwódki rysowały się **wyłącznie na płaszczyźnie
spodniej**, a tę renderer pamięta między klatkami — czyli koszt płacony był raz
na uruchomienie i w tabeli pomiaru pokazywał się jako 0,0 ms („same ramki”).

Poprawka nie tknęła renderera: `DrawsOwnFrame::ownFrame()` **oddaje prymitywy**
zamiast rysować, a `FrameComposer` kładzie je na płaszczyźnie spodniej. Rysowanie
spadło z 49,0 do 12,2 ms, cała klatka z 62,2 do 25,0 ms, a zrzut przed poprawką
i po niej jest **piksel w piksel ten sam** — zmieniła się płaszczyzna, nie obraz.

Wniosek zapisany w `architecture.md` i w `SKILL.md`: **co się między klatkami nie
zmienia, należy do płaszczyzny spodniej — niezależnie od tego, kto to narysował.**

## Dziennik realizacji

**2026-08-10 — krok wykonany.**

Co powstało w rdzeniu — trzy pliki i ani jednego więcej:

- **`Presentation/Ui/Component/Split`** — geometria podziału (obie osie), progi
  czytelności (`MINIMUM_COLUMNS = 72`, `MINIMUM_ROWS = 14`) i statyczne `halves()`
  oraz `fits()`, żeby ekran mógł zapytać o podział, **zanim** zbuduje dzieci.
- **`Presentation/Ui/SplitState`** — trzecia klasa stanu między klatkami, po
  `ScrollWindow` i `SectionState`. Cienka, ale trzyma regułę, dla której powstała:
  wyłączony podział sprowadza ognisko na pierwszy panel.
- **`Presentation/Ui/DrawsOwnFrame`** — jedyny wyłom w zasadzie „ekran nie rysuje
  ramek”, wraz z uzasadnieniem i ceną.

Co powstało w module przeglądarki:

- **`BrowserPanes`** — dwa `BrowserState`, dwa `ScrollWindow` i `SplitState` w
  jednym miejscu, bo panel to nie jedna rzecz, tylko trzy.
- **`Component/EntryList`** — lista wpisów jednego katalogu; wyjęta z
  `BrowserScreen::draw()`, gdy dostała drugiego użytkownika w tej samej klatce.
- **Dwie pozycje ustawień** (`split`, `splitVertical`) — przełączniki, a nie wybór
  z listy, bo wartości wyboru ekran ustawień pokazuje **surowo**, bez katalogu
  napisów, i „vertical” zostałoby w polskim interfejsie po angielsku.
- **`Tab`** w wiązaniach ekranu — ale **tylko przy włączonym podziale**, bo
  podpowiedź o panelach, których nie ma, byłaby kłamstwem.

**Co sprawdziło się samo z siebie.** Obawa planu o kolejność publikacji kontekstu
sesji („gdy oba ekrany rysują się w jednej klatce, kolejność zaczyna decydować”)
okazała się bezprzedmiotowa: kontekst publikuje się w fazie **wejścia**
(`BrowserState`), a czyta w fazie rysowania, więc odbiorca i tak widzi zaznaczenie
z tej samej klatki. Została jedna rzecz do dopisania — przeniesienie ogniska musi
kontekst **ogłosić**, bo zaznaczenie, o którym mówi, jest od tej chwili inne.

**Czego kod nie pozwolił zrobić inaczej.** Drugi panel dostaje **własny agregat**
tego samego katalogu, a nie ten sam obiekt: `Directory` jest mutowalny w miejscu,
więc wspólny obiekt dałby dwa panele z jednym kursorem. To samo ograniczenie
przesądziło wcześniej, że ten sam ekran modułu nie może stanąć w dwóch panelach —
i było jednym z argumentów przeciwko wariantowi „dwa pełne stosy”.

**Testy:** 21 nowych (`SplitTest` — 9, `BrowserSplitTest` — 9, plus 3 w
`ScenarioFactoryTest` i poprawka w `BrowserModuleTest`), razem **901** zielonych.
`BrowserSplitTest` patrzy na klatkę i na klawisze, a nie na klasy, bo błąd, który
tu grozi naprawdę, to rozjazd między tym, kto dostaje klawisz, a tym, co widać na
ekranie. Jedna z jego asercji pilnuje **pomiaru, a nie wyglądu**: obwódki paneli
mają leżeć w płaszczyźnie spodniej i test tego dowodzi.
