# Krok 45 — Ekran modułu dźwięku i playlista

> **Skąd ten krok.** Powstał 2026-08-14, na polecenie użytkownika (D71), tuż po
> ukończeniu kroku 36. Tamten dowiózł dźwięk jako moduł **bez ekranu**, sterowany
> dwiema komendami, z jednym utworem wskazanym kluczem ustawień. Ten krok daje
> mu okno, listę utworów i zdolność grania dalej bez pytania — a przy okazji
> **odwraca rozstrzygnięcie D70**: kontrakt modułu dostaje takt.

## Status

**Nie rozpoczęty** (2026-08-14).

## Cel

Moduł dźwięku ma być modułem jak każdy inny: z własnym oknem, własnym skrótem
i treścią, którą da się obejrzeć. Wybór utworu przenosi się z ustawień do
**playlisty**, a playlista gra dalej sama.

Miarą powodzenia jest zdanie: **`Ctrl`+`A` otwiera okno z listą utworów, `Enter`
gra wskazany, a gdy utwór się skończy, następny rusza sam — także wtedy, gdy
użytkownik dawno wrócił do przeglądarki i o oknie audio zapomniał.**

## Zastrzeżenie do rozstrzygnięcia na starcie — dlaczego ten krok rusza rdzeń

Krok 36 zamknął się zdaniem, że **kontrakt modułu nie zna cyklu życia** i że
rozszerzanie go dla wygody jednego modułu jest niedopuszczalne (D70,
rozstrzygnięcie 5). Ten krok to zdanie odwraca — i różnica, na której się to
opiera, musi zostać nazwana przed pierwszą linią kodu:

- w kroku 36 zdolność miała **jednego użytkownika i wyłącznie dla wygody**:
  muzykę dało się uruchomić komendą, autostart był udogodnieniem;
- tutaj bez wywołania spoza ekranu **funkcja nie istnieje**: playlista, która nie
  wie, że utwór się skończył, nie jest playlistą, tylko listą ścieżek.

Jeśli po rozpisaniu okaże się, że takt da się zastąpić czymś, co już w rdzeniu
jest — krok ma z tego skorzystać zamiast dokładać zdolność. Sprawdzić trzeba
w szczególności, czy `NeedsTime` (krok 23) nie wystarcza: **nie wystarcza**, bo
rdzeń pyta o czas wyłącznie **ekran i okno nakładane**, czyli to, co akurat widać
— a cała rzecz polega na graniu wtedy, gdy nie widać nic.

## Zależności

- **Krok 36** (moduł dźwięku) całkowicie: port `AudioPort`, obie jego
  implementacje, obie komendy i zakładka ustawień pochodzą stamtąd. Ten krok
  **nie buduje odtwarzacza od nowa** — rozbudowuje to, co gra.
- **Krok 20** (moduły) twardo: skrót `Ctrl`+litera, `ProvidesScreen`, zakładka
  ustawień i reguła „jedna zmiana w rdzeniu” to jego dorobek. Krok jest przy tym
  **drugim sprawdzianem kontraktu w tę samą stronę, co krok 21** — z tą różnicą,
  że tamten pytał o moduł rysujący główną funkcję aplikacji, a ten o moduł,
  który **pracuje, gdy go nie widać**.
- **Krok 21** (przeglądarka jako moduł) wzorcowo: ekran modułu zamawia trzy
  strefy klatki i sam składa swoją treść z komponentów rdzenia. Stamtąd też
  pochodzi `ReadsContext` — jedyna uczciwa droga, którą okno audio może się
  dowiedzieć, na czym stoi kursor w przeglądarce (rozstrzygnięcie nr 2).
- **Krok 18** (komponenty) — `ListView`, `ListRow`, `ScrollWindow`; okno audio
  **nie dokłada komponentu** i to jest jego sprawdzian.
- **Krok 09** (pętla główna) twardo: takt modułu wchodzi w takt pętli i musi się
  zmieścić w budżecie klatki. Reguła z kroku 23 („element zmieniający się sam
  z siebie niczego nie wymusza”) obowiązuje tu w drugą stronę: takt **nie ma
  prawa** zażądać przerysowania.
