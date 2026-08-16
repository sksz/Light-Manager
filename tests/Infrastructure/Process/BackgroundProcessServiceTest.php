<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Process;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Dto\BackgroundStage;
use LightManager\Application\Dto\BackgroundState;
use LightManager\Application\Dto\Language;
use LightManager\Application\Dto\Settings;
use LightManager\Infrastructure\Config\SettingsService;
use LightManager\Infrastructure\Process\BackgroundProcessService;
use LightManager\Tests\Support\PinsLanguage;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\TestCase;

/**
 * Proces potomny — krok 26.
 *
 * To jest ten jeden zestaw testów w projekcie, w którym procesy są **prawdziwe**,
 * i inaczej być nie może: cała treść tej usługi to rzeczy, których atrapa nie ma
 * jak udawać — czy `start()` naprawdę nie czeka, czy potok naprawdę nie blokuje
 * i czy po ubitym potomku naprawdę nic nie zostaje. Test na atrapie sprawdzałby
 * wyłącznie, czy atrapa robi to, co jej kazano.
 *
 * Stąd też bierze się kształt kilku poleceń poniżej. `echo $$ > plik; exec sleep`
 * wygląda na sztuczkę, a jest jedynym sposobem, żeby test dowiedział się, **który
 * to proces**: usługa numeru potomka nie ujawnia i ujawniać nie powinna, więc
 * potomek podaje go sam. `exec` jest tam konieczne — bez niego numer w pliku
 * należałby do powłoki, a `sleep` byłby dopiero jej dzieckiem i przeżyłby
 * ubicie rodzica. Dokładnie ten błąd ten zestaw ma wyłapywać, więc nie wolno mu
 * go w sobie powielić.
 */
final class BackgroundProcessServiceTest extends TestCase
{
    use PinsLanguage;
    use ResetsSingletons;

