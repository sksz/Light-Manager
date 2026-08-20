<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Infrastructure;

use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Kubernetes\Application\Port\SecretFilePort;

/**
 * Manifest sekretu na dysku — implementacja `SecretFilePort` (krok 61, etap 3).
 *
 * Powody trzech reguł stoją w porcie. Tutaj jest tylko jedno zdanie warte
 * dopisania: **prawa nadaje się przed treścią**. `file_put_contents()` tworzy
 * plik maską procesu, więc ustawienie `0600` po zapisie zostawia okno, w którym
 * poświadczenie jest czytelne dla wszystkich — a okno mierzy się w tych samych
 * milisekundach, w których działa reszta aplikacji.
 */
final class SecretFileService extends AbstractSingleton implements SecretFilePort
{
    private const PREFIX = 'lm-secret-';

    private const SUFFIX = '.json';

    private const FILE_MODE = 0o600;

    private const DIRECTORY_MODE = 0o700;

    private const DIRECTORY = '.light-manager';

    public function write(string $name, string $content): string
    {
        $path = self::directory() . DIRECTORY_SEPARATOR . self::PREFIX . self::slug($name) . self::SUFFIX;

        // Plik powstaje **pusty i od razu z prawami**, a treść wchodzi dopiero
        // do otwartego uchwytu — patrz opis klasy.
        $handle = @fopen($path, 'wb');

        if ($handle === false) {
            return '';
        }

        @chmod($path, self::FILE_MODE);
        $written = fwrite($handle, $content);
        fclose($handle);

        if ($written === false) {
            $this->forget($path);

            return '';
        }

        return $path;
    }

    public function forget(string $path): void
    {
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    /** Nazwa pliku bez niespodzianek — wszystko spoza alfabetu na myślnik. */
    private static function slug(string $name): string
    {
        $slug = strtolower((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $name));
        $slug = trim($slug, '-');

        return $slug === '' ? 'registry' : $slug;
    }

    /** `XDG_RUNTIME_DIR`, a w jego braku `~/.light-manager` (D102 nr 1). */
    private static function directory(): string
    {
        $runtime = getenv('XDG_RUNTIME_DIR');

        if (is_string($runtime) && $runtime !== '' && is_dir($runtime)) {
            return rtrim($runtime, DIRECTORY_SEPARATOR);
        }

        $home = getenv('HOME');

        if (!is_string($home) || $home === '') {
            $working = getcwd();
            $home = $working === false ? '.' : $working;
        }

        $directory = rtrim($home, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::DIRECTORY;

        if (!is_dir($directory)) {
            @mkdir($directory, self::DIRECTORY_MODE, true);
        }

        return $directory;
    }
}
