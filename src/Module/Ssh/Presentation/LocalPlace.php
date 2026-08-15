<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation;

use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;

/**
 * Ostatnie miejsce, w którym użytkownik stał **na tej maszynie** (krok 50).
 *
 * Klasa istnieje z powodu, którego plan kroku nie przewidywał, a rozpoznanie
 * wykryło przed pierwszą linią kodu (D89 nr 8): **kontekst sesji jest jeden**,
 * a ekran zdalny nadpisuje go własnym, z pochodzeniem `Remote` (krok 49). W
 * chwili przesyłu lokalnej ścieżki nie ma więc czego zapytać — dokładnie wtedy,
 * gdy jest potrzebna.
 *
 * Zatrzask rozwiązuje to bez zmiany w rdzeniu: `FrameComposer` podaje ekranowi
 * kontekst **przed** rysowaniem, więc w pierwszej klatce po przełączeniu przychodzi
 * tu jeszcze ten opublikowany przez przeglądarkę. Zapamiętujemy go i od tej pory
 * ekran zdalny wie, dokąd pobierać i co wysyłać, **nie sięgając do cudzego
 * modułu** (reguła 15) — bo czyta rdzeniową daną, a nie przeglądarkę.
 *
 * Cena jest nazwana i widoczna: przy module `ssh` jako ekranie startowym
 * lokalnego kontekstu nie było nigdy, więc `path()` oddaje `null`, a okno pyta
 * o katalog z podpowiedzią z `getcwd()`. Zdalnego kontekstu klasa **nie
 * przyjmuje w ogóle** — pochodzenie jest tu jedynym warunkiem wpuszczenia.
 */
final class LocalPlace
{
    private ?ModuleContext $context = null;

    /** Przyjmuje wyłącznie kontekst z tej maszyny; zdalny mija bez śladu. */
    public function remember(ModuleContext $context): void
    {
        if ($context->isRemote() || $context->path === '') {
            return;
        }

        $this->context = $context;
    }

    /** Katalog, w którym stoi przeglądarka, albo `null`, gdy nikt go nie opublikował. */
    public function path(): ?string
    {
        return $this->context?->path;
    }

    /**
     * Nazwa zaznaczonego pliku — **wyłącznie pliku**.
     *
     * Katalog oddaje `null` z tego samego powodu, dla którego katalogów nie ma
     * w zakresie kroku (D89 nr 5): przesył drzewa nie ma mianownika, którym
     * mógłby wypełnić pasek.
     */
    public function fileName(): ?string
    {
        if ($this->context?->kind !== ContextEntryKind::File) {
            return null;
        }

        return $this->context->selection;
    }

    /** Pełna ścieżka zaznaczonego pliku albo `null`. */
    public function filePath(): ?string
    {
        return $this->fileName() === null ? null : $this->context?->selectionPath();
    }

    /**
     * Rozmiar zaznaczenia, jeśli wydawca go zna.
     *
     * `null` jest tu odpowiedzią, nie brakiem: wołający sięga wtedy po `stat`,
     * bo plik leży na tej samej maszynie i pytanie jest darmowe.
     */
    public function fileBytes(): ?int
    {
        return $this->fileName() === null ? null : $this->context?->selectionBytes;
    }
}
