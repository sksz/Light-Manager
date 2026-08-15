<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application\Port;

use LightManager\Module\Ssh\Application\HostBook;

/**
 * Książka hostów wraz z tym, co poszło nie tak przy jej czytaniu (krok 48).
 *
 * Wzorem `LoadedPlaylist` z kroku 45: port nie rzuca, więc powód niepowodzenia
 * musi mieć czym wrócić. Rozdzielenie „pusta książka" od „książki nie dało się
 * przeczytać" jest przy tym istotne, a nie kosmetyczne — pierwsze jest
 * normalnym stanem pierwszego uruchomienia, drugie znaczy, że **zapis tego
 * pliku skasuje cudzą treść**, i użytkownik ma prawo o tym wiedzieć, zanim
 * dopisze pierwszy wpis.
 */
final readonly class LoadedHostBook
{
    public function __construct(
        public HostBook $book,
        /** Klucz katalogu z powodem; `null`, gdy odczyt się udał. */
        public ?string $problemKey = null,
        /** Czy pliku po prostu jeszcze nie ma — pierwsze uruchomienie modułu. */
        public bool $fresh = false,
    ) {
    }
}
