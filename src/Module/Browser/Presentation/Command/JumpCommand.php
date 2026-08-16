<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandArgumentKind;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Command\SuggestionSource;
use LightManager\Application\Command\SuggestsArguments;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\Exception\DomainException;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\Repository\DirectoryRepositoryInterface;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Presentation\BrowserPanes;
use LightManager\Module\Browser\Presentation\BrowserQueries;

/**
 * `browser.jump <ścieżka>` — skok do wskazanego katalogu.
 *
 * Pierwsza komenda modułu w projekcie i zarazem **pierwsza implementacja
 * podpowiedzi liczonych na żądanie** (`SuggestionSource::OnDemand`): krok 19
 * zadeklarował ten rodzaj, ale nie miał dla niego użytkownika, bo żadna komenda
 * rdzenia nie przyjmuje ścieżki.
 *
 * Powstała w kroku 20 jako `file-info.jump`, bo `FileInfo` był wtedy jedynym
 * modułem, który mógł ją wnieść. Krok 21 przeniósł ją tam, gdzie należy: po
 * wyprowadzeniu nawigacji z rdzenia **tylko przeglądarka umie zmienić katalog**.
 * Sam mechanizm podpowiadania ścieżek przeniósł się bez zmian.
 *
 * Komenda leży w warstwie `Presentation` modułu, a nie w jego `Application` —
 * dokładnie z tego samego powodu, dla którego `ScreenCommand` i `SettingCommand`
 * rdzenia leżą w `Presentation/Cli/Command`: dostaje obiekt warstwy dostarczania.
 * Kontrakt komendy zostaje w `Application`, implementacja idzie tam, gdzie
 * mieszkają jej zależności.
 *
 * Ekran modułu nie jest do niej potrzebny: zbiór komend jest globalny (D39, P18),
 * więc skok działa także wtedy, gdy na wierzchu stoją ustawienia albo inny moduł.
 */
final class JumpCommand implements CommandInterface, SuggestsArguments
{
    private const ARGUMENT = 'path';

    public function __construct(
        private readonly BrowserPanes $panes,
        /** Odczyt danych przeglądarki — przez rejestr kwerend (krok 53, D92 nr 3). */
        private readonly BrowserQueries $queries,
        private readonly DirectoryRepositoryInterface $directories,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return BrowserSettings::ID . '.jump';
    }

    public function descriptionKey(): string
    {
        return 'module.' . BrowserSettings::ID . '.command.jump';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::ARGUMENT,
                'module.' . BrowserSettings::ID . '.argument.path',
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
                $this->queries->showsHidden(),
            );
        } catch (DomainException) {
            return CommandOutcome::stay(Message::error($this->translator->translate(
                'module.' . BrowserSettings::ID . '.jump.failed',
                ['path' => $value],
            )));
        }

        $this->panes->focused()->enter($directory);

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
     *
     * Sam rachunek zszedł w kroku 42 do `DirectoryPath`, gdy zamówiło go drugie
     * miejsce: okno pytające o katalog docelowy kopiowania.
     */
    private function resolved(string $value): DirectoryPath
    {
        return DirectoryPath::resolvedFrom($value, $this->queries->directory()->path());
    }
}