    /** Ile najdłużej test czeka na coś, co ma się zdarzyć w milisekundach. */
    private const PATIENCE_SECONDS = 5.0;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('proc_open() jest wyłączone — nie ma czego sprawdzać.');
        }

        $this->resetSingleton(BackgroundProcessService::class);
    }

    protected function tearDown(): void
    {
        BackgroundProcessService::getInstance()->shutdown();
        $this->resetSingleton(BackgroundProcessService::class);
        $this->resetSingleton(SettingsService::class);

        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }
    }

    /**
     * Usługa z zadaną granicą liczby prac (krok 51).
     *
     * Granica idzie przez **prawdziwe ustawienia**, a nie przez wstrzyknięcie:
     * pytanie o nią pada w usłudze przy każdym uruchomieniu pracy i to właśnie
     * ta droga ma być sprawdzona. Katalog domowy jest przy tym podmieniony na
     * tymczasowy, więc zapis nie ma jak dotknąć konfiguracji osoby uruchamiającej
     * testy — ta sama ostrożność, co w `SettingsServiceTest`.
     */
    private function serviceWithJobLimit(int $limit): BackgroundProcessService
    {
        $this->pinLanguage(Language::Polish);
        $this->resetSingleton(SettingsService::class);
        SettingsService::getInstance()->save((new Settings())->withBackgroundJobs($limit));

        return BackgroundProcessService::getInstance();
    }

    /**
     * Miara powodzenia całego kroku, sprowadzona do jednej liczby: uruchomienie
     * pracy trwającej pięć sekund ma zająć mniej niż jedną klatkę.
     */
    public function testStartingDoesNotWaitForTheChild(): void
    {
        $service = BackgroundProcessService::getInstance();

        $startedAt = microtime(true);
        $handle = $service->start('sleep 5', 30);
        $elapsed = microtime(true) - $startedAt;

        self::assertLessThan(0.033, $elapsed, 'start pracy nie mieści się w budżecie klatki');
        self::assertSame(BackgroundStage::Running, $service->poll($handle)->stage);
    }

    /** Doglądanie też nie czeka — ani przy pracy trwającej, ani przy skończonej. */
    public function testPollingNeverBlocks(): void
    {
        $service = BackgroundProcessService::getInstance();
        $handle = $service->start('sleep 5', 30);

        $startedAt = microtime(true);

        for ($frame = 0; $frame < 10; ++$frame) {
            $service->poll($handle);
        }

        self::assertLessThan(0.033, microtime(true) - $startedAt, 'dziesięć doglądań poniżej jednej klatki');
    }

    public function testFinishedWorkCarriesTheOutputAndTheExitCode(): void
    {
        $service = BackgroundProcessService::getInstance();
        $state = $this->await($service->start('echo zajete', 30));

        self::assertSame(BackgroundStage::Done, $state->stage);
        self::assertSame('zajete', $state->output);
        self::assertSame(0, $state->exitCode);
    }

    /**
     * Kod wyjścia różny od zera **nie jest niepowodzeniem** dla rdzenia — bo dla
     * `du` nie jest nim naprawdę: polecenie kończy się jedynką za każdy katalog,
     * do którego nie miało dostępu, a mimo to podaje wynik.
     */
    public function testNonZeroExitCodeArrivesAlongsideTheOutput(): void
    {
        $service = BackgroundProcessService::getInstance();
        $state = $this->await($service->start('echo 128; exit 3', 30));

        self::assertSame(BackgroundStage::Done, $state->stage);
        self::assertSame('128', $state->output);
        self::assertSame(3, $state->exitCode);
    }

    /** Strumień błędów jest czytany, ale do wyniku nie wchodzi. */
    public function testErrorStreamDoesNotPolluteTheOutput(): void
    {
        $service = BackgroundProcessService::getInstance();
        $state = $this->await($service->start('echo wynik; echo halas >&2', 30));

        self::assertSame('wynik', $state->output);
    }

    /**
     * **Od kroku 49 strumień błędów nie ginie — jedzie osobnym polem.**
     *
     * Zmiana wyszła z odczytu zdalnego katalogu: polecenie, którego wyjściem
     * jest treść, nie ma prawa scalać z nią diagnostyki w wierszu polecenia
     * (`2>&1` przenosiło tam tryb nieblokujący z deskryptorów multipleksera
     * i psuło dane), a mimo to musi mieć jak powiedzieć, co poszło nie tak.
     * Rozdzielenie pól utrzymuje przy tym zasadę z kroku 26 w mocy: nikt niczego
     * nie skleja, a `du` swojego narzekania po prostu nie czyta.
     */
    public function testErrorStreamArrivesInItsOwnField(): void
    {
        $service = BackgroundProcessService::getInstance();
        $state = $this->await($service->start('echo wynik; echo halas >&2', 30));

        self::assertSame('wynik', $state->output);
        self::assertSame('halas', $state->errorOutput);
    }

    /** Polecenie, które nie narzekało, oddaje pusty napis — a nie „nie wiem". */
    public function testASilentCommandLeavesTheErrorFieldEmpty(): void
    {
        $service = BackgroundProcessService::getInstance();
        $state = $this->await($service->start('echo wynik', 30));

        self::assertSame('', $state->errorOutput);
    }

    /**
     * **Wypis, którego nikt nie odbiera dostatecznie szybko, nie ma prawa się
     * zgubić** — regresja z kroku 49.
     *
     * Polecenie wypisuje ćwierć mebibajta w porcjach rozłożonych na sekundę,
     * a test dogląda pracy raz na klatkę, czyli dokładnie tak, jak robi to pętla
     * główna. Potok mieści 64 KiB, więc bez prawidłowego opróżniania i bez
     * blokującego zapisu po stronie potomka zostałaby z tego jedna trzecia.
     */
    public function testLargeOutputSurvivesFrameRateDraining(): void
    {
        $service = BackgroundProcessService::getInstance();
        $handle = $service->start('for i in $(seq 1 16); do head -c 16384 /dev/zero | tr "\\0" "X"; done', 30);

        while (true) {
            $service->pump();
            $state = $service->poll($handle);

            if (!$state->isRunning()) {
                break;
            }

            usleep(33_000);
        }

        self::assertSame(16 * 16_384, strlen($state->output));
    }

    public function testTimeoutStopsTheChildAndSaysSo(): void
    {
        $service = BackgroundProcessService::getInstance();
        $pidFile = $this->temporaryFile();
        $state = $this->await($service->start($this->announcingSleep($pidFile), 1));

        self::assertSame(BackgroundStage::Failed, $state->stage);
        self::assertSame('process.timedOut', $state->problemKey);
        self::assertSame(['seconds' => 1], $state->problemParameters);
        self::assertProcessGone($this->pidFrom($pidFile));
    }

    public function testStoppingLeavesNoProcessBehind(): void
    {
        $service = BackgroundProcessService::getInstance();
        $pidFile = $this->temporaryFile();
        $handle = $service->start($this->announcingSleep($pidFile), 30);
        $pid = $this->pidFrom($pidFile);

        $service->stop($handle);

        self::assertProcessGone($pid);
        self::assertSame(BackgroundStage::Idle, $service->poll($handle)->stage);
    }

    /**
     * **Odwrócenie testu z kroku 26** i miara powodzenia rozbudowy z kroku 51.
     *
     * Do tamtego kroku nowe zamówienie przerywało poprzednie, a test o tej samej
     * treści sprawdzał, czy wyparty zamawiający **dowiaduje się** o wyparciu.
     * Odtąd nikt nikogo nie wypiera: obie prace trwają, obaj zamawiający widzą
     * swoją. Zdanie z kryteriów kroku — „`du` działa w trakcie pracy compose
     * i odwrotnie” — jest tym sprawdzeniem, sprowadzonym do dwóch `sleep`ów.
     */
    public function testNewWorkLeavesTheRunningOneAlone(): void
    {
        $service = BackgroundProcessService::getInstance();
        $pidFile = $this->temporaryFile();
        $first = $service->start($this->announcingSleep($pidFile), 30);
        $pid = $this->pidFrom($pidFile);

        $second = $service->start('sleep 5', 30);

        self::assertTrue(is_dir('/proc/' . $pid), 'pierwsza praca miała przeżyć zamówienie drugiej');
        self::assertSame(BackgroundStage::Running, $service->poll($first)->stage);
        self::assertSame(BackgroundStage::Running, $service->poll($second)->stage);
    }

    /**
     * Granica z ustawień: praca ponad nią **nie powstaje i mówi dlaczego**, a te
     * trwające zostają nietknięte.
     *
     * Odmowa jest tu tym, czym w kroku 26 było wyparcie — odpowiedzią na pytanie
     * „co, gdy zamówień jest więcej, niż port prowadzi” — i różni się od niego
     * jedną rzeczą: **traci ten, kto przyszedł później**, a nie ten, kto już
     * pracuje.
     */
    public function testWorkBeyondTheLimitIsRefusedWhileTheRunningOnesStay(): void
    {
        $service = $this->serviceWithJobLimit(2);
        $first = $service->start('sleep 5', 30);
        $second = $service->start('sleep 5', 30);

        $refused = $service->poll($service->start('sleep 5', 30));

        self::assertSame(BackgroundStage::Failed, $refused->stage);
        self::assertSame('process.tooMany', $refused->problemKey);
        self::assertSame(['limit' => 2], $refused->problemParameters);
        self::assertSame(BackgroundStage::Running, $service->poll($first)->stage);
        self::assertSame(BackgroundStage::Running, $service->poll($second)->stage);
    }

    /** Zwolnione miejsce jest znowu do wzięcia — granica dotyczy prac trwających. */
    public function testStoppedWorkGivesItsPlaceBack(): void
    {
        $service = $this->serviceWithJobLimit(1);
        $first = $service->start('sleep 5', 30);

        $service->stop($first);

        self::assertSame(BackgroundStage::Running, $service->poll($service->start('sleep 5', 30))->stage);
    }

    /**
     * **Pracę posuwa pompowanie, a nie zaglądanie** — to jest cała zmiana kroku
     * 51 widziana z zewnątrz.
     *
     * Test nie dogląda pracy ani razu, dopóki nie skończy się pompowanie: gdyby
     * posuwanie nadal siedziało w `poll()`, wynik nie miałby prawa dojść.
     */
    public function testPumpingAdvancesWorkNobodyIsLookingAt(): void
    {
        $service = BackgroundProcessService::getInstance();
        $handle = $service->start('echo posuniete', 30);
        $deadline = microtime(true) + self::PATIENCE_SECONDS;

        do {
            $service->pump();
            usleep(5000);
        } while ($service->poll($handle)->isRunning() && microtime(true) < $deadline);

        self::assertSame('posuniete', $service->poll($handle)->output);
    }

    /**
     * Limit czasu pilnuje **pompowanie**, więc obowiązuje także pracę, o którą
     * nikt nie pyta.
     *
     * Przy jednej pracy było to bez znaczenia — właściciel zaglądał co klatkę,
     * bo po to ją zamówił. Przy kilku pracach właściciel bywa nieobecny (ekran
     * modułu zniknął), a proces bez straży wisiałby wtedy bez końca.
     */
    public function testPumpingEnforcesTheTimeoutOfWorkNobodyIsLookingAt(): void
    {
        $service = BackgroundProcessService::getInstance();
        $pidFile = $this->temporaryFile();
        $handle = $service->start($this->announcingSleep($pidFile), 1);
        $pid = $this->pidFrom($pidFile);
        $deadline = microtime(true) + self::PATIENCE_SECONDS;

        while (microtime(true) < $deadline && is_dir('/proc/' . $pid)) {
            $service->pump();
            usleep(20_000);
            clearstatcache();
        }

        self::assertProcessGone($pid);
        self::assertSame('process.timedOut', $service->poll($handle)->problemKey);
    }

    public function testShutdownStopsWorkThatIsStillRunning(): void
    {
        $service = BackgroundProcessService::getInstance();
        $pidFile = $this->temporaryFile();
        $handle = $service->start($this->announcingSleep($pidFile), 30);
        $pid = $this->pidFrom($pidFile);

        $service->shutdown();

        self::assertProcessGone($pid);
        self::assertSame(BackgroundStage::Idle, $service->poll($handle)->stage);
    }

    /**
     * Sprzątanie przy wyjściu obejmuje **komplet** prac — bo od kroku 51 jest ich
     * kilka, a osierocony potomek nie przestaje być osierocony przez to, że miał
     * towarzystwo.
     */
    public function testShutdownStopsEveryRunningJob(): void
    {
        $service = BackgroundProcessService::getInstance();
        $first = $this->temporaryFile();
        $second = $this->temporaryFile();
        $service->start($this->announcingSleep($first), 30);
        $service->start($this->announcingSleep($second), 30);
        $pids = [$this->pidFrom($first), $this->pidFrom($second)];

        $service->shutdown();

        foreach ($pids as $pid) {
            self::assertProcessGone($pid);
        }
    }

    /**
     * Najważniejszy test kroku i jedyny, który uruchamia **całą aplikację
     * w miniaturze**: osobny proces PHP zamawia pracę i kończy się, nie sprzątając
     * po sobie jawnie. Jeśli funkcja zamknięcia procesu nie zadziała, `sleep`
     * zostanie osierocony i test to zobaczy.
     *
     * Sprawdzana jest tu **druga** droga sprzątania — ta z gwarancji ostatniej
     * szansy. Pierwszą, jawną, chodzi `Bootstrap::shutdown()` i pilnuje jej test
     * powyżej: to on woła `shutdown()` wprost.
     */
    public function testNoChildSurvivesTheProcessThatStartedIt(): void
    {
        $pidFile = $this->temporaryFile();
        $script = $this->temporaryFile();

        file_put_contents($script, $this->applicationInMiniature($pidFile));
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script), $output, $exitCode);

        self::assertSame(0, $exitCode, 'proces potomny miał zakończyć się normalnie: ' . implode("\n", $output));
        self::assertProcessGone($this->pidFrom($pidFile));
    }

    /** Praca nieuruchomiona nie ma czego sprzątać — i wolno o to prosić. */
    public function testShutdownWithoutWorkIsHarmless(): void
    {
        BackgroundProcessService::getInstance()->shutdown();

        $this->expectNotToPerformAssertions();
    }

    /**
     * Polecenie, które **podaje swój numer procesu i dopiero potem zasypia**.
     *
     * `exec` sprawia, że `sleep` zajmuje miejsce powłoki, zamiast być jej
     * dzieckiem — a to znaczy, że numer w pliku należy do procesu, którego usługa
     * naprawdę trzyma. Bez tego test przechodziłby, ubijając powłokę i zostawiając
     * `sleep` przy życiu.
     */
    private function announcingSleep(string $pidFile): string
    {
        return 'echo $$ > ' . escapeshellarg($pidFile) . '; exec sleep 30';
    }

    /** Skrypt aplikacji-w-miniaturze: zamów pracę i skończ się bez sprzątania. */
    private function applicationInMiniature(string $pidFile): string
    {
        $autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';

        return <<<PHP
            <?php

            declare(strict_types=1);

            require {$this->quoted($autoload)};

            \$service = \\LightManager\\Infrastructure\\Process\\BackgroundProcessService::getInstance();
            \$service->start({$this->quoted($this->announcingSleep($pidFile))}, 30);

            \$deadline = microtime(true) + 5.0;

            while (trim((string) @file_get_contents({$this->quoted($pidFile)})) === '') {
                if (microtime(true) > \$deadline) {
                    exit(1);
                }

                usleep(5000);
            }

            // Ani `shutdown()`, ani `Bootstrap::shutdown()` — o to właśnie chodzi.
            exit(0);
            PHP;
    }

    private function quoted(string $value): string
    {
        return var_export($value, true);
    }

    /** Doglądanie co 5 ms, aż praca przestanie trwać — tak, jak robi to pętla. */
    private function await(BackgroundHandle $handle): BackgroundState
    {
        $service = BackgroundProcessService::getInstance();
        $deadline = microtime(true) + self::PATIENCE_SECONDS;

        do {
            $service->pump();
            $state = $service->poll($handle);

            if (!$state->isRunning()) {
                return $state;
            }

            usleep(5000);
        } while (microtime(true) < $deadline);

        self::fail('praca nie skończyła się w wyznaczonym czasie');
    }

    /** Czeka, aż potomek zdąży podać swój numer. */
    private function pidFrom(string $pidFile): int
    {
        $deadline = microtime(true) + self::PATIENCE_SECONDS;

        do {
            $pid = trim((string) @file_get_contents($pidFile));

            if ($pid !== '') {
                return (int) $pid;
            }

            usleep(5000);
        } while (microtime(true) < $deadline);

        self::fail('potomek nie podał swojego numeru procesu');
    }

    /**
     * Numer procesu, którego już nie ma — sprawdzany katalogiem `/proc`, a nie
     * sygnałem zerowym: proces cudzy albo pochowany dawałby przy `posix_kill()`
     * odpowiedź zależną od uprawnień, a tutaj chodzi o pytanie „czy istnieje”.
     */
    private static function assertProcessGone(int $pid): void
    {
        $deadline = microtime(true) + self::PATIENCE_SECONDS;

        while (microtime(true) < $deadline) {
            clearstatcache();

            if (!is_dir('/proc/' . $pid)) {
                self::assertFalse(is_dir('/proc/' . $pid));

                return;
            }

            usleep(5000);
        }

        self::fail('proces ' . $pid . ' przeżył — to jest dokładnie ten błąd, którego krok 26 miał nie popełnić');
    }

    private function temporaryFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'lm-bg-');

        self::assertIsString($file);
        $this->temporaryFiles[] = $file;

        return $file;
    }
}
