<?php

declare(strict_types=1);

/*
 * Strings of the "File browser" module — English.
 *
 * Every key must start with `module.browser.`; the catalogue accepts nothing
 * else from a module and reports what it skipped.
 */

return [
    'module.browser.name' => 'File browser',
    'module.browser.description' => 'Directory navigation with image thumbnails — the default module.',

    // Middle zone label; up to step 20 the core key `layout.zone.files`.
    'module.browser.zone.files' => 'FILES',

    // Screen content.
    'module.browser.empty' => '(this directory is empty)',
    'module.browser.hidden' => '• hidden',

    // List filter (step 30). The path-line marker is the only trace of the
    // narrowing once the field is closed, so it carries the typed fragment.
    'module.browser.filter.zone' => 'FILTER',
    'module.browser.filter.prompt' => 'find: ',
    'module.browser.filter.marker' => '• filter: {fragment}',
    'module.browser.filter.none' => '(nothing matches the filter)',
    'module.browser.filter.key.accept' => 'keep the list narrowed',
    'module.browser.filter.key.cancel' => 'drop the filter and go back',

    // Settings tab entries.
    'module.browser.setting.showHidden' => 'Show hidden entries',
    'module.browser.setting.split' => 'Split into two panes',
    'module.browser.setting.splitVertical' => 'Panes side by side',
    'module.browser.setting.details' => 'Detail columns (date, permissions)',
    'module.browser.setting.columnHeader' => 'Column names above the list',
    // The settings screen shows choice values raw, so "no limit" travels as the
    // infinity sign — readable without a translation.
    'module.browser.setting.treeDepth' => 'Tree levels (Ctrl+T)',
    'module.browser.setting.askBeforeDelete' => 'Ask before deleting',

    // Column headings (step 27), shown only when the toggle above is on.
    'module.browser.column.name' => 'Name',
    'module.browser.column.size' => 'Size',
    'module.browser.column.modified' => 'Modified',
    'module.browser.column.permissions' => 'Perms',

    // Thumbnail strip.
    'module.browser.preview.unreadable' => 'The image could not be read.',
    'module.browser.preview.tooLarge' => 'File exceeds the {limit} MB limit — no preview.',
    'module.browser.preview.tooManyPixels' => '{dimensions} — image exceeds the {limit} Mpx limit.',

    // Screen keys — the source of the help listing and of the hints.
    'module.browser.help.open' => 'enter the directory',
    'module.browser.help.up' => 'parent directory',
    'module.browser.help.hidden' => 'show or hide hidden entries',
    'module.browser.help.focus' => 'move to the other pane',
    'module.browser.help.filter' => 'narrow the list by a name fragment',
    'module.browser.help.filter.clear' => 'drop the filter',

    // Directory tree (step 31). The arrow descriptions show up only in a pane
    // displaying the tree — in the list the same keys mean something else.
    'module.browser.help.rename' => 'rename the entry',
    'module.browser.help.copy' => 'copy the entry',
    'module.browser.help.move' => 'move the entry',
    'module.browser.help.mkdir' => 'new directory',
    'module.browser.help.delete' => 'delete the entry',
    'module.browser.help.tree' => 'pane as a tree or a list',
    'module.browser.help.tree.expand' => 'expand the branch',
    'module.browser.help.tree.collapse' => 'collapse the branch or go one level up',

    // Short descriptions for the status bar and names of the focused places (step 40).
    'module.browser.help.open.short' => 'enter',
    'module.browser.help.up.short' => 'up',
    'module.browser.help.hidden.short' => 'hidden',
    'module.browser.help.focus.short' => 'pane',
    'module.browser.help.filter.short' => 'filter',
    'module.browser.help.filter.clear.short' => 'no filter',
    'module.browser.help.rename.short' => 'rename',
    'module.browser.help.copy.short' => 'copy',
    'module.browser.help.move.short' => 'move',
    'module.browser.help.mkdir.short' => 'mkdir',
    'module.browser.help.delete.short' => 'delete',
    'module.browser.help.tree.short' => 'tree',
    'module.browser.help.tree.expand.short' => 'expand',
    'module.browser.help.tree.collapse.short' => 'collapse',
    'module.browser.focus.list' => 'List',
    'module.browser.focus.tree' => 'Tree',
    'module.browser.focus.left' => 'Left pane',
    'module.browser.focus.right' => 'Right pane',
    'module.browser.focus.top' => 'Top pane',
    'module.browser.focus.bottom' => 'Bottom pane',
    'module.browser.tree.depth' => [
        'The tree shows at most {count} level — change it in the module settings.',
        'The tree shows at most {count} levels — change it in the module settings.',
    ],

    // Module commands. The last three arrived in step 32: they name actions
    // the browser had under a key alone.
    'module.browser.command.jump' => 'jump to the given directory',
    'module.browser.command.open' => 'enter the selected directory',
    'module.browser.command.hidden' => 'show or hide hidden entries',
    'module.browser.command.tree' => 'pane as a tree or as a list',
    // The first two commands with an argument in the project (step 41): the name
    // comes on the line, because a command cannot open an overlay (D75, no. 5).
    'module.browser.command.rename' => 'rename the selected entry',
    'module.browser.command.mkdir' => 'create a directory in the pane directory',
    'module.browser.command.delete' => 'delete the selected entry',
    // Two commands of step 42. The path is optional: without it the window opens
    // with the other pane's directory, exactly as the key does.
    'module.browser.command.copy' => 'copy the selected entry into the given directory',
    'module.browser.command.move' => 'move the selected entry into the given directory',
    'module.browser.argument.path' => 'path',
    'module.browser.argument.name' => 'name',
    'module.browser.jump.failed' => 'Cannot open the directory "{path}".',
    'module.browser.open.failed' => 'Cannot open the selected directory.',
    'module.browser.open.notDirectory' => 'The selected entry is not a directory.',
    'module.browser.hidden.failed' => 'Cannot read the directory again — the setting stays unchanged.',

    // Disk-changing actions (step 41): window titles and sentences about the result.
    'module.browser.rename.title' => 'New name for "{name}"',
    'module.browser.rename.done' => 'Renamed to "{name}".',
    'module.browser.mkdir.title' => 'Name of the new directory',
    'module.browser.mkdir.done' => 'Directory "{name}" created.',
    'module.browser.delete.confirm.file' => 'Delete "{name}" for good?',
    'module.browser.delete.confirm.tree' => 'Delete "{name}" with everything inside? To be deleted: {count}.',
    'module.browser.delete.counting' => 'Counting the contents of "{name}"',
    'module.browser.delete.deleting' => 'Deleting "{name}"',
    'module.browser.delete.doneOne' => 'Deleted "{name}".',
    'module.browser.delete.needsOverlay' => 'Deleting needs a confirmation — use F8 or the F9 menu.',
    'module.browser.delete.done' => [
        'Deleted {count} entry.',
        'Deleted {count} entries.',
    ],
    'module.browser.delete.stopped' => [
        'Stopped — deleted {count} entry out of {total}.',
        'Stopped — deleted {count} entries out of {total}.',
    ],
    'module.browser.delete.abandoned' => 'Counting stopped — the disk was left untouched.',

    // Copying and moving (step 42). Window titles end with a colon, because a
    // path field stands below them — not a yes-or-no question.
    'module.browser.copy.title' => 'Copy "{name}" to:',
    'module.browser.move.title' => 'Move "{name}" to:',
    'module.browser.copy.progress' => 'Copying "{name}"',
    'module.browser.move.progress' => 'Moving "{name}"',
    'module.browser.transfer.counting' => 'Counting the contents of "{name}"',
    'module.browser.transfer.abandoned' => 'Counting stopped — the disk was left untouched.',
    'module.browser.transfer.counter' => '{done} of {total} · {entry}/{entries}',
    'module.browser.transfer.counter.size' => '{done} of {total}',
    'module.browser.transfer.needsOverlay' => 'Copying needs a window — use F5 or F6, or the F9 menu.',

    // A name collision: six answers, because "all" is a different answer and not
    // a switch next to the other four (D79, no. 4).
    'module.browser.transfer.collision' => '"{name}" is already there',
    'module.browser.transfer.overwrite' => 'Overwrite',
    'module.browser.transfer.overwriteAll' => 'Overwrite all',
    'module.browser.transfer.skip' => 'Skip',
    'module.browser.transfer.skipAll' => 'Skip all',
    'module.browser.transfer.rename' => 'Save under another name',
    'module.browser.transfer.abort' => 'Stop',
    'module.browser.transfer.newName' => 'New name for "{name}"',
    'module.browser.copy.done' => [
        'Copied {count} entry.',
        'Copied {count} entries.',
    ],
    'module.browser.move.done' => [
        'Moved {count} entry.',
        'Moved {count} entries.',
    ],
    'module.browser.copy.stopped' => [
        'Stopped — copied {count} of {total} entry.',
        'Stopped — copied {count} of {total} entries.',
    ],
    'module.browser.move.stopped' => [
        'Stopped — moved {count} of {total} entry.',
        'Stopped — moved {count} of {total} entries.',
    ],

    // A name typed by the user — every reason for refusing it has its own sentence.
    'module.browser.name.empty' => 'The name cannot be empty.',
    'module.browser.name.reserved' => 'The name "{name}" belongs to the file system.',
    'module.browser.name.separator' => 'The name cannot contain a slash — it is a name, not a path.',
    'module.browser.name.tooLong' => 'The name is longer than {limit} bytes.',

    // Sentences built from the module's own exceptions (`DescribesProblem`).
    'module.browser.problem.unreadable' => 'Cannot read the directory "{path}".',
    'module.browser.problem.invalidPath' => '"{path}" is not an absolute directory path.',
    'module.browser.problem.fallback' => 'Cannot read the directory "{requested}" — opened "{opened}" instead.',
    'module.browser.problem.noSelection' => 'There is no selected entry.',
    'module.browser.problem.noEntry' => 'This directory has no entry named "{name}".',

    // The module's own part of the help tab.
    'module.browser.help.default' => 'The browser is the default module: Esc returns to it from any other '
        . 'screen, and it cannot be disabled.',
    'module.browser.help.jump' => 'The browser.jump command suggests directories from disk — Tab completes the path.',
];