- **Krok 14 i 15** — tryb odtwarzania to pozycja ustawień modułu, a wszystko, co
  widać, idzie przez katalog napisów.
- **Krok 16** (pomiar) twardo: takt wołany trzydzieści razy na sekundę dla
  każdego przyjętego modułu rozlicza się osią `--loop` „przed i po”. Bez tego
  krok byłby zakładem.
- **Krok 24** (podział ekranu) — **nie w tym kroku**: okno audio ma tu jeden
  panel, a podział przychodzi wraz z lewym panelem w kroku 46. Zależność jest
  zapowiedzią, nie długiem.

## Model i wysiłek

**Opus / xhigh.**

Trzy trudności różnej natury. **Pierwsza — rdzeń**: takt dla modułów to zmiana
w pętli głównej i w kontrakcie, czyli w dwóch miejscach, których krok 36
świadomie nie dotknął; łatwo przy tym wpuścić do taktu pracę, która nie ma prawa
dziać się co klatkę. **Druga — trwałość**: playlista jest **listą**, a ustawienia
modułu trzymają wyłącznie skalary, więc krok zakłada moduł od nowa własny nośnik
danych i musi rozstrzygnąć, co się dzieje z plikiem ruszonym ręcznie. **Trzecia —
migracja**: klucz `track` znika z zakładki i jego wartość ma się nie zgubić.

## Stan zastany (sprawdzone przy planowaniu / do potwierdzenia na starcie kroku)

| Element | Stan |
|---|---|
| `Module/Audio/**` | Moduł bez ekranu i bez skrótu: port, dwie implementacje, dwie komendy, trzy pozycje ustawień (krok 36) |
| `AudioPort` | `isAvailable`, `play(path, volume, loop)`, `stop`, `isPlaying`, `useVolume`, `shutdown` — **`play()` przyjmuje ścieżkę**, więc playlista nie wymaga zmiany kontraktu, tylko innego wołającego |
| `GL\Audio\Sound::stop()` | **Pauza, nie przewinięcie** (sprawdzone w kroku 36) — przejście do następnego utworu wymaga więc jawnego wczytania nowego pliku, a nie samego `play()` |
| `GL\Audio\Engine` | **Miksuje kilka dźwięków naraz** (sprawdzone 2026-08-14: dwa `Sound` grają równocześnie) — fakt potrzebny dopiero krokowi 46, ale rozpoznany tutaj |
| Kontrakt modułu | **Nie ma cyklu życia ani taktu**; `NeedsTime` obsługuje wyłącznie ekran i okno nakładane, czyli to, co widać |
| `Settings::$modules` | Wartości **wyłącznie skalarne** (`bool\|int\|string`) — lista utworów się w nich nie zmieści |
| `CommandHistoryService` | Precedens nośnika listy: osobny plik `~/.light-manager/history`, zapis przez plik tymczasowy i `rename()`, **żadna ścieżka nie rzuca** |
| `ModuleRegistry::FORBIDDEN_CHARACTERS` | `c`, `h`, `i`, `j`, `m`, `z`; zajęte są `b` i `d`, więc **litera `a` jest wolna** |
| `ReadsContext` | Ekran modułu dostaje ścieżkę i nazwę zaznaczenia **co klatkę** — gotowa droga „dodaj to, co zaznaczone w przeglądarce”, bez poznawania cudzego modułu |
| `bin/render-bench --loop` | Oś taktu pętli (krok 38) — jedyne narzędzie, którym da się rozliczyć koszt taktu modułu |

## Zakres

### 1. Takt dla modułu — nowa zdolność kontraktu

Zdolność w `Application/Module` (nazwa i kształt: rozstrzygnięcie nr 1),
deklarowana jak `ProvidesCommands`, wołana **raz na takt dla każdego przyjętego
modułu**, niezależnie od tego, co jest na wierzchu. Trzy reguły, bez których
zdolność jest bronią wymierzoną w klatkę:

- **takt jest tani** — porównanie stanu, nigdy wejście-wyjście; praca dłuższa od
  klatki podlega D46 i dzieli się na kawałki,
