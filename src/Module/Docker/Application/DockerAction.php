<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

/**
 * Czynność zmieniająca stan po stronie demona (krok 51).
 *
 * Pięć czynności i wszystkie są **jednym żądaniem HTTP** — stąd jeden enum
 * zamiast pięciu metod portu: różnią się ścieżką i słowem, a nie sposobem
 * prowadzenia. Ta sama zasada, dla której `ComposeAction` jest enumem, a nie
 * pięcioma metodami `ComposePort`.
 *
 * **Odpowiedź demona bywa niepowodzeniem, które nim nie jest**, i każdą taką
 * odpowiedź trzeba znać z góry: `304` przy uruchamianiu znaczy „już działa”,
 * `304` przy zatrzymywaniu — „już nie działa”, a `404` przy usuwaniu — „już go
 * nie ma”. Wszystkie trzy są dla użytkownika **powodzeniem**: stan, o który
 * prosił, jest stanem, który zastał.
 */
enum DockerAction: string
{
    case Start = 'start';
    case Stop = 'stop';
    case Restart = 'restart';
    case RemoveContainer = 'remove-container';
    case RemoveImage = 'remove-image';

    /** Ścieżka żądania dla wskazanego kontenera albo obrazu. */
    public function pathFor(string $reference): string
    {
        return match ($this) {
            self::Start => '/containers/' . $reference . '/start',
            self::Stop => '/containers/' . $reference . '/stop',
            self::Restart => '/containers/' . $reference . '/restart',
            // Kontener działający usuwa się **wyłącznie z `force`**, a bez niego
            // demon odmawia kodem 409. Pytanie „czy na pewno” pada wcześniej,
            // w oknie potwierdzenia, więc drugie pytanie zadawane przez demona
            // byłoby wyłącznie kłopotem.
            self::RemoveContainer => '/containers/' . $reference . '?force=1',
            self::RemoveImage => '/images/' . rawurlencode($reference),
        };
    }

    public function method(): string
    {
        return match ($this) {
            self::Start, self::Stop, self::Restart => 'POST',
            self::RemoveContainer, self::RemoveImage => 'DELETE',
        };
    }

    /** Czy czynność jest nieodwracalna — takie pytają oknem w wariancie groźnym. */
    public function isDestructive(): bool
    {
        return $this === self::RemoveContainer || $this === self::RemoveImage;
    }

    /**
     * Czy kod odpowiedzi znaczy dla użytkownika powodzenie.
     *
     * Poza zwykłym 2xx przechodzą dwa przypadki, w których demon mówi „nie
     * zrobiłem nic, bo nie było czego”: `304` (stan już taki był) i `404` przy
     * usuwaniu (rzeczy już nie ma). Zgłoszenie ich jako niepowodzenia kazałoby
     * użytkownikowi szukać usterki tam, gdzie jej nie ma.
     */
    public function accepts(int $status): bool
    {
        if ($status >= 200 && $status < 300) {
            return true;
        }

        return match ($this) {
            self::Start, self::Stop, self::Restart => $status === 304,
            self::RemoveContainer, self::RemoveImage => $status === 404,
        };
    }

    public function labelKey(): string
    {
        return 'module.docker.action.' . $this->value;
    }
}
