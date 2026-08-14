<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use LightManager\Application\Ui\Frame;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Zrzut **bieżącej** klatki żywej aplikacji do pliku — prymitywy i obraz
 * (krok 38, D64).
 *
 * Powód jest zapisany w kroku 29: dwie pomyłki podglądu tekstu wyszły dopiero
 * na zrzucie prawdziwej klatki spod XTerma, robionym ręcznie narzędziem
 * systemowym (D60). `bin/render-bench` tego nie pokaże — mierzy treść
 * syntetyczną i taka ma zostać. Ten zrzut daje ten sam dowód bez sprzętu
 * i bez ceremonii.
 *
 * **Zamówienie jest odroczone o jedną klatkę i to jest cała konstrukcja.**
 * Komenda wykonuje się w chwili obsługi klawisza, czyli **zanim** klatka
 * powstanie; gdyby zapisywała „teraz”, zapisałaby klatkę poprzednią, jeszcze
 * z otwartym oknem komend. Zamiast tego zostawia znacznik, a zbiera go
 * `DumpingFrameRenderer` tuż po tym, jak klatka trafi na ekran.
 *
 * Usługa jest Singletonem jak każda inna usługa infrastruktury (reguła 3), ale
 * **nie mierzy czasu i nie wchodzi w koszt klatki**: gdy nikt nie zamówił
 * zrzutu, cała jej praca to sprawdzenie jednego pola.
 */
final class FrameDumpService extends AbstractSingleton
{
    /** Przyrostki plików — techniczne i nietłumaczone, jak nazwy wzorców. */
    public const PRIMITIVES_SUFFIX = '-prymitywy.txt';

    public const IMAGE_SUFFIX = '.png';

    private ?string $pendingPath = null;

    private ?FrameImageGrabber $grabber = null;

    private ?string $lastProblem = null;

    /**
     * Sposób oddania obrazu ustala `Bootstrap`, bo tylko on wie, który tor
     * został wybrany. Bez niego zrzut ogranicza się do prymitywów — i mówi to
     * wprost, zamiast po cichu zapisywać obraz z cudzego toru.
     */
    public function useGrabber(FrameImageGrabber $grabber): void
    {
        $this->grabber = $grabber;
    }

    /** Zamawia zrzut najbliższej klatki. */
    public function request(string $path): void
    {
        $this->pendingPath = $path;
        $this->lastProblem = null;
    }

    public function isPending(): bool
    {
        return $this->pendingPath !== null;
    }

    /**
     * Powód, dla którego ostatni zrzut nie powstał w całości — albo `null`.
     *
     * Zrzut dzieje się **poza klatką i poza komendą**, więc nie ma komu rzucić
     * wyjątku: pętla rysuje dalej, a użytkownik zobaczyłby najwyżej migające
     * okno. Kłopot jedzie więc tą samą drogą, co wynik — polem do odczytania.
     */
    public function lastProblem(): ?string
    {
        return $this->lastProblem;
    }

    /**
     * Zapisuje zamówiony zrzut. Wołane przez `DumpingFrameRenderer` po
     * narysowaniu klatki — nigdy przez pętlę i nigdy przez komendę.
     *
     * @return ?string ścieżka pliku z prymitywami albo `null`, gdy nikt nie prosił
     */
    public function captureIfRequested(Frame $frame): ?string
    {
        $path = $this->pendingPath;

        if ($path === null) {
            return null;
        }

        $this->pendingPath = null;

        return $this->write($frame, $path);
    }

    private function write(Frame $frame, string $path): ?string
    {
        $primitives = $path . self::PRIMITIVES_SUFFIX;

        if (file_put_contents($primitives, (new FrameSerializer())->toText($frame)) === false) {
            $this->lastProblem = $primitives;

            return null;
        }

        $this->writeImage($frame, $path . self::IMAGE_SUFFIX);

        return $primitives;
    }

    /**
     * Obraz jest **drugą** połową zrzutu i jego brak nie unieważnia pierwszej:
     * prymitywy odpowiadają na pytanie „co aplikacja kazała narysować”, obraz —
     * „jak to wyszło”. Kłopot z drugim zostaje zapisany, ale plik z pierwszym
     * i tak leży już na dysku.
     */
    private function writeImage(Frame $frame, string $path): void
    {
        if ($this->grabber === null) {
            $this->lastProblem = $path;

            return;
        }

        $image = $this->grabber->imageOf($frame);

        try {
            $image->setImageFormat('png');

            if (!$image->writeImage($path)) {
                $this->lastProblem = $path;
            }
        } finally {
            $image->clear();
        }
    }
}
