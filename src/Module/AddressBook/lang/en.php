<?php

declare(strict_types=1);

/*
 * Address book module strings — English (fallback catalogue).
 *
 * Every key must start with `module.address-book.`; the catalogue skips the rest.
 * Chapter field labels have no keys here on purpose: they are declared by the
 * module that opened the chapter (step 60, D105 no. 3).
 */

return [
    'module.address-book.name' => 'Address book',
    'module.address-book.description' => 'Addresses of things that live somewhere — shared by every module that connects to them.',
    'module.address-book.screen' => 'Address book',

    'module.address-book.setting.order' => 'List order',

    'module.address-book.column.name' => 'Name',
    'module.address-book.column.address' => 'Address',
    'module.address-book.column.id' => 'Entry',

    'module.address-book.empty' => 'The address book is empty. F7 adds an entry.',
    'module.address-book.empty.filtered' => 'No entry matches the filter. Esc clears it.',
    'module.address-book.unnamed' => '(no name)',
    'module.address-book.noAddress' => '(no address)',
    'module.address-book.filter.active' => 'filtered: {filter}',
    'module.address-book.book.unreadable' => 'The address book could not be read — the state file is damaged or written by another version.',

    'module.address-book.key.add' => 'add address',
    'module.address-book.key.add.short' => 'add',
    'module.address-book.key.edit' => 'edit entry',
    'module.address-book.key.edit.short' => 'edit',
    'module.address-book.key.remove' => 'remove entry',
    'module.address-book.key.remove.short' => 'remove',
    'module.address-book.key.filter' => 'filter list',
    'module.address-book.key.filter.short' => 'filter',

    'module.address-book.prompt.name' => 'Entry name — may be left empty.',
    'module.address-book.prompt.name.field' => 'Name',
    'module.address-book.prompt.address' => 'Address of "{name}" — may be left empty.',
    'module.address-book.prompt.address.field' => 'Address',
    'module.address-book.prompt.field' => '{field} — entry "{name}".',
    'module.address-book.prompt.keep' => 'leave unchanged',
    'module.address-book.prompt.filter' => 'Filter the list — by name, address and identifier.',
    'module.address-book.prompt.filter.field' => 'Filter',
    'module.address-book.field.flag.yes' => 'yes',
    'module.address-book.field.flag.no' => 'no',

    'module.address-book.confirm.remove' => 'Remove entry "{name}" from the address book?',
    'module.address-book.added' => 'Added "{name}" — entry identifier: {id}.',
    'module.address-book.changed' => 'Changed entry "{name}" ({id}).',
    'module.address-book.removed' => 'Removed entry "{name}".',
    'module.address-book.copied' => 'Address copied: {address}',
    'module.address-book.unknown' => 'The address book has no entry "{entry}" — check the identifier.',
    'module.address-book.chapter.incomplete' => 'A chapter needs a module identifier and the name of the query declaring its fields.',

    'module.address-book.entry.id.invalid' => 'Entry identifier "{id}" is not eight hexadecimal characters.',
    'module.address-book.entry.name.invalid' => 'Name "{name}" contains control characters or is too long.',
    'module.address-book.entry.address.invalid' => 'Address "{address}" contains a space or control character, or starts with a dash.',
    'module.address-book.entry.chapter.invalid' => 'Chapter "{chapter}" is not a module identifier.',
    'module.address-book.entry.field.invalid' => 'Field "{field}" is not a usable field key.',

    'module.address-book.command.show' => 'show the address book',
    'module.address-book.command.add' => 'add an address to the book',
    'module.address-book.command.remove' => 'remove an entry from the book',
    'module.address-book.command.chapter' => 'open a book chapter for a module',

    'module.address-book.query.entries' => 'address book entries',
    'module.address-book.query.entry' => 'a single address book entry',

    'module.address-book.argument.name' => 'name',
    'module.address-book.argument.address' => 'address',
    'module.address-book.argument.entry' => 'entry',
    'module.address-book.argument.id' => 'entry identifier',
    'module.address-book.argument.chapter' => 'chapter',
    'module.address-book.argument.module' => 'module',
    'module.address-book.argument.query' => 'query declaring the fields',
    'module.address-book.argument.label' => 'string key with the chapter title',

    'module.address-book.event.entry.added' => 'address added',
    'module.address-book.event.entry.changed' => 'address changed',
    'module.address-book.event.entry.removed' => 'address removed',

    'module.address-book.help.start' => 'Ctrl+W opens the address book: the list of places other modules connect to.',
    'module.address-book.help.id' => 'An entry is identified by its identifier, not its name — the name may be changed or left empty, and references held by other modules will not notice.',
    'module.address-book.help.chapters' => 'Fields beyond name and address are added by modules: the remote session adds port, user and authentication method, and the book asks for them in the same chain of windows.',
    'module.address-book.help.secrets' => 'Passwords, keys and certificates are never kept in the book — they stay with the module that presents them.',
    'module.address-book.help.remove' => 'Removing an entry does not warn other modules: a Docker tunnel entry pointing at the removed address will say so only when it tries to connect.',
];
