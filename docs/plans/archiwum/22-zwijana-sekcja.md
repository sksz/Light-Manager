# Krok 22 — Zwijana sekcja jako komponent rdzenia

> **Skąd ten krok.** Powstał 2026-08-10, przy otwieraniu kroku o pełnym obrazie
> stanu pliku. Na pytanie „sekcje na ekranie czy jeden strumień wierszy”
> użytkownik odpowiedział: **zwijane sekcje — i to komponent rdzenia, nie modułu**.
> Odpowiedź przewróciła kolejność: moduł nie może użyć klocka, którego nie ma,
> więc klocek dostał własny krok, a rozbudowa `FileInfo` przesunęła się na 25.

## Status

**Ukończony** (2026-08-10). Kod, testy i dokumentacja gotowe: PHPStan `max` bez
błędów, PHP-CS-Fixer bez uwag, **852 testy** (1938 asercji) zielone, klatka
zmierzona i rozliczona „przed i po”, wzorzec zapisany.

Krok miał przez chwilę status „Ukończony z zastrzeżeniem”, bo `bin/render-bench
--save` odmówił zapisu przy czterech próbach: jego strażnik stabilności uznał
rozrzut części scenariuszy za zbyt duży, a maszyna była wtedy obciążona.
Powtórzony przebieg na zwolnionym hoście zapisał wzorzec bez uwag. Wniosek trafił
do `SKILL.md` (reguła 17) i do `CLAUDE.md`: **o zwolnienie maszyny prosi się przed
pomiarem, a nie po nieudanej próbie.**

## Cel

Dać rdzeniowi **sekcję, którą da się zwinąć**: nagłówek z etykietą i znacznikiem
stanu, pod nim wiersze, a klawisz chowa je i przywraca.

Miarą powodzenia jest zdanie: **ekran, który ma do pokazania cztery grupy po
kilka wierszy, składa je z sekcji i nie pisze ani jednej linijki rachunku
przewijania.**

## Zależności

- **Krok 18** (komponenty i płaszczyzny) — twardo. Stamtąd pochodzi
  `ComponentInterface`, `ListView`, `ListRow`, `Highlight` i zasada, że stan
  przeżywający klatkę nie mieszka w komponencie, tylko obok niego
  (`ScrollWindow`).
- **Krok 17** (optymalizacja) — komponent wchodzi **na** segmentowy `FrameLine`
  i pamięci podręczne z D34, nie obok nich. Klatka do rozliczenia pomiarem.
- **Krok 13** (motyw) — nagłówek sekcji rysuje się rolą motywu, a nie kolorem.

Od kroków 20 i 21 nie zależy: sekcja jest komponentem rdzenia i o modułach nie
wie. Stoi za nimi wyłącznie w kolejce.

## Model i wysiłek

**Opus / high.**

Nie z powodu rozmiaru — pliki są trzy — tylko dlatego, że krok dokłada
**drugi w projekcie obiekt stanu żyjącego między klatkami**. Pierwszym jest
`ScrollWindow` i jego historia jest ostrzeżeniem: ten sam rachunek stał w kodzie
trzy razy, w trzech wariantach, i rozjechał się przy trzecim (krok 18). Stan
zwinięcia ma tę samą naturę i tę samą pokusę — a dodatkowo **wchodzi w drogę
przewijaniu**, bo zmienia liczbę wierszy pod kursorem.

## Stan zastany (sprawdzony w kodzie 2026-08-10, po kroku 21)

| Element | Stan |
|---|---|
| `Presentation/Ui/ComponentInterface` | Jedna metoda `draw(Rect): list<Primitive>`; docblock mówi wprost, że komponent znający swoją naturalną wysokość **wystawia ją własną metodą**, a `measure()` w kontrakcie nie powstaje |
| `Presentation/Ui/Component/ListView` | Dostaje **już wybrany** wycinek `list<ListRow>`, położenie zaznaczenia w wycinku i `?ScrollPosition`; rysuje wiersze, podkład zaznaczenia i suwak |
| `Presentation/Ui/Component/ListRow` | `left`, `right`, `Role` — trzy pola, bez pojęcia zagnieżdżenia |
| `Presentation/Ui/ScrollWindow` | Jedyny obiekt stanu między klatkami w warstwie `Ui`; właścicielem jest ekran; `useContext()`, `keepVisible()`, `scrollBy()`, `clamp()`, `position()` |
| `Presentation/Cli/Screen/HelpScreen` | Ma już **sekcje w potocznym sensie**: spis klawiszy grupowany po ekranach, plus `array<string, list<KeyBinding>>` opisane w kodzie jako „sekcje spoza ekranów”. Grupy są płaskie i niezwijalne |
| `Presentation/Ui/Container/{VStack,Slot}` | Podział pionowy wraz z kolejnością ustępowania — gotowy, ale operuje na prostokątach, nie na wierszach listy |

