# Wzorce pomiarów wydajności

Pliki w tym katalogu to zapisane przebiegi narzędzia `bin/render-bench`
(krok 16). Powstają poleceniem `./bin/render-bench --save`, a odczytuje je
`--compare`.

## Po co leżą w repozytorium

Krok 17 (optymalizacja) ma rozliczyć każdą dźwignię „przed i po”. Punkt
odniesienia trzymany poza repozytorium przepadłby razem z maszyną, na której
powstał — a wtedy rozliczenie sprowadza się do wrażeń. Decyzja o miejscu:
[00-decyzje.md](../plans/00-decyzje.md), D33.

## Spis: element interfejsu → scenariusz

Przegląd domknięty w kroku 38. Reguła jest jedna i nie ma od niej wyjątku:
**każdy element interfejsu ma scenariusz albo zapisany tutaj powód
pominięcia**, a nowy scenariusz musi dać się **rozliczyć w parze**
z istniejącym — jak `highlight` z `columns`.

| Element | Scenariusz | Uwaga |
|---|---|---|
| `Panel` — obwódka, nawiasy, etykieta | `chrome` | |
| `Label` | `chrome-text` | wiersz ścieżki |
| `ListView`, `ListRow` | `text`, `chrome-text` | |
| pasek zaznaczenia (`Highlight`) | `selection` | każdy wiersz zaznaczony — sufit ceny |
| `Scrollbar` | `scrollbar` | |
| `StatusBar` — **wraz z wariantem dwuwierszowym** | `chrome-text` | krok 40; stopka kontekstowa jest tam pełnej długości, więc pasek rośnie do dwóch wierszy w **każdym** scenariuszu z chromem |
| `ImageBox` | `thumbnail` | jedyny scenariusz z bitmapą i paletą 256. Od kroku 47 mierzy ją **tam, gdzie aplikacja ją rysuje**: w prawym panelu podzielonej klatki, czyli w układzie modułu opisu pliku (`PreviewPane`). Do D76 stała w pasie podglądu, a po nim scenariusz budował pas, którego żaden ekran już nie zamawiał — po zniesieniu strefy (D78) nie było z czego go zbudować |
| `Dialog` | `popup` | |
| `TextInput` (z karetką) | `command` | |
| `Section`, `SectionList` | `sections` | także znaki spoza ASCII |
| `ProgressBar` — **oba tryby** | `progress` | prostokąt inny w każdej klatce |
| `Split` | `split` | obwódki w płaszczyźnie treści |
| `Table`, `TableRow`, `Column` | `columns` | |
| `TextView` | `text-view` | z zawijaniem |
| `TextMark` — podświetlenie dopasowania | `highlight` | rozlicza się w parze z `columns` |
| zaznaczenie wielokrotne — kolumna znacznika i druga rola wiersza | `marked` | krok 43; rozlicza się w parze z `columns`, tą samą konstrukcją, co `highlight`. Zaznaczone **trzy pozycje z siedmiu** — rytm nie może się pokrywać z rytmem katalogów (co szósty wiersz), bo wtedy każdy katalog byłby zaznaczony i z klatki nie dałoby się odczytać, czym rola zaznaczenia różni się od akcentu. **Scenariusz mierzy sufit, nie przypadek typowy**: przy zaznaczeniu blisko połowy wierszy różnica wobec `columns` wynosi +7,1 ms i leży w **kwantyzacji**, bo dwunasta rola motywu dołożyła paletę drugiej barwy nasyconej (D80 nr 5a); przy dwóch zaznaczonych wierszach jest to +1,0 ms |
| prostokąt zaznaczony wskaźnikiem — czwarta płaszczyzna klatki (`Marquee`) | `marquee` | krok 56; rozlicza się **w parze z `chrome-text`**, i jest to para najprostsza w całym spisie: klatka jest co do prymitywu ta sama, więc różnica to w całości cena **drugiego przejścia po prymitywach** (warstwa tekstowa `FrameText`) plus pięciu `TextMark`ów. Pięć wierszy na pełnej szerokości panelu, bo tyle bierze jedno przeciągnięcie ręką — **sufitem nie jest** i celowo: prostokąt na całą klatkę mierzyłby przemalowanie okna, a nie czynność, którą ktokolwiek wykonuje. Klatka bez zaznaczenia nie płaci za ten scenariusz nic, bo warstwa tekstowa liczy się wyłącznie wtedy, gdy jest co z niej wziąć |
| `Tabs`, `Choice`, `Toggle`, `Button`, `Spacer`, `VStack` | `settings` | krok 38; rozlicza się w parze z `chrome-text` |
| `TreeNode`, `TreeView` | `tree` | krok 31; wcięcie i prowadnice, znak spoza ASCII **na poziom**; rozlicza się w parze z `sections` — ten sam prostokąt, ten sam `ListView`, różnicą jest sam przedrostek |
| spis środowisk Dockera — tabela z nagłówkiem i trzema rolami wierszy | `environments` | krok 58; rozlicza się **w parze z `columns`**, tą samą konstrukcją, co `highlight` i `marked`. Różnią go trzy rzeczy i wszystkie trzy są treścią pomiaru: **wiersz nagłówka** (spisy hostów i środowisk rysują go od kroku 48, a żaden scenariusz go dotąd nie mierzył), **pięć kolumn** zamiast czterech i **trzy role wierszy naraz** (`Marked` dla bieżącego, `Muted` dla przysłoniętego, `Text` dla reszty) — klucz pamięci podręcznej wierszy buduje się z treści i roli (D34). Rytmy ról (11 i 7) są względnie pierwsze z tej samej lekcji, co siódemka w `marked` |
| ekran książki adresowej — pasek zakładek **nad** tabelą z nagłówkiem | `address-book` | krok 60; rozlicza się **w parze z `columns`**, jak `highlight`, `marked` i `environments`. Istnieje dlatego, że **żadna klatka nie miała dotąd obu tych rzeczy naraz**: zakładki mierzy `settings`, ale tam stoją nad **listą** pozycji, a tabelę z nagłówkiem mierzy `environments`, ale tam nad nią nic nie stoi. Ryzyko siedzi w miejscu styku — pasek zabiera tabeli dwa wiersze, więc to on rozstrzyga, ile zostaje na treść i czy suwak liczy dobrze. Zmierzone: **+1,6 ms wobec `columns`** (20,6 → 22,2) przy siedmiu kolumnach zamiast czterech, wobec **+7,5 ms** tą samą drogą dla `environments` — różnicę między nimi tłumaczą **role wierszy**, których książka nie nadaje (kursor wyróżnia `Table`, a klucz pamięci podręcznej wierszy buduje się z treści i roli, D34). Kolumna klucza niesie **zasłonę stałej długości**, tę samą, którą rysuje pole rodzaju `secret`. Pierwsza wersja scenariusza dawała kolumnom szerokości **stałe** i obejrzenie klatki od razu pokazało, że to nie jest ekran: wartości traciły ostatni znak, a nagłówek „Uwierzytelnienie” ucinał sam siebie — pola rozdziału dostają kolumny **elastyczne**, jak w prawdziwym ekranie, a stały jest wyłącznie identyfikator |
| proces potomny obok pętli | `background` | jedyny scenariusz sięgający poza PHP |
| **komplet prac tłowych obok pętli** | `background-many` | krok 51; rozlicza się **w parze z `background`**, a różnica między nimi jest w całości ceną rozbudowy portu z „jednej pracy naraz" do kilku: jedno przejście `pump()` po ośmiu potomkach zamiast po jednym, plus siedem doglądań. Liczba prac to **domyślna granica z ustawień** (`backgroundJobs`), czyli przypadek najgorszy, jaki aplikacja dopuszcza bez ruszania konfiguracji. Klatka jest co do prymitywu równa `background` — złota klatka obu scenariuszy jest tym samym plikiem |

