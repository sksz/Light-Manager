<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Role;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Application\UseCase\ExpandBranchUseCase;
use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Presentation\Component\EntrySize;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Component\TreeNode;
use LightManager\Presentation\Ui\ScrollWindow;
use LightManager\Presentation\Ui\TreeState;

/**
 * Drzewo katalogów jednego panelu: co jest rozwinięte, co już przeczytane i jak
 * to wygląda po spłaszczeniu.
 *
 * **Spłaszczanie należy do modułu, a nie do komponentu** — to jest główne
 * rozstrzygnięcie planu kroku 31 i wynika wprost z reguły 1 (D42). `TreeView`
 * dostaje gotową listę węzłów, bo komponent schodzący sam po gałęziach musiałby
 * wiedzieć, skąd biorą się dzieci; a biorą się z odczytu katalogu, czyli z rzeczy,
 * której rdzeń nie zna. Ta sama granica, którą krok 22 postawił między
 * `SectionList` a ekranem.
 *
 * **Korzeniem jest katalog panelu, a nie własny odczyt.** Drzewo bierze
 * `BrowserState::directory()` — czyli ten sam obiekt, który widzi lista — więc
 * przełączenie widoku klawiszem nie kosztuje ani jednego sięgnięcia na dysk,
 * a zawężenie filtrem (krok 30) obowiązuje w drzewie na jego pierwszym poziomie.
 * Drugi odczyt tego samego katalogu dałby panelowi dwie prawdy o jednym miejscu.
 *
 * **Odczyt gałęzi jest na żądanie i najwyżej jeden na klatkę.** Rozwinięcie
 * klawiszem czyta od razu, bo użytkownik właśnie o to poprosił i kosztuje to
 * tyle, co `Enter` w liście. Gałęzie, które trzeba **odtworzyć** — bo panel
 * wrócił do katalogu, w którym coś już było rozwinięte, albo bo zmieniła się
 * widoczność wpisów ukrytych — dochodzą po jednej na takt (D46). Dziesięć
 * odczytów naraz nie mieści się w klatce, a dziesięć klatek to jedna trzecia
 * sekundy, w czasie której drzewo dosypuje się na oczach użytkownika zamiast
 * zaciąć aplikację.
 */
final class BrowserTree
{
    private readonly TreeState $state;

    /**
     * Okno przewijania **własne**, a nie to od listy.
     *
     * Panel ma odtąd dwa widoki na jeden katalog i każdy przewija się po czym
     * innym: lista po wpisach, drzewo po węzłach wszystkich rozwiniętych gałęzi
     * naraz. Wspólne okno przenosiłoby wycinek z jednego rachunku na drugi, więc
     * przełączenie widoku klawiszem zaczynałoby oglądanie w przypadkowym miejscu.
     */
    private readonly ScrollWindow $window;

    /**
     * Odczytane gałęzie: ścieżka → zawartość.
     *
     * Pamięć jest **trwalsza od korzenia** i to jest świadome: użytkownik, który
     * wszedł katalog niżej i wrócił, dostaje swoje drzewo z powrotem bez ani
     * jednego odczytu. Ceną jest to, co plan wyklucza wprost — drzewo pokazuje
     * to, co przeczytało, a nie to, co w tej chwili leży na dysku.
     *
     * @var array<string, Directory>
     */
    private array $contents = [];

    /** @var list<TreeNode> */
    private array $nodes = [];

    /** @var list<string> */
    private array $keys = [];

    private bool $flattened = false;

    /** Katalog, z którego wyrosło ostatnie spłaszczenie — porównywany **tożsamością**. */
    private ?Directory $root = null;

    private ?int $limit = null;

    private bool $hidden = false;

    /** Gałąź rozwinięta, ale jeszcze nieprzeczytana — do odczytania w następnym takcie. */
    private ?string $pending = null;

    public function __construct(
        private readonly BrowserState $pane,
        private readonly ExpandBranchUseCase $branches,
        private readonly LoopState $loop,
        private readonly TranslatorPort $translator,
        int $scrollMargin = 0,
    ) {
        $this->state = new TreeState();
        $this->window = new ScrollWindow($scrollMargin);
    }

    /**
     * Zapomnienie odczytanych gałęzi — po zmianie na dysku, o której drzewo nie
     * ma jak wiedzieć (krok 41).
     *
     * Pamięć gałęzi jest z założenia trwalsza od korzenia i mówi o tym, co
     * przeczytano, a nie o tym, co leży na dysku. Dopóki dysk zmieniał się wyłącznie
     * cudzą ręką, było to uczciwe („zmiana spoza aplikacji wymaga wejścia na nowo”);
     * odkąd zmienia go **ta sama aplikacja**, drzewo pokazujące usunięty katalog
     * byłoby po prostu nieprawdą.
     *
     * Gałęzie wracają po jednej na takt (D46), więc zapomnienie kosztuje tyle
     * odczytów, ile węzłów jest rozwiniętych — i ani jednego więcej.
     */
    public function forgetBranches(): void
    {
        $this->contents = [];
        $this->flattened = false;
    }

