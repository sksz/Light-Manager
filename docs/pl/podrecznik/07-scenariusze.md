# 7. Scenariusze

> Podręcznik użytkownika, część 7 z 7. [Spis](README.md) ·
> [English](../../en/manual/07-scenarios.md)

Osiem dróg od początku do końca. Każda ma **po co**, **kroki z klawiszami**,
**co widać** i **co może pójść nie tak**.

Każdy scenariusz opisuje drogę, którą przechodzi **przebieg funkcjonalny**
w `tests/Functional/` — nazwa przebiegu stoi przy scenariuszu. To nie jest
ciekawostka, tylko zobowiązanie: gdy przebieg się zmieni, scenariusz kłamie,
więc oba wymienia się w tym samym kroku planu.

---

## 1. Skopiuj zaznaczone pliki do drugiego panelu i cofnij pomyłkę

*Przebiegi: `FileOperationsFlowTest`, `MarkedEntriesFlowTest`, `UndoFlowTest`*

**Po co.** Najczęstsze zadanie menadżera plików: wziąć kilka plików z jednego
katalogu i położyć w drugim — a potem odkryć, że to był zły katalog.

**Kroki.**

1. `F2` → zakładka „Przeglądarka plików" → **Podział na dwa panele: tak** →
   `Esc`.
2. W lewym panelu wejdź `Enter`ami do katalogu źródłowego, w prawym (`Tab`) do
   docelowego; `Tab` z powrotem na lewy.
3. Ustaw kursor na pierwszym pliku i naciśnij **`Space`** tyle razy, ile plików
   chcesz — kursor schodzi sam. Zakres zaznaczysz `Shift`+`↓`.
4. **`F5`**. Okno pokazuje katalog **drugiego panelu** jako cel — `Enter`
   zatwierdza.
5. Pomyłka? **`Alt`+`U`** cofa ostatnią operację odwracalną, a **`F3`** pokazuje
   cały stos.

**Co widać.** Pas ścieżki podsumowuje zbiór (`• 12 z 340 · 4,1 GB`). W trakcie
kopiowania okno postępu podaje nazwę pliku, licznik „N z M" i pasek liczony
**w bajtach**, nie w plikach. Po operacji zaznaczone zostaje **to, czego nie
dotknęła** — czyli pominięte i nieudane.

**Co może pójść nie tak.**

- **Kopiowania nie da się cofnąć** — na stosie stoi wyszarzone. `Alt`+`U`
  cofnie zmianę nazwy, przeniesienie, kosz i pusty nowy katalog.
- **Nazwa zajęta w celu** zatrzymuje pracę pytaniem o sześciu odpowiedziach;
  „nadpisz wszystkie" i „pomiń wszystkie" dotyczą reszty przebiegu.
- **`Esc` w trakcie** przerywa, a plik zapisany w połowie **znika**.
- **Katalogu nie skopiujesz do jego własnego wnętrza** — aplikacja odmawia
  i mówi dlaczego.

---

## 2. Znajdź plik filtrem, obejrzyj go i sprawdź jego opis

*Przebiegi: `FilterFlowTest`, `TextPreviewFlowTest`, `FileDescriptionFlowTest`*

**Po co.** Katalog ma tysiąc wpisów, a ty pamiętasz trzy litery z nazwy.

**Kroki.**

1. **`/`** i wpisz fragment nazwy — lista zwęża się przy każdej literze.
2. `↑`/`↓` po zawężonej liście; **`Enter`** zostawia ją zawężoną.
3. **`Ctrl`+`D`** otwiera opis wpisu.
4. **`Tab`** przenosi kursor do podglądu treści; `PgUp`/`PgDn` przewijają
   o panel, `Alt`+`Z` przełącza zawijanie.
5. **`s`** liczy sumę kontrolną, **`d`** zajętość katalogu.
6. `Esc` wraca do plików, `Esc` jeszcze raz zdejmuje filtr.

