<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/**
 * Co dokładnie znajdzie się na mierzonej klatce.
 *
 * Scenariusze są dobrane tak, żeby koszt dawało się **odjąć**: `Text` bez
 * chromu, `Chrome` bez wierszy listy, `ChromeWithText` jako pełna klatka. Krok
 * 13 pomylił się właśnie na braku takiego rozdziału — koszt wygładzania podano
 * łącznie dla tekstu i obrysów, więc obrysy wyłączono niepotrzebnie (D27).
 *
 * Wartość przypadku jest tokenem wiersza poleceń (`--scenariusz=text`), a nie
 * napisem dla użytkownika — ten idzie przez katalog pod `labelKey()`.
 */
enum Scenario: string
{
    /**
     * Ile prac tłowych prowadzi scenariusz `background-many` (krok 51).
     *
     * Liczba jest **domyślną granicą z ustawień** (`Settings::DEFAULT_BACKGROUND_JOBS`)
     * przepisaną tutaj, a nie z niej czytaną: pomiar ma być powtarzalny między
     * maszynami i między przebiegami, a konfiguracja użytkownika jest zmienna.
     * Stała powtórzona świadomie — rozjazd z ustawieniami zmieniłby wyłącznie
     * to, jak ostry jest przypadek najgorszy, a nie poprawność pomiaru.
     */
    private const MANY_BACKGROUND_JOBS = 8;

    /** Koszt bazowy: alokacja płótna, tło, kwantyzacja, kodowanie. */
    case Empty = 'empty';

    /** Sama rasteryzacja liter, bez jednej kreski chromu. */
    case Text = 'text';

    /** Cztery panele z łukami, nawiasami i etykietami — bez treści. */
    case Chrome = 'chrome';

    /** Pełna klatka przeglądarki plików: panele plus wiersze listy. */
    case ChromeWithText = 'chrome-text';

    /** Jak `Text`, ale każdy wiersz zaznaczony — różnica to koszt pasków. */
    case Selection = 'selection';

    /** Sama szyna z suwakiem, bez wierszy. */
    case Scrollbar = 'scrollbar';

    /** Pełna klatka z miniaturą w pasie podglądu i paletą 256 kolorów. */
    case Thumbnail = 'thumbnail';

    /** Pełna klatka z okienkiem modalnym narysowanym na wierzchu. */
    case Popup = 'popup';

    /** Pełna klatka z otwartym oknem komend: pasek podpowiedzi i pole wpisywania. */
    case Command = 'command';

    /**
     * Pełna klatka z listą zwijanych sekcji zamiast płaskiej listy (krok 22).
     *
     * Mierzy to, czego `chrome-text` nie mierzy: nagłówki w akcencie i znaczniki
     * spoza ASCII, czyli **litery, których nie ma w żadnym innym scenariuszu**.
     * Znak spoza podstawowej strony kodowej rasteryzuje się osobno i osobno ląduje
     * w pamięci podręcznej wierszy — więc gdyby kosztował, ma to być widać tutaj.
     */
    case Sections = 'sections';

    /**
     * Pełna klatka wypełniona paskami postępu — oba tryby naraz (krok 23).
     *
     * Mierzy to, czego nie mierzy żaden inny scenariusz: **wypełnione prostokąty
     * o zmiennej szerokości** wraz z napisem pociętym na kolumnie wypełnienia.
     * Każdy pasek to prostokąt inny niż w poprzedniej klatce, więc pamięć
     * podręczna wierszy nie ma tu czego trafić — i to jest właśnie treść pomiaru:
     * ile kosztuje element, który z założenia zmienia się co klatkę.
     */
    case Progress = 'progress';

    /**
     * Klatka podzielona na dwa panele plików (krok 24).
     *
     * Mierzy to, czego nie mierzy `chrome-text`: **obwódki narysowane na
     * płaszczyźnie treści**. Przy jednym panelu cała oprawa leży w płaszczyźnie
     * spodniej i renderer podaje ją z pamięci, więc kosztuje zero; przy podziale
     * rysuje ją ekran, bo tylko on wie, który panel jest czynny — i wtedy powstaje
     * na nowo w każdej klatce. Różnica wobec `chrome-text` jest ceną tej decyzji.
     */
    case Split = 'split';

