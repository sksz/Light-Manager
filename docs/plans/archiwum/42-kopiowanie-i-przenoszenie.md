# Krok 42 — Kopiowanie i przenoszenie po kawałku, z postępem

> **Skąd ten krok.** Powstał 2026-08-13 razem z całą Fazą XIV. Jest tą jej
> częścią, w której operacja przestaje mieścić się w klatce — i dlatego stoi
> osobno od kroku 41, a nie obok jego trzech czynności.

## Status

**Ukończony** (2026-08-15). Rozstrzygnięcia startowe — dziesięć, z czego dwa
wynikły z odpowiedzi, a trzy odwołują zapisy tego pliku — leżą
w [00-decyzje.md](../00-decyzje.md), D79.

> **Uwaga do czytania tego pliku.** Powstał 2026-08-13, przed krokami 41 i 47,
> i **trzy jego założenia są nieaktualne** — zapowiedział to D78
> (rozstrzygnięcie 9), a sprawdzenie kodu na starcie potwierdziło co do słowa.
> Miejsca, których to dotyczy, są niżej opisane wraz z poprawką; nie czytaj ich
> jako obowiązujących.

## Cel

Skopiowanie i przeniesienie tego, na czym stoi kursor, do katalogu **drugiego
panelu** — bez zatrzymania pętli, z widocznym postępem i z możliwością
przerwania.

Miarą powodzenia jest zdanie: **skopiowanie pliku wielkości płyty nie gubi ani
jednej klatki, pasek postępu mówi prawdę, a przerwanie w połowie nie zostawia na
dysku pliku, który wygląda na gotowy.**

## Trudność strukturalna — **rozstrzygnięta precedensem, nie wyborem**

> **Poprawka ze startu kroku (2026-08-15, D79, rozstrzygnięcie 1 i 2).** Cała
> sekcja poniżej opisuje świat sprzed kroku 41 i zachowana jest wyłącznie jako
> zapis rozumowania. Krok 41 dowiózł czwarty wariant, którego ta lista nie zna,
> bo wtedy nie istniał: **pracę posuwa okno nakładane** (`Presentation\Ui\RunsWork`),
> pytane raz na takt w `GameLoop`, w fazie „aktualizuj stan”. Praca nie posuwa
> się więc ani w `draw()` ekranu, ani w pętli ponad ekranami — a wyjście do
> innego ekranu w jej trakcie jest niemożliwe, bo okno nie oddaje klawiszy niżej.
> Granica, którą ta sekcja kazała zapisać, przestała istnieć razem z problemem.

## Trudność strukturalna — treść pierwotna (2026-08-13)

Aplikacja umie już pracę dłuższą od klatki i umie ją **dwiema drogami**: własną,
kawałkową (`ChecksumPort`, krok 25, D46) i cudzą, w procesie potomnym
(`BackgroundProcessPort`, krok 26, D47). Użytkownik rozstrzygnął (2026-08-13,
[00-decyzje.md](../00-decyzje.md), D66, rozstrzygnięcie 3), że kopiowanie idzie
**drogą własną, kawałkową** — bez `cp` i bez procesu potomnego.
Powody są trzy i wszystkie są konkretne: proces tłowy prowadzi **jedną** pracę
naraz, więc kopiowanie wypierałoby `du` z modułu opisu pliku; postęp procesu jest
nieznany, a bajty skopiowane własną ręką są policzone co do jednego; zachowanie
`cp` zależy od systemu, a zachowanie własnej pętli — nie.

Z tego wynika trudność, której żaden dotychczasowy krok nie miał:

**Praca kawałkowa posuwa się dziś w `draw()` ekranu, który ją zamówił.** Komentarz
w `FileInfoScreen::draw()` mówi to wprost i jest tam napisany świadomie: „praca nie
posuwa się do przodu, gdy użytkownik patrzy na co innego”. Dla sumy kontrolnej
opisywanego pliku to jest **zaleta** — nikt nie liczy sumy pliku, na który nikt nie
patrzy. Dla kopiowania to jest **wada**: użytkownik, który wystartował kopiowanie
i przeszedł do ustawień, wraca po minucie do pracy stojącej dokładnie tam, gdzie ją
zostawił, i nie ma jak się dowiedzieć dlaczego.

Rozstrzygnięcie (pytanie 1) ma trzy warianty, wszystkie z ceną:

1. **Kopiowanie posuwa pętla główna, nie ekran.** Praca żyje ponad ekranami; pasek
   postępu musi mieć wtedy miejsce widoczne z każdego ekranu, czyli pasek stanu —
   a to jest strefa, o którą upomina się już krok 40.
2. **Kopiowanie posuwa ekran przeglądarki, jak dziś suma kontrolna.** Zero nowych
   mechanizmów, za to zapisana wprost granica: wyjście z przeglądarki wstrzymuje
   pracę, a użytkownik ma się o tym dowiedzieć **z ekranu**, a nie z zaskoczenia.
