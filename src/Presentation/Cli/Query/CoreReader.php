<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Query;

use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Query\QueryRegistry;

/**
 * Odczyt danych rdzenia — **przez rejestr kwerend, jak w każdym module**
 * (krok 53, D92 nr 3).
 *
 * Czwarta fasada tego kroku i ostatnia, która powstała: trzy pierwsze
 * (`BrowserQueries`, `FileInfoQueries`, `AudioQueries`) domknęły odczyt
 * w modułach, a rdzeń czytał własne ustawienia wprost ze stanu pętli — czyli
 * reguła obowiązywała wszystkich poza tym, kto ją ustanowił. To jest ta różnica,
 * którą ta klasa znosi.
 *
 * Zasada jest ta sama, co w fasadach modułów: rozpakowanie ładunku stoi
 * w **jednym** miejscu, a brak odpowiedzi jest zwykłym stanem — wtedy wraca
 * wartość domyślna, nie wyjątek. Pusty komplet ustawień i pusty kontekst są przy
 * tym poprawnymi odpowiedziami, a nie awaryjnymi: dokładnie takie same dostaje
 * aplikacja, zanim ktokolwiek cokolwiek opublikuje.
 */
final readonly class CoreReader
{
    public function __construct(
        private QueryRegistry $queries,
    ) {
    }

    public function settings(): Settings
    {
        $payload = $this->payloadOf('settings');

        return $payload instanceof Settings ? $payload : new Settings();
    }

    public function context(): ModuleContext
    {
        $payload = $this->payloadOf('context');

        return $payload instanceof ModuleContext ? $payload : new ModuleContext();
    }

    private function payloadOf(string $query): ?object
    {
        return $this->queries
            ->ask(QueryRegistry::CORE . '.' . $query)
            ->payloadFor(QueryRegistry::CORE);
    }
}