### Pominięcia i ich powody

| Element | Dlaczego bez scenariusza |
|---|---|
| `ConfirmOverlay` | ta sama obwódka i te same wiersze, co `Dialog` — koszt mieści się w `popup` (zapisane w kroku 28) |
| `MessageOverlay` | rysuje się `Dialog`iem, czyli jest `popup`em pod inną nazwą |
| ekran modułu Dockera (kontenery, obrazy, logi) | krok 51: treść to `Table` w podziale i `TextView` w strefie środkowej, czyli **dokładnie to, co mierzą `columns` i `text-view`**. Panel opisu jest `ListView`em par etykieta–wartość, czyli treścią `chrome-text`. Ani jeden prymityw nie jest nowy, a osobny scenariusz powtarzałby pomiar pod inną nazwą — i nie dałby się rozliczyć w parze z niczym, czego tamte dwa już nie izolują. Cena modułu leży **poza klatką** (rozmowa z demonem, pompowanie gniazda) i mierzy ją oś `--loop` wraz z parą `background`/`background-many`. **Czwarta postać ekranu — spis środowisk — dostała w kroku 58 własny scenariusz** (`environments`, wiersz w tabeli wyżej): rozstrzygnięcie użytkownika ze startu kroku, wbrew rekomendacji rozszerzenia tego pominięcia — nagłówek tabeli i trzy role wierszy naraz nie były mierzone nigdzie |
| spis klastrów — piąta postać ekranu modułu k8s (krok 59) | **konstrukcja co do klocka ta sama, co `environments`**: `Table` z nagłówkiem, pięć kolumn (dwie elastyczne, trzy stałe) i trzy role wierszy naraz (`Marked` dla bieżącego, `Muted` dla przysłoniętego, `Text` dla reszty). Różni je wyłącznie treść komórek, a ta do pomiaru nie wchodzi — klucz pamięci podręcznej wierszy buduje się z treści i roli (D34), więc drugi scenariusz o tym samym kształcie mierzyłby to samo pod inną nazwą i **nie dałby się rozliczyć w parze z niczym**, czego `environments` już nie izoluje. Plan kroku zapowiadał własny scenariusz; rekomendacja upadła przy rozpisaniu, gdy okazało się, że krok 58 dowiózł dokładnie ten kształt tydzień wcześniej. Cena kroku leży **poza klatką** — jeden proces potomny na każdy czytany plik `kubeconfig` — i mierzy ją oś `--loop` wraz z parą `background`/`background-many` |
| ekran modułu klastra (drzewo, lista, opis, logi) | krok 52: cztery postacie i **ani jednego nowego prymitywu**. Drzewo mierzy `tree` (krok 31), lista rodzaju — `columns`, opis w zwijanych sekcjach — `sections`, logi i surowy YAML — `text-view`; oprawa podziału to `chrome-text`. Scenariusz powtarzałby sumę czterech istniejących i nie dałby się rozliczyć w parze z niczym, czego tamte już nie izolują. Cena modułu leży **poza klatką** — proces potomny na każde wywołanie `kubectl` — i mierzy ją oś `--loop` wraz z parą `background`/`background-many` |
| `FilterOverlay` (panel filtra) | `Panel` plus `TextInput`, czyli **okno komend bez listy podpowiedzi** — prymitywy są podzbiorem `command`, a zawężona lista pod spodem jest treścią `highlight`. Osobny scenariusz nie izolowałby kosztu, którego nie izoluje już któryś z tych dwóch, więc nie dałby się rozliczyć w parze z niczym |
| `HelpScreen` | treść ekranu to `SectionList` (mierzy `sections`), a oprawa i pasek stanu — `chrome-text`. Scenariusz powtarzałby sumę dwóch istniejących |
| `StartupScreen` | nie rysuje klatki; wybiera ekran dna stosu |
| zmiana rozmiaru okna | przebudowę po `SIGWINCH` mierzy **zimna klatka** (kolumna „Zimna”), a nie osobny scenariusz — rozstrzygnięcie kroku 33 |
| pamięć rozmiaru, pełny ekran, ikona, skala treści (krok 37) | żadna z tych czterech rzeczy **nie wchodzi do ścieżki klatki**: rozmiar zapisuje się poza rysowaniem i najwyżej raz na pół sekundy, pełny ekran zmienia wyłącznie rozmiar okna (a ten mierzy zimna klatka, wzorem kroku 33), ikonę rysuje `bin/install-desktop-entry` poza aplikacją, a skala treści jest odczytem pokazywanym w pomocy. Jedyny nowy element klatki to wiersz `ListRow` w zakładce „Aplikacja” — czyli treść mierzona już przez `chrome-text` |
| `MenuOverlay` (menu kontekstowe) | krok 32 **nie dowiózł ani jednego komponentu**, więc nie ma czego mierzyć osobno: okno to `Dialog` (mierzy `popup`) z listą `ListView` w środku (mierzy `text`). Wiersze są dwa albo trzy, czyli mniej, niż niesie którykolwiek z tych dwóch scenariuszy — pomiar pokazałby ich sumę pomniejszoną, a rozliczyć w parze nie dałby się z niczym |
| `EntryTree` (drzewo w panelu modułu) | `TreeView` wewnątrz wcięcia pod obwódkę, czyli to samo, co mierzy `tree`; z prymitywów dochodzi wyłącznie suwak, mierzony osobno przez `scrollbar` |
| schowek systemowy (krok 57) | **nie dokłada do klatki ani jednego prymitywu**, a jedyna widoczna zmiana to jedna pozycja więcej w stopce (`Alt`+`c`) i jedna w spisie miejsca z polem tekstowym (`Alt`+`v`) — czyli ta sama treść w tym samym `TextRun`ie, którą mierzy każdy scenariusz z chromem. Koszt, który krok naprawdę wnosi, leży **na ścieżce każdego bajtu z terminala**: parser wejścia dostaje trzecią gałąź (`ESC ] 5 2 ;`), a jej cena mierzy się osią `--loop`, nie rendererem. Samo kopiowanie i wklejanie dzieje się **poza klatką** — w obsłudze klawisza — i to najwyżej kilka razy na minutę, więc scenariusz mierzyłby czynność, której nikt nie powtarza trzydzieści razy na sekundę |
| stopka kontekstowa (krok 40) | **nie jest osobnym kosztem, tylko tą samą treścią w większym prostokącie**: rośnie liczba znaków w `TextRun`, a nie rodzaj prymitywu, i płaci ją **każdy** scenariusz z chromem naraz. Osobny scenariusz nie izolowałby niczego, czego nie izoluje różnica `chrome` (sam prostokąt, bez tekstu) wobec `chrome-text` (prostokąt z tekstem). Samo **składanie** podpowiedzi — pytanie o ognisko, odsiew powtórzeń i podział na wiersze — leży poza rendererem i mierzy je tor `--loop` |
| `PromptOverlay` (okno nazwy, krok 41) | `Dialog` plus `TextInput` z karetką, czyli **prymitywy będące podzbiorem `command`** — okno komend to to samo pole w tej samej oprawie, tylko z listą podpowiedzi pod spodem. Osobny scenariusz zmierzyłby mniej, niż mierzy `command`, i nie dałby się rozliczyć w parze z niczym |
| `ProgressOverlay` (okno pracy, krok 41) | `Dialog` (mierzy `popup`) z jednym `ProgressBar`em w środku (mierzy `progress`, wraz z trybem o nieznanym postępie). Suma dwóch scenariuszy w mniejszym prostokącie; nowego prymitywu krok nie dowiózł ani jednego |
| operacje na plikach — zapis, liczenie, usuwanie (krok 41) | **nie wchodzą do ścieżki klatki w ogóle**: dzieją się w fazie „aktualizuj stan” pętli (`GameLoop`), a nie w rysowaniu, i są kosztem **systemu plików**, nie renderera. Mierzy je pojemność kawałka (512 wpisów na takt liczenia, 256 na takt usuwania) dobrana z budżetu taktu, a nie wzorzec klatki; gdyby kiedyś trzeba było ją zweryfikować, właściwym torem jest `--loop`, bo tam widać takt bez renderera |
| `ChoiceOverlay` (okno wyboru, krok 42) | `Dialog` (mierzy `popup`) z listą `ListView` w środku (mierzy `text`) — dokładnie ten sam skład, co `MenuOverlay`, i z tego samego powodu pominięty: sześć wierszy to mniej, niż niesie którykolwiek z tych dwóch scenariuszy |
| kopiowanie i przenoszenie (krok 42) | **nie wchodzą do ścieżki klatki w ogóle**, jak operacje kroku 41: praca dzieje się w fazie „aktualizuj stan” (`RunsWork` w `GameLoop`), a jej koszt jest kosztem **systemu plików**. Widoczną częścią jest okno pracy, czyli `ProgressOverlay` z wiersza wyżej — treść ta sama, zmienia się wyłącznie napis licznika. **Pojemności kawałka narzędzie nie mierzy i mierzyć nie może**: scenariusz kopiujący pliki musiałby pisać po dysku w środku przebiegu pomiarowego, czyli mieszać koszt renderera z kosztem nośnika. Kawałek jest **ograniczony z góry** (4 MiB na takt kopiowania, 512 wpisów na takt liczenia), a sprawdza się go tam, gdzie widać skutek — w prawdziwym terminalu, obserwując, czy klatka nadal się odświeża (krok 42, dziennik) |
| moduł `Audio` (krok 36) | **nie rysuje niczego**: nie wnosi ekranu ani komponentu, a jedyny jego ślad w klatce to trzy wiersze zakładki ustawień — czyli treść mierzona już przez `settings`. Muzyka gra we własnym wątku silnika, **poza ścieżką klatki**, więc mierzyć trzeba by nie klatkę, tylko jej brak zmiany; robi to porównanie taktu pętli (`--loop`) przy graniu i bez. **Wykonane w kroku 45**: `--loop` bez muzyki +1,5%, z muzyką graną w tle przebiegu +2,4% — obie liczby w rozrzucie szumu, czyli odpowiedź brzmi „nie wchodzi” |
| `UndoOverlay` (widok stosu cofnięć, krok 44) | krok **nie dowiózł ani jednego komponentu**: okno to `Dialog` (mierzy `popup`) z listą `ListView` w środku (mierzy `text`) — ten sam skład i ten sam powód pominięcia, co `MenuOverlay` i `ChoiceOverlay` |
| kosz i cofanie (krok 44) | **nie wchodzą do ścieżki klatki w ogóle**, jak operacje kroków 41–42: kosz to `rename()` w fazie „aktualizuj stan”, cofnięcie — `rename()` albo praca kawałkowa z kroku 42, a droga „skopiuj do kosza” jest tą samą pracą z innym celem. Widoczną częścią są okna mierzone przez `popup` i `progress` |
| ekran modułu dźwięku i playlista (krok 45) | okno **nie dowiozło ani jednego komponentu rdzenia**: to `ListView` z `Label`ami w strefie środkowej (mierzy `text` i `chrome-text`) plus `TextInput` przy dopisywaniu ścieżki (mierzy `command`). Trzy pozycje zakładki ustawień mierzy `settings`. **Takt modułu** leży poza rendererem, w fazie „aktualizuj stan”, więc mierzy go tor `--loop` — i to jest jedyny scenariusz, w którym ten krok mógł zostawić ślad |
| moduł `Ssh` — spis hostów i okna (krok 48) | ekran **nie dowiózł ani jednego komponentu**: to `Table` w strefie środkowej (mierzy `columns`), a okna to `Dialog` z paskiem o nieznanym postępie (`popup` + `progress`), pytanie `ConfirmOverlay` (`popup`) i `TextInput` (`command`). Trzy pozycje zakładki ustawień mierzy `settings`. **Zawijanie pytania w `ConfirmOverlay`** dokłada najwyżej pięć `TextRun`ów w miejscu jednego — to ta sama treść w większym prostokącie, czyli rzecz mierzona już przez `popup` |
| sesja SSH — łączenie, keyscan, mistrz połączenia (krok 48) | **nie wchodzą do ścieżki klatki w ogóle, i to jest cała teza kroku**: sesja żyje w procesie potomnym, a aplikacji zostaje `poll()` w fazie „aktualizuj stan”, który z definicji nie blokuje. Mierzyć trzeba więc nie klatkę, tylko **brak jej zmiany** — robi to tor `--loop` „przed i po”, bo takt modułu (`NeedsTick`) leży poza rendererem. Czasu samego uścisku dłoni narzędzie nie zna i znać nie może: zależy od sieci, nie od maszyny, więc jego pomiar idzie do dziennika kroku, a nie do wzorca |
| zdalny katalog — panel modułu `Ssh` (krok 49) | ekran **nie dowiózł ani jednego komponentu**: to `Table` w strefie środkowej (mierzy `columns`) z podświetleniem dopasowania filtra (mierzy `highlight`), a pole filtra to `Panel` z `TextInput` — czyli dokładnie ten sam skład, co `FilterOverlay` przeglądarki, i z tego samego powodu pominięty. Dochodzi jedna pozycja zakładki ustawień (mierzy `settings`) |
| odczyt katalogu przez SFTP (krok 49) | **nie wchodzi do ścieżki klatki w ogóle**, jak sesja z kroku 48: odczyt dzieje się w procesie potomnym, a aplikacji zostaje `poll()` w fazie „aktualizuj stan”. Mierzy się więc **brak zmiany taktu** — tor `--loop` „przed i po”. Dwie liczby, których narzędzie nie zna i znać nie ma, idą do dziennika kroku, bo zależą od sieci, a nie od maszyny: **ile obiegów kosztuje katalog** (jeden) i **ile trwa wypis** (~0,93 s otwarcia kanału plus ~0,1 s na pięć tysięcy wpisów na pętli zwrotnej). Rozczytanie pięciu tysięcy wpisów w PHP kosztuje **3,2 ms**, czyli jedną dziesiątą klatki — i to jest jedyna część tej pracy, która dzieje się w procesie aplikacji |
| przesył plików przez SFTP (krok 50) | **nie wchodzi do ścieżki klatki w ogóle**, jak sesja (48) i odczyt katalogu (49): bajty przenosi proces potomny, a aplikacji zostaje `poll()` i jedno `stat()` na pliku roboczym — w fazie „aktualizuj stan”, nigdy w rysowaniu (piąta reguła D46). Okien krok **nie dowiózł**: postęp to `ProgressOverlay` (mierzy `popup` + `progress`), pytanie o zajętą nazwę to `ChoiceOverlay` (`popup`), a ścieżka celu to `PromptOverlay` (`command`). Mierzy się więc **brak zmiany taktu** — tor `--loop` „przed i po”, także z pracą trwającą w tle. Liczby zależne od sieci, a nie od maszyny, idą do dziennika kroku: **32 MB w 1,03 s** przez stojącego mistrza na pętli zwrotnej, czyli parytet z `scp` (1,20 s) i z samym `sftp` (1,10–1,20 s) |
| `Shift` w parserze wejścia (krok 44) | jedyna zmiana kroku na ścieżce klatki — parser przestał odrzucać modyfikatory CSI — leży w **wejściu**, nie w rendererze, więc mierzy ją tor `--loop` (takt: wejście → stan → złożenie klatki), a nie wzorzec obrazu. Koszt to jedno `strpos()` na sekwencji **z parametrami**; klawisze jednobajtowe i sekwencje bez parametrów idą starą drogą |

