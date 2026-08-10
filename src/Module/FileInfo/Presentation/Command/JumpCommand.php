<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandArgumentKind;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Command\SuggestionSource;
use LightManager\Application\Command\SuggestsArguments;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\Exception\DomainException;
use LightManager\Domain\Repository\DirectoryRepositoryInterface;
use LightManager\Domain\ValueObject\DirectoryPath;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\FileInfo\Application\FileInfoSettings;
use LightManager\Presentation\Cli\LoopState;

/**
 * `file-info.jump <ścieżka>` — skok do wskazanego katalogu.
 *
 * Pierwsza komenda modułu w projekcie i zarazem **pierwsza implementacja
 * podpowiedzi liczonych na żądanie** (`SuggestionSource::OnDemand`): krok 19
 * zadeklarował ten rodzaj, ale nie miał dla niego użytkownika, bo żadna komenda
 * rdzenia nie przyjmuje ścieżki. Uzupełnianie ścieżek powstaje więc tutaj, przy
 * swoim użytkowniku (zasada P5 kroku 18).
 *
 * Komenda leży w warstwie `Presentation` modułu, a nie w jego `Application` —
 * dokładnie z tego samego powodu, dla którego `ScreenCommand` i `SettingCommand`
 * rdzenia leżą w `Presentation/Cli/Command`: dostaje **stan pętli**, a ten jest
 * obiektem warstwy dostarczania. Kontrakt komendy zostaje w `Application`,
 * implementacja idzie tam, gdzie mieszkają jej zależności.
 *
 * Ekran modułu nie jest do niej potrzebny: zbiór komend jest globalny (D39, P18),
 * więc skok działa niezależnie od tego, czy `FileInfo` jest otwarty. W kroku 21
 * komenda przenosi się do modułu przeglądarki jako `browser.jump` — po
 * wyprowadzeniu nawigacji tylko ona będzie umiała zmienić katalog (D40, P8).
 */
final class JumpCommand implements CommandInterface, SuggestsArguments
{
    private const ARGUMENT = 'path';

    public function __construct(
        private readonly LoopState $state,
        private readonly DirectoryRepositoryInterface $directories,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return FileInfoSettings::ID . '.jump';
    }

    public function descriptionKey(): string
    {
        return 'module.' . FileInfoSettings::ID . '.command.jump';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::ARGUMENT,
                'module.' . FileInfoSettings::ID . '.argument.path',
                CommandArgumentKind::Path,
                suggestions: SuggestionSource::OnDemand,
            ),
        ];
    }

    /**
     * Ścieżka nieistniejąca albo nieczytelna **nie zamyka okna**: użytkownik
     * wpisał ją ręcznie, więc ma ją gdzie poprawić — ta sama reguła, którą kieruje
     * się `core.theme` przy nazwie motywu spoza listy.
     */
    public function execute(CommandInput $input): CommandOutcome
    {
        $value = $input->text(self::ARGUMENT);

        try {
            $directory = $this->directories->get(
                $this->resolved($value),
                $this->state->showsHiddenEntries(),
            );
        } catch (DomainException) {
            return CommandOutcome::stay(Message::error($this->translator->translate(
                'module.' . FileInfoSettings::ID . '.jump.failed',
                ['path' => $value],
            )));
        }

        $this->state->enterDirectory($directory);

        return CommandOutcome::done();
    }

    /**
     * Katalogi pasujące do wpisanego przedrostka.
     *
     * Liczone **na żądanie**, bo zawartość dysku zmienia się pod ręką
     * użytkownika: policzona z góry byłaby kłamstwem, a i tak nie zmieściłaby się
     * w pamięci. Katalog nieczytelny nie jest tu błędem — podpowiedzi po prostu
     * nie ma.
     *
     * @return list<string>
     */
    public function suggestions(string $argument, string $prefix): array
    {
        if ($argument !== self::ARGUMENT) {
            return [];
        }

        $separator = strrpos($prefix, '/');
        $head = $separator === false ? '' : substr($prefix, 0, $separator + 1);
        $needle = $separator === false ? $prefix : substr($prefix, $separator + 1);

        try {
            $directory = $this->directories->get(
                $this->resolved($head),
                str_starts_with($needle, '.'),
            );
        } catch (DomainException) {
            return [];
        }

        $values = [];

        foreach ($directory->entries() as $entry) {
            if (!$entry->isDirectory() || ($needle !== '' && !str_starts_with($entry->name, $needle))) {
                continue;
            }

            // Ukośnik na końcu pozwala uzupełniać dalej, w głąb — bez niego
            // `Tab` zatrzymywałby się na każdym poziomie drzewa.
            $values[] = $head . $entry->name . '/';
        }

        return $values;
    }

    /**
     * Ścieżka bezwzględna z tego, co wpisał użytkownik. Wartość względna liczy
     * się od bieżącego miejsca — tak samo, jak liczyłaby ją powłoka.
     */
    private function resolved(string $value): DirectoryPath
    {
        if (str_starts_with($value, '/')) {
            return new DirectoryPath($value);
        }

        $current = $this->state->directory()->path();

        return $value === '' ? $current : new DirectoryPath($current->value . '/' . $value);
    }
}
