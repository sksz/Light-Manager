<?php

declare(strict_types=1);

namespace LightManager\Examples\ModulPrzykladowy;

use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleSettingsTab;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Application\Module\ProvidesCommands;
use LightManager\Application\Module\ProvidesQueries;
use LightManager\Application\Module\ProvidesSettingsTab;
use LightManager\Application\Port\SettingsPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Examples\ModulPrzykladowy\Command\PowitanieCommand;
use LightManager\Examples\ModulPrzykladowy\Query\StanQuery;

/**
 * Moduł przykładowy — wzorzec dla przewodnika „Nowy moduł”
 * (`docs/pl/przewodnik/03-jak-dodac.md`).
 *
 * **Nie jest wpisany do `Bootstrapu` i nie ma być.** Przykład dydaktyczny
 * w spisie modułów byłby modułem bez odbiorcy, a tego zabrania reguła 13.
 * Wskazuje go dokumentacja; bramka jakości analizuje go razem z `src/`.
 *
 * Cztery rzeczy do przepisania do własnego modułu:
 *
 * 1. **Baza kontraktu to sama tożsamość** — kim moduł jest, jak się nazywa
 *    i czym się go otwiera. Wszystko, co moduł naprawdę wnosi, jest **osobną
 *    zdolnością**, deklarowaną przez implementację interfejsu. Moduł bez ani
 *    jednej zdolności jest legalny i nic złego z tego nie wynika.
 * 2. **Zdolności mówiące danymi leżą w `Application`** (`ProvidesCommands`,
 *    `ProvidesQueries`, `ProvidesSettingsTab`), a te, które wymieniają typ
 *    z `Presentation` — w `Presentation\Ui\Module` (`ProvidesScreen`,
 *    `ProvidesHelpTab`, `ReadsContext`). Granicą jest **typ w sygnaturze**,
 *    nie przeczucie.
 * 3. **Moduł bez ekranu oddaje `null` ze `shortcut()`** — skrót bez okna, które
 *    miałby otworzyć, byłby obietnicą bez pokrycia. Ten moduł wnosi wyłącznie
 *    komendę, kwerendę i pozycję ustawień, więc skrótu nie ma.
 * 4. **Dopisanie modułu kosztuje jedną linię w `Bootstrapie`** — i to jest
 *    miara, nie życzenie: jeśli kosztuje więcej, coś w module jest zrobione
 *    źle (reguła 15). Ten kosztowałby dokładnie tyle:
 *    `new PrzykladModule($translator, $settings),`.
 *
 * Ekran, zakładkę pomocy i takt pokazuje moduł prawdziwy — `AddressBookModule`
 * w `src/Module/AddressBook/Presentation/`. Wzorca, który w aplikacji **jest**,
 * dokumentacja nie kopiuje do `examples/` (konwencja z `docs/KONWENCJE.md`).
 */
final class PrzykladModule implements
    ModuleInterface,
    ProvidesCommands,
    ProvidesQueries,
    ProvidesSettingsTab
{
    public function __construct(
        private readonly TranslatorPort $translator,
        private readonly SettingsPort $settings,
    ) {
    }

    public function id(): string
    {
        return PrzykladSettings::ID;
    }

    public function nameKey(): string
    {
        return PrzykladSettings::key('name');
    }

    public function descriptionKey(): string
    {
        return PrzykladSettings::key('description');
    }

    /** Modułu bez ekranu nie ma czym otworzyć — i to jest poprawna odpowiedź. */
    public function shortcut(): ?ModuleShortcut
    {
        return null;
    }

    public function translations(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'lang';
    }

    public function settingsTab(): ModuleSettingsTab
    {
        return new ModuleSettingsTab($this->nameKey(), PrzykladSettings::declarations());
    }

    public function commands(): array
    {
        return [new PowitanieCommand($this->translator, fn () => $this->settings->current())];
    }

    public function queries(): array
    {
        return [new StanQuery(fn () => $this->settings->current())];
    }
}