## Nazwa pliku

**Konwencja od kroku 38:** `RRRR-MM-DD-po-kroku-NN.json` dla wzorca zamykającego
krok planu, `RRRR-MM-DD-nazwa.json` dla wszystkiego innego (`przed-krokiem-NN`,
`window`, `render`). Data w nazwie układa katalog chronologicznie; `--compare`
bez wskazanego pliku bierze **ostatni po nazwie**, a nie po czasie modyfikacji —
kopiowanie plików potrafi przestawić znacznik systemu plików.

**Datę dokłada narzędzie, a przyrostek toru — nie.** `--save=po-kroku-31` daje
`RRRR-MM-DD-po-kroku-31.json`, więc data w wartości opcji zdublowałaby się
w nazwie. Tor natomiast **nie wchodzi do nazwy sam z siebie**: `--text --save=X`
i `--loop --save=X` zapiszą się do tego samego pliku i nadpiszą sixelowy wzorzec
o tej nazwie (zdarzyło się w kroku 31). Przyrostek trzeba podać ręcznie:
`--text --save=po-kroku-31-text`.

Pliki sprzed tej konwencji zostają pod swoimi nazwami: przemianowanie zerwałoby
związek z dziennikami kroków, które je cytują.

Od kroku 38 katalog mieszczą **cztery tory naraz**, rozpoznawalne po przyrostku
w podpisie konfiguracji:

