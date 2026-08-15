# Krok 50 — Przesył plików: pobranie i wysłanie pracą kawałkową

> **Skąd ten krok.** Powstał 2026-08-15 razem z krokami 48 i 49, jako ostatnia
> trzecia Fazy XVII ([00-decyzje.md](00-decyzje.md), D84). To on domyka zdanie
> „menadżer plików umie SSH”: lista zdalnych plików, po której da się tylko
> chodzić, jest oglądaniem, a nie zarządzaniem.

## Status

**Nie rozpoczęty.** Zablokowany przez kroki 48 i 49.

## Cel

Plik ma się dać skopiować z hosta na dysk lokalny i z dysku lokalnego na host —
po kawałku, z paskiem postępu, z pytaniem o kolizję nazw i z przerwaniem, po
którym na dysku nie zostaje plik wyglądający na gotowy.

Miarą powodzenia jest zdanie: **`F5` na wpisie zdalnym pobiera go do katalogu,
w którym stoi przeglądarka, `F6` wysyła w drugą stronę, okno postępu mówi ile
i dokąd, a `Esc` przerywa pracę, nie zostawiając połówki pliku.**

## Zastrzeżenie do rozstrzygnięcia na starcie — kto pisze po dysku lokalnym

Reguła 15b brzmi bez wyjątków: **wszystko, co pisze po dysku, idzie przez port
rdzenia**. Pobranie pliku pisze po dysku lokalnym, więc albo łamie tę regułę,
albo musi przez taki port przejść — a żaden z trzech istniejących nie umie wziąć
źródła, którego nie ma na dysku.

| Wyjście | Cena |
|---|---|
| **(a)** rdzeń dostaje port „zapisz strumień do pliku” | rdzeń zaczyna wiedzieć, że istnieją źródła nielokalne — czyli dokładnie ta wiedza, której D42 mu odmawia |
| **(b)** moduł pisze sam, a wyjątek 15b dostaje **drugi nazwany przypadek** | wymaga jawnej zgody; granica musi być wąska: moduł pisze wyłącznie w pracy przesyłu i wyłącznie do katalogu wskazanego przez użytkownika. **Rekomendacja** |
| **(c)** ścieżkę strumienia (`ssh2.sftp://…`) podaje się rdzeniowemu `FileTransferPort` jako napis | kusi, bo port bierze „ścieżkę bezwzględną jako napis" — ale rozpoznanie systemu plików idzie tam przez **numer urządzenia** (`lstat()['dev']`), a `is_link()` i prawa dostępu na URI nie znaczą nic. Port zacząłby kłamać w miejscu, w którym dziś jest dokładny |

Wariant **(b)** jest rekomendowany, bo próba z reguły 15b jest tu niespełniona
wprost: funkcja wchodząca do rdzenia musi mieć **dwóch odbiorców i powtórzenie
o koszcie nieodwracalnym**. Odbiorca jest jeden — moduł `ssh` — a powtórzenia
nie ma żadnego.

## Zależności

- **Kroki 48 i 49** całkowicie: sesja i zdalny katalog powstają tam.
- **Krok 42** wzorcowo i najmocniej ze wszystkich — to jest ta sama praca
  z drugim rodzajem źródła. Stamtąd pochodzi komplet reguł, których krok nie ma
  prawa wymyślać od nowa: **liczenie przed pracą** (mianownik znany od pierwszego
  bajtu), **przystanek w środku pracy** na pytanie o kolizję (`Colliding`),
  **sprzątanie pliku zapisanego w połowie** przy przerwaniu i **źródło znika
  dopiero po potwierdzonym zapisaniu celu** (jeśli przesył ma wariant
  przenoszący — rozstrzygnięcie nr 4).
- **Krok 41** — `ProgressOverlay`, `PromptOverlay`, `ChoiceOverlay`, `RunsWork`.
  Praca zmieniająca dysk posuwa się w `GameLoop`, w fazie „aktualizuj stan”,
  **nigdy w rysowaniu** (piąta reguła D46).
