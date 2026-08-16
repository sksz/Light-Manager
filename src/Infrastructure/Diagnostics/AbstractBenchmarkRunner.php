<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Port\BackgroundProcessPort;
use LightManager\Application\Port\BackgroundPumpPort;
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

    /**
     * Pompowanie potoków — ta sama faza, którą pętla główna wykonuje raz na
     * klatkę (krok 51).
     *
     * `null` znaczy „port podany z zewnątrz nie umie pompować” i zdarza się
     * wyłącznie w testach: prawdziwa usługa obsługuje oba porty. Bez tej fazy
     * pomiar scenariuszy tłowych **kłamałby w jedną stronę** — praca, której
     * nikt nie pompuje, nie kończy się nigdy i nie kosztuje nic.
     */
    private readonly ?BackgroundPumpPort $pump;

    /**
     * Prace towarzyszące bieżącemu scenariuszowi.
     *
     * @var list<BackgroundHandle>
     */
    private array $companions = [];

    public function __construct(
        protected readonly ScenarioFactory $factory,
        protected readonly BenchmarkOptions $options,
        ?BackgroundProcessPort $processes = null,
    ) {
        $this->processes = $processes ?? BackgroundProcessService::getInstance();
        $this->pump = $this->processes instanceof BackgroundPumpPort ? $this->processes : null;
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
        $this->startCompanions($scenario);
        $cold = null;

        memory_reset_peak_usage();

        try {
            for ($index = 0; $index < $this->options->warmupIterations; ++$index) {
                $sample = $this->sample($prepared);
                $cold ??= $sample;
            }

            $samples = [];

            for ($index = 0; $index < max(1, $this->options->iterations); ++$index) {
                $samples[] = $this->sample($prepared);
            }
        } finally {
            // `finally`, bo przebieg przerwany w połowie nie ma prawa zostawić
            // po sobie procesów — narzędzie pomiarowe podlega tej samej regule,
            // co aplikacja.
            $this->stopCompanions();
        }

        return ScenarioResult::fromSamples($scenario, $samples, $cold, memory_get_peak_usage(true));
    }

    /** Jedna klatka danego toru wraz z podziałem na fazy. */
    abstract protected function sample(ScenarioFrame $prepared): PhaseSample;

    /** Przygotowanie przebiegu; tory terminalowe nie potrzebują żadnego. */
    protected function prepareRun(): void
    {
    }

    /**
     * Doglądanie procesów towarzyszących **wchodzi do czasu klatki**, bo
     * w aplikacji też do niego wchodzi: pętla pompuje potoki raz na klatkę,
     * a ekran pyta o stan swojej pracy tuż przed rysowaniem. Osobna faza dla
     * dwóch pustych potoków byłaby kolumną zer w każdym pozostałym scenariuszu.
     *
     * Kolejność jest ta sama, co w `GameLoop`: **najpierw pompowanie
     * wszystkich, potem pytanie o stan** — inaczej pomiar sprawdzałby stan
     * sprzed klatki.
     */
    protected function advanceCompanions(): void
    {
        if ($this->companions === []) {
            return;
        }

        $this->pump?->pump();

        foreach ($this->companions as $handle) {
            $this->processes->poll($handle);
        }
    }

    /**
     * Procesy potomne towarzyszące pomiarowi — tyle, ile zamawia scenariusz.
     *
     * Polecenie **milczy i śpi**, bo tak właśnie zachowuje się `du`: nie mówi
     * o sobie nic, aż skończy. Limit czasu jest hojny z tego samego powodu, dla
     * którego proces w ogóle tu stoi — ma przeżyć cały przebieg, także ten
     * z setką powtórzeń, a gdyby mimo wszystko nie przeżył, pomiar zmierzyłby
     * klatkę bez sąsiada i cicho skłamał.
     */
    private function startCompanions(Scenario $scenario): void
    {
        $this->companions = [];

        for ($index = 0; $index < $scenario->backgroundJobs(); ++$index) {
            $this->companions[] = $this->processes->start(
                'sleep ' . self::COMPANION_SECONDS,
                self::COMPANION_SECONDS,
            );
        }
    }

    private function stopCompanions(): void
    {
        foreach ($this->companions as $handle) {
            $this->processes->stop($handle);
        }

        $this->companions = [];
    }
}
