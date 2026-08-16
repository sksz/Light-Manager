<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Domain\ValueObject;

use LightManager\Module\Kubernetes\Domain\Exception\InvalidClusterNameException;

/**
 * Rodzaj zasobu, tak jak opisuje go sam klaster (krok 52).
 *
 * **To jest obiekt, wokół którego obrócił się cały krok** (D91 nr 2). Plan
 * zakładał trzy rodzaje wpisane w kod — pody, wdrożenia, usługi — a
 * rozstrzygnięcie użytkownika postawiło „wszystkie elementy k8s”. Rodzaj
 * przestał być przez to gałęzią `match`a, a stał się **daną przychodzącą
 * z klastra**: jego nazwa, grupa, namespace'owość i czasowniki pochodzą
 * z `kubectl api-resources` i potrafią się zmienić między dwoma uruchomieniami
 * aplikacji, bo w międzyczasie ktoś zainstalował operator z własnymi CRD.
 *
 * Stąd bierze się jedyna rzecz, którą ten obiekt robi ponad przechowywanie:
 * **`address()`**. Nazwa mnoga bywa niejednoznaczna (`events` istnieje w grupie
 * pustej i w `events.k8s.io`), więc do polecenia idzie postać `nazwa.grupa` —
 * ta sama, którą `kubectl` przyjmuje od użytkownika i którą sam wypisuje przy
 * niejednoznaczności.
 */
final readonly class ResourceKind
{
    /** Grupa pusta w wypisie `api-resources` znaczy rdzeń API (`v1`). */
    public const CORE_GROUP = '';

    private const SUBJECT = 'kind';

    /**
     * @param string       $name       nazwa mnoga (`pods`, `deployments`)
     * @param string       $kind       nazwa pojedyncza z wielkiej litery (`Pod`)
     * @param string       $group      grupa API bez wersji (`apps`, `networking.k8s.io`)
     * @param bool         $namespaced czy zasób mieszka w przestrzeni nazw
     * @param list<string> $verbs      czasowniki dozwolone przez serwer
     * @param list<string> $shortNames skróty (`po`, `deploy`) — wpisywane przez człowieka
     */
    private function __construct(
        public string $name,
        public string $kind,
        public string $group,
        public bool $namespaced,
        public array $verbs,
        public array $shortNames,
    ) {
    }

    /**
     * @param list<string> $verbs
     * @param list<string> $shortNames
     */
    public static function of(
        string $name,
        string $kind,
        string $group = self::CORE_GROUP,
        bool $namespaced = true,
        array $verbs = [],
        array $shortNames = [],
    ): self {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw InvalidClusterNameException::forEmptyValue(self::SUBJECT);
        }

        if (str_starts_with($trimmed, '-')) {
            throw InvalidClusterNameException::forOptionLike(self::SUBJECT, $trimmed);
        }

        // Nazwa rodzaju i grupa idą do polecenia sklejone kropką, więc wolno w
        // nich tyle, ile w nazwie zasobu API: małe litery, cyfry, kropka
        // i myślnik. CRD z grupy `example.com` przechodzą, cokolwiek innego nie.
        if (preg_match('/^[a-z0-9][a-z0-9.-]*$/', $trimmed) !== 1) {
            throw InvalidClusterNameException::forMalformedValue(self::SUBJECT, $trimmed);
        }

        $trimmedGroup = trim($group);

        if ($trimmedGroup !== '' && preg_match('/^[a-z0-9][a-z0-9.-]*$/', $trimmedGroup) !== 1) {
            throw InvalidClusterNameException::forMalformedValue(self::SUBJECT, $trimmedGroup);
        }

        return new self($trimmed, trim($kind), $trimmedGroup, $namespaced, $verbs, $shortNames);
    }

    /**
     * Postać, którą podaje się `kubectl`owi — jednoznaczna nawet przy kolizji nazw.
     *
     * `events` bez grupy znaczyłoby dwie różne rzeczy naraz; `events.events.k8s.io`
     * znaczy jedną. Dla rdzenia API grupy nie ma i dokleić jej nie ma czego.
     */
    public function address(): string
    {
        return $this->group === self::CORE_GROUP ? $this->name : $this->name . '.' . $this->group;
    }

    /**
     * Czy rodzaj wolno wypisać.
     *
     * Serwer podaje czasowniki przy każdym rodzaju, a rodzaje **bez `list`**
     * istnieją naprawdę i nie są rzadkością: `bindings`, `tokenreviews`
     * i cała rodzina `*reviews` przyjmują wyłącznie zapis. Gałąź drzewa, która po
     * rozwinięciu zawsze kończy się błędem, jest gorsza od gałęzi, której nie ma.
     */
    public function isListable(): bool
    {
        return $this->verbs === [] || in_array('list', $this->verbs, true);
    }

    /** Czy zasób tego rodzaju daje się usunąć — od tego zależy klawisz na ekranie. */
    public function isDeletable(): bool
    {
        return $this->verbs === [] || in_array('delete', $this->verbs, true);
    }

    /**
     * Nazwa grupy pokazywana w drzewie.
     *
     * Rdzeń API nie ma nazwy własnej, a „(brak)” w korzeniu drzewa byłoby
     * zagadką — pody, usługi i sekrety mieszkają właśnie tam, więc grupa dostaje
     * nazwę, pod którą zna ją dokumentacja Kubernetesa.
     */
    public function groupLabel(): string
    {
        return $this->group === self::CORE_GROUP ? 'core' : $this->group;
    }

    public function equals(self $other): bool
    {
        return $this->name === $other->name && $this->group === $other->group;
    }
}
