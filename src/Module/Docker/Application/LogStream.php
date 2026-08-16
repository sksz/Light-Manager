<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use LightManager\Module\Docker\Application\Port\DockerApiPort;
use LightManager\Module\Docker\Application\Port\LogReaderPort;
use LightManager\Module\Docker\Domain\ValueObject\Container;
use LightManager\Module\Docker\Domain\ValueObject\ContainerId;

/**
 * Logi jednego kontenera płynące na żywo (krok 51).
 *
 * **Płyną także wtedy, gdy ekranu modułu nie widać**, i to jest powód, dla
 * którego moduł w ogóle prosi o takt (`NeedsTick`, warunek z D82 spełniony
 * wprost): strumień nieczytany zatrzymuje nadawcę — ta sama zasada, co „oba
 * potoki czytane co klatkę” z kroku 26. Log zostawiony bez opieki na czas
 * zajrzenia w ustawienia zamilkłby i po powrocie brakowałoby w nim dziury.
 *
 * **Bufor ma górną granicę i jest nią pozycja ustawień modułu** (D90 nr 3).
 * Kontener gadatliwy zapełniłby pamięć w minutę, a wiersze najstarsze są tymi,
 * których nikt już nie czyta. Pominięcie jest przy tym **widoczne**: pierwszym
 * wierszem bufora zostaje zdanie o tym, ile wierszy wypadło — bufor ucinający
 * po cichu wygląda dokładnie tak, jak kontener, który zamilkł.
 */
final class LogStream
{
    /** Ile wierszy zamówić na początek — tyle, ile widać na dużym ekranie z zapasem. */
    private const INITIAL_LINES = 200;

    private ?DockerCall $call = null;

    private ?ContainerId $container = null;

    private string $containerName = '';

    /** @var list<string> */
    private array $lines = [];

    private int $dropped = 0;

    private ?string $problemKey = null;

    private bool $finished = false;

    public function __construct(
        private readonly DockerApiPort $api,
        private readonly LogReaderPort $reader,
        /** Ile wierszy trzymamy — pozycja ustawień modułu (D90 nr 3). */
        private int $limit,
    ) {
    }

    /**
     * Otwiera logi kontenera. Poprzedni strumień zostaje zamknięty — **jeden
     * otwarty log naraz**, bo widoczny panel jest jeden.
     */
    public function open(Container $container): void
    {
        $this->close();

        $this->container = $container->id;
        $this->containerName = $container->name;
        $this->call = $this->api->follow(
            '/containers/' . $container->id->value . '/logs'
            . '?follow=1&stdout=1&stderr=1&timestamps=0&tail=' . self::INITIAL_LINES,
        );
    }

    /** Posunięcie o takt: zabiera to, co doszło, i rozbiera na wiersze. */
    public function tick(): void
    {
        if ($this->call === null) {
            return;
        }

        $result = $this->api->poll($this->call);

        if ($result->body !== '') {
            $this->append($this->reader->push($result->body));
        }

        if ($result->isRunning()) {
            return;
        }

        if ($result->stage === DockerStage::Failed) {
            $this->problemKey = $result->problemKey ?? 'module.docker.logs.failed';
        }

        // Strumień skończony — kontener stanął albo demon zamknął rozmowę.
        // Ostatni wiersz bez znaku nowej linii ma prawo do miejsca w buforze: to
        // zwykle **ten najważniejszy**, po którym proces padł.
        $tail = $this->reader->flush();

        if ($tail !== null) {
            $this->append([$tail]);
        }

        $this->finished = true;
        $this->release();
    }

    public function isOpen(): bool
    {
        return $this->call !== null;
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }

    public function containerName(): string
    {
        return $this->containerName;
    }

    public function containerId(): ?ContainerId
    {
        return $this->container;
    }

    public function problemKey(): ?string
    {
        return $this->problemKey;
    }

    /**
     * Wiersze do pokazania.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /** Ile wierszy wypadło z bufora — panel mówi to wprost, pierwszym wierszem. */
    public function droppedLines(): int
    {
        return $this->dropped;
    }

    /** Zmiana granicy w ustawieniach obowiązuje **od razu**, a nie od nowego logu. */
    public function useLimit(int $limit): void
    {
        $this->limit = max(1, $limit);
        $this->trim();
    }

    public function close(): void
    {
        $this->release();
        $this->container = null;
        $this->containerName = '';
        $this->lines = [];
        $this->dropped = 0;
        $this->problemKey = null;
        $this->finished = false;
    }

    /** @param list<string> $lines */
    private function append(array $lines): void
    {
        if ($lines === []) {
            return;
        }

        foreach ($lines as $line) {
            $this->lines[] = $line;
        }

        $this->trim();
    }

    private function trim(): void
    {
        $excess = count($this->lines) - $this->limit;

        if ($excess <= 0) {
            return;
        }

        $this->lines = array_slice($this->lines, $excess);
        $this->dropped += $excess;
    }

    private function release(): void
    {
        if ($this->call !== null) {
            $this->api->stop($this->call);
            $this->call = null;
        }
    }
}