3. **Ekran nie oddaje klawiszy, dopóki praca trwa.** Najprostsze i najbardziej
   szorstkie; sprzeczne z regułą pętli, która nigdy dotąd niczego nie blokowała.

Rekomendacja: **wariant 2 w tym kroku, z granicą zapisaną w dokumentacji**, i to
nie z ostrożności. Wariant 1 wymaga miejsca na pasek postępu widoczny zewsząd,
czyli tej samej strefy, którą przelicza krok 40 — dwa kroki przebudowujące pasek
stanu z dwóch powodów naraz to najprostsza droga do sprzeczności.

## Zależności

- **Krok 41** twardo i całkowicie: port operacji, okno nazwy, odświeżanie paneli
  i obsługa niepowodzeń powstają tam. Ten krok dokłada do nich czas.
- **Krok 25** (D46) — wzorzec pracy kawałkowej wraz z kształtem portu:
  `begin()`, `advance(bajty)`, `state()`, `stop()`. Kopiowanie jest jego **trzecim**
  zastosowaniem i pierwszym, które nie tylko czyta.
- **Krok 23** — `ProgressBar`, wraz z trybem „postęp nieznany”, który krok 26 dał
  pierwszemu prawdziwemu użytkownikowi. Kopiowanie katalogu bez policzonej sumy
  rozmiarów jest drugim.
- **Krok 24** — dwa panele, czyli **skąd i dokąd**. Bez podziału kopiowanie nie ma
  celu innego niż wpisany ręcznie.
- **Krok 26** — jako **wzorzec do porównania i odrzucenia**: kroku nie robi się
  procesem potomnym i powód jest zapisany wyżej.
- **Krok 38** — wzorce i przebiegi; pomiar w trzech torach.

Z krokiem **43** (zaznaczenie wielokrotne) łączy go zależność **miękka i w jedną
stronę**: jeśli 43 wykona się pierwszy, kopiowanie od razu bierze zbiór zamiast
jednego wpisu; jeśli później, praca kawałkowa musi umieć przyjąć **listę** źródeł
od pierwszego dnia — i dlatego umie ją niezależnie od kolejności (punkt 2).

Od kroków **31, 32, 36, 37, 39 i 40** nie zależy i one nie zależą od niego.

## Model i wysiłek

**Fable / xhigh.**

Powód jest ten sam, co przy krokach 33–35 i 40: rzeczy jest dużo i muszą zgodzić
się ze sobą naraz — pętla kopiowania, postęp, przerwanie, sprzątanie po
przerwaniu, kolizje nazw, przenoszenie między systemami plików, dwa panele
i pomiar w trzech torach. Ryzyko jest przy tym **nieodwracalne**: błąd w tym kroku
to utracone dane użytkownika, a nie krzywa ramka.

## Stan zastany (do sprawdzenia w kodzie na starcie kroku)

| Element | Stan |
|---|---|
| `Module/FileInfo/Application/Port/ChecksumPort` | Wzorzec pracy kawałkowej: `begin`/`advance`/`state`/`stop`, **jedna praca naraz**. |
| `Module/FileInfo/Presentation/FileInfoScreen::draw()` | `$this->state->advance()` **w rysowaniu** — praca stoi, gdy ekran jest niewidoczny. |
| `Application/Port/BackgroundProcessPort` | Proces potomny, jedna praca naraz — używa go `du`; kopiowanie tędy **nie idzie**. |
| `Presentation/Ui/Component/ProgressBar` | Postęp znany i nieznany; `NeedsTime` dla wędrującego wypełnienia. |
| `Presentation/Ui/NeedsTime` | Zegar dla ekranu i komponentu — droga, którą pasek dostaje czas. |
| `Module/Browser/…/BrowserPanes` | Dwa panele; `focused()` i `all()` — źródło i cel są już rozróżnialne. |
| `Application/Port/FileOperationsPort` (po kroku 41) | ~~Czynności natychmiastowe; kopiowanie **nie ma prawa** do niego dojść.~~ **Nieaktualne:** port prowadzi własną pracę kawałkową (usunięcie drzewa: `beginRemoval`/`advanceRemoval`/`confirmRemoval`/`stopRemoval`). Kopiowanie i tak idzie osobnym portem, ale z innego powodu (D79 nr 1). |
| `Infrastructure/Diagnostics/Scenario` | ~~Siedemnaście~~ **osiemnaście** scenariuszy (doszedł `tree`); `progress` obejmuje pasek postępu nad listą. |
| `Presentation/Ui/RunsWork` (krok 41) | **Czego plan nie znał:** zdolność okna nakładanego, pytana raz na takt w `GameLoop`. Praca zmieniająca dysk nie dzieje się w rysowaniu. |
| `Presentation/Ui/Overlay/ProgressOverlay` (krok 41) | **Czego plan nie znał:** gotowe i **ogólne** okno pracy — nazwa, licznik, pasek — karmione `Application/Dto/WorkProgress`. Jego docblock zapowiada kopiowanie jako drugiego użytkownika. |
| `Presentation/Ui/Overlay/PromptOverlay` (krok 41) | **Czego plan nie znał:** okno o jedno słowo, z górną granicą szerokości; napisu **nie ocenia**. Trzeci użytkownik zapowiedziany w D75 to ten krok. |
| `Presentation/Ui/Command/OpensOverlay` (krok 47) | **Czego plan nie znał:** komenda umie otworzyć okno, więc czynności tego kroku dostają komendę i pozycję w menu `F9` bez zmiany w rdzeniu. |
| `Presentation/Ui/Overlay/ConfirmOverlay` | Szerokość liczona z długości napisu, **bez górnej granicy** — dług wskazany krokowi 42 przez D77, bo to on dowozi pytania z nazwami plików. |

