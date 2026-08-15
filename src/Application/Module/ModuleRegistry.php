<?php

declare(strict_types=1);

namespace LightManager\Application\Module;

/**
 * Zestaw modułów tego uruchomienia: kto wchodzi, kto odpada i dlaczego.
 *
 * Rejestr jest zwykłym obiektem, nie Singletonem — składa go `Bootstrap`, tak
 * samo jak ekrany i rejestr komend. Leży **w całości w `Application`** (P15)
 * i operuje wyłącznie na danych: identyfikatorach, skrótach i podprzestrzeni
 * `modules` konfiguracji. O ekranach nie wie nic i wiedzieć nie może — dlatego
 * skrót jest `ModuleShortcut`iem, a nie `KeyBinding`iem.
 *
 * Kolejność sprawdzania jest wymuszona: najpierw odsiew wyłączonych, potem
 * tożsamość, potem środowisko (krok 48), na końcu skrót. Moduł wyłączony
 * **nie jest sprawdzany**, bo
 * wyłączenie może kolizję tylko usunąć, nigdy stworzyć — i dzięki temu wyłączenie
 * modułu z zabronioną literą jest realną drogą wyjścia z sytuacji, a nie
 * kosmetyką.
 *
 * Moduł kolizyjny odpada **w całości**, a nie tylko jego skrót: moduł, do którego
 * nie da się wejść, a który dokłada zakładki do konfiguracji i pomocy, myliłby
 * bardziej, niż pomagał.
 *
 * Od kroku 21 jeden z modułów bywa **modułem ostatniej szansy** — tym, do którego
 * aplikacja wraca, gdy moduł domyślny okaże się niedostępny. Rejestr sprawdza go
 * pierwszym (więc przy kolizji skrótu odrzucony zostaje ten drugi) i nie pozwala
 * go wyłączyć. Jego identyfikator podaje **z zewnątrz** `Bootstrap`: dzięki temu
 * `Application/Module` nie zna nazwy żadnego konkretnego modułu, a test może
 * podstawić inny.
 */
final class ModuleRegistry
{
    /**
     * Litery, których skrót modułu wziąć nie może — każda z konkretnego powodu.
     *
     * `c` (`0x03`) i `z` (`0x1A`) są sygnałami i nie docierają do aplikacji, bo
     * tryb surowy **zostawia `isig` włączone** (P10). Pozostałe cztery przychodzą
     * ze STDIN jako dokładnie ten sam bajt, co klawisz nazwany: `h` to Backspace,
     * `i` to Tab, a `j` i `m` to Enter. Rozdzielić ich nie sposób bez
     * rozszerzonego protokołu klawiatury — a ten jest poza zakresem kroku 20.
     *
     * Lista stoi tutaj, w jednym miejscu, i jest tym samym źródłem dla
     * wykrywania kolizji i dla testu.
     */
    public const FORBIDDEN_CHARACTERS = ['c', 'h', 'i', 'j', 'm', 'z'];

    /** Klucz przełącznika „włączony” w podprzestrzeni `modules.<id>`. */
    public const ENABLED_KEY = 'enabled';

    private const ID_PATTERN = '/^[a-z][a-z0-9-]*$/';

    /** @var list<ModuleInterface> wszystko, co zadeklarował `Bootstrap` */
    private array $declared = [];

    /** @var array<string, ModuleInterface> */
    private array $accepted = [];

    /** @var array<string, ModuleInterface> litera skrótu → moduł */
    private array $shortcuts = [];

    /** @var array<string, ModuleRejection> */
    private array $rejections = [];

    /**
     * Kolejność **deklaracji** i kolejność **sprawdzania** to dwie różne rzeczy.
     * Spis na zakładce „Moduły” idzie w tej pierwszej, bo tak wpisano je
     * w `Bootstrapie`; wpuszczanie zaczyna się od modułu ostatniej szansy, bo to
     * on ma wygrać każdą kolizję skrótu.
     *
     * @param list<ModuleInterface>                         $modules
     * @param array<string, array<string, bool|int|string>> $configuration podprzestrzeń `modules` konfiguracji
     * @param string|null                                   $lastResort    identyfikator modułu ostatniej szansy;
     *                                                                     `null` — żaden moduł nie jest uprzywilejowany
     */
    public function __construct(
        array $modules,
        private readonly array $configuration = [],
        private readonly ?string $lastResort = null,
    ) {
        $this->declared = $modules;

        foreach (self::checkedFirst($modules, $lastResort) as $module) {
            $this->admit($module);
        }
    }

    /** Identyfikator modułu, do którego aplikacja wraca; `null`, gdy nikt taki nie został wskazany. */
    public function lastResort(): ?string
    {
        return $this->lastResort;
    }