    /**
     * Ta sama klatka, co `chrome-text`, ale **z żywym procesem potomnym obok**
     * (krok 26).
     *
     * Pierwszy scenariusz w tym narzędziu, który sięga poza PHP, i jedyny, w
     * którym treść klatki nie jest tematem: co do prymitywu jest bliźniaczo
     * równa `chrome-text`, więc **różnica między nimi jest w całości ceną pracy
     * tłowej**. To jest ta sama reguła rozdzielności, którą kierują się pozostałe
     * scenariusze — tylko że rzeczą odejmowaną nie jest tu element interfejsu,
     * lecz sąsiad pętli.
     *
     * Potomek **milczy przez cały pomiar** i to nie jest uproszczenie, tylko
     * wierność: `du` nie mówi o sobie nic, aż skończy, więc dokładnie tak wygląda
     * te cztery sekundy, o które w tym kroku chodzi. Mierzony jest zatem ten
     * koszt, który aplikacja ponosi naprawdę — dwa puste potoki i pytanie o stan
     * procesu, trzydzieści razy na sekundę.
     */
    case Background = 'background';

    /**
     * Ta sama klatka, co `background`, ale z **kompletem prac tłowych** obok
     * (krok 51).
     *
     * Rozlicza się **w parze z `background`**, nie samodzielnie, i ta para jest
     * całym powodem jego istnienia: różnica między nimi to cena, którą pętla
     * płaci za rozbudowę portu z „jednej pracy naraz” do kilku — jedno przejście
     * pompowania po ośmiu potomkach zamiast po jednym, plus siedem doglądań.
     *
     * Liczba prac jest **domyślną granicą z ustawień**, a nie wartością dobraną
     * pod ładny wynik: mierzymy przypadek najgorszy, jaki aplikacja dopuszcza
     * bez ruszania konfiguracji.
     */
    case BackgroundMany = 'background-many';

    /**
     * Pełna klatka listy plików o **czterech kolumnach** zamiast dwóch (krok 27).
     *
     * Mierzy dokładnie jedno: cenę kolumn. Treść jest ta sama, co w `chrome-text`,
     * i te same są panele — różni się liczba napisów w wierszu, bo rozdział
     * szerokości wypuszcza cztery `TextRun` tam, gdzie wcześniej wychodziły dwa.
     * Różnica między tymi scenariuszami jest więc kosztem rozdziału i dwóch
     * dodatkowych napisów na wiersz, a nie „ogólnym wrażeniem, że lista
     * zdrożała”.
     *
     * Osobny scenariusz jest tu potrzebny z konkretnego powodu: klucz pamięci
     * podręcznej wierszy (D34) buduje się z ich treści, a wiersz z datą i prawami
     * jest treścią dłuższą i **rzadziej powtarzalną** niż sama nazwa z rozmiarem.
     */
    case Columns = 'columns';

    /**
     * Panel wypełniony treścią pliku tekstowego, z zawijaniem (krok 29).
     *
     * Osobny scenariusz jest tu potrzebny z konkretnego powodu i nie jest nim
     * „nowy komponent”: **wiersze podglądu zmieniają się przy każdym
     * przewinięciu**, i to co do znaku, bo okno przesuwa się po pliku zamiast
     * wybierać wycinek z gotowej listy. Pamięć podręczna wierszy (D34) trafia
     * w nie przez to rzadziej niż w listę plików, gdzie ta sama nazwa wraca po
     * przewinięciu w tę i z powrotem.
     *
     * Treść jest wierszami kodu o **zmiennej długości**, bo to ona rozstrzyga
     * o kształcie pracy: wiersz krótszy od panelu daje jeden napis, dłuższy —
     * tyle napisów, na ile się zawinie. Różnica wobec `chrome-text` jest ceną
     * podglądu tekstu; numerów wierszy w niej nie ma, bo w aplikacji są domyślnie
     * wyłączone, a scenariusz ma mierzyć to, co widzi użytkownik.
     */
    case TextView = 'text-view';

    /**
     * Lista w kolumnach, w której **każdy wiersz ma dopasowanie** filtra
     * (krok 30).
     *
     * Przypadek najgorszy z możliwych i właśnie dlatego właściwy: pokazuje sufit
     * ceny podświetlenia, a nie jej wartość typową. Prawdziwy filtr trafia
     * zwykle w kilka wierszy z kilkudziesięciu.
     *
     * **Rozlicza się go w parze z `columns`, nie samodzielnie**, i to jest cała
     * konstrukcja tego pomiaru. Treść wierszy, kolumny i przewinięcie są tu co do
     * znaku takie same, jak tam; różni je wyłącznie ósmy prymityw postawiony raz
     * na wiersz. Różnica między dwiema liczbami jest więc **ceną podświetlenia**,
     * a `columns` odpowiada osobno na pytanie ważniejsze: czy lista bez filtra
     * zdrożała. Nie ma prawa, i to jest główne kryterium tamtego kroku.
     *
     * Fragment jest trzyznakowy i trafia w każdą nazwę dokładnie raz — tak, jak
     * przy wpisaniu trzech liter przez użytkownika. Jeden fragment znaczy też
     * jeden wpis w pamięci podręcznej bitmap, i tak właśnie zachowuje się filtr
     * w aplikacji: podświetla wszędzie ten sam napis.
     */
    case Highlight = 'highlight';