| Przyrostek | Tor | Czym mierzy |
|---|---|---|
| *(brak)* | sixelowy | rysowanie → kwantyzacja → kodowanie |
| `window` | okienkowy (krok 35) | rysowanie → zamiana buforów |
| `text` | tekstowy (krok 38) | prymitywy → bufor komórek → bajty ANSI |
| `loop` | takt pętli (krok 38) | wejście → stan → złożenie klatki, bez renderera |

Liczby torów są z założenia **nieporównywalne** — inne fazy, inna jednostka
pracy — więc wybór bez wskazanego pliku bierze najnowszy wzorzec **porównywalny
z bieżącym przebiegiem**, a nie najnowszy w ogóle. Dzięki temu `--compare`,
`--window --compare` i `--text --compare` trafiają każdy do swojego, choć leżą
obok siebie.

## Jak czytać zawartość

| Klucz | Znaczenie |
|---|---|
| `signature` | konfiguracja pomiaru; dwa wzorce o różnych podpisach są **nieporównywalne** i narzędzie odmówi ich zestawienia |
| `environment` | wersja PHP, wersja ImageMagicka, użyty font, data oraz `loadPerCore` — obciążenie maszyny w chwili pomiaru |
| `options` | pełny zestaw osi, łącznie z liczbą przebiegów i nazwą toru (`track`) |
| `scenarios.<nazwa>` | mediany trzech faz i sumy, rozmiar bloba, znacznik niestabilności, `cold` i `peakBytes` |
| `transfer` | pomiar przesyłu; `null`, gdy przebieg szedł bez terminala |

