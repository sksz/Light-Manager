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

    // The set marked in the browser (step 43) — the receiver of the core's new
    // notion of a marked set.
    'module.file-info.marked' => [
        '{count} entry marked · {size} in total',
        '{count} entries marked · {size} in total',
    ],
    'module.file-info.marked.dirs' => [
        '{count} entry marked · {size} in total, excl. {dirs} dir.',
        '{count} entries marked · {size} in total, excl. {dirs} dir.',
    ],

    'module.file-info.setting.timeout' => 'Command timeout (s)',
    'module.file-info.setting.arguments' => 'Extra arguments',
    'module.file-info.setting.timeFormat' => 'Time format',
    'module.file-info.setting.inode' => 'Show inode and links',
    'module.file-info.setting.checksum' => 'sha256 checksum',
    'module.file-info.setting.checksumLimit' => 'Checksum size limit (MiB)',
    'module.file-info.setting.diskUsage' => 'Directory disk usage (du)',
    'module.file-info.setting.backgroundTimeout' => 'Background work limit (s)',
    'module.file-info.setting.textPreview' => 'Preview text file content',
    'module.file-info.setting.lineNumbers' => 'Line numbers in the preview',
    'module.file-info.setting.textWrap' => 'Wrap lines in the preview',

    'module.file-info.section.identity' => 'IDENTITY',
    'module.file-info.section.remote' => 'LOCATION',
    'module.file-info.section.size' => 'SIZE',
    'module.file-info.section.permissions' => 'PERMISSIONS',
    'module.file-info.section.times' => 'TIMES',

    'module.file-info.row.name' => 'Name',
    'module.file-info.row.host' => 'Host',
    'module.file-info.row.remotePath' => 'Remote directory',
    'module.file-info.row.limits' => 'Note',
    'module.file-info.row.kind' => 'Kind',
    'module.file-info.row.content' => 'Content',
    'module.file-info.row.target' => 'Points to',
    'module.file-info.row.targetState' => 'Target',
    'module.file-info.row.entries' => 'Entries',
    'module.file-info.row.size' => 'Size',
    'module.file-info.row.sizeExact' => 'Exactly',
    'module.file-info.row.blocks' => 'Inode blocks',
    'module.file-info.row.diskUsage' => 'On disk',
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
    'module.file-info.remote.limits' => 'the entry lives on a remote host — the description comes from the directory listing',
    'module.file-info.remote.refused' => 'The entry lives on a remote host — this job cannot run on it.',
    'module.file-info.checksum.notAFile' => 'Checksums are computed for regular files only.',
    'module.file-info.checksum.tooLarge' => 'The file exceeds the configured checksum size limit.',
    'module.file-info.checksum.unreadable' => 'The file could not be read.',

    // Directory disk usage — computed by `du` in a background process (step 26).
    // Reasons shared by every background job (time limit, no proc_open) travel
    // through the core `process.*` keys; what stays here is specific to `du`.
    'module.file-info.diskUsage.idle' => '(press d to compute)',
    'module.file-info.diskUsage.working' => 'measuring disk usage',
    'module.file-info.diskUsage.disabled' => 'Disk usage is switched off in the module settings.',
    'module.file-info.diskUsage.notADirectory' => 'Disk usage is measured for directories only — for a file '
        . 'the inode blocks already say it.',
    'module.file-info.diskUsage.failed' => 'The "du" command returned no result.',

    'module.file-info.preview.none' => '(no preview)',
    'module.file-info.preview.unreadable' => 'The image could not be read.',
    'module.file-info.preview.tooLarge' => 'The file exceeds the {limit} MB limit — no preview.',
    'module.file-info.preview.binary' => '(binary file — no content preview)',

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

    'module.file-info.command.show' => 'show the description of the selected entry',

    'module.file-info.help.checksum' => 'compute the checksum',
    'module.file-info.help.diskUsage' => 'measure the directory disk usage',
    'module.file-info.help.scrollPreview' => 'scroll the preview by a panel',
    'module.file-info.help.scrollLine' => 'scroll the preview by one row',
    'module.file-info.help.edges' => 'start and end of the file',
    'module.file-info.help.sectionEdges' => 'first and last section',
    'module.file-info.help.focus' => 'move between the description and the preview',
    'module.file-info.help.wrap' => 'wrap lines in the preview',

    // Short descriptions for the status bar and names of both focused places (step 40).
    'module.file-info.help.checksum.short' => 'checksum',
    'module.file-info.help.diskUsage.short' => 'disk usage',
    'module.file-info.help.scrollPreview.short' => 'by panel',
    'module.file-info.help.scrollLine.short' => 'by row',
    'module.file-info.help.edges.short' => 'file edges',
    'module.file-info.help.sectionEdges.short' => 'edges',
    'module.file-info.help.focus.short' => 'pane',
    'module.file-info.help.wrap.short' => 'wrap',
    'module.file-info.focus.sections' => 'Description',
    'module.file-info.focus.preview' => 'Preview',
    'module.file-info.help.enter' => 'The description covers the entry selected in the file list — '
        . 'directories included.',
    'module.file-info.help.sections' => 'Enter collapses a section; the checksum starts only after you '
        . 'press s, because it reads the whole file, and the directory disk usage after you press d, '
        . 'because it walks the whole tree.',
    'module.file-info.help.preview' => 'The right panel holds the content of a text file: PgUp and PgDn '
        . 'scroll it by a panel, Home returns to the beginning and Alt+Z toggles line wrapping. Only the '
        . 'visible part is read, so a file of any size opens instantly.',
];
