<?php

declare(strict_types=1);

/*
 * Napisy modułu „Sesja zdalna” — polski.
 *
 * **Każdy klucz musi zaczynać się od `module.ssh.`** — katalog przyjmuje
 * wyłącznie takie i pomija resztę.
 */

return [
    'module.ssh.name' => 'Sesja zdalna',
    'module.ssh.description' => 'Połączenie SSH z hostem ze spisu — z pamiętanym odciskiem klucza i stanem w pasku.',

    // Powód, dla którego moduł bywa odrzucony przez rejestr (krok 48, D87 nr 11).
    'module.ssh.unavailable.client' => 'brak klienta OpenSSH (ssh, ssh-keyscan)',

    // Komendy modułu.
    'module.ssh.command.connect' => 'połącz z hostem ze spisu',
    'module.ssh.command.disconnect' => 'zamknij sesję zdalną',
    'module.ssh.command.hosts' => 'pokaż spis hostów',
    'module.ssh.argument.host' => 'nazwa wpisu w spisie hostów',

    // Pozycje zakładki ustawień.
    'module.ssh.setting.timeout' => 'Limit czasu połączenia (s)',
    'module.ssh.setting.auth' => 'Sposób uwierzytelnienia',
    'module.ssh.setting.remember' => 'Zapamiętuj odciski nowych hostów',
    'module.ssh.setting.showHidden' => 'Pokazuj wpisy ukryte',
    'module.ssh.auth.agent' => 'agent',
    'module.ssh.auth.key' => 'klucz z pliku',
    'module.ssh.auth.password' => 'hasło',

    // Ekran modułu.
    'module.ssh.screen.hosts' => 'Hosty',
    'module.ssh.focus.hosts' => 'Spis hostów',
    'module.ssh.column.name' => 'Nazwa',
    'module.ssh.column.target' => 'Użytkownik i host',
    'module.ssh.column.auth' => 'Sposób',
    'module.ssh.column.state' => 'Stan',
    'module.ssh.empty' => 'Spis jest pusty — dopisz host klawiszem F7.',
    'module.ssh.header.session' => '{stage}: {host}',

    // Zdalny katalog (krok 49).
    'module.ssh.screen.remote' => 'Katalog zdalny',
    'module.ssh.focus.remote' => 'Zdalny katalog',
    'module.ssh.column.entry' => 'Nazwa',
    'module.ssh.column.size' => 'Rozmiar',
    'module.ssh.column.modified' => 'Zmieniony',
    'module.ssh.column.permissions' => 'Prawa',
    'module.ssh.remote.header' => '{host}:{path}',
    'module.ssh.remote.reading' => '{host}:{path} — czytam…',
    'module.ssh.remote.wait' => 'Czytam katalog…',
    'module.ssh.remote.empty' => 'Katalog jest pusty.',
    'module.ssh.remote.noMatch' => 'Żaden wpis nie pasuje do filtra.',
    'module.ssh.remote.atRoot' => 'To jest korzeń — wyżej nic nie ma.',
    'module.ssh.remote.notADirectory' => '„{name}” nie jest katalogiem.',
    'module.ssh.remote.hidden.on' => 'Wpisy ukryte są teraz widoczne.',
    'module.ssh.remote.hidden.off' => 'Wpisy ukryte są teraz schowane.',
    'module.ssh.entry.file' => 'plik',
    'module.ssh.entry.directory' => 'katalog',
    'module.ssh.entry.symlink' => 'dowiązanie',
    'module.ssh.entry.other' => 'inny wpis',

    // Klawisze zdalnego katalogu.
    'module.ssh.remote.key.enter' => 'wejdź do katalogu',
    'module.ssh.remote.key.enter.short' => 'wejdź',
    'module.ssh.remote.key.up' => 'katalog wyżej',
    'module.ssh.remote.key.up.short' => 'wyżej',
    'module.ssh.remote.key.refresh' => 'przeczytaj katalog na nowo',
    'module.ssh.remote.key.refresh.short' => 'odśwież',
    'module.ssh.remote.key.filter' => 'zawęź listę nazwą',
    'module.ssh.remote.key.filter.short' => 'filtr',
    'module.ssh.remote.key.hidden' => 'pokaż albo schowaj wpisy ukryte',
    'module.ssh.remote.key.hidden.short' => 'ukryte',
    'module.ssh.remote.key.hosts' => 'zajrzyj do spisu hostów',
    'module.ssh.remote.key.hosts.short' => 'hosty',
    'module.ssh.remote.key.clear' => 'zdejmij filtr',
    'module.ssh.remote.key.clear.short' => 'bez filtra',

    // Pole filtra zdalnej listy.
    'module.ssh.filter.zone' => 'Filtr',
    'module.ssh.filter.prompt' => 'fragment nazwy ',
    'module.ssh.filter.key.accept' => 'zatwierdź zawężenie',
    'module.ssh.filter.key.cancel' => 'zdejmij filtr',

    // Powody niepowodzeń odczytu katalogu (SftpFailureReader).
    'module.ssh.listing.failed' => 'Nie udało się odczytać zdalnego katalogu.',
    'module.ssh.listing.missing' => 'Takiego katalogu nie ma na zdalnym hoście.',
    'module.ssh.listing.denied' => 'Brak prawa wejścia do tego katalogu.',
    'module.ssh.listing.unreadable' => 'Zdalnego katalogu nie da się odczytać.',
    'module.ssh.listing.dropped' => 'Sesja została zerwana — odczyt nie doszedł do skutku.',
    'module.ssh.listing.unsupported' => 'Host nie udostępnia podsystemu SFTP.',
    'module.ssh.listing.interrupted' => 'Odczyt katalogu przerwała inna praca w tle.',

    // Etapy sesji — kolumna „Stan” i wiersz okna postępu.
    'module.ssh.stage.idle' => 'rozłączony',
    'module.ssh.stage.probing' => 'sprawdzam',
    'module.ssh.stage.approval' => 'czeka na zgodę',
    'module.ssh.stage.connecting' => 'łączę',
    'module.ssh.stage.checking' => 'odświeżam',
    'module.ssh.stage.connected' => 'połączony',
    'module.ssh.stage.failed' => 'nieudane',

    // Klawisze ekranu.
    'module.ssh.key.move' => 'wybór hosta',
    'module.ssh.key.connect' => 'połącz albo rozłącz',
    'module.ssh.key.connect.short' => 'połącz',
    'module.ssh.key.auth' => 'zmień sposób uwierzytelnienia',
    'module.ssh.key.auth.short' => 'sposób',
    'module.ssh.key.refresh' => 'sprawdź stan sesji',
    'module.ssh.key.refresh.short' => 'stan',
    'module.ssh.key.add' => 'dopisz host',
    'module.ssh.key.add.short' => 'dopisz',
    'module.ssh.key.remove' => 'usuń host ze spisu',
    'module.ssh.key.remove.short' => 'usuń',

    // Okna.
    'module.ssh.prompt.host' => 'Nowy host',
    'module.ssh.prompt.host.field' => 'użytkownik@host:port ',
    'module.ssh.prompt.key' => 'Klucz dla {host}',
    'module.ssh.prompt.key.field' => 'ścieżka klucza ',
    'module.ssh.prompt.password' => 'Hasło do {host}',
    'module.ssh.prompt.password.field' => 'hasło ',
    'module.ssh.progress.connecting' => 'Łączenie z {host}',
    'module.ssh.confirm.remove' => 'Usunąć wpis „{host}” ze spisu?',
    'module.ssh.confirm.fingerprint' => 'Host {host} jest nieznany. Odcisk klucza: {fingerprint}. Zaufać mu i dopisać do znanych hostów?',

    // Zdania w pasku stanu.
    'module.ssh.message.connected' => 'Połączono z {host}.',
    'module.ssh.message.disconnected' => 'Rozłączono z {host}.',
    'module.ssh.message.cancelled' => 'Przerwano łączenie z {host}.',
    'module.ssh.message.added' => 'Dopisano host „{host}”.',
    'module.ssh.message.removed' => 'Usunięto host „{host}”.',
    'module.ssh.message.auth' => 'Host „{host}” uwierzytelnia się teraz przez: {auth}.',
    'module.ssh.message.unknown' => 'Nie ma w spisie hosta „{host}”.',
    'module.ssh.message.nothing' => 'Żadna sesja nie jest otwarta.',

    // Powody niepowodzeń — czytane z tego, co wypisał klient (SshFailureReader).
    'module.ssh.problem.failed' => 'Nie udało się połączyć z {host}.',
    'module.ssh.problem.key-changed' => 'UWAGA: klucz hosta {host} jest inny niż zapamiętany. Połączenie odrzucone.',
    'module.ssh.problem.key-rejected' => 'Klucz hosta {host} nie został przyjęty.',
    'module.ssh.problem.denied' => 'Odmowa dostępu do {host} — uwierzytelnienie się nie powiodło.',
    'module.ssh.problem.unresolved' => 'Nie udało się rozwiązać nazwy hosta {host}.',
    'module.ssh.problem.refused' => 'Host {host} odrzucił połączenie.',
    'module.ssh.problem.timeout' => 'Host {host} nie odpowiedział w wyznaczonym czasie.',
    'module.ssh.problem.unreachable' => 'Host {host} jest nieosiągalny.',
    'module.ssh.problem.closed' => 'Host {host} zamknął połączenie.',
    'module.ssh.problem.key-permissions' => 'Plik klucza ma zbyt szerokie prawa dostępu.',
    'module.ssh.problem.key-missing' => 'Nie znaleziono pliku klucza.',
    'module.ssh.problem.unknown-host' => 'Host {host} jest nieznany, a zapamiętywanie odcisków jest wyłączone.',
    'module.ssh.problem.dropped' => 'Sesja z {host} została zerwana.',
    'module.ssh.problem.interrupted' => 'Łączenie z {host} przerwała inna praca w tle.',

    // Profil hosta — powody odmowy samowalidacji.
    'module.ssh.profile.name.empty' => 'Nazwa hosta jest pusta.',
    'module.ssh.profile.name.invalid' => 'Nazwa „{name}” jest za długa albo zawiera znaki sterujące.',
    'module.ssh.profile.host.invalid' => 'To nie jest poprawna nazwa hosta ani adres: „{host}”.',
    'module.ssh.profile.user.invalid' => 'To nie jest poprawna nazwa użytkownika: „{user}”.',
    'module.ssh.profile.port.invalid' => 'Numer portu musi mieścić się w zakresie 1–65535.',
    'module.ssh.profile.key.invalid' => 'Ścieżka klucza musi być bezwzględna: „{path}”.',

    // Książka hostów.
    'module.ssh.book.unreadable' => 'Nie udało się przeczytać spisu hostów — zapis go nadpisze.',

    // Zdarzenia modułu (krok 46).
    'module.ssh.event.connected' => 'sesja zdalna otwarta',
    'module.ssh.event.disconnected' => 'sesja zdalna zamknięta',
    'module.ssh.event.failed' => 'nieudane połączenie zdalne',

    // Zakładka pomocy.
    'module.ssh.help.start' => 'Ctrl+S otwiera spis hostów; to samo robi komenda ssh.hosts.',
    'module.ssh.help.hosts' => 'Hosty dopisuje się klawiszem F7 w postaci użytkownik@host:port; spis mieszka w ~/.light-manager/ssh.json i przeżywa ponowne uruchomienie.',
    'module.ssh.help.auth' => 'Domyślnym sposobem uwierzytelnienia jest agent SSH. Hasła nie są nigdzie zapisywane — pytanie pada przy każdym połączeniu.',
    'module.ssh.help.fingerprint' => 'Host nieznany zatrzymuje połączenie pytaniem o odcisk klucza. Po zgodzie wpis dopisuje sam klient OpenSSH do ~/.ssh/known_hosts; klucz niezgodny z zapamiętanym odmawia bez pytania.',
    'module.ssh.help.remote' => 'Po połączeniu ekran pokazuje zdalny katalog: Enter wchodzi, Backspace wraca wyżej, F5 czyta na nowo, / zawęża listę, a F3 zagląda do spisu hostów. Ostatni katalog jest pamiętany osobno dla każdego wpisu spisu.',
    'module.ssh.help.hidden' => 'Ctrl+H przełącza wpisy ukryte i zapisuje wybór w ustawieniach modułu. Przełączenie czyta katalog na nowo, bo serwer bez tej prośby wpisów zaczynających się kropką w ogóle nie przysyła.',
    'module.ssh.help.refresh' => 'Stan sesji odświeża F5. Aplikacja nie sprawdza go co klatkę, bo każde sprawdzenie to osobny proces — sesja zerwana przez sieć bywa więc przez chwilę pokazana jako żywa.',
];