Najważniejsza pozycja tej tabeli to `HelpScreen`: **pierwszy prawdziwy użytkownik
sekcji już istnieje**, więc komponent nie powstaje na domysł.

## Zakres

### 1. Trzy klasy, z czego jedna stanowa

```
src/Presentation/Ui/
├── Component/
│   ├── Section.php        # nowy — nagłówek, znacznik i wiersze sekcji
│   └── SectionList.php    # nowy — spłaszczenie, przewijanie i suwak
└── SectionState.php       # nowy — co zwinięte i gdzie stoi kursor (między klatkami)
```

Podział przebiega dokładnie tam, gdzie w kroku 18 przebiegł dla listy:
**komponent jest bezstanowy i powstaje co klatkę, pamięć stoi obok niego
i należy do ekranu.** Sekcja nie zapamiętuje, czy jest zwinięta — dostaje to
w konstruktorze.

### 2. `Section` — nagłówek i wiersze

```php
final class Section implements ComponentInterface
{
    /** @param list<ListRow> $rows */
    public function __construct(
        private readonly string $label,
        private readonly array $rows,
        private readonly bool $collapsed = false,
    ) {}

    /** Naturalna wysokość: nagłówek plus wiersze, gdy rozwinięta. */
    public function height(): int;

    public function draw(Rect $bounds): array;
}
```

`height()` obok `draw()` jest zgodne z kontraktem, a nie wyjątkiem od niego:
docblock `ComponentInterface` przewiduje, że komponent znający swoją naturalną
wysokość wystawia ją **własną** metodą, i wskazuje przycisk oraz okno dialogowe
jako precedens.

### 3. `SectionList` — przewijanie w poprzek sekcji

Sekcje trzeba przewijać **jak jedną listę**, a nie jak stos niezależnych paneli:
użytkownik ciągnący `↓` przez opis pliku nie ma czuć granic. `SectionList` bierze
`list<Section>`, spłaszcza je do wierszy w kolejności, wycina okno i rysuje —
czyli robi dla sekcji to, co `ListView` robi dla płaskiej listy, i suwak liczy
tak samo.

**`ListView` zostaje nietknięty.** Używa go dziś przeglądarka, pomoc i ustawienia;
dorzucenie mu wiedzy o sekcjach kosztowałoby trzech użytkowników, z których dwóch
sekcji nie chce.

### 4. `SectionState` — pamięć zwinięcia i kursor

Na wzór `ScrollWindow`, z tą samą regułą własności (właścicielem jest ekran)
i tym samym zabezpieczeniem przed rozjazdem (`useContext()` — zmiana kontekstu
zaczyna oglądanie od początku).

Obowiązki: które sekcje są zwinięte, na której stoi kursor, przełączenie
zwinięcia, ruch kursora. **Klucz sekcji jest napisem**, nie numerem: sekcja, która
zniknęła i wróciła, ma wrócić w tym samym stanie, a numer po zmianie listy
wskazywałby na inną.

### 5. Klawisze i wiązania

Sekcja sama klawiszy nie obsługuje — robi to ekran, bo to on ma `handle()`.
Krok dowozi za to **wiązania w jednym brzmieniu**, żeby drugi ekran z sekcjami
nie wymyślił własnych: `←`/`→` zwija i rozwija, `Enter` przełącza, `↑`/`↓`
przewija. Klucze napisów (`help.key.collapse`, `help.key.expand`) wchodzą do
katalogu rdzenia.

### 6. Pierwszy użytkownik: spis klawiszy w oknie pomocy

Komponent bez użytkownika projektuje się na domysł, więc krok **od razu przestawia
zakładkę „Klawisze”** na sekcje. Grupy tam już są — po jednej na ekran plus
„spoza ekranów” — i są dokładnie tym, co ma dać się zwijać, bo spis po dołożeniu
modułów urósł ponad wysokość okna.

