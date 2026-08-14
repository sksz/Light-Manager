# Krok 46 — Efekty specjalne: zdarzenia aplikacji dostają dźwięk

> **Skąd ten krok.** Powstał 2026-08-14, na polecenie użytkownika (D71), razem
> z krokiem 45 i jako druga połowa tej samej rozbudowy. Rozdzielone są dlatego,
> że każdy rusza **inny mechanizm rdzenia**: tamten takt, ten — zdarzenia.

## Status

**Nie rozpoczęty** (2026-08-14).

## Cel

Aplikacja ma o sobie mówić dźwiękiem: skasowanie pliku, komunikat błędu czy
otwarcie okna mogą zagrać krótką próbką, którą użytkownik sam przypisał.

Miarą powodzenia jest zdanie: **nieudana operacja gra inny dźwięk niż otwarcie
pomocy, lewy panel okna audio mówi, co gdzie gra, a jeden przełącznik ucisza
wszystkie efekty naraz — nie ruszając muzyki.**

## Zastrzeżenie do rozstrzygnięcia na starcie — słownik zdarzeń jest zamknięty

Zdarzenie w rdzeniu to **umowa na lata**: publikuje je rdzeń, a odbiera moduł,
więc każde nowe jest zobowiązaniem, którego nie da się cofnąć bez zmiany
w obu miejscach naraz. Krok ma z tego powodu jedną regułę nadrzędną, wzorowaną
wprost na słowniku prymitywów (reguła 11k): **słownik zdarzeń jest zamknięty,
a jego rozszerzenie wymaga zgody użytkownika.**

Z tego wynika kryterium doboru: wchodzi zdarzenie, które **rdzeń już zna
z nazwy** — bo je gdzieś raportuje albo przełącza — a nie takie, które trzeba by
najpierw wymyślić. Jeśli lista z rozstrzygnięcia nr 1 zacznie puchnąć powyżej
kilku pozycji, znak to, że krok próbuje zbudować szynę zdarzeń zamiast dać
dźwięk kilku momentom.

## Zależności

- **Krok 45** całkowicie: okno modułu, jego panel i plik stanu modułu powstają
  tam. Ten krok **dokłada drugi panel** i drugą mapę do tego samego pliku.
- **Krok 36** — port audio i obie jego implementacje; efekt gra tą samą drogą,
  co muzyka.
- **Krok 20** twardo: zdolność „słucham zdarzeń” wchodzi obok `ProvidesCommands`
  i `ProvidesSettingsTab`, czyli tam, gdzie kontrakt modułu ma swoje zdolności.
- **Krok 24** (podział ekranu): okno audio rośnie tu do dwóch paneli —
  `Split` i `SplitState` biorą się stamtąd, wraz z regułą, że **podział należy do
  modułu**, a nie do rdzenia.
- **Krok 27** (wiersz wielokolumnowy) — lewy panel to wiersz z dwiema kolumnami
  („zdarzenie” i „plik”), czyli `TableRow`, a nie `ListRow`.
- **Krok 19 i 18** — pole tekstowe i okno nakładane, jeśli przypisanie pliku
  odbywa się wpisaniem ścieżki (rozstrzygnięcie nr 3).
- **Kroki 41–44** (operacje na plikach) — **nie są zależnością, tylko
  najciekawszym odbiorcą**: „plik skasowany” jest zdarzeniem, dla którego efekt
  dźwiękowy ma najwięcej sensu. Jeśli Faza XIV wykona się wcześniej, słownik
  zdarzeń dostanie tam gotowych kandydatów; jeśli później, ten krok obejdzie się
  bez nich.

## Model i wysiłek

**Opus / high.**

Kodu niewiele, a cała trudność leży w **doborze zdarzeń i w miejscach ich
publikacji**: rdzeń ma zyskać mechanizm ogólny, a nie wiedzę o dźwięku, więc
każde `publish()` wstawione w kod rdzenia musi dać się obronić bez słowa
„muzyka”. Druga trudność jest cichsza: efekt odpalany zbyt często (zmiana
zaznaczenia przy przewijaniu listy — trzydzieści razy na sekundę) zamienia
funkcję w karę.

## Stan zastany (sprawdzone przy planowaniu / do potwierdzenia na starcie kroku)

