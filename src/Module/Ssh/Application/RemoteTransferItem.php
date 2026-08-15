<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application;

/**
 * Jeden plik do przeniesienia — **wraz z rozmiarem znanym z góry** (krok 50).
 *
 * Rdzeniowy `FileTransferPort` bierze same ścieżki i liczy rozmiary sam, bo
 * `stat()` na dysku kosztuje mikrosekundy. Tutaj policzyć ich nie sposób: rozmiar
 * pliku zdalnego kosztowałby **obieg do serwera na wpis**, a wypis `sftp ls -l`
 * — ten sam, którym panel narysował listę — podał go już przy okazji listowania.
 * Rozmiar jedzie więc razem ze ścieżką i to on jest mianownikiem paska postępu
 * „od pierwszego bajtu”, którego wymagają kryteria kroku.
 *
 * Nazwa stoi obok ścieżki, mimo że da się ją z niej wyciąć, i nie jest to
 * wygodnictwo: ścieżka źródła bywa zdalna (rozdzielana zawsze `/`), a cel
 * lokalny — składany `DIRECTORY_SEPARATOR`em. Jedna klasa wycinająca nazwę
 * z obu musiałaby wiedzieć, którą z nich właśnie ogląda.
 */
final readonly class RemoteTransferItem
{
    public function __construct(
        /** Ścieżka bezwzględna po stronie źródła. */
        public string $path,
        /** Sama nazwa — do pokazania w oknie i do złożenia ścieżki w celu. */
        public string $name,
        /** Rozmiar w bajtach; `0` jest poprawną wartością pustego pliku. */
        public int $sizeInBytes,
    ) {
    }
}
