# 2. Słownik domenowy — Klatka, komponenty i podział ekranu

> Część rozdziału 2. Pojęcia i wstęp: [slownik.md](slownik.md).
> Spis rozdziałów: [docs/architecture.md](../../architecture.md).

Co żyje między klatkami, czego komponentowi nie wolno, jak dzieli się ekran i skąd bierze się miejsce na obie osie.

## Stan żyjący między klatkami

**Komponent jest bezstanowy i powstaje na nowo w każdej klatce**, więc nie
zapamięta niczego, co użytkownik zrobił przed chwilą. Co ma przeżyć klatkę,
mieszka **obok** komponentu, a właścicielem jest ekran:

| Klasa | Katalog | Co pamięta | Od kroku |
|---|---|---|---|
| `ScrollWindow` | `Presentation/Ui` | Który wycinek listy jest widoczny i jak podąża za kursorem. Od kroku 55 pamięta ponadto, czy **przewinięto go ręcznie**: kółko odczepia okno od kursora, a pierwszy ruch kursora przyczepia je z powrotem. | 18 |
| `SectionState` | `Presentation/Ui` | Które sekcje są zwinięte i na której stoi kursor. | 22 |
| `SplitState` | `Presentation/Ui` | Który panel podziału jest czynny — wraz z regułą, że wyłączony podział sprowadza ognisko na pierwszy. Od kroku 55 także **proporcja granicy** i jej przeciąganie: chwyt na dwóch stykających się obwódkach, granice 20–80%, zapis w ustawieniach modułu po zwolnieniu przycisku. | 24 |
| `TreeState` | `Presentation/Ui` | Które gałęzie drzewa są rozwinięte i na którym węźle stoi kursor. | 31 |
| `SelectionState` | `Presentation/Ui` | Prostokąt zaznaczony wskaźnikiem: kotwica, komórka bieżąca i znacznik trwania przeciągnięcia. **Jedyna z tej rodziny należąca do rdzenia** (`LoopState`), bo zaznaczenie dotyczy klatki, a nie treści jednego ekranu. | 56 |

Piąta różni się od czterech pozostałych **właścicielem**, i to jest cała
różnica: `SelectionState` mieszka w `LoopState`, bo prostokąt zaznaczenia
przecina panele, ekrany i okna nakładane. Zamiast `useContext()` ma
`useFrame(ekran, okno, wiersze, kolumny)` — pytanie „czy to nadal ta sama
klatka”, zadawane raz na takt przez `FrameComposer`, czyli w jedynym miejscu,
w którym widać wszystkie trzy powody skasowania naraz.

Trzy z nich mają tę samą metodę `useContext(string)` i to nie jest przypadek:
zmiana kontekstu — inny katalog, inna zakładka, inny opisywany plik — zaczyna
oglądanie od początku. `SectionState` trzyma przy tym zwinięcie **pod kluczem
sekcji, a nie pod jej numerem**, żeby sekcja, która zniknęła z listy i wróciła,
wróciła w tym samym stanie.

`TreeState` powtarza tę regułę o wymiar głębiej i różni się od `SectionState`
trzema rzeczami naraz — wszystkie trzy wynikają z tego, że drzewo zmienia się
częściej niż lista sekcji:

- trzyma **rozwinięcia**, a nie zwinięcia: gałąź bez wpisu jest **zwinięta**,
  sekcja bez wpisu — **rozwinięta**;
- jego kursor jest **kluczem, a nie numerem**, bo numer wiersza zmienia każde
  rozwinięcie i zwinięcie czegokolwiek powyżej;
- **zwinięcie gałęzi przenosi na nią kursor**, zamiast zostawiać go na numerze,
  który przejął przypadkowy sąsiad.

Rozwinięcia przeżywają przy tym zmianę kontekstu — kursor nie. Klucz gałęzi jest
bezwzględny, więc po powrocie do poprzedniego korzenia znaczy dokładnie to samo,
i na tym stoi obietnica „drzewo wraca takie, jakie się je zostawiło”.

## Element żyjący własnym rytmem a takt pętli (od kroku 23)

Niektóre elementy zmieniają wygląd **bez udziału użytkownika**: karetka mruga,
a wypełnienie paska postępu wędruje. Reguła jest jedna i ma dwie części.

