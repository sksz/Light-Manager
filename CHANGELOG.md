# Historia zmian

Light Manager — menadżer plików działający w terminalu i we własnym oknie.

## Niewydane — Kastaniety (Faza XIX): wskaźnik

- **Mysz robi to, czego się po niej spodziewasz** (2026-08-16). Kliknięcie stawia
  kursor tam, gdzie patrzysz, kółko przewija, prawy przycisk otwiera menu,
  granicę między panelami przeciągasz myszą, a podpowiedzi w stopce i zakładki
  pomocy po prostu się klika. Działa tak samo w terminalu i w oknie.

W przygotowaniu: zaznaczanie treści myszą i schowek systemowy (kopiuj-wklej
między aplikacją a resztą pulpitu).

## 18.x — Fisharmonia (Faza XVIII): kontenery i wspólny język modułów

Jeden miech, wiele głosów: dochodzą dwa duże moduły, a wszystkie części
aplikacji dostają wspólny sposób wymiany informacji.

- **18.4.0** — 2026-08-16 — **Obraz z Dockera ląduje w klastrze.** Jedno
  polecenie buduje obraz, czeka na koniec budowy i podmienia go we wskazanym
  wdrożeniu Kubernetesa. Przy wyłączonym module Dockera aplikacja mówi, czego
  brakuje, zamiast się wywrócić.
- **18.3.0** — 2026-08-16 — **Aplikacja umie odpowiedzieć na pytanie o siebie.**
  Osobne okno ze spisem pytań: co jest w schowku modułu, co gra, co się liczy
  w tle, jaki jest motyw i wersja. Każda część aplikacji oddaje to, co wie,
  jedną drogą.
- **18.2.0** — 2026-08-16 — **Podgląd klastra Kubernetes.** `Ctrl`+`U` pokazuje
  zasoby wybranego kontekstu, `Enter` otwiera opis, `l` — logi płynące na żywo,
  a plik da się zastosować z tego samego ekranu. Niedostępny klaster mówi
  o tym po sekundzie, zamiast wieszać program.
- **18.1.0** — 2026-08-16 — **Docker pod ręką.** Kontenery i obrazy na jednym
  ekranie, logi na żywo, budowanie obrazu z widocznym postępem, projekt compose
  podnoszony i kładziony bez wychodzenia do powłoki.

## 17.x — Róg (Faza XVII): praca na cudzej maszynie

Aplikacja po raz pierwszy sięga poza własny komputer.

- **17.3.0** — 2026-08-15 — **Przesyłanie plików w obie strony.** `F5` pobiera,
  `F6` wysyła, okno mówi ile i dokąd, `Esc` przerywa. Kolizja nazw pyta, a po
  przerwaniu nie zostaje plik udający kompletny. Szybkość na poziomie zwykłego
  `scp` — 32 MB w sekundę.
- **17.2.0** — 2026-08-15 — **Zdalny katalog jak własny.** Po połączeniu widać
  zawartość katalogu na drugiej maszynie — z nazwami, rozmiarami i datami —
  i chodzi się po nim tymi samymi klawiszami. Katalog z dziesięcioma tysiącami
  wpisów nie spowalnia obrazu.
- **17.1.0** — 2026-08-15 — **Połączenie SSH i książka hostów.** Spis hostów
  prowadzisz z ekranu aplikacji, a nie wpisujesz przy każdym uruchomieniu.
  `Ctrl`+`S`, `Enter` — i pasek stanu mówi, z kim jesteś połączony. Host
  o nieznanym odcisku klucza zatrzymuje się pytaniem.

## 16.x — Kamerton (Faza XVI): dostrojenie

- **16.1.0** — 2026-08-14 — **Wydanie bez ani jednej nowej funkcji.** Trzy
  rzeczy obiecane wcześniej zaczynają działać: usuwanie wywołasz z menu `F9`,
  a nie tylko klawiszem; zakładka ustawień dłuższa od okna przewija się zamiast
  gubić pozycje; żaden mechanizm nie stoi bezużytecznie.

