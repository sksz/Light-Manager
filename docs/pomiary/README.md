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
| `Tabs`, `Choice`, `Toggle`, `Button`, `Spacer`, `VStack` | `settings` | krok 38; rozlicza się w parze z `chrome-text` |
| `TreeNode`, `TreeView` | `tree` | krok 31; wcięcie i prowadnice, znak spoza ASCII **na poziom**; rozlicza się w parze z `sections` — ten sam prostokąt, ten sam `ListView`, różnicą jest sam przedrostek |
| proces potomny obok pętli | `background` | jedyny scenariusz sięgający poza PHP |

### Pominięcia i ich powody

| Element | Dlaczego bez scenariusza |
|---|---|
| `ConfirmOverlay` | ta sama obwódka i te same wiersze, co `Dialog` — koszt mieści się w `popup` (zapisane w kroku 28) |
| `MessageOverlay` | rysuje się `Dialog`iem, czyli jest `popup`em pod inną nazwą |
| `FilterOverlay` (panel filtra) | `Panel` plus `TextInput`, czyli **okno komend bez listy podpowiedzi** — prymitywy są podzbiorem `command`, a zawężona lista pod spodem jest treścią `highlight`. Osobny scenariusz nie izolowałby kosztu, którego nie izoluje już któryś z tych dwóch, więc nie dałby się rozliczyć w parze z niczym |
| `HelpScreen` | treść ekranu to `SectionList` (mierzy `sections`), a oprawa i pasek stanu — `chrome-text`. Scenariusz powtarzałby sumę dwóch istniejących |
| `StartupScreen` | nie rysuje klatki; wybiera ekran dna stosu |
| zmiana rozmiaru okna | przebudowę po `SIGWINCH` mierzy **zimna klatka** (kolumna „Zimna”), a nie osobny scenariusz — rozstrzygnięcie kroku 33 |
| pamięć rozmiaru, pełny ekran, ikona, skala treści (krok 37) | żadna z tych czterech rzeczy **nie wchodzi do ścieżki klatki**: rozmiar zapisuje się poza rysowaniem i najwyżej raz na pół sekundy, pełny ekran zmienia wyłącznie rozmiar okna (a ten mierzy zimna klatka, wzorem kroku 33), ikonę rysuje `bin/install-desktop-entry` poza aplikacją, a skala treści jest odczytem pokazywanym w pomocy. Jedyny nowy element klatki to wiersz `ListRow` w zakładce „Aplikacja” — czyli treść mierzona już przez `chrome-text` |
| `MenuOverlay` (menu kontekstowe) | krok 32 **nie dowiózł ani jednego komponentu**, więc nie ma czego mierzyć osobno: okno to `Dialog` (mierzy `popup`) z listą `ListView` w środku (mierzy `text`). Wiersze są dwa albo trzy, czyli mniej, niż niesie którykolwiek z tych dwóch scenariuszy — pomiar pokazałby ich sumę pomniejszoną, a rozliczyć w parze nie dałby się z niczym |
| `EntryTree` (drzewo w panelu modułu) | `TreeView` wewnątrz wcięcia pod obwódkę, czyli to samo, co mierzy `tree`; z prymitywów dochodzi wyłącznie suwak, mierzony osobno przez `scrollbar` |
| stopka kontekstowa (krok 40) | **nie jest osobnym kosztem, tylko tą samą treścią w większym prostokącie**: rośnie liczba znaków w `TextRun`, a nie rodzaj prymitywu, i płaci ją **każdy** scenariusz z chromem naraz. Osobny scenariusz nie izolowałby niczego, czego nie izoluje różnica `chrome` (sam prostokąt, bez tekstu) wobec `chrome-text` (prostokąt z tekstem). Samo **składanie** podpowiedzi — pytanie o ognisko, odsiew powtórzeń i podział na wiersze — leży poza rendererem i mierzy je tor `--loop` |
| `PromptOverlay` (okno nazwy, krok 41) | `Dialog` plus `TextInput` z karetką, czyli **prymitywy będące podzbiorem `command`** — okno komend to to samo pole w tej samej oprawie, tylko z listą podpowiedzi pod spodem. Osobny scenariusz zmierzyłby mniej, niż mierzy `command`, i nie dałby się rozliczyć w parze z niczym |
| `ProgressOverlay` (okno pracy, krok 41) | `Dialog` (mierzy `popup`) z jednym `ProgressBar`em w środku (mierzy `progress`, wraz z trybem o nieznanym postępie). Suma dwóch scenariuszy w mniejszym prostokącie; nowego prymitywu krok nie dowiózł ani jednego |
| operacje na plikach — zapis, liczenie, usuwanie (krok 41) | **nie wchodzą do ścieżki klatki w ogóle**: dzieją się w fazie „aktualizuj stan” pętli (`GameLoop`), a nie w rysowaniu, i są kosztem **systemu plików**, nie renderera. Mierzy je pojemność kawałka (512 wpisów na takt liczenia, 256 na takt usuwania) dobrana z budżetu taktu, a nie wzorzec klatki; gdyby kiedyś trzeba było ją zweryfikować, właściwym torem jest `--loop`, bo tam widać takt bez renderera |
| `ChoiceOverlay` (okno wyboru, krok 42) | `Dialog` (mierzy `popup`) z listą `ListView` w środku (mierzy `text`) — dokładnie ten sam skład, co `MenuOverlay`, i z tego samego powodu pominięty: sześć wierszy to mniej, niż niesie którykolwiek z tych dwóch scenariuszy |
| kopiowanie i przenoszenie (krok 42) | **nie wchodzą do ścieżki klatki w ogóle**, jak operacje kroku 41: praca dzieje się w fazie „aktualizuj stan” (`RunsWork` w `GameLoop`), a jej koszt jest kosztem **systemu plików**. Widoczną częścią jest okno pracy, czyli `ProgressOverlay` z wiersza wyżej — treść ta sama, zmienia się wyłącznie napis licznika. **Pojemności kawałka narzędzie nie mierzy i mierzyć nie może**: scenariusz kopiujący pliki musiałby pisać po dysku w środku przebiegu pomiarowego, czyli mieszać koszt renderera z kosztem nośnika. Kawałek jest **ograniczony z góry** (4 MiB na takt kopiowania, 512 wpisów na takt liczenia), a sprawdza się go tam, gdzie widać skutek — w prawdziwym terminalu, obserwując, czy klatka nadal się odświeża (krok 42, dziennik) |
| moduł `Audio` (krok 36) | **nie rysuje niczego**: nie wnosi ekranu ani komponentu, a jedyny jego ślad w klatce to trzy wiersze zakładki ustawień — czyli treść mierzona już przez `settings`. Muzyka gra we własnym wątku silnika, **poza ścieżką klatki**, więc mierzyć trzeba by nie klatkę, tylko jej brak zmiany; robi to porównanie taktu pętli (`--loop`) przy graniu i bez |

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
