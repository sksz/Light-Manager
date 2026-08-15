# Krok 48 — Moduł `Ssh`: sesja, uwierzytelnienie i książka hostów

> **Skąd ten krok.** Powstał 2026-08-15, na polecenie użytkownika, razem
> z krokami 49 i 50 jako pierwsza trzecia Fazy XVII ([00-decyzje.md](../00-decyzje.md),
> D84). Rozdzielone są dlatego, że każdy dowozi **inną rzecz**: ten sesję, tamten
> odczyt zdalnego katalogu, ostatni przesył plików.

## Status

**Ukończony z zastrzeżeniem** (2026-08-15).

> **Zastrzeżenie:** klatki pod XTermem nikt jeszcze nie oglądał, a próba
> z żywym serwerem szła po **pętli zwrotnej** (kontener), nie przez prawdziwą
> sieć — szczegóły w dzienniku realizacji na końcu pliku.

> **Rozstrzygnięcia startowe odwróciły drogę techniczną kroku i całej fazy —
> [00-decyzje.md](../00-decyzje.md), D87.** Sesja **nie** żyje w procesie
> aplikacji: `ext-ssh2` wypada z fazy w całości, a jego miejsce zajmuje klient
> OpenSSH uruchamiany procesem potomnym, z połączeniem trwającym przez
> `ControlMaster`/`ControlPersist`. Sekcje „Zastrzeżenie", „Zakres" nr 1, 3 i 4
> oraz „Planowane zmiany w plikach" są poniżej **przepisane**; reszta planu
> obowiązuje bez zmian. Cel, kryteria ukończenia i granice zakresu zostały te
> same — zmieniło się wyłącznie, czym się je dowozi.

## Cel

Aplikacja ma umieć nawiązać i utrzymać sesję SSH do wskazanego hosta, a spis
hostów ma być rzeczą, którą użytkownik prowadzi z ekranu modułu, a nie wpisuje
przy każdym uruchomieniu.

Miarą powodzenia jest zdanie: **`Ctrl`+`S` pokazuje spis hostów, `Enter` łączy
z podświetlonym, pasek stanu mówi z kim aplikacja jest połączona, a host
o nieznanym odcisku klucza zatrzymuje połączenie pytaniem, na które trzeba
odpowiedzieć.**

Plików zdalnych ten krok **nie pokazuje** — to jest zakres kroku 49. Sesja bez
przeglądarki plików jest przy tym pełnoprawnym odbiorcą samej siebie: ekran
z listą hostów, ich stanem i odciskiem klucza jest tym, co reguła 13 nazywa
prawdziwym użytkownikiem mechanizmu.

## Zastrzeżenie — rozstrzygnięte na starcie (D87 nr 1, 2 i 3)

**Postawienie problemu zostaje w mocy i warto je znać, bo to ono przesądziło
o kształcie kroku.** `ext-ssh2` nie ma ani jednego wywołania nieblokującego,
a `ssh2_connect()` **nie przyjmuje limitu czasu** — sprawdzone w sygnaturze,
argumenty to `(host, port, methods, callbacks)`. Host nieosiągalny zatrzymałby
pętlę na `default_socket_timeout`, czyli domyślnie na **minutę**, i byłoby to
zawieszenie całej aplikacji, nie samego modułu. Sam uścisk dłoni z hostem
osiągalnym kosztowałby setki milisekund, czyli kilkanaście zgubionych klatek.

**Rozwiązanie: sesja nie żyje w procesie aplikacji.** Trzy warianty pośrednie —
przyjęcie zamrożenia z ograniczeniem od góry, strażnik na `pcntl_alarm()`,
uścisk w potomku przy sesji w rodzicu — zostały postawione i odrzucone. Zasób
sesji nie przechodzi przez granicę procesu, więc rozstrzygnięcie obejmuje
**całą Fazę XVII**, a nie sam krok 48.

Reguła nadrzędna fazy zostaje ta sama i **staje się łatwiejsza do dotrzymania,
nie trudniejsza**: żadne wywołanie sieciowe nie pada w rysowaniu klatki — bo
żadne nie pada w procesie aplikacji w ogóle. Aplikacji zostaje `poll()` na
uchwycie pracy tłowej, które z definicji nie blokuje.

## Zależności

- **Krok 20** twardo i całkowicie: moduł bierze kontrakt taki, jaki tam powstał,
  i jest jego **czwartym sprawdzianem** — po przeglądarce (21), module bez
  ekranu (36) i module pracującym, gdy go nie widać (45). Rdzeń ma kosztować
  **jedną linię w `Bootstrapie`** (reguła 15).