**Nikt nie wymusza przerysowania, bo nie ma czego wymuszać.** Pętla główna
rysuje klatkę w każdym takcie — 30 razy na sekundę — niezależnie od tego, czy
cokolwiek się zmieniło, i tak jest od kroku 09. Element ruchomy nie potrzebuje
więc żadnej ścieżki „obudź pętlę”: wystarczy, że w kolejnej klatce narysuje się
inaczej. Gdyby pętla kiedykolwiek zaczęła oszczędzać klatki przy bezruchu, to
**ta** zmiana musiałaby przynieść taką ścieżkę ze sobą — i rozliczyć ją wobec
elementów ruchomych, a nie odwrotnie.

**Zegar przychodzi z zewnątrz, nigdy z `microtime()` w środku.** Czas klatki zna
pętla i tylko ona (`LoopState::tick()`); do elementu wędruje przez
`Presentation\Ui\NeedsTime` — interfejs deklarowany osobno, jak `Resettable`,
więc ekran i okno bez ruchomej treści nie deklarują niczego. Składanie klatki
pyta o niego **ekran** i **okno nakładane**, zawsze przed narysowaniem. Powód
jest testowy: `microtime()` w komponencie zamieniłby ruch w coś, czego nie da się
sprawdzić bez czekania, a tak test podaje własną chwilę i ogląda element
w dowolnym miejscu cyklu.

Cena jest jedna i trzeba ją znać: **element ruchomy z założenia nie trafia do
pamięci podręcznej wierszy** (D34) — jego wiersz jest w każdej klatce inny, więc
rasteryzuje się od nowa. Dlatego pasek postępu ma własny scenariusz pomiaru.

## Komponent nie czyta (od kroku 29)

`TextView` pokazuje treść pliku, a **pliku nie zna**: dostaje listę wierszy już
zdekodowanych, z rozwiniętymi tabulatorami i oznaczonymi znakami sterującymi.
Granica jest ta sama, co między komponentem a rendererem, tyle że po drugiej
stronie: *komponent wie, jak wyglądać* — a nie skąd wziąć treść.

Wszystko, co ma z wejściem-wyjściem wspólnego, zostaje po stronie modułu
(`TextPreviewPort` i jego usługa w `Module/FileInfo/Infrastructure`), bo to tam
mieszka wiedza o tym, co wolno przeczytać i jak długo. Rdzeń nie wie, czym jest
plik — reguła 15 obowiązuje tu tak samo, jak przy podglądzie obrazu.

**Odczyt idzie przesuwnym oknem, jak w edytorze**, i to jest rozstrzygnięcie
o wadze wzorca: w pamięci siedzą wyłącznie te wiersze, które właśnie widać,
a przewinięcie porzuca poprzednie i doczytuje następne. Konsekwencje, które
z tego wynikają i o których trzeba pamiętać przy każdym podobnym podglądzie:

- **Miejsce w pliku to bajt, nie numer wiersza** (`TextAnchor`) — tylko bajt
  pozwala usiąść w środku pliku bez przeczytania wszystkiego przed nim. Numer
  wiersza jedzie obok i liczy się przyrostowo.
- **Ile czytać, wiadomo dopiero przy rysowaniu**, bo budżet odczytu liczy się
  z geometrii panelu. Stąd podgląd powstaje w `draw()`, a przewinięcie
  zamówione klawiszem czeka na rozliczenie — dokładnie jak `ScrollWindow`
  rozdziela `scrollBy()` od `clamp()`.
- **Suwak liczy się w bajtach**, bo liczby wierszy pliku nie znamy i poznać jej
  nie chcemy: kosztowałaby przejście przez cały plik przy pierwszym pokazaniu.
- **Praca kawałkowa (D46) tu nie obowiązuje** i nie musi: jedno okno to
  kilkadziesiąt kilobajtów, więc mieści się w klatce z zapasem. Wzorzec z kroku
  25 dotyczy prac, których w klatce wykonać **nie da się**.
- **Bajt to nie znak** — podgląd czyta także UTF-16 i UTF-32, więc podział na
  wiersze szuka znaku nowej linii w kodowaniu źródła i **wyłącznie na granicy
  jednostki kodowej**. Bajty `0A 00` wypadają w UTF-16LE także w środku pary
  innych znaków; wzięte za koniec wiersza przesunęłyby kotwicę o pół znaku
  i wszystko po niej byłoby śmieciem. Wszystkie kotwice są z tego samego powodu
  wyrównane do jednostki, a bufor urwany budżetem docina się do niej.

## Komponent dostaje drzewo spłaszczone (od kroku 31)