**Co widać.** Dopasowany fragment jest **podświetlony**. Opis to cztery zwijane
sekcje; podgląd pokazuje treść pliku tekstowego — także `README` i `.gitignore`,
bo o tym, czy plik jest tekstem, rozstrzyga kaskada, a nie samo rozszerzenie.

**Co może pójść nie tak.**

- **Filtr nie jest wzorcem** — to podciąg bez rozróżniania wielkości liter.
  Gwiazdki i wyrażeń regularnych nie ma.
- **Suma kontrolna nie startuje sama** i powyżej ustawionego limitu rozmiaru nie
  startuje wcale — mówi dlaczego.
- **Po `End` w podglądzie znikają numery wierszy**; `Home` je przywraca.
- **Podglądu binariów nie ma** i mówi o tym wprost.

---

## 3. Połącz się z hostem i pobierz z niego plik

*Przebiegi: `SshSessionFlowTest`, `RemoteDirectoryFlowTest`, `FileTransferFlowTest`*

**Po co.** Zajrzeć na cudzą maszynę i ściągnąć z niej plik, nie wychodząc
z aplikacji.

**Kroki.**

1. **`Ctrl`+`W`** → zakładka „Sesja zdalna" → **`F7`** dopisuje wpis → `F4`
   prowadzi po polach: adres, port, użytkownik, sposób uwierzytelnienia.
2. **`Ctrl`+`S`** → kursor na wpisie → **`Enter`** łączy.
3. Host nieznany? Okno pyta o **odcisk `SHA256:…`** — przeczytaj i potwierdź.
4. Po połączeniu ekran pokazuje **zdalny katalog**: `Enter` wchodzi,
   `Backspace` wraca, `/` zawęża, `Ctrl`+`H` pokazuje ukryte.
5. Kursor na pliku → **`F5`** pobiera go do katalogu, w którym stoi
   przeglądarka. `F6` wysyła w drugą stronę.

**Co widać.** Zdalna lista ma te same kolumny, co lokalna. Górny pas mówi,
z kim stoi sesja. Pasek postępu liczy bajty **przy pobieraniu**; przy wysyłaniu
pokazuje tylko, że praca trwa — ile poszło w sieć, klient `sftp` na potoku nie
mówi.

**Co może pójść nie tak.**

- **Modułu nie ma na liście** → brakuje klienta OpenSSH.
- **Klucz niezgodny z zapamiętanym** to nie pytanie, tylko **odmowa**.
- **Stan sesji nie odświeża się sam** — sesja zerwana przez sieć bywa przez
  chwilę pokazana jako żywa; od sprawdzenia jest **`F5`**.
- **Przesyłane są pliki, nie katalogi.**
- **Przerwanie nie zostawia pliku wyglądającego na gotowy** — treść leży pod
  nazwą roboczą `.nazwa.lm-part`, dopóki nie dojdzie w całości.

---

## 4. Podnieś projekt compose i obejrzyj logi kontenera

*Przebieg: `DockerFlowTest`*

**Po co.** Uruchomić projekt i zobaczyć, co mówi, gdy nie wstaje.

**Kroki.**

1. W przeglądarce wejdź do katalogu z plikiem `compose.yaml`.
2. **`F12`** → `docker.up` → `Enter`. Bez argumentu komenda bierze plik
   z katalogu, w którym stoi przeglądarka.
3. **`Ctrl`+`O`** otwiera listę kontenerów; **`F5`** zawęża ją do projektu.
4. Kursor na kontenerze → **`Enter`** otwiera **logi na żywo**.
5. `↑` zatrzymuje widok, **`End`** wraca na koniec, `Esc` zamyka.
6. **`F4`** zatrzymuje albo uruchamia kontener, `Shift`+`F4` restartuje.

**Co widać.** Listy pochodzą wprost od demona i odświeżają się co kilka sekund,
dopóki ekran jest widoczny. Logi płyną **także wtedy, gdy patrzysz na co
innego**.

**Co może pójść nie tak.**

