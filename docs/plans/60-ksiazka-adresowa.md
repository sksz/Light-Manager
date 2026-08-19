# Krok 60 — Współdzielona książka adresowa: jeden rejestr wpisów, rozdziały deklarowane przez używających

> **Skąd ten krok.** Powstał 2026-08-19 na polecenie użytkownika, w miejsce
> kroku o tym numerze **usuniętego z kodu i z planu** tego samego dnia (commity
> `ce65103` i `8e4a3e2`, „Usunięcie wadliwej książki adresowej"). Numer **60**
> zostaje po tamtym wolny i ten go zajmuje; plan od **61** wzwyż nie rusza się
> o nic. Rozstrzygnięcia zamówienia: [00-decyzje.md](00-decyzje.md), **D104**.

## Status

**Ukończony** 2026-08-19. Trzy etapy, pomiar na obu osiach bez regresji,
klatki obejrzane pod XTermem, dokumentacja domknięta. Rozstrzygnięcia:
[00-decyzje.md](00-decyzje.md) — **D104** (zamówienie) i **D105**
(rozstrzygnięcia startowe).

Rozstrzygnięcia zamówienia (D104): cztery wady poprzedniej książki do zdjęcia,
**zasada jednakowego dostępu** (patrz niżej — to jest oś całego kroku), trzy
rozdziały naraz (`ssh`, `docker`, `k8s`), wpis niesie nazwę i identyfikator,
sekret jest rodzajem pola. **Rozstrzygnięcia startowe: D105** — wszystkie
siedem pytań rozstrzygniętych przed pierwszą linią kodu; sekcja „Pytania do
rozstrzygnięcia" niesie odtąd ich wynik.

## Zasada, na której stoi cały krok

**Książka czyta i pisze własnymi kwerendami i komendami po wszystkich
rozdziałach — dokładnie tak samo, jak robią to moduły.** Rozdział nie jest
niczyją własnością i nie jest przegrodą: jest **nazwaną grupą pól**. Moduł,
który zamierza z jakichś pól korzystać, po prostu **deklaruje rozdział i pola,
z których będzie używał** — i to jest cała jego rola w mechanizmie.

Z tej jednej zasady wynika reszta konstrukcji i warto wypisać, co dokładnie ona
znaczy:

- **Jeden zestaw kwerend i komend obsługuje wszystkie rozdziały.** Rozdział jest
  **argumentem**, nie osobnym wejściem. Nie ma kwerendy „mojego rozdziału" ani
  komendy „dla właściciela".
- **Książka nie ma drogi uprzywilejowanej.** Jej własny ekran pyta i zmienia
  przez ten sam rejestr, co każdy moduł — jest **pierwszym użytkownikiem
  własnego mechanizmu**, a nie jego wyjątkiem. Deklaruje przy tym własny
  rozdział tymi samymi komendami, którymi deklarują moduły.
- **Deklaracja jest zapowiedzią użycia, nie zastrzeżeniem.** Dwa moduły wolno
  zadeklarować ten sam rozdział i te same pola; deklaracja identyczna jest
  bezczynna, a sprzeczna (ten sam klucz, inny rodzaj) **nie przestawia pola,
  które ktoś już zadeklarował** — pierwsza stoi, druga wraca zdaniem.
- **Nie ma przegrody między rozdziałami.** Moduł Dockera wolno zapytać o pola
  rozdziału `ssh` i to jest **droga zamierzona**, nie obejście: cel tunelu jest
  hostem, a host stoi w rozdziale `ssh`. Kopiowania pól między rozdziałami po to
  tylko, żeby „mieć swoje", ten krok nie potrzebuje i nie przewiduje.
- **Deklaracja jest jedyną drogą, którą pola powstają**, i jest **jednostronna**:
  wchodzi komendami, a książka po nic nie oddzwania.

Zasada zdejmuje przy okazji pojęcie, które w poprzedniej książce było i szkodziło:
**„właściciela rozdziału" nie ma**. Rozdział, którego w danym uruchomieniu nikt
nie zadeklarował (moduł wyłączony, odrzucony albo usunięty), nie staje się przez
to sierotą ani zakazanym obszarem — jego **wartości stoją we wpisach, dają się
obejrzeć, poprawić i usunąć** tymi samymi komendami, co wszystkie inne. Brakuje
mu wyłącznie **deklaracji pól**, czyli etykiet, rodzajów i kolejności kolumn —
i tyle widać na ekranie.

## Cel

W aplikacji jest dziś **trzy razy to samo pojęcie**: spis miejsc, do których
moduł się łączy. Każdy z trzech modułów prowadzi własny (`HostBook`,
`EnvironmentBook`, `ClusterBook`), w własnej sekcji dokumentu stanu, z własnym
ekranem dopisywania i usuwania — a jeden z nich sięga po cudzy wpis napisem
(`EnvironmentScreen::resolveTunnelTarget()` przegląda wiersze `ssh.hosts`
w poszukiwaniu nazwy). Ten krok robi z trzech spisów **jeden rejestr**,
prowadzony przez osobny moduł, w którym **wpis jest tożsamością, a pola przy nim
są wspólne**.

Miarą powodzenia są cztery zdania, każde sprawdzalne:

1. **Jeden wpis, trzy rozdziały, jeden zestaw operacji.** Wpis `biuro` niesie
   naraz pola rozdziałów `ssh`, `docker` i `k8s`; wszystkie trzy czyta się
   i zmienia **tymi samymi** kwerendami i komendami, podając rozdział jako
   argument.
2. **Książka nie zna wyjątku od własnej reguły.** W kodzie modułu książki nie ma
   ani jednego odczytu ani zapisu wpisu z pominięciem jej własnych kwerend
   i komend — sprawdzone maszynowo.
3. **Nowy rozdział kosztuje dwie komendy i ani jednej linii w książce.** Moduł,
   który zechce własnych pól przy wpisie, deklaruje je jednostronnie; książka
   nigdy do niego nie oddzwania i nie musi wiedzieć, że istnieje.
4. **Odrzucenie modułu nie zabiera ani jednej wartości** i nie zamyka dostępu do
   jego rozdziału.

## Czym ten krok różni się od usuniętego

Krok usunięty (`ce65103`) dowiózł moduł, wpis z identyfikatorem, rozdziały
i pola. Użytkownik wskazał w nim **cztery wady** i to one, a nie objętość kodu,
są treścią tego kroku (D104). Wszystkie cztery mają wspólny korzeń: **rozdział
był tam czyjś**.

**1. Deklaracja szła w dwie strony.** Moduł wołał `address-book.chapter`
z trzema napisami, a **książka czytała spis pól z kwerendy zakładającego** —
czyli oddzwaniała. Stąd brała się zależność od kolejności składania modułów
(rozdział trzeba było zakładać w takcie, żeby zdążył przed pierwszym otwarciem
ekranu), zależność od tego, czy tamten moduł nadal ma kwerendę, i milcząca umowa
o kształcie jego wiersza. **Teraz deklaracja jest jednostronna i kompletna**:
rozdział i każde pole wchodzą komendami, książka po nic nie wraca, a kolejność
przestaje mieć znaczenie, bo deklaracja niczego nie zastaje i niczego nie
potrzebuje.

**2. Okno nie pokazywało rozdziałów.** Ekran był tabelą `nazwa | adres | opis`,
a rozdziały widać było dopiero w łańcuchu okien pojedynczego wpisu. **Teraz
ekran zaczyna od rozdziałów**: przełącznik rozdziałów w górnym pasie, kolumny
tabeli **brane z deklaracji pól** wybranego rozdziału i widok „wszystkie",
w którym widać, w których rozdziałach wpis ma wartości. Ekran widzi wszystkie
rozdziały nie dlatego, że jest ekranem książki, tylko dlatego, że **pyta o nie
kwerendą, która nie zna pojęcia cudzego rozdziału**.

**3. Dane wpisu rozjechały się po sekcjach.** Adres leżał w sekcji
`address-book`, a sposób uwierzytelnienia i ścieżka klucza — w sekcji `ssh`,
kluczowane identyfikatorem wpisu. Jeden wpis w dwóch miejscach, dwa zapisy, dwie
migracje. **Teraz wpis leży w całości w sekcji książki**, razem z polami
maskowanymi; sekcja modułu trzyma **wyłącznie wskaźniki i pamięć podręczną**
(który wpis jest bieżący, co moduł zapamiętał o sesji).

**4. Operacje omijały komendy i kwerendy.** Ekran i łańcuch okien pracowały
wprost na obiekcie `Addresses` — istniała druga droga do tych samych operacji.
**Teraz ekran i łańcuch okien nie mają referencji do modelu w ogóle**: biorą
**dwa rejestry rdzenia** ze stanu pętli, wzorem `DeployImageFlow` z kroku 54
(reguła 11x). Model widzą wyłącznie komendy i kwerendy modułu — nikt więcej,
z ekranem książki włącznie.

## Trudność strukturalna — pięć rzeczy, z których trzy są starsze od tego kroku

**Pierwsza: to, co krok wnosi, jest mechanizmem, a mechanizm nie dostaje miejsca
w rdzeniu.** Deklaracja pola jest kształtem bliźniacza wobec `ModuleSetting`
(klucz, etykieta, rodzaj, wartość domyślna, dopuszczalne wartości, wzorzec,
długość, maskowanie) i właśnie dlatego kusi, żeby ją do rdzenia wstawić.
**Nie wchodzi tam**: D42 („rdzeń nie wie, czym jest wpis") zostaje nietknięte,
`ChapterField` jest typem modułu, a rdzeń nadal nie wie, co to adres, host,
środowisko ani klaster.

**Druga: krok rusza cztery moduły naraz i żaden nie ma prawa zobaczyć typu
drugiego.** `HostProfile` wymienia dziś **23 pliki** modułu Ssh,
`DockerEnvironment` — **8** modułu Dockera, `ClusterProfile` — **6** modułu
Kubernetesa. Te typy **nie znikają**: przestają być wpisem książki i stają się
**odczytem modułu z wierszy kwerendy**. Brak przegrody między rozdziałami tego
nie zmienia — granica reguły 15 biegnie po **typach**, nie po danych: moduł
Dockera wolno zapytać o pola rozdziału `ssh`, ale nadal nie wolno mu zobaczyć
`HostProfile`.

**Trzecia: przeprowadzka dotyczy żywych danych użytkownika, i to trzech sekcji
naraz.** `ssh.hosts`, `docker.environments` i `k8s.clusters` mają dziś
**siedemnaście pól poza nazwą** (6 + 7 + 4) i dwa wskaźniki bieżącego wpisu.
Migracja musi być **leniwa i nieniszcząca** wzorem D103 — stare klucze zostają
na dysku nietknięte — bo błąd tutaj to nie usterka wyglądu, tylko utrata
wszystkich adresów, środowisk i klastrów naraz.

**Czwarta: maskowanie jest maskowaniem, a nie zamkiem — i trzeba to powiedzieć
wprost.** Rodzaj `secret` (D104) zasłania wartość **na ekranie**, wzorem
`ModuleSetting::secret()`, i trzyma ją poza domyślnymi wierszami kwerendy, żeby
ścieżka klucza prywatnego nie wyświetlała się w każdym spisie. **Przegrodą nie
jest**: rejestr kwerend nie zna wołającego (reguła kwerendy nr 2), a zasada tego
kroku mówi, że dostęp jest jednakowy. Plik stanu ma prawa `0600` i **nie udaje
sejfu**, tak samo jak `~/.docker/config.json`.

**Piąta: dwa źródła wpisów zostają dwoma.** Konteksty czytane z cudzych plików
(`docker context ls`, `kubeconfig` wraz z `KUBECONFIG`) **nie są wpisami
książki** i nie staną się nimi: aplikacja do tych plików nie pisze (kroki 51,
52, 58, 59) i to zdanie zostaje w mocy. Spis na ekranie modułu jest odtąd
złożeniem **wpisów książki** i **wpisów czytanych z cudzych plików**.

## Stan zastany (sprawdzony w kodzie 2026-08-19)

| Element | Stan |
|---|---|
| Moduły | Sześć: `browser`, `file-info`, `audio`, `ssh`, `docker`, `k8s`. Litery skrótów zajęte: `a`, `b`, `d`, `k`, `o`, `s`; zakazane: `c`, `h`, `i`, `j`, `m`, `z` (`ModuleRegistry::FORBIDDEN_CHARACTERS`). Wolnych **czternaście**. |
| `Application\State\Book` (rdzeń, krok 59) | Porządek i tożsamość: klucz jest tożsamością, kolejność jest kolejnością dopisywania, ładunek nieprzezroczysty. Trzy książki modułów stoją na niej dziś. |
| `Application\Port\StateDocumentPort` (rdzeń, krok 59) | Jeden plik `~/.light-manager/state.json`, sekcje po identyfikatorach właścicieli, `StateFile` z prawami `0600`. Właściciel sięga wyłącznie po **swoją** sekcję; migracja ze starych plików mieszka za portem. |
| `Module/Ssh` — `HostBook` + sekcja `ssh` | Klucze `hosts`, `directories`. Wpis: `name`, `host`, `port`, `user`, `auth`, `keyPath`, `directory`. `HostProfile` w **23 plikach**. `HostsScreen` — 502 wiersze (`F7` dopisuje, `F4` przestawia uwierzytelnienie, `F8` usuwa). Kwerenda `ssh.hosts` nie oddaje obcym odcisku ani ścieżki klucza (11w). |
| `Module/Docker` — `EnvironmentBook` + sekcja `docker` | Klucze `environments`, `currentEnvironment`. Wpis: `name`, `kind`, `socket`, `target`, `port`, `cert`, `key`, `ca`. `EnvironmentScreen` — 488 wierszy; `resolveTunnelTarget()` **przegląda wiersze `ssh.hosts`** szukając nazwy z pola `target`. Obok wpisów stoją konteksty klienta `docker`. |
| `Module/Kubernetes` — `ClusterBook` + sekcja `k8s` | Klucze `clusters`, `currentCluster`. Wpis: `name`, `kubeconfig`, `context`, `namespace`, `timeout`. `ClusterBookScreen` — 344 wiersze, `Clusters` — 578. Obok wpisów stoją konteksty z `~/.kube/config` i z `KUBECONFIG` (`ConfigCatalog`). |
| Rejestr komend | `LoopState::commands()` — moduł zamawia cudzą czynność **nazwą** komendy (reguła 11x, krok 54). Jedyny dzisiejszy odbiorca: `DeployImageFlow`, który bierze **oba rejestry** i nie widzi ani jednego typu modułu Dockera. |
| Rejestr kwerend | Jedyna droga odczytu (11w). Wynik ma dwa oblicza: wiersze dla każdego, ładunek typowany po **podanej** nazwie właściciela. **Kwerenda nie zna wołającego.** Kwerend modułowych jest dziś 31. |
| `ModuleSetting::secret()` (krok 54) | Precedens pola maskowanego: `key`, `labelKey`, `kind`, `choices`, `default`, `pattern`, `maxLength`, `masked`; `shown()` oddaje zasłonę **stałej długości**. Maskuje w interfejsie — plik ma prawa `0600` i tyle. |
| `NoModuleKnowsAnotherModuleTest` | Chodzi po przestrzeniach nazw w `src/Module`; kontrola własna wymaga **co najmniej sześciu** modułów. |
| `QueryIsTheOnlyReadPathTest` | Chodzi po **treści** plików; spis zakazanych wyrażeń wypisany wprost, każde z drogą zastępczą. Zwolnień nie ma od kroku 54. |
| Pomiar | Ekrany spisów mają zapisane pominięcia w `docs/pomiary/README.md`; spis środowisk dostał w kroku 58 własny scenariusz `environments` (nagłówek tabeli i trzy role wierszy). |

## Zależności

- **Kroki 48, 58 i 59** — materia: trzy książki, ich ekrany, łańcuchy okien
  i sekcje stanu. To one się przenoszą; krok **nie dowozi nowego rozmówcy**,
  tylko jedno miejsce zamiast trzech.
- **Krok 59** — rdzeniowa `Book`, `StateDocumentPort`, `StateFile`. Bez nich krok
  powtórzyłby mechanizm zapisu po raz szósty (reguła 15e).
- **Kroki 53 i 54** — **oba rejestry**: kwerendy jako jedyna droga odczytu
  i rejestr komend w stanie pętli (11x). Na tym drugim stoi cała deklaracja
  rozdziału **oraz** droga, którą ekran książki zmienia własne dane.
- **Krok 20** — kontrakt modułu; **siódmy** sprawdzian i pierwszy moduł, który
  istnieje po to, żeby **trzymać pola wszystkich**.
- **Krok 47** — `OpensOverlay`: komenda otwierająca łańcuch okien wpisu.
- **Krok 46** — zdarzenia: zmiana wpisu widoczna w trzech modułach bez
  odpytywania.
- **Kroki 27, 28, 30, 32, 40** — tabela, pytanie, filtr, menu, podpowiedzi
  stopki; **kolumny tabeli powstają z danych**, a nie z kodu ekranu, i to jedyne
  miejsce, w którym krok przyciska komponent rdzenia.
- **Kroki 55 i 57** — wskaźnik i schowek: adres jest treścią, którą się kopiuje.
- **Krok 41** — lekcja `PromptOverlay`: puste pole świadomie nic nie robi, więc
  łańcuch okien nie może odkładać zapisu na koniec.
- **Kroki 14 i 15** — ustawienia i napisy.

## Model i wysiłek

**Opus / xhigh.** Warunek `Fable` z przypisów ¹ i ² nie zachodzi: prymitywów nie
przybywa, słownik wejścia zostaje nietknięty, trzej tłumacze nie dostają ani
jednej linii.

Wysiłek trzymają trzy rzeczy i żadna nie jest rozmiarem kodu. **Pierwsza:** krok
rusza **cztery moduły naraz**, a żaden nie ma prawa zobaczyć typu drugiego —
37 plików wymienia trzy typy, które przestają być wpisem, nie przestając być
pojęciem. **Druga:** migracja obejmuje **trzy sekcje żywych danych**.
**Trzecia:** krok wnosi **mechanizm**, który ma kosztować tyle samo przy szóstym
rozdziale, co przy pierwszym — a poprzednia próba pokazała, że to jest właśnie
ta część, którą łatwo zrobić źle.

## Zakres

### 1. Moduł i jego tożsamość

`src/Module/AddressBook/` z pełnym podziałem na warstwy, identyfikator
`address-book`, skrót `Ctrl`+`W` (litera wolna; mnemonik: **w**pisy). Deklaruje
`ProvidesScreen`, `ProvidesCommands`, `ProvidesQueries`, `ProvidesHelpTab`,
`ProvidesSettingsTab`, `DeclaresEvents` i **`NeedsTick`** — takt jest mu
potrzebny do jednej rzeczy: **zadeklarowania własnego rozdziału tą samą drogą,
co wszyscy** (punkt 3).

**Nie deklaruje `RequiresEnvironment`** i to jest warunek, nie przeoczenie:
książka nie potrzebuje niczego spoza aplikacji, a odrzucona zabrałaby dane
wszystkim modułom naraz.

### 2. Wpis: identyfikator i nazwa, nic poza tym (D104)

`Domain/ValueObject/AddressEntry` niesie **dwie rzeczy własne**:

- **identyfikator** — skrót, tożsamość wpisu, stały przez całe jego życie;
- **nazwa** — dowolny napis czytelny dla człowieka; **wolno ją zmienić,
  powtórzyć i zostawić pustą**, bo tożsamości nie niesie.

Wszystko inne to **wartości rozdziałów**: mapa `rozdział → klucz pola →
wartość`, dla wpisu nieprzezroczysta. Adresu wśród pól własnych **nie ma** —
adres jest polem rozdziału jak każde inne, deklarowanym przez tego, kto go
używa. Książka nie wie, co to adres, port ani kontekst; wie, że wpis ma
tożsamość i że ktoś dołożył przy nim pola.

Samowalidacja wyjątkiem modułu deklarującym `DescribesProblem` (reguła 8)
pilnuje rzeczy, po których przewróci się cudzy kod: znaków sterujących, długości
i identyfikatora niezgodnego ze wzorcem.

### 3. Rozdział i pole — deklaracja jako **zapowiedź użycia**

Rozdział i pola deklaruje **każdy, kto zamierza z nich korzystać** — moduł albo
sama książka — dwoma rodzajami komend i **bez ani jednej kwerendy zwrotnej**:

```
address-book.chapter <rozdział> [klucz-tytułu]
address-book.field   <rozdział> <klucz> <klucz-etykiety> <rodzaj> [domyślna] [wybory]
```

Cztery zdania o tym, czym ta deklaracja jest, a czym nie:

- **Jest zapowiedzią użycia, nie zastrzeżeniem.** Nie tworzy właściciela i nie
  zamyka nikomu drogi; mówi tylko: „będę używał tego rozdziału i tych pól".
- **Jest idempotentna.** Wołanie drugi raz z tą samą treścią nic nie zmienia,
  więc deklarujący nie musi pamiętać, czy już prosił, a dwa moduły wolno
  zadeklarować to samo.
- **Jest niesprzeczna z definicji.** Deklaracja pola o kluczu już
  zadeklarowanym, ale innym rodzaju, **nie przestawia go** — pierwsza stoi,
  druga wraca zdaniem do dziennika. Inaczej dwa moduły przerzucałyby się
  rodzajem pola co takt.
- **Jest jednostronna.** Wchodzą napisy i liczby, ani jednego typu przez granicę
  (15g); książka nie wywołuje kwerendy deklarującego, nie zna go i nie musi
  wiedzieć, że istnieje.

`Application/AddressChapter` i `Application/ChapterField` są typami **modułu
książki**, kształtem bliźniaczymi wobec `ModuleSetting`, ale **krótszymi
o walidację dziedzinową** (D105 nr 3): klucz, klucz etykiety, rodzaj, wartość
domyślna i dopuszczalne wartości — **bez wzorca i bez długości maksymalnej**.
Książka pilnuje **rodzaju** (liczba jest liczbą, wybór jest z listy, odniesienie
wskazuje istniejący wpis) oraz własnej higieny (znaki sterujące, długość, po
której klatka przestaje być rysowalna); reguła dziedzinowa zostaje u czytającego,
bo to on wie, czym wartość ma być. **Kolejność deklaracji pól jest kolejnością
kolumn** na ekranie i kolejnością pytań w łańcuchu okien.

`Domain/ValueObject/FieldKind` — spis **zamknięty i krótki**, bo rodzaj musi
umieć narysować i przyjąć ekran, a ekran jest jeden:

| Rodzaj | Znaczy | Kto go używa w tym kroku |
|---|---|---|
| `text` | napis | adres, kontekst, przestrzeń nazw, katalog zdalny |
| `number` | liczba całkowita | port SSH, port TCP, limit czasu `kubectl` |
| `flag` | tak/nie | — (wchodzi, bo ekran umie i kosztuje jedną gałąź) |
| `choice` | jedna z wypisanych wartości | sposób uwierzytelnienia, rodzaj środowiska |
| `secret` | napis **maskowany na ekranie** (punkt 4) | ścieżka klucza, ścieżki certyfikatów |
| `entry` | **odniesienie do innego wpisu książki** | cel tunelu w rozdziale `docker` |

Rodzaj nieznany książce **pomija się w ciszy** — moduł nowszy od książki nie ma
prawa jej zepsuć, a pole, którego ekran nie umie pokazać, jest polem bez
użytkownika.

**Kiedy się deklaruje.** W takcie (`NeedsTick`) — trzy moduły tego kroku takt
już mają, a książka bierze go właśnie po to. W odróżnieniu od poprzedniej
książki **nie jest to obejście kolejności, tylko jej brak**: deklaracja jest
jednostronna i idempotentna, więc nie ma czego zdążyć przed czym.

**Deklaracje nie są zapisywane na dysk.** Powstają przy każdym uruchomieniu,
więc rozdział, którego nikt w tym uruchomieniu nie zadeklarował, po prostu nie
ma opisu pól — a jego **wartości we wpisach stoją nietknięte, czytelne
i zmienialne** tymi samymi kwerendami i komendami, co wszystkie inne. Ekran
pokazuje wtedy surowe klucze i mówi, że opisu brak.

**Książka deklaruje własny rozdział** (`ogólny`: `address`, `note`) tymi samymi
komendami, w swoim takcie — jest pierwszym użytkownikiem własnego mechanizmu
i to jest zamierzone: gdyby mechanizm był niewygodny, poczułaby to pierwsza.

### 4. Wartości i maskowanie — wszystko w jednej sekcji, dostęp jednakowy

Wartości wszystkich rozdziałów leżą **w sekcji `address-book`** dokumentu stanu,
przy wpisie, którego dotyczą. Sekcja modułu trzyma odtąd **wyłącznie wskaźniki
i pamięć podręczną** — który wpis jest bieżący, co moduł zapamiętał o sesji —
i **ani jednego pola wpisu** (wada nr 3).

Pole rodzaju `secret` znaczy dokładnie trzy rzeczy i **żadna z nich nie jest
przegrodą**:

1. **Na ekranie widać zasłonę stałej długości**, wzorem `ModuleSetting::shown()`
   — w tabeli, w łańcuchu okien i w oknie kwerend.
2. **Nie wchodzi do domyślnych wierszy** kwerend spisu; jego miejsce zajmuje
   znacznik `set` / `unset`. Powodem jest hałas i przypadek — ścieżka klucza
   prywatnego nie ma się wyświetlać w każdym spisie — a nie tajność.
3. **Da się je przeczytać** kwerendą przeznaczoną do tego wprost
   (`address-book.value`, punkt 5), i **wolno to każdemu** — zasada tego kroku
   nie zna wyjątków, a rejestr kwerend nie zna wołającego.

Czego to nie obiecuje, powiedziane wprost: plik stanu ma prawa `0600` i **nie
jest sejfem**. Kto ma dostęp do pliku, ma dostęp do wartości — tak samo jak
w `~/.docker/config.json` i w pliku ustawień z krokiem 54.

### 5. Kwerendy — sześć, każda po wszystkich rozdziałach

| Kwerenda | Argumenty | Wiersze | Pokolenie |
|---|---|---|---|
| `address-book.chapters` | — | `chapter`, `title`, `fields`, `declared` (czy ktokolwiek zadeklarował go w tym uruchomieniu) | licznik deklaracji |
| `address-book.fields` | `chapter` | `key`, `label`, `kind`, `default`, `choices`, `secret`, `position` | licznik deklaracji |
| `address-book.entries` | `chapter` (nieobowiązkowy) | `id`, `name` — a z argumentem także wartości pól tego rozdziału | licznik zmian książki |
| `address-book.entry` | `entry`, `chapter` (nieobowiązkowy) | jeden wpis, ta sama treść | licznik zmian książki |
| `address-book.value` | `entry`, `chapter`, `field` | jedna wartość, **także maskowana** | `VOLATILE` |
| `address-book.last` | — | identyfikator wpisu dopisanego **ostatnio w tym uruchomieniu**; pusto, gdy nikt nic nie dopisał | `VOLATILE` |

`address-book.last` istnieje z jednego, policzonego powodu (D105 nr 6): komenda
oddaje **zdanie, nie daną**, więc deklarujący, który dopisał wpis, nie ma jak
poznać jego identyfikatora — a przy migracji trzech sekcji potrzebuje go do
każdego `set`. Pętla jest jednowątkowa, więc w obrębie taktu odpowiedź jest
jednoznaczna.

Argument `chapter` przyjmuje **dowolny** rozdział — pytający nie musi go
deklarować i nie musi go „mieć". Pokolenie jest prawdziwym licznikiem wszędzie
poza dwiema ostatnimi kwerendami: książka zmienia się w policzalnych miejscach i wszystkie
biją licznik, więc warunek z D93 nr 1 zachodzi i wynik wolno pamiętać.
`address-book.value` i `address-book.last` są `VOLATILE`: odpowiedzi pierwszej
nie chcemy w pamięci rejestru, a druga zmienia się dokładnie wtedy, kiedy nikt
jej nie pilnuje.

Ładunek typowany (`payloadFor`) rozpakowuje **fasada modułu książki** i tylko
ona — to jest zwykły wzorzec projektu (11w) i dotyczy **typów, nie dostępu do
danych**: wiersze niosą wszystko, co niesie ładunek.

### 6. Komendy — dziesięć, i to jest **cała** droga zmiany

```
address-book.show    [rozdział]                       — otwiera ekran, opcjonalnie na rozdziale
address-book.chapter <rozdział> [tytuł]               — deklaracja rozdziału (punkt 3)
address-book.field   <rozdział> <klucz> …             — deklaracja pola (punkt 3)
address-book.add     [nazwa]                          — nowy wpis; bez nazwy otwiera łańcuch okien
address-book.rename  <wpis> <nazwa>                   — nazwa jest zwykłym polem
address-book.remove  <wpis>                           — pyta oknem
address-book.set     <wpis> <rozdział> <pole> <wartość>
address-book.clear   <wpis> <rozdział> [pole]         — czyści pole albo cały rozdział wpisu
address-book.edit    <wpis> [rozdział]                — otwiera łańcuch okien (OpensOverlay, krok 47)
address-book.forget  <rozdział>                       — usuwa wartości rozdziału ze wszystkich wpisów
```

`address-book.forget` jest w tym spisie z powodu, który wynika wprost z zasady:
skoro rozdział niczyj i deklaracje nie są zapisywane, to **wartości rozdziału,
którego już nikt nie używa, nie mają kto posprzątać** — więc musi to być
czynność użytkownika, pytająca oknem i nazwana wprost.

Podpowiedzi argumentów (`SuggestsArguments`, `SuggestionSource::OnDemand`)
oddają identyfikatory **wraz z nazwą obok**, spisy rozdziałów i spisy pól — bo
identyfikatora nikt nie pamięta, a komenda przyjmuje właśnie jego.

**Ekran książki i jej łańcuch okien nie mają referencji do modelu** (wada nr 4):
biorą **rejestr kwerend i rejestr komend** ze stanu pętli. `F7` na ekranie robi
dokładnie to, co `address-book.add` w oknie komend — **bo to jest to samo
wywołanie**, a nie druga droga do tej samej czynności (11n).

### 7. Ekran książki: rozdziały widać i da się je przeglądać (wada nr 2)

`Presentation/AddressBookScreen` — **pasek zakładek** rdzeniowym komponentem
`Tabs` (ten sam, co na ekranie ustawień i pomocy — D105 nr 5), a pod nim `Table`
z wpisami. Zakładka to rozdział:

- **Widok „wszystkie"** — kolumny `nazwa`, `identyfikator` i po jednej kolumnie
  znacznika na rozdział („ten wpis ma wartości w `ssh` i w `docker`").
- **Widok rozdziału** — kolumny **wzięte z deklaracji jego pól**: nazwa plus
  wszystkie pola w kolejności deklaracji, wartości maskowane zasłonięte.
  Nagłówki tłumaczone kluczami z deklaracji, czyli z katalogu **cudzego**
  modułu — a to jest w porządku, bo napisy wszystkich modułów leżą w jednym
  katalogu pod przedrostkami (reguła 15).
- **Rozdział bez deklaracji** (nikt go w tym uruchomieniu nie zadeklarował) stoi
  w spisie z adnotacją, pokazuje surowe klucze i **da się w nim pracować** —
  poprawiać wartości i czyścić je (`clear`, `forget`).

**Wyszukiwanie i sortowanie** (D105 nr 5) należą do tabeli wpisów:
`Ctrl`+`F` zawęża spis po nazwie i po wartościach widocznych kolumn (filtr
z kroku 30), a `F6` przestawia kolumnę porządkującą — kolejne naciśnięcie
odwraca kierunek, a nagłówek niesie znacznik. **Sortowanie mieszka w ekranie,
nie w `Table`**: wiersze porządkuje ekran, zanim poda je komponentowi, więc
pozycja „sortowanie listy po kolumnie", wyłączona z kroku 27, zostaje tam, gdzie
była, a rdzeń nie rośnie o nic.

Klawisze: `←`/`→` i kliknięcie przechodzą między zakładkami (`Tabs::at()`
oddaje trafienie, krok 55), `↑`/`↓` chodzą po wpisach, `F7` dopisuje wpis,
`F4` zmienia podświetlony łańcuchem okien prowadzącym przez pola **bieżącej
zakładki**, `F8` usuwa za `ConfirmOverlay`. Ekran deklaruje `CopiesContent`
(krok 57), `DeclaresFocus` (krok 40 — dwa miejsca: pasek zakładek i tabela)
oraz przyjmuje wskaźnik (krok 55).

Łańcuch okien zapisuje **po każdym ogniwie**, a nie na końcu — lekcja z kroku 41
(`PromptOverlay` na pustym polu świadomie nic nie robi), z jej dobrym skutkiem
ubocznym: `Esc` w środku zostawia wpis z tym, co już dostał.

### 8. Moduł Ssh przestaje trzymać hosty

- **Deklaruje rozdział `ssh` i sześć pól**: `host` (`text`), `port` (`number`,
  domyślnie 22), `user` (`text`), `auth` (`choice`), `keyPath` (`secret`),
  `directory` (`text` — zapamiętany katalog zdalny).
- **`HostBook`, `HostBookView`, `HostBookPort`, `LoadedHostBook` znikają**;
  `SshSession` traci książkę i licznik jej pokoleń.
- **`HostProfile` zostaje** i jest odtąd **odczytem** z wierszy
  `address-book.entries ssh`; tożsamością wpisu jest identyfikator książki,
  a walidacja wąskimi wzorcami zostaje tam, gdzie była (wartości wchodzą do
  wiersza polecenia).
- **Sekcja `ssh`** traci `hosts` i `directories`; zostaje jej pamięć modułu.
- **`HostsScreen`** pokazuje wpisy czytane kwerendą wraz z tym, czego książka
  nie wie: z kim stoi sesja. `Enter` łączy albo rozłącza, `F5` odświeża;
  **`F4`, `F7` i `F8` schodzą z ekranu** do książki, a ich miejsce zajmuje jeden
  skrót otwierający ją na rozdziale `ssh` (`address-book.show ssh`).
- **Kwerenda `ssh.hosts` znika** — powtarzałaby cudzą odpowiedź. Zostaje
  `ssh.session`.

### 9. Moduł Dockera przestaje trzymać środowiska

- **Deklaruje rozdział `docker` i siedem pól**: `kind` (`choice`: gniazdo /
  tunel / TCP), `socket` (`text`), `target` (**`entry`** — odniesienie do wpisu),
  `port` (`number`), `cert`, `key`, `ca` (`secret` — ścieżki do materiału TLS).
- **`EnvironmentBook` znika**; sekcja `docker` zostaje ze wskaźnikiem bieżącego
  środowiska, przeliczonym na identyfikator wpisu.
- **`resolveTunnelTarget()` znika w dzisiejszej postaci**: cel tunelu jest
  odniesieniem, więc bierze się go **jednym pytaniem o cudzy rozdział** —
  `address-book.entry <id> ssh` — zamiast przechodzenia po całym spisie. To jest
  ta zmiana, dla której identyfikator w ogóle powstał (**zmiana nazwy hosta nie
  psuje wpisu tunelowego**), i **pierwszy w kodzie dowód zasady kroku**: moduł
  Dockera czyta rozdział `ssh` drogą zamierzoną, nie obejściem.
- **`EnvironmentScreen`** zachowuje spis złożony z wpisów książki i kontekstów
  klienta `docker`, wybór bieżącego (`Enter`) i wybór sposobu uwierzytelnienia
  tunelu; dopisywanie, zmiana i usuwanie schodzą do książki.

### 10. Moduł Kubernetesa przestaje trzymać klastry

- **Deklaruje rozdział `k8s` i cztery pola**: `kubeconfig` (`text` — ścieżka),
  `context` (`text`), `namespace` (`text`), `timeout` (`number`).
- **`ClusterBook` znika**; sekcja `k8s` zostaje ze wskaźnikiem bieżącego
  klastra, przeliczonym na identyfikator wpisu.
- **`ClusterProfile` i `ClusterPlace` zostają** — miejsce ma nadal dwie
  współrzędne (plik i kontekst), a dwa stany błędu z kroku 59 (`MissingFile`,
  `UnknownContext`) mówią dalej, **którą** poprawić. Zmienia się jedno: skąd
  biorą się współrzędne.
- **`ClusterBookScreen`** zostaje spisem złożonym z wpisów książki i kontekstów
  czytanych z plików; `Enter` przełącza, reszta schodzi do książki.

### 11. Migracja trzech sekcji — leniwa, nieniszcząca, wykonana przez właścicieli sekcji

Zasada z D103 zostaje: **sekcja nieobecna czyta się ze starego miejsca, a stare
klucze zostają na dysku nietknięte**. Zmienia się to, **kto** ją wykonuje.
Książka **nie czyta cudzych sekcji dokumentu stanu** — nie wolno jej i nie ma po
co (granica `StateDocumentPort`, nie granica rozdziałów). Migrację robi
**właściciel sekcji** przy pierwszym takcie, w którym widzi swoje stare klucze,
i robi ją **komendami książki**, tak samo jak deklarację:

1. zadeklaruj rozdział i pola,
2. dla każdego starego wpisu: `address-book.add <nazwa>`, potem `set` na każde
   pole (identyfikator nowego wpisu — patrz pytanie 4),
3. przelicz wskaźnik bieżącego wpisu z nazwy na identyfikator,
4. zapisz we **własnej** sekcji znacznik, że przeniesienie się odbyło.

Wpisy tunelowe modułu Dockera wskazujące host po nazwie przeliczają się na
**odniesienie do wpisu** w tym samym przebiegu — o ile host o tej nazwie jest
w książce; jeśli nie, pole zostaje puste, a ekran mówi, co wybrać. Cisza jest
tu zakazana: to jedyne miejsce, w którym migracja może czegoś nie umieć.

### 12. Zdarzenia, ustawienia, napisy, pomoc

- **Zdarzenia** (`AddressBookEvent`, enum modułu — słownik zamknięty
  konstrukcyjnie, 11o''): wpis dopisany, wpis zmieniony, wpis usunięty, rozdział
  zadeklarowany. Odbiorcami są ekrany trzech modułów, które mają się przerysować.
- **Ustawienia** — jedna pozycja: **kolejność spisu** (dopisywania /
  alfabetycznie). Pozycji „pytaj przed usunięciem" nie ma: usunięcie pyta
  zawsze, bo z ekranu nie widać, kto się na wpis powołuje.
- **Napisy** (`lang/pl.php`, `lang/en.php`, przedrostek `module.address-book.`)
  oraz komplety kluczy etykiet w katalogach trzech modułów — etykiety pól należą
  do tego, kto pola deklaruje.
- **Pomoc** (`ProvidesHelpTab`): czym jest wpis, czym rozdział, **dlaczego
  rozdział nie jest niczyj**, dlaczego identyfikator zamiast nazwy, co się
  dzieje z wartościami, gdy moduł zniknie, i co znaczy zasłonięte pole.

### 13. Strażnicy, przebiegi, pomiar, dokumentacja

- **Strażnicy:** `NoModuleKnowsAnotherModuleTest` — próg kontroli własnej
  z sześciu modułów na **siedem**, bez wyjątku dla czterech modułów tego kroku.
  Nowy test: **moduł książki nie czyta ani nie zmienia wpisu z pominięciem
  własnych kwerend i komend** — poza samymi komendami i kwerendami nikt nie
  wymienia typu modelu (dopisanie pozycji do `QueryIsTheOnlyReadPathTest` wraz
  z drogą zastępczą). Nowy test: **deklaracja jest jednostronna** — w module
  książki nie ma wywołania kwerendy o nazwie spoza jego własnej przestrzeni.
- **Przebiegi** (`tests/Functional/`): wpis dopisany w książce widać w trzech
  modułach bez restartu; **moduł czyta i zmienia cudzy rozdział** i to jest
  przebieg oczekiwany, nie błąd; rozdział zadeklarowany dwa razy nie tworzy
  drugiego, a deklaracja sprzeczna nie przestawia pola; moduł odrzucony nie
  zabiera wartości, a jego rozdział zostaje czytelny i zmienialny bez opisu pól;
  migracja trzech sekcji daje komplet wpisów, przeliczone wskaźniki i **stare
  klucze nietknięte**; zmiana nazwy hosta **nie psuje** wpisu tunelowego.
  Żaden test nie uruchamia `ssh`, `docker` ani `kubectl` — atrapy portów, jak
  w krokach 48–52.
- **Pomiar:** oś `--loop` „przed i po" wobec wzorca po kroku 59 — i **to jest
  zaległość po poprzedniej książce, której nie zmierzono** (reguła 17: prośba
  o zwolnienie maszyny i czekanie na potwierdzenie). Ekran to `Table`
  o kolumnach z danych, czyli skład mierzony przez `columns` i `environments`;
  plan **rekomenduje pominięcie** scenariusza sixelowego z wpisem
  w `docs/pomiary/README.md`, a rozstrzygnięcie należy do użytkownika. **Klatka
  obejrzana pod XTermem** — druga zaległość po tamtym kroku; lekcja z kroku 58
  mówi, po co: ucięta kolumna nie ma testu.
- **Dokumentacja:** `docs/architecture.md` — rozdział o książce jako
  **wspólnym rejestrze pól** wraz z zasadą jednakowego dostępu i czterema wadami
  poprzedniej próby. `SKILL.md` — reguła **15h** w nowym brzmieniu: *dana
  dzielona przez kilka modułów dostaje moduł-rejestr, a nie właściciela; pola
  deklaruje się jednostronnie, komendą, i deklaracja jest zapowiedzią użycia,
  nie zastrzeżeniem — rejestr obsługuje wszystkie rozdziały jednym zestawem
  kwerend i komend i sam z niego korzysta*. `README.md` — skąd biorą się adresy
  i co znaczy rozdział. `CHANGELOG.md` — wpis do sekcji „Niewydane" Fazy XX
  wedle `docs/konwencja-changelogu.md`.

## Kolejność wykonania — trzy etapy, jeden krok

Krok jest duży i **wykonuje się etapami, z których każdy zostawia aplikację
działającą** (gdyby padło rozstrzygnięcie o rozbiciu na osobne kroki, przebiega
to po tych samych szwach — patrz pytanie 1):

1. **Książka i rozdział `ssh`.** Moduł, wpis, deklaracja, pięć kwerend, dziesięć
   komend, ekran, rozdział własny książki, migracja sekcji `ssh`. Na końcu etapu
   `ssh.hosts` znika, a moduł Dockera czyta cel tunelu po nazwie ze
   `address-book.entries ssh` — tymczasowo, tak jak dziś czyta z `ssh.hosts`.
2. **Rozdział `docker`.** Migracja środowisk, pole `target` rodzaju `entry`,
   przeliczenie odniesień. Zdanie sprawdzające etap: **zmiana nazwy hosta nie
   psuje tunelu**.
3. **Rozdział `k8s`.** Migracja klastrów, przeliczenie wskaźnika bieżącego,
   złożenie spisu z wpisów książki i kontekstów z plików.

## Poza zakresem

- **Prawa dostępu do rozdziałów** — świadomie nie ma ich w tym kroku i nie jest
  to przeoczenie: zasada mówi, że dostęp jest jednakowy. Gdyby kiedyś miały
  wejść, wejdą jako osobna decyzja o osobnym mechanizmie, a nie jako skutek
  uboczny nazwy „rozdział".
- **Szyfrowanie pól maskowanych** — plik stanu ma prawa `0600` i nie udaje
  sejfu; klucz do szyfrowania musiałby gdzieś leżeć, a nie ma gdzie (to samo
  zdanie, co przy `ModuleSetting::secret()` w kroku 54).
- **Rejestry obrazów jako rozdział** — to jest krok **61** i on ma po tym kroku
  tańsze wejście: rejestr staje się wpisem z rozdziałem `registry` (dwie komendy
  w takcie) zamiast czwartej książki. Pytanie otwarte tamtego kroku dostaje tu
  **mechanizm**, ale nie odpowiedź.
- **Import z `~/.ssh/config`** — czytanie cudzego formatu jest osobną pracą
  wielkości kroku (parser, `Include`, wzorce `Host *`). Naturalny kandydat na
  krok następny, teraz tańszy: `Host` staje się wpisem, reszta rozdziałem.
- **Pisanie do cudzych plików** (`kubeconfig`, konteksty klienta `docker`) —
  zdanie z kroków 51, 52, 58 i 59 zostaje w mocy.
- **Grupy, znaczniki i drzewo wpisów** — bez odbiorcy (reguła 13); rozdział jest
  osią, po której wpisy się dzielą, i na razie wystarcza.
- **Odniesienia międzywpisowe głębsze niż jeden poziom** — rodzaj `entry`
  wchodzi z jednym odbiorcą (cel tunelu) i bez cyklu; pilnowanie cykli poczeka
  na drugiego.
- **Sprzątanie wartości rozdziału bez udziału użytkownika** — `address-book.forget`
  jest czynnością zamawianą, nie automatem: brak deklaracji znaczy „nikt tego
  dziś nie używa", a nie „to jest śmieć".

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Module/AddressBook/Domain/ValueObject/AddressEntry.php`, `FieldKind.php` | Moduł/Domain | Nowe — wpis (identyfikator + nazwa + wartości) i zamknięty spis rodzajów pól. |
| `Module/AddressBook/Domain/Exception/InvalidAddressEntryException.php` | Moduł/Domain | Nowe — powody odrzucenia wpisu i wartości (reguła 8). |
| `Module/AddressBook/Application/Addresses.php`, `AddressBook.php`, `AddressBookView.php` | Moduł/Application | Nowe — rejestr na rdzeniowej `Book` (klucz = identyfikator), koordynator i migawka. |
| `Module/AddressBook/Application/AddressChapter.php`, `ChapterField.php`, `Chapters.php` | Moduł/Application | Nowe — rozdział, deklaracja pola i spis deklaracji **bez pojęcia właściciela**. |
| `Module/AddressBook/Application/Port/AddressBookPort.php`, `LoadedAddressBook.php` | Moduł/Application | Nowe — odczyt i zapis sekcji. |
| `Module/AddressBook/Application/AddressBookEvent.php`, `AddressBookSettings.php` | Moduł/Application | Nowe — cztery zdarzenia, jedna pozycja ustawień. |
| `Module/AddressBook/Infrastructure/AddressBookStateService.php` | Moduł/Infrastructure | Nowe — sekcja `address-book`; cudzych sekcji nie czyta (punkt 11). |
| `Module/AddressBook/Presentation/AddressBookModule.php`, `AddressBookScreen.php`, `EntryFlow.php`, `AddressBookQueries.php` | Moduł/Presentation | Nowe — moduł (z taktem na własną deklarację), ekran z przełącznikiem rozdziałów, łańcuch okien, fasada. |
| `Module/AddressBook/Presentation/Command/{Show,Chapter,Field,Add,Rename,Remove,Set,Clear,Edit,Forget}Command.php` | Moduł/Presentation | Nowe — dziesięć komend (punkt 6). |
| `Module/AddressBook/Presentation/Query/{Chapters,Fields,Entries,Entry,Value}Query.php` | Moduł/Presentation | Nowe — pięć kwerend (punkt 5). |
| `Module/AddressBook/lang/pl.php`, `en.php` | Napisy | Punkt 12. |
| `Module/Ssh/Application/{HostBook,HostBookView}.php`, `Port/{HostBookPort,LoadedHostBook}.php` | Moduł/Application | **Usunięte** — spis przenosi się w całości. |
| `Module/Ssh/Application/SshSession.php`, `Domain/ValueObject/HostProfile.php` | Moduł | Cel składany z wiersza kwerendy; tożsamość po identyfikatorze wpisu. |
| `Module/Ssh/Infrastructure/SshStateService.php` | Moduł/Infrastructure | Traci `hosts` i `directories`; zostaje pamięć modułu. |
| `Module/Ssh/Presentation/{HostsScreen,SshModule,SshQueries}.php`, `Query/HostsQuery.php` | Moduł/Presentation | Deklaracja w takcie, spis czytany kwerendą, `ssh.hosts` **usunięta**. |
| `Module/Docker/Application/EnvironmentBook.php`, `Port/EnvironmentBookPort.php` | Moduł/Application | **Usunięte**. |
| `Module/Docker/Application/Environments.php`, `Domain/ValueObject/DockerEnvironment.php` | Moduł | Środowisko składane z wiersza kwerendy; cel tunelu jako odniesienie. |
| `Module/Docker/Infrastructure/DockerStateService.php` | Moduł/Infrastructure | Zostaje sam wskaźnik bieżącego środowiska. |
| `Module/Docker/Presentation/{EnvironmentScreen,DockerModule}.php` | Moduł/Presentation | Deklaracja w takcie, spis z dwóch źródeł, cel tunelu pytaniem o rozdział `ssh`. |
| `Module/Kubernetes/Application/ClusterBook.php`, `Port/{ClusterBookPort,LoadedClusterBook}.php` | Moduł/Application | **Usunięte**. |
| `Module/Kubernetes/Application/{Clusters,ClusterState}.php` | Moduł/Application | Wpisy z kwerendy; `ClusterPlace` i dwa stany błędu bez zmian. |
| `Module/Kubernetes/Infrastructure/KubernetesStateService.php` | Moduł/Infrastructure | Zostaje sam wskaźnik bieżącego klastra. |
| `Module/Kubernetes/Presentation/{ClusterBookScreen,KubernetesModule}.php` | Moduł/Presentation | Deklaracja w takcie, spis z dwóch źródeł. |
| `Presentation/Cli/Bootstrap.php` | Rdzeń | **Jedna pozycja na liście modułów** — i ani linii więcej. |
| `tests/Module/AddressBook/…`, `tests/Functional/AddressBookFlowTest.php` | Testy | Punkt 13. |
| `tests/Presentation/{NoModuleKnowsAnotherModuleTest,QueryIsTheOnlyReadPathTest}.php` | Testy | Próg siedmiu modułów; książka bez drogi na skróty. |
| `docs/architecture.md`, `SKILL.md` (15h), `README.md`, `CHANGELOG.md`, `docs/pomiary/README.md` | Dokumentacja | Punkt 13. |

## Kryteria ukończenia

- **Jeden zestaw operacji na wszystkie rozdziały** — każdą z pięciu kwerend
  i każdą z komend zmieniających wpis da się wywołać na dowolnym rozdziale,
  także takim, którego wołający nie zadeklarował.
- **Książka nie zna wyjątku od własnej reguły** — jej ekran i łańcuch okien
  działają wyłącznie przez oba rejestry; sprawdzone maszynowo.
- **Deklaracja jest jednostronna** — w module książki nie ma ani jednego
  wywołania cudzej kwerendy; sprawdzone maszynowo.
- **Rozdział kosztuje dwie komendy** — dopisanie go w czwartym module nie wymaga
  zmiany ani jednej linii w `src/Module/AddressBook/`.
- **Jeden wpis, trzy rozdziały, trzej czytelnicy** — pole poprawione w książce
  widać w module Ssh, Dockera i Kubernetesa **bez restartu**.
- **Moduł czyta cudzy rozdział** — cel tunelu Dockera bierze się z rozdziału
  `ssh` jednym pytaniem i to jest przebieg oczekiwany.
- **Ani jeden typ nie przechodzi między modułami** —
  `NoModuleKnowsAnotherModuleTest` zielony przy progu siedmiu modułów.
- **Migracja nie gubi ani jednego wpisu z trzech sekcji**, przelicza oba
  wskaźniki bieżącego i **zostawia stare klucze nietknięte**.
- **Zmiana nazwy wpisu nie psuje niczego** — wpis tunelowy wskazujący host po
  zmianie nazwy nadal wskazuje ten sam host.
- **Brak deklaracji nie zabiera dostępu** — po odrzuceniu modułu wartości jego
  rozdziału są widoczne, zmienialne i wracają wraz z nim.
- **Rdzeń urósł o jedną pozycję w `Bootstrap`** i ani o linię więcej (reguła 15).
- Napisy w obu językach, `bin/render-bench --loop` „przed i po" bez regresji,
  **klatka obejrzana pod XTermem**, PHPStan `max`, PHP-CS-Fixer, `make qa`
  zielone.

## Rozstrzygnięcia startowe (D105, 2026-08-19)

Siedem pytań planu rozstrzygniętych **przed pierwszą linią kodu**; pełne
uzasadnienia i odrzucone warianty stoją w [00-decyzje.md](00-decyzje.md), D105.

| # | Pytanie | Rozstrzygnięcie |
|---|---|---|
| 1 | Jeden krok czy trzy | **Jeden krok, trzy etapy** — plan od 61 wzwyż nietknięty |
| 2 | Postać identyfikatora | **Losowy szesnastkowy, 12 znaków** (`random_bytes(6)`) |
| 3 | Kształt komendy deklarującej pole | **Jedna komenda na pole**, argumenty typowane |
| 4 | Wzorzec w deklaracji pola | **Nie** — książka pilnuje rodzaju i własnej higieny; reguła dziedzinowa u czytającego (**odwrócona rekomendacja planu**) |
| 5 | Rodzaj `entry` | **Wchodzi**, z odbiorcą (cel tunelu Dockera) |
| 6 | Identyfikator świeżo dopisanego wpisu | Kwerenda **`address-book.last`** |
| 7 | Skrót i przechodzenie między rozdziałami | `Ctrl`+`W`; **rozdziały jako zakładki** (rdzeniowy `Tabs`), wpisy jako **tabela z wyszukiwaniem i sortowaniem** — poza podanymi wariantami, dla spójności z pozostałymi modułami |

## Dziennik realizacji

**2026-08-19 — etap 1a: moduł książki stoi, `make qa` zielone.** Powstał cały
mechanizm wraz z ekranem, komendami, kwerendami, sekcją stanu i testami;
**rozdziałów `ssh`, `docker` i `k8s` jeszcze nie ma** — to reszta etapu 1 i etapy
2–3. Rdzeń urósł o **jedną pozycję w `Bootstrapie`** i ani o linię więcej.

**Co powstało.** 39 plików modułu (4874 wiersze): 3 `Domain`, 11 `Application`
(w tym dwa porty), 1 `Infrastructure`, 22 `Presentation` (ekran, łańcuch okien,
fasada, podpowiedzi, **dziesięć komend**, **sześć kwerend** wraz ze wspólnym
budowniczym wiersza), 2 katalogi napisów po **101 kluczy**. Testów 1286 wierszy
w sześciu plikach; `make qa`: **2445 testów, 8216 asercji**, PHPStan `max`,
PHP-CS-Fixer.

**Pięć rzeczy wartych zapisania.**

1. **Zakładki nie kosztowały nic — komponent już był.** `Tabs` stoi w rdzeniu od
   kroku 18 z dwoma użytkownikami (ustawienia, pomoc); książka jest trzecim
   i bierze też jego `at()`, czyli **ten sam rachunek trafienia wskaźnika, co
   rysowanie**. Rozstrzygnięcie D105 nr 5 („spójny interfejs z pozostałymi
   modułami") okazało się przez to tańsze niż przełącznik proponowany przez plan.
2. **Wzorce walidacji przepuszczały końcowy znak nowej linii — złapał to test.**
   W PCRE `$` dopasowuje się **przed** końcowym `\n`, więc nazwa `"biuro\n"`
   przechodziła przez wzorzec zakazujący znaków sterujących, a identyfikator
   `"a1b2c3d4e5f6\n"` — przez wzorzec tożsamości. Wszystkie siedem wzorców
   modułu ma odtąd modyfikator `D`. Wniosek ogólny, wart tego, żeby stanął
   w konwencjach: **wzorzec zakazujący znaków sterujących bez `D` nie zakazuje
   tego jednego, który najczęściej wpada z wklejenia**.
3. **Pułapka rejestru komend z poprzedniej książki potwierdziła się i została
   ominięta.** `LoopState::commands()` **tworzy pusty rejestr**, gdy `Bootstrap`
   jeszcze nie podał właściwego — a komendy modułu powstają wcześniej niż
   `useCommands()`. Zapamiętany w konstruktorze byłby pusty na zawsze, więc
   `EntryFlow`, ekran i takt biorą rejestr **w chwili użycia**. Lekcja z dziennika
   usuniętego kroku 60 (poprawka nr 4) sprawdziła się co do joty.
4. **Jedno odstępstwo, nazwane i policzone: etykiety odniesień w `ChoiceOverlay`.**
   Okno tłumaczy pozycje kluczami katalogu, a nazwa wpisu jest daną użytkownika,
   więc klucza mieć nie może. Działa, bo tłumacz oddaje klucz nieznany bez zmian;
   ceną jest wpis nazwany dokładnie jak istniejący klucz. Alternatywą był
   znacznik `raw` w rdzeniowym oknie — **zmiana rdzenia dla jednego wołającego,
   wprost przeciwna kryterium kroku**, więc odrzucona.
5. **Siódmy moduł poruszył trzy istniejące testy i każdy z nich słusznie.**
   Pierwsza kwerenda alfabetycznie to odtąd `address-book.chapters`, a nie
   `audio.effects`; zakładka pomocy modułu opisu pliku jest o jedną w lewo dalej;
   komplet skrótów wbudowanych ma siódmą literę (`w`). Żaden nie sprawdzał
   niczego innego, niż sprawdzał wcześniej.

**Strażnicy, którzy powstali razem z kodem** (dwa nowe, jeden podniesiony):
`AddressBookHasNoShortcutPathTest` — model widzą **wyłącznie** komendy
i kwerendy, a moduł **nie pyta ani jednej cudzej kwerendy** (jednostronność
deklaracji, sprawdzana po treści plików); `SecretFieldsStayOutOfRowsTest` —
sekret o rozpoznawalnej treści nie wychodzi wierszami **żadnej** kwerendy
aplikacji poza `address-book.value`, która oddaje go każdemu i tak ma być;
`NoModuleKnowsAnotherModuleTest` — próg kontroli własnej z sześciu modułów na
**siedem**, z książką wymienioną z nazwy.

**Pomiar — obie osie zmierzone na zwolnionej maszynie** (2026-08-19 wieczorem,
reguła 17; użytkownik zwolnił host, obciążenie spadło z 0,57 do 0,30 na
12 rdzeniach, przeglądarka zamknięta). **Oś `--loop` wobec wzorca po kroku 59:
−2,2% / −1,7%** — czyli w rozrzucie, przy obciążeniu 0,04 na rdzeń wobec 0,10
we wzorcu. **Tor sixelowy wobec wzorca po kroku 58: 22 scenariusze, wszystkie
bez regresji** (−6,4% … +3,7%, mediana bliska zeru). Narzędzie **nie odmówiło
zapisu** ani razu — inaczej niż w kroku 59, gdzie strażnik rozrzutu odrzucił
wzorzec sixelowy dwukrotnie.

Zapisane wzorce: `2026-08-19-przed-rozdzialami-60.json`
i `2026-08-19-przed-rozdzialami-60-loop.json`. **Nazwa nie jest przypadkowa
i nie jest to wzorzec zamykający krok**: mierzy stan „moduł książki stoi,
rozdziałów jeszcze nie ma", czyli dokładnie ten punkt odniesienia, wobec którego
da się odczytać koszt **migracji trzech modułów** — a to ona, nie sam moduł,
dokłada pracę do taktu (trzy moduły deklarujące rozdziały w swoim takcie i trzy
ekrany czytające wpisy kwerendą). Wzorzec `po-kroku-60` powstanie, gdy krok się
domknie.

Czego pomiar **nie** obejmuje: **ekran książki nie ma własnego scenariusza**
i plan rekomenduje, żeby go nie dostał — pasek zakładek mierzy `settings`
(ekran ustawień ma dokładnie ten sam `Tabs`), a tabelę z nagłówkiem `columns`
i `environments`. Rozstrzygnięcie należy do użytkownika; do czasu jego podjęcia
pominięcie **nie jest** wpisane do `docs/pomiary/README.md`.

**2026-08-19, wieczorem — scenariusz pomiarowy i klatki pod XTermem.**
Użytkownik **odrzucił rekomendację pominięcia**: ekran książki dostał własny
scenariusz. Obie zaległości po kroku usuniętym są tym samym zamknięte.

**Scenariusz `address-book`** wszedł **do `ScenarioFactory`** (reguła 18), wraz
z przypadkiem enuma, dwoma napisami i złotą klatką. Rozlicza się **w parze
z `columns`**: **+1,6 ms** (20,6 → 22,2) przy siedmiu kolumnach zamiast czterech
i pasku zakładek nad tabelą; dla porównania `environments` tą samą drogą kosztuje
**+7,5 ms**, a różnicę tłumaczą **role wierszy**, których książka nie nadaje.
Powód istnienia scenariusza da się powiedzieć w zdaniu: **żadna klatka nie miała
dotąd zakładek i tabeli z nagłówkiem naraz** — `settings` ma zakładki nad listą,
`environments` tabelę bez niczego nad nią, a ryzyko siedzi w miejscu styku, bo to
pasek rozstrzyga, ile wierszy zostaje tabeli. `--golden-save` odnowił wszystkie
złote klatki i **zmienił dokładnie jeden plik** — nowy; to jest dowód, że
scenariusz nie ruszył pozostałych.

**Oglądanie klatki znalazło dwie usterki i żadnej nie widział test** — dokładnie
tak, jak w kroku 58.

1. **Scenariusz nie odwzorowywał ekranu.** Pierwsza wersja dawała kolumnom
   szerokości **stałe**; zrzut pokazał wartości tracące ostatni znak
   (`uzytkownik…`) i nagłówek `Uwierzytelnien…` ucinający sam siebie. Prawdziwy
   ekran daje polom rozdziału kolumny **elastyczne**, a stały jest wyłącznie
   identyfikator — poprawione, zrzut czysty.
2. **Stopka wypisywała „Enter zmień · F4 zmień"** — dwa wiązania o tym samym
   opisie zamiast jednego o dwóch klawiszach (reguła 11p). Widać to wyłącznie
   w klatce, więc od tej pory pilnuje tego **test przebiegu**: żaden opis nie
   powtarza się w spisie wiązań ekranu.

**Aplikacja obejrzana pod XTermem naprawdę**, z tymczasowym `HOME`, żeby nie
dopisywać wpisów próbnych do książki użytkownika. Cztery klatki: pusta książka
(zakładka `Ogólne` **zadeklarowana w takcie**, zdanie „F7 dopisuje wpis"), dwa
wpisy z dwunastoznakowymi identyfikatorami i znacznikiem sortowania `Nazwa ▲`,
przejście strzałką na zakładkę rozdziału — **kolumny zmieniły się na `Adres`
i `Opis`, czyli na te zadeklarowane** — oraz łańcuch okien pod `F4`
(`biuro: Adres`). Sekcja `address-book` w pliku stanu tymczasowego `HOME`
zawierała po wyjściu oba wpisy. **Mechanizm przeszedł całą drogę w prawdziwej
aplikacji, nie tylko w testach.**

**Pomiar pod XTermem** (`bin/run-render-bench.sh --transfer`) — jedyna droga do
fazy przesyłu: klatka **45,5 kB**, zapis **3,4 ms** (min 2,3, maks 4,5),
**7 wywołań `fwrite()`**, przepustowość **13 507 kB/s**, odpowiedź DA1 po
**29,4 ms** (wartość przybliżona — terminal może odpowiedzieć, zanim domaluje
obraz). Trójka scenariuszy zmierzona tam ma ten sam porządek, co bez XTerma:
`columns` 18,1 ms, `address-book` 20,4 ms, `environments` 24,6 ms.

Zapisane wzorce: `2026-08-19-po-scenariuszu-ksiazki-60.json` (22 scenariusze
plus nowy, bez regresji) i `2026-08-19-pod-xtermem-60.json` (trzy scenariusze
wraz z przesyłem). Wpis w spisie `docs/pomiary/README.md` — dopisany.

**2026-08-19, wieczorem — etap 1 domknięty: rozdział `ssh` i przeniesienie
książki hostów.** `make qa` zielone: **2444 testy, 8184 asercje**.

**Rozstrzygnięcie użytkownika zawęziło rozdział wobec planu.** Plan wymieniał
sześć pól, w tym zapamiętany katalog zdalny; użytkownik odrzucił to jednym
zdaniem: *„directory to nie jest pole rozdziału. Dla ssh to adres/host,
użytkownik i hasło/certyfikat. Nic więcej nie jest potrzebne. Jeśli moduł ssh
potrzebuje przechować ścieżkę zdalną, to niech zrobi to dla siebie"*. Rozdział
ma przez to **pięć pól** (`host`, `port`, `user`, `auth`, `keyPath`), a katalog
został pamięcią modułu — w sekcji `ssh`, kluczowany **identyfikatorem wpisu**.
Powód jest przy tym wymierny, nie tylko porządkowy: katalog zapisuje się **przy
każdej zmianie katalogu**, więc w książce znaczyłby zapis wspólnego dokumentu
i zdarzenie `entry.changed` kilka razy na sekundę przy chodzeniu po drzewie.

**Co zniknęło z modułu Ssh.** `HostBook`, `HostBookView`, `HostBookPort`,
`LoadedHostBook`, kwerenda `ssh.hosts` — oraz **`HostTarget`**, rozbiór napisu
`[użytkownik@]host[:port]`, który stracił ostatniego odbiorcę, gdy dopisywanie
wpisu przeszło do książki (reguła 13 zastosowana wstecz, precedens z kroku 47).
`SshSession` straciła książkę, licznik jej pokoleń i zapis; został jej stan
sesji. **`HostProfile` został i jest odtąd odczytem z wiersza kwerendy**, a jego
tożsamością jest identyfikator wpisu, nie nazwa.

**Trzy rzeczy warte zapisania.**

1. **Znacznik pola maskowanego wyciął ze spisu każdy wpis z kluczem — złapał to
   przebieg funkcjonalny.** W wierszach spisu pole `keyPath` niesie `set`/`unset`
   (to jest cała jego rola, D104 nr 6), a `HostProfile::fromRow()` brał tę
   wartość jako ścieżkę. `unset` nie jest ścieżką bezwzględną, więc
   samowalidacja odrzucała **cały profil** i host znikał z ekranu. Poprawka jest
   trwalsza niż jedna linia: profil **nie czyta ścieżki z wiersza w ogóle**,
   a `SshQueries::entry()` dokłada ją osobnym pytaniem — w chwili łączenia
   i tylko dla tego jednego wpisu.
2. **Ekran nie umie otworzyć ekranu, więc `F7` nie wraca.** Plan chciał, żeby
   miejsce po `F4`/`F7`/`F8` zajął skrót otwierający książkę
   (`address-book.show ssh`), ale `ScreenOutcome` zna wyłącznie **okna
   nakładane** — przełączenie ekranu z ekranu wymagałoby drogi w rdzeniu dla
   jednego wołającego, wprost przeciwnie do kryterium kroku. Książkę otwiera
   globalny `Ctrl`+`W`, a górny pas spisu mówi o tym wprost.
3. **Pozycja ustawień „sposób uwierzytelnienia" zmieniła znaczenie, nie
   zniknęła.** Wchodzi do deklaracji jako **wartość domyślna pola `auth`**,
   czytana raz, przy pierwszym takcie — więc zmiana pozycji obowiązuje od
   następnego uruchomienia. Inaczej byłaby deklaracją sprzeczną z tą, która już
   stoi, a taka niczego nie przestawia (D104 nr 2).

**Migracja przeszła i ma przebieg.** Stary spis czyta **właściciel sekcji**
(książka cudzych sekcji nie czyta), dopisuje wpisy komendami, pyta
`address-book.last` o identyfikatory i przekluczowuje zapamiętane katalogi
z nazwy na identyfikator; stare klucze zostają na dysku, a znacznik `migrated`
pilnuje, żeby stało się to raz. Przebieg sprawdza wszystkie cztery rzeczy naraz.

**2026-08-19, wieczorem — etap 1 obejrzany pod XTermem, z prawdziwą migracją.**
Aplikacja uruchomiona z tymczasowym `HOME`, w którym leżał **dokument stanu
sprzed kroku 60**: sekcja `ssh` z książką dwóch hostów (jeden z kluczem, port
niestandardowy) i zapamiętanym katalogiem pod nazwą wpisu.

**Migracja przeszła na żywo i zgadza się co do klucza.** Po pierwszym takcie
w dokumencie stoją dwa wpisy z dwunastoznakowymi identyfikatorami i kompletem
wartości rozdziału `ssh`, sekcja `ssh` ma `migrated: true`, a zapamiętany
katalog jest **pod identyfikatorem** — przy czym stary klucz `"biuro"` i stary
spis `hosts` **zostały nietknięte**, dokładnie jak obiecuje migracja
nieniszcząca (D103).

**Cztery klatki obejrzane**: spis hostów czytany z książki (nagłówek mówi, skąd
się bierze, a `F4`/`F7`/`F8` zniknęły ze stopki), zakładka „Wszystkie",
**zakładka „Sesja zdalna" z pięcioma zadeklarowanymi polami** i łańcuch okien
pod `F4`. Na zakładce rozdziału klucz prywatny stoi jako **`set`/`unset`** —
ścieżka nie pokazuje się w tabeli ani razu.

**Oglądanie znalazło dwie usterki, obie niewidoczne dla testów.**

1. **`klucz z pli…`** — kolumna „Sposób" na ekranie hostów ucinała wartość
   o jeden znak (`AUTH_COLUMN` miała 13, a napis ma 13). **To ta sama usterka,
   co „gniazdo lo…" w kroku 58**, znaleziona tą samą drogą i **starsza od tego
   kroku** — stała tam od kroku 48 i żaden test jej nie widział, bo żaden nie
   mierzy szerokości liter.
2. **Tabela książki pokazywała `key`, a okno pytające o to samo pole „klucz
   z pliku".** Wartość rodzaju `choice` tłumaczy się odtąd w obu miejscach tą
   samą konwencją (`<etykieta pola>.<wartość>`); wartość spoza deklaracji
   zostaje surowa, bo klucza dla niej nikt nie zapowiedział.

Zakładki stoją w **kolejności deklaracji**, a ta idzie za kolejnością modułów —
rozdział własny książki jest przez to ostatni („Wszystkie | Sesja zdalna |
Ogólne"). Nie jest to usterka i nie ma powodu tego odwracać: kolejność mówi
prawdę o tym, kto się kiedy zapowiedział.

**2026-08-19, wieczorem — etap 2 domknięty: rozdział `docker`.** `make qa`
zielone: **2447 testów, 8195 asercji**.

**Rozdział ma siedem pól** (`kind`, `socket`, `target`, `port`, `cert`, `key`,
`ca`), a `target` jest rodzaju **`entry`** — i to jest jedyne miejsce w całym
kroku, w którym ten rodzaj ma odbiorcę. Zysk widać przy zmianie nazwy hosta:
przed tym krokiem wpis tunelowy trzymał nazwę i psuł się po cichu, gdy ktoś ją
poprawił. Migracja **przelicza nazwę na odniesienie**, a nazwę, której w książce
nie ma (adres wpisany wprost), zostawia pustą — pole odniesienia nie przyjmuje
czegoś, co nie jest wpisem.

**Warstwa `Application` nie zobaczyła książki i nie miała jak.** `Environments`
jest koordynatorem w `Application`, a fasada odczytu leży w `Presentation`, więc
zamiast portu albo odwrócenia zależności moduł **podaje wpisy raz na takt**
(`useEntries()`). Koordynator dostaje gotową listę i nie wie, skąd się wzięła —
tak samo, jak nie wiedział, że czyta ją z pliku. Pokolenie kwerendy podbija się
przy tym **po treści**, a nie po samym wywołaniu: lista przychodzi trzydzieści
razy na sekundę i prawie zawsze jest ta sama.

**Zniknęły** `EnvironmentBook`, `EnvironmentBookPort`, `LoadedEnvironmentBook`
i **`EnvironmentFlow`** — łańcuch okien dopisywania środowiska, który stracił
odbiorcę, gdy `F4`/`F7`/`F8` zeszły z ekranu do książki (reguła 13 wstecz, tak
samo jak `HostTarget` w etapie pierwszym).

**Trzy rzeczy warte zapisania.**

1. **Wskazanie bieżącego środowiska jest napisem o dwóch znaczeniach** —
   identyfikatorem wpisu albo nazwą kontekstu klienta. Inaczej być nie może:
   kontekst czytany z cudzego pliku nie ma i nie będzie miał identyfikatora,
   a bieżącym bywa równie dobrze on. Rozstrzyga o tym spis złożony z obu źródeł,
   nie sam napis.
2. **Wybiera się identyfikatorem, a mówi nazwą.** Pierwsza wersja przekazywała
   identyfikator także do zdania w pasku stanu i użytkownik czytał „przełączono
   na bbbbbbbbbbbb". Złapał to przebieg funkcjonalny, ale wart jest zapisania
   jako reguła: **tożsamość jest do wybierania, nazwa do mówienia**.
3. **Gniazdo domyślne dotyczy dwóch rodzajów, nie jednego.** Wpis tunelowy
   niesie ścieżkę gniazda **po stronie zdalnej**, więc pusta wartość znaczy
   „domyślne" tak samo, jak przy gnieździe lokalnym; pierwsza wersja
   `fromRow()` dawała domyślne tylko lokalnemu i **wszystkie wpisy tunelowe
   wypadały ze spisu** na samowalidacji. Złapał to przebieg, nie PHPStan.

**2026-08-19, wieczorem — etap 3 domknięty: rozdział `k8s`. Wszystkie trzy
rozdziały stoją.** `make qa` zielone: **2446 testów, 8193 asercje**.

**Rozdział ma cztery pola** (`kubeconfig`, `context`, `namespace`, `timeout`) —
dwie współrzędne miejsca i dwie rzeczy opisujące sposób rozmowy z nim.
Materiału uwierzytelnienia tu nie ma i nie będzie: leży w pliku `kubeconfig`,
do którego aplikacja **nie pisze** (zdanie z kroków 52 i 59 zostaje w mocy).

**Trzecia klasa rozdziału wyszła niemal identyczna jak dwie poprzednie — i to
jest wynik, nie powtórzenie.** Mechanizm miał kosztować tyle samo przy trzecim
rozdziale, co przy pierwszym; `SshChapter`, `DockerChapter` i
`KubernetesChapter` różnią się **spisem pól i tym, co trzeba przeliczyć przy
migracji**, a poza tym są tym samym plikiem. Kryterium „rozdział kosztuje dwie
komendy" jest tym samym spełnione trzy razy.

**Dwie rzeczy warte zapisania.**

1. **Koordynator nie ma jak pisać po książce — więc zamawia.** `Clusters`
   zapisywał przestrzeń nazw i przestawiał współrzędne wpisu wprost w książce;
   teraz zostawia **zamówienia** (`takePendingWrites()`), które moduł wykonuje
   komendą w takcie — wzorem `takeSwitched()`. Wyszło z tego zdanie ogólne dla
   całego kroku: **warstwa `Application` czyta wpisy podane z zewnątrz i zamawia
   zapisy; komendy zna wyłącznie `Presentation`.**
2. **Migracja z pozycji ustawień przeżyła drugą przeprowadzkę.** Kontekst
   zapamiętany w zakładce (sprzed kroku 59) szedł w kroku 59 do książki modułu,
   a teraz idzie do wspólnej — z tym samym warunkiem, co wtedy: **wyłącznie przy
   świeżej sekcji**, bo inaczej każdy start nadpisywałby wybór użytkownika.
   Warunek przeniósł się do portu (`isFresh()`), bo to on jedyny wie, czy sekcja
   jest pusta.

**Dokumentacja domknięta 2026-08-19.** `docs/architecture.md` dostał rozdział
„Książka adresowa: wspólny rejestr wpisów i pól" wraz z sześcioma zdaniami
granicznymi wzorca, a passusy o Dockerze i Kubernetesie mówią odtąd, że ich
spisy są rozdziałami tej książki. `SKILL.md` — reguła **15h** („dana dzielona
dostaje moduł-rejestr, a nie właściciela"). `README.md` — moduł książki wraz
z opisem zakładek i migracji; przy okazji wyszła luka **starsza od tego kroku**:
moduł Kubernetesa nie ma tam wpisu w ogóle i **nie został dopisany**, bo nie
należy do tego zakresu. `CHANGELOG.md` — wpis w sekcji „Niewydane" Fazy XX.

**2026-08-19, po etapie trzecim — usterka zgłoszona przez użytkownika:
zakładka rozdziału pokazywała całą książkę.** Klaster `minikube` stał
w zakładce Dockera, z pustymi kolumnami, choć nie ma z nią nic wspólnego.

**Przyczyna była w kwerendzie, nie w ekranie.** `address-book.entries`
z argumentem rozdziału kształtowała **kolumny**, ale nie **wiersze** — oddawała
całą książkę, a każdy czytelnik odsiewał ją potem sam (moduł Ssh po polu `host`,
Docker po `kind`, k8s po współrzędnych). Ekran nie odsiewał, bo nie miał po
czym. Poprawka przenosi odsiew tam, gdzie należy: **z argumentem rozdziału
kwerenda oddaje wpisy tego rozdziału**, czyli te, które mają w nim jakąkolwiek
wartość. `address-book.entry` zachowuje się tak samo — wpis pytany o rozdział,
do którego nie należy, jest pustą odpowiedzią, a nie wierszem z pustymi
kolumnami.

**Poprawka miała drugą połowę i bez niej byłaby gorsza od usterki.** Skoro do
rozdziału należy się **przez wartości**, to wpis dopisany na jego zakładce
znikałby zaraz po dopisaniu — bo świeży wpis nie ma żadnej. `address-book.add`
przyjmuje więc opcjonalny rozdział i po nazwie prowadzi **od razu przez jego
pola**; `F7` na zakładce podaje tę nazwę sam. Dopisanie wpisu do rozdziału jest
przez to jedną czynnością, a nie dwiema z szukaniem wpisu w zakładce
„wszystkie" pośrodku.

Zdanie ogólne, które z tego zostaje: **argument kwerendy, który zmienia
kolumny, ma zmieniać też wiersze** — inaczej pytanie „co jest w tym rozdziale"
dostaje odpowiedź „wszystko, tylko w części pusto". Pilnują tego dwa przebiegi:
wpis `k8s` nie stoi w zakładce Dockera, a wpis dopisany na zakładce w niej
zostaje.

**2026-08-19, wieczorem — pomiar „po kroku" i próba funkcjonalna z trzema
starymi książkami naraz.** Maszyna zwolniona (obciążenie 0,02–0,05 na rdzeń,
sam edytor).

**Oś `--loop` wobec wzorca sprzed rozdziałów: −1,1% / +1,7%, bez regresji** —
ale **pierwszy przebieg podniósł alarm** (+37,5% na `background-many`) i to jest
rzecz warta zapisania. Wartość bezwzględna wynosi tam **0,12 ms**, więc jedno
zaokrąglenie w tabeli zmienia procent o kilkanaście punktów; przebieg powtórzony
z **sześćdziesięcioma** próbkami zamiast piętnastu dał **+0,9%**. Wniosek na
przyszłość: **na osi `--loop` alarm z jednego przebiegu piętnastu próbek nie
jest jeszcze regresją** — tam mierzy się dziesiąte części milisekundy.

**Tor sixelowy: 23 scenariusze bez regresji** (−4,5% … +2,1%), a **wzorca za
pierwszym razem nie udało się zapisać** — strażnik rozrzutu odrzucił przebieg
przez jeden niestabilny scenariusz (`zwijane sekcje`), tak samo jak dwukrotnie
w kroku 59. Drugi przebieg był spokojny i wzorzec stanął:
`2026-08-19-po-kroku-60.json` oraz `2026-08-19-po-kroku-60-loop.json`.

**Próba funkcjonalna zrobiła to, czego nie zrobi żaden test jednostkowy:
uruchomiła aplikację ze stanem sprzed kroku 60 zawierającym trzy stare książki
naraz.** Po jednym takcie w dokumencie stały cztery wpisy z rozdziałami `ssh`,
`docker` (dwa) i `k8s`, oba wskaźniki bieżącego przeliczone na identyfikatory,
trzy znaczniki `migrated` i **stare klucze nietknięte**. Najważniejsze:
**cel tunelu `serwer` wskazuje `b0aec15df0fd`, czyli identyfikator wpisu
`biuro`** — nazwa zamieniła się w odniesienie **ponad granicą modułów**, co jest
zdaniem-miarą etapu drugiego sprawdzonym na prawdziwych danych.

**Zakładki potwierdziły poprawkę filtrowania**: „Docker" pokazuje `lokalny`
i `serwer`, „Kubernetes" — samo `minikube`, „Wszystkie" — komplet czterech
wpisów wraz z kolumną rozdziałów. Rodzaj połączenia stoi przetłumaczony
(„gniazdo lokalne", „tunel SSH"), a trzy kolumny TLS — jako `unset`.

**Oglądanie klatki znalazło jeszcze jedną rzecz.** Kolumna „Host tunelu"
pokazywała **surowy identyfikator** wskazywanego wpisu zamiast jego nazwy.
Poprawione: pole rodzaju `entry` rysuje się nazwą celu, a identyfikator zostaje
tylko wtedy, gdy wpisu już nie ma — bo wtedy jest to jedyne, co o nim wiadomo.
To ta sama zasada, która wyszła w etapie drugim przy komunikacie o przełączeniu:
**identyfikatorem się wskazuje, nazwą się mówi.** Obie zaległości po kroku usuniętym — pomiar
i klatka pod XTermem — są **zamknięte** (patrz wpis niżej).
