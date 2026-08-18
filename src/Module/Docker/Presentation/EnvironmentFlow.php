<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\Environments;
use LightManager\Module\Docker\Domain\Exception\InvalidDockerEnvironmentException;
use LightManager\Module\Docker\Domain\ValueObject\DockerEnvironment;
use LightManager\Module\Docker\Domain\ValueObject\EnvironmentKind;
use LightManager\Presentation\Ui\Overlay\ChoiceOverlay;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * Łańcuch okien, którym prowadzi się wpis środowiska (krok 58, D102 nr 2).
 *
 * Rodzaje mają różne pola, a rdzeń nie ma komponentu formularza (reguła 13) —
 * więc wpis powstaje **oknami po kolei**, wzorem `ConnectFlow` z kroku 48:
 * stos okien ma jedno piętro (D75), każde ogniwo ustępuje następnemu przez
 * `OverlayOutcome::replace()`, a wartości niosą się domknięciami. Najkrótsza
 * droga (gniazdo lokalne) to rodzaj → nazwa → ścieżka; najdłuższa (TCP z TLS)
 * — sześć okien.
 *
 * **Zmiana idzie tym samym łańcuchem z wypełnionymi polami**, bez okna
 * rodzaju: rodzaj jest kształtem wpisu, nie jego polem — wpis tunelowy
 * „zmieniony na TCP" to inny wpis, a nie ten sam po edycji.
 *
 * Port w celu tunelu i w adresie demona wpisuje się przyrostkiem `:port`;
 * rozbiór pada przy zapisie. Cena jednego pola zamiast dwóch: nazwa wpisu
 * książki hostów zawierająca dwukropek i cyfry na końcu przegra z rozbiorem —
 * przypadek nazwany w `DockerEnvironment` i przyjęty świadomie.
 */
final class EnvironmentFlow
{
    public function __construct(
        private readonly Environments $environments,
        private readonly TranslatorPort $translator,
    ) {
    }

    /** Pierwsze ogniwo dodawania: pytanie o rodzaj. `Esc` znaczy odpowiedź ostatnią — „przerwij". */
    public function add(): OverlayInterface
    {
        return new ChoiceOverlay(
            $this->key('env.prompt.kind'),
            [],
            [
                EnvironmentKind::LocalSocket->value => EnvironmentKind::LocalSocket->labelKey(),
                EnvironmentKind::SshTunnel->value => EnvironmentKind::SshTunnel->labelKey(),
                EnvironmentKind::Tcp->value => EnvironmentKind::Tcp->labelKey(),
                'cancel' => $this->key('env.prompt.cancel'),
            ],
            fn (string $choice): OverlayOutcome => EnvironmentKind::of($choice) instanceof EnvironmentKind
                ? OverlayOutcome::replace($this->namePrompt(EnvironmentKind::from($choice), null))
                : OverlayOutcome::close(),
            $this->translator,
        );
    }

    /** Pierwsze ogniwo zmiany: rodzaj zostaje, pola przychodzą wypełnione. */
    public function edit(DockerEnvironment $entry): OverlayInterface
    {
        return $this->namePrompt($entry->kind, $entry);
    }

    private function namePrompt(EnvironmentKind $kind, ?DockerEnvironment $entry): PromptOverlay
    {
        return new PromptOverlay(
            $this->key('env.prompt.name'),
            [],
            $entry->name ?? '',
            fn (string $name): OverlayOutcome => OverlayOutcome::replace(
                $this->fieldPrompt($kind, $entry, trim($name)),
            ),
            $this->translator,
            $this->key('env.prompt.name.field'),
        );
    }

    /** Drugie ogniwo — pole zależne od rodzaju. */
    private function fieldPrompt(EnvironmentKind $kind, ?DockerEnvironment $entry, string $name): PromptOverlay
    {
        return match ($kind) {
            EnvironmentKind::LocalSocket => new PromptOverlay(
                $this->key('env.prompt.socket'),
                [],
                $entry->socketPath ?? DockerEnvironment::DEFAULT_SOCKET,
                fn (string $socket): OverlayOutcome => $this->finishLocal($name, trim($socket)),
                $this->translator,
                $this->key('env.prompt.socket.field'),
            ),
            EnvironmentKind::SshTunnel => new PromptOverlay(
                $this->key('env.prompt.target'),
                [],
                $entry === null ? '' : self::targetWithPort($entry),
                fn (string $target): OverlayOutcome => OverlayOutcome::replace(
                    $this->remoteSocketPrompt($name, trim($target), $entry),
                ),
                $this->translator,
                $this->key('env.prompt.target.field'),
            ),
            EnvironmentKind::Tcp => new PromptOverlay(
                $this->key('env.prompt.address'),
                [],
                $entry === null ? '' : $entry->target . ':' . $entry->port,
                fn (string $address): OverlayOutcome => OverlayOutcome::replace(
                    $this->certPrompt($name, trim($address), $entry),
                ),
                $this->translator,
                $this->key('env.prompt.address.field'),
            ),
        };
    }

