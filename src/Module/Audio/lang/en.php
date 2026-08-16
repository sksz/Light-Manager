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
    'module.audio.command.hook' => 'assign a sound to an event',
    'module.audio.query.playlist' => 'playlist entries along with missing files',
    'module.audio.query.now-playing' => 'what plays, in which mode and whether there is an engine',
    'module.audio.query.effects' => 'sounds assigned to application events',
    'module.audio.argument.level' => 'volume in percent',
    'module.audio.argument.path' => 'path to an audio file',
    'module.audio.argument.event' => 'event name',

    // Settings tab positions.
    'module.audio.setting.volume' => 'Volume (%)',
    'module.audio.setting.mode' => 'After a track',
    'module.audio.setting.autostart' => 'Play from startup',
    'module.audio.setting.effects' => 'Sound effects',
    'module.audio.setting.effectsVolume' => 'Effects volume (%)',

    // The module window's top zone and the playback modes.
    'module.audio.zone.now' => 'Playback',
    'module.audio.nowPlaying' => 'Playing: {track}',
    'module.audio.paused' => 'Paused: {track}',
    'module.audio.nothing' => 'Silence',
    'module.audio.mode.list' => 'loop the list',
    'module.audio.mode.once' => 'stop',
    'module.audio.mode.repeat' => 'repeat the track',

    // Labels of both split panels (step 46).
    'module.audio.zone.effects' => 'Effects',
    'module.audio.zone.playlist' => 'Playlist',

    // Focus places and window keys.
    'module.audio.focus.playlist' => 'Playlist',
    'module.audio.focus.effects' => 'Effects',
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
    'module.audio.key.pane' => 'go to the other panel',
    'module.audio.key.pane.short' => 'panel',

    // Effects panel keys (step 46) — the same numbers as in the playlist,
    // because they mean the same: take, type, take away.
    'module.audio.key.hook.take' => 'assign the entry selected in the browser',
    'module.audio.key.hook.take.short' => 'take entry',
    'module.audio.key.hook.type' => 'assign a file by typing its path',
    'module.audio.key.hook.type.short' => 'type',
    'module.audio.key.hook.clear' => 'take the file away from the event',
    'module.audio.key.hook.clear.short' => 'take away',
    'module.audio.key.hook.mute' => 'mute it or switch it back on',
    'module.audio.key.hook.mute.short' => 'mute',

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

    // Effects panel: row markers and sentences about assignments (step 46).
    'module.audio.effect.on' => '♪',
    'module.audio.effect.muted' => '×',
    'module.audio.effect.missing' => '!',
    'module.audio.effect.none' => '—',
    'module.audio.effects.empty' => 'The application announces no events.',
    'module.audio.effect.assigned' => 'The "{event}" event now plays: {file}.',
    'module.audio.effect.cleared' => 'The "{event}" event plays nothing now.',
    'module.audio.effect.nothingToClear' => 'The "{event}" event played nothing anyway.',
    'module.audio.effect.unknownEvent' => 'There is no event named "{event}".',

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
    'module.audio.help.effects' => 'The window\'s left panel (Tab) assigns sound files to application events: '
        . 'F5 takes the entry from the browser, F7 asks for a path, F8 takes the assignment away, and Space '
        . 'mutes it without taking it away. An effect plays over the music, at its own volume, and the "Sound '
        . 'effects" position mutes them all at once.',
];