- **Krok 23** — `ProgressBar`; rozmiar pliku znamy ze `stat`, więc pasek ma
  mianownik od początku i tryb „postęp nieznany” nie dostaje tu użytkownika.
- **Krok 21** przez `ReadsContext`, i to jest **legalna droga do drugiej strony
  przesyłu**: przeglądarka publikuje `ModuleContext`, więc moduł `ssh` zna
  katalog, w którym użytkownik stoi lokalnie, **nie sięgając do tamtego modułu**.
  Dokładnie po to kontekst istnieje (D40 P5).
- **Krok 47** — `OpensOverlay`: obie czynności dostają komendę i pozycję w menu
  `F9` za darmo.
- **Krok 46** — zdarzenia modułu (`ssh.transfer.done`, `ssh.transfer.failed`)
  dają dźwięk bez zmiany w rdzeniu.
- **Krok 43** miękko: zaznaczenie wielokrotne jest mnożnikiem tej czynności.
  Rekomendacja: praca bierze **listę źródeł od pierwszego dnia** (jak
  `FileTransferPort::begin()` w kroku 42), nawet jeśli zaznaczenia w panelu
  zdalnym jeszcze nie ma — wtedy dołożenie go nie kosztuje ani jednej zmiany
  w pracy.

## Model i wysiłek

**Opus / xhigh.**

Cena błędu jest tu **nieodwracalna** — to jedyna praca w projekcie, która pisze
po dwóch systemach plików naraz — a do trudności kroku 42 dochodzi jedna, której
tamten nie miał: **kawałek pracy trwa tyle, ile trwa sieć**. Budżet dobrany
w bajtach przestaje mówić cokolwiek o czasie klatki, a przerwane połączenie
w środku zapisu jest stanem, którego kopiowanie lokalne nie zna.

## Stan zastany (sprawdzone przy planowaniu 2026-08-15 / do potwierdzenia na starcie kroku)

| Element | Stan |
|---|---|
| Drogi przesyłu w rozszerzeniu | Trzy: strumień `ssh2.sftp://` (czytany i pisany po kawałku), `ssh2_scp_recv`/`ssh2_scp_send` (**cały plik w jednym wywołaniu — do pętli się nie nadają**) oraz `ssh2_exec` |
| `FileTransferService` | Rdzeniowy wzorzec pracy: dwa etapy, dwie miary budżetu, pamięć odpowiedzi o kolizjach, sprzątanie połówki przy przerwaniu |
| Reguła 15b | Granicą jest **katalog `Infrastructure/FileSystem`**, a próba na przyszłość brzmi: dwóch odbiorców i powtórzenie o koszcie nieodwracalnym |
| `ModuleContext` | Niesie lokalną ścieżkę i zaznaczenie; publikuje przeglądarka, czyta każdy moduł z `ReadsContext` |
| `WorkProgress` | Licznik wolno podać **gotowym napisem**, gdy praca liczy w czymś innym niż sztuki (`12,3 MB`) — krok 42 |

## Zakres

### 1. Port przesyłu w module

`Module\Ssh\Application\Port\RemoteTransferPort`: `begin(list<string> $sources,
string $target, TransferDirection $direction)`, `advance(int $budget)`,
`resolve(TransferChoice $choice, ?string $newName)`, `state()`, `stop()` —
kształt wzięty z `FileTransferPort` co do metody, bo praca jest tą samą pracą.

**Lista źródeł, nie jeden wpis**, od pierwszego dnia (nauka z kroków 42 i 43).

### 2. Dwie strony i sposób wskazania celu

Pobranie: cel podpowiada `ModuleContext` (katalog, w którym stoi przeglądarka),
a użytkownik może go zmienić w `PromptOverlay`. Wysłanie: źródłem jest wpis
zaznaczony w przeglądarce — **też przez kontekst** — a celem katalog otwarty
w panelu zdalnym.