- **takt niczego nie wymusza** — nie prosi o przerysowanie i nie zwraca skutku
  do pętli (reguła 11b w drugą stronę),
- **takt nie rzuca** — wyjątek modułu nie ma prawa przerwać pętli; łapie go ta
  sama droga, którą łapane są wyjątki ekranu.

Pierwszym i jedynym użytkownikiem jest playlista: zauważa, że utwór się skończył,
i sięga po następny.

### 2. Playlista jako dane i jako plik

Lista pozycji (ścieżka plus nazwa do pokazania) wraz z kursorem „co gra teraz”.
Trwałość — **własny plik modułu** wzorem historii komend (`~/.light-manager/…`,
nazwa: rozstrzygnięcie nr 3), bo ustawienia modułu trzymają wyłącznie skalary.
Port i usługa leżą w module, nie w rdzeniu; plik ruszony ręcznie ma dać pustą
playlistę i komunikat, nigdy wyjątek.

### 3. Okno modułu

`AudioScreen` ze skrótem `Ctrl`+`A`, jeden panel na całą strefę środkową
(podział dokłada krok 46). Górny pas: co gra teraz i w jakim trybie. Treść:
`ListView` z pozycjami playlisty i znacznikiem utworu granego. Klawisze: kursor,
`Enter` (rozstrzygnięcie nr 4), usunięcie pozycji, dodanie pozycji
(rozstrzygnięcie nr 2), `Esc` wraca do modułu domyślnego.

**Komponent rdzenia nie powstaje ani jeden** — i to jest sprawdzian tego kroku,
tak samo jak w kroku 32.

### 4. Tryb odtwarzania zamiast zapętlenia

Pozycja ustawień `loop` (prawda/fałsz, krok 36) zamienia się w pozycję wyboru:
**pętla listy**, **zatrzymaj po utworze**, **powtarzaj utwór**. Wartość
dotychczasowa przekłada się na nową bez pytania użytkownika o zdanie.

### 5. Wyniesienie utworu z ustawień

Klucz `track` znika z zakładki. Jego dotychczasowa wartość **zasila playlistę**
przy pierwszym uruchomieniu po zmianie — nikt nie ma stracić tego, co ustawił.
Zakładka zostaje z trzema pozycjami: tryb odtwarzania, głośność, przełącznik
efektów (ten ostatni dopiero w kroku 46).

### 6. Pomiar

**Oś `--loop`, „przed i po”, obowiązkowo.** Takt modułu wchodzi w każdą klatkę,
więc to jedyny scenariusz, w którym ten krok może zostawić ślad — i zarazem
odpowiedź na pytanie odłożone w kroku 36 („czy dźwięk naprawdę nie wchodzi do
ścieżki klatki”), tym razem z muzyką **grającą w tle przebiegu**. Scenariusza
ekranu audio nie ma: to `ListView` w strefie środkowej, czyli treść mierzona już
przez `text`.

## Poza zakresem

- **Efekty specjalne** — krok 46; ten krok nie dokłada ani jednego zdarzenia.
- **Podział okna audio na dwa panele** — przychodzi wraz z lewym panelem
  w kroku 46, bo panel bez treści jest obietnicą bez pokrycia.
- **Pasek postępu utworu i przewijanie** — `readFrames()` i `seekTo()` kuszą, ale
  postęp odświeżany co klatkę to praca w ścieżce klatki, której ten krok
  świadomie nie dotyka (wykluczenie z kroku 36 zostaje w mocy).
- **Przeglądanie plików audio w oknie modułu** — od tego jest przeglądarka;
  moduł audio ma dostać ścieżkę, a nie drugi menadżer plików.
- **Wiele playlist, tagi ID3, głośność pojedynczej pozycji, przenoszenie
  pozycji między playlistami** — jedna lista wystarczy, dopóki nie ma dowodu,
  że nie wystarcza.
