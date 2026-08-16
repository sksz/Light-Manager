<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Ssh\Application\SshSession;
use LightManager\Module\Ssh\Application\SshSettings;

/**
 * `ssh.session` — etap sesji, host i powód niepowodzenia.
 *
 * **Ulotna, i to jest tu tańsze niż licznik.** Etap zmienia proces potomny, więc
 * o zmianie dowiadujemy się dopiero w takcie — licznik trzeba by bić w miejscu,
 * które samo nie wie, czy coś się stało (`advance()` woła `poll()` i porównuje).
 * Warunek ulotności z 11w jest spełniony: `ask()` przepisuje pięć pól obiektu,
 * który już istnieje, i wraca.
 *
 * **Odcisk klucza wychodzi wyłącznie do właściciela.** Wiersze niosą sam etap
 * i host, bo tyle wystarcza obcemu, żeby wiedzieć, czy ma z czym rozmawiać;
 * `SessionState` z listą odcisków dostaje przez ładunek ekran modułu, bo to on
 * pyta o zaufanie nieznanemu kluczowi (krok 48). Ta granica jest tą samą, którą
 * `ssh.hosts` przykłada do ścieżki klucza prywatnego.
 */
final class SessionQuery implements QueryInterface
{
    public function __construct(
        private readonly SshSession $session,
    ) {
    }

    public function name(): string
    {
        return SshSettings::ID . '.session';
    }

    public function descriptionKey(): string
    {
        return 'module.' . SshSettings::ID . '.query.session';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return self::VOLATILE;
    }

    public function ask(CommandInput $input): QueryResult
    {
        $state = $this->session->state();

        return QueryResult::owned(SshSettings::ID, $state, static fn (): array => [[
            'stage' => strtolower($state->stage->name),
            // `->` zamiast `?->`, bo `??` ma semantykę `isset()` i sam radzi sobie
            // z pustym hostem — nullsafe byłby tu drugim zabezpieczeniem tej samej
            // rzeczy (PHPStan `nullsafe.neverNull`).
            'host' => $state->host->host ?? '',
            'name' => $state->host->name ?? '',
            'user' => $state->host->user ?? '',
            'connected' => $state->isConnected(),
            'working' => $state->isWorking(),
            'problem' => $state->problemKey ?? '',
        ]]);
    }
}