- **Krok 36** — zależność **zniesiona przez D87 nr 11**. Planowana była jako
  najmocniejsza w kroku (port z dwiema implementacjami, wybór raz przy składaniu
  modułu — reguła 11o), ale degradacja poszła inną drogą: brak klienta OpenSSH
  **odrzuca moduł**, więc pustego obiektu nie ma czego dowozić. Różnica wobec
  `ext-glfw` jest zamierzona — tam brak rozszerzenia zostawiał moduł działający
  na ciszy, tu nie ma czego zostawić.
- **Krok 26** twardo i to jest zależność, która zastąpiła powyższą jako
  najmocniejsza: potomków uruchamia rdzeniowy `BackgroundProcessPort` (D87 nr 9),
  więc krok jest jego **drugim odbiorcą** po `du` z modułu opisu pliku — i płaci
  jego cenę, czyli jedną pracę naraz.
- **Krok 45** wzorcowo: książka hostów mieszka w **pliku stanu modułu**
  (`~/.light-manager/ssh.json`), bo `ModuleSetting` bierze wyłącznie skalary
  (`bool|int|string`) — dokładnie ten sam rachunek, który wypchnął tam playlistę
  (D82 nr 3). Nieznane klucze mają przeżywać zapis od pierwszego dnia, bo kroki
  49 i 50 dopiszą do tego samego dokumentu.
- **Krok 18 i 19** — `ListView`, `Table`, `Dialog`, `PromptOverlay`,
  `ConfirmOverlay`, `TextInput`, kontrakt komendy i podpowiedzi argumentów.
  Komponentu krok **nie dokłada**.
- **Krok 26 i 47** — sprzątanie dwiema drogami (jawnie w `Bootstrap::shutdown()`
  i przez `register_shutdown_function`, D47): sesja SSH jest zasobem, który musi
  zginąć razem z procesem, jak proces potomny i silnik audio.
- **Krok 46** miękko, ale za darmo: moduł deklaruje `DeclaresEvents`, więc
  „połączono”, „rozłączono” i „nie udało się połączyć” dostają dźwięk bez ani
  jednej linii w rdzeniu. To jest zarazem **pierwszy sprawdzian mechanizmu
  zdarzeń przez moduł, którego przy jego powstawaniu nie było**.
- **Krok 40** — ekran ma więcej niż jedno miejsce ognisko deklaruje
  (`DeclaresFocus`), inaczej stopka będzie o nim milczeć.
- Od **kroków 41–44** nie zależy w ogóle: operacje na plikach dotyczą dysku
  lokalnego, a ten krok nie dotyka dysku poza własnym plikiem stanu.

## Model i wysiłek

**Opus / high.**

Wszystkie wzorce, których krok potrzebuje, projekt ma: moduł z ekranem (45),
port z dwiema implementacjami (36), plik stanu modułu (45), sprzątanie zasobu
(26, 36). Nowe są dwie rzeczy i obie są wąskie: **weryfikacja klucza hosta**
(patrz zakres nr 4 — droga jest wykonalna, ale nieoczywista) oraz **blokujące
wejście-wyjście w takcie pętli**. Kontraktu rdzenia krok nie rusza, torów
renderowania nie dotyka, komponentu nie dokłada — czyli nie zachodzi żaden
z warunków, dla których plan przewiduje `xhigh`.

## Stan zastany (sprawdzone przy planowaniu 2026-08-15 / do potwierdzenia na starcie kroku)

| Element | Stan |
|---|---|
| `ext-ssh2` | **Załadowane, wersja 1.3.1 na libssh2 1.11.0.** 34 funkcje, w tym `ssh2_auth_agent`, cały zestaw `ssh2_sftp_*` i `ssh2_exec` |
| Opakowania strumieni | Zarejestrowane: `ssh2.shell`, `ssh2.exec`, `ssh2.tunnel`, `ssh2.scp`, `ssh2.sftp` |
| `ssh2_connect()` | **Bez parametru limitu czasu** — `(host, port, methods, callbacks)` |
| `ssh2_fingerprint()` | Wyłącznie **MD5 i SHA1** (`SSH2_FINGERPRINT_MD5`, `SSH2_FINGERPRINT_SHA1`, `..._HEX`, `..._RAW`). **SHA256 nie ma**, a to jest postać, którą pokazuje dzisiejszy OpenSSH |
| API `known_hosts` libssh2 | **Nieudostępnione w PHP** — w spisie funkcji nie ma ani jednej `ssh2_known_hosts*` |
| `~/.ssh/known_hosts` użytkownika | 23 wpisy, **wszystkie z zahaszowaną nazwą hosta** (`\|1\|salt\|hash`), typy `ssh-rsa`, `ecdsa-sha2-nistp256`, `ssh-ed25519` |
| Agent | `SSH_AUTH_SOCK` ustawiony (`/run/user/1000/keyring/ssh`) — `ssh2_auth_agent` ma z czym rozmawiać |
| `~/.ssh/config` | Istnieje, dwa wpisy `Host`. **libssh2 go nie czyta** — profil hosta trzeba podać modułowi wprost albo przeczytać plik samodzielnie |
| Litera skrótu `s` | **Wolna**: zajęte `b`, `d`, `a`; `ModuleRegistry::FORBIDDEN_CHARACTERS` to `c, h, i, j, m, z` |
| `Ctrl`+`S` w terminalu | **Bezpieczne** — `TerminalService::RAW_MODE_SETTINGS` zawiera `-ixon`, więc XOFF nie zadziała |
| Serwer SSH do sprawdzenia | **Na maszynie nie nasłuchuje żaden** (port 22 zamknięty). Jest za to `docker` — sprawdzenie ręczne wymaga albo kontenera z `sshd`, albo hosta podanego przez użytkownika |
| `src/Module/Ssh/` | Nie istnieje |

