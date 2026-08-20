# 2. Słownik domenowy — Miejsca: host zdalny, demon, klaster, książka i kosz

> Część rozdziału 2. Pojęcia i wstęp: [slownik.md](slownik.md).
> Spis rozdziałów: [docs/architecture.md](../../architecture.md).

Słownik tych modułów, które rozmawiają z czymś poza maszyną albo poza aplikacją — wraz z kosztem, jaki każdy z nich położył na rdzeniu.

## Sesja zdalna: praca poza maszyną (od kroku 48)

Moduł `src/Module/Ssh/` nawiązuje i utrzymuje połączenie SSH z hostem ze spisu,
który użytkownik prowadzi — **a od kroku 60 nie prowadzi go tutaj**: wpisy
mieszkają we wspólnej książce adresowej, a ten moduł czyta je kwerendą
`address-book.entries` z argumentem `ssh` i dokłada do nich to, czego książka
nie wie: z kim stoi sesja. Ekran (`Ctrl`+`S`) pokazuje spis i łączy; dopisanie,
zmiana i usunięcie wpisu należą do książki (`Ctrl`+`W`). Jest **czwartym
sprawdzianem kontraktu modułu** — po module rysującym główną funkcję (21), module bez ekranu
(36) i module pracującym, gdy go nie widać (45), przyszedł moduł **rozmawiający
z czymś poza maszyną**.

**Reguła nadrzędna, obowiązująca całą Fazę XVII: żadne wywołanie sieciowe nie
pada w rysowaniu klatki.** Jest to piąta reguła D46 rozciągnięta z zapisu na dysk
na sieć — i tutaj jest spełniona mocniej, niż brzmi: **żadne nie pada w procesie
aplikacji w ogóle**.

**Sesja żyje w procesie potomnym, a nie w procesie aplikacji** (D87 nr 1 i 2),
i to jest jawne odwrócenie D84 nr 2. Powód jest wymierny: `ext-ssh2` nie ma ani
jednego wywołania nieblokującego, a `ssh2_connect()` nie przyjmuje limitu czasu,
więc host nieosiągalny zamroziłby **całą aplikację** na `default_socket_timeout`,
czyli na minutę. Trzy warianty pośrednie — przyjęcie zamrożenia z ograniczeniem
od góry, strażnik na `pcntl_alarm()`, uścisk w potomku przy sesji w rodzicu —
zostały postawione i odrzucone. Zasób sesji nie przechodzi przez granicę procesu,
więc rozstrzygnięcie obejmuje także kroki 49 i 50.

**Trwanie daje `ControlMaster` klienta OpenSSH.** `ssh -M -N -f -o ControlPath=…
-o ControlPersist=yes` zestawia połączenie **raz** i demonizuje się samo, więc
aplikacja nie trzyma jego potoków ani przez chwilę; każda późniejsza operacja to
krótki potomek wchodzący przez gniazdo **bez uścisku dłoni** — milisekundy
zamiast setek. Stan sesji to `ssh -O check`, rozłączenie to `ssh -O exit`,
a gniazdo mieszka w `~/.light-manager/` pod nazwą będącą **skrótem z celu**, bo
gniazdo uniksowe ma twardy limit długości ścieżki.

Potomków uruchamia **rdzeniowy `BackgroundProcessPort`** (D87 nr 9) — moduł
sięga po port rdzenia, jak `FileInfo` po `du`. Cena przyjęta świadomie: port
prowadzi **jedną pracę naraz**, więc zestawianie sesji przerywa liczenie `du`
i odwrotnie. Z tego samego powodu **stan sesji odświeża się wyłącznie na żądanie
(`F5`)**, a nie w takcie: pytanie co kilka sekund znaczyłoby proces potomny co
kilka sekund, czyli zabijanie cudzej pracy w kółko. Sesja zerwana przez sieć bywa
przez to przez chwilę pokazana jako żywa i **jest to znana cena, nie usterka**.

**Weryfikacja klucza hosta: czyta moduł, pisze `ssh`** (D87 nr 5 i 6).
`KnownHostsReader` — klasa czysta poza jednym odczytem pliku — mówi **przed**
połączeniem, czy `~/.ssh/known_hosts` zna ten host; nazwy są tam zahaszowane, więc
dopasowanie to `hash_hmac('sha1', $nazwa, base64_decode($sól), true)`, w którym
**kluczem HMAC jest sól, a nazwa jest treścią** (odwrotnie, niż podpowiada
kolejność argumentów). Host nieznany idzie po odcisk potokiem
`ssh-keyscan | ssh-keygen -lf -`, po czym **zatrzymuje połączenie oknem groźnym**;
po zgodzie łączy się z `StrictHostKeyChecking=accept-new`, a wiersz dopisuje
**klient**, w postaci kanonicznej i zahaszowanej. Aplikacja nie dotyka tego pliku
do zapisu ani razu. Klucz niezgodny z zapamiętanym to nie pytanie, tylko odmowa —
i tej odmowy też nie piszemy sami.

Dwie rzeczy, o które łatwo się potknąć przy ruszaniu tego kodu. **Diagnostyka
klienta idzie na strumieniu błędów**, więc polecenia **sesji** kończą się na
`2>&1` — i wolno im, bo mistrz z `-N` na standardowym wyjściu nie pisze nic,
a cały ich wypis to kilkadziesiąt bajtów. **Poleceniu odczytu katalogu (krok 49)
wolno tego nie robić i nie wolno tego robić** — powód stoi niżej, przy zdalnym
katalogu, i jest zmierzony, nie teoretyczny. **Hasło nie może iść wejściem**: `ssh` czyta je
z terminala sterującego, a port tłowy potomkowi wejścia nie podaje — więc idzie
przez `SSH_ASKPASS` (`bin/ssh-askpass`) i zmienną środowiskową, nigdy przez
wiersz polecenia, który widzi w systemie każdy.

**Profil hosta pilnuje się sam i jest to warstwa bezpieczeństwa, nie porządek.**
Nazwa hosta, login i ścieżka klucza trafiają do wiersza polecenia uruchamianego
przez powłokę, więc mają wąskie wzorce — a osobno pilnowane jest to, czego
cytowanie upilnować nie może: **żadna wartość nie zaczyna się od `-`**, bo `ssh`
przeczytałby ją jako opcję.

