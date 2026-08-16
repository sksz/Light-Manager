<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Role;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;
use LightManager\Module\Kubernetes\Application\ResourceCache;
use LightManager\Module\Kubernetes\Application\ResourceRow;
use LightManager\Module\Kubernetes\Application\RowTone;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceKind;
use LightManager\Presentation\Ui\Component\TreeNode;
use LightManager\Presentation\Ui\TreeState;

/**
 * Drzewo lewego panelu: **grupy API → rodzaje → zasoby** (krok 52, D91 nr 3).
 *
 * Spłaszcza **moduł, nie komponent** — reguła 11m z kroku 31, i tu obowiązuje
 * z podwojoną siłą: `TreeView` schodzący sam po gałęziach musiałby wiedzieć, że
 * rozwinięcie rodzaju znaczy uruchomienie procesu potomnego i czekanie na klaster.
 *
 * Trzy poziomy zamiast dwóch wybrano dlatego, że rodzajów bywa **kilkadziesiąt**
 * (klaster minikube podaje ich około sześćdziesięciu, a każdy operator dokłada
 * swoje). Płaska lista w korzeniu kazałaby przewijać obok rzeczy oglądanych raz
 * w roku, żeby dojść do podów; kilkanaście grup mieści się w panelu naraz.
 *
 * **Klucz węzła jest adresem, nie numerem** — `core`, `core/pods`,
 * `core/pods/web-7d9f`. Wynika to wprost z `TreeState` (kursor jest kluczem, bo
 * numer wiersza zmienia każde rozwinięcie powyżej), ale ma tu drugie dno: klucz
 * przeżywa odświeżenie listy, więc kursor stojący na podzie zostaje na nim także
 * wtedy, gdy lista przyszła z klastra na nowo.
 */
final class ClusterTree
{
    /** @var list<TreeNode> */
    private array $nodes = [];

    /** @var list<string> */
    private array $keys = [];

    /**
     * @param ResourceCache $cache **wyłącznie do zamawiania odczytu** (`load()`);
     *                             wszystko, co drzewo czyta, idzie przez `$reader`
     */
    public function __construct(
        private readonly ResourceCache $cache,
        private readonly TreeState $state,
        private readonly TranslatorPort $translator,
        private readonly KubernetesQueries $reader,
    ) {
    }

    /**
     * Buduje węzły na tę klatkę.
     *
     * Powstaje **za każdym razem od nowa**, bo komponent jest bezstanowy
     * (reguła 11a), a rozwinięcia i kursor mieszkają w `TreeState`. Koszt to
     * przejście po katalogu i po wierszach rozwiniętych rodzajów — bez ani
     * jednego pytania do klastra.
     *
     * @return list<TreeNode>
     */
    public function nodes(): array
    {
        $this->build();

        return $this->nodes;
    }

    /**
     * Klucze wszystkich widocznych węzłów — po nich chodzi kursor.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $this->build();

        return $this->keys;
    }

    /** Rodzaj wskazany kluczem — `null`, gdy klucz wskazuje grupę albo zasób. */
    public function kindAt(string $key): ?ResourceKind
    {
        $parts = explode('/', $key);

        return count($parts) >= 2 ? $this->reader->findKind($parts[1]) : null;
    }

    /** Nazwa zasobu wskazanego kluczem — `null`, gdy klucz wskazuje grupę albo rodzaj. */
    public function resourceAt(string $key): ?string
    {
        $parts = explode('/', $key);

        return count($parts) >= 3 ? $parts[2] : null;
    }

    /**
     * Rozwija albo zwija węzeł, zamawiając odczyt, gdy trzeba.
     *
     * Odczyt pada **wyłącznie przy rozwijaniu rodzaju** i wyłącznie wtedy, gdy
     * jego wierszy jeszcze nie mamy — bo każdy taki odczyt to proces potomny.
     * Zwijanie nie kosztuje nic i niczego nie zapomina: wiersze zostają, żeby
     * powrót do gałęzi był natychmiastowy.
     */
    public function toggle(string $key): void
    {
        $this->state->toggle($key);

        if ($this->state->isExpanded($key)) {
            $kind = $this->kindAt($key);

            if ($kind !== null && $this->resourceAt($key) === null) {
                $this->cache->load($kind);
            }
        }
    }

