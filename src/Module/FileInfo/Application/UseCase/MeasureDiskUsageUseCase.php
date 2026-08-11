<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application\UseCase;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Dto\BackgroundStage;
use LightManager\Application\Port\BackgroundProcessPort;
use LightManager\Module\FileInfo\Application\Dto\DiskUsageState;

/**
 * Zajętość katalogu wraz z zawartością — poleceniem `du`, procesem tłowym.
 *
 * To jest ta jedna wartość, której `lstat` nie zna i znać nie może: sekcja
 * „Rozmiar” pokazuje od kroku 25 bloki i-węzła, ale i-węzeł katalogu waży cztery
 * kibibajty niezależnie od tego, czy w środku jest pustka, czy pół dysku. Suma
 * po drzewie wymaga przejścia po drzewie, a przejście po drzewie nie mieści się
 * w klatce.
 *
 * **Przypadek użycia jest bezstanowy, a właścicielem pracy jest ekran** — trzecia
 * część wzorca z D46. Uchwyt trzyma `FileInfoState`, bo to on wie, kiedy
 * zaznaczenie się zmienia i kiedy ekran się zamyka; gdyby trzymał go przypadek
 * użycia, przerywanie pracy zależałoby od tego, kto akurat pamięta, żeby o nie
 * poprosić.
 *
 * **Wiersz polecenia składa się tutaj, a nie w infrastrukturze**, i jest to
 * zgodne z kontraktem portu, który przyjmuje gotowe polecenie i mówi wprost, że
 * cytowanie należy do wołającego. Powód jest taki, że „czym zmierzyć zajętość”
 * to wiedza tego modułu, a nie rdzenia — rdzeń umie uruchomić proces i na tym
 * jego wiedza o świecie się kończy. Ścieżka idzie przez `escapeshellarg()`,
 * a `--` zamyka listę opcji, żeby katalog o nazwie zaczynającej się od myślnika
 * nie został wzięty za flagę.
 */
final class MeasureDiskUsageUseCase
{
    /**
     * `-s` sumuje zamiast wypisywać drzewo, `-B1` każe podać wynik w bajtach.
     *
     * `-B1` jest tu ważniejsze, niż wygląda: bez niego `du` liczy w kibibajtach
     * i wynik trzeba by mnożyć, a mnożenie zaokrąglonej liczby daje wartość
     * dokładną z wyglądu i przybliżoną w istocie. `--apparent-size` **celowo
     * nie ma**: pytamy o zajętość na dysku, a nie o sumę rozmiarów, i te dwie
     * liczby różnią się dla plików rzadkich w obie strony.
     */
    private const COMMAND = 'du -sB1 --';

    /** Wynik `du`: liczba bajtów, tabulator, ścieżka. Bierzemy pierwszą liczbę. */
    private const OUTPUT_PATTERN = '/^\s*(\d+)/';

    public function __construct(private readonly BackgroundProcessPort $processes)
    {
    }

    /** Zamawia pomiar; nie czeka na niego ani chwili. */
    public function begin(string $path, int $timeoutSeconds): BackgroundHandle
    {
        return $this->processes->start(self::COMMAND . ' ' . escapeshellarg($path), $timeoutSeconds);
    }

    /**
     * Stan procesu przełożony na język tego modułu.
     *
     * Kod wyjścia różny od zera **nie jest sam z siebie powodem niepowodzenia**
     * i to jest jedyne miejsce, w którym ten przypadek użycia robi coś więcej
     * niż tłumaczenie: `du` kończy się jedynką za każdy katalog, do którego nie
     * miało dostępu, a mimo to podaje na wyjściu sumę tego, co przeczytało.
     * Odrzucenie takiego wyniku zamieniłoby zwyczajny katalog domowy z jednym
     * cudzym podkatalogiem w „nie udało się policzyć”.
     *
     * Niepowodzeniem jest dopiero **brak liczby na wyjściu** — polecenia nie ma
     * w systemie, nie zna flagi `-B` albo nie przeczytało niczego.
     */
    public function read(BackgroundHandle $handle): DiskUsageState
    {
        $state = $this->processes->poll($handle);

        return match ($state->stage) {
            BackgroundStage::Idle => DiskUsageState::idle(),
            BackgroundStage::Running => DiskUsageState::running(),
            BackgroundStage::Failed => DiskUsageState::failed(
                $state->problemKey ?? 'module.file-info.diskUsage.failed',
                $state->problemParameters,
            ),
            BackgroundStage::Done => $this->measured($state->output),
        };
    }

    public function stop(BackgroundHandle $handle): void
    {
        $this->processes->stop($handle);
    }

    private function measured(string $output): DiskUsageState
    {
        $matches = [];

        if (preg_match(self::OUTPUT_PATTERN, $output, $matches) !== 1) {
            return DiskUsageState::failed('module.file-info.diskUsage.failed');
        }

        return DiskUsageState::measured((int) $matches[1]);
    }
}
