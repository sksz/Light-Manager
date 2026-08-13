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

    // Module command.
    'module.browser.command.jump' => 'jump to the given directory',
    'module.browser.argument.path' => 'path',
    'module.browser.jump.failed' => 'Cannot open the directory "{path}".',

    // Sentences built from the module's own exceptions (`DescribesProblem`).
    'module.browser.problem.unreadable' => 'Cannot read the directory "{path}".',
    'module.browser.problem.invalidPath' => '"{path}" is not an absolute directory path.',
    'module.browser.problem.fallback' => 'Cannot read the directory "{requested}" — opened "{opened}" instead.',

    // The module's own part of the help tab.
    'module.browser.help.default' => 'The browser is the default module: Esc returns to it from any other '
        . 'screen, and it cannot be disabled.',
    'module.browser.help.jump' => 'The browser.jump command suggests directories from disk — Tab completes the path.',
];