    private function remoteSocketPrompt(string $name, string $target, ?DockerEnvironment $entry): PromptOverlay
    {
        return new PromptOverlay(
            $this->key('env.prompt.remoteSocket'),
            [],
            $entry->socketPath ?? DockerEnvironment::DEFAULT_SOCKET,
            fn (string $socket): OverlayOutcome => $this->finishTunnel($name, $target, trim($socket)),
            $this->translator,
            $this->key('env.prompt.socket.field'),
        );
    }

    private function certPrompt(string $name, string $address, ?DockerEnvironment $entry): PromptOverlay
    {
        return new PromptOverlay(
            $this->key('env.prompt.cert'),
            [],
            $entry->certPath ?? '',
            fn (string $cert): OverlayOutcome => OverlayOutcome::replace(
                $this->keyPrompt($name, $address, trim($cert), $entry),
            ),
            $this->translator,
            $this->key('env.prompt.path.field'),
        );
    }

    private function keyPrompt(string $name, string $address, string $cert, ?DockerEnvironment $entry): PromptOverlay
    {
        return new PromptOverlay(
            $this->key('env.prompt.key'),
            [],
            $entry->keyPath ?? '',
            fn (string $key): OverlayOutcome => OverlayOutcome::replace(
                $this->caPrompt($name, $address, $cert, trim($key), $entry),
            ),
            $this->translator,
            $this->key('env.prompt.path.field'),
        );
    }

    private function caPrompt(
        string $name,
        string $address,
        string $cert,
        string $key,
        ?DockerEnvironment $entry,
    ): PromptOverlay {
        return new PromptOverlay(
            $this->key('env.prompt.ca'),
            [],
            $entry->caPath ?? '',
            fn (string $ca): OverlayOutcome => $this->finishTcp($name, $address, $cert, $key, trim($ca)),
            $this->translator,
            $this->key('env.prompt.path.field'),
        );
    }

    private function finishLocal(string $name, string $socket): OverlayOutcome
    {
        return $this->save(fn (): DockerEnvironment => DockerEnvironment::localSocket($name, $socket));
    }

    private function finishTunnel(string $name, string $target, string $remoteSocket): OverlayOutcome
    {
        [$host, $port] = self::splitPort($target, DockerEnvironment::DEFAULT_TUNNEL_PORT);

        return $this->save(
            fn (): DockerEnvironment => DockerEnvironment::sshTunnel($name, $host, $port, $remoteSocket),
        );
    }

    private function finishTcp(string $name, string $address, string $cert, string $key, string $ca): OverlayOutcome
    {
        [$host, $port] = self::splitPort($address, DockerEnvironment::DEFAULT_TLS_PORT);

        return $this->save(
            fn (): DockerEnvironment => DockerEnvironment::tcp($name, $host, $port, $cert, $key, $ca),
        );
    }

    /**
     * Ostatnie ogniwo: samowalidacja wpisu i zapis książki.
     *
     * Wartość nie do przyjęcia wraca zdaniem z wyjątku modułu — okno się
     * zamyka, a użytkownik zaczyna od nowa z wiedzą, co odrzucono (wzorem
     * `HostsScreen::added()`).
     *
     * @param callable(): DockerEnvironment $factory
     */
    private function save(callable $factory): OverlayOutcome
    {
        try {
            $entry = $factory();
        } catch (InvalidDockerEnvironmentException $exception) {
            return OverlayOutcome::close(Message::error(
                $this->translator->translate($exception->problemKey(), $exception->problemParameters()),
            ));
        }

        $this->environments->add($entry);

        return OverlayOutcome::close(Message::info(
            $this->translator->translate($this->key('env.saved'), ['name' => $entry->name]),
        ));
    }

    /** Cel z powrotem w jednym polu — do wypełnienia okna zmiany. */
    private static function targetWithPort(DockerEnvironment $entry): string
    {
        return $entry->port === DockerEnvironment::DEFAULT_TUNNEL_PORT
            ? $entry->target
            : $entry->target . ':' . $entry->port;
    }

    /**
     * Rozbiór przyrostka `:port` — ostatni dwukropek, po którym stoją same
     * cyfry. IPv6 w nawiasach przechodzi, bo po `]` dwukropek portu jest
     * jedynym możliwym.
     *
     * @return array{string, int}
     */
    private static function splitPort(string $value, int $defaultPort): array
    {
        if (preg_match('/^(.*):([0-9]{1,5})$/', $value, $matches) === 1 && !str_ends_with($matches[1], ':')) {
            return [$matches[1], (int) $matches[2]];
        }

        return [$value, $defaultPort];
    }

    private function key(string $suffix): string
    {
        return 'module.' . DockerSettings::ID . '.' . $suffix;
    }
}
