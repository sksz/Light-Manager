# Krok 42 — Kopiowanie i przenoszenie po kawałku, z postępem

> **Skąd ten krok.** Powstał 2026-08-13 razem z całą Fazą XIV. Jest tą jej
> częścią, w której operacja przestaje mieścić się w klatce — i dlatego stoi
> osobno od kroku 41, a nie obok jego trzech czynności.

## Status

**Nie rozpoczęty** (2026-08-13).

## Cel

Skopiowanie i przeniesienie tego, na czym stoi kursor, do katalogu **drugiego
panelu** — bez zatrzymania pętli, z widocznym postępem i z możliwością
przerwania.

Miarą powodzenia jest zdanie: **skopiowanie pliku wielkości płyty nie gubi ani
jednej klatki, pasek postępu mówi prawdę, a przerwanie w połowie nie zostawia na
dysku pliku, który wygląda na gotowy.**

## Trudność strukturalna — najważniejsza treść tego pliku

Aplikacja umie już pracę dłuższą od klatki i umie ją **dwiema drogami**: własną,
kawałkową (`ChecksumPort`, krok 25, D46) i cudzą, w procesie potomnym
(`BackgroundProcessPort`, krok 26, D47). Użytkownik rozstrzygnął (2026-08-13,
[00-decyzje.md](00-decyzje.md), D66, rozstrzygnięcie 3), że kopiowanie idzie
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
| `Application/Port/FileOperationsPort` (po kroku 41) | Czynności natychmiastowe; kopiowanie **nie ma prawa** do niego dojść. |
| `Infrastructure/Diagnostics/Scenario` | Siedemnaście scenariuszy; `progress` obejmuje pasek postępu nad listą. |

## Zakres

### 1. Port kopiowania w rdzeniu

Osobny od portu z kroku 41 i to jest rozstrzygnięcie, nie podział z wygody:
czynność natychmiastowa nie ma stanu, a kopiowanie ma go całe — źródło, cel,
pozycję w pliku, listę tego, co zostało, i decyzję o kolizjach. Kształt bierze się
z `ChecksumPort`, bo tam ten sam problem został już raz rozwiązany.

**Jedna praca naraz** — ta sama zasada, co w obu poprzednikach, i z tego samego
powodu: pasek postępu jest jeden.

### 2. Praca przyjmuje listę źródeł, nie jeden plik

Nawet jeśli krok 43 jeszcze nie powstał. Lista jednoelementowa nie kosztuje nic,
a port przerobiony później na listę oznaczałby przepisanie pętli postępu, obsługi
kolizji i sprzątania — czyli trzech najtrudniejszych rzeczy w tym kroku.

### 3. Katalog kopiuje się z chodzeniem po drzewie, ale **leniwie**

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

### 7. Postęp na ekranie

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
| `Application/Port/FileTransferPort.php` | Application | Nowe — praca kawałkowa: `begin(lista, cel)`, `advance(bajty)`, `state()`, `stop()`. |
| `Application/Dto/TransferState.php` | Application | Nowe — stan pracy: bieżący plik, bajty, pozycja na liście, powód niepowodzenia. |
| `Infrastructure/FileSystem/FileTransferService.php` | Infrastructure | Nowe — pętla kopiowania, leniwe chodzenie po drzewie, sprzątanie po przerwaniu. |
| `Presentation/Ui/Overlay/ChoiceOverlay.php` | Presentation | Nowe albo rozbudowa `ConfirmOverlay` — wedle rozstrzygnięcia 4. |
| `Module/Browser/Application/UseCase/CopyEntriesUseCase.php` | Moduł | Nowe — kopiowanie do katalogu drugiego panelu. |
| `Module/Browser/Application/UseCase/MoveEntriesUseCase.php` | Moduł | Nowe — przeniesienie: zmiana nazwy albo kopiowanie i usunięcie. |
| `Module/Browser/Presentation/BrowserScreen.php` | Moduł | Klawisze kopiowania i przenoszenia, posuwanie pracy, pasek postępu, `Esc` jako przerwanie. |
| `Module/Browser/Presentation/Component/EntryList.php` | Moduł | Wiersz mniej, gdy pasek postępu stoi nad listą. |
| `Module/Browser/lang/pl.php`, `lang/en.php` | Napisy | Postęp, kolizje, przerwanie, niepowodzenia. |
| `Infrastructure/Diagnostics/ScenarioFactory.php` | Infrastructure | Scenariusz kopiowania — **tylko jeśli** okaże się osobnym kosztem. |
| `tests/Functional/FileTransferFlowTest.php` | Testy | Kopiowanie, przerwanie, przenoszenie, kolizje — na prawdziwym katalogu. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Trzeci użytkownik pracy kawałkowej; granica „praca stoi poza ekranem”. |
| `docs/pomiary/` | Pomiary | Takt pętli w trakcie kopiowania kontra bez niego. |

## Do rozstrzygnięcia na starcie kroku

1. **Kto posuwa pracę** — pętla główna (praca ponad ekranami), ekran przeglądarki
   (rekomendacja, jak dziś suma kontrolna), czy ekran blokujący klawisze.
2. **Rozmiar kawałka** — stały (ile bajtów), czy dobierany do czasu klatki;
   w obu wariantach **zmierzony**, nie wybrany.
3. **Postęp przy nieznanym rozmiarze** — tryb „postęp nieznany” czy mianownik
   rosnący w miarę chodzenia po drzewie.
4. **Okno kolizji** — nowe okno wyboru w rdzeniu czy trzy klawisze w oknie
   potwierdzenia; czy „zastosuj do wszystkich” wchodzi teraz, czy razem
   z krokiem 43.
5. **Zapis celu** — nazwa tymczasowa i przemianowanie na końcu, czy zapis wprost
   z usunięciem przy przerwaniu.
6. **Dowiązania symboliczne** — kopiować dowiązanie (rekomendacja) czy jego treść;
   jak rozpoznać pętlę w drzewie.
7. **Cel kopiowania** — zawsze katalog drugiego panelu, czy okno ze ścieżką, gdy
   podziału nie ma.
8. **Przerwanie przez zmianę katalogu** — czy wejście do innego katalogu w trakcie
   kopiowania przerywa pracę, czy praca trwa niezależnie od tego, co widać.

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

*(pusty — krok nierozpoczęty)*
