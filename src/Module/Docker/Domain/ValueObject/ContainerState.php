<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Domain\ValueObject;

/**
 * Stan kontenera tak, jak nazywa go demon (krok 51).
 *
 * Siedem wartości i wszystkie pochodzą z API — nie z naszego rozeznania: pole
 * `State` listy kontenerów przyjmuje dokładnie te napisy. Nieznana wartość
 * (nowsza wersja demona, stan, którego dziś nie ma) **nie jest błędem**:
 * kontener z takim stanem ma się pokazać na liście, bo użytkownik chce go
 * zobaczyć właśnie wtedy, gdy dzieje się z nim coś niezwykłego. Stąd `Unknown`
 * zamiast wyjątku.
 *
 * Roli motywu ten enum **nie zna i znać nie ma prawa**: `Role` leży
 * w `Application/Ui`, czyli po drugiej stronie granicy warstw (reguła 1),
 * a domena musi dać się sprawdzić bez jednej linii o rysowaniu. Kolor dobiera
 * panel.
 */
enum ContainerState: string
{
    case Created = 'created';
    case Running = 'running';
    case Paused = 'paused';
    case Restarting = 'restarting';
    case Removing = 'removing';
    case Exited = 'exited';
    case Dead = 'dead';
    case Unknown = 'unknown';

    public static function of(string $value): self
    {
        return self::tryFrom(strtolower(trim($value))) ?? self::Unknown;
    }

    public function isRunning(): bool
    {
        return $this === self::Running;
    }

    /** Czy kontener da się zatrzymać — czyli czy cokolwiek w nim jeszcze działa. */
    public function isStoppable(): bool
    {
        return match ($this) {
            self::Running, self::Paused, self::Restarting => true,
            default => false,
        };
    }

    /** Czy kontener da się uruchomić — stan spoczynku po obu stronach życia. */
    public function isStartable(): bool
    {
        return match ($this) {
            self::Created, self::Exited => true,
            default => false,
        };
    }

    /** Czy stan jest sam w sobie kłopotem — panel maluje go wtedy rolą ostrzeżenia. */
    public function isTroubled(): bool
    {
        return match ($this) {
            self::Dead, self::Restarting => true,
            default => false,
        };
    }

    public function labelKey(): string
    {
        return 'module.docker.state.' . $this->value;
    }
}
