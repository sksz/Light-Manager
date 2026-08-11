<?php

declare(strict_types=1);

/*
 * Message catalogue of the "File info" module — English.
 *
 * See `pl.php` for the annotated original; the key set must match. Every key has
 * to start with `module.file-info.` — the catalogue accepts nothing else.
 */

return [
    'module.file-info.name' => 'File info',
    'module.file-info.description' => 'The full picture of an entry: what it is, how much it takes, '
        . 'who owns it and when it was last touched.',

    'module.file-info.setting.timeout' => 'Command timeout (s)',
    'module.file-info.setting.arguments' => 'Extra arguments',
    'module.file-info.setting.timeFormat' => 'Time format',
    'module.file-info.setting.inode' => 'Show inode and links',
    'module.file-info.setting.checksum' => 'sha256 checksum',
    'module.file-info.setting.checksumLimit' => 'Checksum size limit (MiB)',

    'module.file-info.section.identity' => 'IDENTITY',
    'module.file-info.section.size' => 'SIZE',
    'module.file-info.section.permissions' => 'PERMISSIONS',
    'module.file-info.section.times' => 'TIMES',

    'module.file-info.row.name' => 'Name',
    'module.file-info.row.kind' => 'Kind',
    'module.file-info.row.content' => 'Content',
    'module.file-info.row.target' => 'Points to',
    'module.file-info.row.targetState' => 'Target',
    'module.file-info.row.entries' => 'Entries',
    'module.file-info.row.size' => 'Size',
    'module.file-info.row.sizeExact' => 'Exactly',
    'module.file-info.row.blocks' => 'Inode blocks',
    'module.file-info.row.checksum' => 'sha256',
    'module.file-info.row.mode' => 'Mode',
    'module.file-info.row.owner' => 'Owner',
    'module.file-info.row.group' => 'Group',
    'module.file-info.row.inode' => 'Inode',
    'module.file-info.row.links' => 'Hard links',
    'module.file-info.row.modified' => 'Contents changed',
    'module.file-info.row.changed' => 'Inode changed',
    'module.file-info.row.accessed' => 'Accessed',

    'module.file-info.kind.file' => 'regular file',
    'module.file-info.kind.directory' => 'directory',
    'module.file-info.kind.symlink' => 'symbolic link',
    'module.file-info.kind.block' => 'block device',
    'module.file-info.kind.character' => 'character device',
    'module.file-info.kind.fifo' => 'named pipe',
    'module.file-info.kind.socket' => 'socket',
    'module.file-info.kind.unknown' => 'unknown',

    'module.file-info.target.exists' => 'exists',
    'module.file-info.target.missing' => 'missing',

    'module.file-info.principal' => '{name} ({id})',
    'module.file-info.principal.numeric' => '{id} (no posix extension)',

    'module.file-info.entries' => ['{count} entry', '{count} entries'],
    'module.file-info.bytes' => ['{count} byte', '{count} bytes'],

    'module.file-info.checksum.idle' => '(press s to compute)',
    'module.file-info.checksum.working' => 'computing sha256',
    'module.file-info.checksum.disabled' => 'The checksum is switched off in the module settings.',
    'module.file-info.checksum.notAFile' => 'Checksums are computed for regular files only.',
    'module.file-info.checksum.tooLarge' => 'The file exceeds the configured checksum size limit.',
    'module.file-info.checksum.unreadable' => 'The file could not be read.',

    'module.file-info.preview.none' => '(no preview)',
    'module.file-info.preview.unreadable' => 'The image could not be read.',
    'module.file-info.preview.tooLarge' => 'The file exceeds the {limit} MB limit — no preview.',

    'module.file-info.ago.now' => 'just now',
    'module.file-info.ago.minutes' => ['{count} minute ago', '{count} minutes ago'],
    'module.file-info.ago.hours' => ['{count} hour ago', '{count} hours ago'],
    'module.file-info.ago.days' => ['{count} day ago', '{count} days ago'],
    'module.file-info.ago.months' => ['{count} month ago', '{count} months ago'],
    'module.file-info.ago.years' => ['{count} year ago', '{count} years ago'],

    'module.file-info.nothing' => '(no entry selected)',
    'module.file-info.empty' => 'No description.',
    'module.file-info.execDisabled' => 'proc_open() is disabled — the "file" command cannot be run.',
    'module.file-info.failed' => 'The "file" command failed.',
    'module.file-info.failedWith' => 'The "file" command failed: {detail}',
    'module.file-info.timedOut' => 'The "file" command did not answer within {seconds} s — aborted.',

    'module.file-info.command.jump' => 'go to the given directory',
    'module.file-info.argument.path' => 'path',
    'module.file-info.jump.failed' => 'Could not open the directory "{path}".',

    'module.file-info.help.checksum' => 'compute the checksum',
    'module.file-info.help.enter' => 'The description covers the entry selected in the file list — '
        . 'directories included.',
    'module.file-info.help.sections' => 'Enter collapses a section; the checksum starts only after you '
        . 'press s, because it reads the whole file.',
];
