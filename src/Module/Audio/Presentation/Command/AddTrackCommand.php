<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandArgumentKind;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Command\SuggestionSource;
use LightManager\Application\Command\SuggestsArguments;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\PlaylistPlayer;
use LightManager\Module\Audio\Application\Port\TrackFilesPort;

/**
 * `audio.add <ścieżka>` — utwór wchodzi na playlistę (krok 45).
 *
 * Trzecia z trzech dróg dopisania utworu (D82 nr 2) i jedyna, która działa
 * **spoza okna modułu**: zbiór komend jest globalny (D39, P18), więc ścieżkę da
 * się dopisać z ustawień, z pomocy albo z przeglądarki, bez przechodzenia do
 * playlisty. Wchodzi przez to także do menu kontekstowego, jak każda komenda
 * z rejestru.
 *
 * Podpowiedzi liczone **na żądanie**, dokładnie jak w `browser.jump`: zawartość
 * dysku zmienia się pod ręką użytkownika. Katalog przegląda **własny port
 * modułu**, bo do repozytorium wpisów przeglądarki sięgnąć nie wolno — moduły się
 * nie znają (reguła 15).
 */
final class AddTrackCommand implements CommandInterface, SuggestsArguments
{
    private const ARGUMENT = 'path';

    public function __construct(
        private readonly PlaylistPlayer $player,
        private readonly TrackFilesPort $files,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return AudioSettings::ID . '.add';
    }

    public function descriptionKey(): string
    {
        return 'module.' . AudioSettings::ID . '.command.add';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::ARGUMENT,
                'module.' . AudioSettings::ID . '.argument.path',
                CommandArgumentKind::Path,
                suggestions: SuggestionSource::OnDemand,
            ),
        ];
    }

    /**
     * Ścieżka wskazująca plik, którego nie ma, **wchodzi na listę** — wyszarzona
     * i pomijana przy graniu (D82 nr 6). Odmowa byłaby tu gorsza od przyjęcia:
     * nośnik odpięty w tej chwili bywa podpięty za minutę, a użytkownik i tak
     * widzi na liście, że pozycja jest martwa.
     */
    public function execute(CommandInput $input): CommandOutcome
    {
        $path = $input->text(self::ARGUMENT);
        $problem = $this->player->add($path);

        if ($problem !== null) {
            return CommandOutcome::stay(Message::error($problem));
        }

        return CommandOutcome::done(Message::info($this->translator->translate(
            'module.' . AudioSettings::ID . '.playlist.added',
            ['track' => basename($path)],
        )));
    }

    public function suggestions(string $argument, string $prefix): array
    {
        return $argument === self::ARGUMENT ? $this->files->suggestions($prefix) : [];
    }
}