    /**
     * Pełna klatka **ekranu ustawień**: pasek zakładek, pozycje przełączane
     * i wybierane, wiersz czynności (krok 38).
     *
     * Domyka lukę zauważoną przy przeglądzie „element → scenariusz”: `Tabs`,
     * `Choice`, `Toggle`, `Button` i `Spacer` to **jedyne komponenty rdzenia,
     * których nie mierzył żaden scenariusz**, a stoją na ekranie odwiedzanym
     * przy każdej zmianie ustawień. Ekran pomocy i pasek filtra takiej luki nie
     * tworzą (powody zapisane w kroku 38, sekcja „Spis element → scenariusz”).
     *
     * **Rozlicza się w parze z `chrome-text`**: obwódki, wiersz ścieżki i pasek
     * stanu są tu co do prymitywu takie same, więc różnica między nimi jest
     * ceną **treści ekranu ustawień wobec listy plików** o tej samej liczbie
     * wierszy. Stąd pozycje wypełniają panel do końca, choć najdłuższa
     * prawdziwa zakładka ma ich dziesięć: mierzymy koszt pozycji **na wiersz**,
     * a przy ośmiu wierszach różnica utonęłaby w rozrzucie.
     */
    case Settings = 'settings';

    /**
     * Panel wypełniony **drzewem** o zmiennej głębokości (krok 31).
     *
     * Mierzy dokładnie to, czym drzewo różni się od listy, którą krok 27 zmierzył
     * już scenariuszem `columns`: **wcięcie i prowadnice gałęzi**. Każdy poziom
     * dokłada do wiersza znak spoza podstawowej strony kodowej (`│`, `├─`, `└─`),
     * a taki znak rasteryzuje się osobno i osobno ląduje w pamięci podręcznej
     * wierszy (D34) — więc jeśli prowadnice kosztują, ma to być widać tutaj. To ta
     * sama konstrukcja, co przy `sections`, tylko że tam znaki spoza ASCII stały
     * po jednym na wiersz, a nie po jednym na poziom.
     *
     * **Rozlicza się w parze z `sections`**, a nie z `chrome-text`, i to jest
     * wybór, nie przypadek: obydwa wypełniają ten sam prostokąt wierszami
     * rysowanymi przez `ListView`, obydwa mają w wierszu znak spoza ASCII i żaden
     * nie rysuje pasa ścieżki ani paska stanu. Różnicą jest **wyłącznie
     * przedrostek**, czyli dokładnie to, co ten scenariusz ma wycenić. Para
     * z `chrome-text` mierzyłaby przy okazji dwa pasy klatki, a para z `columns` —
     * różnicę między jednym napisem w wierszu a czterema.
     *
     * Wiersze mają zmienną głębokość i **wszystkie trzy kształty naraz** — gałąź
     * rozwiniętą, zwiniętą i liść — bo klucz pamięci podręcznej buduje się
     * z treści wiersza, a wiersz drzewa jest treścią rzadziej powtarzalną niż
     * sama nazwa: to samo `plik-03.txt` na dwóch różnych poziomach daje dwa różne
     * wiersze.
     */
    case Tree = 'tree';

    /**
     * Lista w kolumnach z **zaznaczeniem wielokrotnym** (krok 43).
     *
     * **Rozlicza się w parze z `columns`**, tą samą konstrukcją, co `highlight`:
     * treść wierszy, kolumny i przewinięcie są co do znaku takie same, więc
     * różnica między dwiema liczbami jest w całości ceną zaznaczenia. Różni je
     * dokładnie to, co dokłada zbiór — **piąta kolumna** ze znakiem spoza ASCII
     * i **druga rola napisu** w co trzecim wierszu.
     *
     * Osobny scenariusz jest tu potrzebny z powodu, który w tym narzędziu wraca
     * od kroku 22: klucz pamięci podręcznej wierszy (D34) buduje się z treści
     * **i roli**, więc ten sam wiersz zaznaczony i niezaznaczony to dwa różne
     * wpisy. Zaznaczenie zmienia się przy tym przy każdym naciśnięciu spacji,
     * czyli częściej niż cokolwiek innego na liście.
     *
     * Zaznaczone są **trzy pozycje z siedmiu**, a nie wszystkie: zbiór pełny
     * mierzyłby listę o jednej roli — czyli to samo, co `columns`, tylko innym
     * kolorem — a zbiór o jednym elemencie utopiłby różnicę w rozrzucie. Udział
     * bliski jednej trzeciej daje obie role w pamięci podręcznej naraz, czyli
     * stan, w którym lista naprawdę bywa; siódemka zamiast trójki bierze się
     * stąd, że katalogi wypadają co szósty wiersz — przy „co trzeciej” pozycji
     * **każdy** katalog byłby zaznaczony, a wzorzec nie pokazywałby, czym rola
     * zaznaczenia różni się od akcentu.
     */
    case Marked = 'marked';

