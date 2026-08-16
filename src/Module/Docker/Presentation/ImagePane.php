<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Domain\ValueObject\Image;
use LightManager\Module\Docker\Presentation\Component\ImageSize;
use LightManager\Presentation\Ui\Component\Align;
use LightManager\Presentation\Ui\Component\Column;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\ListView;
use LightManager\Presentation\Ui\Component\Table;
use LightManager\Presentation\Ui\Component\TableRow;
use LightManager\Presentation\Ui\Container\Span;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Lista obrazów i opis wybranego (krok 51).
 *
 * Cztery kolumny wobec pięciu w liście kontenerów — i ta różnica jest właśnie
 * tym, po co `Column` z kroku 27 w ogóle powstał: ten sam komponent układa dwie
 * różne tabele, bo rozdział szerokości należy do danych, a nie do kodu tabeli.
 *
 * **Kolumn jest trzy, a nie cztery**, i czwarta („Użyć") wypadła po obejrzeniu
 * klatki: demon nie wypełnia pola `Containers` na tym zasobie w żadnym wariancie
 * zapytania, więc kolumna stała pusta w każdym wierszu i zabierała osiem znaków
 * nazwie obrazu. Powód stoi przy `Image`.
 *
 * **Obraz osierocony jest tu obywatelem pierwszej kategorii**, a nie usterką:
 * zostaje po przebudowie, gdy etykieta przeszła na nowszą warstwę, i to
 * właśnie takie obrazy zajmują dysk niezauważone. Lista pokazuje go rolą
 * przygaszoną wraz z samym skrótem treści — widać, że jest, i widać, że nie ma
 * nazwy.
 */
final class ImagePane
{
    private const NAME_MINIMUM = 20;

    private const SIZE_WIDTH = 10;

    private const AGE_WIDTH = 12;

    /** Ile bajtów ma kibibajt — i każdy następny stopień. */
    public function __construct(
        private readonly DockerQueries $reader,
        private readonly TranslatorPort $translator,
        private readonly ScrollWindow $window,
    ) {
    }

    /** @return list<Primitive> */
    public function draw(Rect $bounds): array
    {
        $entries = $this->reader->images()->entries;

        if ($entries === []) {
            return (new Label($this->emptySentence()))->draw($bounds);
        }

        $count = count($entries);
        $capacity = Table::capacityOf($bounds, withHeader: true);
        $cursor = $this->reader->images()->cursor;
        $offset = $this->window->keepVisible($cursor, $count, $capacity);

        return (new Table(
            $this->columns(),
            $this->rows(array_slice($entries, $offset, max(1, $capacity))),
            $cursor - $offset,
            $this->window->position($count, $capacity),
            withHeader: true,
        ))->draw($bounds);
    }

    /** @return list<Primitive> */
    public function drawDetails(Rect $bounds): array
    {
        $image = $this->reader->images()->selected();

        if ($image === null) {
            return [];
        }

        return (new ListView($this->detailRows($image)))->draw($bounds);
    }

    public function capacityOf(Rect $bounds): int
    {
        return max(1, Table::capacityOf($bounds, withHeader: true));
    }

    /** @return list<Column> */
    private function columns(): array
    {
        return [
            new Column(Span::flexible(self::NAME_MINIMUM), Align::Left, $this->text('column.image')),
            new Column(Span::rigid(self::SIZE_WIDTH, 1), Align::Right, $this->text('column.size')),
            new Column(Span::rigid(self::AGE_WIDTH, 0), Align::Right, $this->text('column.created')),
        ];
    }

    /**
     * @param list<Image> $images
     *
     * @return list<TableRow>
     */
    private function rows(array $images): array
    {
        $rows = [];

        foreach ($images as $image) {
            $rows[] = new TableRow(
                [
                    $image->label(),
                    $this->size($image->sizeInBytes),
                    $this->age($image->createdAt),
                ],
                $image->isDangling() ? Role::Muted : Role::Text,
            );
        }

        return $rows;
    }

    /** @return list<ListRow> */
    private function detailRows(Image $image): array
    {
        $rows = [
            new ListRow($this->text('detail.id'), $image->id->short()),
            new ListRow($this->text('detail.size'), $this->size($image->sizeInBytes)),
        ];

        // Nazw bywa **kilka na jeden obraz** i pokazujemy wszystkie: usunięcie
        // dotyczy pierwszej z nich, więc użytkownik ma prawo wiedzieć, co jeszcze
        // wskazuje na tę samą treść.
        foreach ($image->tags as $index => $tag) {
            $rows[] = new ListRow($index === 0 ? $this->text('detail.tags') : '', $tag);
        }

        if ($image->isDangling()) {
            $rows[] = new ListRow('', $this->text('images.dangling'), Role::Muted);
        }

        return $rows;
    }

    private function emptySentence(): string
    {
        $problem = $this->reader->images()->problemKey;

        if ($problem !== null) {
            return $this->translator->translate($problem);
        }

        return $this->reader->images()->loaded ? $this->text('images.empty') : $this->text('images.reading');
    }

    /**
     * Rozmiar w postaci „1,4 GB” — liczy go `ImageSize`.
     *
     * Rachunek wyszedł stąd w kroku 54, gdy dostał **drugiego** użytkownika
     * (kwerenda `docker.images`). Metoda zostaje jako jedno wywołanie, bo panel
     * woła ją w dwóch miejscach i nazwa `size()` mówi tu więcej niż nazwa klasy.
     */
    private function size(?int $bytes): string
    {
        return ImageSize::of($this->translator, $bytes);
    }

    private function age(?int $createdAt): string
    {
        if ($createdAt === null || $createdAt <= 0) {
            return '';
        }

        $seconds = max(0, time() - $createdAt);

        return match (true) {
            $seconds < 3600 => $this->plural('age.minutes', intdiv($seconds, 60)),
            $seconds < 86_400 => $this->plural('age.hours', intdiv($seconds, 3600)),
            default => $this->plural('age.days', intdiv($seconds, 86_400)),
        };
    }

    /** Liczbę wstawia sam tłumacz pod `{count}` — podana drugi raz nadpisałaby zapis języka. */
    private function plural(string $key, int $count): string
    {
        return $this->translator->plural('module.' . DockerSettings::ID . '.' . $key, $count);
    }

    private function text(string $key): string
    {
        return $this->translator->translate('module.' . DockerSettings::ID . '.' . $key);
    }
}
