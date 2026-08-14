<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Port\BackgroundProcessPort;
use LightManager\Infrastructure\Process\BackgroundProcessService;

/**
 * Metodyka pomiaru wspólna dla wszystkich torów: rozgrzewka, zimna klatka,
 * szczyt pamięci, proces towarzyszący i sprzątanie po nim.
 *
 * Wydzielona w kroku 38 z konkretnego powodu: torów zrobiło się **trzy**
 * (sixelowy, okienkowy, tekstowy), a reguły metodyki są w nich co do słowa te
 * same. Trzy kopie tej pętli znaczyłyby trzy miejsca, w których trzeba pamiętać,
 * że pierwsza próbka rozgrzewki jest zimną klatką, a licznik pamięci zeruje się
 * przed scenariuszem — czyli dwa miejsca za dużo.
 *
 * Tor różni się wyłącznie **jedną próbką** (`sample()`) i ewentualnym
 * przygotowaniem przebiegu (`prepareRun()`, którego potrzebuje okno). Wszystko
 * pozostałe jest tutaj i obowiązuje wszystkich jednakowo.
 */
abstract class AbstractBenchmarkRunner
{
    /**
     * Ile sekund ma przeżyć proces towarzyszący scenariuszowi `background`.
     *
     * Pięć minut z zapasem starcza na przebieg o stu powtórzeniach w dużym oknie,
     * a jednocześnie jest liczbą skończoną: gdyby narzędzie padło w sposób, którego
     * `finally` nie łapie, potomek zniknie sam.
     */
    protected const COMPANION_SECONDS = 300;

    protected readonly BackgroundProcessPort $processes;

    public function __construct(
        protected readonly ScenarioFactory $factory,
        protected readonly BenchmarkOptions $options,
        ?BackgroundProcessPort $processes = null,
    ) {
        $this->processes = $processes ?? BackgroundProcessService::getInstance();
    }

    /**
     * @param list<Scenario> $scenarios
     *
     * @return list<ScenarioResult>
     */
    public function run(array $scenarios): array
    {
        $this->prepareRun();

        $results = [];

        foreach ($scenarios as $scenario) {
            $results[] = $this->runOne($scenario);
        }

        return $results;
    }

    /**
     * Jeden scenariusz: zimna klatka, rozgrzewka, próbki mierzone.
     *
     * **Pierwsza próbka rozgrzewki przestała być odrzucana** (krok 38, D64) —
     * jest jedynym miejscem, w którym widać koszt płacony przy starcie
     * aplikacji i po każdej zmianie rozmiaru okna, bo pamięci podręczne klatki
     * są w niej puste. Zimno jest tu zimnem **pamięci podręcznych**, a nie
     * procesu: singletony, font i biblioteka graficzna są już ciepłe, bo żyją
     * dłużej niż scenariusz.
     *
     * Szczyt pamięci liczy się **od zera dla każdego scenariusza**: bez
     * `memory_reset_peak_usage()` każdy następny dziedziczyłby szczyt
     * poprzedniego i kolumna pokazywałaby narastającą wartość zamiast kosztu
     * scenariusza.
     */
    protected function runOne(Scenario $scenario): ScenarioResult
    {
        $prepared = $this->factory->build($scenario);
        $work = $this->startCompanion($scenario);
        $cold = null;

        memory_reset_peak_usage();

        try {
            for ($index = 0; $index < $this->options->warmupIterations; ++$index) {
                $sample = $this->sample($prepared, $work);
                $cold ??= $sample;
            }

            $samples = [];

            for ($index = 0; $index < max(1, $this->options->iterations); ++$index) {
                $samples[] = $this->sample($prepared, $work);
            }
        } finally {
            // `finally`, bo przebieg przerwany w połowie nie ma prawa zostawić
            // po sobie procesu — narzędzie pomiarowe podlega tej samej regule,
            // co aplikacja.
            if ($work !== null) {
                $this->processes->stop($work);
            }
        }

        return ScenarioResult::fromSamples($scenario, $samples, $cold, memory_get_peak_usage(true));
    }

    /** Jedna klatka danego toru wraz z podziałem na fazy. */
    abstract protected function sample(ScenarioFrame $prepared, ?BackgroundHandle $work = null): PhaseSample;

    /** Przygotowanie przebiegu; tory terminalowe nie potrzebują żadnego. */
    protected function prepareRun(): void
    {
    }

    /**
     * Doglądanie procesu towarzyszącego **wchodzi do czasu klatki**, bo
     * w aplikacji też do niego wchodzi: ekran pyta o stan raz na klatkę, tuż
     * przed rysowaniem. Osobna faza dla dwóch pustych potoków byłaby kolumną zer
     * w każdym pozostałym scenariuszu.
     */
    protected function pollCompanion(?BackgroundHandle $work): void
    {
        if ($work !== null) {
            $this->processes->poll($work);
        }
    }

    /**
     * Proces potomny towarzyszący pomiarowi — albo `null`, gdy scenariusz go nie
     * zamawia.
     *
     * Polecenie **milczy i śpi**, bo tak właśnie zachowuje się `du`: nie mówi
     * o sobie nic, aż skończy. Limit czasu jest hojny z tego samego powodu, dla
     * którego proces w ogóle tu stoi — ma przeżyć cały przebieg, także ten
     * z setką powtórzeń, a gdyby mimo wszystko nie przeżył, pomiar zmierzyłby
     * klatkę bez sąsiada i cicho skłamał.
     */
    private function startCompanion(Scenario $scenario): ?BackgroundHandle
    {
        return $scenario->needsBackgroundWork()
            ? $this->processes->start('sleep ' . self::COMPANION_SECONDS, self::COMPANION_SECONDS)
            : null;
    }
}
