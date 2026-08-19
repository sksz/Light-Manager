<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation\Query;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\SuggestionSource;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\Addresses;

/**
 * `address-book.fields` — deklaracja pól jednego rozdziału (krok 60).
 *
 * Argument przyjmuje **dowolny** rozdział: pytający nie musi go deklarować ani
 * „mieć" (D104 nr 1). Rozdział bez deklaracji oddaje pustą listę pól — i to
 * jest prawdziwa odpowiedź, a nie odmowa: wartości w nim są, opisu nie ma.
 *
 * Kolumna `secret` mówi, że wartość pola **zasłania się przy pokazywaniu**;
 * nie mówi, że jest komukolwiek niedostępna (D104 nr 6).
 */
final class FieldsQuery implements QueryInterface
{
    public const CHAPTER = 'chapter';

    public function __construct(private readonly Addresses $addresses)
    {
    }

    public function name(): string
    {
        return AddressBookSettings::ID . '.fields';
    }

    public function descriptionKey(): string
    {
        return AddressBookSettings::key('query.fields');
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::CHAPTER,
                AddressBookSettings::key('argument.chapter'),
                suggestions: SuggestionSource::OnDemand,
            ),
        ];
    }

    public function generation(): int
    {
        return $this->addresses->chapterGeneration();
    }

    public function ask(CommandInput $input): QueryResult
    {
        $view = $this->addresses->chapterView(trim($input->text(self::CHAPTER)));

        return QueryResult::owned(AddressBookSettings::ID, $view, static function () use ($view): array {
            $rows = [];

            foreach ($view->fields as $position => $field) {
                $rows[] = [
                    'chapter' => $view->id,
                    'key' => $field->key,
                    'label' => $field->labelKey,
                    'kind' => $field->kind->value,
                    'default' => $field->default,
                    'choices' => implode(',', $field->choices),
                    'secret' => $field->kind->isMasked(),
                    'position' => $position,
                ];
            }

            return $rows;
        });
    }
}
