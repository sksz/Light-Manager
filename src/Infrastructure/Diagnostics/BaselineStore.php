<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/**
 * Czyta i zapisuje wzorce w `docs/pomiary/`.
 *
 * Nazwa pliku niesie datę (`2026-08-09-render.json`), więc katalog sam układa
 * się chronologicznie, a porównanie bez wskazanego pliku sięga po najnowszy.
 * Wzorce trafiają do repozytorium celowo: krok 17 ma rozliczać każdą dźwignię
 * „przed i po”, a punkt odniesienia trzymany poza repozytorium przepadłby razem
 * z maszyną, na której powstał.
 */
final class BaselineStore
{
    private const EXTENSION = '.json';

    public function __construct(
        private readonly string $directory,
    ) {
    }

    /** Domyślne miejsce: `docs/pomiary/` w korzeniu repozytorium. */
    public static function default(): self
    {
        return new self(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'pomiary');
    }

    /**
     * @return string ścieżka zapisanego pliku
     *
     * @throws DiagnosticsException gdy katalogu nie da się utworzyć albo pliku zapisać
     */
    public function save(BaselineSnapshot $snapshot, string $name): string
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0o755, true) && !is_dir($this->directory)) {
            throw DiagnosticsException::forFailedWrite($this->directory);
        }

        $path = $this->directory . DIRECTORY_SEPARATOR . $this->fileName($name);
        $json = json_encode(
            $snapshot->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        if ($json === false || file_put_contents($path, $json . "\n") === false) {
            throw DiagnosticsException::forFailedWrite($path);
        }

        return $path;
    }

    /**
     * @throws DiagnosticsException gdy pliku nie ma albo nie jest wzorcem
     */
    public function load(string $path): BaselineSnapshot
    {
        if (!is_file($path)) {
            throw DiagnosticsException::forMissingBaseline($path);
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw DiagnosticsException::forUnreadableBaseline($path);
        }

        /** @var mixed $decoded */
        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            throw DiagnosticsException::forUnreadableBaseline($path);
        }

        $data = [];

        /** @var mixed $value */
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $data[$key] = $value;
            }
        }

        if ($data === [] || !array_key_exists('scenarios', $data)) {
            throw DiagnosticsException::forUnreadableBaseline($path);
        }

        return BaselineSnapshot::fromArray($data);
    }

    /**
     * Najnowszy wzorzec w katalogu — po nazwie, nie po czasie modyfikacji, bo
     * to data w nazwie mówi, kiedy pomiar powstał; kopiowanie plików potrafi
     * przestawić znacznik systemu plików.
     *
     * Od kroku 35 katalog mieszczą **dwa tory naraz** (sixelowy i okienkowy),
     * a ich wyniki są z założenia nieporównywalne. Dlatego wybór z pominięciem
     * podpisu bierze najnowszy wzorzec **porównywalny z bieżącą konfiguracją** —
     * inaczej `--compare` po zapisaniu wzorca okienkowego odmawiałby porównania
     * torowi terminalowemu, choć jego własny wzorzec leży obok. Gdy nic nie
     * pasuje, wraca najnowszy w ogóle: odmowa z wypisanymi obiema
     * konfiguracjami mówi więcej niż „brak wzorca”.
     *
     * @throws DiagnosticsException gdy katalog nie zawiera żadnego wzorca
     */
    public function newest(?BenchmarkOptions $comparableWith = null): string
    {
        $files = $this->all();

        if ($files === []) {
            throw DiagnosticsException::forMissingBaseline($this->directory);
        }

        if ($comparableWith !== null) {
            $signature = $comparableWith->signature();

            foreach (array_reverse($files) as $path) {
                if ($this->signatureOf($path) === $signature) {
                    return $path;
                }
            }
        }

        return $files[count($files) - 1];
    }

    /** Podpis wzorca albo `null`, gdy pliku nie da się przeczytać — wybór ma nie wywracać się na cudzym pliku. */
    private function signatureOf(string $path): ?string
    {
        try {
            return $this->load($path)->options->signature();
        } catch (DiagnosticsException) {
            return null;
        }
    }

    /** @return list<string> ścieżki wzorców, posortowane rosnąco po nazwie */
    public function all(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $found = glob($this->directory . DIRECTORY_SEPARATOR . '*' . self::EXTENSION);

        if ($found === false) {
            return [];
        }

        sort($found);

        return $found;
    }

    private function fileName(string $name): string
    {
        $slug = strtolower((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $name));
        $slug = trim($slug, '-');

        return sprintf('%s-%s%s', date('Y-m-d'), $slug === '' ? 'render' : $slug, self::EXTENSION);
    }
}