`TreeView` rysuje drzewo, a **drzewa nie zna**: dostaje listę `TreeNode`ów, w której
gałęzie zostały już rozwinięte do wierszy. Węzeł nie ma wskaźnika na rodzica ani
listy dzieci i to nie jest oszczędność — komponent, który sam schodziłby po
gałęziach, musiałby wiedzieć, **skąd biorą się dzieci**, a biorą się z odczytu
katalogu. Wracamy tym samym do granicy z kroku 29: *komponent wie, jak wyglądać* —
a nie skąd wziąć treść. Spłaszcza więc moduł (`BrowserTree`), dokładnie tak, jak
w kroku 22 spłaszczał ekran.

Konsekwencje, które z tego wynikają:

- **Węzeł niesie prowadnice, a nie samą głębokość** (`guides`, po jednej wartości
  logicznej na przodka). Z liczby poziomów nie da się narysować pionowej kreski:
  poziom przodka, który był ostatnim dzieckiem, musi zostać pusty. Głębokość jest
  przez to długością tej listy — dwa pola mówiące to samo rozjechałyby się przy
  pierwszym spłaszczeniu liczonym inaczej.
- **Spłaszczenie jest zapamiętane, nie liczone co klatkę.** Klatka, w której nic
  się nie zmieniło, kosztuje trzy porównania zamiast tysiąca konstruktorów — i to
  jest cała odpowiedź na pytanie, czy rozwinięta gałąź o tysiącu wpisów gubi
  klatkę.
- **Gałąź czyta się na żądanie i najwyżej jedną na klatkę.** Rozwinięcie klawiszem
  czyta od razu, bo kosztuje tyle, co `Enter` w liście i użytkownik właśnie o to
  poprosił. Gałęzie **odtwarzane** — po powrocie do katalogu, po przełączeniu
  wpisów ukrytych — dochodzą po jednej na takt, wzorcem pracy kawałkowej (D46):
  dziesięć odczytów naraz nie mieści się w klatce, a dziesięć klatek to jedna
  trzecia sekundy.
- **Drzewo pokazuje to, co przeczytało.** Pamięć odczytanych gałęzi jest trwalsza
  od korzenia, więc wejście katalog niżej i powrót nie kosztuje ani jednego
  sięgnięcia na dysk. Ceną jest brak śledzenia zmian w systemie plików — świadomy
  i wykluczony z zakresu kroku.

## Ósmy prymityw: dlaczego słownik został otwarty (krok 30)

> **Sprostowanie liczby (krok 56, zapisane tu w kroku 64).** Kształtów jest
> **siedem**, nie osiem: `TextRun`, `TextMark`, `RoundRect`, `Bar`, `Bitmap`,
> `Scrollbar`, `CornerBrackets` — sprawdza się to jednym `grep`em po
> `implements Primitive`. Krok 30 nazwał `TextMark` ósmym i stąd nazwa tego
> podrozdziału; reszta jego treści jest prawdziwa, bo mówi o **otwarciu
> słownika**, a nie o liczbie. Rozjazd wyszedł przy pisaniu przewodnika
> dewelopera: `SKILL.md` niósł sprostowanie od kroku 56, a rozdział — nie.

Słownik prymitywów był **zamknięty od kroku 18** i przez dwanaście kroków nikt
go nie ruszył — łącznie z krokiem 19, w którym karetka pola tekstowego udała
podświetlenie parą „wypełnienie plus napis”, żeby nowego kształtu nie dokładać.
Krok 30 otwiera go raz, na jawną zgodę użytkownika (D48), i dokłada **jeden**
kształt: `TextMark`, czyli napis na własnym tle.

Ważniejsze od samego dołożenia jest to, **czego nie dołożono**. Wyjściowa
propozycja planu — „samo tło pod fragmentem” — okazała się przy rozpisaniu
synonimem: prostokąt wypełniony rolą motywu **już jest** w słowniku dwa razy,
jako `Bar` z `Weight::Fill` i jako `RoundRect` bez obrysu. Ósmy kształt musiał
więc być czymś, czego żaden z siedmiu nie umie, i jest: **związaniem pisma
z tłem w jednej rzeczy**. Trzy konsekwencje, wszystkie zmierzone albo widoczne
w kodzie:

- **Tor sixelowy składa jedną bitmapę zamiast dwóch.** `compositeImage` kosztuje
  tyle, ile kształt, ale samo wywołanie kosztuje zawsze — a przy filtrze
  trafiającym w każdy wiersz listy wywołań jest tyle, ile wierszy.
- **Tor tekstowy degraduje kształt do atrybutu, nie do treści.** Tło i kolor
  pisma to dokładnie te dwa atrybuty, które komórka siatki znakowej ma, więc
  dopasowanie widać tam co do znaku tak samo, jak w torze graficznym. Odwracanie
  atrybutów, które plan kroku dopuszczał jako ostateczność, okazało się zbędne.