To jest **jedyna zmiana widoczna w interfejsie** w tym kroku i ma nią zostać.

### 7. Znacznik stanu i rola motywu

Dwie rzeczy do przesądzenia w kodzie, obie z pomiarem, nie z gustu:

- **Czym rysować znacznik.** Trójkąty `▾`/`▸` czyta się najlepiej, ale wymagają
  kroju z tymi znakami — a klatka idzie przez Imagick z jednym, wybranym krojem.
  Zapasowo `[-]`/`[+]` albo `v`/`>`, wyłącznie ASCII.
- **Jaka rola nagłówka.** `Accent` istnieje i pasuje, ale jest już rolą nazwy
  pliku w opisie; osobna rola w `Role` znaczyłaby nowy kolor w palecie, a paleta
  jest liczona (D37).

## Poza zakresem

- **Sekcje zagnieżdżone** — sekcja w sekcji. Nie ma dziś użytkownika, a wciąga
  rachunek wcięć i przewijania na drugi poziom.
- **Trwałość zwinięcia** — stan sekcji nie przeżywa restartu i nie wchodzi do
  konfiguracji.
- **Animacja zwijania** — klatka jest przerysowywana w całości; ruch kosztowałby
  klatki bez treści.
- **Zwijanie myszą** — aplikacja nie czyta dziś zdarzeń myszy.
- **Zmiana `ListView`** — jakakolwiek.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Presentation/Ui/Component/Section.php` | Presentation | Nowy — nagłówek, znacznik, wiersze, `height()`. |
| `Presentation/Ui/Component/SectionList.php` | Presentation | Nowy — spłaszczenie sekcji, okno przewijania, suwak. |
| `Presentation/Ui/SectionState.php` | Presentation | Nowy — zwinięcia po kluczu, kursor, `useContext()`. |
| `Presentation/Cli/Screen/HelpScreen.php` | Presentation | Zakładka „Klawisze” na sekcjach; wiązania zwijania. |
| `lang/pl.php`, `lang/en.php` | Napisy | `help.key.collapse`, `help.key.expand`. |
| `Infrastructure/Diagnostics/ScenarioFactory.php` | Infrastructure | Scenariusz „sekcje” do `bin/render-bench`. |
| `docs/architecture.md` | Dokumentacja | Sekcja jako komponent; druga klasa stanu między klatkami i reguła jej własności. |
| `.claude/skills/light-manager-conventions/SKILL.md` | Dokumentacja | To samo w skrócie operacyjnym — **w tym samym kroku**. |
| testy | Testy | Wysokość zwiniętej i rozwiniętej, spłaszczenie kolejności, przewijanie w poprzek granicy sekcji, suwak przy zmianie zwinięcia, klucz sekcji przeżywający zniknięcie, pusty prostokąt. |

## Rozstrzygnięcia wykonawcze ze startu kroku (2026-08-10)

Cztery pytania rozstrzygnięte przez użytkownika przed otwarciem edytora, plus
piąte, które przy okazji rozstrzygnął kod.

| # | Pytanie | Wybór |
|---|---|---|
| 1 | Po czym chodzi kursor | **Po nagłówkach sekcji** |
| 2 | Znacznik zwinięcia | **Duże trójkąty `▼`/`▶`** (U+25BC, U+25B6) |
| 3 | Rola nagłówka | **Istniejący `Accent`** — paleta nietknięta |
| 4 | Okno przewijania `SectionList` | **Z zewnątrz, jak `ListView`** |
| 5 | Stan początkowy zakładki „Sterowanie” | **Rozwinięta**; zwinięcie przeżywa `Esc`, a `reset()` sprowadza tylko kursor |

Pytanie o znacznik (nr 2) rozstrzygnięto **pomiarem, nie założeniem**, tak jak
kazał plan: renderer wybiera `DejaVu-Sans-Mono`, a próba rasteryzacji pokazała, że
rysują się w nim wszystkie kandydujące znaki — `▾`, `▸`, `▼`, `▶`, `►`, `v`, `>`
oraz `[-]`/`[+]`. Wybór był więc kwestią wyglądu, a nie dostępności.

## Odstępstwa od planu

Cztery, każde z powodem.

1. **`Section` jest daną, a nie komponentem.** Plan zapowiadał `Section
   implements ComponentInterface` z `height()` i `draw()`. Otwarcie edytora
   pokazało, że `draw()` nie miałby wołającego: sekcje przewijają się **jak jedna
   lista**, więc wycinanie okna musi widzieć wiersze wszystkich sekcji naraz,
   a sekcja rysująca się we własnym prostokącie nie umiałaby powiedzieć, że
   zaczyna się trzy wiersze nad krawędzią okna. Podział wyszedł więc taki sam, jak
   istniejąca para `ListRow` (dana) i `ListView` (komponent) — czyli zgodnie
   z konwencją, którą projekt już ma. Reguła 13 („żaden komponent bez prawdziwego
   użytkownika”) obowiązuje tym samym także wstecz: `Section::draw()` byłoby
   martwym kodem od pierwszego dnia.
2. **Klawiszem zwijania jest `Enter`, a nie `←`/`→`.** W oknie pomocy strzałki
   poziome są zajęte przez zmianę zakładki — od kroku 20, gdy zakładek zrobiło się
   więcej niż dwie. Odrzucono przeniesienie zmiany zakładki na `Tab`, bo `Tab`
   należy do uzupełniania w oknie komend, a krok 22 nie ma mandatu na przestawianie
   klawiszy poza własnym zakresem.
3. **Wiązania rdzenia dostały nagłówek** (`help.section.global`, „Wszędzie”).
   Do kroku 22 stały na górze spisu bez etykiety; sekcja bez etykiety nie ma czego
   pokazać obok znacznika, a zostawienie ich poza sekcjami znaczyłoby, że jedyna
   grupa, której nie da się zwinąć, jest tą pierwszą. To **jedyny nowy napis
   widoczny w interfejsie** poza opisem klawisza.
4. **Okno przewijania podąża za sekcją dwoma wywołaniami `keepVisible()`.**
   Pierwsze ściąga okno do **końca** sekcji pod kursorem, drugie pilnuje jej
   **nagłówka** i wygrywa. Bez pierwszego rozwinięta sekcja pokazywałaby sam
   nagłówek, a jej treść zostawałaby pod dolną krawędzią; bez drugiego kursor
   wyjeżdżałby z okna przy sekcji wyższej od panelu.

**Czego nie zrobiono:** sekcja wyższa od panelu ma niedostępny koniec, bo kursor
chodzi po nagłówkach i nie ma klawisza przewijania w środku sekcji (`PgUp`/`PgDn`
są rozpoznawane przez parser od kroku 06, ale nie używa ich żaden ekran). Przy
dzisiejszych sekcjach — najdłuższa ma sześć wierszy — nie da się tego zobaczyć.
Do rozstrzygnięcia w kroku 25, gdzie sekcje opisu pliku mogą być dłuższe.

## Kryteria ukończenia

- Ekran składa cztery grupy po kilka wierszy z `Section` i `SectionList`,
  **nie pisząc rachunku przewijania** — sprawdza to zakładka „Klawisze”.
- Zwinięcie sekcji zmienia liczbę wierszy, a suwak i okno przewijania nadążają
  za tą zmianą w tej samej klatce.
- Stan zwinięcia przeżywa zniknięcie i powrót sekcji, bo kluczem jest napis.
- `ListView`, `ListRow` i `ScrollWindow` **nietknięte**; jeśli któryś się zmienił,
  dziennik mówi który i dlaczego.
- Klatka zmierzona `bin/render-bench` i rozliczona „przed i po” — również wtedy,
  gdy wynik jest niekorzystny.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.
- `docs/architecture.md` i `SKILL.md` opisują sekcję i drugą klasę stanu między
  klatkami — zgodnie ze sobą.

## Pomiar

Wzorce: [2026-08-10-po-kroku-21.json](../../pomiary/2026-08-10-po-kroku-21.json)
i [2026-08-10-po-kroku-22.json](../../pomiary/2026-08-10-po-kroku-22.json).

| Scenariusz | Przed | Po | Zmiana |
|---|---|---|---|
| puste płótno | 6,8 ms | 6,6 ms | −3,9% |
| sam tekst | 13,0 ms | 10,9 ms | −15,8% |
| same ramki | 11,4 ms | 10,6 ms | −7,2% |
| ramki z tekstem | 19,0 ms | 17,0 ms | −10,7% |
| zaznaczenie | 20,4 ms | 18,8 ms | −8,1% |
| suwak | 16,6 ms | 13,6 ms | −18,3% |
| klatka z miniaturą | 29,3 ms | 26,2 ms | −10,8% |
| klatka z okienkiem | 24,6 ms | 21,9 ms | −11,0% |
| okno komend | 30,3 ms | 27,2 ms | −10,2% |
| **zwijane sekcje** | — | **16,7 ms** | nowy scenariusz |

**Bez regresji powyżej progu.**

**Tabelę trzeba czytać z zastrzeżeniem, bo inaczej obiecywałaby coś, czego nie
mierzy.** Spadki rzędu 10–18% **nie są zasługą tego kroku** — pochodzą
z maszyny. Wzorzec kroku 21 powstał na hoście obciążonym, ten przebieg na
zwolnionym, i widać to po rozrzucie: „sam tekst” miał wtedy 11,1–13,4 ms, a teraz
10,5–11,5 ms. Krok 22 nie tknął ani `ListView`, ani `ListRow`, ani `ScrollWindow`,
więc żaden z tych dziewięciu scenariuszy nie przechodzi przez nowy kod i **nie ma
prawa** zmienić się z jego powodu. Prawdziwą treścią pomiaru jest właśnie to:
zmiana nie dotknęła ani jednego prymitywu docierającego do renderera.

Stąd wniosek zapisany na przyszłość i przeniesiony do `SKILL.md` (reguła 17):
**wzorzec z obciążonej maszyny psuje każde następne porównanie**, bo różnica
środowiska udaje różnicę kodu. Za pierwszym razem `--save` odmówił zapisu
czterokrotnie i miał rację.

Nowy scenariusz `sections` kosztuje 16,7 ms — między „same ramki” (10,4 ms)
a „ramki z tekstem” (17,8 ms), i to jest jego treść: sekcje mieszane (co trzecia
zwinięta) rysują mniej wierszy niż pełna lista, a znaczniki spoza ASCII nie
kosztują nic mierzalnego ponad zwykłą literę.

## Dziennik realizacji

**2026-08-10 — krok wykonany.**

Co powstało:

- **`Presentation/Ui/Component/Section`** — dana opisująca sekcję: klucz, etykieta,
  wiersze i stan zwinięcia, wraz z `height()` i `lines()`.
- **`Presentation/Ui/Component/SectionList`** — komponent: spłaszcza sekcje do
  wierszy, wycina okno, przelicza kursor na położenie **w wycinku** i oddaje
  rysowanie `ListView`owi. Statyczne `rowCount()` i `rowOf()` są dla ekranu, bo to
  on karmi `ScrollWindow`.
- **`Presentation/Ui/SectionState`** — druga w projekcie klasa pamiętająca coś
  między klatkami. Zwinięcia pod kluczem-napisem, kursor jako numer,
  `useContext()` jak w `ScrollWindow`.
- **Zakładka „Sterowanie” w oknie pomocy** przestawiona na sekcje — pierwszy
  prawdziwy użytkownik, zgodnie z regułą 13. Cztery sekcje: „Wszędzie”,
  „USTAWIENIA”, „POMOC”, „KOMENDY”.
- **Scenariusz `sections`** w `bin/render-bench` wraz z etykietami w obu katalogach.

Czego **nie** ruszono, i to jest główny wynik kroku: **`ListView`, `ListRow`
i `ScrollWindow` są co do znaku takie, jak przed nim.** Sekcje weszły obok listy,
a nie w nią — a to była jedyna droga, przy której trzej dzisiejsi użytkownicy
`ListView` (przeglądarka, ustawienia, pomoc) nie płacą za funkcję, której dwóch
z nich nie chce.

**Co sprawdziło się samo z siebie i warto to zapisać.** Podział na daną
i komponent nie był decyzją projektową tego kroku — **wymusił go kształt
przewijania**. Plan zakładał `Section` jako komponent i dopiero pisanie `draw()`
pokazało, że nie ma dla niego wołającego. Konwencja `ListRow`/`ListView`, która
w projekcie istniała od kroku 18, okazała się odpowiedzią na pytanie, którego
wtedy nikt nie zadał.

**Testy:** 33 nowe (`SectionTest`, `SectionStateTest`, `HelpScreenSectionsTest`),
razem 852 zielone. Pierwsze dwa pilnują, żeby `height()` nie rozjechało się
z `lines()` — bo to jest ten błąd, który widać dopiero po przewinięciu; trzeci
patrzy na spis tak, jak patrzy użytkownik: przez klatkę i przez klawisze.