- **Modułu nie ma na liście** → brakuje rozszerzenia PHP `curl`.
- **Brak gniazda lokalnego nie zabiera modułu** — to stan środowiska, mówiony
  zdaniem na ekranie.
- **`docker.up` w środowisku zdalnym pyta przed podniesieniem**: plik compose
  czyta klient po tej stronie, ale `volumes:` wskazują ścieżki na maszynie
  demona.

---

## 5. Zbuduj obraz i wdroż go w klastrze

*Przebiegi: `DeployImageFlowTest`, `ClusterFlowTest`*

**Po co.** Przejść drogę od katalogu z `Dockerfile` do działającego wdrożenia,
z rejestrem prywatnym po drodze.

**Kroki.**

1. **`Ctrl`+`O`** → **`F7`** buduje obraz: pyta o katalog z `Dockerfile`, potem
   o nazwę.
2. **`F12`** → `docker.push` → wybierz rejestr (przy jednym nie pyta).
3. **`Ctrl`+`K`** → **`c`** wybiera klaster, **`n`** przestrzeń nazw.
4. **`F12`** → `k8s.deploy-image` → podaj nazwę obrazu i wdrożenie.
5. `Enter` na zasobie otwiera opis, **`y`** pokazuje surowy YAML, **`l`** logi
   poda.

**Co widać.** Drzewo po lewej: grupy API, w nich rodzaje zasobów, w nich zasoby
— **gałąź czyta się dopiero przy rozwinięciu**. Nagłówek pokazuje obie wersje,
gdy klient i serwer różnią się o więcej niż jedno wydanie.

**Co może pójść nie tak.**

- **Rejestr prywatny nie wymaga niczego po stronie klastra**: wdrożenie **samo
  zakłada sekret** (`lm-registry-<nazwa>`) i dopina go, nie kasując tych, które
  wdrożenie już miało.
- **Poświadczenie nie przechodzi przez wiersz polecenia** — idzie plikiem
  `0600`, kasowanym zaraz po użyciu.
- **Klaster nieosiągalny** daje ekran z trzema klawiszami: `c` spis klastrów,
  `k` kontekst, `Enter` spytaj jeszcze raz.
- **Plik `kubeconfig` zostaje nietknięty** — aplikacja do niego nie pisze.

---

## 6. Zapytaj aplikację o jej własny stan

*Przebiegi: `QueryWindowFlowTest`, `QueryCatalogueTest`*

**Po co.** Dowiedzieć się, co aplikacja o sobie wie: co jest zaznaczone, jakie
prace idą w tle, co gra, jakie kontenery widzi demon.

**Kroki.**

1. **`F12`** otwiera okno komend.
2. **`Tab` przy pustym wierszu** przełącza je na **kwerendy**.
3. `↑`/`↓` po liście albo wpisz fragment nazwy; `Enter` zadaje pytanie.
4. **`Alt`+`C`** kopiuje całą odpowiedź do schowka.
5. `Tab` wraca do komend, `Esc` zamyka okno.

**Co widać.** Odpowiedź wierszami, w oknie nad ekranem. Kwerendy nazywają się
z przestrzenią właściciela — `core.*`, `browser.*`, `docker.*` i tak dalej.

**Co może pójść nie tak.**

- **Kwerenda czyta i nie zmienia** — żadna nie może niczego zepsuć.
- **Kwerenda z argumentem** poprosi o niego zamiast odpowiedzieć; brak
  argumentu **zostawia okno otwarte** wraz z wpisanym wierszem.
- **Odpowiedź, która jeszcze nie przyszła** (praca w tle) mówi, że praca trwa,
  zamiast czekać.

---

## 7. Dopisz miejsce do książki adresowej i połącz się z niego

*Przebiegi: `AddressBookFlowTest`, `ClusterBookFlowTest`*

**Po co.** Jeden adres bywa potrzebny trzem modułom naraz — i wtedy warto go
mieć w jednym miejscu, a nie w trzech.

**Kroki.**

