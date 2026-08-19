<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Domain\ValueObject;

use LightManager\Module\Kubernetes\Domain\Exception\InvalidClusterNameException;

/**
 * Wpis książki klastrów (krok 59, D96 nr 3 i 4).
 *
 * **Nazwa własna jest tożsamością miejsca** — nie nazwa kontekstu, bo `default`
 * w dwóch plikach to dwa różne klastry, a `minikube` odtworzony po skasowaniu
 * to trzeci. Wpis niesie obie współrzędne miejsca (plik i kontekst), do tego
 * przestrzeń nazw i opcjonalny własny limit czasu — pola, których nie ma
 * w `kubeconfig` albo których nie chcemy stamtąd brać.
 *
 * Ścieżka pliku sprawdzana jest **istnieniem dopiero przy użyciu**: plik na
 * dysku sieciowym bywa chwilowo nieobecny, a wpis nie ma przez to przestać
 * istnieć (plan kroku, punkt 1).
 *
 * Nazwę wpisuje człowiek, więc odsiew jest jak przy nazwie kontekstu — szeroki:
 * odpada wyłącznie to, co zmieniłoby polecenie w co innego (wiodący `-`) albo
 * czego nie widać (znaki sterujące). Spacja w nazwie własnej jest legalna,
 * bo nazwa wpisu nie jedzie wierszem polecenia — jedzie nim kontekst i plik.
 */
final readonly class ClusterProfile
{
    private const SUBJECT = 'cluster';

    /** Ta sama granica, co przy nazwie kontekstu — spisu nie ma, bierzemy granicę zasobu. */
    private const MAXIMUM_LENGTH = 253;

    private function __construct(
        /** Identyfikator wpisu książki — tożsamość miejsca (krok 60). */
        public string $id,
        public string $name,
        public string $kubeconfig,
        public string $context,
        /** Pusty napis znaczy „ta z kontekstu w pliku, a w jej braku `default`". */
        public string $namespace,
        /** `null` znaczy „limit z ustawień modułu". */
        public ?int $timeoutSeconds,
    ) {
    }

    public static function of(
        string $name,
        string $kubeconfig,
        string $context,
        string $namespace = '',
        ?int $timeoutSeconds = null,
        string $id = '',
    ): self {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw InvalidClusterNameException::forEmptyValue(self::SUBJECT);
        }

        if (str_starts_with($trimmed, '-')) {
            throw InvalidClusterNameException::forOptionLike(self::SUBJECT, $trimmed);
        }

        if (strlen($trimmed) > self::MAXIMUM_LENGTH) {
            throw InvalidClusterNameException::forTooLongValue(self::SUBJECT, $trimmed, self::MAXIMUM_LENGTH);
        }

        if (preg_match('/[\p{Cc}\p{Cf}]/u', $trimmed) === 1) {
            throw InvalidClusterNameException::forMalformedValue(self::SUBJECT, $trimmed);
        }

        if ($timeoutSeconds !== null && $timeoutSeconds < 1) {
            throw InvalidClusterNameException::forMalformedValue('timeout', (string) $timeoutSeconds);
        }

        // Współrzędne przechodzą przez te same odsiewy, co przy użyciu — wpis,
        // z którego nie da się złożyć polecenia, nie ma prawa powstać.
        return new self(
            $id,
            $trimmed,
            ClusterPlace::path($kubeconfig),
            ContextName::of($context)->value,
            trim($namespace) === '' ? '' : NamespaceName::of($namespace)->value,
            $timeoutSeconds,
        );
    }

    /**
     * Miejsce z wiersza kwerendy `address-book.entries k8s` albo `null`, gdy
     * wiersz nie opisuje klastra, z którym da się rozmawiać (krok 60).
     *
     * **Jedyna droga, którą wpis powstaje z książki**, i idzie wyłącznie przez
     * napisy i liczby (reguła 15g). Wiersz bez pliku albo bez kontekstu nie
     * jest błędem: książka jest wspólna, więc wpis może istnieć po to, żeby
     * nieść pola zupełnie innego rozdziału.
     *
     * @param array<string, string|int|bool> $row
     */
    public static function fromRow(array $row): ?self
    {
        $id = $row['id'] ?? '';
        $kubeconfig = $row['kubeconfig'] ?? '';
        $context = $row['context'] ?? '';

        if (!is_string($id) || $id === '' || !is_string($kubeconfig) || $kubeconfig === '') {
            return null;
        }

        if (!is_string($context) || $context === '') {
            return null;
        }

        $namespace = $row['namespace'] ?? '';
        $timeout = $row['timeout'] ?? null;
        $timeout = is_int($timeout) ? $timeout : (int) (is_string($timeout) ? $timeout : 0);

        try {
            return self::of(
                is_string($row['name'] ?? null) && $row['name'] !== '' ? $row['name'] : $context,
                $kubeconfig,
                $context,
                is_string($namespace) ? $namespace : '',
                $timeout > 0 ? $timeout : null,
                $id,
            );
        } catch (InvalidClusterNameException) {
            // Wpis nie do przyjęcia wypada, a reszta spisu zostaje.
            return null;
        }
    }

    public function place(): ClusterPlace
    {
        return ClusterPlace::of($this->kubeconfig, ContextName::of($this->context));
    }

    /** Tożsamością wpisu jest nazwa własna — jak w książce hostów (krok 48). */
    public function equals(self $other): bool
    {
        return $this->name === $other->name;
    }
}