To jest cała odpowiedź na pytanie „skąd moduł zna drugą stronę”, i ona **nie
łamie reguły 15**: moduł czyta kontekst, a nie cudzy moduł.

### 3. Budżet kawałka mierzony czasem

Praca lokalna liczy bajty, bo bajt kosztuje tyle samo co poprzedni. Tutaj
kawałek to obieg sieci: rozmiar bloku dobiera się do przepustowości (rzędu
64–256 kB), ale **wyjściem z pętli kawałka jest zegar, nie licznik**. Rozstrzygnięcie
nr 2 mówi, ile milisekund taktu wolno oddać przesyłowi.

Konsekwencja, o której trzeba wiedzieć z góry: przy zerwanym łączu pojedyncze
`fread()` może zamrzeć na dłużej niż cały budżet — limit czasu strumienia
(`stream_set_timeout()`) jest częścią zakresu, nie ozdobą.

### 4. Kolizje, przerwanie, sprzątanie

Kolizja nazw pyta `ChoiceOverlay` (nadpisz / pomiń / zmień nazwę / przerwij,
z pamięcią odpowiedzi „dla wszystkich”), przerwanie usuwa plik zapisany
w połowie — **po obu stronach**, bo wysyłanie zostawia połówkę na hoście.
`Esc` w oknie postępu przerywa; wyjście z aplikacji w trakcie pracy sprząta
dwiema drogami (D47).

### 5. Postęp

`ProgressOverlay` z `WorkProgress`: nazwa pliku, licznik w bajtach podany
**gotowym napisem** (jednostki idą przez katalog napisów) i pasek z mianownikiem
znanym ze `stat`. Przy wielu plikach licznik mówi też, który to z ilu.

### 6. Pomiar

Praca dzieje się w fazie „aktualizuj stan”, nie w rysowaniu, więc scenariusza
klatki krok **nie dokłada** — powód pominięcia idzie do
[docs/pomiary/README.md](../pomiary/README.md) tą samą drogą, którą zapisano go
dla operacji na plikach w kroku 41. Rozlicza się oś `--loop` „przed i po”: takt
z trwającym przesyłem nie ma prawa wyjść poza budżet.

Do dziennika kroku, poza narzędziem: **przepustowość osiągnięta wobec `scp`**
na tym samym pliku i tym samym łączu. Jeśli różnica będzie wielokrotnością,
znak to, że rozmiar bloku jest dobrany źle.

## Poza zakresem

- **Zapis po zdalnej stronie poza przesyłem** — zmiana nazwy, nowy katalog,
  usunięcie zdalne. Osobny krok, jeśli faza się przedłuży.
- **Wznawianie przerwanego przesyłu** — wymaga pamięci pozycji przeżywającej
  zamknięcie aplikacji, czyli osobnej rzeczy do zaprojektowania.
- **Przesył katalogu wraz z zawartością** — rozstrzygnięcie nr 3; rekomendacja:
  pliki w tym kroku, drzewa osobno, bo chodzenie po zdalnym drzewie to kolejne
  obiegi i kolejny etap liczenia.