## 15.x — Perkusja (Faza XV): dźwięk z charakterem

- **15.2.0** — 2026-08-15 — **Aplikacja mówi o sobie dźwiękiem.** Skasowanie
  pliku, błąd czy otwarcie okna mogą zagrać próbkę, którą sam przypiszesz.
  Jeden przełącznik wycisza wszystkie efekty naraz — muzyki nie rusza.
- **15.1.0** — 2026-08-15 — **Ekran muzyki i playlista.** `Ctrl`+`A` otwiera
  listę utworów, `Enter` gra wskazany, a po skończonym utworze następny rusza
  sam — nawet gdy dawno wróciłeś do przeglądania plików.

## 14.x — Fortepian (Faza XIV): operacje na plikach

Do tej pory aplikacja tylko pokazywała. Tu zaczyna pracować.

- **14.4.0** — 2026-08-15 — **Kosz i cofanie.** Usunięty wpis trafia do kosza
  środowiska graficznego, razem ze ścieżką, z której zniknął — a jeden klawisz
  cofa ostatnią operację bez wychodzenia z aplikacji.
- **14.3.0** — 2026-08-15 — **Zaznaczanie wielu wpisów.** Każda czynność działa
  na całym zaznaczeniu, a pytanie potwierdzenia mówi „12”, a nie nazwę
  pierwszego z brzegu.
- **14.2.0** — 2026-08-15 — **Kopiowanie i przenoszenie do drugiego panelu.**
  Kopiowanie płyty nie zacina obrazu, pasek postępu mówi prawdę, a przerwanie
  w połowie nie zostawia pliku udającego gotowy.
- **14.1.0** — 2026-08-14 — **Zmiana nazwy, nowy katalog, usunięcie.** Pierwsze
  czynności zmieniające dysk — z pytaniem przed skutkiem i z widocznym efektem
  w obu panelach od razu. Operacja nieudana zostawia dysk nietknięty i mówi
  dlaczego.

## 13.x — Flet (Faza XIII): prowadzenie za rękę

- **13.1.0** — 2026-08-14 — **Stopka podpowiada, co da się zrobić tu i teraz.**
  Zamiast czterech niezmiennych klawiszy — podpowiedzi zależne od tego, na czym
  stoi kursor. Co stopka wymienia, to naprawdę działa; co działa, jest
  wymienione.

## 12.x — Katarynka (Faza XII): jedno miejsce do kręcenia korbką

- **12.1.0** — 2026-08-14 — **`make` i po sprawie.** Sprawdzenie środowiska,
  instalacja, kontrola jakości, testy i budowa aplikacji zebrane w jednym
  miejscu, zamiast rozsypane po dokumentacji i skryptach.

## 11.x — Werbel (Faza XI): pilnowanie tempa

- **11.1.0** — 2026-08-13 — **Miary nadążają za aplikacją.** Każdy element
  interfejsu ma własny pomiar albo zapisany powód pominięcia, zrzuty ekranu
  porównuje narzędzie zamiast oka, a typowe drogi użytkownika chodzą jako
  nazwane przebiegi testowe.

## 10.x — Gitara elektryczna (Faza X): muzyka

- **10.1.0** — 2026-08-14 — **Aplikacja gra.** Riff „Smoke On The Water”
  w tle, zatrzymywany i wznawiany poleceniem. Muzyka nie spowalnia obrazu ani
  o milisekundę, a brak wsparcia w środowisku to komunikat, nie awaria.

## 9.x — Syntezator (Faza IX): wyjście poza terminal

- **9.3.0** — 2026-08-13 — **Okno pamięta, jak je ustawiłeś.** Rozmiar
  i położenie wracają przy następnym starcie, dochodzi pełny ekran, własna
  ikona i poprawna skala treści.
- **9.2.0** — 2026-08-12 — **W oknie wygląda tak samo jak w terminalu.**
  Wszystkie ekrany, okna i podglądy — ta sama treść i ten sam układ, tyle że
  rysowane kartą graficzną.
