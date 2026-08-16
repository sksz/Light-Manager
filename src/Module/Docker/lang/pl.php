<?php

declare(strict_types=1);

/*
 * Napisy modułu „Docker” — polski.
 *
 * **Każdy klucz musi zaczynać się od `module.docker.`** — katalog przyjmuje
 * wyłącznie takie i pomija resztę.
 *
 * Napisy pochodzące od demona (powód odmowy, wiersz wypisu budowy) **nie są
 * tłumaczone i nie mają tu kluczy**: to cytaty z cudzego programu, wstawiane
 * w miejsce `{reason}`. Ta sama granica, co przy strumieniu błędów `sftp`
 * w kroku 49.
 */

return [
    'module.docker.name' => 'Docker',
    'module.docker.description' => 'Kontenery, obrazy, logi na żywo, budowanie obrazów i projekty compose.',

    // Powody, dla których rejestr odrzuca moduł (krok 51, D90 nr 6).
    'module.docker.unavailable.curl' => 'brak rozszerzenia PHP „curl”',
    'module.docker.unavailable.socket' => 'brak gniazda demona /var/run/docker.sock',

    // Komendy modułu.
    'module.docker.command.ps' => 'pokaż kontenery',
    'module.docker.command.images' => 'pokaż obrazy',
    'module.docker.command.build' => 'zbuduj obraz z katalogu',
    'module.docker.command.up' => 'podnieś projekt compose',
    'module.docker.command.down' => 'połóż projekt compose',
    'module.docker.argument.directory' => 'katalog z plikiem Dockerfile',
    'module.docker.argument.file' => 'plik compose albo katalog, w którym leży',

    // Kwerendy modułu (krok 54).
    'module.docker.query.images' => 'obrazy znane demonowi wraz z etykietami',
    'module.docker.query.containers' => 'kontenery wraz z projektem compose',
    'module.docker.query.compose' => 'projekty compose wraz z etapem pracy',
    'module.docker.query.build' => 'stan budowy: etap, znacznik, ostatni komunikat',

    // Pozycja zakładki ustawień — jedyna (D90 nr 3).
    'module.docker.setting.logLines' => 'Wierszy logu w pamięci',
    'module.docker.setting.registry' => 'Rejestr obrazów',
    'module.docker.setting.registryUser' => 'Użytkownik rejestru',
    'module.docker.setting.registryToken' => 'Token rejestru',

    // Nazwy postaci ekranu — widać je w etykiecie strefy.
    'module.docker.view.containers' => 'KONTENERY',
    'module.docker.view.images' => 'OBRAZY',
    'module.docker.view.logs' => 'LOGI',
    'module.docker.detail.title' => 'OPIS',

    // Nagłówki kolumn.
    'module.docker.column.name' => 'Nazwa',
    'module.docker.column.image' => 'Obraz',
    'module.docker.column.state' => 'Stan',
    'module.docker.column.ports' => 'Porty',
    'module.docker.column.created' => 'Wiek',
    'module.docker.column.size' => 'Rozmiar',

    // Pola opisu wybranego wpisu.
    'module.docker.detail.name' => 'Nazwa',
    'module.docker.detail.id' => 'Identyfikator',
    'module.docker.detail.image' => 'Obraz',
    'module.docker.detail.state' => 'Stan',
    'module.docker.detail.ports' => 'Porty',
    'module.docker.detail.project' => 'Projekt',
    'module.docker.detail.size' => 'Rozmiar',
    'module.docker.detail.tags' => 'Nazwy',

    // Stany kontenera — nazwy pochodzą z API demona, opisy są nasze.
    'module.docker.state.created' => 'utworzony',
    'module.docker.state.running' => 'działa',
    'module.docker.state.paused' => 'wstrzymany',
    'module.docker.state.restarting' => 'restartuje',
    'module.docker.state.removing' => 'usuwany',
    'module.docker.state.exited' => 'zatrzymany',
    'module.docker.state.dead' => 'martwy',
    'module.docker.state.unknown' => 'nieznany',

    // Zdania górnego pasa i puste listy.
    'module.docker.containers.header' => 'Kontenery na tej maszynie',
    'module.docker.containers.reading' => 'Pytam demona o kontenery…',
    'module.docker.containers.empty' => 'Demon nie prowadzi ani jednego kontenera.',
    'module.docker.containers.emptyProject' => 'Projekt {project} nie ma ani jednego kontenera.',
    'module.docker.containers.confirmRemoval' => 'Usunąć kontener {name}? Działający zostanie zatrzymany, a jego zawartość przepadnie.',
    'module.docker.images.header' => 'Obrazy na tej maszynie',
    'module.docker.images.reading' => 'Pytam demona o obrazy…',
    'module.docker.images.empty' => 'Demon nie ma ani jednego obrazu.',
    'module.docker.images.dangling' => 'obraz bez nazwy — został po przebudowie',
    'module.docker.images.confirmRemoval' => 'Usunąć obraz {image}? Odzyskanie go wymaga ponownego pobrania albo zbudowania.',

    // Logi.
    'module.docker.logs.header' => 'Logi: {name}',
    'module.docker.logs.waiting' => 'Czekam na pierwszy wiersz logu…',
    'module.docker.logs.ended' => 'Strumień logu się skończył — kontener nie działa.',
    'module.docker.logs.failed' => 'Nie udało się odczytać logu kontenera.',
    'module.docker.logs.dropped' => [
        '… pominięto {count} najstarszy wiersz',
        '… pominięto {count} najstarsze wiersze',
        '… pominięto {count} najstarszych wierszy',
    ],

    // Czynności na kontenerze i obrazie.
    'module.docker.action.working' => 'Czekam na demona…',
    'module.docker.action.impossible' => 'Kontenera {name} nie da się w tym stanie ani uruchomić, ani zatrzymać.',
    'module.docker.action.rejected' => 'Demon odmówił: {reason}',
    'module.docker.action.start' => 'uruchom',
    'module.docker.action.stop' => 'zatrzymaj',
    'module.docker.action.restart' => 'zrestartuj',
    'module.docker.action.remove-container' => 'usuń kontener',
    'module.docker.action.remove-image' => 'usuń obraz',
    'module.docker.action.done.start' => 'Kontener {name} działa.',
    'module.docker.action.done.stop' => 'Kontener {name} zatrzymany.',
    'module.docker.action.done.restart' => 'Kontener {name} zrestartowany.',
    'module.docker.action.done.remove-container' => 'Kontener {name} usunięty.',
    'module.docker.action.done.remove-image' => 'Obraz {name} usunięty.',

    // Powody, dla których rozmowa z demonem się nie udała.
    'module.docker.daemon.unsupported' => 'Rozmowa z demonem wymaga rozszerzenia „curl” i gniazda /var/run/docker.sock.',
    'module.docker.daemon.failed' => 'Nie udało się rozpocząć rozmowy z demonem.',
    'module.docker.daemon.unreachable' => 'Demon Dockera nie odpowiada — sprawdź, czy działa.',
    'module.docker.daemon.timedOut' => 'Demon Dockera nie odpowiedział w wyznaczonym czasie.',
    'module.docker.daemon.refused' => 'Demon Dockera odrzucił zapytanie.',
    'module.docker.stream.flood' => 'Strumień sypał szybciej, niż dało się go czytać — został zamknięty.',

    // Budowanie obrazu.
    'module.docker.build.title' => 'Budowanie {tag}',
    'module.docker.build.directory' => 'Katalog z plikiem Dockerfile',
    'module.docker.build.tag' => 'Nazwa obrazu (np. moj-obraz:latest)',
    'module.docker.build.stage.packing' => 'pakuję kontekst',
    'module.docker.build.stage.building' => 'buduję',
    'module.docker.build.busy' => 'Budowa już trwa — poczekaj, aż się skończy.',
    'module.docker.build.done' => 'Obraz {tag} zbudowany.',
    // Wypychanie obrazu do rejestru (krok 54).
    'module.docker.push.title' => 'Wypychanie {tag}',
    'module.docker.push.target' => 'Nazwa w rejestrze',
    'module.docker.push.stage' => 'wysyłanie warstw…',
    'module.docker.push.busy' => 'Wypychanie już trwa — poczekaj, aż się skończy.',
    'module.docker.push.noImage' => 'Nie ma czego wypchnąć: obraz bez etykiety nie ma nazwy, którą przyjmie rejestr.',
    'module.docker.push.done' => 'Obraz {tag} wypchnięty do rejestru.',
    'module.docker.push.cancelled' => 'Wypychanie przerwane.',
    'module.docker.push.started' => 'Wypychanie {tag} zaczęte.',
    'module.docker.push.failed' => 'Nie udało się wypchnąć obrazu.',
    'module.docker.push.rejected' => 'Rejestr odmówił: {reason}',
    'module.docker.push.notTagged' => 'Nie udało się nadać obrazowi {source} nazwy {target} — bez niej rejestr go nie przyjmie.',
    'module.docker.push.noCredentials' => 'Brak poświadczeń rejestru — uzupełnij je w ustawieniach modułu.',
    'module.docker.command.push' => 'wypchnij obraz do rejestru',
    'module.docker.argument.image' => 'nazwa obrazu wraz z etykietą',
    'module.docker.query.push' => 'stan wypychania obrazu do rejestru',

    'module.docker.build.cancelled' => 'Budowa przerwana.',
    'module.docker.build.failed' => 'Budowa się nie udała.',
    'module.docker.build.rejected' => 'Budowa się nie udała: {reason}',
    'module.docker.build.noContext' => 'Katalog {path} nie istnieje.',
    'module.docker.build.noDockerfile' => 'W katalogu {path} nie ma pliku Dockerfile.',
    'module.docker.build.emptyContext' => 'W katalogu {path} nie ma nic do spakowania.',
    'module.docker.build.tooLarge' => 'Kontekst budowy przekracza {limit} MB — zawęź go plikiem .dockerignore.',
    'module.docker.build.packFailed' => 'Nie udało się spakować kontekstu: {reason}',

    // Compose.
    'module.docker.compose.ls' => 'Czytam spis projektów…',
    'module.docker.compose.up' => 'Podnoszę projekt…',
    'module.docker.compose.down' => 'Kładę projekt…',
    'module.docker.compose.done.up' => 'Projekt podniesiony.',
    'module.docker.compose.done.down' => 'Projekt położony.',
    'module.docker.compose.busy' => 'Poprzednia praca compose jeszcze trwa.',
    'module.docker.compose.noFile' => 'Pod ścieżką {path} nie ma pliku compose.',
    'module.docker.compose.failed' => 'Praca compose się nie udała.',
    'module.docker.compose.rejected' => 'Compose odmówił: {reason}',
    'module.docker.compose.interrupted' => 'Praca compose została przerwana.',
    'module.docker.compose.noProjects' => 'Żaden kontener nie należy do projektu compose.',
    'module.docker.compose.narrowed' => 'Pokazuję projekt {project}.',
    'module.docker.compose.allProjects' => 'Pokazuję wszystkie kontenery.',

    // Rozmiary — jednostki są napisem, bo zapis liczby zależy od języka.
    'module.docker.size.bytes' => '{value} B',
    'module.docker.size.kib' => '{value} KiB',
    'module.docker.size.mib' => '{value} MiB',
    'module.docker.size.gib' => '{value} GiB',

    // Wiek wpisu — formy mnogie, bo polski ma ich trzy.
    'module.docker.age.minutes' => ['{count} min', '{count} min', '{count} min'],
    'module.docker.age.hours' => ['{count} godz.', '{count} godz.', '{count} godz.'],
    'module.docker.age.days' => ['{count} dzień', '{count} dni', '{count} dni'],

    // Opisy klawiszy — długi do okna pomocy, krótki do paska stanu.
    'module.docker.key.logs' => 'pokaż logi kontenera',
    'module.docker.key.logs.short' => 'logi',
    'module.docker.key.images' => 'przejdź do obrazów',
    'module.docker.key.images.short' => 'obrazy',
    'module.docker.key.containers' => 'wróć do kontenerów',
    'module.docker.key.containers.short' => 'kontenery',
    'module.docker.key.toggle' => 'uruchom albo zatrzymaj kontener',
    'module.docker.key.toggle.short' => 'start/stop',
    'module.docker.key.restart' => 'zrestartuj kontener',
    'module.docker.key.restart.short' => 'restart',
    'module.docker.key.project' => 'zawęź do projektu compose',
    'module.docker.key.project.short' => 'projekt',
    'module.docker.key.build' => 'zbuduj obraz z katalogu',
    'module.docker.key.build.short' => 'buduj',
    'module.docker.key.remove' => 'usuń kontener',
    'module.docker.key.remove.short' => 'usuń',
    'module.docker.key.removeImage' => 'usuń obraz',
    'module.docker.key.refresh' => 'odśwież listy',
    'module.docker.key.refresh.short' => 'odśwież',
    'module.docker.key.follow' => 'wróć na koniec logu',
    'module.docker.key.follow.short' => 'koniec',
    'module.docker.key.back' => 'wróć do listy kontenerów',
    'module.docker.key.back.short' => 'powrót',

    // Nazwy zdarzeń — widoczne w oknie odbiorcy (moduł dźwięku).
    'module.docker.event.container.changed' => 'Kontener zmienił stan',
    'module.docker.event.removed' => 'Kontener albo obraz usunięty',
    'module.docker.event.action.failed' => 'Czynność Dockera nieudana',
    'module.docker.event.build.finished' => 'Obraz zbudowany',
    'module.docker.event.build.failed' => 'Budowa nieudana',
    'module.docker.event.compose.changed' => 'Projekt compose zmieniony',

    // Zakładka pomocy — część, której z deklaracji wyczytać się nie da.
    'module.docker.help.start' => 'Ctrl+O otwiera listę kontenerów; F3 przełącza między kontenerami a obrazami.',
    'module.docker.help.lists' => 'Listy pochodzą wprost od demona (gniazdo /var/run/docker.sock) i odświeżają się co kilka sekund, dopóki ten ekran jest widoczny.',
    'module.docker.help.actions' => 'F4 uruchamia albo zatrzymuje kontener — zależnie od jego stanu; Shift+F4 restartuje. F8 usuwa i pyta o zgodę.',
    'module.docker.help.logs' => 'Enter otwiera logi kontenera. Płyną na żywo także wtedy, gdy patrzysz na co innego; strzałka w górę zatrzymuje widok, End wraca na koniec.',
    'module.docker.help.build' => 'F7 buduje obraz: pyta o katalog z plikiem Dockerfile, potem o nazwę. Kontekst pakowany jest z pominięciem tego, co wyklucza .dockerignore.',
    'module.docker.help.compose' => 'Komendy docker.up i docker.down podnoszą i kładą projekt compose; bez argumentu biorą plik z katalogu, w którym stoi przeglądarka. F5 zawęża listę do projektu.',
    'module.docker.help.refresh' => 'Ctrl+R odświeża obie listy natychmiast — po własnej czynności robi się to samo z siebie.',
];
