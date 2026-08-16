<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application\Port;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Dto\BackgroundState;
use LightManager\Module\Kubernetes\Application\KubectlCall;

/**
 * Klient `kubectl` uruchamiany **obok klatki** (krok 52).
 *
 * Port jest cienki i taki ma być: dokłada do rdzeniowego portu pracy tłowej
 * dokładnie trzy rzeczy, których warstwa aplikacji nie ma prawa znać — nazwę
 * pliku wykonywalnego, cytowanie argumentów i **limity czasu**. Wszystko inne
 * (uchwyty, doglądanie, przerywanie) zostaje takie samo, bo jest rdzeniowe
 * i podrabianie go w module byłoby złamaniem reguły 15e.
 *
 * **Limit czasu jest częścią każdego wywołania, a nie ozdobą** — to jest jedyna
 * trudność, którą plan kroku zapowiadał, i pochodzi z natury narzędzia: każde
 * `kubectl` rozmawiające z klastrem może wisieć aż do własnego limitu, a bez
 * limitu — aż do limitu warstwy sieciowej. Limity są **dwa i oba obowiązkowe**:
 * `--request-timeout` (klient przestaje czekać na serwer) i limit procesu
 * (rdzeń ubija potomka). Pierwszy jest uprzejmy, drugi jest ostateczny; sam
 * pierwszy nie chroni przed klientem, który zawiesił się przed wysłaniem
 * żądania.
 *
 * Wyjątek jest jeden i nazwany przy `KubectlCall`: **wywołanie strumieniowe nie
 * dostaje `--request-timeout`**, bo ten zamknąłby strumień w chwili, w której
 * miał zacząć płynąć.
 *
 * Portu **nie pytamy o gotowe dane**, tylko o pracę: metody `resources()` czy
 * `logs(): string` tu nie ma i być nie może — czekanie na odpowiedź klastra
 * w klatce jest dokładnie tym, czego zakazuje reguła nadrzędna pracy poza
 * maszyną (krok 48, 11r).
 */
interface KubectlPort
{
    /**
     * Uruchamia wywołanie i **nie czeka na nie ani chwili**.
     *
     * Uchwyt wraca zawsze — także wtedy, gdy klienta nie ma albo gdy rdzeń
     * odmówił z powodu granicy liczby prac; powód odbiera pierwszy `poll()`.
     * Wołający nie obsługuje przez to awarii dwiema drogami.
     *
     * @param int $timeoutSeconds limit **procesu**; przy wywołaniu niestrumieniowym
     *                            usługa dokłada z niego także `--request-timeout`
     */
    public function start(KubectlCall $call, int $timeoutSeconds): BackgroundHandle;

    /**
     * Zagląda, co z pracą. **Nigdy nie blokuje.**
     *
     * Stan jest rdzeniowy (`BackgroundState`) i to nie jest lenistwo, tylko
     * reguła 15e: praca tłowa jest mechanizmem rdzenia, a moduł nie powtarza
     * mechanizmów rdzenia we własnych typach. Od kroku 52 stan `Running` niesie
     * przy tym wypis, który dotąd przyszedł — i na tym stoją logi.
     */
    public function poll(BackgroundHandle $handle): BackgroundState;

    /** Przerywa **tę** pracę. Wolno wołać zawsze, także dla uchwytu nieznanego. */
    public function stop(BackgroundHandle $handle): void;
}
