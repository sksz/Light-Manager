<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Ssh\Application\RemoteBrowser;
use LightManager\Module\Ssh\Application\RemoteView;
use LightManager\Module\Ssh\Application\SshSettings;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntry;
use LightManager\Module\Ssh\Presentation\Component\RemoteSize;

/**
 * `ssh.entries` — zdalny katalog wraz z etapem pracy. **Nigdy nie czeka na sieć.**
 *
 * To jest wykonanie reguły nr 4 kwerendy na najostrzejszym przypadku, jaki ma ta
 * aplikacja: odczyt katalogu zdalnego trwa **niemal sekundę** (krok 49 zmierzył
 * ~0,93 s na otwarcie kanału `sftp` po pętli zwrotnej), a klatka trwa 33 ms.
 * Pytanie zadane w trakcie odpowiada `listing` i pustą listą — i to jest
 * pełnoprawna odpowiedź, a nie brak odpowiedzi. Reguła nadrzędna Fazy XVII
 * („żadne wywołanie sieciowe nie pada w rysowaniu klatki") jest tu spełniona
 * mocniej niż wymaga: kwerenda nie dotyka nawet portu, bo listę przyjmuje takt
 * (`RemoteBrowser::tick()`).
 *
 * **Ulotna z tego samego powodu, co `browser.entries`**: katalog zmienia się przy
 * wejściu, wyjściu wyżej, odświeżeniu, filtrze, przełączeniu ukrytych i przy
 * każdym ruchu kursora, więc licznik trzeba by bić w siedmiu miejscach —
 * a przeoczone uderzenie znaczy panel, który nie zauważył zmiany. Cena jest
 * bliska zeru: `ask()` składa migawkę z gotowych pól, a wiersze budują się
 * **leniwie**.
 */
final class EntriesQuery implements QueryInterface
{
    /** Ten sam zapis czasu, co w kolumnie „Zmieniony" i w `browser.entries` (D93 nr 2). */
    private const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly RemoteBrowser $browser,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return SshSettings::ID . '.entries';
    }

    public function descriptionKey(): string
    {
        return 'module.' . SshSettings::ID . '.query.entries';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return self::VOLATILE;
    }

    public function ask(CommandInput $input): QueryResult
    {
        $state = $this->browser->state();
        $view = new RemoteView(
            $state->stage,
            $this->browser->host(),
            $this->browser->path(),
            $this->browser->entries(),
            $this->browser->cursor(),
            $this->browser->filter(),
            $this->browser->showsHidden(),
            $this->browser->hasListing(),
            $state->problemKey,
            $state->problemParameters,
        );

        $translator = $this->translator;

        return QueryResult::owned(SshSettings::ID, $view, static function () use ($view, $translator): array {
            $stage = strtolower($view->stage->name);
            $rows = [];
            $selected = $view->selected();

            foreach ($view->entries as $entry) {
                $rows[] = self::describe($entry, $selected === $entry, $stage, $translator);
            }

            // Etap wchodzi do **każdego** wiersza, a przy pustej liście zostaje
            // wiersz sam z etapem — i to jest cała odpowiedź na „wraz z etapem
            // pracy" z planu kroku. Bez tego katalog czytany właśnie z sieci
            // wyglądałby dla obcego dokładnie tak, jak katalog pusty, i tak samo
            // jak katalog, którego nie udało się przeczytać. Właściciel tego
            // wiersza nie widzi — ma etap w ładunku.
            return $rows === [] ? [self::stageOnly($view->stage->name, $view->problemKey)] : $rows;
        });
    }

    /** @return array<string, string|int|bool> */
    private static function stageOnly(string $stage, ?string $problemKey): array
    {
        return [
            'name' => '',
            'kind' => '',
            'size' => '',
            'modified' => '',
            'permissions' => '',
            'hidden' => false,
            'selected' => false,
            'stage' => strtolower($stage),
            'problem' => $problemKey ?? '',
        ];
    }

    /**
     * @return array<string, string|int|bool>
     */
    private static function describe(
        RemoteEntry $entry,
        bool $selected,
        string $stage,
        TranslatorPort $translator,
    ): array {
        return [
            'name' => $entry->name,
            'kind' => strtolower($entry->type->name),
            // Rozmiar liczy `RemoteSize` — ta sama klasa, którą podaje go kolumna
            // listy i licznik okna przesyłu (krok 50). Katalog oddaje pusty napis,
            // bo jego zajętości nie zna nikt poza `du`, a zero byłoby nieprawdą.
            'size' => $entry->isDirectory() || $entry->sizeInBytes === null
                ? ''
                : RemoteSize::of($translator, $entry->sizeInBytes),
            'modified' => $entry->modifiedAt === null ? '' : date(self::TIMESTAMP_FORMAT, $entry->modifiedAt),
            'permissions' => $entry->permissionsAsText(),
            'hidden' => $entry->isHidden(),
            'selected' => $selected,
            'stage' => $stage,
            'problem' => '',
        ];
    }
}