## Zakres

### 1. Sesja jako proces potomny trzymany przez `ControlMaster`

**Przepisane wedle D87 nr 1, 2, 3, 7 i 9.**

`Module\Ssh\Application\Port\SshSessionPort` — nawiązanie, zerwanie, stan
bieżącej sesji, posunięcie łączenia o takt. Implementacja jest **jedna**
(`OpenSshSessionService`), bo brak klienta odrzuca cały moduł; pustego obiektu
nie ma czego dowozić.

Sesja trwa przez **mistrza połączenia**: `ssh -M -N -f -o ControlPath=…
-o ControlPersist=…` zestawia ją raz i **demonizuje się sam**, więc aplikacja nie
trzyma jego potoków ani przez chwilę. Gniazdo mieszka w `~/.light-manager/`,
a jego nazwa bierze się z profilu hosta. Trzy czynności i wszystkie są krótkimi
potomkami:

| Czynność | Polecenie |
|---|---|
| zestawienie | `ssh -M -N -f -o ControlPath=… -o ControlPersist=… <cel>` |
| stan | `ssh -O check -o ControlPath=… <cel>` |
| rozłączenie | `ssh -O exit -o ControlPath=… <cel>` |

Potomków uruchamia **rdzeniowy `BackgroundProcessPort`** (D87 nr 9) — moduł
sięga po port rdzenia, jak `FileInfo` po `du`. Cena przyjęta świadomie: **jedna
praca naraz**, więc zestawianie sesji przerwie liczenie `du` i odwrotnie.

Port **nie rzuca przez granicę** (reguła 8): niepowodzenie wraca opisem w stanie,
bo połączenie nie udaje się rutynowo, a nie wyjątkowo.

**Jedna sesja naraz** (D87 nr 7), zgodnie z rekomendacją i regułą 11d: jedno
okno postępu, jedno miejsce w pasku stanu, jeden stan do narysowania.

### 2. Książka hostów w pliku stanu modułu

`~/.light-manager/ssh.json`, wzorem `audio.json` z kroku 45: zapis przez plik
tymczasowy i `rename()`, nieznane klucze przeżywają zapis (kroki 49 i 50 dopiszą
do tego dokumentu ostatni katalog i historię przesyłów).

Profil hosta: nazwa własna, host, port, użytkownik, sposób uwierzytelnienia,
ścieżka klucza, katalog startowy na zdalnej stronie. **Hasła w pliku nie ma
i mieć nie będzie** — patrz rozstrzygnięcie nr 2.

### 3. Uwierzytelnienie

**Przepisane wedle D87 nr 4.** Trzy drogi, wszystkie prowadzone przez klienta
OpenSSH, w kolejności malejącego bezpieczeństwa:

| Droga | Jak | Cena |
|---|---|---|
| agent | `-o PreferredAuthentications=publickey`, agent z `SSH_AUTH_SOCK` | żadna — klucz nie opuszcza agenta |
| klucz z pliku | `-o IdentityFile=<ścieżka> -o IdentitiesOnly=yes` | ścieżka w profilu hosta; klucz zaszyfrowany hasłem pyta o nie jak niżej |
| hasło | pytanie w oknie, przekazane klientowi przez `SSH_ASKPASS` | **maskowany tryb `TextInput`** — zmiana komponentu rdzenia |

Hasło **weszło do zakresu wbrew rekomendacji planu** (D87 nr 4). Hasła w pliku
nie ma i nie będzie — pytanie pada przy każdym połączeniu, a odpowiedź nie
przeżywa go ani o klatkę.

Droga do klienta prowadzi przez `SSH_ASKPASS` z `SSH_ASKPASS_REQUIRE=force`,
bo `ssh` czyta hasło **z terminala sterującego, nie ze standardowego wejścia**
— a `BackgroundProcessPort` i tak nie umie podać potomkowi wejścia (krok 26).

### 4. Odcisk klucza hosta i `known_hosts`

