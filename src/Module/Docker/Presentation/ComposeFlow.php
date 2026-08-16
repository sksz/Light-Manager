<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Docker\Application\ComposeAction;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\Port\ComposePort;

/**
 * Podnoszenie i kładzenie projektu compose (krok 51).
 *
 * Klasa istnieje z tego samego powodu, co `BuildFlow`: czynność ma **dwa
 * wejścia** — komendy `docker.up` i `docker.down` — a rozstrzygnięcie, gdzie
 * właściwie leży plik projektu, jest w obu takie samo (reguła 11n).
 *
 * **Ścieżka wskazuje plik albo katalog** i to jest cała treść tej klasy poza
 * dwoma wywołaniami portu: użytkownik stoi w przeglądarce **w katalogu**, więc
 * kontekst podaje katalog, a `compose -f` chce pliku. Nazwy sprawdzamy w tej
 * samej kolejności, w której szuka ich sam klient — `compose.yaml` przed
 * `docker-compose.yaml` — bo inna kolejność podniosłaby w projekcie mającym oba
 * pliki co innego niż `docker compose up` wywołane z powłoki.
 */
final class ComposeFlow
{
    /**
     * Nazwy plików projektu w kolejności, w jakiej szuka ich klient.
     *
     * Kolejność jest **cytatem z dokumentacji Compose**, a nie naszym wyborem:
     * `compose.yaml` jest nazwą zalecaną, `docker-compose.yml` — historyczną.
     *
     * @var list<string>
     */
    private const FILE_NAMES = [
        'compose.yaml',
        'compose.yml',
        'docker-compose.yaml',
        'docker-compose.yml',
    ];

    public function __construct(
        private readonly ComposePort $compose,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function up(string $path): ?Message
    {
        return $this->begin(ComposeAction::Up, $path);
    }

    public function down(string $path): ?Message
    {
        return $this->begin(ComposeAction::Down, $path);
    }

    /**
     * Zaczyna czynność albo mówi, dlaczego się nie da.
     *
     * `null` znaczy „ruszyło” — o skutku powie ekran, gdy praca się skończy.
     * Zdanie zwrotne pada wyłącznie wtedy, gdy praca **nie zaczęła się w ogóle**:
     * plik nie istnieje albo poprzednia czynność jeszcze trwa.
     */
    private function begin(ComposeAction $action, string $path): ?Message
    {
        if ($this->compose->state()->isWorking()) {
            return Message::warning($this->text('compose.busy'));
        }

        $file = self::fileAt($path);

        if ($file === null) {
            return Message::error($this->text('compose.noFile', ['path' => $path]));
        }

        $this->compose->begin($action, $file);

        return null;
    }

    /**
     * Plik projektu pod wskazaną ścieżką — `null`, gdy żadnego tam nie ma.
     *
     * Odczyt dysku pada tu **w obsłudze komendy**, a nie w rysowaniu klatki, więc
     * mieści się w regule „żadne wywołanie sieciowe ani dyskowe nie pada
     * w klatce”: to jedno `is_file()` na cztery nazwy, w chwili naciśnięcia
     * `Enter` w oknie komend.
     */
    private static function fileAt(string $path): ?string
    {
        $trimmed = trim($path);

        if ($trimmed === '') {
            return null;
        }

        if (is_file($trimmed)) {
            return $trimmed;
        }

        if (!is_dir($trimmed)) {
            return null;
        }

        $directory = rtrim($trimmed, DIRECTORY_SEPARATOR);

        foreach (self::FILE_NAMES as $name) {
            $candidate = $directory . DIRECTORY_SEPARATOR . $name;

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @param array<string, string|int|float> $parameters */
    private function text(string $key, array $parameters = []): string
    {
        return $this->translator->translate('module.' . DockerSettings::ID . '.' . $key, $parameters);
    }
}