- **9.1.0** — 2026-08-11 — **Tryb okienkowy.** Aplikacja uruchamiana flagą
  otwiera własne okno zamiast rysować w terminalu. Terminal, z którego padło
  polecenie, zostaje nietknięty.

## 8.x — Akordeon (Faza VIII): okno, które się rozciąga

- **8.1.0** — 2026-08-11 — **Zmiana rozmiaru okna działa w locie.** Przeciągasz
  róg, a lista płynie za nim: większe okno pokazuje więcej wierszy, mniejsze —
  mniej. Bez restartu i bez śladów po poprzednim rozmiarze.

## 7.x — Cymbały (Faza VII): sześć nowych elementów interfejsu

- **7.6.0** — 2026-08-14 — **Menu kontekstowe.** Jeden klawisz na zaznaczonym
  pliku pokazuje tylko to, co naprawdę da się z nim zrobić — bez pamiętania
  klawiszy i nazw.
- **7.5.0** — 2026-08-14 — **Drzewo katalogów.** Struktura z wcięciami
  i rozwijaniem zamiast płaskiej listy; rozwinięte gałęzie wracają w tym samym
  stanie po powrocie z innego ekranu.
- **7.4.0** — 2026-08-12 — **Filtrowanie z podświetleniem.** Trzy litery
  zawężają listę natychmiast, pasujący fragment widać na pierwszy rzut oka,
  a wyjście z filtra przywraca listę razem z zaznaczeniem sprzed niego.
- **7.3.0** — 2026-08-12 — **Podgląd plików tekstowych.** Tam, gdzie stało
  „(brak podglądu)”, widać treść — przewijaną, a plik na pół gigabajta
  niczego nie zatrzymuje.
- **7.2.0** — 2026-08-12 — **Pytanie przed skutkiem.** Jeden sposób zadawania
  pytań, na które trzeba odpowiedzieć, zanim coś się stanie.
- **7.1.0** — 2026-08-11 — **Lista z kolumnami.** Nazwa, rozmiar, data
  i uprawnienia obok siebie; w wąskim oknie kolumny znikają w ustalonej
  kolejności, a nie przypadkiem.

## 6.x — Kotły (Faza VI): robota w tle

- **6.1.0** — 2026-08-11 — **Długie zadania przestają zamrażać ekran.**
  Liczenie zajętości katalogu trwa cztery sekundy, a aplikacja przez ten czas
  chodzi płynnie i nie zostawia po sobie porzuconych procesów.

## 5.x — Ksylofon (Faza V): elementy do składania ekranów

- **5.4.0** — 2026-08-10 — **Pełny obraz pliku.** Czym jest, ile naprawdę
  zajmuje, do kogo należy, co wolno z nim zrobić i kiedy był ruszany — bez
  wychodzenia z menadżera do powłoki.
- **5.3.0** — 2026-08-10 — **Dwa panele obok siebie.** Każdy działa osobno,
  reaguje na własne klawisze, a jeden klawisz przenosi między nimi uwagę.
- **5.2.0** — 2026-08-10 — **Pasek postępu.** Jeden sposób pokazywania, że coś
  trwa — także wtedy, gdy nie wiadomo, ile jeszcze.
- **5.1.0** — 2026-08-10 — **Zwijane sekcje.** Grupy wierszy da się schować
  i przywrócić jednym klawiszem.

## 4.x — Organy (Faza IV): rejestry, które można dokładać

- **4.4.0** — 2026-08-10 — **Przeglądarka plików staje się modułem jak każdy
  inny.** Uruchamia się domyślnie, bo tak mówi konfiguracja — da się ją
  podmienić na inną.
- **4.3.0** — 2026-08-09 — **Moduły.** Nową funkcję dokłada się bez ruszania
  rdzenia: własne okno ze skrótem, własna zakładka w ustawieniach i pomocy,
  własne napisy i własne polecenia.