## Zakres

### 1. Port kopiowania w rdzeniu

Osobny od portu z kroku 41 i to jest rozstrzygnięcie, nie podział z wygody:
~~czynność natychmiastowa nie ma stanu, a kopiowanie ma go całe~~ — **powód
z planu się zdezaktualizował** (port z kroku 41 ma własną pracę kawałkową), więc
osobność stoi odtąd na drugim: stan kopiowania jest nieporównanie większy —
źródło, cel, otwarte uchwyty, pozycja w pliku, lista tego, co zostało, i pamięć
decyzji o kolizjach (D79, rozstrzygnięcie 1). Kształt bierze się z `ChecksumPort`,
bo tam ten sam problem został już raz rozwiązany.

**Jedna praca naraz** — ta sama zasada, co w obu poprzednikach, i z tego samego
powodu: pasek postępu jest jeden.

### 2. Praca przyjmuje listę źródeł, nie jeden plik

Nawet jeśli krok 43 jeszcze nie powstał. Lista jednoelementowa nie kosztuje nic,
a port przerobiony później na listę oznaczałby przepisanie pętli postępu, obsługi
kolizji i sprzątania — czyli trzech najtrudniejszych rzeczy w tym kroku.

### 3. Katalog kopiuje się z chodzeniem po drzewie — **przed pierwszym bajtem, nie na przemian z nim**

> **Poprawka ze startu kroku (D79, rozstrzygnięcie 3).** Chodzenie po drzewie
> zostaje pracą kawałkową, ale dzieje się **w całości przed** kopiowaniem, wzorem
> liczenia przed usuwaniem z kroku 41 — z własnym oknem, jeśli nie zmieści się
> w pierwszym kawałku. Dzięki temu mianownik jest znany od pierwszego
> skopiowanego bajtu i pasek nie potrafi się cofnąć. Treść pierwotna punktu
> zostaje niżej, bo jej argument o zawieszonej klatce jest nadal prawdziwy — to
> właśnie dlatego liczenie jest kawałkowe, a nie jednym `scandir`em w klatce.

#### Treść pierwotna (2026-08-13): leniwie, na przemian z kopiowaniem

Zbudowanie listy wszystkich plików przed pierwszym bajtem jest zawieszoną klatką
na katalogu, który ma sto tysięcy wpisów. Chodzenie po drzewie jest więc częścią
pracy kawałkowej, a nie jej przygotowaniem: kawałek to **albo** kilka bajtów
pliku, **albo** odczytanie kolejnego katalogu.

Konsekwencja dla postępu: całkowity rozmiar **nie jest znany** na starcie.
Pytanie 3 rozstrzyga, czy pasek pokazuje wtedy tryb „postęp nieznany” (uczciwie),
czy sumę liczoną w miarę chodzenia (rośnie mianownik, więc pasek potrafi się
cofnąć).

### 4. Przenoszenie: zmiana nazwy albo kopiowanie i usunięcie

W obrębie jednego systemu plików przeniesienie jest natychmiastowe i idzie portem
z kroku 41. Między systemami plików **nie ma innej drogi** niż skopiowanie
i usunięcie źródła — i to jest miejsce, w którym łatwo stracić dane.

Reguła, od której nie ma odstępstwa: **źródło znika dopiero po potwierdzonym
zapisaniu celu w całości.** Przerwanie w połowie zostawia źródło nietknięte.

### 5. Kolizje nazw

Cel istnieje — i to jest przypadek zwykły, nie brzegowy. Odpowiedzi:
**nadpisz / pomiń / zmień nazwę / przerwij**, a przy liście źródeł także
**zastosuj do wszystkich**.

`ConfirmOverlay` odpowiada „tak” albo „nie” i **nie da się go do tego nagiąć**.
Pytanie 4 rozstrzyga, czy powstaje okno wyboru z listą odpowiedzi (nowe okno
w rdzeniu, składane z `Dialog`u i `ListView` — bez nowego komponentu), czy krok
zawęża się do „nadpisz / pomiń / przerwij” obsłużonych trzema klawiszami
w oknie potwierdzenia rozbudowanym o trzeci przycisk.