| Element | Stan |
|---|---|
| `src/Domain/Event/` | **Katalog pusty od kroku 01.** Nazwę zarezerwowano przy zakładaniu struktury i nigdy nie wypełniono — ten krok jest pierwszym, który ma czym |
| Mechanizm zdarzeń | **Nie istnieje**: ani szyny, ani portu, ani jednego `dispatch()` w całym `src/` |
| `GL\Audio\Engine` | **Miksuje kilka dźwięków naraz** — sprawdzone 2026-08-14: dwa `Sound` z tego samego silnika grają równocześnie. Efekt zagra **na** muzyce, nie zamiast niej |
| `LoopState::report()` | Jedyne miejsce, przez które przechodzą **wszystkie** komunikaty aplikacji wraz z tonem (`Info`, `Warning`, `Error`) — najtańszy kandydat na źródło zdarzeń |
| `ScreenStack`, `OverlayStack` | Otwarcie i zamknięcie ekranu oraz okna nakładanego mają po jednym miejscu w kodzie |
| `Bootstrap::boot()` / `shutdown()` | Start i koniec pracy — dwa oczywiste momenty, oba już nazwane |
| `Module/Audio/**` po kroku 45 | Okno z jednym panelem, plik stanu modułu, takt modułu |
| `Table`, `TableRow`, `Column` | Wiersz o dwóch kolumnach gotowy od kroku 27 — lewy panel nie potrzebuje komponentu |

## Zakres

### 1. Słownik zdarzeń

Zamknięty zbiór nazwanych zdarzeń (`Domain/Event` albo `Application/Event` —
rozstrzygnięcie nr 2), z listą wybraną na starcie (rozstrzygnięcie nr 1).
Kandydaci, wszyscy **już nazwani w rdzeniu**:

| Kandydat | Skąd rdzeń już o nim wie |
|---|---|
| komunikat błędu | `LoopState::report()` wraz z `MessageTone::Error` |
| komunikat ostrzeżenia | tamże, `MessageTone::Warning` |
| start aplikacji | `Bootstrap::boot()` |
| koniec pracy | `Bootstrap::shutdown()` |
| otwarcie okna nakładanego | `OverlayStack::open()` |
| uruchomienie komendy | `CommandOverlay::run()` i `MenuOverlay::run()` |
| potwierdzenie czynności nieodwracalnej | `ConfirmOverlay` w wariancie `dangerous` |
| operacja na pliku udana / nieudana | Faza XIV, jeśli wykona się wcześniej |

Zdarzenie niesie **wyłącznie tożsamość i to, co da się powiedzieć bez znajomości
odbiorcy** — nazwę i ewentualnie jedną daną pierwotną. Obiektów domeny modułu
przez zdarzenie nie przekazujemy nigdy (ta sama zasada, którą kieruje się
`ModuleContext`, D40 P5).

### 2. Publikacja przez rdzeń i zdolność modułu

Rdzeń publikuje, moduł deklaruje `ListensToEvents` (nazwa: rozstrzygnięcie nr 2)
i dostaje zdarzenie synchronicznie, w takcie, w którym padło. Trzy reguły:

- **publikacja jest tania i nie rzuca** — odbiorca, który rzuci, nie ma prawa
  przerwać pętli,
- **rdzeń nie wie, kto słucha** — publikacja wygląda tak samo przy zerze
  odbiorców,
- **zdarzenie nie wraca z odpowiedzią** — to nie jest droga, którą moduł zmienia
  bieg aplikacji; od tego są komendy.

### 3. Mapa „zdarzenie → plik” i lewy panel okna

Przypisania trafiają do pliku stanu modułu z kroku 45. Okno audio rośnie do
**dwóch paneli** (`Split`, `SplitState`): po lewej zdarzenia z przypisanymi
plikami (`Table`, dwie kolumny), po prawej playlista. `Tab` przenosi ognisko —
dokładnie jak w przeglądarce od kroku 24.

### 4. Odtwarzanie efektu

Efekt gra **obok muzyki**, nie zamiast niej: silnik miksuje (sprawdzone).
Wymaga to drugiego uchwytu `Sound` w usłudze i reguły na wypadek dwóch efektów
naraz (rozstrzygnięcie nr 5). Efekt **nie zatrzymuje** i **nie przycisza**
muzyki — ducking jest poza zakresem.

### 5. Przełącznik efektów

Pozycja w zakładce ustawień modułu: efekty włączone albo nie (globalnie).
Przełącznik per zdarzenie — rozstrzygnięcie nr 4; jeśli wejdzie, mieszka
w mapie, a nie w ustawieniach, bo lista zdarzeń jest zamknięta, ale plik mapy
i tak trzyma po jednym wierszu na zdarzenie.

### 6. Pomiar

Publikacja zdarzeń dzieje się **w takcie pętli**, więc rozlicza ją oś `--loop`
„przed i po”, tak samo jak takt modułu w kroku 45. Scenariusza okna audio nie ma
z tego samego powodu, co tam: to `Table` i `ListView` w strefie środkowej, czyli
treść mierzona już przez `columns` i `text`.