- **Odtwarzanie strumieni sieciowych** — moduł czyta pliki z dysku.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Application/Module/…` (nowa zdolność) | Application | **Nowa** — takt modułu; kształt wedle rozstrzygnięcia nr 1. |
| `Presentation/Cli/GameLoop.php` | Presentation | Wołanie taktu raz na klatkę, przed składaniem klatki. |
| `Presentation/Cli/Bootstrap.php` | Presentation | Przekazanie pętli listy modułów z taktem — poza tym bez zmian. |
| `Module/Audio/Application/Playlist*.php` | Moduł | **Nowe** — playlista jako dane wraz z kursorem. |
| `Module/Audio/Application/Port/PlaylistPort.php` | Moduł | **Nowy** — trwałość listy; wzorem `CommandHistoryPort`, nie rzuca. |
| `Module/Audio/Infrastructure/…PlaylistService.php` | Moduł | **Nowa** — plik w `~/.light-manager/`, zapis przez `rename()`. |
| `Module/Audio/Presentation/AudioScreen.php` | Moduł | **Nowy** — okno modułu, jeden panel. |
| `Module/Audio/Presentation/AudioModule.php` | Moduł | `ProvidesScreen`, skrót `Ctrl`+`A`, deklaracja taktu. |
| `Module/Audio/Application/AudioSettings.php` | Moduł | `track` wychodzi, `loop` zamienia się w tryb odtwarzania. |
| `Module/Audio/lang/{pl,en}.php` | Napisy | Nazwy klawiszy okna, tryby odtwarzania, komunikaty playlisty. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Takt modułu jako mechanizm rdzenia; playlista jako nośnik wyboru utworu. |
| `docs/pomiary/README.md` | Pomiar | Rozliczenie osi `--loop` i powód braku scenariusza ekranu. |
| testy | Testy | Takt (że jest wołany i że nie rzuca), playlista (kolejność, kursor, plik ruszony ręcznie), migracja klucza `track`, ekran za `ScreenFixture`. |

## Do rozstrzygnięcia na starcie kroku

1. **Kształt zdolności taktu** — czy takt dostaje czas klatki (jak `NeedsTime`),
   czy nic; czy wołany jest przed obsługą wejścia, czy po niej; czy zdolność ma
   jedną metodę, czy parę „obudź się / skończ pracę”.
2. **Skąd biorą się utwory na playliście.** Trzy drogi i można wybrać więcej niż
   jedną: komenda `audio.add <ścieżka>` z podpowiedziami z dysku (wzorem
   `browser.jump`), pole tekstowe w oknie modułu albo **wpis zaznaczony
   w przeglądarce** przez `ReadsContext` — ta ostatnia jest najtańsza dla
   użytkownika i najciekawsza dla kontraktu, bo moduł nie poznaje cudzego modułu,
   tylko ścieżkę.
3. **Jeden plik modułu czy osobny na playlistę** — krok 46 dołoży mapę hooków,
   więc pytanie brzmi, czy `audio.json` ma od razu być plikiem stanu modułu.
4. **Co robi `Enter` na pozycji** — gra od razu czy tylko wskazuje „następny”.
5. **Kolejność pozycji** — czy w tym kroku wolno ją zmieniać, czy playlista jest
   listą w kolejności dodania.
6. **Pozycja wskazująca plik, którego nie ma** — zostaje z oznaczeniem czy
   wypada przy odczycie.

## Kryteria ukończenia

- `Ctrl`+`A` otwiera okno z playlistą; `Enter` gra wskazany utwór, a kolejny
  rusza sam po jego zakończeniu — także gdy na wierzchu stoi inny ekran.
- Tryb odtwarzania działa we wszystkich trzech wariantach.
- Klucz `track` zniknął z zakładki, a jego dotychczasowa wartość znalazła się na
  playliście.
- Plik playlisty ruszony ręcznie **nie wywraca startu**: pusta lista plus
  komunikat.
- Takt modułu **nie kosztuje mierzalnie** — `bin/render-bench --loop` „przed
  i po” bez regresji, przy muzyce grającej w tle przebiegu.
- Komponent rdzenia nie powstał ani jeden.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone; **żaden test
  nie uruchamia silnika audio**.

## Dziennik realizacji

*(pusty — krok nie rozpoczęty)*
