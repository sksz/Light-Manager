<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application\UseCase;

use LightManager\Application\Port\SettingsPort;
use LightManager\Module\FileInfo\Application\Dto\EntryKind;
use LightManager\Module\FileInfo\Application\Dto\TextAnchor;
use LightManager\Module\FileInfo\Application\Dto\TextWindow;
use LightManager\Module\FileInfo\Application\FileInfoSettings;
use LightManager\Module\FileInfo\Application\Port\TextPreviewPort;

/**
 * Treść pliku tekstowego — trzecia odpowiedź prawego panelu opisu.
 *
 * Przypadek użycia stoi obok `PreviewEntryUseCase`, a nie w nim, i to jest
 * rozstrzygnięcie: miniatura i tekst wykluczają się w panelu, ale nie
 * w kodzie — pierwsza pyta o nagłówek obrazu i pamięta wynik pod ścieżką,
 * druga czyta przesuwnym oknem, którego treść zmienia się przy każdym
 * przewinięciu. Wspólny byłby im wyłącznie `if`.
 *
 * Pamięć podręczna jest tu **konieczna, nie oszczędna**: panel pyta o okno
 * przy każdym rysowaniu, czyli trzydzieści razy na sekundę, a odpowiedź zmienia
 * się wyłącznie wtedy, gdy zmieni się kotwica albo geometria panelu. Bez niej
 * podgląd czytałby z dysku trzydzieści razy na sekundę to samo.
 */
final class PreviewTextUseCase
{
    private ?string $cachedKey = null;

    private ?TextWindow $cached = null;

    public function __construct(
        private readonly TextPreviewPort $texts,
        private readonly SettingsPort $settings,
    ) {
    }

    /**
     * Okno podglądu albo `null`, gdy podglądu tekstu nie ma **w ogóle**:
     * wyłączony w ustawieniach modułu albo wpis, który tekstem być nie może.
     *
     * Różnica między `null` a oknem z powodem odmowy jest widoczna dla
     * użytkownika: pierwsze zostawia dawny napis „(brak podglądu)”, drugie
     * mówi, dlaczego treści nie widać.
     *
     * @param ?string $description wyjście polecenia `file`, jeśli moduł je ma
     */
    public function execute(
        ?string $path,
        EntryKind $kind,
        ?string $description,
        TextAnchor $anchor,
        int $lines,
        int $characters,
    ): ?TextWindow {
        if ($path === null || $kind !== EntryKind::File) {
            return null;
        }

        if (!FileInfoSettings::textPreview($this->settings->current())) {
            return null;
        }

        $key = $path . '|' . $anchor->signature() . '|' . $lines . 'x' . $characters;

        if ($this->cachedKey === $key && $this->cached !== null) {
            return $this->cached;
        }

        $refusal = $this->texts->refuse($path, $description);
        $this->cachedKey = $key;

        return $this->cached = $refusal !== null
            ? TextWindow::problem($refusal)
            : $this->texts->forward($path, $anchor, $lines, $characters);
    }

    /** Kotwica o panel wyżej — jedyny ruch, którego nie da się wyczytać z okna. */
    public function previous(string $path, TextAnchor $anchor, int $lines, int $characters): TextAnchor
    {
        return $this->texts->backward($path, $anchor, $lines, $characters);
    }
}