**Nadpisanie samego siebie musi być niemożliwe**: kopiowanie do katalogu
źródłowego, kopiowanie katalogu do jego własnego wnętrza i dowiązanie prowadzące
w górę drzewa to trzy drogi do pętli nieskończonej, którą trzeba zatrzymać
sprawdzeniem, a nie limitem.

### 6. Przerwanie i sprzątanie

`Esc` przerywa. Plik zapisany w połowie **znika** — plik, który wygląda na gotowy,
a nie jest, jest gorszy niż brak pliku. Kierunek do rozstrzygnięcia (pytanie 5):
zapis do nazwy tymczasowej i przemianowanie na końcu (bezpieczne, wymaga miejsca
i sprzątania po awarii) albo usunięcie celu przy przerwaniu (prostsze, gorsze przy
zabiciu procesu).

Aplikacja kończona `F10` w trakcie kopiowania sprząta tą samą drogą, co terminal
przy wyjściu — trzema ścieżkami wyjścia z kroku 6, nie jedną.

### 7. Postęp na ekranie — **oknem, nie paskiem nad listą**

> **Poprawka ze startu kroku (D79, rozstrzygnięcie 2).** Punkt jest **odwołany
> w całości**: postęp pokazuje `ProgressOverlay` z kroku 41 — to samo okno, co
> przy usuwaniu, karmione `WorkProgress`. Treść zostaje ta sama (nazwa
> kopiowanego pliku, licznik, pasek), zmienia się miejsce. Reguła „jeden pasek,
> jedno miejsce” z kroku 23 jest przez to **dotrzymana**, a nie naruszona, i lista
> nie oddaje ani jednego wiersza. Cena zapisana wprost: **w trakcie kopiowania nie
> widać listy i nie da się nawigować**, bo okno nie oddaje klawiszy niżej
> (reguła 10) — i to jest zarazem odpowiedź na pytanie nr 8 z tego pliku.
>
> Licznik jest tu drugą zmianą: pasek rośnie **w bajtach**, a napis licznika
> składa wołający i podaje go w `WorkProgress` gotowym („12,3 MB z 700 MB — plik
> 3 ze 120”), bo para liczb w bajtach byłaby nieczytelna (D79, rozstrzygnięcie 9).

#### Treść pierwotna (2026-08-13): pasek nad listą

Pasek nad listą panelu czynnego, wraz z nazwą kopiowanego pliku i licznikiem
„plik n z m”, jeśli m jest znane. Miejsce paska bierze się z kroku 23 („jeden
pasek, jedno miejsce”), a wiersz zabiera **liście**, nie stopce — inaczej krok
wchodzi w treść kroku 40.

### 8. Prawa, czasy i dowiązania

Kopia dostaje prawa dostępu i czas zmiany oryginału; właściciela nie — to wymaga
uprawnień, których aplikacja nie ma i mieć nie powinna. Dowiązanie symboliczne
kopiuje się **jako dowiązanie**, a nie jako jego treść (pytanie 6); inaczej
skopiowanie katalogu z dowiązaniem do `/` kończy się kopiowaniem systemu.

### 9. Pomiar

Scenariusz `progress` z kroku 23 obejmuje pasek nad listą i najpewniej wystarcza.
Prawdziwe pytanie pomiarowe tego kroku jest inne i dotyczy **taktu**: kawałek
przypadający na klatkę ma być dobrany tak, żeby klatka nie urosła — czyli
zmierzony, a nie wybrany. Tryb `--loop` z kroku 38 jest do tego narzędziem: takt
pętli w trakcie kopiowania kontra takt bez niego.

Reguła 17 obowiązuje: **przed pomiarem poproś o zwolnienie maszyny i poczekaj na
potwierdzenie.**

### 10. Wzorce i przebiegi

- Przebieg funkcjonalny na prawdziwym katalogu tymczasowym: skopiowanie drzewa
  z podkatalogami, porównanie zawartości, sprawdzenie praw i czasów;
- przebieg przerwania: `Esc` w połowie — cel nie istnieje, źródło nietknięte;
- przebieg przenoszenia między katalogami: źródło znika **dopiero** po zapisaniu
  celu;
- przebieg kolizji: każda odpowiedź robi to, co obiecuje;
- złota klatka dla paska postępu kopiowania, jeśli scenariusz powstanie.

## Poza zakresem

- **Kopiowanie w tle, gdy widać inny ekran** — wedle rekomendacji z sekcji
  o trudności strukturalnej; granica ma być **zapisana**, a nie przemilczana.
- **Wiele prac kopiowania naraz** — jedna praca, jeden pasek (kroki 23, 25, 26).
- **Kolejka zadań** — z wielu prac wynika kolejka, a z kolejki jej ekran; to
  osobna funkcja.