    public function state(): TreeState
    {
        return $this->state;
    }

    public function window(): ScrollWindow
    {
        return $this->window;
    }

    /** @return list<TreeNode> wszystkie widoczne węzły, w kolejności wierszy */
    public function nodes(): array
    {
        $this->refresh();

        return $this->nodes;
    }

    public function count(): int
    {
        $this->refresh();

        return count($this->nodes);
    }

    /** Numer węzła pod kursorem — wejście do `TreeView` i do suwaka. */
    public function cursorIndex(): ?int
    {
        $this->refresh();

        return $this->state->indexIn($this->keys);
    }

    public function cursorNode(): ?TreeNode
    {
        $index = $this->cursorIndex();

        return $index === null ? null : $this->nodes[$index];
    }

    /** Ruch kursora po widocznych węzłach; `0` znaczy „ustaw się w granicach drzewa”. */
    public function moveBy(int $delta): void
    {
        $this->refresh();
        $this->state->moveBy($delta, $this->keys);
    }

    /**
     * Rozwinięcie gałęzi wraz z odczytem jej zawartości.
     *
     * Oddaje `false`, gdy limit głębokości na to nie pozwala — wołający ma wtedy
     * co powiedzieć użytkownikowi. Cichy brak reakcji byłby gorszy: klawisz, który
     * czasem działa, a czasem nie, czyta się jak usterka.
     */
    public function expand(TreeNode $node): bool
    {
        if (!$this->allows($node->depth())) {
            return false;
        }

        $this->state->expand($node->key);
        $this->read($node->key);
        $this->flattened = false;

        return true;
    }

    /** Zwinięcie gałęzi; kursor przenosi na nią `TreeState` (rozstrzygnięcie kroku). */
    public function collapse(TreeNode $node): void
    {
        $this->state->collapse($node->key);
        $this->flattened = false;
    }

    /** Kursor na pierwsze dziecko rozwiniętej gałęzi — czyli na wiersz zaraz pod nią. */
    public function focusChild(TreeNode $node): void
    {
        $index = $this->cursorIndex();

        if ($index === null || !isset($this->nodes[$index + 1])) {
            return;
        }

        if ($this->nodes[$index + 1]->depth() === $node->depth() + 1) {
            $this->state->moveTo($this->nodes[$index + 1]->key);
        }
    }

    /**
     * Kursor na rodzica; `false` znaczy „węzeł stoi na pierwszym poziomie i wyżej
     * w drzewie nie ma dokąd iść”.
     *
     * Rodzica szukamy **wstecz po spłaszczonej liście**, a nie przez obcięcie
     * ścieżki, i to nie jest dłuższa droga do tego samego: pierwszy węzeł powyżej
     * o głębokości mniejszej o jeden **jest** rodzicem z definicji spłaszczenia,
     * niezależnie od tego, jak wyglądają klucze. Rachunek na ścieżkach zakładałby,
     * że klucz jest ścieżką — a to jest umowa tej klasy, nie własność drzewa.
     */
    public function focusParent(TreeNode $node): bool
    {
        $index = $this->cursorIndex();

        if ($index === null || $node->depth() === 0) {
            return false;
        }

        for ($above = $index - 1; $above >= 0; --$above) {
            if ($this->nodes[$above]->depth() === $node->depth() - 1) {
                $this->state->moveTo($this->nodes[$above]->key);

                return true;
            }
        }

        return false;
    }

    /**
     * Katalog, w którym leży węzeł pod kursorem — z zaznaczeniem ustawionym na
     * tym węźle.
     *
     * Ta jedna metoda odpowiada za to, że **reszta modułu nie musi wiedzieć
     * o istnieniu drzewa**: pas ścieżki, podgląd miniatury i kontekst sesji
     * dostają zwykły `Directory` z zaznaczeniem, czyli dokładnie to, co dostawały
     * od listy. Węzeł z pierwszego poziomu przesuwa przy okazji zaznaczenie
     * **listy**, bo katalogiem panelu jest wtedy ten sam obiekt — dzięki temu
     * powrót do widoku listy staje na wpisie, na którym stało drzewo.
     */
    public function cursorDirectory(): Directory
    {
        $this->refresh();
        $node = $this->cursorNode();
        $root = $this->pane->directory();

        if ($node === null) {
            return $root;
        }

        $owner = $this->ownerOf($node->key);

        if ($owner === null) {
            return $root;
        }

        $owner->selectEntryNamed(basename($node->key));

        return $owner;
    }