- **`TextRun` zostaje nietknięty**, a wraz z nim koszt wiersza bez dopasowania.
  To jest najważniejsze kryterium kroku 30 i pilnuje go zarówno test
  strukturalny (`TableTest`: wiersz bez zakresów oddaje te same podpisy, co
  przed krokiem), jak i para scenariuszy pomiaru `columns` i `highlight`.

Zakresy dopasowania niesie **wiersz**, nie gotowy podział na kawałki
(`TableRow::$marks`, klucz = numer kolumny; pusta tablica domyślnie).
Przesunięcie liczy się w **znakach**, bo rysuje się je w kolumnach — nazwa
`zażółć.txt` ma dziewięć znaków i trzynaście bajtów, a zakres liczony bajtami
wylądowałby w połowie znaku.

## Efekty specjalne (od kroku 46)

Moduł dźwięku jest **pierwszym odbiorcą zdarzeń** i nie zna ani jednej ich nazwy:
dostaje napis, zagląda do mapy „zdarzenie → plik” i gra albo milczy. Zdarzenie
dołożone gdziekolwiek indziej pojawia się przez to w jego oknie bez ani jednej
zmiany w module.

Mapa mieszka **w tym samym pliku, co playlista** (`~/.light-manager/audio.json`,
klucz `hooks`) — na tym właśnie polegało rozstrzygnięcie D82 nr 3, podjęte krok
wcześniej. Porty są przy tym dwa (`PlaylistPort`, `EffectMapPort`) mimo jednej
usługi, bo odbiorcy są różni i żaden nie ma powodu widzieć cudzych metod.

Efekt gra **na muzyce, nie zamiast niej**: `AudioPort::playEffect()` sięga po
**drugi uchwyt `Sound`** z tego samego silnika, który miksuje oba (sprawdzone
przy planowaniu fazy). Uchwyt jest jeden, więc nowy efekt przerywa poprzedni —
przy dźwiękach trwających pół sekundy to jest wybór, a nie ograniczenie. Kursor
efektu cofa się przed każdym zagraniem, bo `play()` na przerwanym wznowiłby go od
miejsca przerwania.

Trzy rzeczy, które przy dokładaniu odbiorcy trzeba znać:

- **odbiór nie dotyka dysku** — mapę wczytuje **takt**, a dostępność plików
  przelicza się przy otwarciu okna; zdarzenie, które padło przed pierwszym
  taktem, milczy;
- **minimalny odstęp należy do odbiorcy** — trzymana strzałka daje trzydzieści
  zdarzeń kursora na sekundę, więc odtwarzacz efektów milczy przez 100 ms po
  każdym zagraniu **tego samego** zdarzenia; publikujący nie ma prawa wiedzieć,
  że ktoś zamienia jego zdarzenie na dźwięk;
- **kłopotu z odtworzeniem nie zgłaszamy nikomu** — jesteśmy w środku cudzej
  czynności, a zdanie w pasku stanu nadpisałoby to, które ta czynność właśnie
  o sobie powiedziała.

Okno modułu rośnie do **dwóch paneli** (`Split`, `SplitState`, `Tab`): po lewej
spis zdarzeń z przypisaniami (`Table`, trzy kolumny), po prawej playlista.
Spis składa się **ze słownika, a nie z mapy** — widać wszystkie zdarzenia, także
nieprzypisane, wyszarzone i z kreską. Podział zachowuje się przy tym **inaczej niż
w przeglądarce**: poniżej progu szerokości widać panel **z ogniskiem**, a nie
zawsze pierwszy, bo panele są tu dwiema różnymi rzeczami, a nie dwoma widokami
tego samego.

Wyciszenie i zabranie pliku to **dwie różne czynności** (spacja i `F8`):
przełącznik siedzi przy przypisaniu, a nie w ustawieniach, bo mapa i tak trzyma po
wierszu na zdarzenie, a pozycja w zakładce musiałaby powstać dla każdego z osobna.
W zakładce stoją za to dwie pozycje wspólne: **przełącznik uciszający wszystko
naraz** i **własna głośność efektów** — bo klik zmiksowany na poziomie muzyki
ginie pod nią albo krzyczy w ciszy.

## Jeden ekran, dwa panele (od kroku 24)

