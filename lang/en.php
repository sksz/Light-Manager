<?php

declare(strict_types=1);

/*
 * Message catalogue — English. This is the fallback language: a key missing
 * from another catalogue is looked up here, so this file has to stay complete.
 *
 * Keys are flat and dot-separated; parameters are written in braces ({path},
 * {count}). A message stored as a list carries plural forms — English has two
 * (1 file, 2 files), Polish three. The order is fixed by
 * `Infrastructure\I18n\PluralRule`.
 *
 * See `lang/pl.php` for the annotated original; the key set must match.
 */

return [
    'app.name' => 'Light Manager',

    'format.decimal' => '.',
    'format.percent' => '{value}%',

    'layout.zone.path' => 'PATH',
    'layout.zone.about' => 'APPLICATION',
    'layout.zone.settings.file' => 'CONFIGURATION FILE',
    'layout.zone.settings' => 'SETTINGS',
    'layout.zone.help' => 'HELP',
    'layout.zone.preview' => 'PREVIEW',
    'layout.zone.command' => 'COMMANDS',
    'layout.zone.query' => 'DATA SOURCES',

    'settings.tab.appearance' => 'APPEARANCE',
    'settings.tab.graphics' => 'GRAPHICS',
    'settings.tab.resources' => 'RESOURCES',
    'settings.tab.modules' => 'MODULES',
    'settings.modules.empty' => '(no module is declared)',
    'settings.modules.essential' => 'always enabled',
    'settings.modules.essential.reason' => 'This module cannot be disabled — it is the one the application falls back to when the default module is unavailable.',
    'settings.key.language' => 'Language',
    'settings.key.theme' => 'Theme',
    'settings.key.startupModule' => 'Module opened at startup',
    'settings.key.textAntialias' => 'Text antialiasing',
    'settings.key.strokeAntialias' => 'Stroke antialiasing',
    'settings.key.paletteColors' => 'Sixel palette colours',
    'settings.key.windowColumns' => 'Window columns (windowed mode)',
    'settings.key.windowRows' => 'Window rows (windowed mode)',
    'settings.key.backgroundOutputKib' => 'Memory for background job output',
    'settings.key.backgroundJobs' => 'Concurrent background jobs',
    'settings.language.auto' => 'Automatic',
    'settings.language.pl' => 'Polski',
    'settings.language.en' => 'English',
    'settings.action.restore' => 'Restore default settings',
    'settings.restore.confirm' => 'Restore the default settings? The current ones will be lost for good.',
    'settings.restore.done' => 'Default settings restored.',
    'settings.restore.unchanged' => 'Settings are already at their defaults.',
    'settings.value.yes' => 'yes',
    'settings.value.no' => 'no',
    'settings.value.kib' => '{value} KiB',
    'settings.value.empty' => '(empty)',
    'settings.value.unknown' => 'unknown value: {value}',
    'settings.palette.warning' => 'Below {colors} colours the panel borders vanish from the frame.',

    'help.key.move' => 'move the selection',
    'help.key.help' => 'help',
    'help.key.settings' => 'settings',
    'help.key.back' => 'back to the file list',
    'help.key.quit' => 'quit',
    'help.key.change' => 'change value',
    'help.key.scroll' => 'scroll',
    'help.key.page' => 'page up or down, first and last',
    'help.key.tab' => 'switch tab',
    'help.key.restore' => 'restore default settings',
    'help.key.commands' => 'command window',
    'help.key.edit' => 'edit the value',
    'help.key.commit' => 'commit the value',
    'help.key.cancel' => 'discard the change',
    'help.key.collapse' => 'collapse or expand the section',
    'help.key.fullscreen' => 'fullscreen',
    'help.key.menu' => 'context menu',

    // Short descriptions — for the status bar only (step 40).
    'help.key.move.short' => 'select',
    'help.key.page.short' => 'page',
    'help.key.back.short' => 'back',
    'help.key.change.short' => 'value',
    'help.key.tab.short' => 'tab',
    'help.key.restore.short' => 'restore',
    'help.key.commands.short' => 'commands',
    'help.key.edit.short' => 'edit',
    'help.key.commit.short' => 'commit',
    'help.key.cancel.short' => 'discard',
    'help.key.collapse.short' => 'collapse',
    'help.key.menu.short' => 'menu',

    // Names of the places the focus visits on the core screens (step 40).
    'settings.focus.tabs' => 'Tabs',
    'settings.focus.item' => 'Item',
    'settings.focus.action' => 'Action',
    'settings.focus.edit' => 'Editing',

    // Module tab in the help window — headings of the declared part.
    'help.module.shortcut' => 'Shortcut',
    'help.module.open' => 'open the module window',
    'help.module.keys' => 'Window keys',
    'help.module.settings' => 'Settings',

    // Command window (step 19).
    'command.key.run' => 'run the command',
    'command.key.complete' => 'complete the name, on an empty line: mode',
    'command.key.complete.short' => 'complete or mode',
    'command.key.pick' => 'pick from the list',
    'command.key.close' => 'close the window',
    'command.key.caret' => 'move the caret',
    'command.key.erase' => 'erase a character',
    'command.key.dismiss' => 'close the window',

    // Core event dictionary (step 46) — names shown in the listener's window.
    // They say **what happened**, not what should play: the core publishes
    // moments, not sounds.
    'event.core.message.info' => 'Message: success',
    'event.core.message.warning' => 'Message: warning',
    'event.core.message.error' => 'Message: error',
    'event.core.overlay.opened' => 'Overlay opened',
    'event.core.command.executed' => 'Command executed',

    // Context menu (step 32) — the second door to the command registry.
    'menu.title' => 'ACTIONS',
    'menu.empty' => 'There is nothing to do with the selection.',
    'menu.key.run' => 'run the action',
    'menu.key.pick' => 'pick from the list',
    'menu.key.close' => 'close the menu',

    // Confirmation overlay (step 28).
    'confirm.title' => 'QUESTION',
    'confirm.title.dangerous' => 'WARNING',
    'confirm.yes' => 'Yes',
    'confirm.no' => 'No',
    'confirm.key.move' => 'change the answer',
    'confirm.key.answer' => 'confirm',
    'confirm.key.refuse' => 'refuse',

    // Name prompt and progress overlays (step 41). Both live in the core because
    // steps 42 and 44 will ask for them too — they know nothing about files.
    'prompt.name' => 'name: ',
    'prompt.key.accept' => 'accept the name',
    'prompt.key.accept.short' => 'accept',
    'prompt.key.cancel' => 'abandon typing',
    'prompt.key.cancel.short' => 'abandon',
    'progress.counter' => '{done} of {total}',
    'progress.key.cancel' => 'stop the work',
    'progress.key.cancel.short' => 'stop',

    // Path prompt and choice overlay (step 42). The name prompt got a second
    // field label, because a target directory is not an entry name; the choice
    // overlay is the fifth in the core and knows nothing about what it asks for.
    'prompt.path' => 'directory: ',
    'choice.key.pick' => 'pick from the list',
    'choice.key.pick.short' => 'pick',
    'choice.key.answer' => 'answer',
    'choice.key.answer.short' => 'answer',
    'choice.key.cancel' => 'back out',
    'choice.key.cancel.short' => 'back out',
    'command.history' => 'history',
    'command.dump.requested' => 'Next frame will be written to {file}-prymitywy.txt and {file}.png',
    'command.problem.empty' => 'no command name was typed',
    'command.problem.unknown' => 'unknown command: {name}',
    'command.problem.missing' => 'missing argument: {argument}',
    'command.problem.extra' => 'command {name} takes at most this many arguments: {count}',
    'command.problem.number' => 'argument {argument} must be a number, got: {value}',
    'command.rejected.namespace' => 'name outside its own namespace',
    'command.rejected.duplicate' => 'name already taken',
    'command.rejected' => 'commands skipped: {names}',
    'query.problem.empty' => 'no query name was typed',
    'query.problem.unknown' => 'unknown data source: {name}',
    'query.problem.nested' => 'a query may not ask a query',
    'query.problem.failed' => 'the data source did not answer',
    'query.rejected.namespace' => 'name outside its own namespace',
    'query.rejected.duplicate' => 'name already taken',
    'query.result.empty' => 'nothing to show',
    'query.argument.module' => 'module',
    'query.argument.pane' => 'pane',
    'query.argument.path' => 'path',
    'query.core.settings' => 'core settings with their values',
    'query.core.module-settings' => 'settings of the given module',
    'query.core.modules' => 'modules: accepted, disabled and rejected',
    'query.core.commands' => 'list of actions callable by name',
    'query.core.queries' => 'list of data sources of this run',
    'query.core.events' => 'dictionary of application events',
    'query.core.jobs' => 'background jobs: stage, exit code, output size',
    'query.core.viewport' => 'window size and frame rendering track',
    'query.core.theme' => 'active theme and themes to choose from',
    'query.core.language' => 'active language and languages to choose from',
    'query.core.version' => 'application and PHP version, extensions present',
    'query.core.status' => 'last message with its tone',
    'query.core.context' => 'where the user stands and what is marked',
    'command.core.settings' => 'open the settings',
    'command.core.help' => 'open the help',
    'command.core.quit' => 'quit the application',
    'command.core.dump' => 'write the next frame to a file (primitives and image)',
    'command.core.fullscreen' => 'toggle fullscreen',
    'command.fullscreen.on' => 'Fullscreen on.',
    'command.fullscreen.off' => 'Fullscreen off.',
    'command.core.theme' => 'set the colour theme',
    'command.core.language' => 'set the interface language',
    'command.argument.path' => 'file path (without an extension)',
    'command.argument.theme' => 'theme',
    'command.argument.language' => 'language',
    'help.section.global' => 'Everywhere',
    'help.tab.keys' => 'Controls',
    'help.tab.about' => 'Application',
    'help.about.version' => 'Version',
    'help.about.renderer' => 'Rendering mode',
    'help.about.scale' => 'Display scale',
    'help.settings.location' => 'Settings are stored in:',

    'desktop.comment' => 'A file manager for the terminal and the desktop',
    'desktop.written' => 'Written: {path}',
    'desktop.hint' => 'Done. The icon shows up on the taskbar the next time the application starts '
        . 'with --window; some desktops refresh their application list only after you log in again.',
    'desktop.problem.home' => 'ERROR: the home directory is unknown (the HOME variable is empty).',
    'desktop.problem.executable' => 'ERROR: bin/light-manager was not found next to this script.',
    'desktop.problem.directory' => 'ERROR: the directory {path} could not be created.',
    'desktop.problem.file' => 'ERROR: the file {path} could not be written.',

    // Distribution build (bin/build-phar, `make build`). Assets stay **next to**
    // the archive: the audio engine is a C extension and cannot read phar://.
    'build.step.stage' => 'Collecting the distribution contents…',
    'build.step.deps' => 'Installing dependencies without dev ones…',
    'build.step.phar' => 'Assembling the archive…',
    'build.step.assets' => 'Copying assets next to the archive…',
    'build.step.smoke' => 'Checking that the result loads…',
    'build.done' => 'Done: {path} ({size} MB)',
    'build.assets' => 'Assets: {path}',
    'build.hint.track' => 'In the built application the music track is given as an absolute path '
        . '(settings → “Audio” tab → Track), e.g. {path}/… — a relative one is resolved against '
        . 'the project root, which the distribution does not have.',
    'build.problem.readonly' => 'ERROR: building the archive requires writable PHARs. '
        . 'Run “make build” or “php -d phar.readonly=0 bin/build-phar”.',
    'build.problem.install' => 'ERROR: installing the distribution dependencies failed.',
    'build.problem.smoke' => 'ERROR: the built archive does not load. {details}',

    'config.rejected' => [
        'Settings: {keys} — value out of range, the default was used.',
        'Settings: {keys} — values out of range, the defaults were used.',
    ],
    'config.unreadable' => 'Could not read "{path}" — default settings were used.',
    'config.save.directory' => 'Could not create the settings directory "{path}".',
    'config.save.file' => 'Could not write the settings file "{path}".',
    'config.save.encoding' => 'Could not build the contents of the settings file.',

    // Modules (step 20).
    'module.rejected.id' => 'invalid identifier',
    'module.rejected.duplicate' => 'identifier already taken',
    'module.rejected.character' => 'shortcut outside the allowed letters',
    'module.rejected.taken' => 'shortcut taken by another module',
    'module.rejected' => [
        'Module skipped: {modules}',
        'Modules skipped: {modules}',
    ],
    'module.lang.ignored' => [
        'Module string skipped — key outside the module prefix: {keys}',
        'Module strings skipped — keys outside the module prefix: {keys}',
    ],
    'module.setting.invalid' => 'Value rejected — "{name}" does not accept what was typed.',
    'module.restart' => 'The change takes effect after a restart.',
    'module.startup.unknown' => 'There is no module "{module}" — opened the file browser.',
    'module.startup.disabled' => 'The module "{module}" is disabled — opened the file browser.',
    'module.startup.rejected' => 'The module "{module}" was rejected at startup — opened the file browser.',
    'module.startup.screenless' => 'The module "{module}" provides no window — opened the file browser.',

    'problem.terminal.notInteractive' => 'Standard input is not a terminal — the file manager needs an '
        . 'interactive session (no redirection from a file or a pipe).',
    'problem.terminal.missingPcntl' => 'The PHP extension "pcntl" is unavailable — without signal handling '
        . 'the terminal cannot be guaranteed to be restored after the process is interrupted.',
    'problem.terminal.disabledExec' => 'exec() is disabled — without it "stty" cannot be called and the '
        . 'terminal cannot be switched to raw mode.',
    'problem.terminal.stty' => 'Could not switch the terminal to raw mode: {detail}',
    'problem.missingGlfw' => 'ERROR: the PHP extension "glfw" is not loaded — without it the windowed mode '
        . '(--window) cannot start. Installation: https://phpgl.net',
    'problem.glfw.init' => 'Could not initialise GLFW — check that the session can reach a display server.',
    'problem.glfw.window' => 'Could not open a window with an OpenGL 3.3 core context — check the graphics drivers.',
    'problem.glfw.font' => 'No monospace font was found — the windowed mode has nothing to draw text with.',
    'window.title' => 'Light Manager',
    'problem.missingImagick' => 'ERROR: the PHP extension "imagick" is not loaded — without it the screen '
        . 'frame cannot be built.',
    'problem.unexpected' => 'This operation could not be completed.',

    // Failures of the disk-changing actions (step 41). The sentences live in the
    // core because the write port does — and they speak of the **name** only.
    'problem.fileops.missing' => 'Entry "{name}" is gone.',
    'problem.fileops.taken' => 'The name "{name}" is already taken.',
    'problem.fileops.denied' => 'No permission to change "{name}".',
    'problem.fileops.notEmpty' => 'Directory "{name}" is not empty.',
    'problem.fileops.failed' => 'The action on "{name}" failed: {detail}',

    // Copy and move failures (step 42). The first three close the three roads to
    // an endless loop — and say which one they closed.
    'problem.transfer.noTarget' => 'There is no directory "{name}".',
    'problem.transfer.sameDirectory' => '"{name}" already lies in that directory.',
    'problem.transfer.intoItself' => 'Cannot copy "{name}" into itself.',
    'problem.transfer.targetDirectory' => 'A non-empty directory "{name}" is in the way — remove it first.',
    'problem.transfer.unreadable' => 'Cannot read "{name}".',

    // Background work (step 26). The reasons belong to the core because they
    // speak about the process, not about what it was started for.
    'process.unavailable' => 'Starting processes is disabled in this environment.',
    'process.failed' => 'The process could not be started.',
    'process.timedOut' => 'The background work exceeded its {seconds}s limit and was stopped.',
    'process.tooMany' => 'Already running {limit} background jobs — this one was not started.',

    // The `bin/render-bench` measurement tool (step 16). Tool strings go through
    // the catalog like the rest of the interface — but the content of the
    // measured frames does not, because its length in characters is part of the
    // measurement (see `ScenarioFactory`).
    'bench.report.title' => 'Rendering pipeline benchmark',
    'bench.report.title.loop' => 'Loop tick benchmark (no renderer, no transfer)',
    'bench.report.config' => 'Configuration: {config}',
    'bench.report.environment' => 'Environment: PHP {php} · {imagick} · font {font}',
    'bench.report.load' => 'Machine load: {load} per core.',
    'bench.report.loadNoisy' => 'Machine load: {load} per core — THE MACHINE WAS BUSY. '
        . 'The numbers may describe a neighbour, not the code.',
    'bench.report.loadUnknown' => 'Machine load: unknown (the system does not report it).',
    'bench.report.iterations' => 'Runs: {iterations} measured, {warmup} for warm-up '
        . '(median shown, min–max spread next to it).',
    'bench.report.unstableNote' => 'Rows marked "!" had a spread wider than {ratio}× — '
        . 'those numbers are unreliable and will not be saved as a baseline.',
    'bench.report.coldNote' => 'The "Cold" column is the FIRST warm-up frame — a single sample, '
        . 'not a median.' . "\n"
        . '  Frame caches (rows, base plane, thumbnail) are empty in it; the process, the font and '
        . 'the' . "\n"
        . '  singletons are already warm. That is what application start-up and every window resize '
        . 'pay.' . "\n"
        . '  It is saved to the baseline but NEVER raises a regression alarm — one sample spreads '
        . 'wider than the threshold.',

    'bench.column.scenario' => 'Scenario',
    'bench.column.draw' => 'Drawing',
    'bench.column.quantize' => 'Quantization',
    'bench.column.encode' => 'Encoding',
    'bench.column.swap' => 'Buffers',
    'bench.column.total' => 'Total',
    'bench.column.cold' => 'Cold',
    'bench.column.input' => 'Input',
    'bench.column.state' => 'State',
    'bench.column.primitives' => 'Primitives',
    'bench.column.compose' => 'Composition',
    'bench.column.spread' => 'Spread',
    'bench.column.blob' => 'Blob',
    'bench.column.memory' => 'Memory',

    'bench.scenario.empty' => 'empty canvas',
    'bench.scenario.text' => 'text only',
    'bench.scenario.chrome' => 'panels only',
    'bench.scenario.chrome-text' => 'panels with text',
    'bench.scenario.selection' => 'selection bars',
    'bench.scenario.scrollbar' => 'scrollbar',
    'bench.scenario.thumbnail' => 'frame with a thumbnail',
    'bench.scenario.popup' => 'frame with a popup',
    'bench.scenario.command' => 'command window',
    'bench.scenario.sections' => 'collapsible sections',
    'bench.scenario.progress' => 'progress bars',
    'bench.scenario.split' => 'split frame',
    'bench.scenario.background' => 'frame with background work',
    'bench.scenario.background-many' => 'frame with a full set of background jobs',
    'bench.scenario.columns' => 'list with columns',
    'bench.scenario.text-view' => 'text preview',
    'bench.scenario.highlight' => 'list with highlighting',
    'bench.scenario.settings' => 'settings screen',
    'bench.scenario.tree' => 'directory tree',
    'bench.scenario.marked' => 'list with marks',

    'bench.transfer.title' => 'Frame transfer to the terminal',
    'bench.transfer.blob' => '  frame size:         {kilobytes} kB',
    'bench.transfer.write' => '  write time:         {milliseconds} ms (min {minimum}, max {maximum})',
    'bench.transfer.chunks' => '  fwrite() calls:     {chunks}',
    'bench.transfer.throughput' => '  throughput:         {throughput} kB/s',
    'bench.transfer.roundTrip' => '  DA1 answer after:   {milliseconds} ms — this value is APPROXIMATE: '
        . 'the terminal may answer before it finishes painting the image.',
    'bench.transfer.roundTripMissing' => '  DA1 answer:         none within the timeout — not measured.',
    'bench.transfer.skippedNoTerminal' => 'Transfer phase NOT MEASURED: input or output is not a terminal. '
        . 'Run it under a real terminal, e.g. ./bin/run-render-bench.sh --transfer',
    'bench.transfer.skippedNoSixel' => 'Transfer phase NOT MEASURED: this terminal does not report Sixel '
        . 'support, so the frame would have nowhere to appear.',

    'bench.compare.title' => 'Comparison against the baseline {file}',
    'bench.compare.baseline' => 'Baseline',
    'bench.compare.current' => 'Now',
    'bench.compare.change' => 'Change',
    'bench.compare.clean' => 'No regressions above the threshold.',
    'bench.compare.regressions' => [
        'Regression in {count} scenario (marked ▲).',
        'Regressions in {count} scenarios (marked ▲).',
    ],
    'bench.compare.load' => 'Machine load: baseline {baseline} / now {current} (per core). '
        . 'A different load means a different environment, not a code change.',
    'bench.compare.incomparable' => 'The baseline was recorded with a different configuration, so the '
        . 'comparison would mean nothing.' . "\n" . '  baseline: {baseline}' . "\n" . '  now:      {current}',

    'bench.image.title' => 'Snapshot comparison against baselines (threshold {threshold} ‰ of pixels)',
    'bench.image.column.pixels' => 'Differing pixels',
    'bench.image.column.share' => 'Share',
    'bench.image.column.verdict' => 'Verdict',
    'bench.image.verdict.match' => 'matches',
    'bench.image.verdict.differs' => 'DIFFERS — difference image below',
    'bench.image.verdict.missing' => 'no baseline — save one: --png-save',
    'bench.image.verdict.resized' => 'canvas size differs from the baseline',
    'bench.image.verdict.incomparable' => 'baseline from another configuration — not comparable',
    'bench.image.saved' => 'Baseline snapshot saved: {file}',
    'bench.golden.saved' => 'Golden frame saved: {file}',

    'bench.save.done' => 'Baseline saved: {file}',
    'bench.save.noisyLoad' => 'WARNING: machine load was {load} per core (threshold {limit}). '
        . 'The baseline will be saved,' . "\n"
        . '  but remember it was recorded on a busy host — in step 22 such a pair of baselines '
        . 'got 8-18% "faster"' . "\n" . '  without a single change in the code.',
    'bench.save.refusedUnstable' => 'Baseline NOT SAVED: some measurements were unstable. '
        . 'Close other programs and run it again.',
    'bench.snapshot.saved' => 'Canvas snapshot ({scenario}, before quantization) saved: {file}',
    'bench.progress.running' => 'Measuring {scenarios} scenarios, {iterations} runs each…',

    'bench.help.usage' => 'Usage: ./bin/render-bench [options]' . "\n\n"
        . '  Measures the frame rendering pipeline: drawing, quantization and Sixel encoding,' . "\n"
        . '  separately for each scenario. With no arguments it measures every scenario in the' . "\n"
        . '  configuration that reproduces the reference point from step 13.',
    'bench.help.axes' => 'Configuration axes:' . "\n"
        . '  --size=1000x600      canvas size in pixels (or --width= and --height=)' . "\n"
        . '  --grid=166x46        character grid (or --columns= and --rows=)' . "\n"
        . '  --palette=64         Sixel palette colours: 16, 32, 64, 128, 256' . "\n"
        . '  --text-aa[=0|1]      text antialiasing' . "\n"
        . '  --stroke-aa[=0|1]    stroke antialiasing' . "\n"
        . '  --theme=grafit       theme: grafit, nordyk, papier, indygo' . "\n"
        . '  --font=NAME          font from the preference list (default: automatic choice)' . "\n"
        . '  --iterations=15      number of measured runs' . "\n"
        . '  --warmup=3           number of warm-up runs',
    'bench.help.modes' => 'Modes and output:' . "\n"
        . '  --window             measure the windowed path (OpenGL, hidden window) instead of Sixel' . "\n"
        . '  --text               measure the text path (ANSI fallback) instead of Sixel' . "\n"
        . '  --loop               measure the loop tick (input, state, frame composition), no renderer' . "\n"
        . '  --scenarios=a,b      measure only the selected scenarios' . "\n"
        . '  --transfer           also measure the frame transfer (needs a real terminal)' . "\n"
        . '  --save[=name]        save a baseline into docs/pomiary/' . "\n"
        . '  --compare[=file]     compare against a baseline (no value: the newest one)' . "\n"
        . '  --threshold=10       regression threshold in per cent' . "\n"
        . '  --png=FILE           write the canvas to a PNG instead of measuring' . "\n"
        . '  --scenario=NAME      scenario for the PNG snapshot' . "\n"
        . '  --png-save           save baseline snapshots into docs/pomiary/wzorce-png/' . "\n"
        . '  --png-compare        compare snapshots against baselines (exit code 1 on a mismatch)' . "\n"
        . '  --png-threshold=0.5  difference threshold in per mille of pixels (0 ‰; 5 ‰ in the window)' . "\n"
        . '  --golden-save        write golden frames (primitives) into tests/Golden/ — READ the diff first',
    'bench.help.scenarios' => 'Scenarios:',

    'bench.problem.emptySampleSet' => 'The benchmark produced no samples at all.',
    'bench.problem.unknownScenario' => 'Unknown scenario "{detail}". List them: ./bin/render-bench --help',
    'bench.problem.unknownTheme' => 'Unknown theme "{detail}". Available: grafit, nordyk, papier, indygo.',
    'bench.problem.invalidArgument' => 'Invalid argument "{detail}". Help: ./bin/render-bench --help',
    'bench.problem.baselineUnreadable' => 'The file "{detail}" is not a saved benchmark baseline.',
    'bench.problem.baselineMissing' => 'No baseline found in "{detail}". Save the first one with --save',
    'bench.problem.writeFailed' => 'Could not write "{detail}".',
    'bench.problem.terminalUnavailable' => 'Measuring the transfer needs a terminal on both input and output.',
    'bench.problem.glfwUnavailable' => 'Measuring the windowed path needs the "glfw" extension. Installation: https://phpgl.net',
];
