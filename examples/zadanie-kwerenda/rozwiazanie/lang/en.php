<?php

declare(strict_types=1);

/**
 * Message catalogue of the exercise module — the counterpart of `pl.php`.
 *
 * Both files carry **exactly the same keys**: a key without a translation and
 * a translation without a key are the same defect. English is the reference
 * language here, so a module without this file has no catalogue at all as far
 * as the quality gate is concerned.
 */
return [
    'module.czas.name' => 'Uptime',
    'module.czas.description' => 'Tells how long this run has been going.',

    'module.czas.query.dzialanie' => 'seconds since this run started',
];