Rdzeń urósł przez ten moduł o **trzy rzeczy, nie o jedną**, i wszystkie trzy są
rozstrzygnięciami użytkownika podjętymi z ceną wypisaną przed wyborem (D87):
pozycję w `Bootstrapie` (reguła 15), **tryb maskowany `TextInput`** (bo hasło
weszło do zakresu) i **zdolność `Application\Module\RequiresEnvironment`** — piąty
powód odrzucenia w rejestrze i pierwszy zależny od maszyny, na której aplikacja
stoi. Czwarta zmiana doszła w trakcie i wynikła z obejrzenia okna:
**`ConfirmOverlay` zawija długie pytanie zamiast je ucinać**, bo odcisk SHA256
ucięty w połowie nie jest odciskiem. Samo łamanie po słowach mieszka od poprawki
z 2026-08-16 w `Label::wrap()`, obok `fit()` i `fitEnd()` — wyszło z okna
z chwilą, gdy o tę samą regułę zapytał drugi wołający (zdanie stanu w ekranie
klastra); oknu została jego własna granica sześciu wierszy.

**Bez klienta OpenSSH moduł znika ze spisu wraz z powodem** — inaczej niż moduł
dźwięku, który bez rozszerzenia zostaje na pustym obiekcie. Różnica jest
zamierzona: cisza jest sensowną postacią muzyki, a spis hostów, z którymi nie da
się połączyć, nie jest sensowną postacią sesji zdalnej — obiecywałby.

## Zdalny katalog: lista przychodząca później (od kroku 49)

Ten sam moduł pokazuje **zawartość katalogu na połączonym hoście**, a ekran ma
odtąd **dwie postacie**: spis hostów przed połączeniem i zdalny katalog po nim,
z `F3` zaglądającym z powrotem do spisu (`F2` należy do rdzenia). Postać zmienia
**takt, a nie klawisz** — połączenie kończy się w procesie potomnym, więc chwili,
w której „jest sesja”, nie zna żaden klawisz; zna ją dopiero `poll()`.

**Jedno wywołanie na katalog, nie jedno na wpis.** Odczyt idzie poleceniem
`printf 'ls -lf "…"' | sftp -b - -o ControlPath=…`, które wchodzi przez gniazdo
stojącego mistrza. Wypis `sftp ls -l` niesie **nazwę razem z atrybutami**
(rodzaj, prawa, rozmiar, czas) i składa go **klient**, a nie serwer — pole liczby
dowiązań pokazuje `?`, bo protokół jej nie niesie, właściciel jest zawsze liczbą,
a nazwa miesiąca nie zależy od ustawień językowych. Postać wiersza nie zależy więc
od tego, co stoi po drugiej stronie; `ssh ls -l` zależałoby, bo zakłada powłokę
POSIX, której serwer SFTP mieć nie musi. Czas formatuje **potomek**, dlatego
polecenie narzuca mu `TZ=UTC` — inaczej daty zdalne rozjeżdżałyby się z lokalnymi
o różnicę stref.

**Praca kawałkowa została jednostopniowa** i to jest zmiana wobec planu kroku,
podyktowana pomiarem: koszt siedzi w **wywołaniu** (~0,93 s otwarcia kanału na
pętli zwrotnej), a nie we wpisie (pięć tysięcy wpisów to +0,1 s, a ich rozczytanie
w PHP — 3,2 ms). Plan przewidywał drugi stopień („atrybuty widocznego okna po
jednym obiegu na wpis”) i budżet kawałka mierzony zegarem; jedno i drugie
chroniłoby przed kosztem, którego nie ma. Z wzorca D46 zostaje to, co się liczy:
**praca jest daną oglądaną co klatkę, nie procesem**.

**Strumieni tego polecenia nie wolno scalać** (reguła 15f w `SKILL.md`) i jest to
najdroższa lekcja tego kroku. `2>&1` przenosiło na wyjście `sftp` tryb
nieblokujący, który mistrz połączenia nakłada deskryptorom przekazanym mu przez
klienta multipleksera — a wtedy zapis do zapełnionego potoku zwracał `EAGAIN`,
OpenSSH porzucał porcję wypisu i kończył się **kodem zero**. Z 419 KB listy
dochodziło 130 KB, bez śladu w kodzie wyjścia. Stąd czwarta zmiana rdzenia kroku:
`BackgroundState` niesie odtąd **strumień błędów osobnym polem**, a zasada z kroku
26 („strumieni się nie skleja”) zostaje w mocy — pola są rozdzielone właśnie po to.

**Moduł ma własną domenę plikową i jest to świadome powtórzenie** (`RemotePath`,
`RemoteEntry`, `RemoteEntryType`, `RemoteNameFilter`, `RemoteEntryComparator`):
reguła 15 zabrania sięgania do przeglądarki, a wyniesienie ścieżki do rdzenia
byłoby odwróceniem D42. Granica tego powtarzania — pojęcia wolno, mechanizmy nie,
a trzeci taki moduł uruchamia przegląd — stoi w `SKILL.md` jako reguła 15e.
Ścieżka zdalna porządkuje się **tekstowo**, bo systemu plików po drugiej stronie
nie ma o co zapytać bez obiegu; dowiązanie w środku ścieżki zostaje przez to
nierozwinięte, a rozwinięcie należy do serwera (`pwd` przy katalogu startowym).

**Kontekst sesji mówi odtąd, czyja jest ścieżka** (`ContextOrigin`, piąta zmiana
rdzenia). Bez tego ekran zdalny publikujący `/var/log` kazałby modułowi opisu
pliku pokazać **lokalny** `/var/log` — kłamstwo ciche, bo obie ścieżki istnieją
i obie się czytają. Odbiorca wszedł razem z mechanizmem (reguła 13): moduł opisu
pliku rozpoznaje wpis zdalny i opisuje go **wyłącznie z kontekstu**, nie dotykając
ani dysku, ani sieci. Kontekst niesie po to trzy atrybuty zaznaczenia (rozmiar,
czas, prawa), a suma kontrolna i zajętość odmawiają pracy zdaniem, które mówi
dlaczego.

## Przesył plików: pobranie i wysłanie (od kroku 50)

Ten sam moduł **przenosi pliki w obie strony**: `F5` pobiera wpis zdalny do
katalogu, w którym stoi przeglądarka, `F6` wysyła wpis lokalny do katalogu
otwartego w panelu. Odświeżanie listy przeprowadziło się przy tym z `F5` na
`Ctrl`+`R` — układ jest odtąd ten sam, co w przeglądarce (`F5` kopiuj, `F6`
przenieś) i w menadżerach dwupanelowych. Obie czynności mają komendy (`ssh.get`,
`ssh.put`) i pozycje w menu `F9`, a mieszkają w **jednym** miejscu
(`RemoteTransfer`, reguła 11n).

