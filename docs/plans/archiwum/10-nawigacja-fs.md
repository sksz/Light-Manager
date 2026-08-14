# Krok 10 — Stan i nawigacja po systemie plików

## Status

Ukończony

## Zależności

Krok 09.

## Model i wysiłek

Sonnet / medium — standardowa logika aplikacyjna, bez nietypowego ryzyka
technicznego.

## Cel

Wprowadzić realny stan aplikacji: bieżący katalog, listę jego zawartości i
zaznaczenie (kursor), oraz obsługę klawiszy nawigacyjnych.

## Zakres

- Model stanu: ścieżka bieżącego katalogu, lista wpisów (nazwa, czy
  katalog, rozmiar), indeks zaznaczonego wpisu.
- Odczyt zawartości katalogu (np. `DirectoryIterator`/`scandir`) z
  sortowaniem (katalogi przed plikami, potem alfabetycznie — do
  potwierdzenia/skorygowania w trakcie realizacji).
- Obsługa klawiszy: góra/dół (przesunięcie zaznaczenia), Enter (wejście do
  zaznaczonego katalogu), klawisz cofnięcia (wyjście do katalogu
  nadrzędnego).
- Obsługa przypadków brzegowych: pusty katalog, brak uprawnień do odczytu
  katalogu, katalog usunięty w trakcie działania aplikacji.

## Poza zakresem tego kroku

Rysowanie stanu na ekranie (krok 11), operacje modyfikujące system plików
(poza MVP).

## Kryteria ukończenia

- Możliwa jest nawigacja po drzewie katalogów (wejście/wyjście, zmiana
  zaznaczenia) bez błędów i bez wycieku zasobów przy długim użytkowaniu.
- Przypadki brzegowe (pusty katalog, brak uprawnień) nie powodują awarii
  aplikacji.

## Specyfikacja zrealizowana

### Powstałe pliki

| Plik | Warstwa | Rola |
|---|---|---|
| `Domain/Aggregate/Directory.php` | Domain | Korzeń agregatu: katalog, wpisy, zaznaczenie; mutowalny w miejscu. |
| `Domain/ValueObject/DirectoryPath.php` | Domain | Bezwzględna, uporządkowana ścieżka; `parent()`, `child()`, `name()`. |
| `Domain/ValueObject/Entry.php` | Domain | Wpis katalogu: nazwa, typ, rozmiar; wie, czy jest ukryty. |
| `Domain/ValueObject/EntryType.php` | Domain | Enum `Directory` \| `File`. |
| `Domain/ValueObject/Selection.php` | Domain | Nieujemny indeks zaznaczenia. |
| `Domain/Repository/DirectoryRepositoryInterface.php` | Domain | `get(DirectoryPath, bool $includeHidden): Directory`. |
| 4 wyjątki w `Domain/Exception` | Domain | Zła ścieżka, zły wpis, złe zaznaczenie, katalog nie do odczytania. |
| `Application/Port/FileInspectorPort.php` | Application | Opis pliku dla okienka. |
| `Application/Dto/Popup.php` | Application | Treść okienka (tytuł + wiersze). |
| 6 przypadków użycia w `Application/UseCase` | Application | Ruch zaznaczenia, wejście, wyjście w górę, przełączenie ukrytych, opis wpisu, otwarcie katalogu startowego. |
| `Infrastructure/Filesystem/FilesystemDirectoryRepository.php` | Infrastructure | Odczyt katalogu przez `scandir`. |
| `Infrastructure/Filesystem/EntryComparator.php` | Infrastructure | Sortowanie: katalogi przed plikami, alfabetycznie. |
| `Infrastructure/Filesystem/FileInspectorService.php` | Infrastructure | Singleton wywołujący `file -b`. |
| `Presentation/Cli/InputHandler.php` | Presentation | Mapowanie klawiszy na przypadki użycia. |
| 10 plików testów | Testy | 85 nowych testów (łącznie 191). |

Zmienione: `LoopState` (realny stan nawigacji), `GameLoop` (przyjmuje gotowy
stan i przekazuje czas do obsługi klawiszy), `Bootstrap` (dowiązanie
repozytorium i przypadków użycia), `bin/light-manager` (łapie też wyjątki
domenowe).

### Ustalone zachowania

| Zachowanie | Rozstrzygnięcie |
|---|---|
| Sortowanie | Katalogi przed plikami, w obu grupach alfabetycznie bez rozróżniania wielkości liter |
| Polskie znaki | `Collator` z `intl`, gdy dostępny; inaczej ścieżka awaryjna z odwzorowaniem `ą→a` itd. |
| Wpisy ukryte | Domyślnie schowane, klawisz `.` przełącza (ponowny odczyt katalogu) |
| Krańce listy | Zaznaczenie zatrzymuje się, nie zawija |
| Wejście do katalogu | Enter oraz strzałka w prawo |
| Wyjście w górę | Backspace oraz strzałka w lewo; zaznaczany jest katalog, z którego wyszliśmy |
| Enter/prawo na pliku | Okienko z wynikiem `file -b`, nazwa wpisu w nagłówku |
| Okienko | Modalne — pierwszy dowolny klawisz je zamyka i nic poza tym nie robi |
| Zaznaczenie po `.` | Zostaje na tym samym wpisie po nazwie; gdy zniknął — pierwszy wpis |
| Błąd odczytu | Nawigacja zostaje na miejscu, w stanie ląduje komunikat |
| Czas życia komunikatu | Gasi go klawisz, ale nie wcześniej niż po `3 s + 0,5 s × liczba słów` |
| Katalog startowy nie do odczytania | Cofnięcie w górę do pierwszego czytelnego katalogu + komunikat |

