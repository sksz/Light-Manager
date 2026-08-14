<?php

declare(strict_types=1);

/*
 * Napisy modułu „Dźwięk” — polski.
 *
 * **Każdy klucz musi zaczynać się od `module.audio.`** — katalog przyjmuje
 * wyłącznie takie i pomija resztę.
 */

return [
    'module.audio.name' => 'Dźwięk',
    'module.audio.description' => 'Odtwarzanie muzyki obok pracy z plikami — komendami, bez własnego okna.',

    // Komendy modułu.
    'module.audio.command.music' => 'włącz muzykę albo ją zatrzymaj',
    'module.audio.command.volume' => 'ustaw głośność muzyki',
    'module.audio.argument.level' => 'głośność w procentach',

    // Pozycje zakładki ustawień.
    'module.audio.setting.track' => 'Utwór',
    'module.audio.setting.volume' => 'Głośność (%)',
    'module.audio.setting.loop' => 'Odtwarzanie w kółko',

    // Zdania w pasku stanu.
    'module.audio.playing' => 'Gra: {track}',
    'module.audio.stopped' => 'Muzyka zatrzymana.',
    'module.audio.volume.set' => 'Głośność: {level}%.',
    'module.audio.volume.rejected' => 'Głośność przyjmuje wartości: {levels}.',

    // Powody, dla których nie ma czego usłyszeć.
    'module.audio.problem.unavailable' => 'Rozszerzenie glfw nie jest załadowane — nie ma czym odtworzyć muzyki.',
    'module.audio.problem.load' => 'Nie można odtworzyć pliku „{path}” — sprawdź ścieżkę i format (WAV, MP3, FLAC).',

    // Własna część zakładki pomocy — to, czego z deklaracji wyczytać się nie da.
    'module.audio.help.start' => 'Muzyka nie rusza sama: włącza ją komenda audio.music, a drugie jej '
        . 'wywołanie zatrzymuje granie i zapamiętuje miejsce.',
    'module.audio.help.track' => 'Utwór wskazuje pozycja „Utwór” — ścieżka względna liczy się od katalogu '
        . 'aplikacji, a formaty to WAV, MP3 i FLAC (plik MIDI się nie nada).',
    'module.audio.help.volume' => 'Komenda audio.volume zmienia głośność natychmiast; pozycja na zakładce '
        . 'obowiązuje od następnego uruchomienia utworu.',
];
