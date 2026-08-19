<?php

declare(strict_types=1);

/*
 * Strings of the "Address book" module — English (fallback catalogue).
 *
 * **Every key must start with `module.address-book.`** — the catalogue accepts
 * only those and skips the rest.
 */

return [
    'module.address-book.name' => 'Address book',
    'module.address-book.description' => 'Shared list of places: an entry has a name and an id, and modules add fields to it in chapters.',
    'module.address-book.screen' => 'Address book',

    'module.address-book.tab.all' => 'All',
    'module.address-book.column.name' => 'Name',
    'module.address-book.column.chapters' => 'Chapters',
    'module.address-book.column.id' => 'Id',

    'module.address-book.chapter.general' => 'General',
    'module.address-book.field.address' => 'Address',
    'module.address-book.field.note' => 'Note',

    'module.address-book.key.move' => 'move between entries',
    'module.address-book.key.chapter' => 'change chapter',
    'module.address-book.key.chapter.short' => 'chapter',
    'module.address-book.key.edit' => 'edit entry fields',
    'module.address-book.key.edit.short' => 'edit',
    'module.address-book.key.sort' => 'change sorting column',
    'module.address-book.key.sort.short' => 'sort',
    'module.address-book.key.add' => 'add entry',
    'module.address-book.key.add.short' => 'add',
    'module.address-book.key.remove' => 'remove entry',
    'module.address-book.key.remove.short' => 'remove',
    'module.address-book.key.filter' => 'narrow the list',
    'module.address-book.key.filter.short' => 'search',
    'module.address-book.focus.entries' => 'Entries',

    'module.address-book.header.filter' => 'Narrowed to "{filter}" — entries: {count}',
    'module.address-book.header.undeclared' => 'Nobody uses chapter "{chapter}" today — the values are here, the field descriptions are not',
    'module.address-book.empty' => 'The book is empty. F7 adds an entry.',
    'module.address-book.empty.filter' => 'Nothing matches. Ctrl+F changes the filter, Esc on an empty field drops it.',

    'module.address-book.prompt.name' => 'Entry name',
    'module.address-book.prompt.name.field' => 'name',
    'module.address-book.prompt.filter' => 'Narrow the list',
    'module.address-book.prompt.filter.field' => 'search',
    'module.address-book.prompt.field' => '{entry}: {field}',
    'module.address-book.flag.yes' => 'yes',
    'module.address-book.flag.no' => 'no',
    'module.address-book.confirm.remove' => 'Remove entry "{entry}" with the values of all its chapters?',
    'module.address-book.confirm.forget' => 'Remove the values of chapter "{chapter}" from every entry?',

    'module.address-book.command.show' => 'open the address book',
    'module.address-book.command.chapter' => 'announce the use of a chapter',
    'module.address-book.command.field' => 'announce the use of a chapter field',
    'module.address-book.command.add' => 'add an entry',
    'module.address-book.command.rename' => 'rename an entry',
    'module.address-book.command.remove' => 'remove an entry',
    'module.address-book.command.set' => 'write a field value',
    'module.address-book.command.clear' => 'clear a field or a chapter of an entry',
    'module.address-book.command.edit' => 'walk through the fields of a chapter',
    'module.address-book.command.forget' => 'remove the values of a chapter from every entry',

    'module.address-book.argument.entry' => 'entry',
    'module.address-book.argument.chapter' => 'chapter',
    'module.address-book.argument.field' => 'field',
    'module.address-book.argument.value' => 'value',
    'module.address-book.argument.name' => 'name',
    'module.address-book.argument.title' => 'title key',
    'module.address-book.argument.label' => 'label key',
    'module.address-book.argument.kind' => 'kind',
    'module.address-book.argument.default' => 'default value',
    'module.address-book.argument.choices' => 'allowed values',

    'module.address-book.query.entries' => 'book entries, optionally with the values of a chapter',
    'module.address-book.query.entry' => 'a single book entry',
    'module.address-book.query.chapters' => 'chapters: declared and present in the data',
    'module.address-book.query.fields' => 'fields of a chapter',
    'module.address-book.query.value' => 'value of a single field, masked ones included',
    'module.address-book.query.last' => 'id of the entry added most recently',

    'module.address-book.kind.text' => 'text',
    'module.address-book.kind.number' => 'number',
    'module.address-book.kind.flag' => 'yes/no',
    'module.address-book.kind.choice' => 'choice',
    'module.address-book.kind.secret' => 'secret',
    'module.address-book.kind.entry' => 'entry',

    'module.address-book.message.added' => 'Added entry "{entry}" ({id}).',
    'module.address-book.message.renamed' => 'Entry {id} is now called "{name}".',
    'module.address-book.message.removed' => 'Removed entry "{entry}".',
    'module.address-book.message.set' => 'Saved {chapter}.{field}.',
    'module.address-book.message.cleared' => 'Cleared chapter "{chapter}" of this entry.',
    'module.address-book.message.edited' => 'Changed entry "{entry}".',
    'module.address-book.message.forgotten' => 'Removed the values of chapter "{chapter}" from {count} entries.',
    'module.address-book.message.nothing.forget' => 'No entry has values in chapter "{chapter}".',
    'module.address-book.message.unknown' => 'There is no entry with id "{entry}".',
    'module.address-book.message.noFields' => 'Chapter "{chapter}" has no declared fields today.',
    'module.address-book.message.noCommand' => 'The address book command is unavailable.',
    'module.address-book.message.copied' => 'Copied the row of entry "{entry}".',

    'module.address-book.entry.id.invalid' => 'Id "{id}" is not twelve hexadecimal characters.',
    'module.address-book.entry.name.invalid' => 'Name "{name}" has control characters or is too long.',
    'module.address-book.chapter.invalid' => 'Chapter name "{chapter}" does not match [a-z][a-z0-9-]*.',
    'module.address-book.field.invalid' => 'Field key "{field}" does not match [a-zA-Z][a-zA-Z0-9_-]*.',
    'module.address-book.value.invalid' => 'Value of field "{field}" has control characters or is too long.',
    'module.address-book.value.number' => 'Field "{field}" takes a number, not "{value}".',
    'module.address-book.value.choice' => 'Field "{field}" takes one of: {choices}.',
    'module.address-book.value.entry' => 'Field "{field}" points at entry "{entry}", which is not in the book.',
    'module.address-book.field.incomplete' => 'The declaration of field "{field}" is incomplete or has an unknown kind.',
    'module.address-book.field.conflict' => 'Field {chapter}.{field} is already declared differently — the first declaration stands.',
    'module.address-book.book.unreadable' => 'The entries cannot be read from the state file.',

    'module.address-book.setting.order' => 'List order',

    'module.address-book.event.entry.added' => 'entry added',
    'module.address-book.event.entry.changed' => 'entry changed',
    'module.address-book.event.entry.removed' => 'entry removed',
    'module.address-book.event.chapter.declared' => 'chapter use announced',

    'module.address-book.help.entry' => 'An entry carries a name and an id. The id is its identity, so the name may be changed, repeated or left empty — references held by other modules will not notice.',
    'module.address-book.help.chapter' => 'A chapter is a named group of fields, declared by whoever uses it. The tab shows columns from that declaration; a chapter without one shows raw keys.',
    'module.address-book.help.access' => 'A chapter belongs to nobody: every module and the book itself read and write across all chapters through the same commands and queries.',
    'module.address-book.help.secret' => 'A field of kind "secret" is masked on screen and stays out of list rows. It is not encrypted — the state file is mode 0600 and that is all.',
];
