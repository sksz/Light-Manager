<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandRegistry;
use LightManager\Application\Port\SettingsPort;
use LightManager\Module\Kubernetes\Application\Clusters;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;
use LightManager\Module\Kubernetes\Application\Port\KubernetesStatePort;
use LightManager\Presentation\Cli\LoopState;

/**
 * Zapowiedź użycia rozdziału `k8s` wraz z przeniesieniem starej książki
 * klastrów (krok 60, etap 3).
 *
 * Trzecia i ostatnia klasa tego kształtu — po `SshChapter` i `DockerChapter`.
 * To, że wszystkie trzy wyszły niemal identyczne, jest **wynikiem, a nie
 * powtórzeniem**: mechanizm miał kosztować tyle samo przy trzecim rozdziale, co
 * przy pierwszym, i kosztuje. Różnią się wyłącznie spisem pól i tym, co trzeba
 * przeliczyć przy migracji.
 *
 * Rozdział niesie **dwie współrzędne miejsca** (plik `kubeconfig` i nazwę
 * kontekstu) oraz dwie rzeczy, które opisują sposób rozmowy z nim: przestrzeń
 * nazw i limit czasu. Materiału uwierzytelnienia tu nie ma i nie będzie —
 * leży w pliku `kubeconfig`, do którego aplikacja **nie pisze** (zdanie z kroków
 * 52 i 59 zostaje w mocy).
 */
final class KubernetesChapter
{
    public const ID = KubernetesSettings::ID;

    private const DECLARE_CHAPTER = 'address-book.chapter';

    private const DECLARE_FIELD = 'address-book.field';

    private const ADD_ENTRY = 'address-book.add';

    private const SET_VALUE = 'address-book.set';

    /** Pola rozdziału, w kolejności kolumn i pytań łańcucha okien. */
    private const FIELDS = ['kubeconfig', 'context', 'namespace', 'timeout'];

    private bool $declared = false;

    public function __construct(
        private readonly LoopState $state,
        private readonly KubernetesQueries $reader,
        private readonly KubernetesStatePort $storage,
        private readonly SettingsPort $settings,
    ) {
    }

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

    /**
     * Zapisuje pole wpisu — **zamówienie koordynatora**, wykonane komendą
     * książki.
     */
    public function write(string $entry, string $field, string $value): void
    {
        $this->state->commands()->find(self::SET_VALUE)?->execute(new CommandInput([
            'entry' => $entry,
            'chapter' => self::ID,
            'field' => $field,
            'value' => $value,
        ]));
    }

    private function declare(CommandRegistry $commands): void
    {
        $commands->find(self::DECLARE_CHAPTER)?->execute(new CommandInput([
            'chapter' => self::ID,
            'title' => 'module.' . self::ID . '.name',
        ]));

        $field = $commands->find(self::DECLARE_FIELD);

        foreach (self::declarations() as $declaration) {
            $field?->execute(new CommandInput($declaration));
        }
    }

    /** @return list<array<string, string>> */
    private static function declarations(): array
    {
        $label = static fn (string $field): string => 'module.' . self::ID . '.field.' . $field;

        return [
            [
                'chapter' => self::ID,
                'field' => 'kubeconfig',
                'label' => $label('kubeconfig'),
                'kind' => 'text',
                'default' => Clusters::defaultConfigPath(),
            ],
            ['chapter' => self::ID, 'field' => 'context', 'label' => $label('context'), 'kind' => 'text'],
            ['chapter' => self::ID, 'field' => 'namespace', 'label' => $label('namespace'), 'kind' => 'text'],
            ['chapter' => self::ID, 'field' => 'timeout', 'label' => $label('timeout'), 'kind' => 'number'],
        ];
    }

    /**
     * Przenosi stary spis klastrów, a przy pustej sekcji także **dwie pozycje
     * ustawień** z czasów sprzed kroku 59.
     *
     * Druga droga jest tą samą migracją, którą krok 59 zrobił z ustawień do
     * własnej książki — tylko celem jest teraz książka wspólna. Warunek zostaje
     * ten sam: wolno ją zrobić **wyłącznie przy świeżej sekcji**, bo inaczej
     * każdy start nadpisywałby wybór użytkownika.
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

        $fresh = $this->storage->isFresh();

        foreach ($this->storage->legacyClusters() as $cluster) {
            $this->adopt($add, $set, $cluster);
        }

        if ($fresh) {
            $this->adoptSetting($add, $set);
        }

        $this->storage->markMigrated();
    }

    /**
     * @param array<string, string|int> $cluster
     */
    private function adopt(CommandInterface $add, CommandInterface $set, array $cluster): void
    {
        $name = (string) ($cluster['name'] ?? '');
        $add->execute(new CommandInput(['name' => $name]));
        $id = $this->reader->lastAddedEntry();

        if ($id === '') {
            return;
        }

        foreach (self::FIELDS as $field) {
            $value = (string) ($cluster[$field] ?? '');

            if ($value !== '') {
                $set->execute(new CommandInput([
                    'entry' => $id,
                    'chapter' => self::ID,
                    'field' => $field,
                    'value' => $value,
                ]));
            }
        }

        if ($this->storage->current() === $name) {
            $this->storage->makeCurrent($id);
        }
    }

    /** Zapamiętany kontekst z zakładki ustawień — wpis powstaje z niego raz. */
    private function adoptSetting(CommandInterface $add, CommandInterface $set): void
    {
        $settings = $this->settings->current();
        $context = KubernetesSettings::contextFrom($settings);

        if ($context === '') {
            return;
        }

        $this->adopt($add, $set, [
            'name' => $context,
            'kubeconfig' => Clusters::defaultConfigPath(),
            'context' => $context,
            'namespace' => KubernetesSettings::namespaceFrom($settings),
        ]);

        if ($this->storage->current() === '') {
            $this->storage->makeCurrent($this->reader->lastAddedEntry());
        }
    }
}