    /** Klucz gałęzi rodzaju, na której stoi kursor — także wtedy, gdy stoi na zasobie. */
    public function focusedKind(): ?ResourceKind
    {
        $cursor = $this->state->cursor();

        return $cursor === null ? null : $this->kindAt($cursor);
    }

    private function build(): void
    {
        $this->nodes = [];
        $this->keys = [];

        $groups = $this->reader->groups();
        $lastGroup = count($groups) - 1;

        foreach ($groups as $index => $group) {
            $expanded = $this->state->isExpanded($group);

            $this->push(new TreeNode(
                $group,
                $group,
                [],
                $index === $lastGroup,
                true,
                $expanded,
                (string) count($this->reader->kindsOf($group)),
                Role::Accent,
            ));

            if ($expanded) {
                $this->walkKinds($group, $index !== $lastGroup);
            }
        }

        $this->ensureCursor();
    }

    /**
     * Kursor startuje na pierwszym węźle i **nigdy nie zostaje na nieistniejącym**.
     *
     * Drzewo bez kursora nie odpowiada na klawisze — `Enter` nie ma czego
     * rozwinąć — a taki właśnie jest stan zaraz po przyjściu spisu rodzajów,
     * bo nikt jeszcze niczego nie wybrał. Klucz sprzed odświeżenia bywa z kolei
     * nieobecny: rodzaj mógł zniknąć razem z operatorem, a zasób — zostać
     * usunięty przez kogoś innego.
     */
    private function ensureCursor(): void
    {
        if ($this->keys === []) {
            return;
        }

        $cursor = $this->state->cursor();

        if ($cursor === null || !in_array($cursor, $this->keys, true)) {
            $this->state->moveTo($this->keys[0]);
        }
    }

    private function walkKinds(string $group, bool $groupContinues): void
    {
        $kinds = $this->reader->kindsOf($group);
        $last = count($kinds) - 1;

        foreach ($kinds as $index => $kind) {
            $key = $group . '/' . $kind->address();
            $expanded = $this->state->isExpanded($key);
            $known = $this->reader->knows($kind);

            $this->push(new TreeNode(
                $key,
                $kind->name,
                [$groupContinues],
                $index === $last,
                true,
                $expanded,
                // Liczba zasobów dopiero po odczycie: przed nim nie wiemy jej
                // i **nie zgadujemy** — pusta wartość znaczy „jeszcze nie
                // pytaliśmy”, a zero znaczy „pytaliśmy i nie ma nic”.
                $known ? (string) count($this->reader->rowsOf($kind)) : '',
                Role::Text,
            ));

            if ($expanded) {
                $this->walkResources($key, $kind, [$groupContinues, $index !== $last]);
            }
        }
    }

    /** @param list<bool> $guides */
    private function walkResources(string $branch, ResourceKind $kind, array $guides): void
    {
        $rows = $this->reader->rowsOf($kind);

        if ($rows === []) {
            $this->push(new TreeNode(
                $branch . '/',
                $this->translator->translate(
                    'module.' . KubernetesSettings::ID
                    . ($this->reader->knows($kind) ? '.tree.empty' : '.tree.reading'),
                ),
                $guides,
                true,
                false,
                false,
                '',
                Role::Muted,
            ));

            return;
        }

        $last = count($rows) - 1;

        foreach ($rows as $index => $row) {
            $this->push(new TreeNode(
                $branch . '/' . $row->name,
                $row->name,
                $guides,
                $index === $last,
                false,
                false,
                '',
                self::roleOf($row),
            ));
        }
    }

    private function push(TreeNode $node): void
    {
        $this->nodes[] = $node;
        $this->keys[] = $node->key;
    }

    /**
     * Kolor wiersza zasobu.
     *
     * Ton liczy warstwa aplikacji, rolę dobiera ta — bo `Role` leży
     * w `Application/Ui` i enum stanu nie ma prawa jej znać (reguła 1). `Danger`
     * dla zasobu, który sam z siebie nie wstanie, `Warning` dla przejściowego.
     */
    private static function roleOf(ResourceRow $row): Role
    {
        return match ($row->tone) {
            RowTone::Broken => Role::Danger,
            RowTone::Waiting => Role::Warning,
            RowTone::Normal => Role::Text,
        };
    }
}