Czasy są w milisekundach, rozmiary w bajtach.

Trzy kolumny doszły w kroku 38 i każda niesie inną obietnicę:

- **`cold`** — czas **pierwszej klatki rozgrzewki**, czyli pojedyncza próbka,
  nie mediana. Puste są w niej pamięci podręczne klatki (wiersze, płaszczyzna
  spodnia, miniatura), ale proces, font i singletony są już ciepłe. Tyle płaci
  start aplikacji i każda zmiana rozmiaru okna. **Nie podnosi alarmu regresji** —
  rozrzut jednej próbki jest większy niż próg, którym mierzy się mediany.
- **`peakBytes`** — szczyt pamięci procesu w obrębie scenariusza; licznik jest
  zerowany przed każdym, więc liczba nie niesie szczytu poprzedników.
- **`loadPerCore`** — średnie obciążenie z ostatniej minuty na rdzeń. Powyżej
  0,5 `--save` **ostrzega, ale zapisuje**: narzędzie zna tu przesłankę, a nie
  skutek, więc decyzję podejmuje człowiek. `null` znaczy „system nie podaje”.

## Wzorcowe zrzuty (`wzorce-png/`)

Regresję wizualną wykrywa `--png-compare`, a nie oko: bieżąca klatka jest
porównywana z plikiem `wzorce-png/<tor>-<scenariusz>.png` **metryką AE**, czyli
liczbą różniących się pikseli. Przy przekroczeniu progu narzędzie zapisuje obok
obraz różnicy (`-roznica.png`, poza repozytorium) i kończy się kodem 1.

