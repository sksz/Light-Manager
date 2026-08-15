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
use LightManager\Application\Event\EventDeclaration;
use LightManager\Application\Event\EventRegistry;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\Port\TrackFilesPort;
use LightManager\Module\Audio\Application\SoundEffects;

/**
 * `audio.hook <zdarzenie> <ścieżka>` — przypisanie efektu spoza okna modułu
 * (krok 46).
 *
 * Trzecia droga do mapy, obok `F5` i `F7` w oknie, i jedyna, która działa
 * **z każdego miejsca aplikacji**: zbiór komend jest globalny (D39, P18), więc
 * dźwięk da się podpiąć, nie przerywając tego, co się właśnie robi.
 *
 * **Pierwsza komenda projektu z dwoma argumentami podpowiadanymi** i to jest jej
 * jedyny koszt ponad `audio.add`. Podpowiedzi rozdziela `SuggestsArguments` po
 * nazwie argumentu, więc mechanizm był na to gotowy od kroku 19 — pierwszy
 * argument bierze wartości ze **słownika zdarzeń** (lista zamknięta, znana po
 * złożeniu aplikacji), drugi z dysku, tak samo jak ścieżka utworu.
 *
 * Ścieżka pusta **zabiera przypisanie**, zamiast być błędem: `audio.hook
 * core.message.error` czyta się jako „zdejmij dźwięk z tego zdarzenia" i jest
 * jedyną drogą do wyczyszczenia mapy bez otwierania okna.
 */
final class HookCommand implements CommandInterface, SuggestsArguments
{
    private const EVENT = 'event';

    private const PATH = 'path';

    public function __construct(
        private readonly SoundEffects $effects,
        private readonly EventRegistry $events,
        private readonly TrackFilesPort $files,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return AudioSettings::ID . '.hook';
    }

    public function descriptionKey(): string
    {
        return 'module.' . AudioSettings::ID . '.command.hook';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::EVENT,
                'module.' . AudioSettings::ID . '.argument.event',
                CommandArgumentKind::Text,
                suggestions: SuggestionSource::OnDemand,
            ),
            new CommandArgument(
                self::PATH,
                'module.' . AudioSettings::ID . '.argument.path',
                CommandArgumentKind::Path,
                required: false,
                suggestions: SuggestionSource::OnDemand,
            ),
        ];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $event = trim($input->text(self::EVENT));

        if (!$this->events->has($event)) {
            return CommandOutcome::stay(Message::error($this->text('effect.unknownEvent', ['event' => $event])));
        }

        $path = trim($input->text(self::PATH));

        if ($path === '') {
            return $this->effects->clear($event)
                ? CommandOutcome::done(Message::info($this->text('effect.cleared', [
                    'event' => $this->nameOf($event),
                ])))
                : CommandOutcome::done(Message::info($this->text('effect.nothingToClear', [
                    'event' => $this->nameOf($event),
                ])));
        }

        $this->effects->assign($event, $path);

        return CommandOutcome::done(Message::info($this->text('effect.assigned', [
            'event' => $this->nameOf($event),
            'file' => basename($path),
        ])));
    }

    public function suggestions(string $argument, string $prefix): array
    {
        if ($argument === self::PATH) {
            return $this->files->suggestions($prefix);
        }

        if ($argument !== self::EVENT) {
            return [];
        }

        $names = [];

        foreach ($this->events->all() as $declaration) {
            if ($prefix === '' || str_starts_with($declaration->name, $prefix)) {
                $names[] = $declaration->name;
            }
        }

        return $names;
    }

    /** Nazwa zdarzenia widoczna dla użytkownika — a gdy jej nie ma, sama nazwa techniczna. */
    private function nameOf(string $event): string
    {
        foreach ($this->events->all() as $declaration) {
            if ($declaration->name === $event) {
                return $this->labelOf($declaration);
            }
        }

        return $event;
    }

    private function labelOf(EventDeclaration $declaration): string
    {
        return $this->translator->translate($declaration->labelKey);
    }

    /** @param array<string, string> $parameters */
    private function text(string $key, array $parameters = []): string
    {
        return $this->translator->translate('module.' . AudioSettings::ID . '.' . $key, $parameters);
    }
}