**Treść ląduje pod nazwą roboczą, a zatwierdza ją zmiana nazwy** — i to jest
całe zabezpieczenie przed połówką pliku. Lokalnie zatwierdza `FileOperationsPort`
rdzenia, zdalnie — `rename -l` w tym samym wsadzie `sftp`. Myślnik w `rename -l`
nie jest ozdobą: zwykłe `rename` idzie rozszerzeniem `posix-rename@openssh.com`
i **nadpisuje cicho** (sprawdzone: kod zero na zajętej nazwie), a nadpisanie ma
być skutkiem odpowiedzi użytkownika, nie właściwością protokołu. Cel zwalnia się
przez to **jawnie** (`-rm`) i tylko po zgodzie.

**Wyjątek 15b zostaje przy jednym nazwanym przypadku**, choć krok pisze po dysku:
plik pisze `sftp`, czyli potomek uruchomiony rdzeniowym `BackgroundProcessPort`,
a jedyne zapisy z PHP — zmiana nazwy i skasowanie połówki — idą przez port
rdzenia z kroku 41. Zastrzeżenie startowe planu („rdzeń dostaje port zapisu
strumienia”) okazało się bezprzedmiotowe wraz z drogą techniczną fazy (D89 nr 1).

**Postęp czyta `stat`, a nie klienta.** `sftp` pokazuje pasek postępu wyłącznie
wtedy, gdy jego wyjście jest terminalem sterującym (`progressmeter.c` porównuje
`getpgrp()` z `tcgetpgrp()`), więc na potoku milczy — nawet po poleceniu
`progress` w wsadzie. Pobieranie liczy się przez to **rosnącym plikiem roboczym**
(odczyt lokalny, darmowy, co klatkę), a wysyłanie zna wyłącznie granice plików:
w środku jednego pasek wchodzi w tryb „postęp nieznany”. Asymetria jest
własnością drogi i jest widoczna dla użytkownika. Ten sam odczyt pełni straż nad
zerwanym łączem: plik, który nie rośnie przez 30 s, kończy pracę zdaniem „łącze
milczy”, a limit twardy (godzina) jest wyłącznie sufitem awarii.

**Jeden potomek na plik** (D89 nr 3). Wsad `sftp` przerywa się na pierwszym
błędzie, więc jedno wywołanie na całą pracę znaczyłoby, że niepowodzenie jednego
pliku ubija resztę, a o kolizje trzeba by pytać w komplecie przed startem. Cena
jest zmierzona i nazwana: otwarcie kanału kosztuje tyle, co cały odczyt katalogu.

**Kolizję rozstrzyga strona, która wie za darmo**: przy pobieraniu dysk
(`file_exists`), przy wysyłaniu **lista, którą panel ma na ekranie**. Katalog
zdalny inny niż otwarty oddaje „nie wiem”, a nie „nic tam nie ma” — i wtedy przed
cichym nadpisaniem broni `rename -l`. Odpowiedzi są rdzeniowe (`TransferChoice`),
bo słownik „nadpisz / pomiń / zmień nazwę / przerwij (i wszystkie)” jest
mechanizmem, a mechanizmów rdzenia moduł nie powtarza (15e).

**Druga strona przesyłu bierze się z kontekstu sesji.** Ekran zdalny publikuje
własny kontekst (`Remote`), więc lokalnej ścieżki w chwili przesyłu nie ma czego
zapytać — `LocalPlace` zatrzaskuje ostatni kontekst z pochodzeniem `Local`,
podany ekranowi przez `ReadsContext` przed rysowaniem. Moduł nie sięga przez to
do przeglądarki ani razu (reguła 15).

**Przesyłane są pliki, nie katalogi**, a przesył wyłącznie kopiuje: wariantu
przenoszącego nie ma, praw i czasu zmiany nie przenosi (`sftp -p` poza zakresem),
a wznawianie przerwanej pracy zostaje osobną rzeczą do zaprojektowania.

## Docker: kilka rozmów naraz (od kroku 51)

Moduł `src/Module/Docker/` (`Ctrl`+`O`) pokazuje kontenery i obrazy tej maszyny,
puszcza logi na żywo, buduje obrazy i podnosi projekty compose. Jest **piątym
sprawdzianem kontraktu modułu** — po module rysującym główną funkcję (21),
module bez ekranu (36), module pracującym, gdy go nie widać (45), i module
rozmawiającym z cudzą maszyną (48) przyszedł moduł **prowadzący kilka rozmów
naraz**: dwie listy, strumień logów, budowa i praca compose potrafią trwać w tej
samej chwili.

**Drogi są dwie i to nie z wygody.** Docker idzie **gniazdem unixowym**
(`/var/run/docker.sock`, `ext-curl` z `CURLOPT_UNIX_SOCKET_PATH`, rodzina
`curl_multi_*` w trybie nieblokującym), a compose — **procesem potomnym**, bo
demon nie wystawia dla wtyczki ani jednego zasobu w API. Rozmowy pompuje takt
modułu (`NeedsTick`), nigdy rysowanie klatki; `curl_multi_select()` nie pada ani
razu, bo pytanie o gotowość deskryptorów kosztuje tyle samo, co samo posunięcie
transferu.

**Dwa formaty strumieniowe są pułapkami dającymi „działa, ale wygląda na
zepsute”** i oba rozbiera moduł, nie komponent (reguła 11i):

- **logi kontenera bez TTY są multipleksowane** — osiem bajtów przed każdą porcją
  (numer strumienia, trzy wypełniające, cztery długości w kolejności sieciowej).
  Czytane jak tekst dają śmieci co kilka wierszy. Czy strumień jest ramkowany,
  rozstrzyga **treść, a nie pytanie do demona**, i rozstrzyga się to dopiero
  z ósmym bajtem: porcja krótsza od nagłówka wygląda jak zwykły tekst, a odpowiedź
  raz udzielona obowiązuje do końca strumienia;
- **budowa oddaje postęp strumieniem obiektów JSON**, po jednym na wiersz, i to
  w nim — a nie w kodzie odpowiedzi — przychodzi **niepowodzenie**: nieudana
  budowa kończy się kodem HTTP 200, bo z punktu widzenia protokołu wszystko
  poszło dobrze.

