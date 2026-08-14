<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use Imagick;
use LightManager\Infrastructure\Glfw\GlfwFontLocator;
use LightManager\Infrastructure\Imagick\ImagickCapabilityService;

/**
 * Metryczka środowiska dopisywana do każdego wzorca.
 *
 * Bez niej plik z pomiarem jest liczbami bez kontekstu: ta sama konfiguracja na
 * innej wersji ImageMagicka albo z innym fontem daje inny wynik i nikt po roku
 * nie odtworzy, dlaczego. Wersja PHP i ImageMagicka są tu ważniejsze niż nazwa
 * maszyny — zmiana rozkładu potrafi przesunąć czas rasteryzacji o dziesiątki
 * procent.
 */
final class EnvironmentMetadata
{
    /**
     * Powyżej tego obciążenia na rdzeń wzorzec dostaje ostrzeżenie.
     *
     * Pół rdzenia zajęte na rdzeń to jeszcze nie katastrofa, ale to już dość,
     * żeby przesunąć medianę o kilkanaście procent — a tyle wynosiła „regresja”,
     * którą krok 22 gonił przez cztery przebiegi, zanim okazało się, że to
     * przeglądarka w tle. **Ostrzeżenie, nie odmowa** (D64): narzędzie zna tu
     * przesłankę, a nie skutek, więc decyzję o zapisie podejmuje człowiek,
     * mając liczbę przed oczami.
     */
    public const NOISY_LOAD_PER_CORE = 0.5;

    public function __construct(
        public readonly string $phpVersion,
        public readonly string $imageMagickVersion,
        public readonly string $font,
        /** Data i godzina w formacie ISO 8601. */
        public readonly string $recordedAt,
        /**
         * Średnie obciążenie z ostatniej minuty **na rdzeń**; `null`, gdy system
         * go nie podaje (Windows).
         */
        public readonly ?float $loadPerCore = null,
    ) {
    }

    /** Czy maszyna była w trakcie pomiaru czymś zajęta na tyle, by to powiedzieć. */
    public function isNoisy(): bool
    {
        return $this->loadPerCore !== null && $this->loadPerCore > self::NOISY_LOAD_PER_CORE;
    }

    /**
     * Tor okienkowy (krok 35) bierze font **z lokatora plików TTF**, a nie
     * z listy nazw Imagicka: to jego metryki dyktują komórkę, więc wzorzec
     * zapisany z innym plikiem fontu opisuje inną siatkę.
     *
     * Tor tekstowy (krok 38) nie rasteryzuje niczego — pismo dobiera terminal,
     * a narzędzie nie ma jak go poznać. Zapisujemy więc `terminal`, bo wpisanie
     * w metryczkę fontu, którego pomiar nie dotyczy, byłoby myleniem czytelnika
     * wzorca.
     */
    public static function current(?string $requestedFont, BenchmarkTrack $track = BenchmarkTrack::Sixel): self
    {
        return new self(
            PHP_VERSION,
            self::imageMagickVersion(),
            $track === BenchmarkTrack::Text
                ? 'terminal'
                : $requestedFont ?? self::font($track === BenchmarkTrack::Window) ?? 'default',
            date('c'),
            self::loadPerCore(),
        );
    }

    /** @return array<string, string|float|null> */
    public function toArray(): array
    {
        return [
            'php' => $this->phpVersion,
            'imageMagick' => $this->imageMagickVersion,
            'font' => $this->font,
            'recordedAt' => $this->recordedAt,
            'loadPerCore' => $this->loadPerCore === null ? null : round($this->loadPerCore, 2),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            JsonValue::string($data, 'php'),
            JsonValue::string($data, 'imageMagick'),
            JsonValue::string($data, 'font'),
            JsonValue::string($data, 'recordedAt'),
            JsonValue::nullableFloat($data, 'loadPerCore'),
        );
    }

    /**
     * Obciążenie z ostatniej minuty podzielone przez liczbę rdzeni.
     *
     * Dzielenie jest tu istotne: „1,0” na maszynie ośmiordzeniowej znaczy spokój,
     * a na jednordzeniowej — kolejkę do procesora. Bez podzielenia liczba
     * w metryczce myliłaby przy każdej zmianie maszyny.
     */
    private static function loadPerCore(): ?float
    {
        $load = sys_getloadavg();
        $cores = self::cores();

        if ($load === false || $cores === null) {
            return null;
        }

        return $load[0] / $cores;
    }

    /**
     * Liczba rdzeni logicznych, czytana z `/proc/cpuinfo`.
     *
     * Poza Linuksem odpowiedzi nie ma i **nie zgadujemy jej**: obciążenie na
     * rdzeń policzone przez zmyśloną liczbę rdzeni byłoby liczbą, która wygląda
     * jak pomiar. `null` znaczy „nie wiadomo” i tak trafia do metryczki.
     * Procesu potomnego nie uruchamiamy — metryczka nie jest tego warta.
     */
    private static function cores(): ?int
    {
        $cpuinfo = @file_get_contents('/proc/cpuinfo');

        if (!is_string($cpuinfo)) {
            return null;
        }

        $count = preg_match_all('/^processor\s*:/m', $cpuinfo);

        return $count > 0 ? $count : null;
    }

    private static function font(bool $windowed): ?string
    {
        return $windowed
            ? (new GlfwFontLocator())->locate()
            : ImagickCapabilityService::getInstance()->monospaceFont();
    }

    private static function imageMagickVersion(): string
    {
        if (!extension_loaded('imagick')) {
            return 'unavailable';
        }

        return Imagick::getVersion()['versionString'];
    }
}
