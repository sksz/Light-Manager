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

    'settings.hints' => '↑↓ move · ←→ change · Esc back',
    'settings.tab.appearance' => 'APPEARANCE',
    'settings.tab.graphics' => 'GRAPHICS',
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
    'settings.language.auto' => 'Automatic',
    'settings.language.pl' => 'Polski',
    'settings.language.en' => 'English',
    'settings.action.restore' => 'Restore default settings',
    'settings.restore.confirm' => 'Restore the default settings? The current ones will be lost for good.',
    'settings.restore.done' => 'Default settings restored.',
    'settings.restore.unchanged' => 'Settings are already at their defaults.',
    'settings.value.yes' => 'yes',
    'settings.value.no' => 'no',
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
    'help.key.tab' => 'switch tab',
    'help.key.restore' => 'restore default settings',
    'help.key.commands' => 'command window',
    'help.key.edit' => 'edit the value',
    'help.key.commit' => 'commit the value',
    'help.key.cancel' => 'discard the change',
    'help.key.collapse' => 'collapse or expand the section',

    // Module tab in the help window — headings of the declared part.
    'help.module.shortcut' => 'Shortcut',
    'help.module.open' => 'open the module window',
    'help.module.keys' => 'Window keys',
    'help.module.settings' => 'Settings',

    // Command window (step 19).
    'command.key.run' => 'run the command',
    'command.key.complete' => 'complete the name',
    'command.key.pick' => 'pick from the list',
    'command.key.close' => 'close the command window',
    'command.key.caret' => 'move the caret',
    'command.key.erase' => 'erase a character',
    'command.key.dismiss' => 'close the window',

    // Confirmation overlay (step 28).
    'confirm.title' => 'QUESTION',
    'confirm.title.dangerous' => 'WARNING',
    'confirm.yes' => 'Yes',
    'confirm.no' => 'No',
    'confirm.key.move' => 'change the answer',
    'confirm.key.answer' => 'confirm',
    'confirm.key.refuse' => 'refuse',
    'command.history' => 'history',
    'command.problem.empty' => 'no command name was typed',
    'command.problem.unknown' => 'unknown command: {name}',
    'command.problem.missing' => 'missing argument: {argument}',
    'command.problem.extra' => 'command {name} takes at most this many arguments: {count}',
    'command.problem.number' => 'argument {argument} must be a number, got: {value}',
    'command.rejected.namespace' => 'name outside its own namespace',
    'command.rejected.duplicate' => 'name already taken',
    'command.rejected' => 'commands skipped: {names}',
    'command.core.settings' => 'open the settings',
    'command.core.help' => 'open the help',
    'command.core.quit' => 'quit the application',
    'command.core.theme' => 'set the colour theme',
    'command.core.language' => 'set the interface language',
    'command.argument.theme' => 'theme',
    'command.argument.language' => 'language',
    'help.section.global' => 'Everywhere',
    'help.tab.keys' => 'Controls',
    'help.tab.about' => 'Application',
    'help.about.version' => 'Version',
    'help.about.renderer' => 'Rendering mode',
    'help.settings.location' => 'Settings are stored in:',

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

    // Background work (step 26). The reasons belong to the core because they
    // speak about the process, not about what it was started for.
    'process.unavailable' => 'Starting processes is disabled in this environment.',
    'process.failed' => 'The process could not be started.',
    'process.timedOut' => 'The background work exceeded its {seconds}s limit and was stopped.',

    // The `bin/render-bench` measurement tool (step 16). Tool strings go through
    // the catalog like the rest of the interface — but the content of the
    // measured frames does not, because its length in characters is part of the
    // measurement (see `ScenarioFactory`).
    'bench.report.title' => 'Rendering pipeline benchmark',
    'bench.report.config' => 'Configuration: {config}',
    'bench.report.environment' => 'Environment: PHP {php} · {imagick} · font {font}',
    'bench.report.iterations' => 'Runs: {iterations} measured, {warmup} for warm-up '
        . '(median shown, min–max spread next to it).',
    'bench.report.unstableNote' => 'Rows marked "!" had a spread wider than {ratio}× — '
        . 'those numbers are unreliable and will not be saved as a baseline.',

    'bench.column.scenario' => 'Scenario',
    'bench.column.draw' => 'Drawing',
    'bench.column.quantize' => 'Quantization',
    'bench.column.encode' => 'Encoding',
    'bench.column.swap' => 'Buffers',
    'bench.column.total' => 'Total',
    'bench.column.spread' => 'Spread',
    'bench.column.blob' => 'Blob',

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
    'bench.scenario.columns' => 'list with columns',

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
    'bench.compare.incomparable' => 'The baseline was recorded with a different configuration, so the '
        . 'comparison would mean nothing.' . "\n" . '  baseline: {baseline}' . "\n" . '  now:      {current}',

    'bench.save.done' => 'Baseline saved: {file}',
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
        . '  --scenarios=a,b      measure only the selected scenarios' . "\n"
        . '  --transfer           also measure the frame transfer (needs a real terminal)' . "\n"
        . '  --save[=name]        save a baseline into docs/pomiary/' . "\n"
        . '  --compare[=file]     compare against a baseline (no value: the newest one)' . "\n"
        . '  --threshold=10       regression threshold in per cent' . "\n"
        . '  --png=FILE           write the canvas to a PNG instead of measuring' . "\n"
        . '  --scenario=NAME      scenario for the PNG snapshot',
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
