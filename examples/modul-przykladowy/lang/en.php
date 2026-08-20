<?php

declare(strict_types=1);

/**
 * Message catalogue of the example module — the counterpart of `pl.php`.
 *
 * Both files carry **exactly the same keys**: a key without a translation and
 * a translation without a key are the same defect, and a quality-gate test
 * watches for both.
 */
return [
    'module.przyklad.name' => 'Example',
    'module.przyklad.description' => 'A model module for the developer guide.',

    'module.przyklad.setting.ton' => 'Greeting tone',

    'module.przyklad.command.powitanie' => 'say hello',
    'module.przyklad.argument.imie' => 'name',

    'module.przyklad.query.stan' => 'greeting tone set in the module',

    'module.przyklad.message.zwykle' => 'Hello, {imie}.',
    'module.przyklad.message.glosne' => 'HELLO, {imie}!',
];
