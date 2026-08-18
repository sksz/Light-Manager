<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Dto\BackgroundStage;
use LightManager\Module\Kubernetes\Application\Port\KubectlPort;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceRef;

/**
 * Logi poda płynące na żywo (krok 52).
 *
 * **To dla tej klasy rozbudowano rdzeń** (D91 nr 12). Do kroku 52
 * `BackgroundState` przy pracy trwającej nie niósł ani jednego bajtu wypisu, więc
 * `kubectl logs -f` — polecenie, które nie kończy się nigdy — nie miało jak
 * powiedzieć czegokolwiek. Rdzeń oddaje odtąd bufor trwającej pracy, a strumień
 * zapomina najstarsze zamiast odrzucać najnowsze.
 *
 * Rachunek pozycji jest tu **w bajtach bezwzględnych** i to jest jedyna
 * trudność tej klasy. Bufor rdzenia się przesuwa, więc „to, czego jeszcze nie
 * widziałem” nie da się wskazać pozycją w buforze — trzeba liczyć od początku
 * strumienia i porównywać z liczbą bajtów, które z niego wypadły. Gdy wypadło
 * więcej, niż zdążyliśmy przeczytać, **dziura jest nie do odzyskania** i mówimy
 * o niej wprost: log ucięty po cichu wygląda tak samo jak log, w którym nic się
 * nie działo.
 *
 * Wiersz niepełny **czeka na swój koniec**. Potomek pisze porcjami, które nie
 * układają się w wiersze, więc bez tego co trzydziesta linia byłaby przecięta
 * w losowym miejscu.
 */
final class LogStream
{
    /**
     * Ile najdłużej żyje strumień, gdy nikt go nie zamknie.
     *
     * Limit procesu jest w rdzeniu obowiązkowy, a `logs -f` nie kończy się sam,
     * więc liczba musi tu być jakaś. Godzina to tyle, ile trwa najdłuższe
     * sensowne wpatrywanie się w log — a strumień zamknięty limitem mówi o tym
     * zdaniem, zamiast po prostu zamilknąć.
     */
    private const LIFETIME_SECONDS = 3600;

    private ?BackgroundHandle $handle = null;

    private ?ResourceRef $reference = null;

    private ?string $container = null;

    /** @var list<string> wiersze gotowe do pokazania, najstarsze pierwsze */
    private array $lines = [];

    /** Ogon bez znaku końca wiersza — czeka na resztę. */
    private string $partial = '';

    /** Ile bajtów strumienia już przeczytaliśmy, licząc od jego początku. */
    private int $consumed = 0;

    /** Ile bajtów przepadło, zanim zdążyliśmy je przeczytać. */
    private int $lostBytes = 0;

    private bool $ended = false;

    private ?string $problemKey = null;

    public function __construct(
        private readonly KubectlPort $kubectl,
        private int $limit = KubernetesSettings::DEFAULT_LOG_LINES,
    ) {
    }

    /** @return list<string> */
    public function lines(): array
    {
        return $this->lines;
    }

    public function reference(): ?ResourceRef
    {
        return $this->reference;
    }

    public function container(): ?string
    {
        return $this->container;
    }

    public function isOpen(): bool
    {
        return $this->handle !== null;
    }

    /** Czy strumień się skończył — pod zniknął, sesja padła albo minął limit. */
    public function hasEnded(): bool
    {
        return $this->ended;
    }

    public function problemKey(): ?string
    {
        return $this->problemKey;
    }

    public function lostBytes(): int
    {
        return $this->lostBytes;
    }

    public function useLimit(int $limit): void
    {
        $this->limit = max(1, $limit);
        $this->trim();
    }

    /**
     * Otwiera logi poda, **zamykając poprzednie**.
     *
     * Kryterium ukończenia kroku mówi to wprost: „drugi pod otwarty w logach
     * zastępuje pierwszy, a nie mnoży prac bez końca”. Strumień jest jeden, bo
     * widoczny jest jeden.
     */
    public function open(ResourceRef $reference, ?string $container, int $tail, ClusterSession $session): void
    {
        $this->close();

        $this->reference = $reference;
        $this->container = $container;
        $this->handle = $this->kubectl->start(
            KubectlCall::logs($reference, $container, $tail, $session->place()),
            self::LIFETIME_SECONDS,
        );
    }

