<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\Kubernetes\Application\Port\SecretFilePort;

/**
 * Manifest sekretu **w pamięci** — atrapa `SecretFilePort` (krok 61, etap 3).
 *
 * Prawdziwa usługa pisze poświadczenie na dysk, więc przebieg testowy nie ma
 * prawa jej użyć: zostawiony plik jest zostawionym poświadczeniem, a testy
 * uruchamia się częściej niż aplikację.
 *
 * Atrapa pamięta przy tym **całą historię**, a nie stan bieżący — bo jedyne
 * zdanie, które etap trzeci ma tu do udowodnienia, brzmi „plik ginie **także po
 * niepowodzeniu**", a tego nie da się sprawdzić, patrząc na koniec.
 */
final class StubSecretFiles implements SecretFilePort
{
    /** @var array<string, string> treść pod ścieżką — wyłącznie pliki **żyjące** */
    public array $files = [];

    /** @var list<string> co zapisano, w kolejności — także to, co już skasowano */
    public array $written = [];

    /** @var list<string> co skasowano, w kolejności */
    public array $forgotten = [];

    public bool $refuses = false;

    private int $next = 0;

    public function write(string $name, string $content): string
    {
        if ($this->refuses) {
            return '';
        }

        $path = '/atrapa/' . $name . '-' . ++$this->next . '.json';
        $this->files[$path] = $content;
        $this->written[] = $path;

        return $path;
    }

    public function forget(string $path): void
    {
        if ($path === '') {
            return;
        }

        unset($this->files[$path]);
        $this->forgotten[] = $path;
    }

    /** Czy po wszystkim nie został ani jeden plik z poświadczeniem. */
    public function nothingLeft(): bool
    {
        return $this->files === [];
    }
}
