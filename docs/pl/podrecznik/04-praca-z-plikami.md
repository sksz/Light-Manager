# 4. Praca z plikami

> Podręcznik użytkownika, część 4 z 7. [Spis](README.md) ·
> [English](../../en/manual/04-working-with-files.md)

## Poruszanie się

`↑` i `↓` przesuwają kursor, `Enter` albo `→` wchodzi do katalogu, `Backspace`
albo `←` wraca wyżej. Na pliku `Enter` nie robi nic — opisem pliku zajmuje się
osobny moduł (`Ctrl`+`D`), bo dwa miejsca robiące to samo znaczyłyby dwa razy
ten sam odczyt.

Lista ma **cztery kolumny**: nazwa, rozmiar, data zmiany i prawa dostępu.
W wąskim oknie kolumny **ustępują po kolei** — najpierw prawa, potem data, potem
rozmiar; nazwa nie ustępuje nigdy. Kolumna, która nie mieści się w całości,
**znika w całości**: przycięta data (`2026-08-…`) nie mówi nic, a zabiera znaki
nazwie, która by je wykorzystała.

Wpisy ukryte pokazuje i chowa **`.`** — ustawienie jest trwałe i dotyczy obu
paneli.

## Dwa panele

Przeglądarkę można podzielić na **dwa panele**: dwa katalogi, dwa kursory,
niezależne od siebie. Włącza się to w ustawieniach modułu („Podział na dwa
panele"), a `Tab` przenosi ognisko. Panel czynny poznaje się po akcencie
w obwódce i po ścieżce w górnym pasie; katalog panelu nieczynnego widać
w etykiecie jego ramki. Panele stają domyślnie obok siebie — pozycja „Panele
obok siebie" przestawia je jeden nad drugi, a granicę wolno przeciągnąć myszą.

Podział **nie powstaje w oknie węższym niż 72 kolumny** (przy układzie poziomym
— niższym niż 14 wierszy w strefie listy). Poniżej progu widać jeden panel: dwa
panele mieszczą się tam arytmetycznie, ale nazw plików nie da się w nich
przeczytać.

## Drzewo

`Ctrl`+`T` zamienia panel z ogniskiem w **drzewo** — i z powrotem w listę. Widok
należy do panelu, więc przy podziale wolno mieć drzewo po jednej stronie i listę
po drugiej.

| Klawisz | W drzewie |
|---|---|
| `→` | rozwinięcie gałęzi; na rozwiniętej — zejście do pierwszego dziecka |
| `←` | zwinięcie; na zwiniętej — skok do rodzica; na pierwszym poziomie — katalog wyżej |
| `Enter` | katalog spod kursora staje się katalogiem panelu |
| `Backspace` | katalog wyżej |

Gałąź czyta się z dysku **dopiero przy rozwinięciu**, więc drzewo nietknięte nie
kosztuje ani jednego dodatkowego odczytu. Raz przeczytana gałąź zostaje
w pamięci — i stąd bierze się jedyna cena tej zamiany, którą warto znać:
**drzewo pokazuje to, co przeczytało**, a nie to, co w tej sekundzie leży na
dysku.

Ile poziomów wolno rozwinąć, rozstrzyga pozycja „Poziomy drzewa (Ctrl+T)"
w ustawieniach modułu; `∞` znaczy „bez limitu". Przy limicie osiągniętym `→`
melduje się zdaniem w pasku stanu, zamiast nie robić nic.

## Filtr

**`/`** otwiera pole filtra przy dolnej krawędzi, a lista zwęża się przy każdej
literze, **w tej samej klatce**. Dopasowany fragment jest podświetlony.

Dopasowanie to **podciąg bez rozróżniania wielkości liter**, także poza ASCII
(`Ł` znajduje `ł`). Wzorców ani wyrażeń regularnych nie ma.

Strzałki w otwartym polu chodzą po zawężonej liście, `Enter` zostawia ją
zawężoną, `Esc` zdejmuje filtr i wraca do wpisu sprzed jego otwarcia. Filtr
dotyczy **panelu z ogniskiem**, widać go znacznikiem w pasie ścieżki i znika
przy zmianie katalogu.

## Zaznaczenie wielokrotne

**Spacja** zaznacza wpis pod kursorem i schodzi wiersz niżej, więc ciąg plików
zaznacza się jednym palcem. `Shift`+strzałki zaznaczają zakresem, a `*` odwraca
zaznaczenie na tym, co widać.

Zaznaczone wiersze mają własny znacznik w kolumnie przed nazwą **i** własny
kolor napisu — widać je więc i wtedy, gdy kursor stoi gdzie indziej. Pas ścieżki
podsumowuje zbiór: `• 12 z 340 · 4,1 GB`. Katalogi wolno zaznaczyć na równi
z plikami, ale ich rozmiaru nikt nie zna — suma je pomija i mówi o tym wprost
(`bez 2 kat.`).

**Pusty zbiór znaczy „wpis pod kursorem", a nie „nic".** Bez zaznaczenia każda
czynność działa dokładnie tak, jakby zaznaczenia w ogóle nie było.

Zbiór **przeżywa zawężenie filtrem** — wpis, którego filtr nie pokazuje, nadal
do niego należy — a ginie razem z katalogiem. `Esc` zdejmuje warstwy po kolei:
najpierw filtr, potem zaznaczenie. Każdy panel ma własny zbiór, a drzewo ani go
nie pokazuje, ani na nim nie działa.

## Pięć czynności zmieniających dysk

| Klawisz | Czynność | Działa na |
|---|---|---|
| `F4` | zmiana nazwy | jednym wpisie (nazwa jest jedna z definicji) |
| `F5` | kopiowanie | zaznaczonych albo wpisie pod kursorem |
| `F6` | przeniesienie | zaznaczonych albo wpisie pod kursorem |
| `F7` | nowy katalog | — |
| `F8` / `Del` | usunięcie | zaznaczonych albo wpisie pod kursorem |

`F4` otwiera okno z **nazwą bieżącą** w polu, `F7` z pustym; `Enter` zatwierdza,
`Esc` odmawia i nie dotyka dysku. **Nazwa jest nazwą, nie ścieżką**: ukośnik
w niej jest błędem, a nie zaproszeniem do utworzenia katalogu piętro niżej.
Nazwa zajęta **nie zamyka okna** — jest co poprawić.

Wszystkie czynności poza usunięciem mają drugie wejście w oknie komend
(`browser.rename`, `browser.mkdir`, `browser.copy`, `browser.move`);
`browser.delete` też tam jest, ale pytanie stawia zawsze. Nazwa ze spacją idzie
w cudzysłowach, a komenda bez argumentu otwiera to samo okno, co klawisz.

### Kopiowanie i przenoszenie

`F5` i `F6` otwierają okno z **katalogiem docelowym** wypełnionym katalogiem
drugiego panelu; ścieżkę wolno poprawić albo wpisać własną, także względną.
Okno działa również przy wyłączonym podziale, więc cel nigdy nie jest
niespodzianką.

Przeniesienie w obrębie **jednego systemu plików** dzieje się natychmiast —
kosztuje jedną zmianę nazwy, niezależnie od tego, ile plików jest w katalogu.
Między systemami plików nie ma innej drogi niż skopiowanie i usunięcie źródła,
a wtedy obowiązuje reguła bez odstępstwa: **źródło znika dopiero po zapisaniu
celu w całości**.

Praca idzie **po kawałku na klatkę**: aplikacja najpierw liczy, ile bajtów
i wpisów przybędzie, a potem kopiuje — pasek postępu mówi więc prawdę od
pierwszego bajtu. `Esc` przerywa, a **plik zapisany w połowie znika**: plik
wyglądający na gotowy jest gorszy niż brak pliku.

Kiedy w katalogu docelowym coś już stoi pod tą nazwą, aplikacja pyta — sześcioma
odpowiedziami: nadpisz, nadpisz wszystkie, pomiń, pomiń wszystkie, zapisz pod
inną nazwą, przerwij. **Katalog o tej samej nazwie nie jest kolizją**, tylko
scaleniem. Dowiązanie symboliczne kopiuje się jako dowiązanie, a kopia dostaje
prawa i czas zmiany oryginału; właściciela nie — na to trzeba uprawnień,
których aplikacja nie ma i mieć nie powinna.

Skopiować katalogu do jego własnego wnętrza ani do katalogu, w którym już leży,
nie można — aplikacja odmawia i mówi dlaczego.

### Kosz, usunięcie trwałe i dwie drogi

`F8` (albo `Del`) robi to, co mówi pozycja **„Usuwaj do kosza"** — domyślnie
przenosi do kosza środowiska graficznego, wraz z plikiem informacyjnym wedle
freedesktop.org, więc wpis widać i da się go przywrócić także z pulpitu.
`Shift`+`F8` robi zawsze **to drugie**.

Usunięcie trwałe **pyta zawsze**, w wariancie groźnym: czerwoną oprawą,
z ogniskiem na odmowie, więc przytrzymany `Enter` trafia w „nie". Pytanie przed
koszem podlega pozycji „Pytaj przed usunięciem", bo kosz jest odwracalny. Przy
zaznaczeniu wielokrotnym pytanie mówi **liczbą**, a nie nazwą pierwszego wpisu.

Katalog usuwa się trwale wraz z zawartością, ale nie po cichu: aplikacja
najpierw liczy, ile wpisów zniknie. Przy dużym drzewie liczenie i usuwanie
**nie zatrzymują aplikacji**, a `Esc` przerywa i mówi uczciwie, ile już
zniknęło. Do kosza katalog jedzie za to **w całości i od razu** — jedną zmianą
nazwy, bez liczenia i bez okien.

Katalog kosza wolno przestawić w ustawieniach (pusty znaczy: systemowy). Wpis
z innego systemu plików niż kosz dostaje pytanie: skopiować do kosza, usunąć
trwale, czy przerwać.

### Cofanie

`Alt`+`U` cofa **ostatnią operację odwracalną**, a `F3` otwiera **stos
cofnięć** — listę wykonanych operacji, z której wolno cofnąć dowolną pozycję.

| Operacja | Odwracalna? |
|---|---|
| zmiana nazwy | tak |
| przeniesienie | tak |
| kosz | tak |
| nowy katalog | tak, dopóki pozostał pusty |
| kopiowanie | **nie** — cofnięciem byłoby usunięcie kopii |
| usunięcie trwałe | **nie** |

Operacje nieodwracalne stoją na liście **wyszarzone**, więc widać, że były.
Cofnięcie nieudane mówi dlaczego i nie zdejmuje zapisu. Stos **nie przeżywa
zamknięcia aplikacji**; jego głębokość ustawia pozycja „Głębokość stosu cofnięć".

## Co widać po operacji

Zaznaczone zostaje **to, czego operacja nie dotknęła**: wpisy, które zniknęły,
wypadają ze zbioru, a pominięte przy kolizji i nieudane **zostają zaznaczone** —
to jedyna droga, którą widać, co się nie udało.

Skutek widać w tej samej klatce **w obu panelach**, jeśli oba patrzą na ten sam
katalog; panel, któremu usunięto katalog pod nogami, wchodzi do najbliższego
czytelnego wyżej. Zmiana zrobiona **spoza aplikacji** wymaga wejścia do katalogu
na nowo: aplikacja odświeża listę po własnej operacji.

## Menu kontekstowe

`F9` otwiera pośrodku klatki listę czynności **dla tego, co jest zaznaczone** —
bez pamiętania klawisza i bez pisania nazwy. Na katalogu widać wejście do niego,
opis wpisu i pięć operacji na plikach; na pliku znika samo wejście.

Menu jest **drugim wejściem do rejestru komend, a nie drugim zbiorem
czynności**: pozycje pochodzą z tego samego rejestru, co lista w oknie komend,
a wybór robi dokładnie to, co komenda o tej nazwie — nazwa stoi w wierszu po
lewej, opis po prawej.

Do menu trafiają wyłącznie czynności **na zaznaczeniu**. `browser.hidden`
i `browser.tree` są w rejestrze, ale w menu ich nie ma: dotyczą panelu, a nie
wpisu. Gdy nie pasuje ani jedna czynność — na przykład w pustym katalogu —
menu **nie otwiera się wcale** i mówi to zdaniem, zamiast prosić o zamknięcie
pustego okna.

## Dokąd dalej

- [5. Moduły](05-moduly.md) — co potrafi każde z siedmiu okien
- [7. Scenariusze](07-scenariusze.md) — kopiowanie i cofnięcie krok po kroku