**Kontekst budowy pakuje `PharData` pracą kawałkową** (D46), z pominięciem tego,
co wyklucza `.dockerignore` — czytany w podzbiorze składni, którego różnica wobec
pełnej semantyki Dockera objawia się **rozmiarem kontekstu, a nie wynikiem
budowy**. Bez tego pierwszy lepszy projekt Node.js wysłałby demonowi
`node_modules`.

**Moduł odmawia startu wyłącznie bez `ext-curl`** (`RequiresEnvironment`; od
kroku 58). Do tamtego kroku odrzucał go także brak gniazda lokalnego — a przy
środowisku zdalnym byłaby to odmowa bez powodu: maszyna bez demona lokalnego
jest dokładnie tą, na której zdalne środowisko ma sens. Brak gniazda jest odtąd
**stanem wpisu środowiska**, mówionym zdaniem w treści ekranu; precedens z kroku
51 („leżący demon nie odrzuca modułu") objął demona nieobecnego.

**Listy odświeżają się z zegara co pięć sekund, ale wyłącznie przy widocznym
ekranie**; zawężenie do projektu compose nie kosztuje ani jednego pytania więcej,
bo kontener zna swój projekt z etykiety `com.docker.compose.project`
przychodzącej razem z listą.

### Środowiska: jeden demon przestaje być założeniem (od kroku 58)

**Z którym demonem moduł rozmawia, jest daną wpisu, a nie stałą usługi.** Wpis
środowiska (`DockerEnvironment`) ma nazwę własną i jeden z trzech rodzajów:
**gniazdo lokalne**, **tunel SSH** (`ssh -L` przywozi gniazdo zdalnego demona)
albo **TCP z TLS-em klienta** (`https://host:2376`, trzy ścieżki plików). Zyskiem
wspólnym obu dróg zdalnych jest to, że **kod rozmowy z demonem zostaje jeden**
(D96 nr 2): ramkowanie logów, strumień budowy i `X-Registry-Auth` nie zmieniają
się o linię — usługa gniazda dostaje z wybranego wpisu gotowy `DockerEndpoint`
i nie wie, skąd się wziął. Odrzucone zostało `docker -H ssh://` przez klienta,
i to twardo: listy zdalne szłyby wtedy inną drogą niż lokalne, czyli powstałaby
druga droga do tej samej danej.

**Spis ma dwa źródła** (D96 nr 3): konteksty klienta `docker` czyta się pracą
tłową (`docker context ls --format json`, NDJSON), a wpisy własne dochodzą
**od kroku 60 z rozdziału `docker` wspólnej książki adresowej** (do tamtego
kroku — z własnej książki modułu w sekcji `docker`, gdzie został wyłącznie
wskaźnik bieżącego środowiska). Cel tunelu jest tam polem rodzaju `entry`,
czyli **odniesieniem do wpisu**, a nie jego nazwą — dzięki temu zmiana nazwy
hosta przestała psuć tunel po cichu.
Trzy reguły scalania: **pochodzenie jest widoczne**, przy zbieżnej nazwie
**wygrywa wpis własny** (kolizja zostaje w spisie jako wiersz przysłonięty),
a **brak klienta nie jest awarią** — lista schodzi do wpisów własnych plus
gniazda lokalnego. Do cudzych plików moduł **nie pisze**: wpisu czytanego od
klienta nie da się z aplikacji ani zmienić, ani skasować.

**Tunel jest pracą, która przeżywa swój uchwyt** (wzorzec `ssh -M -N -f`
z kroku 48): mistrz demonizuje się sam, uchwyt pracy tłowej gaśnie, a na dysku
zostają dwa pliki — gniazdo przywiezione i gniazdo mistrza, którym tunel się
potem zamyka (`-O exit`). Gniazdo leży w `XDG_RUNTIME_DIR` (w jego braku —
w `~/.light-manager`, D102 nr 1), nazwa zawiera nazwę wpisu, a sprzątanie idzie
dwiema drogami (D47) **plus skasowanie pliku gniazda**, bo `ssh` zostawia je po
sobie — a gniazdo po nieżyjącym tunelu wisi przy `connect()`.
`ExitOnForwardFailure=yes` jest warunkiem prawdomówności stanu: bez niego
„tunel stoi" znaczyłoby tylko „uwierzytelniłem się". Stan tunelu ma **cztery
postacie** (nie ma / wstaje / stoi / nie wstał z powodem) i jest widoczny
w górnym pasie — inaczej „demon nie odpowiada" i „tunel nie wstał" wyglądałyby
identycznie, a wymagają dwóch różnych czynności. Tunel wstaje **na wybór
środowiska**, nigdy przy starcie aplikacji (start nie ma prawa kosztować
procesu potomnego), a cel bierze się z **rozdziału `ssh` książki adresowej**
(`address-book.entries`), gdy wpis wskazuje host po nazwie — trzy napisy, ani
jednego typu (reguła 15g). Do kroku 60 szło to kwerendą `ssh.hosts` cudzego
modułu; ta zniknęła razem z książką hostów, a nowa droga jest zarazem
pierwszym dowodem zasady kroku 60: **rozdział nie jest przegrodą**, więc moduł
Dockera czyta cudzy rozdział tą samą kwerendą, co jego właściciel. Uwierzytelnienie
ma **dwie drogi do wyboru przy połączeniu** (D102 nr 4): klucz albo agent
(`BatchMode=yes`, odpowiedź domyślna) i hasło — polem maskowanym, przez
`SSH_ASKPASS` wzorcem hasłowej drogi modułu Ssh z kroku 48: nigdy wierszem
polecenia, bez zapisywania gdziekolwiek.

**Compose dostaje środowisko przedrostkiem wiersza polecenia**
(`DOCKER_HOST=…`, dla TCP także `DOCKER_TLS_VERIFY=1` i `DOCKER_CERT_PATH=…`),
bo port pracy tłowej bierze gotowy napis i nie ma powodu, żeby przestał.
Pułapka środowiska zdalnego jest **nazwana w napisach i pada przed
podniesieniem**: plik compose czyta klient po stronie lokalnej, ale montowania
`volumes:` wskazują ścieżki po stronie demona, a kontekst budowy jedzie przez
sieć — `docker.up` pyta o to oknem, zanim cokolwiek ruszy.

**Przełączenie środowiska unieważnia obie listy, logi i budowę** — pytania
w locie są przerywane, bo odpowiedź zamówiona przed przełączeniem przyszłaby od
poprzedniego demona. Kwerendy `docker.containers`, `docker.images`
i `docker.compose` niosą przez to **nazwę środowiska w każdym wierszu** (inaczej
odpowiedź dwóch demonów wyglądałaby dla obcego identycznie), a nowa
`docker.environments` oddaje spis wraz z wyborem i stanem tunelu — **bez celu
SSH i bez ścieżek kluczy TLS** (reguła 11w).

### Rejestry obrazów: drugi rozmówca HTTP i pierwszy w internecie (od kroku 61)

**Rejestrów jest wiele i mieszkają w książce adresowej, nie w ustawieniach.**
Do kroku 61 rejestr był w aplikacji **jeden**, opisany trzema pozycjami zakładki
(krok 54); odtąd moduł Dockera deklaruje w książce **drugi rozdział** —
`registry` obok `docker` — a trzy stare pozycje przeniosły się do niego raz,
komendami książki. Dwa rozdziały, a nie jeden, bo **pola są rozłączne**: demon
opisuje się gniazdem, celem tunelu i materiałem TLS, rejestr adresem,
użytkownikiem i tokenem. Wpis książki zostaje przy tym **jeden**, więc maszyna
ma prawo być naraz demonem i rejestrem albo tylko jednym z nich; wzorzec książki
nie staje po raz czwarty (D104).

**Rozmowa z rejestrem to ta sama maszyneria i inny rozmówca.** `curl_multi_*`
w trybie nieblokującym, pompowane taktem modułu — bo reguła nadrzędna Fazy XVII
(**żadne wywołanie sieciowe nie pada w rysowaniu klatki**) obowiązuje tu mocniej
niż przy demonie: demon stoi na gnieździe, rejestr w internecie, więc pytanie
trwa wielokrotność budżetu klatki.

**Rejestr uwierzytelnia dwustopniowo, więc jedna rozmowa to nawet trzy
żądania.** `GET /v2/…` bez tokenu oddaje `401` z nagłówkiem `WWW-Authenticate:
Bearer realm=…,service=…,scope=…`; pytanie pod `realm` — z podstawowym
uwierzytelnieniem — oddaje token; dopiero trzecie wywołanie, podpisane
`Authorization: Bearer`, przynosi odpowiedź. Wszystkie trzy są nieblokujące,
więc jest to **maszyna stanu** (`RegistryConversation`), a nie ciąg wywołań:
kolejny obieg zaczyna się w `pump()`. Trzy rzeczy warte zapamiętania. Rozmowa
zbiera **nagłówki**, nie tylko treść (`CURLOPT_HEADERFUNCTION`) — bez tego drugi
obieg nie ma dokąd pytać. Rozbiór wyzwania czyta się **wyrażeniem, nie podziałem
po przecinkach**, bo `scope` bywa listą (`repository:a:pull,push`) i podział
rozerwałby go w pół, oddając token na węższe uprawnienie, niż poproszono.
A `401` **po** tokenie znaczy **złe poświadczenia** i kończy rozmowę — obiegu
czwartego nie ma, bo ponawianie byłoby pytaniem o to samo trzydzieści razy na
sekundę.

**Katalog rejestru jest rozszerzeniem opcjonalnym i widok ma dwa zachowania.**
`/v2/_catalog` należy do API v2 Dockera, ale specyfikacja OCI go **nie
wymaga**. Piąta postać ekranu modułu (klawisz `r`) pokazuje więc spis
repozytoriów tam, gdzie katalog jest, a gdzie go nie ma — przechodzi w tryb
„podaj nazwę obrazu, pokażę etykiety". `404` na katalogu **nie jest awarią**
i cały panel jest tak napisany, żeby rejestr bez katalogu nie wyglądał na
zepsuty. Sprawdzone na `registry:2` (cel `make registry-start`): katalog jest
i odpowiada w **pierwszym** obiegu, bo ten rejestr nie uwierzytelnia; o rejestrach
publicznych krok niczego nie twierdzi, bo wyjścia w internet nie było.

**Pytanie pada na żądanie, nigdy przy wejściu w widok** (`Ctrl`+`R`) — katalog
cudzego serwera to ruch, którego nikt nie zamówił; ta sama reguła, którą krok 48
zapisał dla odświeżania sesji. Posuwa je **takt modułu, nie widok**, więc
odpowiedź dojdzie także wtedy, gdy użytkownik przełączy postać ekranu — lekcja,
którą krok 54 zapłacił za budowę posuwaną własnym oknem.

**Sekret rejestru w klastrze: poświadczenie idzie kwerendą, plikiem i nigdy
wierszem polecenia.** `k8s.deploy-image` ma od kroku 61 drugi wariant: obraz
z rejestru prywatnego. Moduł Dockera oddaje **gotową treść `.dockerconfigjson`**
osobną kwerendą (`docker.registry-secret`, `VOLATILE`, pytanie o **jeden** wpis),
bo format jest pojęciem Dockera; moduł k8s zapisuje ją do pliku o prawach
**`0600`** w `XDG_RUNTIME_DIR`, stosuje `kubectl apply -f <plik>` i **kasuje
plik — także po niepowodzeniu**. Pliku nie da się pominąć: `kubectl` nie
przyjmuje wejścia, więc `apply -f -` jest niewykonalne (reguła 11v).

Zdanie graniczne, które przy tym **upadło i zostało zastąpione**: plan kroku
chciał, żeby token nie przechodził przez rejestr kwerend. Przesłanka tego
żądania przestała być prawdą w kroku 60 — `address-book.value` oddaje pole
rodzaju `secret` **każdemu, kto zapyta**, i mówi to we własnej dokumentacji.
Przegrody nie ma, więc broni się nie tajemnicy, tylko **przypadkowego wycieku do
cudzej tabeli**: kwerenda poświadczenia jest osobna od spisu, spis nie niesie
tokenu ani razu, a do wiersza polecenia nie trafia nic — i ten ostatni zakaz
jest twardy, bo `ps` widzi wiersz polecenia (krok 48).

**Sekret dopina się łatą strategiczną, nie scalającą, i jest to wynik pomiaru.**
Sprawdzone na żywym klastrze na wdrożeniu, które **już miało** sekret: łata
domyślna dała `[nowy, istniejący]` — dopisała; powtórzona **nie zdublowała**
wpisu (klucz scalania po nazwie), więc kod nie sprawdza, czy sekret już jest;
`--type=merge` dał **`[nowy]`**, czyli skasował istniejący. Ostatnie jest tą samą
pułapką, którą krok 54 zapłacił przy kontenerach, a **lekcja jest ogólniejsza,
niż ją wtedy zapisano**: `--type=merge` podmienia **każdą** tablicę, nie tylko
tablicę kontenerów — rodzaj łaty dobiera się do **pola**, nie do zasobu. Sekret
powstaje przy tym **przed** podmianą obrazu, bo wdrożenie przestawione na obraz
bez poświadczenia kończy się `ImagePullBackOff`.

**Łańcuch sekretu ma własny tor czynności**, osobny od tego, którym posługuje
się ekran, i jest to warunek działania, a nie porządek: `ClusterActions` prowadzi
**jedną czynność naraz** (a wdrożenie zamawia sekret i podmianę obrazu w tej
samej chwili), a skutek zabiera się **raz** — więc wspólny tor znaczyłby, że
jeden z dwóch odbiorców nigdy niczego nie zobaczy, i to cicho. Reguła ogólna:
**kanał „zabierz raz" ma jednego odbiorcę; nowy odbiorca to nowy kanał, nie
podział istniejącego.**

## Kubernetes: moduł, który nie wie z góry, co pokaże (od kroku 52)

Moduł `src/Module/Kubernetes/` (`Ctrl`+`K`) pokazuje zasoby klastra wskazanego
przez `kubeconfig`, opisuje wybrany zasób, puszcza logi poda na żywo, stosuje
manifesty i pozwala zmienić wartość w sekrecie. Jest **szóstym sprawdzianem
kontraktu modułu** i różni się od pięciu poprzednich jedną rzeczą: **nie zna
z góry swojej treści**. Rodzaje zasobów przychodzą z klastra, więc drzewo jednego
wygląda inaczej niż drugiego, a operator zainstalowany w międzyczasie zmienia je
bez ani jednej linii dopisanej do aplikacji (D91 nr 2).

**Wszystko idzie procesem potomnym** — `kubectl` uruchamiany rdzeniowym portem
pracy tłowej — więc reguła nadrzędna z kroku 48 jest spełniona w najmocniejszej
postaci: żadne wywołanie sieciowe nie pada w rysowaniu klatki, bo żadne nie pada
w procesie aplikacji. **Limit czasu jest częścią każdego wywołania i jest
podwójny**: `--request-timeout` (klient przestaje czekać na serwer) plus limit
procesu (rdzeń ubija potomka, który zawiesił się przed wysłaniem żądania).
Wyjątek jest jeden i nazwany: **strumień logów nie dostaje limitu żądania**, bo
ten zamknąłby go w chwili, gdy zaczyna działać.

**Ekran to `Split`: drzewo grup API i rodzajów po lewej, treść po prawej**
(D91 nr 3) — dla rodzaju jego lista, dla zasobu opis w zwijanych sekcjach, a `y`
przełącza na surowy YAML. Trzy poziomy drzewa zamiast dwóch, bo rodzajów bywa
kilkadziesiąt; grupa mieści się w panelu, płaska lista sześćdziesięciu pozycji
nie. **Rozwinięcie gałęzi jest jedynym momentem, w którym pytamy o listę** —
każde takie pytanie to proces potomny, więc gałąź rozwinięta i nieoglądana
zostaje taka, jaka była.

**Kolumny liczy moduł, nie serwer** (D91 nr 4) i jest to cena wybrana świadomie:
`-o json` plus ręcznie napisane pakiety kolumn dla kilkunastu popularnych
rodzajów, a rodzaj spoza spisu — w tym każdy CRD — pokazuje nazwę, wiek i nic
więcej. Odrzucona droga (rozczytywanie tabeli drukowanej przez serwer) dawała
prawdziwe kolumny każdemu rodzajowi za darmo, ale kupowała je parserem tekstu
wyrównanego spacjami.

**Jedno wywołanie mimo to oddaje tekst**: `kubectl api-resources` nie umie JSON-a
w kliencie 1.25 (`-o` przyjmuje `wide` i `name`). Wiersz rozbiera się wyrażeniem
opartym na niezmiennikach — czasowniki w nawiasach kwadratowych, przed nimi stała
kolejność pól — **nigdy podziałem po spacjach**: kolumna `SHORTNAMES` bywa pusta,
a wtedy podział przesuwa wszystkie pozostałe i `namespaced` czyta się
z `APIVERSION`, czyli połowa katalogu dostaje odwróconą odpowiedź na pytanie „czy
ten zasób mieszka w przestrzeni nazw”.

**Sekrety są zamaskowane** w liście, w opisie i w widoku YAML; `x` odsłania
**jeden** klucz, a `e` otwiera zmianę — wartość tekstem albo zapisem base64,
dodanie klucza, skasowanie klucza (D91 nr 10). Wszystkie trzy idą jednym
poleceniem `kubectl patch --type=merge -p '<json>'`, bo scalająca zmiana kasuje
klucz o wartości `null` i zostawia nietknięte te, których nie wymieniono. Fragment
idzie **argumentem**, nigdy wejściem standardowym — ta sama reguła unieważnia
`kubectl apply -f -`, więc manifest podaje się ścieżką. Powód maskowania jest
wymierny: `core.dump` z kroku 38 zapisuje klatkę na dysk.

**Stan „nie ma klastra” jest stanem zwykłym, nie awarią**, i rozpada się na
**pięć** (trzy do kroku 59): brak wybranego klastra (spis czyta się z plików,
więc pada także bez sieci — ekran mówi, co wybrać), **nie ma pliku**, **nie ma
w pliku takiego kontekstu**, klaster nieosiągalny (powód pochodzi ze strumienia
błędów klienta, nie z domysłu) i klaster gotowy. **Wersje klienta i serwera są
widoczne, a różnica większa niż jedno wydanie jest ostrzeżeniem, nie odmową** —
Kubernetes nazywa ją niewspieraną, a nie niemożliwą.

**Miejsce jest parą — plik `kubeconfig` i kontekst — a jego tożsamością jest
nazwa wpisu książki** (krok 59, D96 nr 4). Do kroku 59 współrzędna była jedna,
flaga `--kubeconfig` nie padała nigdzie, a klucze czterech klas stanu brały się
z nazwy kontekstu — więc `default` w dwóch plikach był dla aplikacji **jednym
miejscem** i mieszał drzewo, listy oraz otwarty opis po cichu. Odtąd
`ClusterPlace` jedzie w każdym wywołaniu dwiema flagami (także w `config view`,
bo to ono ma wypisać zawartość *wskazanego* pliku), a `ClusterSession::key()`
jest kluczem `TreeState`, `SectionState`, `ScrollWindow` i `ResourceCache`.
Klastry prowadzi się **rozdziałem `k8s` wspólnej książki adresowej** (od kroku
60; do tamtego kroku — własną książką modułu), a ogląda klawiszem `c`, w piątej
postaci ekranu, obok kontekstów czytanych z `~/.kube/config` i ze ścieżek
w `KUBECONFIG` — z trzema
regułami dwóch źródeł znanymi ze środowisk Dockera: pochodzenie widoczne, wpis
własny wygrywa przy zbieżnej nazwie, wpisu czytanego nie da się z aplikacji
skasować, bo **do `kubeconfig` moduł nie pisze** i to zdanie z kroku 52 zostaje
w mocy. Konteksty tej samej nazwy z dwóch plików dostają przez to **różne nazwy
wierszy**; brak pliku rozstrzyga się `is_file()` **przed** procesem potomnym, bo
`kubectl` oddaje wtedy pustą konfigurację z kodem zero.

Rdzeń kosztuje **jedną linię w `Bootstrapie` plus rozbudowę portu pracy tłowej**
o wypis pracy trwającej ([praca poza klatką](praca-poza-klatka.md)) — plan kroku zakładał, że
mechanizmu rdzenia nie ruszy żadnego, i to założenie zostało odwołane
rozstrzygnięciem użytkownika (D91 nr 12). **Krok 59 dołożył do tego rachunku
dwie rzeczy** i obie są wynikiem przeglądu reguły 15e, nie potrzebą modułu:
wspólną książkę wpisów i wspólny dokument stanu (niżej).

## Stan aplikacji: jeden dokument, sekcja na właściciela (od kroku 59)

Przegląd z reguły 15e — obowiązkowy przy trzecim powtórzeniu wzorca — wypadł
w kroku 59 **na przeprowadzkę**, i to szerszą, niż rekomendował plan (D103).
Wzorzec książki wpisów stał wtedy po raz trzeci (`HostBook`, `EnvironmentBook`,
`ClusterBook`), a mechanizm zapisu pliku stanu — po raz **piąty**: trzy usługi
modułów plus konfiguracja i historia komend, skopiowany niemal co do znaku.

Do rdzenia wyszło jedno i drugie. **Pojęcie**: `Application\State\Book` —
kolejność dopisywania i tożsamość po nazwie własnej, z ładunkiem
**nieprzezroczystym**, bo pola trzech książek są rozłączne, a D42 („rdzeń nie
wie, czym jest wpis”) zostaje w mocy; moduł nadal trzyma własny typ wpisu.
**Mechanizm**: `Application\Port\StateDocumentPort` z usługą
`Infrastructure\Config\StateDocumentService` — jeden plik
`~/.light-manager/state.json` z **sekcją na właściciela** — oraz
`Infrastructure\Config\StateFile`, jedyna droga zapisu pliku stanu (`0600`,
plik tymczasowy i `rename()`), z której korzystają także `settings.json`
i historia komend.

Trzy zdania graniczne: **właściciel sięga wyłącznie po sekcję o własnym
identyfikatorze** (po cudzą daną drogą jest rejestr kwerend), **cudze sekcje
i nieznane klucze przeżywają zapis**, a **migracja mieszka za portem** — sekcja
nieobecna w dokumencie czyta się ze starego `<sekcja>.json`, stary plik zostaje
na dysku nietknięty (nikt go już nie czyta, a skasowanie nie ma odbiorcy),
a sekcją dokumentu staje się przy pierwszym zapisie któregokolwiek właściciela.

## Książka adresowa: wspólny rejestr wpisów i pól (od kroku 60)

Moduł `src/Module/AddressBook/` (`Ctrl`+`W`) trzyma **wpisy** — miejsca, do
których łączą się pozostałe moduły — i **deklaracje pól** przy nich. Jest
siódmym sprawdzianem kontraktu modułu i **pierwszym modułem istniejącym po to,
żeby trzymać cudze dane**; poprzednie sześć powstało dla własnych funkcji.

**Zdanie, na którym stoi całość: rozdział nie jest niczyją własnością.**
Rozdział to **nazwana grupa pól**, a nie przegroda. Jeden zestaw kwerend
i komend obsługuje **wszystkie** rozdziały — rozdział jest w nich argumentem,
nie osobnym wejściem — a książka używa tego zestawu **tak samo, jak moduły**:
jej własny ekran pyta i zmienia przez rejestry rdzenia, nie przez model, i sama
deklaruje swój rozdział (`general`) tymi samymi komendami. Nie ma tu wyjątku
dla właściciela, bo nie ma właściciela.

**Wpis niesie dwie rzeczy własne: identyfikator i nazwę.** Identyfikator jest
losowy (dwanaście znaków szesnastkowych) i jest **tożsamością**; nazwę wolno
zmienić, powtórzyć i zostawić pustą. Adresu wśród pól własnych **nie ma** —
adres jest polem rozdziału jak każde inne, bo książka nie wie, co to adres.
Odwraca to wzorzec trzech książek, które ten moduł zastąpił (`HostBook`,
`EnvironmentBook`, `ClusterBook`): tam tożsamością była nazwa, więc jej zmiana
psuła cudze odniesienia po cichu.

**Pola dokłada się deklaracją, a deklaracja jest jednostronna.** Kto zamierza
z pól korzystać, woła dwie komendy — `address-book.chapter` i po jednym
`address-book.field` na pole — i **książka po nic nie wraca**. Deklaracja jest
**zapowiedzią użycia, nie zastrzeżeniem**: wolno ją powtórzyć, wolno ją złożyć
dwóm modułom naraz, a sprzeczna (ten sam klucz, inny rodzaj) **nie przestawia
pola, które już stoi**. Deklaracje **nie są zapisywane na dysk** — powstają
w takcie przy każdym uruchomieniu, więc rozdział, którego dziś nikt nie
deklaruje, traci opis pól, a jego **wartości we wpisach stoją nietknięte**,
czytelne i zmienialne. Sprząta je czynność zamawiana wprost
(`address-book.forget`), nie automat.

Rodzajów pól jest sześć — `text`, `number`, `flag`, `choice`, `secret`
i `entry`. Rodzaj spoza spisu **pomija się w ciszy**: moduł nowszy od książki
nie ma prawa jej zepsuć. `secret` znaczy **zasłonę na ekranie i nieobecność
w domyślnych wierszach** (stoi tam `set`/`unset`), a nie przegrodę: wartość
oddaje kwerenda przeznaczona do tego wprost i **wolno to każdemu**, bo rejestr
kwerend nie zna wołającego. Plik stanu ma prawa `0600` i **nie udaje sejfu**.
`entry` jest odniesieniem do innego wpisu i ma dziś jednego odbiorcę — cel
tunelu SSH w rozdziale `docker`; to dla niego identyfikator w ogóle rozwiązuje
jakiś dzisiejszy problem.

**Trzy rozdziały weszły razem z mechanizmem** (`ssh`, `docker`, `k8s`), a wraz
z nimi zniknęły trzy książki modułów i kwerenda `ssh.hosts`. Każdy z modułów
zna książkę **wyłącznie z nazw komend i kwerend** (15g) — pilnuje tego
`NoModuleKnowsAnotherModuleTest` — i każdy sięga po nią z dwóch miejsc: klasy
`…Chapter` (deklaracja i migracja) oraz własnej fasady odczytu.

Dwa zdania graniczne dla dopisujących czwarty rozdział. **Warstwa `Application`
nie widzi książki**: koordynator dostaje wpisy podaną listą raz na takt
(`useEntries()`) i **zamawia** zapisy, które wykonuje `Presentation` komendą —
bo komendy są tam, a nie tu. **Sekcja modułu w dokumencie stanu trzyma odtąd
wyłącznie wskaźniki i pamięć podręczną**: który wpis jest bieżący, co moduł
zapamiętał o sesji, znacznik przeniesienia. Zapamiętany katalog zdalny został
przez to u modułu sesji zdalnej i **nie jest polem rozdziału** — zapisuje się
przy każdej zmianie katalogu, więc w książce znaczyłby zapis wspólnego dokumentu
i zdarzenie kilka razy na sekundę.

**Migrację robi właściciel sekcji, nie książka.** Książka cudzych sekcji
dokumentu stanu nie czyta; stary spis przenosi ten, kto go tam zostawił —
czyta własny klucz, dopisuje wpisy komendami i pyta `address-book.last`
o identyfikator każdego z nich. Stare klucze **zostają na dysku nietknięte**
(D103), a o tym, że przeniesienie się odbyło, mówi osobny znacznik.

## Kosz i cofnięcie ostatniej operacji (od kroku 44)

Usunięcie przestało być końcem: klawisz domyślny (`F8`, `Delete`) robi to, co
mówi pozycja modułu „usuwaj do kosza” (domyślnie: kosz), a `Shift`+`F8`
i `Shift`+`Delete` — **zawsze to drugie** (D81, nr 1–2). Ustawienie przestawia
znaczenie klawisza, a nie wyłącza drugą drogę, więc obie są zawsze osiągalne.
Usunięcie trwałe **pyta zawsze**, oknem w wariancie groźnym — ustawienie „pytaj
przed usunięciem” z kroku 41 rządzi odtąd czynnością odwracalną, czyli koszem.

Kosz jest **katalogiem konfigurowalnym o stałym układzie** (D81, nr 3):
domyślnie `$XDG_DATA_HOME/Trash` (bez zmiennej — `~/.local/share/Trash`), czyli
kosz środowiska graficznego, a pozycja tekstowa modułu wolno wskazać dowolny
inny. Układ freedesktop.org obowiązuje wszędzie: wpis ląduje w `files/`,
a w `info/` staje `nazwa.trashinfo` ze ścieżką powrotną (kodowaną jak adres URL)
i datą usunięcia — **pisany przed przeniesieniem**, bo wpis w koszu bez niego
jest wpisem, którego nie da się przywrócić. Plik informacyjny tworzony trybem
`x` jest zarazem rezerwacją nazwy; kolizję rozwiązuje sufiks liczbowy przed
rozszerzeniem (`raport.pdf`, `raport.1.pdf`), jak w koszu środowiska.

Do kosza przenosi się **zmianą nazwy, nigdy kopiowaniem** (D81, nr 4) — dlatego
katalog z zawartością jedzie w całości, bez liczenia i bez okien pracy, i to
jest główny zysk kosza nad usuwaniem. Wpis z **innego systemu plików** dostaje
ostrzeżenie i pytanie o trzech odpowiedziach (D81, nr 5): skopiować do kosza
(pracą kawałkową z kroku 42 — nazwy rezerwuje się w koszu przed pierwszym
bajtem, a praca dostaje je mapą `targetNames` w `begin()`), usunąć trwale albo
przerwać. Kosza na wolumenie (`.Trash-$uid`) aplikacja **nie zakłada**.

**Stos cofnięć jest pamięcią modułu, nie rdzenia** — wbrew literze planu kroku
i zgodnie z regułą 15: operacje zmaterializowały się w całości po stronie
przeglądarki, więc dziennik (`Module/Browser/Application/Undo/UndoJournal`) ma
jednego piszącego i jednego czytającego. `Alt`+`u` cofa najnowszą operację
odwracalną; `F3` otwiera widok stosu, w którym cofnąć wolno **dowolną pozycję**
(D81, nr 6) — pozycje nieodwracalne stoją wyszarzone i niewybieralne (nr 8),
bo lista odpowiada też na pytanie „co się właściwie wydarzyło”. Głębokość stosu
jest pozycją ustawień (nr 7); zapis **nie przeżywa zamknięcia aplikacji** —
cofanie po restarcie byłoby dziennikiem transakcji, a nie wygodą.

**Spis operacji odwracalnych mieszka w kodzie** (`UndoEntry::reversible()`),
nie w napisie — wraz z powodami, dla których pozostałe nimi nie są:

| Operacja | Cofnięcie |
|---|---|
| zmiana nazwy | zmiana nazwy z powrotem |
| nowy katalog | usunięcie — **dopóki pozostał pusty** (D81, nr 10) |
| do kosza | przywrócenie z kosza (ścieżka z pliku informacyjnego) |
| przeniesienie | przeniesienie z powrotem, tą samą pracą kawałkową |
| kopiowanie | **nie** — cofnięciem byłoby usunięcie kopii, czyli operacja nieodwracalna udająca powrót |
| usunięcie trwałe | **nie** — nie ma skąd wrócić |

Cofnięcie nieudane (wpis zniknął, miejsce zajęte, katalog przestał być pusty)
**mówi dlaczego i nie zdejmuje zapisu** — inaczej użytkownik traci jedyną
informację o tym, co się stało; przywrócenie zbioru przerwane w połowie wymienia
zapis na pomniejszony o to, co już wróciło. Kursor po cofnięciu staje na wpisie
przywróconym, bo to on jest odpowiedzią na pytanie „czy się udało”.
