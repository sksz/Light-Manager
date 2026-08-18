<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Infrastructure;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Dto\BackgroundState;
use LightManager\Application\Port\BackgroundProcessPort;
use LightManager\Infrastructure\Process\BackgroundProcessService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Kubernetes\Application\KubectlCall;
use LightManager\Module\Kubernetes\Application\Port\KubectlPort;

/**
 * `kubectl` uruchamiany rdzeniowym portem pracy tłowej (krok 52).
 *
 * Usługa robi trzy rzeczy i ani jednej więcej: **składa wiersz polecenia**,
 * **cytuje argumenty** i **dokłada limity czasu**. Uchwyty, doglądanie
 * i przerywanie przechodzą wprost do rdzenia — praca tłowa jest jego
 * mechanizmem, a moduł go nie powtarza (reguła 15e).
 *
 * **Strumieni nie scalamy** (reguła 15f) i tutaj jest to warunek poprawności,
 * a nie porządku. Wyjściem `kubectl get -o json` jest **treść**, a klient pisze
 * przy tym na strumieniu błędów rzeczy, które treścią nie są — ostrzeżenie
 * o niezgodnej wersji, informację o przełączonym kontekście, komunikat
 * o odmowie. `2>&1` zamieniłoby JSON, który da się rozczytać, w JSON, który da
 * się rozczytać **tylko wtedy, gdy klaster akurat o niczym nie ostrzegał**.
 * Powód niepowodzenia bierze się z osobnego pola `BackgroundState::$errorOutput`.
 *
 * **Miejsce jedzie dwiema flagami** od kroku 59: `--kubeconfig` obok
 * `--context`. Do tamtego kroku pierwsza z nich nie padała ani razu, więc dwa
 * pliki z kontekstem tej samej nazwy mieszały dane po cichu (D96).
 *
 * **Limity są dwa i oba obowiązkowe**, poza jednym nazwanym wyjątkiem:
 * `--request-timeout` mówi klientowi, kiedy przestać czekać na serwer, a limit
 * procesu (rdzeń) ubija potomka, który zawiesił się przed wysłaniem żądania albo
 * po jego wysłaniu. Wyjątkiem jest **wywołanie strumieniowe**: `logs -f`
 * z limitem żądania kończyłby się dokładnie wtedy, gdy zaczyna działać.
 */
final class KubectlService extends AbstractSingleton implements KubectlPort
{
    private const BINARY = 'kubectl';

    private ?BackgroundProcessPort $processes = null;

    /**
     * Podstawienie portu pracy tłowej — **wyłącznie dla testów**.
     *
     * Ten sam szew, co w `ComposeCliService` i `RemoteTransferService`, i z tego
     * samego powodu: **żaden test nie wywołuje `kubectl`** (kryterium ukończenia
     * kroku), a maszyna testująca nie musi mieć ani klienta, ani klastra.
     */
    public function useSeam(BackgroundProcessPort $processes): void
    {
        $this->processes = $processes;
    }

    /**
     * Czy klient w ogóle jest — pytanie tanie, zadawane raz (reguła 11s).
     *
     * `PATH` przeglądamy sami, zamiast wołać `command -v`: odpowiedź pada
     * w ścieżce startu aplikacji, a uruchamianie tam procesu jest wprost
     * zakazane przez regułę zdolności `RequiresEnvironment`.
     */
    public static function hasClient(): bool
    {
        $path = getenv('PATH');

        if (!is_string($path) || $path === '') {
            return false;
        }

        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            if ($directory !== '' && is_executable(rtrim($directory, '/') . '/' . self::BINARY)) {
                return true;
            }
        }

        return false;
    }

    public function start(KubectlCall $call, int $timeoutSeconds): BackgroundHandle
    {
        return $this->processes()->start(
            self::commandFor($call, $timeoutSeconds),
            $timeoutSeconds,
            $call->shape,
        );
    }

    public function poll(BackgroundHandle $handle): BackgroundState
    {
        return $this->processes()->poll($handle);
    }

    public function stop(BackgroundHandle $handle): void
    {
        $this->processes()->stop($handle);
    }

    /**
     * Gotowy wiersz polecenia.
     *
     * **Cytujemy wszystko, łącznie z flagami** — `'get' 'pods' '-o' 'json'` znaczy
     * dla powłoki dokładnie to samo, co bez cudzysłowów, a reguła „cytuj każdy
     * argument” bez wyjątków nie ma jak zostać przeoczona przy dopisywaniu
     * następnego wywołania. Wartości i tak przeszły wcześniej przez samowalidację
     * obiektów wartości, bo cytowanie broni przed powłoką, a nie przed `kubectl`,
     * który sam rozbiera swoje argumenty (reguła 11r).
     */
    private static function commandFor(KubectlCall $call, int $timeoutSeconds): string
    {
        $arguments = $call->arguments;
        $place = $call->place;

        // Obie współrzędne miejsca idą **argumentem**, a nie zmienną
        // środowiskową (krok 59): cytowanie jest wtedy jedno i stoi w jednym
        // miejscu — tym samym, przez które od kroku 52 przechodzi kontekst.
        if ($place !== null) {
            $arguments[] = '--kubeconfig';
            $arguments[] = $place->kubeconfig;

            if ($call->withContext && $place->context !== null) {
                $arguments[] = '--context';
                $arguments[] = $place->context->value;
            }
        }

        if (!$call->isStreaming()) {
            $arguments[] = '--request-timeout=' . max(1, $timeoutSeconds) . 's';
        }

        return self::BINARY . ' ' . implode(' ', array_map(escapeshellarg(...), $arguments));
    }

    private function processes(): BackgroundProcessPort
    {
        return $this->processes ?? BackgroundProcessService::getInstance();
    }
}
