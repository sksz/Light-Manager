<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandRegistry;
use LightManager\Application\Port\SettingsPort;
use LightManager\Module\Ssh\Application\Port\SshStatePort;
use LightManager\Module\Ssh\Application\SshSettings;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Presentation\Cli\LoopState;

/**
 * Zapowiedź użycia rozdziału `ssh` wraz z przeniesieniem starej książki
 * (krok 60).
 *
 * **Cała wiedza tego modułu o książce adresowej mieści się w tej klasie i w
 * `SshQueries`** — a składa się z samych **nazw komend i kwerend** (15g). Typu
 * modułu książki nie widzi tu nic; pilnuje tego `NoModuleKnowsAnotherModuleTest`.
 *
 * **Deklaracja jest jednostronna**: pięć wywołań komendy i ani jednego pytania
 * z powrotem. Rozdział `ssh` niesie **adres, użytkownika i materiał
 * uwierzytelnienia** — i nic ponad to. Zapamiętany katalog zdalny **nie jest
 * polem rozdziału**: to pamięć tego modułu, więc mieszka w jego sekcji
 * dokumentu stanu (patrz `SshStatePort`).
 *
 * **Pada raz na uruchomienie.** Komendy są idempotentne, ale takt biegnie
 * trzydzieści razy na sekundę, a takt ma być tani (11o'). Skutek uboczny jest
 * zamierzony: pozycja ustawień „sposób uwierzytelnienia" wchodzi do deklaracji
 * jako **wartość domyślna pola** i zmienia się dopiero przy następnym
 * uruchomieniu — inaczej zmiana pozycji w trakcie oznaczałaby deklarację
 * sprzeczną z tą, która już stoi, a taka nie przestawia pola (D104 nr 2).
 *
 * **Przeniesienie starej książki idzie tą samą drogą, co deklaracja** — bo nie
 * ma innej. Książka nie czyta cudzych sekcji dokumentu stanu, więc wpisy
 * przenosi ten, kto je tam zostawił: czyta swój stary klucz, dopisuje wpisy
 * komendą i pyta `address-book.last` o identyfikator każdego z nich. Stare
 * klucze **zostają na dysku nietknięte** (migracja nieniszcząca, D103),
 * a o tym, że przeniesienie się odbyło, mówi osobny znacznik.
 */
final class SshChapter
{
    public const ID = SshSettings::ID;

    private const DECLARE_CHAPTER = 'address-book.chapter';

    private const DECLARE_FIELD = 'address-book.field';

    private const ADD_ENTRY = 'address-book.add';

    private const SET_VALUE = 'address-book.set';

    /** Pola rozdziału, w kolejności, w jakiej mają stać w kolumnach i w oknach. */
    private const FIELDS = ['host', 'port', 'user', 'auth', 'keyPath'];

    private bool $declared = false;

    public function __construct(
        private readonly LoopState $state,
        private readonly SshQueries $reader,
        private readonly SshStatePort $storage,
        private readonly SettingsPort $settings,
    ) {
    }

    /**
     * Takt: zapowiedz użycie rozdziału, a przy pierwszym uruchomieniu przenieś
     * stary spis.
     *
     * Brak książki (moduł wyłączony przez użytkownika) **nie jest awarią**:
     * deklaracja po prostu nie ma dokąd pójść i spróbuje w następnym takcie.
     * Moduł sesji zdalnej działa wtedy bez spisu — i to jest uczciwsze niż
     * odmowa startu, bo książki nie wyłączył on.
     */
    public function tick(): void
    {
        if ($this->declared) {
            return;
        }

        $commands = $this->state->commands();

        if ($commands->find(self::DECLARE_CHAPTER) === null || $commands->find(self::DECLARE_FIELD) === null) {
            return;
        }

        $this->declared = true;
        $this->declare($commands);
        $this->migrate($commands);
    }

    private function declare(CommandRegistry $commands): void
    {
        $commands->find(self::DECLARE_CHAPTER)?->execute(new CommandInput([
            'chapter' => self::ID,
            'title' => 'module.' . self::ID . '.name',
        ]));

        $field = $commands->find(self::DECLARE_FIELD);

        foreach ($this->declarations() as $declaration) {
            $field?->execute(new CommandInput($declaration));
        }
    }

    /** @return list<array<string, string>> */
    private function declarations(): array
    {
        $label = static fn (string $field): string => 'module.' . self::ID . '.field.' . $field;

        return [
            ['chapter' => self::ID, 'field' => 'host', 'label' => $label('host'), 'kind' => 'text'],
            [
                'chapter' => self::ID,
                'field' => 'port',
                'label' => $label('port'),
                'kind' => 'number',
                'default' => (string) HostProfile::DEFAULT_PORT,
            ],
            ['chapter' => self::ID, 'field' => 'user', 'label' => $label('user'), 'kind' => 'text'],
            [
                'chapter' => self::ID,
                'field' => 'auth',
                'label' => $label('auth'),
                'kind' => 'choice',
                'default' => SshSettings::authFrom($this->settings->current())->value,
                'choices' => 'agent,key,password',
            ],
            ['chapter' => self::ID, 'field' => 'keyPath', 'label' => $label('keyPath'), 'kind' => 'secret'],
        ];
    }

    /**
     * Przenosi stary spis do książki — **raz, komendami, nieniszcząco**.
     *
     * Zapamiętane katalogi przekluczają się przy okazji z nazwy wpisu na jego
     * identyfikator: to ta sama pamięć, tylko pod tożsamością, która odtąd
     * obowiązuje.
     */
    private function migrate(CommandRegistry $commands): void
    {
        if ($this->storage->isMigrated()) {
            return;
        }

        $add = $commands->find(self::ADD_ENTRY);
        $set = $commands->find(self::SET_VALUE);

        if ($add === null || $set === null) {
            return;
        }

        foreach ($this->storage->legacyHosts() as $host) {
            $name = (string) ($host['name'] ?? '');
            $add->execute(new CommandInput(['name' => $name]));
            $id = $this->reader->lastAddedEntry();

            if ($id === '') {
                continue;
            }

            foreach (self::FIELDS as $field) {
                if (isset($host[$field])) {
                    $set->execute(new CommandInput([
                        'entry' => $id,
                        'chapter' => self::ID,
                        'field' => $field,
                        'value' => (string) $host[$field],
                    ]));
                }
            }

            $directory = $this->storage->lastDirectory($name);

            if ($directory !== null) {
                $this->storage->rememberDirectory($id, $directory);
            }
        }

        $this->storage->markMigrated();
    }
}
