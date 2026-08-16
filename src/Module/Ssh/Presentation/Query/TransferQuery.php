<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Ssh\Application\SshSettings;
use LightManager\Module\Ssh\Presentation\Component\RemoteSize;
use LightManager\Module\Ssh\Presentation\RemoteTransfer;

/**
 * `ssh.transfer` — stan przesyłu: kierunek, plik, bajty, etap.
 *
 * Trzeci w projekcie użytkownik reguły nr 4 kwerendy, po `file-info.digest`
 * i `file-info.usage`: praca trwa minuty, więc kwerenda oddaje **jej stan**,
 * nigdy jej wynik po czekaniu. Ulotna, bo licznik bajtów zmienia się w każdym
 * takcie — `ask()` przepisuje przez to kilka pól i wraca.
 *
 * **Asymetria pobierania i wysyłania jest widoczna w odpowiedzi, a nie ukryta.**
 * Przy pobieraniu bajty czyta rosnący plik roboczy, przy wysyłaniu takiego pliku
 * po naszej stronie nie ma i `sftp` postępu nie odda (11r'), więc licznik liczy
 * wtedy pliki. Pole `bytes` mówi więc prawdę w obie strony, ale w jedną z nich
 * zostaje zerem do końca pliku — i to jest przyjęta cena kroku 50, a nie usterka
 * tej kwerendy. Kierunek stoi w wierszu obok właśnie po to, żeby dało się jedno
 * od drugiego odróżnić.
 */
final class TransferQuery implements QueryInterface
{
    public function __construct(
        private readonly RemoteTransfer $transfers,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return SshSettings::ID . '.transfer';
    }

    public function descriptionKey(): string
    {
        return 'module.' . SshSettings::ID . '.query.transfer';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return self::VOLATILE;
    }

    public function ask(CommandInput $input): QueryResult
    {
        $progress = $this->transfers->snapshot();
        $state = $progress->state;
        $translator = $this->translator;

        return QueryResult::owned(SshSettings::ID, $progress, static fn (): array => [[
            'stage' => strtolower($state->stage->name),
            'direction' => strtolower($progress->direction->name),
            'name' => $progress->name,
            'current' => $state->current,
            'size' => RemoteSize::of($translator, $state->doneBytes),
            'total' => RemoteSize::of($translator, $state->totalBytes),
            'entries' => $state->doneEntries,
            'totalEntries' => $state->totalEntries,
            'skipped' => $state->skippedEntries,
            'running' => $state->isRunning(),
            'problem' => $state->problemKey ?? '',
        ]]);
    }
}