### Kluczowe rozstrzygnięcia techniczne

- **Filtr wpisów ukrytych siedzi w repozytorium**, nie w agregacie —
  przełączenie `.` powoduje ponowny odczyt katalogu, co przy okazji odświeża
  listę o zmiany na dysku. Agregat pozostaje prosty: zawiera wyłącznie to, co
  widoczne. Znacznik w stanie przestawiany jest **po** udanym odczycie, żeby
  nieudane przełączenie nie rozjechało się z tym, co widać.
- **Czas wstrzykiwany, nie odczytywany** — `InputHandler::handle()` przyjmuje
  bieżącą chwilę jako parametr, a `GameLoop` podaje moment rozpoczęcia
  iteracji, który i tak liczy na potrzeby taktu. Dzięki temu reguła gaszenia
  komunikatu jest w pełni testowalna bez zegara i bez czekania w testach.
- **Wyjątki domenowe łapane w `InputHandler`** — nawigacja, która się nie
  powiodła, zamienia się w komunikat, zamiast wywracać pętlę.
- **`Directory` bez `array_values()`** w konstruktorze: kontrakt `list<Entry>`
  wymusza PHPStan, więc dodatkowe przepisywanie tablicy byłoby martwym kodem
  (zgłoszone przez `arrayValues.list`).
- **Repozytorium tworzone przez `new` w `Bootstrap`**, nie jako Singleton —
  jest bezstanowe i nie ma efektu ubocznego wymagającego miejsca w sekwencji
  startowej, podobnie jak `SixelFrameEncoder` z kroku 08.

### Weryfikacja

Testy jednostkowe pokrywają całą domenę i nawigację bez dotykania dysku
(atrapa repozytorium w pamięci). Jedyny test celowo używający dysku to
`FilesystemDirectoryRepositoryTest` — sprawdza odczyt prawdziwego katalogu,
wpisy ukryte, brak uprawnień, zerwane dowiązanie i katalog nieistniejący.

Dodatkowo, na prawdziwym drzewie katalogów pod PTY:

| Sprawdzenie | Wynik |
|---|---|
| Sortowanie z polskimi znakami | `[Alfa] [bez-dostepu] [ćwiczenia] notatka.txt obrazek.png` |
| Przełączanie `.` | `.ukryty` i `.konfiguracja` pojawiają się i znikają, zaznaczenie zostaje na `Alfa` |
| Wejście i wyjście | Po Backspace zaznaczony jest katalog, z którego wyszliśmy |
| Okienko `file` | `notatka.txt: ASCII text`, `obrazek.png: data` |
| Klawisz przy otwartym okienku | Okienko znika, zaznaczenie się nie rusza |
| Katalog bez uprawnień | Ścieżka bez zmian, komunikat ustawiony, aplikacja działa dalej |
| Katalog startowy usunięty | Otwarcie katalogu nadrzędnego |
| Próg gaszenia komunikatu | 2 słowa: gaśnie po 4,0 s; 5 słów: po 5,5 s |
| Cała aplikacja pod PTY | 16 klatek, nawigacja klawiszami, wyjście `q`, bufor ekranu 1/1 |

## Dziennik realizacji

- **2026-08-07** — Ukończono. Powstał pełny model domenowy nawigacji
  (agregat `Directory`, cztery obiekty wartości, interfejs repozytorium, cztery
  wyjątki), sześć przypadków użycia, implementacja repozytorium na systemie
  plików, sortowanie i mapowanie klawiszy. 85 nowych testów (łącznie 191),
  PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag.

  **Decyzje użytkownika podjęte w trakcie kroku** (zobacz
  [00-decyzje.md](../00-decyzje.md), D20): sortowanie, obsługa wpisów ukrytych z
  przełącznikiem, klawisze nawigacji, zachowanie zaznaczenia, okienko z
  wynikiem `file`, czas życia komunikatu i zachowanie przy nieczytelnym
  katalogu startowym.

  **Rozszerzenia poza literalny zakres kroku** (wszystkie na życzenie
  użytkownika): przełącznik wpisów ukrytych — plan wymieniał tylko górę, dół,
  Enter i cofnięcie; okienko z wynikiem `file` wraz z portem
  `FileInspectorPort` i usługą go realizującą; cofanie się w górę drzewa przy
  nieczytelnym katalogu startowym.

  **Zgodnie z planem nic nie zmieniło się na ekranie** — krok 10 dostarcza
  wyłącznie stan i nawigację, a klatka nadal pokazuje treść zastępczą z
  kroku 09. Lista wpisów, zaznaczenie, komunikat i okienko zostaną narysowane
  w kroku 11, który ma już w stanie wszystko, czego potrzebuje.

  **Znane ograniczenia:** dowiązanie symboliczne do katalogu jest traktowane
  jak katalog (bez wykrywania pętli); rozmiar katalogu to zawsze 0; lista nie
  odświeża się sama, gdy zawartość katalogu zmieni się na dysku — dopiero przy
  ponownym wejściu albo przełączeniu `.`.
