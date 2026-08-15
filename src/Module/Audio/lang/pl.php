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
    'module.audio.description' => 'Odtwarzanie muzyki obok pracy z plikami — playlista, która gra dalej sama.',

    // Komendy modułu.
    'module.audio.command.music' => 'włącz muzykę albo ją zatrzymaj',
    'module.audio.command.volume' => 'ustaw głośność muzyki',
    'module.audio.command.add' => 'dopisz utwór do playlisty',
    'module.audio.command.hook' => 'przypisz dźwięk do zdarzenia',
    'module.audio.argument.level' => 'głośność w procentach',
    'module.audio.argument.path' => 'ścieżka pliku dźwiękowego',
    'module.audio.argument.event' => 'nazwa zdarzenia',

    // Pozycje zakładki ustawień.
    'module.audio.setting.volume' => 'Głośność (%)',
    'module.audio.setting.mode' => 'Po utworze',
    'module.audio.setting.autostart' => 'Graj od uruchomienia',
    'module.audio.setting.effects' => 'Efekty specjalne',
    'module.audio.setting.effectsVolume' => 'Głośność efektów (%)',

    // Strefa górna okna modułu i tryby odtwarzania.
    'module.audio.zone.now' => 'Odtwarzanie',
    'module.audio.nowPlaying' => 'Gra: {track}',
    'module.audio.paused' => 'Zatrzymane: {track}',
    'module.audio.nothing' => 'Cisza',
    'module.audio.mode.list' => 'pętla listy',
    'module.audio.mode.once' => 'zatrzymaj',
    'module.audio.mode.repeat' => 'powtarzaj utwór',

    // Etykiety obu paneli podziału (krok 46).
    'module.audio.zone.effects' => 'Efekty',
    'module.audio.zone.playlist' => 'Playlista',

    // Miejsca ogniska i klawisze okna modułu.
    'module.audio.focus.playlist' => 'Playlista',
    'module.audio.focus.effects' => 'Efekty',
    'module.audio.focus.path' => 'Ścieżka utworu',
    'module.audio.key.play' => 'zagraj wskazany utwór',
    'module.audio.key.play.short' => 'graj',
    'module.audio.key.pause' => 'zatrzymaj albo wznów',
    'module.audio.key.pause.short' => 'pauza',
    'module.audio.key.take' => 'dopisz wpis zaznaczony w przeglądarce',
    'module.audio.key.take.short' => 'weź wpis',
    'module.audio.key.type' => 'dopisz utwór, wpisując ścieżkę',
    'module.audio.key.type.short' => 'wpisz',
    'module.audio.key.remove' => 'usuń pozycję z playlisty',
    'module.audio.key.remove.short' => 'usuń',
    'module.audio.key.move' => 'przestaw pozycję w liście',
    'module.audio.key.move.short' => 'przestaw',
    'module.audio.key.confirm' => 'dopisz wpisaną ścieżkę',
    'module.audio.key.cancel' => 'zamknij pole bez dopisywania',
    'module.audio.key.cancel.short' => 'zamknij pole',
    'module.audio.key.pane' => 'przejdź do drugiego panelu',
    'module.audio.key.pane.short' => 'panel',

    // Klawisze panelu efektów (krok 46) — te same numery, co w playliście, bo
    // znaczą to samo: weź, wpisz, zabierz.
    'module.audio.key.hook.take' => 'przypisz wpis zaznaczony w przeglądarce',
    'module.audio.key.hook.take.short' => 'weź wpis',
    'module.audio.key.hook.type' => 'przypisz plik, wpisując ścieżkę',
    'module.audio.key.hook.type.short' => 'wpisz',
    'module.audio.key.hook.clear' => 'zabierz zdarzeniu plik',
    'module.audio.key.hook.clear.short' => 'zabierz',
    'module.audio.key.hook.mute' => 'wycisz albo włącz z powrotem',
    'module.audio.key.hook.mute.short' => 'wycisz',

    // Zdania w pasku stanu i w oknie modułu.
    'module.audio.playing' => 'Gra: {track}',
    'module.audio.stopped' => 'Muzyka zatrzymana.',
    'module.audio.volume.set' => 'Głośność: {level}%.',
    'module.audio.volume.rejected' => 'Głośność przyjmuje wartości: {levels}.',
    'module.audio.playlist.empty' => 'Playlista jest pusta — dopisz utwór klawiszem F5 albo F7.',
    'module.audio.playlist.nothingPlayable' => 'Żadnego z plików playlisty nie da się teraz odtworzyć.',
    'module.audio.playlist.added' => 'Dopisano do playlisty: {track}.',
    'module.audio.playlist.missing' => 'brak pliku',
    'module.audio.playlist.noSelection' => 'W przeglądarce nie ma zaznaczonego wpisu.',
    'module.audio.playlist.path' => 'Ścieżka',
    'module.audio.playlist.unreadable' => 'Nie można odczytać playlisty z pliku „{path}” — zaczynamy od pustej.',

    // Panel efektów: znaczniki wierszy i zdania o przypisaniach (krok 46).
    'module.audio.effect.on' => '♪',
    'module.audio.effect.muted' => '×',
    'module.audio.effect.missing' => '!',
    'module.audio.effect.none' => '—',
    'module.audio.effects.empty' => 'Aplikacja nie ogłasza żadnych zdarzeń.',
    'module.audio.effect.assigned' => 'Zdarzenie „{event}” gra teraz: {file}.',
    'module.audio.effect.cleared' => 'Zdarzenie „{event}” nie gra już niczego.',
    'module.audio.effect.nothingToClear' => 'Zdarzenie „{event}” i tak nic nie grało.',
    'module.audio.effect.unknownEvent' => 'Nie ma zdarzenia o nazwie „{event}”.',

    // Powody, dla których nie ma czego usłyszeć.
    'module.audio.track.empty' => 'Ścieżka utworu jest pusta.',
    'module.audio.problem.unavailable' => 'Rozszerzenie glfw nie jest załadowane — nie ma czym odtworzyć muzyki.',
    'module.audio.problem.load' => 'Nie można odtworzyć pliku „{path}” — sprawdź ścieżkę i format (WAV, MP3, FLAC).',

    // Własna część zakładki pomocy — to, czego z deklaracji wyczytać się nie da.
    'module.audio.help.start' => 'Ctrl+A otwiera playlistę, Enter gra wskazany utwór, a spacja zatrzymuje '
        . 'granie i zapamiętuje miejsce. Muzyka rusza sama przy starcie, jeśli włączysz to na zakładce.',
    'module.audio.help.playlist' => 'Utwory dopisujesz na trzy sposoby: F5 bierze wpis zaznaczony '
        . 'w przeglądarce, F7 pyta o ścieżkę, a komenda audio.add działa także spoza okna. Playlista mieszka '
        . 'w pliku ~/.light-manager/audio.json.',
    'module.audio.help.mode' => 'Pozycja „Po utworze” rozstrzyga, co się dzieje po ostatniej nucie: pętla '
        . 'listy gra dalej, „zatrzymaj” milknie, a „powtarzaj utwór” gra ten sam w kółko. Pozycje bez pliku '
        . 'playlista pomija.',
    'module.audio.help.volume' => 'Komenda audio.volume zmienia głośność natychmiast; pozycja na zakładce '
        . 'obowiązuje od następnego uruchomienia utworu.',
    'module.audio.help.effects' => 'Lewy panel okna (Tab) przypisuje pliki dźwiękowe do zdarzeń aplikacji: '
        . 'F5 bierze wpis z przeglądarki, F7 pyta o ścieżkę, F8 zabiera przypisanie, a spacja wycisza je bez '
        . 'zabierania. Efekt gra na muzyce, własną głośnością, a pozycja „Efekty specjalne” ucisza wszystkie '
        . 'naraz.',
];