- Próg podaje `--png-threshold` w **promilach** pikseli; domyślnie 0 ‰ dla toru
  sixelowego (potok Imagicka jest deterministyczny) i 5 ‰ dla okienkowego,
  gdzie różnice subpikselowe między sterownikami są normą. **Wzorzec okienkowy
  jest związany z maszyną i wersją sterownika** — to znana cena decyzji
  o trzymaniu go w repozytorium (D64).
- Każdy wzorcowy PNG niesie w metadanych podpis konfiguracji, więc porównanie
  z klatką w innym motywie kończy się słowem „nieporównywalny”, a nie fałszywą
  regresją.
- Zapis: `./bin/render-bench --png-save` (i `--window --png-save`).

## Złote klatki (`tests/Golden/`)

Ten sam katalog scenariuszy służy testom: `tests/Golden/<scenariusz>.txt` to
serializacja **prymitywów** klatki, porównywana przez `GoldenFrameTest`
niezależnie od renderera. Łapie przesunięty napis, zgubiony suwak i panel niższy
o wiersz — czyli to, czego nie widać ani w czasach, ani w rozmiarze bloba.

Regeneracja: `./bin/render-bench --golden-save`, **wyłącznie po przeczytaniu
różnicy**. Złoty plik odnowiony automatem przestaje być testem.