    /**
     * Czy modułu nie wolno wyłączyć.
     *
     * Przełącznik na zakładce „Moduły” stoi dla niego tak samo jak dla reszty, ale
     * jest zablokowany wraz z powodem — dokładnie jak dla modułu odrzuconego.
     * Wyłączony moduł ostatniej szansy zostawiłby aplikację bez ekranu przy
     * pierwszym błędzie w kluczu `startupModule`.
     */
    public function isEssential(string $id): bool
    {
        return $this->lastResort !== null && $id === $this->lastResort;
    }

    /**
     * @param list<ModuleInterface> $modules
     *
     * @return list<ModuleInterface>
     */
    private static function checkedFirst(array $modules, ?string $lastResort): array
    {
        if ($lastResort === null) {
            return $modules;
        }

        $first = [];
        $rest = [];

        foreach ($modules as $module) {
            if ($module->id() === $lastResort) {
                $first[] = $module;

                continue;
            }

            $rest[] = $module;
        }

        return [...$first, ...$rest];
    }

    /**
     * Litery, które skrót modułu wziąć może — dopełnienie listy zabronionych.
     *
     * @return list<string>
     */
    public static function allowedCharacters(): array
    {
        return array_values(array_diff(range('a', 'z'), self::FORBIDDEN_CHARACTERS));
    }

    /** @return list<ModuleInterface> wszystkie zadeklarowane — także wyłączone i odrzucone */
    public function declared(): array
    {
        return $this->declared;
    }

    /** @return list<ModuleInterface> moduły włączone i poprawne, w kolejności deklaracji */
    public function accepted(): array
    {
        return array_values($this->accepted);
    }

    /** @return list<ModuleRejection> */
    public function rejections(): array
    {
        return array_values($this->rejections);
    }

    public function rejectionOf(string $id): ?ModuleRejection
    {
        return $this->rejections[$id] ?? null;
    }

    public function find(string $id): ?ModuleInterface
    {
        return $this->accepted[$id] ?? null;
    }

    /** @return array<string, ModuleInterface> litera skrótu → moduł, który ją zajął */
    public function shortcuts(): array
    {
        return $this->shortcuts;
    }

    /**
     * Czy moduł jest włączony w konfiguracji. Brak wpisu znaczy „włączony”:
     * moduł wbudowany w repozytorium ma działać od razu po dopisaniu do listy
     * w `Bootstrap`, bez ceremonii w pliku konfiguracyjnym.
     */
    public function isEnabled(string $id): bool
    {
        if ($this->isEssential($id)) {
            return true;
        }

        $value = $this->configuration[$id][self::ENABLED_KEY] ?? true;

        return is_bool($value) ? $value : true;
    }

    private function admit(ModuleInterface $module): void
    {
        $id = $module->id();

        if (!$this->isEnabled($id)) {
            return;
        }

        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            $this->reject($id, 'module.rejected.id');

            return;
        }

        if (isset($this->accepted[$id])) {
            $this->reject($id, 'module.rejected.duplicate');

            return;
        }

        // Piąty powód i pierwszy niezależny od autora modułu (krok 48, D87 nr 11).
        // Stoi **przed** sprawdzeniem skrótu umyślnie: moduł, który i tak nie
        // wejdzie, nie ma prawa zająć litery, bo zabrałby ją komuś, kto by
        // działał. Powód jest kluczem podanym przez moduł, a nie stałą rdzenia —
        // rdzeń nie wie, czego brakuje, i wiedzieć nie ma po co.
        if ($module instanceof RequiresEnvironment) {
            $reason = $module->unavailableReason();

            if ($reason !== null) {
                $this->reject($id, $reason);

                return;
            }
        }

        $shortcut = $module->shortcut();

        if ($shortcut !== null && !self::isUsable($shortcut)) {
            $this->reject($id, 'module.rejected.character');

            return;
        }

        if ($shortcut !== null && isset($this->shortcuts[$shortcut->character])) {
            $this->reject($id, 'module.rejected.taken');

            return;
        }

        $this->accepted[$id] = $module;

        if ($shortcut !== null) {
            $this->shortcuts[$shortcut->character] = $module;
        }
    }

    /**
     * Skrót bez `Ctrl` odpada razem z literą zabronioną, bo dziś nie ma jak
     * zaistnieć: rdzeń nie rezerwuje ani jednej litery, ale gołe litery należą do
     * ekranów, a te nie mają jak się o nie umówić z modułami.
     */
    private static function isUsable(ModuleShortcut $shortcut): bool
    {
        if (!$shortcut->ctrl) {
            return false;
        }

        return in_array($shortcut->character, self::allowedCharacters(), true);
    }

    private function reject(string $id, string $reasonKey): void
    {
        $this->rejections[$id] = new ModuleRejection($id, $reasonKey);
    }
}
