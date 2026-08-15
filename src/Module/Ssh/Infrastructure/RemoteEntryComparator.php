<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Infrastructure;

use Collator;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntry;
use Throwable;

/**
 * Porządkuje wpisy zdalnego katalogu: najpierw katalogi, potem reszta, w obu
 * grupach alfabetycznie bez rozróżniania wielkości liter (krok 49).
 *
 * **Powtarza `EntryComparator` przeglądarki świadomie** — wedle tej samej
 * granicy, co `RemotePath` i `RemoteEntry`. Powtórzenie jest tu przy tym
 * najtańsze z całej trójki i najbardziej oczywiste: reguła porządku jest
 * własnością tego, kto listę pokazuje.
 *
 * Sortowanie robimy **u siebie, a nie po drugiej stronie**, choć `sftp ls` umie
 * sortować sam. Powód jest wymierny: klient sortuje bajtami, więc `Ż` wypada
 * u niego za `z`, a `Collator` z rozszerzenia `intl` stawia `ą` obok `a` — czyli
 * tam, gdzie użytkownik go szuka i gdzie stoi w liście lokalnej. Polecenie
 * prosi zresztą o `-f` („nie sortuj”), żeby serwer i klient nie robili tej samej
 * pracy dwa razy.
 */
final class RemoteEntryComparator
{
    private const FALLBACK_TRANSLITERATION = [
        'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
        'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
    ];

    public function __construct(
        private readonly ?Collator $collator,
    ) {
    }

    public static function create(): self
    {
        return new self(self::createCollator());
    }

    /**
     * @param list<RemoteEntry> $entries
     *
     * @return list<RemoteEntry>
     */
    public function sort(array $entries): array
    {
        usort($entries, function (RemoteEntry $left, RemoteEntry $right): int {
            if ($left->isDirectory() !== $right->isDirectory()) {
                return $left->isDirectory() ? -1 : 1;
            }

            return $this->compareNames($left->name, $right->name);
        });

        return $entries;
    }

    private function compareNames(string $left, string $right): int
    {
        if ($this->collator !== null) {
            $result = $this->collator->compare($left, $right);

            if ($result !== false) {
                return $result;
            }
        }

        return strcmp(self::simplified($left), self::simplified($right));
    }

    private static function simplified(string $name): string
    {
        return strtr(mb_strtolower($name, 'UTF-8'), self::FALLBACK_TRANSLITERATION);
    }

    private static function createCollator(): ?Collator
    {
        if (!extension_loaded('intl')) {
            return null;
        }

        try {
            $collator = new Collator(locale_get_default());
        } catch (Throwable) {
            return null;
        }

        // Wielkość liter ma nie wpływać na kolejność, ale nadal rozróżniamy
        // znaki diakrytyczne, żeby „lan” i „łan” nie zlały się w jedno.
        $collator->setStrength(Collator::SECONDARY);

        return $collator;
    }
}