## Czego wzorzec NIE gwarantuje

**Porównywalność między maszynami.** Wynik zależy od procesora, wersji
ImageMagicka i — co najdotkliwsze — od tego, czym maszyna była zajęta w trakcie
pomiaru. Ten sam kod na tej samej maszynie potrafi dać różnicę kilkudziesięciu
procent, gdy w tle chodzi coś jeszcze. Porównanie ma sens **na tej samej
maszynie, przy tej samej konfiguracji, przy porównywalnym obciążeniu**.

Dwa zabezpieczenia są wbudowane w narzędzie, ale żadne nie usuwa tego
ograniczenia:

- przebieg, w którym `max/min` przekroczy 1,35×, jest oznaczany jako
  niewiarygodny i **nie zostaje zapisany** jako wzorzec;
- wiersz oznaczony jako niestabilny nigdy nie podnosi alarmu o regresji.

Rozrzut wewnątrz przebiegu potrafi być wąski, mimo że cały przebieg jest
równomiernie wolniejszy od wzorca — wtedy narzędzie pokaże „regresję”, której nie
ma. Dlatego wynik `--compare` jest **przesłanką, nie werdyktem**; przy
podejrzeniu regresji powtórz oba przebiegi obok siebie.

## Zanim uruchomisz pomiar

