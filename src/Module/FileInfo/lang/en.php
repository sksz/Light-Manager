<?php

declare(strict_types=1);

/*
 * Napisy modułu „Opis pliku” — angielski (język zapasowy).
 *
 * Zestaw kluczy musi być identyczny z plikiem polskim — pilnuje tego test
 * kompletności języków, który od kroku 20 obejmuje także pliki modułów.
 */

return [
    'module.file-info.name' => 'File info',
    'module.file-info.description' => 'Shows what the selected file is, as reported by the "file" command.',

    'module.file-info.setting.timeout' => 'Command timeout (s)',
    'module.file-info.setting.arguments' => 'Extra arguments',

    'module.file-info.nothing' => '(no file selected)',
    'module.file-info.empty' => 'No description.',
    'module.file-info.execDisabled' => 'The proc_open() function is disabled — cannot run the "file" command.',
    'module.file-info.failed' => 'The "file" command failed.',
    'module.file-info.failedWith' => 'The "file" command failed: {detail}',
    'module.file-info.timedOut' => 'The "file" command did not answer within {seconds} s — aborted.',

    'module.file-info.command.jump' => 'jump to the given directory',
    'module.file-info.argument.path' => 'path',
    'module.file-info.jump.failed' => 'Cannot open the directory "{path}".',

    'module.file-info.help.enter' => 'The description follows the entry selected in the file list; '
        . 'directories are not described.',
    'module.file-info.help.jump' => 'The file-info.jump command suggests directories from disk — '
        . 'Tab completes the path.',
];
