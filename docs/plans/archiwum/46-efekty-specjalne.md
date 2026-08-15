# Krok 46 — Efekty specjalne: zdarzenia aplikacji dostają dźwięk

> **Skąd ten krok.** Powstał 2026-08-14, na polecenie użytkownika (D71), razem
> z krokiem 45 i jako druga połowa tej samej rozbudowy. Rozdzielone są dlatego,
> że każdy rusza **inny mechanizm rdzenia**: tamten takt, ten — zdarzenia.

## Status

**Ukończony** (2026-08-15) — z **jawnym rozszerzeniem zakresu**: zdarzenia
publikuje nie tylko rdzeń, ale i moduły (D83). Pozycja „zdarzenia publikowane
przez moduły" wyszła przez to z sekcji *Poza zakresem*; reszta tamtych wykluczeń
została nietknięta.

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
  asynchroniczne. Rdzeń publikuje kilka nazwanych momentów i tyle; reszta jest
  rozwiązaniem problemu, którego nikt jeszcze nie ma.
  **Poprawka wykonawcza (D83):** „zdarzenia publikowane przez moduły" stały w tej
  liście i **zostały z niej wyjęte** rozstrzygnięciem użytkownika. Powód jest
  wymierny, nie estetyczny: wszystkie zdania modułów schodzą się
  w `LoopState::report()` z tonem, więc zdarzenia rdzenia odróżniają powodzenie od
  awarii, ale **nie odróżniają kopiowania od usunięcia** — a efekt przypisany do
  „zakończonego kopiowania" wymaga, żeby to kopiowanie samo o sobie powiedziało.
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

### 2026-08-15 — krok wykonany

**Rozstrzygnięcia startowe: [00-decyzje.md](../00-decyzje.md), D83.** Sześć pytań
z sekcji powyżej, jedno dodatkowe (co widać przy pierwszym uruchomieniu) i jedno
powtórzone — bo odpowiedź na nie zmieniła zakres kroku.

**1. Zakres urósł o zdarzenia modułów — i to jest główne odstępstwo od planu.**
Plan mówił „rdzeń publikuje, moduł odbiera", a „zdarzenia publikowane przez
moduły" miał w *Poza zakresem*. Odpowiedź użytkownika na pytanie o dwa efekty
naraz okazała się odpowiedzią na coś zupełnie innego: zdarzenia mają być listą
zdarzeń **rdzenia i modułów**, z przykładami „zakończenie kopiowania z sukcesem",
„przejście kursora na liście", „niepowodzenie usunięcia pliku". Rozpoznanie
w kodzie potwierdziło, że inaczej się nie da — wszystkie zdania modułów schodzą
się w `LoopState::report()` z tonem (sprawdzone: 24 miejsca tworzące `Message`
w samej przeglądarce), więc trzy zdarzenia rdzenia odróżniają powodzenie od
awarii, ale **nie odróżniają kopiowania od usunięcia**.

**2. Słownik ma 22 pozycje.** Rdzeń pięć (trzy tony komunikatu, otwarcie okna
nakładanego, wykonanie komendy), przeglądarka siedemnaście (kursor, wejście do
katalogu, zaznaczenie oraz siedem czynności × udana/nieudana). Etykieta wariantu
w pytaniu mówiła „+13", a wyliczenie pod nią dawało 16 — pomyłka wyszła przy
rozpisywaniu spisu i **wróciła do użytkownika osobnym pytaniem**, zamiast zostać
rozstrzygnięta po cichu w którąkolwiek stronę.

**3. Zamknięcie słownika jest konstrukcyjne, nie regulaminowe.** Nazwy pochodzą
z enumów (`AppEvent`, `BrowserEvent`), a deklaracje katalogu powstają
z `cases()` — publikacja i spis w oknie odbiorcy nie mają jak się rozjechać.
Warto nazwać, dlaczego to ważniejsze niż zwykła higiena: rozjazd byłby
**niewidoczny**, bo wiersz, do którego nic nie dochodzi, wygląda dokładnie tak
samo jak wiersz, do którego nic nie przypisano.

**4. Publikator zamieszkał w `LoopState`.** Obok kontekstu sesji i z tego samego
powodu: stan pętli dostaje **każdy** moduł, więc `Bootstrap` urósł o jedną linię
(`useModules()`), a nie o argument przy każdym publikującym module. Reguła 15
została nietknięta.

**5. Trzy miejsca publikacji rdzenia, wszystkie istniejące wcześniej.**
`LoopState::report()` (ton → zdarzenie), `OverlayStack::open()`
i `run()` w obu oknach rejestru komend. Zamknięcia okna **nie ogłaszamy** —
okno zamyka się na kilka sposobów naraz (`Esc`, wykonanie, ustąpienie miejsca
przez `replace()`), więc zdarzenie znaczyłoby raz „zrezygnowałem", raz
„zrobione", czyli nic. Do słownika nie wszedł też **koniec pracy**, i to
z powodu mechanicznego: `Bootstrap::shutdown()` zatrzymuje silnik audio, więc
dźwięk podpięty do zakończenia zostałby ucięty w pół.

**6. „Wejście do katalogu" ogłasza `BrowserState::enter()`, a nie ekran.**
Pierwsza wersja publikowała w `BrowserScreen::open()` i `goUp()`, ale wtedy
`browser.jump` i `browser.open` milczały. Metoda `enter()` jest **jedyną drogą,
którą katalog się zmienia** — z tym że wchodzą nią także przełączenie wpisów
ukrytych i odczyt katalogu na nowo po operacji. Jedno porównanie ścieżek
(`$changed`) rozwiązało oba problemy naraz i jest tańsze niż cztery miejsca
publikacji.

