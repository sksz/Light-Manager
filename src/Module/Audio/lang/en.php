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
    'module.audio.description' => 'Music playback alongside file work — a playlist that keeps going on its own.',

    // Module commands.
    'module.audio.command.music' => 'start the music or stop it',
    'module.audio.command.volume' => 'set the music volume',
    'module.audio.command.add' => 'add a track to the playlist',
    'module.audio.argument.level' => 'volume in percent',
    'module.audio.argument.path' => 'path to an audio file',

    // Settings tab positions.
    'module.audio.setting.volume' => 'Volume (%)',
    'module.audio.setting.mode' => 'After a track',
    'module.audio.setting.autostart' => 'Play from startup',

    // The module window's top zone and the playback modes.
    'module.audio.zone.now' => 'Playback',
    'module.audio.nowPlaying' => 'Playing: {track}',
    'module.audio.paused' => 'Paused: {track}',
    'module.audio.nothing' => 'Silence',
    'module.audio.mode.list' => 'loop the list',
    'module.audio.mode.once' => 'stop',
    'module.audio.mode.repeat' => 'repeat the track',

    // Focus places and window keys.
    'module.audio.focus.playlist' => 'Playlist',
    'module.audio.focus.path' => 'Track path',
    'module.audio.key.play' => 'play the selected track',
    'module.audio.key.play.short' => 'play',
    'module.audio.key.pause' => 'stop or resume',
    'module.audio.key.pause.short' => 'pause',
    'module.audio.key.take' => 'add the entry selected in the browser',
    'module.audio.key.take.short' => 'take entry',
    'module.audio.key.type' => 'add a track by typing its path',
    'module.audio.key.type.short' => 'type',
    'module.audio.key.remove' => 'remove the position from the playlist',
    'module.audio.key.remove.short' => 'remove',
    'module.audio.key.move' => 'move the position within the list',
    'module.audio.key.move.short' => 'move',
    'module.audio.key.confirm' => 'add the typed path',
    'module.audio.key.cancel' => 'close the field without adding',
    'module.audio.key.cancel.short' => 'close the field',

    // Status bar and module window sentences.
    'module.audio.playing' => 'Playing: {track}',
    'module.audio.stopped' => 'Music stopped.',
    'module.audio.volume.set' => 'Volume: {level}%.',
    'module.audio.volume.rejected' => 'Volume takes these values: {levels}.',
    'module.audio.playlist.empty' => 'The playlist is empty — add a track with F5 or F7.',
    'module.audio.playlist.nothingPlayable' => 'None of the playlist files can be played right now.',
    'module.audio.playlist.added' => 'Added to the playlist: {track}.',
    'module.audio.playlist.missing' => 'file missing',
    'module.audio.playlist.noSelection' => 'Nothing is selected in the browser.',
    'module.audio.playlist.path' => 'Path',
    'module.audio.playlist.unreadable' => 'Cannot read the playlist from "{path}" — starting from an empty one.',

    // Reasons there is nothing to hear.
    'module.audio.track.empty' => 'The track path is empty.',
    'module.audio.problem.unavailable' => 'The glfw extension is not loaded — there is nothing to play the music with.',
    'module.audio.problem.load' => 'Cannot play the file "{path}" — check the path and the format (WAV, MP3, FLAC).',

    // The module's own part of the help tab — what cannot be read off the declarations.
    'module.audio.help.start' => 'Ctrl+A opens the playlist, Enter plays the selected track, and Space stops '
        . 'the playback and remembers the spot. The music starts on its own if you switch that on in the tab.',
    'module.audio.help.playlist' => 'Tracks come in three ways: F5 takes the entry selected in the browser, F7 '
        . 'asks for a path, and the audio.add command works from outside the window too. The playlist lives in '
        . '~/.light-manager/audio.json.',
    'module.audio.help.mode' => 'The "After a track" position decides what happens after the last note: looping '
        . 'the list keeps going, "stop" falls silent, and "repeat the track" plays the same one over. Positions '
        . 'without a file are skipped.',
    'module.audio.help.volume' => 'The audio.volume command changes the volume at once; the tab position '
        . 'applies from the next start of the track.',
];
