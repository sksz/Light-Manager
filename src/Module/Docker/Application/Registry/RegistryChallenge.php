<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application\Registry;

/**
 * Rozebrany nagłówek `WWW-Authenticate` — **drugi obieg wie stąd, dokąd pytać**
 * (krok 61, etap 2).
 *
 * Rejestr odpowiada na pytanie bez tokenu kodem `401` i zdaniem w rodzaju
 * `Bearer realm="https://auth.example.com/token",service="registry",scope="repository:x:pull"`.
 * Dopiero pytanie pod `realm` — z podstawowym uwierzytelnieniem — oddaje token,
 * którym podpisuje się obieg trzeci.
 *
 * **Rozbiór stoi w `Application`, a nie w usłudze**, bo jest czystym rachunkiem
 * na napisie i nie dotyka ani `curl`a, ani sieci: da się go sprawdzić testem
 * jednostkowym bez ani jednego wywołania. Ta sama zasada, którą `GlfwKeyMapper`
 * odsuwa mapowanie klawiszy od okna (11g).
 */
final readonly class RegistryChallenge
{
    /** @param non-empty-string $realm bo wyzwanie bez `realm` nie powstaje */
    private function __construct(
        /** @var non-empty-string */
        public string $realm,
        public string $service,
        public string $scope,
    ) {
    }

    /**
     * Wyzwanie z nagłówków odpowiedzi albo `null`, gdy rejestr żadnego nie
     * postawił.
     *
     * `null` **nie jest błędem**: rejestr bez uwierzytelnienia — a takim jest
     * `registry:2` postawiony `make registry-start` — odpowiada `200` już
     * w pierwszym obiegu i drugiego nie ma po co robić.
     *
     * @param list<string> $headers wiersze nagłówków, tak jak przyszły
     */
    public static function fromHeaders(array $headers): ?self
    {
        foreach ($headers as $header) {
            if (stripos($header, 'www-authenticate:') !== 0) {
                continue;
            }

            $value = trim(substr($header, strlen('www-authenticate:')));

            if (stripos($value, 'bearer ') !== 0) {
                // `Basic` też się zdarza i **nie jest wyzwaniem tego rodzaju**:
                // token pobiera się wtedy nieskąd, bo poświadczenie idzie wprost.
                continue;
            }

            $realm = self::parameter($value, 'realm');

            if ($realm === '') {
                continue;
            }

            return new self($realm, self::parameter($value, 'service'), self::parameter($value, 'scope'));
        }

        return null;
    }

    /**
     * Pełny adres drugiego obiegu — `realm` wraz z tym, o co pytamy.
     *
     * @return non-empty-string bo wyzwanie bez `realm` w ogóle nie powstaje
     */
    public function tokenUrl(): string
    {
        $query = [];

        if ($this->service !== '') {
            $query['service'] = $this->service;
        }

        if ($this->scope !== '') {
            $query['scope'] = $this->scope;
        }

        return $query === [] ? $this->realm : $this->realm . '?' . http_build_query($query);
    }

    /**
     * Wartość jednego parametru wyzwania.
     *
     * Czytane wyrażeniem, a nie podziałem po przecinkach, i to jest ostrożność
     * wymuszona treścią: `scope` **zawiera przecinki** przy żądaniu wielu
     * uprawnień naraz (`repository:a:pull,push`), więc podział rozerwałby go
     * w środku.
     */
    private static function parameter(string $header, string $name): string
    {
        if (preg_match('/(?:^|[\s,])' . preg_quote($name, '/') . '="([^"]*)"/i', $header, $match) === 1) {
            return $match[1];
        }

        return '';
    }
}