**7. Efekt gra na drugim uchwycie `Sound`.** Fakt sprawdzony przy planowaniu fazy
(silnik miksuje) okazał się wystarczający: `playEffect()` bierze osobne pole,
bo `$sound` trzyma muzykę, a jego kursor jest pamięcią pauzy — zagranie efektu
tamtym obiektem kasowałoby miejsce, w którym stanął utwór. Kursor efektu cofamy
przed każdym zagraniem (`seekTo(0)`), bo `play()` na dźwięku przerwanym w połowie
wznowiłby go od miejsca przerwania, a przy kliku trwającym pół sekundy różnicę
słychać.

**8. Odbiór nie dotyka dysku — i to jest reguła, nie optymalizacja.** Mapę
wczytuje **takt** modułu (`SoundEffects::useTime()`), a dostępność plików
przelicza się przy otwarciu okna. Zdarzenie, które padło przed pierwszym taktem,
milczy: to są zdarzenia ze startu aplikacji, a odczyt pliku w środku
`LoopState::report()` byłby wejściem-wyjściem w cudzej czynności. Przy
wyłączonych efektach mapa nie czyta się **wcale** — sprawdza to osobny test.

**9. Próg 100 ms na zdarzenie stoi po stronie odbiorcy.** Trzymana strzałka daje
trzydzieści zdarzeń kursora na sekundę; bez progu klik zamieniłby się w warkot,
czyli dokładnie w to, przed czym ostrzegał opis modelu i wysiłku. Wariant
„przeglądarka publikuje rzadziej" odpadł, bo wnosiłby wiedzę o dźwięku do
publikującego.

**10. Rachunek kolumn wyszedł dopiero z policzenia najdłuższej nazwy.** Pierwsza
wersja panelu dawała nazwie zdarzenia i nazwie pliku po kolumnie elastycznej,
czyli po połowie miejsca — a wtedy „Usunięcie trwałe: zakończone" (28 znaków)
kończyło się wielokropkiem w miejscu, w którym przestawało odróżniać czynność
udaną od nieudanej. Kolumna pliku jest przez to **stała i ustępuje pierwsza**
(reguła 11e), a nazwa dostaje 27 znaków treści; jedna polska nazwa została przy
okazji skrócona o jeden znak. Pilnuje tego test czytający **oba katalogi napisów**
— dopisanie zdarzenia o zbyt długiej nazwie skończy się na testach, a nie na
klatce pod XTermem.

**Miara powodzenia z celu kroku: spełniona.** Nieudana operacja gra inny dźwięk
niż otwarcie pomocy (osobne zdarzenia, `browser.*.failed` wobec
`core.overlay.opened`), lewy panel mówi, co gdzie gra, a przełącznik „Efekty
specjalne" ucisza wszystko naraz, nie ruszając muzyki.

### Pomiar (maszyna zwolniona przez użytkownika, obciążenie 0,05–0,07 na rdzeń)

| Oś | Wobec wzorca | Wynik |
|---|---|---|
| `--loop` | `2026-08-15-po-kroku-45-loop.json` | **+0,1%** — w szumie |
| sixel, 19 scenariuszy | `2026-08-15-po-kroku-44.json` | od **−1,6%** do **+6,4%**, bez regresji powyżej progu |

Wzorzec `2026-08-15-po-kroku-46-loop.json` zapisany.

**Granica pomiaru jest ta sama, co w kroku 45, i warto ją powtórzyć:**
`LoopBenchmarkRunner` powtarza trzy fazy pętli ręcznie i **modułów nie tyka**,
więc ani takt, ani odbiór zdarzeń nie leżą w mierzonej ścieżce. Liczba mówi, że
reszta taktu pętli się nie zmieniła. Koszt samej publikacji jest przy tym
rachunkiem konstrukcyjnym, nie domysłem: przy zerze odbiorców `publish()` kończy
się na jednym sprawdzeniu pola, a publikacja pada **na klawisz**, nie na klatkę —
trzydzieści razy na sekundę dzieje się wyłącznie przy trzymanym klawiszu, i to
jest dokładnie ten przypadek, dla którego powstał próg 100 ms.

Scenariusza okna audio nie ma z tego samego powodu, co w kroku 45: lewy panel to
`Table` w strefie środkowej, czyli treść mierzona już przez `columns`. Spis
„element → scenariusz" w [docs/pomiary/README.md](../../pomiary/README.md) nie
zmienia przez to ani jednej pozycji.

### Czego ten krok nie dowiózł

**Klatki pod XTermem nikt jeszcze nie oglądał.** Rachunek kolumn został
sprawdzony testem na obu katalogach napisów, ale wygląd panelu w prawdziwym
terminalu — nie. Krok 45 złapał tą właśnie drogą kłamiącą stopkę, więc jest to
sprawdzenie warte zrobienia przy najbliższej okazji, w której maszyna będzie
wolna.

**Podglądu przypisanego dźwięku nie ma.** `Enter` w panelu efektów nie robi nic;
żeby usłyszeć, co się przypisało, trzeba wywołać samo zdarzenie. Świadomie poza
zakresem — jedna linia kodu, ale i jeden klawisz więcej w spisie, a plan go nie
przewidywał.