- **`rsync`, `scp` jako proces potomny** — dostęp ma być w procesie (D84).
- **Przesył zdalny → zdalny** (jedna sesja, dwa katalogi) — nie ma odbiorcy.
- **Zachowanie praw i czasu zmiany** przy przesyle — rozstrzygnięcie nr 5.
- **Kosz i cofanie** dla operacji zdalnych — `TrashPort` jest portem dysku
  lokalnego i tak ma zostać.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Module/Ssh/Application/Port/RemoteTransferPort.php` | Moduł/Application | **Nowy** |
| `Module/Ssh/Application/{RemoteTransferState,TransferDirection}.php` | Moduł/Application | **Nowe** — stan pracy i kierunek |
| `Module/Ssh/Infrastructure/RemoteTransferService.php` | Moduł/Infrastructure | **Nowa** — praca kawałkowa na strumieniach |
| `Module/Ssh/Presentation/RemoteTransfer.php` | Moduł/Presentation | **Nowa** — czynność w jednym miejscu dla klawisza i komendy (reguła 11n) |
| `Module/Ssh/Presentation/Command/{Download,Upload}Command.php` | Moduł/Presentation | **Nowe**, z `OpensOverlay` |
| `Module/Ssh/Presentation/RemoteScreen.php` | Moduł/Presentation | Klawisze przesyłu, okno postępu, `RunsWork` |
| `Module/Ssh/Presentation/SshModule.php` | Moduł/Presentation | `ReadsContext`, nowe zdarzenia w `DeclaresEvents` |
| `Module/Ssh/lang/{pl,en}.php` | Napisy | Nazwy czynności, jednostki, pytania o kolizję, powody niepowodzeń |
| `docs/architecture.md`, `SKILL.md` | Dokumentacja | Drugi nazwany przypadek wyjątku 15b (jeśli wariant (b)) wraz z granicą; kawałek mierzony czasem |
| `docs/pomiary/README.md` | Dokumentacja | Powód pominięcia scenariusza |
| testy | Testy | Praca na atrapie portu (bez sieci): kolizja, przerwanie w połowie, zerwana sesja w środku zapisu, lista wielu źródeł, sprzątanie połówki po obu stronach |

## Do rozstrzygnięcia na starcie kroku

1. **Kto pisze po dysku lokalnym** — wariant (a), (b) czy (c) z zastrzeżenia.
   Wariant (b) znaczy **rozszerzenie wyjątku 15b** i wymaga zgody wprost.
2. **Ile milisekund taktu wolno oddać przesyłowi** i jaki jest rozmiar bloku.
3. **Czy katalogi wchodzą do zakresu**, czy krok przesyła wyłącznie pliki.
4. **Czy jest wariant przenoszący** (skopiuj i usuń źródło), czy wyłącznie
   kopiowanie. Przenoszenie znaczy usuwanie po drugiej stronie, czyli czynność
   nieodwracalną — a te w tym projekcie zawsze pytają.
5. **Prawa i czas zmiany** — przenosić czy zostawić domyślne.
6. **Zachowanie przy zerwanej sesji w środku pracy** — próbować połączyć się
   ponownie, czy przerwać i posprzątać (rekomendacja: przerwać; wznawianie jest
   poza zakresem).
7. **Klawisze** — `F5`/`F6` powtarzają układ znany z menadżerów dwupanelowych,
   ale w tej aplikacji `F5` znaczy dziś „odśwież” w panelu zdalnym (krok 49).

## Kryteria ukończenia

- Pobranie pliku z hosta do katalogu, w którym stoi przeglądarka, kończy się
  plikiem **identycznym co do bajtu** (sprawdzone sumą kontrolną) i wpisem
  widocznym po odświeżeniu panelu lokalnego.
- Wysłanie działa w drugą stronę, z tym samym sprawdzeniem.
- Okno postępu mówi, ile i dokąd; pasek ma mianownik od pierwszego bajtu.
- `Esc` przerywa pracę i **nie zostawia połówki pliku po żadnej ze stron**.
- Kolizja nazw pyta, a odpowiedź „dla wszystkich” zapamiętuje się na czas pracy.
- Zerwane łącze w środku pracy kończy się zdaniem w pasku stanu, nie wyjątkiem
  i nie zawieszoną pętlą.
- Klatka w trakcie przesyłu mieści się w budżecie — `bin/render-bench --loop`
  „przed i po” bez regresji.
- PHPStan `max`, PHP-CS-Fixer, `make qa` zielone; **żaden test nie otwiera
  połączenia sieciowego**.

## Dziennik realizacji

_(pusty — krok nie rozpoczęty)_