**Przepisane wedle D87 nr 5 i 6.** Zapisu pilnuje `ssh`, odczytu — moduł.

Rachunek rozpisany w planie (`ssh2_methods_negotiated()` → SHA1 z base64 →
`ssh2_fingerprint()`) znika razem z rozszerzeniem. Zostaje droga krótsza,
w której aplikacja **nie dotyka `~/.ssh/known_hosts` do zapisu ani razu**:

1. `KnownHostsReader` — **klasa czysta**, czyta `~/.ssh/known_hosts` i mówi, czy
   host jest znany. Nazwy są zahaszowane (wszystkie 23 wpisy na maszynie
   projektu), więc dopasowanie to `hash_hmac('sha1', $host, base64_decode($salt))`
   porównane z drugą połową pola `|1|<sól>|<skrót>`; wpisy jawne i wzorce
   też obsługuje. To jedyny sposób, żeby ekran **przed** połączeniem wiedział,
   czy pytanie padnie.
2. Host znany → `-o StrictHostKeyChecking=yes -o BatchMode=yes`. Klucz
   **niezgodny** z zapamiętanym to nie pytanie, tylko odmowa — i tej odmowy nie
   piszemy sami: `ssh` odmawia z własnym komunikatem, a moduł go pokazuje.
3. Host nieznany → odcisk bierze potok
   `ssh-keyscan -T <limit> -p <port> <host> | ssh-keygen -lf -`, po czym pyta
   `ConfirmOverlay` w wariancie **`dangerous`** (ten sam, którym usuwa się
   trwale). Keyscan jest w łańcuchu dlatego, że `ssh` wypisuje „host nieznany”
   na **strumieniu błędów**, a `BackgroundState` go świadomie nie niesie (krok
   26). Zyskuje się przy tym postać `SHA256:…` — tę samą, którą pokazuje `ssh`,
   a której `ssh2_fingerprint()` nie umiał w ogóle. Ten sam potok jest zarazem
   **sprawdzeniem osiągalności** hosta, ograniczonym w czasie przez `-T`, więc
   `stream_socket_client()` z planu nie jest potrzebny.
4. Zgoda → połączenie z `-o StrictHostKeyChecking=accept-new`, a wiersz dopisuje
   **klient**, w postaci kanonicznej i zahaszowanej (`HashKnownHosts yes`).
   Odmowa → połączenia nie ma i w pliku nie zmienia się nic.

### 5. Ekran modułu i klawisze

`Ctrl`+`S` otwiera ekran ze spisem hostów: `Table` o trzech kolumnach (nazwa,
`użytkownik@host`, stan), `Enter` łączy albo rozłącza, `F7` dodaje wpis
(`PromptOverlay`), `F8` usuwa (`ConfirmOverlay`), `F5` odświeża stan. Ognisko
deklaruje `DeclaresFocus`, więc stopka mówi, co działa tu i teraz (krok 40).

Komendy: `ssh.connect <nazwa>` (z podpowiedziami z książki hostów przez
`SuggestsArguments`), `ssh.disconnect`, `ssh.hosts`. Komenda otwierająca okno
deklaruje `OpensOverlay` (krok 47), więc trafia do menu `F9` za darmo.

Zakładka ustawień modułu: limit czasu połączenia, domyślny sposób
uwierzytelnienia, czy pamiętać odciski kluczy, czy łączyć się przy starcie
z ostatnim hostem.

### 6. Sprzątanie i zdarzenia

`ssh2_disconnect()` przy każdym wyjściu, **dwiema drogami naraz** (D47).
Zdarzenia modułu (`DeclaresEvents`, krok 46): `ssh.connected`,
`ssh.disconnected`, `ssh.failed` — nazwy w przestrzeni publikującego, jak każe
reguła 11o''.

### 7. Pomiar

Oś `--loop` „przed i po”: jeśli moduł weźmie takt (rozstrzygnięcie nr 6),
rozlicza się nim tak samo, jak takt playlisty w kroku 45. Scenariusza klatki
krok **nie dokłada** — ekran to `Table` w strefie środkowej, czyli treść mierzona
już przez `columns`; powód pominięcia idzie do
[docs/pomiary/README.md](../../pomiary/README.md).

Osobno, poza `bin/render-bench`, do zapisania w dzienniku kroku: **ile trwa
uścisk dłoni** na maszynie projektu. To nie jest pomiar klatki i narzędzie go nie
zna, ale jest liczbą, od której zależy odpowiedź na zastrzeżenie ze startu.

## Poza zakresem

- **Przeglądanie zdalnych plików** — krok 49. Ten krok kończy się na sesji.
- **Przesył plików** — krok 50.
- **Sesja powłoki (`ssh2_shell`)** — emulacja sekwencji sterujących i własny
  bufor ekranu to osobna funkcja, której aplikacja nie ma w żadnej postaci.
