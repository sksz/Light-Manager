# 5. Konwencje nazewnictwa

> Rozdział 5 dokumentu źródłowego. Spis rozdziałów: [docs/architecture.md](../architecture.md).

- Segmenty namespace = katalogi 1:1 (PSR-4), root: `LightManager\`.
- **Value Objects**: rzeczownik bez sufiksu, `final`, `readonly`,
  samowalidacja w konstruktorze, metoda `equals()`. Natywne `enum` liczy
  się jako Value Object.
- **Encje / agregaty**: rzeczownik bez sufiksu, jawna tożsamość, `equals()`
  porównuje wyłącznie identyfikator, mutowalne w miejscu.
- **Interfejsy repozytoriów**: sufiks `RepositoryInterface`.
- **Implementacje repozytoriów**: sufiks `Repository` poprzedzony
  technologią (`FilesystemDirectoryRepository`).
- **Porty aplikacyjne**: sufiks `Port`.
- **Implementacje portów (Singletony)**: sufiks `Service`.
- **Use case'y**: czasownik + rzeczownik + sufiks `UseCase`.
- **Wyjątki domenowe**: sufiks `Exception`, dziedziczą po
  `Domain\Exception\DomainException` (abstrakcyjna, `extends \RuntimeException`).
  Preferowane nazwane konstruktory statyczne (`::forPath()`). **Treść
  komunikatu jest techniczna i po angielsku** — pisana dla śladu stosu, nie dla
  użytkownika; dane potrzebne warstwie `Presentation` do złożenia napisu (np.
  ścieżka) wyjątek wystawia jako publiczne, typowane pola (§7).
- **Wyjątki infrastruktury**: sufiks `Exception`, dziedziczą po
  `Infrastructure\Support\InfrastructureException` (abstrakcyjna,
  `extends \RuntimeException`) — osobna hierarchia, równoległa do domenowej,
  ta sama konwencja nazwanych konstruktorów (`TerminalException::forMissingPcntl()`)
  i ta sama zasada technicznego komunikatu po angielsku.
  Wprowadzona w kroku 06 ([docs/plans/00-decyzje.md](../plans/00-decyzje.md), D16).
- **DTO portów**: obiekty wejścia/wyjścia portów aplikacyjnych żyją w
  `Application/Dto` (np. `KeyPress` i enum `Key` z kroku 06). Pojęcie
  techniczne warstwy dostarczania nie trafia do `Domain/ValueObject`, nawet
  gdy formalnie jest niemutowalną wartością.
  **Od kroku 55 słownik wejścia ma dwie postacie i wspólny nadtyp**
  (`Application\Dto\InputEvent`, D95 nr 1): `KeyPress` — bez zmiany w polach —
  oraz `PointerEvent` z komórką siatki znakowej, przyciskiem, rodzajem czynności
  i tymi samymi trzema modyfikatorami. Interfejs jest **znacznikowy**, bo obie
  postacie nie mają ani jednego wspólnego pytania; wspólny jest wyłącznie
  **kanał**, i to on jest powodem jego istnienia: `InputPort::readEvent()`
  oddaje jedną kolejkę, więc kolejność kliknięcia wobec klawisza jest tą,
  w jakiej padły u użytkownika. Drugi kanał portu (`readPointer()`) byłby tańszy
  i został odrzucony właśnie dlatego. **Współrzędne są w komórkach, nigdy
  w pikselach** — `Rect` jest jedynym układem komponentów, a przeliczenie należy
  do infrastruktury (protokół SGR w terminalu, `GlfwPointerMapper` w oknie).
  `KeyPress` niesie **trzy
  modyfikatory, rozłącznie**: `ctrl` od kroku 19 (skróty modułów) i `alt` od
  kroku 29 (zawijanie wierszy w podglądzie) — oba wyłącznie przy literach —
  oraz `shift` od kroku 44 (druga droga usunięcia, zaznaczanie zakresem),
  wyłącznie przy **klawiszach nazwanych**: litera z `Shift`em przychodzi z obu
  torów jako inna litera, więc znacznik przy znaku nie miałby czego nieść.
  Kombinacji modyfikatorów słownik **nie zna** i nie ma ich po co znać, dopóki
  nie pojawi się użytkownik — w torze okienkowym `Ctrl` wygrywa, w terminalowym
  taka para w ogóle nie powstaje, a `Ctrl`+`Shift`+`Delete` niesie w CSI bit
  `Shift`a i tym samym jest `Shift`+`Delete`. Cena `Alt` w terminalu jest znana
  i zapisana przy parserze: `ESC`+litera to te same dwa bajty co `Esc`
  naciśnięty tuż przed literą, więc rozstrzyga o nich czas — jak we wszystkich
  emulatorach terminala od czasów VT100. Wiązanie klawisza porównuje
  **wszystkie** znaczniki, więc wiązanie na gołą literę nie łapie skrótu
  z modyfikatorem, a goły `F8` nie łapie `Shift`+`F8` — od kroku 44 znaczą
  dwie różne rzeczy, z których jedna jest nieodwracalna.
- **Moduły** (krok 20): klasa modułu ma sufiks `Module` (`FileInfoModule`) i leży
  w warstwie `Presentation` swojego katalogu, bo implementuje zdolności
  wymieniające typy z `Presentation/Ui`. Zdolności nazywają się od tego, co
  wnoszą (`Provides…`) albo czego potrzebują (`Reads…`). Komenda modułu, która
  dostaje stan pętli, leży w jego `Presentation/Command` — tą samą zasadą, którą
  komendy rdzenia leżą w `Presentation/Cli/Command`, a nie w `Application`.
  Moduł może mieć **własne komponenty** w swoim `Presentation/Component` (krok 21:
  `PathLine`, `PreviewBox`), gdy element interfejsu zna typ jego domeny —
  postawiony w katalogu komponentów rdzenia przywróciłby rdzeniowi wiedzę, którą
  właśnie mu odebrano. Słownik prymitywów zostaje przy tym **zamknięty**: komponent
  modułu składa się z komponentów rdzenia, a nie z nowych kształtów. Moduł może
  mieć także **własne okno nakładane** w `Presentation/Overlay` (krok 30:
  `FilterOverlay`), gdy okno zna jego stan; obowiązuje tam ta sama zasada, co
  przy komponentach.
- **Wyjątki modułu** (krok 21) dziedziczą po rdzeniowym `DomainException`
  i mogą zadeklarować `Domain\Exception\DescribesProblem` — parę „klucz katalogu
  plus parametry”, z której `ProblemPresenter` składa zdanie dla użytkownika.
  Rozpoznawanie po klasie zostaje wyłącznie dla wyjątków rdzenia; wyjątek modułu
  przedstawia się sam, bo rdzeń nie ma prawa znać jego nazwy.
- PHPDoc tylko tam, gdzie typy PHP nie wystarczają (kształt kolekcji:
  `list<Entry>`). Komentarze tylko dla nieoczywistego „dlaczego”.