- **Sprawdzenie sumy kontrolnej po skopiowaniu** — moduł `FileInfo` umie liczyć
  sumy i kto chce, ten porówna; wpisane w kopiowanie podwoiłoby jego czas.
- **Kopiowanie z zachowaniem twardych dowiązań i plików rzadkich** — właściwość
  narzędzi systemowych, nie menadżera plików w terminalu.
- **Wznowienie przerwanego kopiowania** — praca przerwana jest pracą zakończoną.
- **Kopiowanie przez sieć, na inne maszyny** — aplikacja zna jeden system plików.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Application/Port/FileTransferPort.php` | Application | Nowe — praca kawałkowa: `begin(lista, cel, przenieś)`, `advance(budżet)`, `resolve(odpowiedź)`, `state()`, `stop()`. |
| `Application/Dto/TransferState.php`, `TransferStage.php`, `TransferChoice.php` | Application | Nowe — stan pracy (bieżący wpis, bajty, wpisy, kolizja, powód) i słownik odpowiedzi na kolizję. |
| `Application/Dto/WorkProgress.php` | Application | Czwarte pole: gotowy napis licznika (D79 nr 9); puste znaczy „złóż jak dotąd”. |
| `Infrastructure/FileSystem/FileTransferService.php` | Infrastructure | Nowe — liczenie, pętla kopiowania, kolizje, sprzątanie po przerwaniu. |
| `Presentation/Ui/Overlay/ChoiceOverlay.php` | Presentation | Nowe — okno wyboru z listą odpowiedzi (`Dialog` + `ListView`, bez nowego komponentu). |
| `Presentation/Ui/Overlay/ConfirmOverlay.php` | Presentation | Górna granica szerokości — dług kroku 41 dostaje tu pierwszego odbiorcę. |
| `Module/Browser/Presentation/EntryTransfer.php` | Moduł | Nowe — kopiowanie i przeniesienie wraz z łańcuchem okien; wzorem `EntryOperations`, a **nie** przypadkiem użycia, jak zakładał plan (D79). |
| `Module/Browser/Presentation/Command/CopyCommand.php`, `MoveCommand.php` | Moduł | Nowe — `browser.copy [ścieżka]`, `browser.move [ścieżka]`, w menu `F9` przez `AppliesToSelection` i `OpensOverlay`. |
| `Module/Browser/Presentation/BrowserScreen.php` | Moduł | `F5` kopiowanie, `F6` przeniesienie, zmiana nazwy schodzi na `F4`. |
| `Module/Browser/lang/pl.php`, `lang/en.php` | Napisy | Postęp, kolizje, przerwanie, niepowodzenia. |
| `Infrastructure/Diagnostics/ScenarioFactory.php` | Infrastructure | Scenariusz kopiowania — **tylko jeśli** okaże się osobnym kosztem. |
| `tests/Functional/FileTransferFlowTest.php` | Testy | Kopiowanie, przerwanie, przenoszenie, kolizje — na prawdziwym katalogu. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Trzeci użytkownik pracy kawałkowej; granica „praca stoi poza ekranem”. |
| `docs/pomiary/` | Pomiary | Takt pętli w trakcie kopiowania kontra bez niego. |

## Do rozstrzygnięcia na starcie kroku — **rozstrzygnięte 2026-08-15**

Pełne uzasadnienia i odrzucone alternatywy: [00-decyzje.md](../00-decyzje.md), D79.
Skrót odpowiedzi:

1. **Kto posuwa pracę** — ~~pętla główna / ekran / ekran blokujący~~ **okno
   nakładane, raz na takt w `GameLoop`**; rozstrzygnięte precedensem kroku 41
   (`RunsWork`), a nie wyborem między trzema wariantami tego pliku.
2. **Rozmiar kawałka** — **stały, dobrany pomiarem** (`--loop`: takt pętli
   w trakcie kopiowania kontra bez niego), wzorem stałych z kroku 41.
3. **Postęp przy nieznanym rozmiarze** — pytanie znika: **etap liczenia** przed
   kopiowaniem sprawia, że rozmiar jest znany od pierwszego bajtu.
4. **Okno kolizji** — **nowe okno wyboru w rdzeniu** (`ChoiceOverlay`, `Dialog`
   plus `ListView`), sześć pozycji, „do wszystkich” **teraz**, nie z krokiem 43.
5. **Zapis celu** — **wprost, z usunięciem niedokończonego pliku przy
   przerwaniu**; nazwa tymczasowa odrzucona.
6. **Dowiązania symboliczne** — **kopiują się jako dowiązanie**; wykrywanie pętli
   staje się przez to niepotrzebne, bo chodzenie po drzewie w nie nie wchodzi.
7. **Cel kopiowania** — **okno ze ścieżką** (`PromptOverlay`), wypełnione
   katalogiem drugiego panelu; `Enter` jest zarazem potwierdzeniem.
8. **Przerwanie przez zmianę katalogu** — pytanie znika wraz z odpowiedzią nr 1:
   okno nie oddaje klawiszy niżej, więc katalogu nie da się zmienić w trakcie
   pracy.

Dwa rozstrzygnięcia wynikłe z odpowiedzi, których ten spis nie przewidział:
**licznik postępu przychodzi do okna gotowym napisem** (pasek rośnie w bajtach)
oraz **kolizja pyta o każdy wpis, który miałby coś nadpisać** — katalog o tej
samej nazwie jest scaleniem, a nie kolizją.

## Kryteria ukończenia

- Kopiowanie pliku wielkości płyty **nie gubi klatek** — dowodzi tego pomiar
  taktu, nie wrażenie.
- Pasek postępu mówi prawdę: postęp znany, gdy jest znany, i nieznany, gdy nie
  jest.
- Przerwanie nie zostawia na dysku pliku wyglądającego na gotowy; źródło
  przenoszenia jest nietknięte.
- Przeniesienie między systemami plików usuwa źródło **dopiero** po zapisaniu
  celu w całości.
- Kopiowanie katalogu do własnego wnętrza jest niemożliwe i mówi dlaczego.
- Prawa i czas zmiany przenoszą się na kopię; dowiązanie zostaje dowiązaniem.
- Przebiegi funkcjonalne działają na prawdziwym katalogu tymczasowym i sprzątają
  po sobie.
- Pomiar „przed i po” wykonany na zwolnionej maszynie i zapisany w `docs/pomiary/`.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.

## Dziennik realizacji

### 2026-08-15 — rozstrzygnięcia startowe i cały kod kroku

**Rozstrzygnięć wyszło dziesięć, a nie osiem** ([00-decyzje.md](../00-decyzje.md),
D79). Dwa pytania z planu **rozstrzygnęło sprawdzenie kodu, a nie wybór**
(„kto posuwa pracę” — precedens `RunsWork` z kroku 41; „przerwanie przez zmianę
katalogu” — okno nie oddaje klawiszy niżej, więc katalogu nie da się zmienić),
jedno **zniknęło wraz z odpowiedzią na inne** („postęp przy nieznanym rozmiarze”
— etap liczenia sprawia, że rozmiar jest znany), a dwa **dołożyły się od nowa**:
gotowy napis licznika w `WorkProgress` i reguła, co właściwie jest kolizją.

**Trzy zapisy tego pliku zostały odwołane** i każdy ma poprawkę wpisaną w miejsce,
w którym stał: sekcja o trudności strukturalnej (pracę posuwa okno, nie ekran),
punkt 3 (liczenie **przed** kopiowaniem, nie na przemian z nim) i punkt 7 (postęp
oknem, nie paskiem nad listą).

**Co powstało w rdzeniu:**

| Plik | Rola |
|---|---|
| `Application/Port/FileTransferPort` | pięć metod: `begin(lista, cel, przenieś)`, `advance(budżet)`, `resolve(odpowiedź, nazwa)`, `state()`, `stop()` |
| `Application/Dto/TransferState`, `TransferStage`, `TransferChoice` | stan pracy **o dwóch miarach naraz** (bajty i wpisy), sześć etapów wraz z przystankiem `Colliding`, sześć odpowiedzi na kolizję |
| `Infrastructure/FileSystem/FileTransferService` | druga usługa rdzenia pisząca po dysku; liczenie, kolejka, kopiowanie, kolizje, sprzątanie |
| `Infrastructure/FileSystem/TransferItem`, `TransferItemKind` | pozycja kolejki i pięć jej rodzajów — wewnętrzna sprawa infrastruktury, przez port nie przechodzi |
| `Presentation/Ui/Overlay/ChoiceOverlay` | piąte okno rdzenia: pytanie o więcej niż dwie odpowiedzi (`Dialog` + `ListView`, bez nowego komponentu) |

**Dwie zmiany w rdzeniu, których plan nie przewidywał, i obie z tego samego
powodu — okno stanęło w środku łańcucha:**

1. **`PromptOverlay` oddaje `OverlayOutcome`, nie `?Message`** — tą samą drogą,
   którą krok 41 przeprowadził `ConfirmOverlay`. Wpisana ścieżka **zaczyna pracę**,
   a praca pokazuje się oknem postępu, więc „zamknij i otwórz” musi stać się naraz.
2. **`WorkProgress` zyskał czwarte pole: gotowy napis licznika.** Para liczb
   („3840 z 30001”) czyta się dobrze dla sztuk i nie czyta się wcale dla bajtów
   („12914688 z 734003200”). Puste pole znaczy „złóż jak dotąd”, więc usuwanie
   z kroku 41 zostało nietknięte.

**Dług kroku 41 spłacony przy okazji:** `ConfirmOverlay` dostał górną granicę
szerokości (`MAX_COLUMNS = 64`), tę samą, którą `PromptOverlay` dostał po
obejrzeniu okna w prawdziwym terminalu.

**Co powstało w module** (`Module/Browser/`): `EntryTransfer` (dwie czynności,
jedno miejsce dla klawisza i komendy — wzorzec `EntryOperations`), `CopyCommand`
i `MoveCommand` (`browser.copy [ścieżka]`, `browser.move [ścieżka]`, w menu `F9`
przez `AppliesToSelection` i `OpensOverlay`), `PaneRefresh` (odświeżanie paneli
**wyjęte** z `EntryOperations`, bo doszedł drugi wołający — a ten zmienia dwa
katalogi naraz), `BrowserPanes::focusedSelection()` i
`DirectoryPath::resolvedFrom()` (rachunek ścieżki wpisanej przez użytkownika,
wyjęty z `JumpCommand` z tego samego powodu).

**Odstępstwo od planu w tabeli plików:** `CopyEntriesUseCase` i `MoveEntriesUseCase`
**nie powstały**. Czynność składa łańcuch okien i zna `LoopState`, więc mieszka
w `Presentation` modułu (D41) — przypadek użycia, który nie robi nic poza
wywołaniem portu, byłby warstwą przepisującą argumenty. Wzorzec ten sam, co
w kroku 41.

**Trzy rzeczy wypatrzone przy pisaniu, warte zapamiętania:**

1. **`rename()` w PHP nie zawsze jest operacją na metadanych.** Dla zwykłych
   plików PHP obsługuje `EXDEV` sam — kopiując plik **w środku wywołania**. Próba
   „spróbuj `rename()`, a jak się nie uda, kopiuj” zatrzymałaby więc pętlę na czas
   całego pliku, czyli zrobiła dokładnie to, czemu ten krok ma zapobiec. Stąd
   rozpoznanie idzie **numerem urządzenia** (`lstat()['dev']`), a nie próbą.
2. **Katalog o tej samej nazwie w celu nie jest kolizją, tylko scaleniem** —
   utworzenie katalogu, który już jest, niczego nie niszczy. Kolizją jest wpis,
   którego zawartość zniknęłaby pod nowym; bez tego rozróżnienia kopiowanie
   drzewa do katalogu z jednym wspólnym podkatalogiem pytałoby o rzecz bez treści.
3. **Odpowiedź „zmień nazwę” musi przepisać całą gałąź.** Cele wpisów policzono
   przy liczeniu, więc zmiana nazwy katalogu bez przepisania tego, co w środku,
   wysłałaby zawartość pod ścieżkę, której nikt już nie tworzy.

**Prawa katalogów nadaje się na końcu pracy**, osobnymi pozycjami kolejki:
katalog o prawach `0555` nie przyjąłby ani jednego pliku, gdyby dostał je przy
tworzeniu. Kolejka ma przez to pięć rodzajów pozycji i kolejność wymuszoną przez
system plików: katalogi, zawartość, pieczątki, sprzątanie po przeniesieniu.

**Testy:** 1564 zielone (przed krokiem 1518, więc +46), PHPStan `max` bez błędów,
PHP-CS-Fixer bez uwag — `make qa` przechodzi. Nowe pliki testowe:
`tests/Infrastructure/FileSystem/FileTransferServiceTest` (19 przypadków **na
prawdziwym katalogu tymczasowym**), `tests/Functional/FileTransferFlowTest`
(16 przypadków, cała droga przez `InputHandler`, prawdziwy dysk),
`tests/Application/Dto/TransferStateTest`,
`tests/Presentation/Ui/Overlay/ChoiceOverlayTest` oraz atrapa
`tests/Support/StubFileTransfers` (z tego samego powodu, co `StubFileOperations`
w kroku 41: ścieżki katalogów trzymanych w pamięci bywają na maszynie testowej
prawdziwe).

**Dwa testy trzeba było poprawić po tym, jak pokazały, że premisa była błędna,
a kod dobry:** kopia dowiązania nadal wskazuje **oryginalną** gałąź, więc plik
„przez nią widać” — dowodem, że chodzenie po drzewie w nią nie weszło, jest brak
drugiego katalogu w kopii, a nie brak pliku pod ścieżką dowiązania.

**Pomiar:** krok **nie dokłada ani jednego prymitywu** i nie dotyka ścieżki
rysowania — praca dzieje się w fazie „aktualizuj stan” pętli. Powody pominięcia
scenariuszy dla okna wyboru i dla samego kopiowania zapisane
w [docs/pomiary/README.md](../../pomiary/README.md).

### 2026-08-15 — pomiar i sprawdzenie w prawdziwym terminalu

**Pomiar (`--compare` w czterech torach, host zwolniony na prośbę):** bez
regresji. Wzorce zamykające: `2026-08-15-po-kroku-42{,-loop,-text,-window}.json`.

Trzy pozorne regresje okazały się **rozrzutem środowiska** i warto zapisać, jak
je odróżniono: `okno komend` wyszło w torze sixelowym +13% z ostrzeżeniem „!”
(rozrzut powyżej 1,35×), a w torze okienkowym trzy najtańsze scenariusze
pokazały +16…+22% na wartościach rzędu 0,1–0,3 ms. Przebieg powtórzony
**w izolacji** (`--scenarios=`) dał odpowiednio +1,4% i +0,6% — czyli różnicę
w granicach szumu. Wniosek na przyszłość: **scenariusz oznaczony „!” trzeba
zmierzyć osobno, zanim się go uzna za regresję**, a przy pomiarach
poniżej milisekundy sama rozdzielczość zapisu (0,1 → 0,2 ms) robi „+100%”.
Sixelowy wzorzec **odmówił zapisu za pierwszym razem** (reguła 17 zadziałała
dokładnie tak, jak opisano) i zapisał się dopiero przy obciążeniu 0,09 na rdzeń.

**Czego pomiar nie dowiódł i trzeba to powiedzieć wprost.** Kryterium kroku
brzmi „kopiowanie pliku wielkości płyty nie gubi klatek — dowodzi tego pomiar
taktu, nie wrażenie”, a tor `--loop` mierzy takt **bez** kopiowania: scenariusz
kopiujący naprawdę pliki mieszałby koszt nośnika z kosztem składania klatki
i byłby pomiarem dysku, nie aplikacji (zapisane w
[docs/pomiary/README.md](../../pomiary/README.md) jako powód pominięcia). Dowodem
zostaje więc **obserwacja na żywej aplikacji**, i jest to dowód mocniejszy niż
wrażenie: między dwoma zrzutami odległymi o sekundę licznik przeszedł z 44,0 MB
na 100,0 MB (~56 MB/s **przy jednoczesnym rysowaniu klatek**), a pasek, nazwa
pliku i stopka odświeżały się przez cały czas. Kawałek jest przy tym ograniczony
z góry (4 MiB), więc najgorszy przypadek jest **znany z konstrukcji**, a nie
z pomiaru.

**Sprawdzenie klatki (`make run-xterm` 110×30, katalog pokazowy w scratchpadzie,
wejście komendą `browser.jump` — repozytorium nietknięte).** Obejrzany cały
łańcuch okien na drzewie 286 MB:

- okno ze ścieżką: „Skopiuj „film.iso” do:” wraz z polem „katalog:” wypełnionym
  katalogiem drugiego panelu — okno **zostało wąskie** mimo stukolumnowej ścieżki,
  czyli granica szerokości działa;
- okno pracy: nazwa pliku, pasek rosnący (13% → 35% → koniec), licznik
  „44,0 MB z 286,1 MB”, napis zmieniający rolę tam, gdzie przechodzi przez
  wypełnienie; stopka z „Esc przerwij”. Aplikacja **ani razu nie stanęła**;
- okno kolizji: sześć odpowiedzi, ognisko na pierwszej, stopka „↑/↓ wybór ·
  Enter odpowiedz · Esc wycofaj”; po „Zapisz pod inną nazwą” staje **piąte okno
  łańcucha** z nazwą bieżącą w polu — i kopia ląduje pod nową nazwą, a plik
  w celu zostaje nietknięty;
- `Esc` w połowie kopiowania: „Przerwano — skopiowano 0 wpisów z 1.”, katalog
  docelowy **pusty**, źródło co do bajta nietknięte;
- przeniesienie `F6` w obrębie jednego systemu plików: „Przeniesiono 1 wpis.”
  w tej samej klatce, bez okna pracy — katalog o dwunastu plikach zmienił miejsce
  jednym `rename()`;
- menu `F9`: siedem pozycji wraz z `browser.copy` i `browser.move`, dowiezionych
  **bez zmiany w rdzeniu** — czyli dokładnie tak, jak zapowiadał rachunek z D77.

**Jedna poprawka, którą pokazał wyłącznie prawdziwy terminal:** licznik przy
**jednym** pliku pokazywał „0/1” przez cały czas pracy i czytał się jak usterka —
plik jest jeden, więc licznik wpisów stoi na zerze aż do końca. Licznik pokazuje
odtąd sam rozmiar, gdy wpis jest jeden, a parę „4/7” dopiero przy wielu
(`module.browser.transfer.counter.size` obok `…counter`). Sprawdzone w obu
przypadkach na tej samej klatce; regresji pilnuje
`FileTransferFlowTest::testTheCounterOfASingleFileShowsSizeAloneWithoutTheEntryTally`.

Wniosek ten sam, co po krokach 28 i 41: **napis liczony z danych trzeba
zobaczyć, a nie wyliczyć.** Tamte dwa razy chodziło o szerokość okna, ten raz
o treść licznika — a wspólne jest to, że test przechodził w obu wypadkach.

**Bramka po poprawce:** `make qa` zielone — 1565 testów, 4277 asercji.