**Zwolnij maszynę** — zatrzymaj kompilacje, kontenery i przeglądarkę — i dopiero
wtedy uruchamiaj `bin/render-bench`. Nie chodzi o kosmetykę wyniku: wzorzec
zapisany na obciążonym hoście **psuje każde następne porównanie**, bo różnica
środowiska udaje wtedy różnicę kodu.

Widać to na parze `2026-08-10-po-kroku-21.json` i `2026-08-10-po-kroku-22.json`:
wszystkie dziewięć wspólnych scenariuszy „potaniało” o 8–18%, choć krok 22 nie
tknął ani jednej klasy, przez którą przechodzą. Cała różnica siedzi w tym, czym
maszyna była zajęta.

Reguła jest zapisana w `.claude/skills/light-manager-conventions/SKILL.md`
(punkt 17) i w `CLAUDE.md`. Para `2026-08-10-po-kroku-22.json`
i `2026-08-10-po-kroku-23.json` pokazuje, jak wygląda to samo porównanie, gdy oba
przebiegi szły na zwolnionym hoście: wspólne scenariusze mieszczą się w ±5%,
a jedyna prawdziwa różnica siedzi tam, gdzie zmienił się kod.

## Po co porównywać, skoro kod „i tak tego nie dotyka”

Bo bywa, że dotyka. Krok 23 dołożył pierwszy w projekcie użytkownik prymitywu
`Bar` o grubości `Fill` — gałęzi, która **istniała od kroku 18, a nigdy nie
została zmierzona**, bo nikt jej nie wołał. Kosztowała 73 ms rysowania na klatkę.
Pomiar był jedynym miejscem, w którym mogło to wyjść na jaw ([00-decyzje.md](../plans/00-decyzje.md), D44).

Krok 24 powtórzył tę lekcję w drugim wariancie: obwódka panelu kosztuje ~13 ms,
ale **nikt tego nie wiedział**, bo obwódki rysowały się wyłącznie na płaszczyźnie
spodniej, pamiętanej między klatkami — w tabeli pomiaru pokazywały się jako
0,0 ms („same ramki”). Dopiero przeniesienie ich do treści wyceniło je na 27 ms za
dwie (D45). Wspólny mianownik obu przypadków: **gałąź wykonywana dotąd raz na
uruchomienie po przeniesieniu do klatki kosztuje tyle, ile nikt nie sprawdził.**

Z tego samego powodu próg regresji świadomie nie jest pilnowany testem w bramce
jakości ([00-decyzje.md](../plans/00-decyzje.md), D28).