    /**
     * Zabiera z rdzenia to, co przyszło od ostatniego razu.
     *
     * Wołane raz na takt — **także wtedy, gdy ekranu logów nie widać**, bo
     * strumień nieczytany zatrzymuje nadawcę (ta sama reguła, dla której moduł
     * Dockera pompuje logi niezależnie od widoku).
     */
    public function advance(): void
    {
        if ($this->handle === null) {
            return;
        }

        $state = $this->kubectl->poll($this->handle);

        if ($state->stage === BackgroundStage::Idle) {
            $this->handle = null;
            $this->ended = true;
            $this->problemKey = 'module.' . KubernetesSettings::ID . '.logs.interrupted';

            return;
        }

        $this->consume($state->output, $state->droppedBytes);

        if ($state->stage === BackgroundStage::Running) {
            return;
        }

        $this->handle = null;
        $this->ended = true;

        if ($state->stage === BackgroundStage::Failed) {
            $this->problemKey = $state->problemKey ?? 'module.' . KubernetesSettings::ID . '.logs.failed';

            return;
        }

        // Kod niezerowy przy strumieniu znaczy zwykle, że pod zniknął w trakcie
        // — powód klient wypisuje na strumieniu błędów, a my mamy go już
        // w wierszach, bo przy `Done` przychodzi razem z resztą.
        $this->problemKey = ($state->exitCode ?? 0) === 0
            ? 'module.' . KubernetesSettings::ID . '.logs.closed'
            : 'module.' . KubernetesSettings::ID . '.logs.broken';
    }

    public function close(): void
    {
        if ($this->handle !== null) {
            $this->kubectl->stop($this->handle);
        }

        $this->handle = null;
        $this->reference = null;
        $this->container = null;
        $this->lines = [];
        $this->partial = '';
        $this->consumed = 0;
        $this->lostBytes = 0;
        $this->ended = false;
        $this->problemKey = null;
    }

    /**
     * Wycina z bufora to, czego jeszcze nie widzieliśmy.
     *
     * Cała arytmetyka tej klasy siedzi w tych kilkunastu wierszach i opiera się
     * na jednym niezmienniku rdzenia: `droppedBytes` liczy bajty, które **wypadły
     * z początku** bufora od chwili startu. Pozycja bezwzględna końca bufora to
     * więc `droppedBytes + strlen(bufor)`, a nasze miejsce w nim — różnica między
     * tym, co przeczytaliśmy, a tym, co wypadło.
     */
    private function consume(string $buffer, int $droppedBytes): void
    {
        if ($this->consumed < $droppedBytes) {
            $this->lostBytes += $droppedBytes - $this->consumed;
            $this->consumed = $droppedBytes;
        }

        $offset = $this->consumed - $droppedBytes;
        $fresh = $offset >= strlen($buffer) ? '' : substr($buffer, $offset);

        if ($fresh === '') {
            return;
        }

        $this->consumed = $droppedBytes + strlen($buffer);
        $this->append($fresh);
    }

    private function append(string $text): void
    {
        $combined = $this->partial . $text;
        $parts = explode("\n", $combined);
        // Ostatni kawałek nie ma znaku końca wiersza, więc jeszcze nie jest
        // wierszem — czeka na następną porcję. `explode()` oddaje zawsze co
        // najmniej jeden element, więc jest co zdjąć.
        $this->partial = array_pop($parts);

        foreach ($parts as $line) {
            $this->lines[] = rtrim($line, "\r");
        }

        $this->trim();
    }

    private function trim(): void
    {
        $excess = count($this->lines) - $this->limit;

        if ($excess > 0) {
            $this->lines = array_slice($this->lines, $excess);
        }
    }
}