    /**
     * Pełna klatka z **prostokątem zaznaczonym wskaźnikiem** (krok 56).
     *
     * **Rozlicza się w parze z `chrome-text`**, i to jest tu para najprostsza
     * z możliwych: klatka jest co do prymitywu ta sama, a różnica między dwiema
     * liczbami to w całości cena **czwartej płaszczyzny** — drugiego przejścia
     * po prymitywach (warstwa tekstowa) plus kilku `TextMark`ów pokrywających
     * zaznaczone wiersze.
     *
     * Zaznaczenie obejmuje **pięć wierszy na pełnej szerokości panelu**, bo tyle
     * bierze przeciągnięcie ręką przez listę plików — i dlatego, że miara kroku
     * mówi o dokładnie pięciu wierszach. Sufitem nie jest: prostokąt da się
     * rozciągnąć na całą klatkę, ale wtedy scenariusz mierzyłby przemalowanie
     * okna, a nie czynność, którą ktokolwiek wykonuje.
     */
    case Marquee = 'marquee';

    /**
     * Spis środowisk Dockera: tabela **z nagłówkiem kolumn** i trzema rolami
     * wierszy naraz (krok 58).
     *
     * **Rozlicza się w parze z `columns`**, tą samą konstrukcją, co `highlight`
     * i `marked`: ten sam prostokąt, ta sama `Table`, to samo przewinięcie.
     * Różnią go trzy rzeczy i wszystkie trzy są treścią pomiaru: **wiersz
     * nagłówka** (jedyny w aplikacji mierzony scenariuszem — spisy hostów
     * i środowisk rysują go, a żaden scenariusz go dotąd nie miał), **pięć
     * kolumn** zamiast czterech oraz **trzy role wierszy naraz** (bieżące
     * `Marked`, przysłonięty `Muted`, reszta `Text`) — a klucz pamięci
     * podręcznej wierszy buduje się z treści i roli (D34).
     */
    case Environments = 'environments';

    /** @return list<self> kolejność wydruku: od najtańszego do najbogatszego */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * @param list<string> $names
     *
     * @return list<self>
     *
     * @throws DiagnosticsException gdy któraś nazwa nie ma pokrycia w enumie
     */
    public static function fromNames(array $names): array
    {
        $scenarios = [];

        foreach ($names as $name) {
            $scenario = self::tryFrom($name);

            if ($scenario === null) {
                throw DiagnosticsException::forUnknownScenario($name);
            }

            $scenarios[] = $scenario;
        }

        return $scenarios;
    }

    public function labelKey(): string
    {
        return 'bench.scenario.' . $this->value;
    }

    /** Czy scenariusz wymaga układu panelowego (a nie gołego płótna). */
    public function needsChrome(): bool
    {
        return match ($this) {
            self::Chrome, self::ChromeWithText, self::Thumbnail, self::Popup,
            self::Command, self::Sections, self::Progress, self::Split,
            self::Background, self::BackgroundMany, self::Columns, self::TextView,
            self::Highlight, self::Settings, self::Tree, self::Marked,
            self::Marquee, self::Environments => true,
            default => false,
        };
    }

    /**
     * Przy ilu uruchomionych procesach potomnych ma toczyć się pomiar.
     *
     * Każdy kosztuje przebieg jeden proces — uruchamiany przed rozgrzewką
     * i ubijany po ostatniej próbce — oraz jedno doglądanie na klatkę; wszystkie
     * razem kosztują jedno przejście pompowania, bo pętla przechodzi po nich
     * raz na klatkę niezależnie od tego, ile ich jest.
     *
     * Do kroku 51 odpowiedź była **dwustanowa**, bo port prowadził jedną pracę
     * naraz i „kilka prac” nie było stanem osiągalnym. Odkąd jest, liczba stała
     * się osią pomiaru: różnica między `background` a `background-many` jest
     * w całości ceną rozbudowy portu, płaconą w klatce.
     */
    public function backgroundJobs(): int
    {
        return match ($this) {
            self::Background => 1,
            self::BackgroundMany => self::MANY_BACKGROUND_JOBS,
            default => 0,
        };
    }
}
