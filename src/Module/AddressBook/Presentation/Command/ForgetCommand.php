<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Command\SuggestionSource;
use LightManager\Application\Command\SuggestsArguments;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\Addresses;
use LightManager\Module\AddressBook\Presentation\AddressBookQueries;
use LightManager\Module\AddressBook\Presentation\Suggestions;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\Overlay\ConfirmOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * `address-book.forget <rozdział>` — usuwa wartości rozdziału ze **wszystkich**
 * wpisów (krok 60).
 *
 * Komenda istnieje jako **wprost nazwany skutek zasady tego kroku**: skoro
 * rozdział nie ma właściciela, a deklaracje nie są zapisywane na dysk, to
 * wartości po module, którego już nie ma, **nie mają kto posprzątać**.
 * Sprzątanie automatyczne byłoby przy tym gorsze niż jego brak: brak deklaracji
 * znaczy „nikt tego dziś nie używa" — moduł bywa wyłączony na jedno
 * uruchomienie — a nie „to jest śmieć".
 *
 * Pyta oknem i pokazuje w pytaniu **liczbę wpisów**, których to dotknie: bez
 * niej „usuń rozdział" brzmi jak czynność na rozdziale, a jest czynnością na
 * całej książce.
 */
final class ForgetCommand implements CommandInterface, SuggestsArguments, OpensOverlay
{
    public const CHAPTER = 'chapter';

    public function __construct(
        private readonly Addresses $addresses,
        private readonly AddressBookQueries $reader,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return AddressBookSettings::ID . '.forget';
    }

    public function descriptionKey(): string
    {
        return AddressBookSettings::key('command.forget');
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::CHAPTER,
                AddressBookSettings::key('argument.chapter'),
                suggestions: SuggestionSource::OnDemand,
            ),
        ];
    }

    public function suggestions(string $argument, string $prefix): array
    {
        return $argument === self::CHAPTER ? Suggestions::chapters($this->reader, $prefix) : [];
    }

    public function overlayFor(CommandInput $input): ?OverlayOutcome
    {
        $chapter = trim($input->text(self::CHAPTER));
        $touched = $this->countOf($chapter);

        if ($chapter === '' || $touched === 0) {
            return null;
        }

        return OverlayOutcome::replace(new ConfirmOverlay(
            AddressBookSettings::key('confirm.forget'),
            ['chapter' => $chapter],
            fn (): OverlayOutcome => OverlayOutcome::close($this->forgotten($chapter)),
            $this->translator,
            dangerous: true,
            count: $touched,
        ));
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        return CommandOutcome::done(Message::info(
            $this->translator->translate(AddressBookSettings::key('message.nothing.forget'), [
                'chapter' => trim($input->text(self::CHAPTER)),
            ]),
        ));
    }

    private function countOf(string $chapter): int
    {
        $count = 0;

        foreach ($this->reader->book()->entries as $entry) {
            if ($entry->hasChapter($chapter)) {
                ++$count;
            }
        }

        return $count;
    }

    private function forgotten(string $chapter): Message
    {
        $touched = $this->addresses->forget($chapter);

        return Message::info($this->translator->translate(AddressBookSettings::key('message.forgotten'), [
            'chapter' => $chapter,
            'count' => (string) $touched,
        ]));
    }
}