- **Tunele i przekierowania portów** (`ssh2_tunnel`, `ssh2_forward_listen`) —
  nie ma odbiorcy, czyli reguła 13.
- **`~/.ssh/config`, `ProxyJump`, `Match`** — libssh2 tego pliku nie czyta,
  a napisanie własnego parsera `ssh_config` jest osobną pracą o rozmiarze kroku.
  Profil podaje się modułowi wprost.
- **Wiele sesji naraz** — jedna sesja, wzorem „jedna praca naraz” (11d).
- **Przekazywanie agenta (agent forwarding)** i dopisywanie kluczy do agenta.
- **Hasło jako droga uwierzytelnienia** — dopóki nie ma maskowanego pola
  tekstowego (rozstrzygnięcie nr 2).

## Planowane zmiany w plikach

**Przepisane wedle D87.** Zmiany wobec planu pierwotnego: znika
`Ssh2SessionService` i `UnavailableSshService` (jedna implementacja zamiast
dwóch), znika `ext-ssh2` z `composer.json`, dochodzą **dwie zmiany rdzenia**
ponad przewidzianą linię.

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Module/Ssh/Domain/ValueObject/{HostProfile,HostFingerprint}.php` | Moduł/Domain | **Nowe** — profil hosta i odcisk klucza wraz z samowalidacją |
| `Module/Ssh/Domain/Exception/…` | Moduł/Domain | **Nowe** — wyjątki modułu z `DescribesProblem` (reguła 8) |
| `Module/Ssh/Application/Port/SshSessionPort.php` | Moduł/Application | **Nowy** — kontrakt sesji |
| `Module/Ssh/Application/Port/HostBookPort.php` | Moduł/Application | **Nowy** — odczyt i zapis książki hostów |
| `Module/Ssh/Application/{SshSettings,SessionState}.php` | Moduł/Application | **Nowe** — ustawienia modułu i stan sesji jako dana oglądana co klatkę |
| `Module/Ssh/Application/UseCase/{Connect,Disconnect}UseCase.php` | Moduł/Application | **Nowe** |
| `Module/Ssh/Infrastructure/OpenSshSessionService.php` | Moduł/Infrastructure | **Nowa** — Singleton prowadzący mistrza `ControlMaster` przez `BackgroundProcessPort` |
| `Module/Ssh/Infrastructure/KnownHostsReader.php` | Moduł/Infrastructure | **Nowa, czysta** — dopasowanie zahaszowanej nazwy (HMAC-SHA1), wpisów jawnych i wzorców |
| `Module/Ssh/Infrastructure/SshStateService.php` | Moduł/Infrastructure | **Nowa** — `~/.light-manager/ssh.json` |
| `Module/Ssh/Presentation/{SshModule,HostsScreen}.php` | Moduł/Presentation | **Nowe** — moduł i ekran |
| `Module/Ssh/Presentation/Command/{Connect,Disconnect,Hosts}Command.php` | Moduł/Presentation | **Nowe** |
| `Module/Ssh/lang/{pl,en}.php` | Napisy | Nazwy, stany sesji, komunikaty niepowodzeń, pytanie o odcisk |
| `Application/Module/RequiresEnvironment.php` | **Rdzeń** | **Nowa zdolność** (D87 nr 11) — `unavailableReason(): ?string`, deklarowana osobno jak `NeedsTick` |
| `Application/Module/ModuleRegistry.php` | **Rdzeń** | **Piąty powód odrzucenia** — pytanie o zdolność w `admit()` |
| `Presentation/Ui/Component/TextInput.php` | **Rdzeń** | **Tryb maskowany** (D87 nr 4) — treść rysowana znakiem zastępczym |
| `Presentation/Cli/Bootstrap.php` | Rdzeń | **Jedna linia** — pozycja na liście modułów (reguła 15) |
| `lang/{pl,en}.php` | Napisy rdzenia | Powód odrzucenia `module.rejected.environment` |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Moduł, reguła „sieć nie pada w rysowaniu”, weryfikacja klucza hosta, wymaganie klienta OpenSSH |
| testy | Testy | **Żaden test nie otwiera połączenia** — atrapa portu (`tests/Support/StubSshSession`), czysty `KnownHostsReader` na wpisach przykładowych, plik stanu na katalogu tymczasowym |

## Rozstrzygnięte na starcie kroku (2026-08-15)

Pełne uzasadnienia i odrzucone alternatywy: [00-decyzje.md](../00-decyzje.md), D87.
**Cztery odpowiedzi poszły wbrew rekomendacji planu** — nr 1, 2, 3 i 7.

| # | Pytanie | Rozstrzygnięcie | Wobec rekomendacji |
|---|---|---|---|
| 1 | Zamrożona klatka na czas uścisku | **Cała sesja w procesie potomnym** (klient OpenSSH, `ControlMaster`) | wbrew — obejmuje całą Fazę XVII |
| 2 | Hasło jako droga uwierzytelnienia | **Tak**, z maskowanym trybem `TextInput` w rdzeniu | wbrew |
| 3 | Gdzie mieszkają odciski | **`~/.ssh/known_hosts`**, prowadzony przez `ssh` | wbrew |
| 4 | Czy czytamy `known_hosts` | **Tak** — czysty `KnownHostsReader` | zgodnie |
| 5 | Jedna sesja czy wiele | **Jedna** | zgodnie |
| 6 | Czy moduł bierze takt | **Tak** — przy potomku warunek D82 spełniony wprost | — |
| 7 | Aplikacja bez narzędzia | **Moduł odrzucony przez rejestr** — nowa zdolność `RequiresEnvironment` | wbrew |
| 8 | Łączenie przy starcie | **Nie** — start nie sięga do sieci ani razu | zgodnie |
| 9 | Czym uruchamiać potomków | **Rdzeniowy `BackgroundProcessPort`** — jedna praca naraz przyjęta świadomie | pytanie wynikłe z nr 1 |
| 10 | Jak sesja trwa | **`ControlMaster` + `ControlPersist`** | pytanie wynikłe z nr 1 |

**Kryterium ukończenia „rdzeń urósł o jedną linię" jest tym samym odwołane.**
Rdzeń rośnie o trzy rzeczy: linię w `Bootstrapie`, tryb maskowany `TextInput`
(nr 2) i zdolność `RequiresEnvironment` wraz z piątym powodem odrzucenia (nr 7).
Obie zmiany ponad linię są rozstrzygnięciami użytkownika podjętymi z ceną
wypisaną **przed** wyborem, a nie długiem przeoczonym w trakcie — i obie są
mechanizmami rdzenia z odbiorcą (reguła 13), a nie funkcją modułu wepchniętą do
rdzenia.

## Kryteria ukończenia

- `Ctrl`+`S` otwiera spis hostów; wpis dodany w oknie przeżywa ponowne
  uruchomienie aplikacji.
- `Enter` na wpisie łączy przez agenta albo klucz, a pasek stanu mówi, z kim
  aplikacja jest połączona.
- Host o nieznanym odcisku **zatrzymuje połączenie** oknem groźnym; odcisk
  niezgodny z zapamiętanym odmawia bez pytania.
- Host nieosiągalny kończy się komunikatem **w czasie z ustawienia**, a nie po
  minucie — i przez ten czas aplikacja rysuje klatki.
- Wyjście z aplikacji zamyka sesję **obiema drogami** — także po błędzie
  krytycznym.
- Bez klienta OpenSSH aplikacja działa jak dotąd, a moduł **znika ze spisu
  z powodem** widocznym na zakładce „Moduły" (D87 nr 11 — zmienione wobec planu,
  który przewidywał moduł przyjęty z komunikatem).
- Rdzeń urósł o **jedną linię w `Bootstrapie` plus dwie zmiany rozstrzygnięte
  jawnie** (tryb maskowany `TextInput`, zdolność `RequiresEnvironment`) —
  **i o nic ponad to**. Kryterium pierwotne („o jedną linię") odwołane przez D87;
  każda czwarta zmiana rdzenia jest błędem do naprawienia (reguła 15).
- `bin/render-bench --loop` „przed i po” bez regresji.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, `make qa` zielone; **żaden test
  nie otwiera połączenia sieciowego**.

## Dziennik realizacji

### 2026-08-15 — rozstrzygnięcia startowe i przepisanie planu

Osiem pytań z sekcji „Do rozstrzygnięcia" plus dwa wynikłe z odpowiedzi na
pierwsze plus jedno zadane ponownie po sprostowaniu — wszystkie w
[00-decyzje.md](../00-decyzje.md), D87. Stan zastany sprawdzony przed pytaniami
i zgadza się z tabelą co do wiersza; rozpoznanie dołożyło dwa fakty, których
plan nie miał: klient OpenSSH 9.6p1 wraz z `sftp`, `ssh-keyscan` i `ssh-keygen`
jest w `PATH`, a `HashKnownHosts` stoi na `yes`.

**Dwie rzeczy sprostowane w trakcie, obie zmieniające cenę wyboru:**

1. Napisano użytkownikowi, że „rejestr zna wariant odrzucenia środowiskowego" —
   **nie zna**. `ModuleRegistry::admit()` odrzuca z czterech powodów i wszystkie
   dotyczą deklaracji, nie środowiska. Pytanie zadano ponownie z prawdziwym
   rachunkiem (nowa zdolność + ~5 linii w `admit()`); odpowiedź się nie zmieniła.
2. Zapisano w D87, że `ssh-keyscan` jest niepotrzebny. **Jest potrzebny** — nie
   do zapisu, lecz do pokazania odcisku w oknie pytania, bo `ssh` wypisuje „host
   nieznany" na strumieniu błędów, którego `BackgroundState` świadomie nie
   niesie. Poprawka wpisana do D87 nr 5 i do zakresu nr 4.

Plan przepisany w pięciu miejscach (status, zastrzeżenie, zakres 1/3/4, spis
plików, rozstrzygnięcia); cel, granice zakresu i kryteria ukończenia — poza
odwołanym kryterium „jednej linii" — zostały nietknięte.

### 2026-08-15 — wykonanie

**Rdzeń urósł o cztery rzeczy, nie o trzy zapowiedziane.** Trzy stoją w D87
(pozycja w `Bootstrapie`, tryb maskowany `TextInput`, zdolność
`RequiresEnvironment` wraz z piątym powodem odrzucenia). Czwarta doszła
**w trakcie i wynikła z obejrzenia okna**: `ConfirmOverlay` rysował pytanie
w jednym wierszu o szerokości najwyżej 64 kolumn i ucinał resztę, a odcisk
SHA256 to samo 50 znaków — użytkownik dostałby pytanie „zaufać temu kluczowi?"
z kluczem uciętym w połowie. Pytanie wróciło do użytkownika z trzema wariantami
i wybrał zawijanie w rdzeniu; dawne uzasadnienie ucinania („nazwa ucięta widoczna
jest piętro niżej, pod kursorem listy") okazało się prawdziwe **tylko dla nazw
wpisów**. Piąta zmiana rdzenia jest **poprawką usterki, nie mechanizmem**: napisy
modułów wchodzą do katalogu z `declared()`, a nie `accepted()`, bo zakładka
„Moduły" tłumaczy także moduł odrzucony — przy `accepted()` wypisywała tam surowe
klucze. Do tego kroku nikt tego nie widział, bo wszystkie cztery dotychczasowe
powody odrzucenia były błędami autora modułu.

**Warstwy `UseCase` moduł nie dostał, choć plan ją przewidywał.** Powód ten sam,
dla którego nie ma jej moduł dźwięku: `ConnectUseCase` i `DisconnectUseCase`
byłyby przepuszczeniem wywołania do portu. Ich miejsce zajął koordynator
`Application\SshSession` (wzorem `PlaylistPlayer`) i `Presentation\ConnectFlow` —
ten drugi powstał, bo łańcuch okien ma **dwa wejścia**: `Enter` w spisie i komenda
`ssh.connect` (reguła 11n).

**Ekran dostał klawisz, którego plan nie przewidywał** (`F4`, zmiana sposobu
uwierzytelnienia). Bez niego sposób dałoby się zmienić **wyłącznie przez edycję
pliku**, bo zakładka ustawień rządzi tylko wpisami nowymi — a spis, który
użytkownik prowadzi z ekranu, musi umieć zmienić to, co już w nim stoi.

#### Próba z żywym serwerem — trzy usterki, których testy nie mogły złapać

Sprawdzenie ręczne poszło na kontenerze `atmoz/sftp:alpine` (port 2222),
**prawdziwym kodem modułu**, nie skrótem przez powłokę. Wykryło trzy rzeczy
i wszystkie trzy były prawdziwymi usterkami:

1. **`ssh` rozwija `~` w `UserKnownHostsFile` z wpisu w `passwd`, a nie
   z `HOME`** — a `KnownHostsReader` czytał `HOME`. Na zwykłej maszynie to ten
   sam plik, ale **z przypadku, nie z gwarancji**; w próbie te drogi się rozeszły
   i zapisany wpis „zniknął" czytającemu. Poprawka: usługa narzuca klientowi
   `UserKnownHostsFile=` z **tą samą** ścieżką, którą czyta moduł, więc obie
   strony zgadzają się z konstrukcji.
2. **`ssh -O exit` szedł portem pracy tłowej, a ten prowadzi jedną pracę naraz** —
   więc najbliższe `connect()` ubijało zamknięcie, zanim zdążyło zadziałać, i po
   próbie zostawało żywe gniazdo mistrza. Poprawka: zamykanie idzie odłączonym
   `exec(… &)`, którego nie ma czym wyprzeć i na które nikt nie czeka (rozmowa
   z gniazdem na dysku, nie z siecią).
3. **`shutdown()` zamykał mistrza tylko przy etapie `Connected`** — a mistrz jest
   procesem zdemonizowanym i istnieje niezależnie od tego, co stan zdążył
   pokazać. Poprawka: usługa pamięta profil, dla którego mistrz **istnieje**
   (`$master`), i zamyka po nim, nie po etapie.

**Pomyłka po mojej stronie, którą trzeba zapisać:** próba miała chronić
`~/.ssh/known_hosts` użytkownika tymczasowym `HOME` — i **nie ochroniła**,
bo o usterce nr 1 nikt jeszcze nie wiedział. Do pliku doszedł jeden wiersz
z kluczem kontenera; plik przywrócono z kopii zrobionej przed próbą
i zweryfikowano bajt po bajcie (23 wiersze, identyczny). Druga próba, już po
poprawce, pliku użytkownika nie tknęła. Osobno: pierwszy przebieg raportował
„2 procesy mistrza" — to była **pomyłka pomiaru**, bo `pgrep -f 'ssh -M -N'`
łapał własną linię poleceń skryptu; prawdziwych procesów `ssh` było zero,
a prawdziwe było wyłącznie osierocone gniazdo.

#### Liczby z próby (kontener na pętli zwrotnej, maszyna projektu)

| Czynność | Czas | Klatek po 33 ms |
|---|---|---|
| odcisk hosta (`ssh-keyscan \| ssh-keygen -lf -`) | **202 ms** | 6 |
| uścisk dłoni, host nieznany, po zgodzie | **199 ms** | 6 |
| uścisk dłoni, host już znany | **199 ms** | 6 |
| `ssh -O check` (stan sesji) | **66 ms** | 2 |
| odmowa przy złym haśle | **199 ms** | 6 |
| host nieosiągalny (port zamknięty) | **66 ms** | 2 |

**To jest liczba, od której zależała odpowiedź na zastrzeżenie startowe:** uścisk
dłoni kosztuje ~200 ms, czyli **sześć klatek**. W procesie aplikacji byłoby to
sześć klatek zamrożonych; w procesie potomnym są to klatki narysowane z oknem
postępu, bo jedyne, co pada w takcie, to `poll()`. Liczby są przy tym
**zaniżone wobec prawdziwej sieci** — pętla zwrotna nie ma opóźnienia — i to
wzmacnia wniosek, a nie osłabia: przy prawdziwym hoście różnica między
zamrożeniem a rysowaniem jest większa, nie mniejsza.

Sprawdzone przy okazji i zgodne z oczekiwaniem: odciski wracają w postaci
`SHA256:…` (tej samej, którą pokazuje `ssh`; odrzucony wariant na `ext-ssh2`
umiał wyłącznie SHA1), wpis dopisany przez klienta jest **zahaszowany**, drugie
połączenie z tym samym hostem **nie pyta o odcisk**, złe hasło kończy się
`module.ssh.problem.denied`, a `shutdown()` zostawia zero gniazd i zero procesów.

#### Pomiar

`bin/render-bench --loop` „przed i po" wobec wzorca po kroku 46: dwa przebiegi,
**+3,6%** i **−1,1%** — obie strony zera, czyli szum; narzędzie nie zgłosiło
regresji. Wzorzec zapisany jako `2026-08-15-po-kroku-48-loop.json`. **Granica tej
liczby jest ta sama, co w kroku 45**: `--loop` nie woła taktu modułów, więc mówi
ona, że *reszta* taktu się nie zmieniła; o koszcie samego taktu modułu świadczy
to, że sprowadza się on do jednego `poll()`, który nigdy nie blokuje. Scenariusza
klatki krok nie dokłada — powody pominięcia (dwa wiersze) stoją
w [docs/pomiary/README.md](../../pomiary/README.md).

#### Czego krok nie dowiózł

- **Klatki pod XTermem nikt nie oglądał** — jak w kroku 46. Okno postępu, spis
  hostów i zawinięte pytanie o odcisk sprawdzono prymitywami w testach
  i przebiegiem funkcjonalnym, ale nie okiem w prawdziwym terminalu.
- **Prawdziwej sieci nie było** — próba szła po pętli zwrotnej, więc czasy są
  dolną granicą, a zachowanie przy hoście wolnym albo zrywającym połączenie
  w trakcie uścisku pozostaje niesprawdzone.
- **Sesja zerwana przez sieć pokazuje się jako żywa**, dopóki ktoś nie naciśnie
  `F5`. To jest cena rozstrzygnięcia D87 nr 9 (jedna praca naraz w porcie
  rdzenia), zapisana świadomie, a nie przeoczenie.

#### Rachunek końcowy

Testów **1847** (przybyło 25), PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag,
`make qa` zielone. **Żaden test nie otwiera połączenia sieciowego** — atrapy
`StubSshSession` i `StubHostBook`, klasy czyste (`KnownHostsReader`,
`FingerprintParser`, `SshFailureReader`) na wejściach podanych wprost, plik stanu
na katalogu tymczasowym. Wpisy zahaszowane w testach pochodzą z prawdziwego
`ssh-keygen -H`, a nie z ręcznego rachunku.