    /** Limit głębokości w chwili ostatniego spłaszczenia; `null` — bez limitu. */
    public function limit(): ?int
    {
        $this->refresh();

        return $this->limit;
    }

    /**
     * Czy węzeł na tej głębokości wolno rozwinąć.
     *
     * Poziomem pierwszym są wpisy katalogu w korzeniu (głębokość 0), więc
     * rozwinięcie węzła o głębokości `d` tworzy poziom `d + 2`.
     */
    private function allows(int $depth): bool
    {
        return $this->limit === null || $depth + 2 <= $this->limit;
    }

    /**
     * Uzgodnienie z panelem i z ustawieniami — raz na klatkę, przed każdym
     * pytaniem o treść.
     *
     * Spłaszczenie jest **zapamiętane**, i to jest cała odpowiedź na kryterium
     * „rozwinięcie gałęzi o tysiącu wpisów nie gubi klatki”: klatka, w której nic
     * się nie zmieniło, kosztuje trzy porównania, a nie tysiąc konstruktorów.
     */
    private function refresh(): void
    {
        $root = $this->pane->directory();
        $limit = BrowserSettings::treeDepth($this->loop->settings());
        $hidden = $this->pane->showsHiddenEntries();

        if ($this->hidden !== $hidden) {
            // Gałęzie czytano z innym filtrem, więc mówią o czymś innym niż
            // korzeń. Odczyty wracają po jednym na takt, jak każde odtworzenie.
            $this->hidden = $hidden;
            $this->contents = [];
            $this->flattened = false;
        }

        if ($this->root !== $root || $this->limit !== $limit) {
            $this->root = $root;
            $this->limit = $limit;
            $this->flattened = false;
            $this->state->useContext($root->path()->value);
            $this->window->useContext($root->path()->value);
        }

        if ($this->pending !== null) {
            $this->read($this->pending);
            $this->pending = null;
            $this->flattened = false;
        }

        if (!$this->flattened) {
            $this->flatten($root);
        }
    }

    private function flatten(Directory $root): void
    {
        $this->nodes = [];
        $this->keys = [];
        $this->pending = null;
        $this->flattened = true;

        $this->walk($root, []);

        // Drzewo bez kursora zaczyna od **zaznaczenia listy**, a nie od pierwszego
        // wiersza. Trzy przejścia korzystają z tego naraz i we wszystkich trzech
        // chodzi o to samo — żeby użytkownik nie musiał szukać, gdzie był:
        // przełączenie widoku klawiszem, wyjście katalog wyżej (`NavigateUpUseCase`
        // zaznacza katalog, z którego przyszliśmy) i powrót do korzenia, w którym
        // drzewo już kiedyś stało.
        $selected = $root->selectedEntry();

        if ($this->state->cursor() === null && $selected !== null) {
            $this->state->moveTo($root->path()->child($selected->name)->value);
        }

        $this->state->moveBy(0, $this->keys);
    }

    /** @param list<bool> $guides */
    private function walk(Directory $directory, array $guides): void
    {
        $entries = $directory->entries();
        $last = count($entries) - 1;

        foreach ($entries as $index => $entry) {
            $key = $directory->path()->child($entry->name)->value;
            $branch = $entry->isDirectory();
            $expanded = $branch && $this->allows(count($guides)) && $this->state->isExpanded($key);

            $this->nodes[] = new TreeNode(
                $key,
                $entry->name . ($branch ? '/' : ''),
                $guides,
                $index === $last,
                $branch,
                $expanded,
                $branch ? '' : EntrySize::of($this->translator, $entry->sizeInBytes),
                $branch ? Role::Accent : Role::Text,
            );
            $this->keys[] = $key;

            if (!$expanded) {
                continue;
            }

            $contents = $this->contents[$key] ?? null;

            if ($contents === null) {
                $this->pending ??= $key;

                continue;
            }

            $this->walk($contents, [...$guides, $index !== $last]);
        }
    }

    private function read(string $key): void
    {
        if (isset($this->contents[$key])) {
            return;
        }

        $this->contents[$key] = $this->branches->execute(new DirectoryPath($key), $this->hidden);
    }

    /** Katalog, w którym leży węzeł o tym kluczu — korzeń albo odczytana gałąź. */
    private function ownerOf(string $key): ?Directory
    {
        $parent = (new DirectoryPath($key))->parent();

        if ($parent === null) {
            return null;
        }

        $root = $this->pane->directory();

        if ($parent->equals($root->path())) {
            return $root;
        }

        return $this->contents[$parent->value] ?? null;
    }
}