- **4.2.0** — 2026-08-09 — **Okno poleceń pod `F12`.** Czynność wywołujesz
  nazwą, a nie szukaniem wolnego klawisza — z podpowiedziami i uzupełnianiem.
- **4.1.0** — 2026-08-09 — **Wspólne klocki interfejsu.** Okna, etykiety,
  przyciski, pola i listy jako gotowe elementy — zamiast rysowania każdego
  ekranu od zera.

## 3.x — Harfa (Faza III): wygląd, ustawienia i tempo

- **3.5.0** — 2026-08-09 — **Dziesięciokrotne przyspieszenie obrazu.** Klatka
  z 184 ms schodzi grubo poniżej budżetu — aplikacja przestaje zajmować rdzeń
  procesora, gdy nic się nie dzieje.
- **3.4.0** — 2026-08-09 — **Narzędzie do mierzenia wydajności.** Powtarzalny
  pomiar zamiast wrażeń, sterowany z linii poleceń.
- **3.3.0** — 2026-08-09 — **Dwa języki interfejsu.** Polski i angielski,
  przełączane w ustawieniach; wszystkie napisy w jednym miejscu.
- **3.2.0** — 2026-08-09 — **Ustawienia zapisywane na stałe.** Ekran ustawień
  wywoływany z aplikacji i plik `~/.light-manager/settings.json` — motyw
  i przełączniki przestają być zaszyte w kodzie.
- **3.1.0** — 2026-08-08 — **Zaprojektowany wygląd.** Motyw Grafit: jedna
  paleta, rozdzielone strefy ekranu i rama o zaokrąglonych rogach zamiast
  przypadkowych kolorów.

## 2.x — Fujarka (Faza II): pierwsza działająca aplikacja

- **2.8.0** — 2026-08-08 — **Miniatury obrazków.** Zaznaczony plik graficzny
  pokazuje się jako miniatura wprost w oknie terminala. Pierwsza wersja, której
  da się używać.
- **2.7.0** — 2026-08-08 — **Lista plików na ekranie.**
- **2.6.0** — 2026-08-07 — **Chodzenie po katalogach.** Bieżący katalog, jego
  zawartość i kursor sterowany strzałkami.
- **2.5.0** — 2026-08-07 — **Pętla główna.** Aplikacja żyje: czyta klawisze,
  odświeża obraz i kończy się na żądanie.
- **2.4.0** — 2026-08-07 — **Obraz w terminalu.** Klatka rysowana jako obrazek
  i wypychana do terminala tak, by nadpisywała poprzednią, a nie przewijała
  ekran w nieskończoność.
- **2.3.0** — 2026-08-07 — **Rozpoznanie terminala.** Aplikacja sprawdza przy
  starcie, czy terminal umie pokazać grafikę — a jeśli nie, przechodzi na tryb
  tekstowy zamiast odmówić startu.
- **2.2.0** — 2026-08-07 — **Klawiatura i czyste wyjście.** Pojedyncze klawisze
  bez wciskania Entera, a terminal wraca do normalnego stanu przy każdym
  zakończeniu — również awaryjnym.
- **2.1.0** — 2026-08-07 — **Szkielet projektu i sprawdzenie środowiska.**

## 1.x — Kontrabas (Faza I): fundament

Wydanie bez ani jednej rzeczy widocznej na ekranie — same zasady, dzięki którym
kolejne osiemnaście faz powstało szybko i nie pokłóciło się ze sobą.

- **1.4.0** — 2026-08-07 — **Ustalenia spisane raz, w miejscu, do którego się
  wraca** — dokumentacja projektu wraz z instrukcją dla asystenta AI.
- **1.3.0** — 2026-08-07 — **Standardy kodu pilnowane narzędziem**, a nie dobrą
  pamięcią.
- **1.2.0** — 2026-08-07 — **Jeden sposób powoływania usług i startu
  aplikacji.**
- **1.1.0** — 2026-08-07 — **Podział kodu na warstwy** i wspólne nazewnictwo.
