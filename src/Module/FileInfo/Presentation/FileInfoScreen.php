<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Presentation;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Module\FileInfo\Application\Dto\EntryDescription;
use LightManager\Module\FileInfo\Application\UseCase\InspectSelectedEntryUseCase;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\ListView;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Module\ReadsContext;
use LightManager\Presentation\Ui\Resettable;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Ekran modułu: opis zaznaczonego pliku w środkowym panelu.
 *
 * Do kroku 20 ten sam opis pokazywało okno modalne otwierane `Enter`em na pliku.
 * Przeprowadzka nie jest kosmetyczna: `Enter` staje się w całej aplikacji
 * klawiszem **zatwierdzania** (P3), a opis pliku — pierwszym dowodem na to, że
 * kontrakt modułu wystarcza do wyprowadzenia z rdzenia działającej funkcji.
 *
 * Ekran implementuje `ReadsContext`, bo bez wiedzy o zaznaczeniu nie miałby czego
 * opisać. Kontekst przychodzi co klatkę, ale polecenie `file` **nie jest
 * uruchamiane co klatkę**: opis liczy się przy zmianie zaznaczenia i przy każdym
 * otwarciu ekranu (`reset()`), a między nimi stoi zapamiętany. Trzydzieści
 * procesów potomnych na sekundę kosztowałoby więcej niż cała reszta klatki.
 */
final class FileInfoScreen implements ScreenInterface, ReadsContext, Resettable
{
    private readonly ScrollWindow $window;

    /** Ścieżka, dla której policzono opis; `null` — jeszcze niczego nie liczono. */
    private ?string $path = null;

    private ?EntryDescription $description = null;

    public function __construct(
        private readonly InspectSelectedEntryUseCase $inspect,
        private readonly TranslatorPort $translator,
    ) {
        $this->window = new ScrollWindow();
    }

    public function id(): string
    {
        return 'file-info';
    }

    public function labelKey(): string
    {
        return 'module.file-info.name';
    }

    public function usesPreview(): bool
    {
        return false;
    }

    public function headerSuffix(): string
    {
        return '';
    }

    /**
     * Każde otwarcie ekranu liczy opis od nowa.
     *
     * Nie chodzi o zaznaczenie — to zmienia się samo — tylko o **ustawienia**:
     * zmiana limitu czasu albo argumentów polecenia ma być widoczna od razu, a
     * nie dopiero po przejściu na inny plik.
     */
    public function reset(): void
    {
        $this->path = null;
        $this->description = null;
        $this->window->scrollBy(-PHP_INT_MAX);
    }

    public function useContext(ModuleContext $context): void
    {
        $path = $context->selectionPath();

        if ($path === $this->path && $this->path !== null) {
            return;
        }

        $this->path = $path;
        $this->description = $this->inspect->execute($context);
        $this->window->useContext($path ?? '');
    }

    public function draw(Rect $bounds): array
    {
        if ($this->description === null) {
            return (new Label($this->translator->translate('module.file-info.nothing')))->draw($bounds);
        }

        $rows = $this->rows($bounds->columns);
        $capacity = max(0, $bounds->rows);

        $this->window->clamp(count($rows), $capacity);

        return (new ListView(
            array_slice($rows, $this->window->offset(), $capacity),
            null,
            $this->window->position(count($rows), $capacity),
        ))->draw($bounds);
    }

    public function bindings(): array
    {
        return [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.scroll'),
            KeyBinding::of([Key::Escape], 'help.key.back'),
        ];
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        switch ($key->key) {
            case Key::Escape:
                return ScreenOutcome::close();
            case Key::ArrowUp:
                $this->window->scrollBy(-1);

                return ScreenOutcome::stay();
            case Key::ArrowDown:
                $this->window->scrollBy(1);

                return ScreenOutcome::stay();
            default:
                return ScreenOutcome::stay();
        }
    }

    /**
     * Nazwa pliku, odstęp i opis zawinięty do szerokości panelu.
     *
     * Zawijanie, a nie przycinanie: opis `file` bywa jednym długim zdaniem
     * z listą cech, a ucięte wielokropkiem mówiłoby najmniej tam, gdzie mówi
     * najwięcej — na końcu.
     *
     * @return list<ListRow>
     */
    private function rows(int $columns): array
    {
        $description = $this->description;

        if ($description === null) {
            return [];
        }

        $rows = [
            new ListRow($description->name, '', Role::Accent),
            new ListRow('', '', Role::Muted),
        ];

        foreach ($description->lines as $line) {
            foreach (self::wrapped($line, max(1, $columns)) as $part) {
                $rows[] = new ListRow($part);
            }
        }

        return $rows;
    }

    /** @return list<string> */
    private static function wrapped(string $text, int $columns): array
    {
        if ($text === '') {
            return [''];
        }

        $lines = [];
        $current = '';

        foreach (explode(' ', $text) as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;

            if (mb_strlen($candidate) <= $columns) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
            }

            // Samo słowo dłuższe od panelu (ścieżka, suma kontrolna) idzie
            // w kawałkach — inaczej zniknęłoby w całości.
            while (mb_strlen($word) > $columns) {
                $lines[] = mb_substr($word, 0, $columns);
                $word = mb_substr($word, $columns);
            }

            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }
}
