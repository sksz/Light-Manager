<?php

declare(strict_types=1);

namespace LightManager\Examples\ModulPrzykladowy\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Examples\ModulPrzykladowy\PrzykladSettings;

/**
 * `przyklad.powitanie` — wzorzec komendy dla przewodnika „Nowa komenda”
 * (`docs/pl/przewodnik/03-jak-dodac.md`).
 *
 * Cztery rzeczy do przepisania do własnej komendy:
 *
 * 1. **Nazwa zaczyna się od identyfikatora właściciela.** Przedrostka pilnuje
 *    `CommandRegistry`, więc kolizja między modułami jest niemożliwa
 *    z konstrukcji — a użytkownik czyta z nazwy, czyja to czynność.
 * 2. **Argumenty się deklaruje, a nie rozbiera.** Wiersz dzieli, mapuje
 *    i sprawdza jeden parser w rdzeniu, więc każda komenda tłumaczy się
 *    użytkownikowi tak samo. Argument nieobowiązkowy to `required: false`.
 * 3. **Komenda nie zna okna, które ją wywołało.** Skutek oddaje
 *    `CommandOutcome`; ekran do otwarcia wskazuje się **identyfikatorem**
 *    (`CommandOutcome::opens('nazwa-ekranu')`), a nie obiektem — `ScreenInterface`
 *    leży w warstwie dostarczania i `Application` go nie widzi (D39).
 * 4. **Żaden napis nie jest wpisany w kod.** Komenda niesie klucz katalogu,
 *    a zdanie dla użytkownika składa tłumacz.
 */
final class PowitanieCommand implements CommandInterface
{
    public const IMIE = 'imie';

    public function __construct(
        private readonly TranslatorPort $translator,
        /** Domknięcie, bo ustawienia zmieniają się między klatkami — wartość zestarzałaby się. */
        private readonly \Closure $settings,
    ) {
    }

    public function name(): string
    {
        return PrzykladSettings::ID . '.powitanie';
    }

    public function descriptionKey(): string
    {
        return PrzykladSettings::key('command.powitanie');
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::IMIE,
                PrzykladSettings::key('argument.imie'),
                required: false,
            ),
        ];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $settings = ($this->settings)();
        assert($settings instanceof Settings);

        $imie = $input->has(self::IMIE) ? $input->text(self::IMIE) : '';

        $klucz = PrzykladSettings::mowiGlosno($settings)
            ? PrzykladSettings::key('message.glosne')
            : PrzykladSettings::key('message.zwykle');

        return CommandOutcome::done(Message::info(
            $this->translator->translate($klucz, ['imie' => $imie]),
        ));
    }
}