## Poza zakresem

- **Szyna zdarzeń ogólnego przeznaczenia** — kolejki, priorytety, zdarzenia
  publikowane przez moduły, zdarzenia asynchroniczne. Rdzeń publikuje kilka
  nazwanych momentów i tyle; reszta jest rozwiązaniem problemu, którego nikt
  jeszcze nie ma.
- **Ściszanie muzyki na czas efektu (ducking)** — silnik miksuje bez tego,
  a próg „o ile ściszyć i na jak długo” byłby wartością wziętą z sufitu.
- **Dźwięki syntezowane w aplikacji** — efekt jest plikiem, który ktoś podłożył.
- **Efekty przypisane do klawiszy** — klawisz nie jest zdarzeniem aplikacji,
  tylko wejściem; dźwięk pod każdym naciśnięciem to osobna decyzja, jeśli w ogóle.
- **Zestaw dźwięków w repozytorium** — moduł ma umieć odtworzyć plik, a nie
  dowozić bibliotekę próbek.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Domain/Event/…` albo `Application/Event/…` | wedle rozstrzygnięcia nr 2 | **Nowe** — zamknięty słownik zdarzeń. |
| `Application/Module/ListensToEvents.php` | Application | **Nowa** — zdolność odbioru; nazwa wedle rozstrzygnięcia. |
| `Presentation/Cli/LoopState.php` | Presentation | Publikacja zdarzenia przy komunikacie (ton → zdarzenie). |
| `Presentation/Cli/{Bootstrap,ScreenStack,OverlayStack}.php` | Presentation | Publikacja pozostałych wybranych momentów. |
| `Module/Audio/Application/…` | Moduł | Mapa „zdarzenie → plik” wraz z odczytem i zapisem. |
| `Module/Audio/Presentation/AudioScreen.php` | Moduł | Drugi panel, podział, `Tab`, przypisywanie pliku. |
| `Module/Audio/Presentation/AudioModule.php` | Moduł | Deklaracja `ListensToEvents`. |
| `Module/Audio/Application/AudioSettings.php` | Moduł | Przełącznik efektów. |
| `Module/Audio/lang/{pl,en}.php` | Napisy | Nazwy zdarzeń widoczne w oknie, komunikaty przypisania. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Zdarzenia jako mechanizm rdzenia wraz z regułą zamkniętego słownika. |
| testy | Testy | Publikacja bez odbiorców, odbiorca rzucający wyjątek, mapa bez pliku, efekt nieprzerywający muzyki (za dublerem portu), przełącznik. |

## Do rozstrzygnięcia na starcie kroku

1. **Które zdarzenia wchodzą** — z listy kandydatów w zakresie nr 1; im mniej,
   tym lepiej, a każde musi dać się obronić bez słowa „muzyka”.
2. **Gdzie mieszka słownik i jak nazywa się zdolność** — `Domain/Event`
   (katalog czeka pusty od kroku 01) czy `Application/Event`; zdarzenie jako enum
   czy jako obiekt wartości z danymi.
3. **Jak przypisuje się plik do zdarzenia** — pole tekstowe w oknie modułu, wpis
   zaznaczony w przeglądarce (przez `ReadsContext`) czy komenda z podpowiedziami.
4. **Czy zdarzenie ma własny przełącznik**, czy wystarczy jeden globalny.
5. **Dwa efekty naraz** — drugi czeka, przerywa pierwszy czy gra razem z nim.
6. **Zdarzenie bez przypisanego pliku** — cisza czy wiersz oznaczony w oknie
   jako „nieprzypisane”.

## Kryteria ukończenia

- Przypisanie pliku do zdarzenia w oknie audio sprawia, że zdarzenie gra —
  i przeżywa ponowne uruchomienie aplikacji.
- Efekt gra **na muzyce**: utwór nie milknie ani nie przeskakuje.
- Jeden przełącznik ucisza wszystkie efekty, nie ruszając muzyki.
- Rdzeń publikuje zdarzenia **nie wiedząc o odbiorcach**: przy zerze modułów
  słuchających nic się nie zmienia, a odbiorca rzucający wyjątek nie przerywa
  pętli.
- Słownik zdarzeń jest zamknięty i opisany w `docs/architecture.md` wraz
  z regułą rozszerzania.
- Publikacja **nie kosztuje mierzalnie** — `bin/render-bench --loop` „przed
  i po” bez regresji.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone; **żaden test
  nie uruchamia silnika audio**.

## Dziennik realizacji

*(pusty — krok nie rozpoczęty)*
