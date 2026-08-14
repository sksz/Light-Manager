<?php

declare(strict_types=1);

/*
 * Strings of the "Audio" module — English.
 *
 * **Every key must start with `module.audio.`** — the catalogue takes those and
 * skips the rest.
 */

return [
    'module.audio.name' => 'Audio',
    'module.audio.description' => 'Music playback alongside file work — driven by commands, with no window of its own.',

    // Module commands.
    'module.audio.command.music' => 'start the music or stop it',
    'module.audio.command.volume' => 'set the music volume',
    'module.audio.argument.level' => 'volume in percent',

    // Settings tab positions.
    'module.audio.setting.track' => 'Track',
    'module.audio.setting.volume' => 'Volume (%)',
    'module.audio.setting.loop' => 'Play in a loop',

    // Status bar sentences.
    'module.audio.playing' => 'Playing: {track}',
    'module.audio.stopped' => 'Music stopped.',
    'module.audio.volume.set' => 'Volume: {level}%.',
    'module.audio.volume.rejected' => 'Volume takes these values: {levels}.',

    // Reasons there is nothing to hear.
    'module.audio.problem.unavailable' => 'The glfw extension is not loaded — there is nothing to play the music with.',
    'module.audio.problem.load' => 'Cannot play the file "{path}" — check the path and the format (WAV, MP3, FLAC).',

    // The module's own part of the help tab — what cannot be read off the declarations.
    'module.audio.help.start' => 'The music does not start by itself: the audio.music command starts it, and '
        . 'calling it again stops the playback and remembers the spot.',
    'module.audio.help.track' => 'The "Track" position points at the file — a relative path counts from the '
        . 'application directory, and the formats are WAV, MP3 and FLAC (a MIDI file will not do).',
    'module.audio.help.volume' => 'The audio.volume command changes the volume at once; the tab position '
        . 'applies from the next start of the track.',
];
