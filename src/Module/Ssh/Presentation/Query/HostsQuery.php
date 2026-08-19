<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Ssh\Application\HostBookView;
use LightManager\Module\Ssh\Application\SshSession;
use LightManager\Module\Ssh\Application\SshSettings;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;

/**
 * `ssh.hosts` — książka hostów: z kim, jako kto i czym się przedstawiając.
 *
 * **Pokolenie jest prawdziwym licznikiem, a nie `VOLATILE`** — i to jest różnica
 * wobec trzech pozostałych kwerend tego modułu. Książka zmienia się w dwóch
 * miejscach (`add()`, `remove()`), oba są w jednej klasie i oba biją licznik,
 * więc źródło **umie powiedzieć, że się zmieniło** — czyli zachodzi dokładnie ten
 * warunek, pod którym D93 nr 1 pozwala pamiętać wynik. Zysk jest realny: spis
 * hostów rysuje się co klatkę, a wiersze budują się raz na zmianę.
 *
 * **Odcisku klucza w wierszach nie ma i nie będzie.** Kwerenda oddaje obcym dane
 * pierwotne, ale „pierwotna” nie znaczy „wszystka”: odcisk jest materiałem
 * uwierzytelnienia, a nie opisem wpisu. Ścieżka klucza prywatnego wypada z tego
 * samego powodu — obcy moduł, który dostałby ją w wierszu, wiedziałby, gdzie
 * szukać klucza. Zostaje **sposób** uwierzytelnienia, bo on opisuje wpis.
 */
final class HostsQuery implements QueryInterface
{
    public function __construct(
        private readonly SshSession $session,
    ) {
    }

    public function name(): string
    {
        return SshSettings::ID . '.hosts';
    }

    public function descriptionKey(): string
    {
        return 'module.' . SshSettings::ID . '.query.hosts';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return $this->session->revision();
    }

    public function ask(CommandInput $input): QueryResult
    {
        $book = $this->session->book();
        $connected = $this->session->state()->host;
        $view = new HostBookView($book, $this->session->location(), $this->session->bookProblem());

        return QueryResult::owned(SshSettings::ID, $view, static function () use ($book, $connected): array {
            $rows = [];

            foreach ($book->all() as $profile) {
                $rows[] = self::describe($profile, $connected !== null && $connected->equals($profile));
            }

            return $rows;
        });
    }

    /** @return array<string, string|int|bool> */
    private static function describe(HostProfile $profile, bool $connected): array
    {
        return [
            'name' => $profile->name,
            'host' => $profile->host,
            'port' => $profile->port,
            'user' => $profile->user,
            'auth' => $profile->auth->value,
            'connected' => $connected,
        ];
    }
}
