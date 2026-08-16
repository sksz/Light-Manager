<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Domain\ValueObject;

/**
 * Wersje klienta i serwera wraz z odpowiedzią, czy wolno im razem pracować
 * (krok 52).
 *
 * Obiekt istnieje z powodu zapisanego w planie kroku i potwierdzonego na
 * maszynie: **klient jest stary** (v1.25.2 z września 2022), a Kubernetes
 * wspiera różnicę **najwyżej jednego wydanego numeru** między klientem
 * a serwerem. Rozstrzygnięcie D91 nr 8 mówi, co z tym zrobić: **pokazać obie
 * wersje i ostrzec, ale niczego nie odmawiać** — bo dokumentacja Kubernetesa
 * nazywa taką różnicę „niewspieraną”, a nie „niemożliwą”, i większość poleceń
 * działa mimo niej.
 *
 * Numer poboczny bywa podany z plusem (`27+` na klastrach zarządzanych, gdzie
 * dostawca wydał łatkę własną), więc czyta się z niego **wiodące cyfry**, a nie
 * całość. Wersja nieodczytana nie jest błędem: ostrzeżenia po prostu nie ma,
 * bo nie ma czego z czym porównać.
 */
final readonly class ClusterVersion
{
    /** Ile wydanych numerów różnicy Kubernetes wspiera. */
    private const SUPPORTED_SKEW = 1;

    private function __construct(
        public string $client,
        public ?string $server,
    ) {
    }

    public static function of(string $client, ?string $server = null): self
    {
        $trimmedServer = $server === null ? null : trim($server);

        return new self(trim($client), $trimmedServer === '' ? null : $trimmedServer);
    }

    /** Czy serwer w ogóle odpowiedział — bez tego mówimy o samym kliencie. */
    public function knowsServer(): bool
    {
        return $this->server !== null;
    }

    /**
     * Czy różnica przekracza to, co Kubernetes wspiera.
     *
     * Odpowiedź **przecząca przy nieznanej wersji serwera** jest tu świadoma:
     * ostrzeżenie „wersje mogą być niezgodne”, wypisane wtedy, gdy jednej z nich
     * nie znamy, straszyłoby zamiast informować.
     */
    public function isSkewed(): bool
    {
        $client = self::minorOf($this->client);
        $server = $this->server === null ? null : self::minorOf($this->server);

        if ($client === null || $server === null) {
            return false;
        }

        return abs($client - $server) > self::SUPPORTED_SKEW;
    }

    /**
     * Numer poboczny z wersji `v1.25.2` albo `v1.27+`.
     *
     * Bierzemy **drugi człon**, bo pierwszy (`1`) nie zmienił się od dekady
     * i porównywanie go nie odpowiada na żadne pytanie.
     */
    private static function minorOf(string $version): ?int
    {
        if (preg_match('/^v?(\d+)\.(\d+)/', trim($version), $matches) !== 1) {
            return null;
        }

        return (int) $matches[2];
    }
}
