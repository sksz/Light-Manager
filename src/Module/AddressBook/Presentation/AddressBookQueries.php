<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation;

use LightManager\Application\Query\QueryRegistry;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\AddressBookView;
use LightManager\Module\AddressBook\Application\Addresses;
use LightManager\Module\AddressBook\Application\ChapterField;

/**
 * Odczyt danych książki — **przez rejestr kwerend, jak każdy inny** (krok 53,
 * D92 nr 3) — oraz jedno miejsce, w którym czyta się **cudze deklaracje pól**.
 *
 * Druga rola jest tu nowa i wynika wprost z D105 nr 3: rozdział zna nazwę
 * kwerendy, z której książka bierze spis swoich pól, a rejestrem kwerend
 * dysponuje `Presentation` — więc czytanie stoi w fasadzie, a nie w koordynatorze.
 *
 * **Deklaracji nie czyta się w trakcie odpowiadania na kwerendę** i nie jest to
 * ostrożność: rejestr odmawia pytania zadanego w trakcie odpowiadania („kwerenda
 * nie woła kwerendy", krok 53). Stąd `refreshChapters()` wołane jest z ekranu
 * i z łańcucha okien — czyli stamtąd, gdzie deklaracje są potrzebne.
 */
final class AddressBookQueries
{
    /** Pokolenie książki, przy którym ostatnio czytano deklaracje. */
    private int $chaptersRead = -1;

    public function __construct(
        private readonly QueryRegistry $queries,
        private readonly Addresses $addresses,
    ) {
    }

    public function view(): AddressBookView
    {
        $payload = $this->queries
            ->ask(AddressBookSettings::ID . '.entries')
            ->payloadFor(AddressBookSettings::ID);

        return $payload instanceof AddressBookView ? $payload : AddressBookView::empty();
    }

    /**
     * Dociąga pola rozdziałów z kwerend ich właścicieli.
     *
     * Pytanie pada **raz na rozdział i raz na pokolenie książki**, a nie co
     * klatkę: deklaracja jest stała w czasie uruchomienia, a rejestr kwerend
     * i tak pamięta odpowiedź po pokoleniu. Rozdział, którego właściciel milczy
     * (moduł wyłączony, odrzucony albo nieobecny), zostaje **bez pól** — i to
     * jest zwykły stan, nie awaria (reguła 15g).
     */
    public function refreshChapters(): void
    {
        $revision = $this->addresses->revision();

        if ($this->chaptersRead === $revision) {
            return;
        }

        $this->chaptersRead = $revision;

        foreach ($this->addresses->chapters() as $chapter) {
            $fields = [];

            foreach ($this->queries->ask($chapter->query)->rows() as $row) {
                $field = ChapterField::of($row);

                if ($field !== null) {
                    $fields[] = $field;
                }
            }

            $this->addresses->useChapterFields($chapter->owner, $fields);
        }
    }
}
