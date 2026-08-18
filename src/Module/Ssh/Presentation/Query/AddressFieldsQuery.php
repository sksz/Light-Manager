<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Ssh\Application\SshSettings;

/**
 * `ssh.address-fields` — deklaracja pól, które ten moduł dokłada wpisowi
 * książki adresowej (krok 60, D105 nr 3).
 *
 * **Deklaracja jest daną, nie kodem**: cztery napisy w wierszu — klucz pola,
 * klucz napisu z etykietą, rodzaj i wartość domyślna. Książka czyta je nazwą
 * kwerendy podaną przy zakładaniu rozdziału, więc nie zna ani jednego typu tego
 * modułu (reguła 15g), a rdzeń nie bierze w tym udziału w ogóle.
 *
 * **Pola są dwa i to nie jest wybór estetyczny.** Port i login opisują *gdzie
 * i jako kto* — czyli są opisem adresu i wolno im leżeć w książce, którą czyta
 * każdy. Sposób uwierzytelnienia i ścieżka klucza opisują *czym się
 * przedstawiam* i zostają w sekcji tego modułu (reguła 11w) — dlatego nie ma
 * ich w tej deklaracji.
 *
 * Pokolenie jest **stałe**: deklaracja nie zmienia się w czasie uruchomienia,
 * więc rejestr policzy wiersze raz i odda je z pamięci każdemu następnemu.
 */
final class AddressFieldsQuery implements QueryInterface
{
    private const GENERATION = 1;

    public function name(): string
    {
        return SshSettings::ID . '.address-fields';
    }

    public function descriptionKey(): string
    {
        return 'module.' . SshSettings::ID . '.query.addressFields';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return self::GENERATION;
    }

    public function ask(CommandInput $input): QueryResult
    {
        return QueryResult::of([
            [
                'key' => 'port',
                'label' => 'module.' . SshSettings::ID . '.field.port',
                'kind' => 'number',
                'default' => SshSettings::DEFAULT_ADDRESS_PORT,
            ],
            [
                'key' => 'user',
                'label' => 'module.' . SshSettings::ID . '.field.user',
                'kind' => 'text',
                'default' => '',
            ],
        ]);
    }
}