1. **`Ctrl`+`W`** otwiera książkę; `←`/`→` chodzą po **rozdziałach**.
2. **`F7`** dopisuje wpis — podaj nazwę.
3. Zakładka „Sesja zdalna" → **`F4`** prowadzi po polach: adres, port,
   użytkownik, uwierzytelnienie.
4. **Ten sam wpis**, zakładka „Docker" → `F4` → rodzaj `tunel`, cel wskazujący
   ten wpis, ścieżka gniazda.
5. Zakładka „Kubernetes" → `F4` → plik `kubeconfig` i kontekst.
6. `Ctrl`+`S`, `Ctrl`+`O`, `Ctrl`+`K` — każdy moduł widzi ten wpis u siebie.

**Co widać.** Zakładka „Wszystkie" pokazuje wpisy z identyfikatorami, każda
następna — kolumny jednego rozdziału. Pola oznaczone jako **sekret** są na
ekranie zasłonięte.

**Co może pójść nie tak.**

- **Tożsamością wpisu jest identyfikator, nie nazwa** — nazwę wolno zmienić,
  powtórzyć albo zostawić pustą; odniesienia innych modułów tego nie zauważą.
- **Rozdział nie jest niczyj** — każdy moduł czyta i zmienia wszystkie
  rozdziały.
- **Token leży w pliku jawnie**, z prawami `0600`. Szyfrowania nie ma.
- **Spisy ze starszych wersji przenoszą się same** przy pierwszym uruchomieniu;
  stary zapis zostaje nietknięty.

---

## 8. Zaznacz błąd w logu myszą i skopiuj go

*Przebiegi: `ClipboardFlowTest`, `SelectionInOverlayFlowTest`, `PointerFlowTest`*

**Po co.** Wziąć z ekranu treść, której nigdzie nie wpisałeś — wiersz logu,
odcisk klucza, odpowiedź kwerendy — i wkleić ją gdzie indziej.

**Kroki.**

1. Otwórz treść: logi kontenera (`Ctrl`+`O`, `Enter`), okno kwerend (`F12`,
   `Tab`) albo pytanie o odcisk klucza hosta.
2. **Przeciągnij myszą** po klatce — prostokąt obejmuje to, co chcesz.
3. **`Alt`+`C`** kopiuje. Pasek stanu mówi, **co** skopiowano.
4. Wklejasz **`Alt`+`V`** — w polu tekstowym z ogniskiem: nazwa pliku, wiersz
   komend, wartość ustawienia.

**Co widać.** Prostokąt na treści i zdanie w rodzaju „Zaznaczono 14 wierszy
klatki", a po skopiowaniu — „Skopiowano zaznaczenie: 14 wierszy". Zaznaczanie
działa **w oknie nakładanym** tak samo, jak na ekranie pod nim.

**Co może pójść nie tak.**

- **Bez zaznaczenia `Alt`+`C` kopiuje co innego**: ścieżki wpisów zaznaczonych
  spacją, a gdy i tych nie ma — ścieżkę wpisu pod kursorem.
- **`Alt`+`V` nad listą plików** mówi, że nie ma gdzie wkleić, i **nie pyta
  terminala** o zawartość schowka.
- **Terminal, który nie oddaje schowka**, milczy zamiast odmówić — `Alt`+`V`
  kończy się po ćwierć sekundy zdaniem. Zobacz [rozdział 2](02-instalacja.md),
  „Gdy coś nie działa".
- **Treść dłuższa niż 64 kB kończy się odmową** ze zdaniem, zamiast cichym
  obcięciem w połowie.
- **Pod XTermem `Alt` wymaga `metaSendsEscape: true`** — bez niego skrót
  w ogóle nie dochodzi.

---

## Dokąd dalej

Pełny spis klawiszy: [rozdział 3](03-ekran-i-sterowanie.md). Co potrafi każdy
moduł: [rozdział 5](05-moduly.md). Chcesz **rozwijać** aplikację, a nie tylko
jej używać → [przewodnik dewelopera](../przewodnik/README.md).