Podział ekranu **nie znosi zasady „jeden ekran naraz”** i to jest jego
najważniejsze rozstrzygnięcie. `ScreenStack` ma nadal dno i jedno piętro nad nim,
`ScreenInterface` ma nadal sześć metod, a `InputHandler` nadal oddaje klawisz
jednemu ekranowi. Podział dzieje się **wewnątrz** ekranu: `F1`, `F2` i skrót
modułu zastępują go w całości, razem z oboma panelami.

Wynika z tego reguła własności: **podział należy do modułu, nie do rdzenia.**
Rdzeń daje klocek (`Split`) i pamięć ogniska (`SplitState`); to, czy dwa panele
w ogóle powstaną, co w nich stoi i którym klawiszem chodzi ognisko, rozstrzyga
ekran — a ustawienia podziału leżą w podprzestrzeni modułu, nie w kluczach
rdzenia.

Jeden wyjątek od podziału obowiązków musiał przy tym powstać i ma własny
interfejs: **`Presentation\Ui\DrawsOwnFrame`**. Rdzeń rysuje obwódki stref (reguła
kroku 21), ale ekran podzielony potrzebuje **dwóch** obwódek zamiast jednej,
a rdzeń nie wie, który panel jest czynny, więc nie ma czym pokazać ogniska.
Ekran deklarujący ten interfejs dostaje **cały** prostokąt strefy i oddaje własną
oprawę; odpowiedź zależy od ustawień i od szerokości okna, więc jest metodą,
a nie samą deklaracją klasy. `ScreenInterface` zostaje przez to nietknięty po raz
trzeci — tą samą drogą, którą idą `Resettable`, `ReadsContext` i `NeedsTime`.

**Metoda oddaje prymitywy, a nie rysuje na miejscu, i to jest w niej
najważniejsze.** Rdzeń kładzie je na płaszczyźnie **spodniej** — tej samej, którą
renderer pamięta między klatkami (krok 17, dźwignia 4). Powód jest zmierzony,
a nie estetyczny: obwódka z wygładzanym obrysem kosztuje **około 13 ms**, więc
dwie ramki rysowane co klatkę zabierały 27 ms z 33 ms budżetu. Po przeniesieniu
na płaszczyznę spodnią kosztują tyle, co pierwsza klatka po zmianie — a pamięć
odświeża się sama, bo podpis płaszczyzny obejmuje każdy prymityw: przeniesienie
ogniska albo zmiana katalogu zmienia podpis i oprawa powstaje na nowo raz.

Zasada ogólniejsza, którą to wyraża: **wszystko, co między klatkami się nie
zmienia, należy do płaszczyzny spodniej — niezależnie od tego, kto to narysował.**

## Rozdział miejsca: jedna reguła na dwie osie (od kroku 27)

Miejsce dzieli się w tej aplikacji **wszędzie tak samo**, niezależnie od osi:
wiersze między strefami klatki, kolumny między polami wiersza listy. Regułę
niesie `Container\Distribution`, a to, czego chce uczestnik podziału — `Span`:
minimum, rozmiar preferowany i kolejność ustępowania.

Reguła ma trzy zdania i wszystkie trzy obowiązują obie osie:

1. uczestnicy o podanej mierze biorą swoje, elastyczni dzielą resztę;
2. gdy brakuje, oddają miejsce w kolejności `yieldOrder` — każdy do swojego
   minimum, dopiero potem ustępuje następny;
3. uczestnik, któremu zostałoby mniej niż minimum, **znika w całości**.

Punkt trzeci jest sednem i był rozstrzygany trzy razy niezależnie, zanim dostał
jedno miejsce w kodzie: pas podglądu w kroku 12, drabinka stref w 13, kolumny
w 27. Za każdym razem odpowiedź brzmiała tak samo — **element przycięty w połowie
jest gorszy niż nieobecny**.

Miara ma dwie postacie stałe i różnią się dokładnie minimum:

- `Span::fixed()` — **kurczy się stopniowo** do zera. Pas podglądu niższy o wiersz
  jest nadal pasem podglądu.
- `Span::rigid()` — **tyle albo nic**. Kolumna z datą zwężona o trzy znaki nie
  jest „węższą datą”, tylko napisem `2026-08-…`, a przy okazji zabiera te znaki
  nazwie, która by je wykorzystała.

Minimum uczestnika elastycznego jest przy tym **progiem ustępowania sąsiadów**,
a nie obietnicą: dopóki suma minimów mieści się w prostokącie, nikt nie ustępuje.
Kolumna nazwy z minimum równym czterem znaczyłaby więc „nazwa może zejść do
czterech znaków, byle data została” — czyli odwrotność tego, czego chce lista.
