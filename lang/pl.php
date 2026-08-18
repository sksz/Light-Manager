<?php

declare(strict_types=1);

/*
 * Katalog napisów — polski.
 *
 * Klucze są płaskie, rozdzielone kropką: pierwszy człon nazywa miejsce w
 * interfejsie, dalsze uszczegóławiają. Parametry wstawia się w nawiasach
 * klamrowych ({path}, {count}) — nie przez `%s`, bo kolejność podstawień bywa
 * w różnych językach inna, a nazwa przeżyje przestawienie zdania.
 *
 * Napis zapisany jako lista jest formą mnogą: polski ma trzy formy (1 plik,
 * 2 pliki, 5 plików) i tyle właśnie pozycji musi mieć każda taka lista.
 * Kolejność form pilnuje `Infrastructure\I18n\PluralRule`.
 *
 * Zestaw kluczy musi być identyczny we wszystkich katalogach — pilnuje tego
 * test `TranslatorServiceTest`.
 */

return [
    // Nazwa aplikacji — jedyny napis, którego się nie tłumaczy, a który mimo to stoi
    // w katalogu: pokazuje go nagłówek ekranu pomocy, a napisów w kodzie nie ma.
    'app.name' => 'Light Manager',

    // Formatowanie liczb — ścieżka awaryjna, gdy brakuje rozszerzenia `intl`.
    'format.decimal' => ',',

    // Zapis liczby procent w pasku postępu. Osobny klucz, bo odstęp przed znakiem
    // procenta jest sprawą języka, a nie komponentu.
    'format.percent' => '{value}%',

    // Etykiety stref układu HUD, wpięte w krawędź obwódki panelu.
    'layout.zone.path' => 'ŚCIEŻKA',
    'layout.zone.about' => 'APLIKACJA',
    'layout.zone.settings.file' => 'PLIK KONFIGURACYJNY',
    'layout.zone.settings' => 'USTAWIENIA',
    'layout.zone.help' => 'POMOC',
    'layout.zone.preview' => 'PODGLĄD',
    'layout.zone.command' => 'KOMENDY',
    'layout.zone.query' => 'ŹRÓDŁA DANYCH',

    // Ekran ustawień.
    'settings.tab.appearance' => 'WYGLĄD',
    'settings.tab.graphics' => 'GRAFIKA',
    'settings.tab.resources' => 'ZASOBY',
    'settings.tab.modules' => 'MODUŁY',
    'settings.modules.empty' => '(żaden moduł nie jest zadeklarowany)',
    'settings.modules.essential' => 'zawsze włączony',
    'settings.modules.essential.reason' => 'Tego modułu nie da się wyłączyć — to do niego aplikacja wraca, gdy moduł domyślny jest niedostępny.',
    'settings.key.language' => 'Język',
    'settings.key.theme' => 'Motyw',
    'settings.key.startupModule' => 'Moduł otwierany przy starcie',
    'settings.key.textAntialias' => 'Wygładzanie tekstu',
    'settings.key.strokeAntialias' => 'Wygładzanie obrysów',
    'settings.key.paletteColors' => 'Kolory palety Sixela',
    'settings.key.windowColumns' => 'Kolumny okna (tryb okienkowy)',
    'settings.key.windowRows' => 'Wiersze okna (tryb okienkowy)',
    'settings.key.backgroundOutputKib' => 'Pamięć na wynik pracy w tle',
    'settings.key.backgroundJobs' => 'Prace w tle naraz',
    'settings.key.mouse' => 'Mysz',
    'settings.language.auto' => 'Automatyczny',
    'settings.language.pl' => 'Polski',
    'settings.language.en' => 'English',
    'settings.action.restore' => 'Przywróć ustawienia domyślne',
    'settings.restore.confirm' => 'Przywrócić ustawienia domyślne? Obecne przepadną bezpowrotnie.',
    'settings.restore.done' => 'Przywrócono ustawienia domyślne.',
    'settings.restore.unchanged' => 'Ustawienia są już domyślne.',
    'settings.value.yes' => 'tak',
    'settings.value.no' => 'nie',
    'settings.value.kib' => '{value} KiB',
    'settings.value.empty' => '(puste)',
    'settings.value.unknown' => 'nieznana wartość: {value}',
    'settings.palette.warning' => 'Poniżej {colors} kolorów obwódki paneli znikają z klatki.',

    // Ekran pomocy. Same nazwy klawiszy zostają w kodzie — ich lokalizacja leży
    // poza zakresem kroku 15.
    'help.key.move' => 'zmiana zaznaczenia',
    'help.key.help' => 'pomoc',
    'help.key.settings' => 'ustawienia',
    'help.key.back' => 'powrót do listy plików',
    'help.key.quit' => 'wyjście',
    'help.key.change' => 'zmiana wartości',
    'help.key.scroll' => 'przewijanie',
    'help.key.page' => 'strona w górę lub w dół, początek i koniec',
    'help.key.tab' => 'zmiana zakładki',
    'help.key.restore' => 'przywróć ustawienia domyślne',
    'help.key.commands' => 'okno komend',
    'help.key.edit' => 'edycja wartości',
    'help.key.commit' => 'zatwierdź wartość',
    'help.key.cancel' => 'porzuć zmianę',
    'help.key.collapse' => 'zwiń lub rozwiń sekcję',
    'help.key.fullscreen' => 'pełny ekran',
    'help.key.menu' => 'menu kontekstowe',

    // Krótkie opisy — **wyłącznie dla paska stanu** (krok 40, rozstrzygnięcie 5).
    // W oknie pomocy każdy klawisz ma wiersz dla siebie i pełne zdanie czyta się
    // tam lepiej; w stopce pozycji jest kilkanaście naraz i liczy się kolumna.
    // Klucza bez pary `.short` nie brakuje — znaczy „ten opis jest już krótki”.
    'help.key.move.short' => 'zaznaczenie',
    'help.key.page.short' => 'strona',
    'help.key.back.short' => 'powrót',
    'help.key.change.short' => 'wartość',
    'help.key.tab.short' => 'zakładka',
    'help.key.restore.short' => 'przywróć',
    'help.key.commands.short' => 'komendy',
    'help.key.edit.short' => 'edycja',
    'help.key.commit.short' => 'zatwierdź',
    'help.key.cancel.short' => 'porzuć',
    'help.key.collapse.short' => 'zwiń',
    'help.key.menu.short' => 'menu',

    // Zaznaczanie treści wskaźnikiem (krok 56). Jedyne zdanie, które ten
    // mechanizm mówi wprost — liczba wierszy odmienia je, stąd trzy formy.
    'selection.rows' => [
        'Zaznaczono {count} wiersz klatki.',
        'Zaznaczono {count} wiersze klatki.',
        'Zaznaczono {count} wierszy klatki.',
    ],

    // Schowek systemowy (krok 57). Zdania o skopiowaniu są **osobne dla każdego
    // źródła**, bo trzy różne treści po tym samym klawiszu są dla użytkownika
    // nierozróżnialne, dopóki zdanie jest jedno.
    'clipboard.key.copy' => 'skopiuj do schowka',
    'clipboard.key.paste' => 'wklej ze schowka',
    'clipboard.copied.selection' => [
        'Skopiowano zaznaczenie: {count} wiersz.',
        'Skopiowano zaznaczenie: {count} wiersze.',
        'Skopiowano zaznaczenie: {count} wierszy.',
    ],
    'clipboard.copied.answer' => [
        'Skopiowano odpowiedź: {count} wiersz.',
        'Skopiowano odpowiedź: {count} wiersze.',
        'Skopiowano odpowiedź: {count} wierszy.',
    ],
    'clipboard.copied.path' => 'Skopiowano ścieżkę: {path}',
    'clipboard.copied.question' => 'Skopiowano treść pytania.',
    'clipboard.nothing' => 'Nie ma czego skopiować — zaznacz treść myszą albo stań na wpisie.',
    'clipboard.no-target' => 'Nie ma gdzie wkleić — schowek trafia do pola tekstowego z ogniskiem.',
    'clipboard.empty' => 'Schowek jest pusty.',
    'clipboard.unreachable' => 'Ten terminal nie oddaje zawartości schowka.',
    'clipboard.problem.empty' => 'Nie ma czego położyć w schowku.',
    'clipboard.problem.too-long' => 'Treść jest za długa dla schowka terminala — nic nie skopiowano.',
    'clipboard.problem.unavailable' => 'Schowek jest w tym trybie nieosiągalny.',

    // Nazwy miejsc, w których staje ognisko na ekranach rdzenia (krok 40).
    'settings.focus.tabs' => 'Zakładki',
    'settings.focus.item' => 'Pozycja',
    'settings.focus.action' => 'Czynność',
    'settings.focus.edit' => 'Edycja',

    // Zakładka modułu w oknie pomocy — nagłówki części składanej z deklaracji.
    'help.module.shortcut' => 'Skrót',
    'help.module.open' => 'otwórz okno modułu',
    'help.module.keys' => 'Klawisze okna',
    'help.module.settings' => 'Ustawienia',

    // Okno komend (krok 19).
    'command.key.run' => 'uruchom komendę',
    'command.key.complete' => 'uzupełnij nazwę, przy pustym wierszu: tryb',
    'command.key.complete.short' => 'uzupełnij lub tryb',
    'command.key.pick' => 'wybór z listy',
    'command.key.close' => 'zamknij okno',
    'command.key.caret' => 'ruch karetki w wierszu',
    'command.key.erase' => 'kasowanie znaku',
    'command.key.dismiss' => 'zamknij okno',

    // Słownik zdarzeń rdzenia (krok 46) — nazwy widoczne w oknie odbiorcy.
    // Napisy mówią, **co się stało**, a nie co ma zagrać: rdzeń publikuje
    // momenty, a nie dźwięki.
    'event.core.message.info' => 'Komunikat: powodzenie',
    'event.core.message.warning' => 'Komunikat: ostrzeżenie',
    'event.core.message.error' => 'Komunikat: błąd',
    'event.core.overlay.opened' => 'Otwarcie okna',
    'event.core.command.executed' => 'Wykonanie komendy',

    // Menu kontekstowe (krok 32) — drugie wejście do rejestru komend.
    'menu.title' => 'DZIAŁANIA',
    'menu.empty' => 'Dla zaznaczenia nie ma żadnego działania.',
    'menu.key.run' => 'wykonaj działanie',
    'menu.key.pick' => 'wybór z listy',
    'menu.key.close' => 'zamknij menu',

    // Okno potwierdzenia (krok 28).
    'confirm.title' => 'PYTANIE',
    'confirm.title.dangerous' => 'UWAGA',
    'confirm.yes' => 'Tak',
    'confirm.no' => 'Nie',
    'confirm.key.move' => 'zmień odpowiedź',
    'confirm.key.answer' => 'potwierdź',
    'confirm.key.refuse' => 'odmów',

    // Okno wpisywania nazwy i okno postępu (krok 41). Oba są rdzeniowe, bo poproszą
    // o nie także kroki 42 i 44 — o plikach nie wiedzą nic.
    'prompt.name' => 'nazwa: ',
    'prompt.key.accept' => 'zatwierdź wpisaną nazwę',
    'prompt.key.accept.short' => 'zatwierdź',
    'prompt.key.cancel' => 'porzuć wpisywanie',
    'prompt.key.cancel.short' => 'porzuć',
    'progress.counter' => '{done} z {total}',
    'progress.key.cancel' => 'przerwij pracę',
    'progress.key.cancel.short' => 'przerwij',

    // Okno wpisywania ścieżki i okno wyboru (krok 42). Okno nazwy dostało drugą
    // etykietę pola, bo katalog docelowy nie jest nazwą wpisu; okno wyboru jest
    // piąte w rdzeniu i o tym, czego dotyczy pytanie, nie wie nic.
    'prompt.path' => 'katalog: ',
    'choice.key.pick' => 'wybór z listy',
    'choice.key.pick.short' => 'wybór',
    'choice.key.answer' => 'odpowiedz',
    'choice.key.answer.short' => 'odpowiedz',
    'choice.key.cancel' => 'wycofaj się',
    'choice.key.cancel.short' => 'wycofaj',
    'command.history' => 'historia',
    'command.clipboard.done' => 'Schowek: gotowe.',
    'command.dump.requested' => 'Zrzut następnej klatki: {file}-prymitywy.txt oraz {file}.png',
    'command.problem.empty' => 'nie wpisano nazwy komendy',
    'command.problem.unknown' => 'nieznana komenda: {name}',
    'command.problem.missing' => 'brakuje argumentu: {argument}',
    'command.problem.extra' => 'komenda {name} przyjmuje najwyżej tyle argumentów: {count}',
    'command.problem.number' => 'argument {argument} ma być liczbą, a jest: {value}',
    'command.rejected.namespace' => 'nazwa spoza własnej przestrzeni',
    'command.rejected.duplicate' => 'nazwa już zajęta',
    'command.rejected' => 'komendy pominięte: {names}',
    'query.problem.empty' => 'nie wpisano nazwy kwerendy',
    'query.problem.unknown' => 'nieznane źródło danych: {name}',
    'query.problem.nested' => 'kwerenda nie może pytać kwerendy',
    'query.problem.failed' => 'źródło danych nie odpowiedziało',
    'query.rejected.namespace' => 'nazwa spoza własnej przestrzeni',
    'query.rejected.duplicate' => 'nazwa już zajęta',
    'query.result.empty' => 'brak danych do pokazania',
    'query.argument.module' => 'moduł',
    'query.argument.pane' => 'panel',
    'query.argument.path' => 'ścieżka',
    'query.core.settings' => 'ustawienia rdzenia wraz z wartościami',
    'query.core.module-settings' => 'ustawienia wskazanego modułu',
    'query.core.modules' => 'moduły: przyjęte, wyłączone i odrzucone',
    'query.core.commands' => 'spis czynności wywoływanych po nazwie',
    'query.core.queries' => 'spis źródeł danych tego uruchomienia',
    'query.core.events' => 'słownik zdarzeń aplikacji',
    'query.core.jobs' => 'prace tłowe: etap, kod wyjścia, rozmiar wypisu',
    'query.core.viewport' => 'rozmiar okna i tor rysowania klatki',
    'query.core.theme' => 'motyw czynny i motywy do wyboru',
    'query.core.language' => 'język czynny i języki do wyboru',
    'query.core.version' => 'wersja aplikacji, PHP i obecność rozszerzeń',
    'query.core.status' => 'ostatni komunikat wraz z tonem',
    'query.core.context' => 'gdzie użytkownik stoi i co ma zaznaczone',
    'command.core.settings' => 'otwórz ustawienia',
    'command.core.help' => 'otwórz pomoc',
    'command.core.quit' => 'zakończ pracę',
    'command.core.clipboard.copy' => 'skopiuj do schowka systemowego',
    'command.core.clipboard.paste' => 'wklej ze schowka systemowego',
    'command.core.dump' => 'zapisz następną klatkę do pliku (prymitywy i obraz)',
    'command.core.fullscreen' => 'włącz lub wyłącz pełny ekran',
    'command.fullscreen.on' => 'Pełny ekran włączony.',
    'command.fullscreen.off' => 'Pełny ekran wyłączony.',
    'command.core.theme' => 'ustaw motyw graficzny',
    'command.core.language' => 'ustaw język interfejsu',
    'command.argument.path' => 'ścieżka pliku (bez rozszerzenia)',
    'command.argument.theme' => 'motyw',
    'command.argument.language' => 'język',
    'help.section.global' => 'Wszędzie',
    'help.tab.keys' => 'Sterowanie',
    'help.tab.about' => 'Aplikacja',
    'help.about.version' => 'Wersja',
    'help.about.renderer' => 'Tryb renderowania',
    'help.about.scale' => 'Gęstość wyświetlacza',
    'help.settings.location' => 'Ustawienia zapisywane są w pliku:',

    // Wpis pulpitu (bin/install-desktop-entry). Ikona okna idzie tą drogą, bo
    // rozszerzenie PHP-GLFW nie wystawia `glfwSetWindowIcon` (krok 37).
    'desktop.comment' => 'Menadżer plików w terminalu i w oknie',
    'desktop.written' => 'Zapisano: {path}',
    'desktop.hint' => 'Gotowe. Ikona pojawi się na pasku zadań przy najbliższym uruchomieniu '
        . 'z flagą --window; niektóre pulpity odświeżają spis programów dopiero po ponownym '
        . 'zalogowaniu.',
    'desktop.problem.home' => 'BŁĄD: nie znam katalogu domowego (zmienna HOME jest pusta).',
    'desktop.problem.executable' => 'BŁĄD: nie znalazłem pliku bin/light-manager obok tego skryptu.',
    'desktop.problem.directory' => 'BŁĄD: nie udało się utworzyć katalogu {path}.',
    'desktop.problem.file' => 'BŁĄD: nie udało się zapisać pliku {path}.',

    // Budowa dystrybucji (bin/build-phar, `make build`). Zasoby zostają **obok**
    // archiwum, bo silnik audio jest rozszerzeniem C i spod `phar://` nie czyta.
    'build.step.stage' => 'Kompletuję zawartość dystrybucji…',
    'build.step.deps' => 'Instaluję zależności bez deweloperskich…',
    'build.step.phar' => 'Składam archiwum…',
    'build.step.assets' => 'Kopiuję zasoby obok archiwum…',
    'build.step.smoke' => 'Sprawdzam, czy wynik się ładuje…',
    'build.done' => 'Gotowe: {path} ({size} MB)',
    'build.assets' => 'Zasoby: {path}',
    'build.hint.track' => 'Utwór w zbudowanej aplikacji wskazuje się ścieżką bezwzględną '
        . '(ustawienia → zakładka „Dźwięk” → Utwór), np. {path}/… — ścieżka względna '
        . 'liczy się od korzenia projektu, którego w dystrybucji nie ma.',
    'build.problem.readonly' => 'BŁĄD: budowa archiwum wymaga zapisu do PHAR-ów. '
        . 'Uruchom „make build” albo „php -d phar.readonly=0 bin/build-phar”.',
    'build.problem.install' => 'BŁĄD: instalacja zależności dystrybucji nie powiodła się.',
    'build.problem.smoke' => 'BŁĄD: zbudowane archiwum się nie ładuje. {details}',

    // Konfiguracja. Liczba odrzuconych kluczy odmienia zdanie, stąd trzy formy.
    'config.rejected' => [
        'Konfiguracja: {keys} — wartość spoza zakresu, użyto domyślnej.',
        'Konfiguracja: {keys} — wartości spoza zakresu, użyto domyślnych.',
        'Konfiguracja: {keys} — wartości spoza zakresu, użyto domyślnych.',
    ],
    'config.unreadable' => 'Nie udało się wczytać "{path}" — użyto ustawień domyślnych.',
    'config.save.directory' => 'Nie można utworzyć katalogu konfiguracji "{path}".',
    'config.save.file' => 'Nie można zapisać pliku konfiguracji "{path}".',
    'config.save.encoding' => 'Nie udało się zbudować treści pliku konfiguracji.',

    // Moduły (krok 20). Powody odrzucenia są kluczami rejestru — jedno źródło
    // dla paska stanu przy starcie i dla spisu w zakładce „Moduły”.
    'module.rejected.id' => 'nieprawidłowy identyfikator',
    'module.rejected.duplicate' => 'identyfikator już zajęty',
    'module.rejected.character' => 'skrót spoza dozwolonych liter',
    'module.rejected.taken' => 'skrót zajęty przez inny moduł',
    'module.rejected' => [
        'Moduł pominięty: {modules}',
        'Moduły pominięte: {modules}',
        'Moduły pominięte: {modules}',
    ],
    'module.lang.ignored' => [
        'Napis modułu pominięty — klucz poza przedrostkiem modułu: {keys}',
        'Napisy modułów pominięte — klucze poza przedrostkiem modułu: {keys}',
        'Napisy modułów pominięte — klucze poza przedrostkiem modułu: {keys}',
    ],
    'module.setting.invalid' => 'Wartość odrzucona — „{name}” nie przyjmuje tego, co wpisano.',
    'module.restart' => 'Zmiana zadziała po ponownym uruchomieniu.',
    'module.startup.unknown' => 'Nie ma modułu „{module}” — otwarto przeglądarkę plików.',
    'module.startup.disabled' => 'Moduł „{module}” jest wyłączony — otwarto przeglądarkę plików.',
    'module.startup.rejected' => 'Moduł „{module}” został odrzucony przy starcie — otwarto przeglądarkę plików.',
    'module.startup.screenless' => 'Moduł „{module}” nie wnosi własnego okna — otwarto przeglądarkę plików.',

    // Problemy pokazywane użytkownikowi — pasek stanu i wyjście awaryjne.
    'problem.terminal.notInteractive' => 'Standardowe wejście nie jest terminalem — menadżer plików '
        . 'wymaga interaktywnej sesji (bez przekierowania z pliku lub potoku).',
    'problem.terminal.missingPcntl' => 'Rozszerzenie PHP "pcntl" nie jest dostępne — bez obsługi sygnałów '
        . 'nie da się zagwarantować przywrócenia terminala po przerwaniu procesu.',
    'problem.terminal.disabledExec' => 'Funkcja exec() jest wyłączona — bez niej nie da się wywołać "stty" '
        . 'i przełączyć terminala w tryb surowy.',
    'problem.terminal.stty' => 'Nie udało się przełączyć terminala w tryb surowy: {detail}',
    'problem.missingImagick' => 'BŁĄD: rozszerzenie PHP "imagick" nie jest załadowane — bez niego nie da się '
        . 'zbudować klatki ekranu.',
    'problem.missingGlfw' => 'BŁĄD: rozszerzenie PHP "glfw" nie jest załadowane — bez niego tryb okienkowy '
        . '(--window) nie może wystartować. Instalacja: https://phpgl.net',
    'problem.glfw.init' => 'Nie udało się uruchomić GLFW — sprawdź, czy sesja ma dostęp do serwera wyświetlania.',
    'problem.glfw.window' => 'Nie udało się otworzyć okna z kontekstem OpenGL 3.3 core — sprawdź sterowniki grafiki.',
    'problem.glfw.font' => 'Nie znaleziono żadnego fontu o stałej szerokości — tryb okienkowy nie ma czym rysować tekstu.',
    'problem.unexpected' => 'Nie udało się wykonać tej operacji.',

    // Niepowodzenia czynności zmieniających dysk (krok 41). Zdania są rdzeniowe, bo
    // rdzeń ma port zapisu — ale mówią wyłącznie o **nazwie**, bo tyle rdzeń wie.
    'problem.fileops.missing' => 'Wpisu „{name}” już nie ma.',
    'problem.fileops.taken' => 'Nazwa „{name}” jest już zajęta.',
    'problem.fileops.denied' => 'Brak uprawnień do zmiany „{name}”.',
    'problem.fileops.notEmpty' => 'Katalog „{name}” nie jest pusty.',
    'problem.fileops.failed' => 'Nie udało się wykonać czynności na „{name}”: {detail}',

    // Niepowodzenia kopiowania i przenoszenia (krok 42). Trzy pierwsze zamykają
    // trzy drogi do pętli nieskończonej — i mówią, którą z nich zamknęły.
    'problem.transfer.noTarget' => 'Nie ma katalogu „{name}”.',
    'problem.transfer.sameDirectory' => '„{name}” już leży w tym katalogu.',
    'problem.transfer.intoItself' => 'Nie można skopiować „{name}” do wnętrza samego siebie.',
    'problem.transfer.targetDirectory' => 'W celu jest niepusty katalog „{name}” — usuń go najpierw.',
    'problem.transfer.unreadable' => 'Nie można odczytać „{name}”.',

    // Okno trybu okienkowego (krok 34).
    'window.title' => 'Light Manager',

    // Praca tłowa (krok 26). Powody należą do rdzenia, bo mówią o procesie,
    // a nie o tym, po co go uruchomiono — moduł dokłada do nich własne.
    'process.unavailable' => 'Uruchamianie procesów jest w tym środowisku wyłączone.',
    'process.failed' => 'Nie udało się uruchomić procesu.',
    'process.timedOut' => 'Praca w tle przekroczyła limit {seconds} s i została przerwana.',
    'process.tooMany' => 'W tle trwa już {limit} prac naraz — ta nie została uruchomiona.',

    // Narzędzie pomiarowe `bin/render-bench` (krok 16). Napisy narzędzia idą
    // przez katalog jak reszta interfejsu — ale treść mierzonych klatek już nie,
    // bo jej długość w znakach jest częścią pomiaru (patrz `ScenarioFactory`).
    'bench.report.title' => 'Pomiar potoku renderowania',
    'bench.report.title.loop' => 'Pomiar taktu pętli (bez renderera i bez przesyłu)',
    'bench.report.config' => 'Konfiguracja: {config}',
    'bench.report.environment' => 'Środowisko: PHP {php} · {imagick} · font {font}',
    'bench.report.load' => 'Obciążenie maszyny: {load} na rdzeń.',
    'bench.report.loadNoisy' => 'Obciążenie maszyny: {load} na rdzeń — MASZYNA BYŁA ZAJĘTA. '
        . 'Liczby mogą opisywać sąsiada, a nie kod.',
    'bench.report.loadUnknown' => 'Obciążenie maszyny: nieznane (system go nie podaje).',
    'bench.report.iterations' => 'Przebiegi: {iterations} mierzonych, {warmup} na rozgrzewkę '
        . '(podana mediana, obok rozrzut min–max).',
    'bench.report.unstableNote' => 'Wiersze oznaczone „!” miały rozrzut większy niż {ratio}× — '
        . 'te liczby są niewiarygodne i nie trafią do wzorca.',
    'bench.report.coldNote' => 'Kolumna „Zimna” to PIERWSZA klatka rozgrzewki — pojedyncza próbka, '
        . 'nie mediana.' . "\n"
        . '  Puste są w niej pamięci podręczne klatki (wiersze, płaszczyzna spodnia, miniatura); '
        . 'proces, font' . "\n"
        . '  i singletony są już ciepłe. Tyle płaci start aplikacji i każda zmiana rozmiaru okna. '
        . 'Kolumna' . "\n"
        . '  wchodzi do wzorca, ale NIE podnosi alarmu regresji — rozrzut jednej próbki jest większy '
        . 'niż próg.',

    'bench.column.scenario' => 'Scenariusz',
    'bench.column.draw' => 'Rysowanie',
    'bench.column.quantize' => 'Kwantyzacja',
    'bench.column.encode' => 'Kodowanie',
    'bench.column.swap' => 'Bufory',
    'bench.column.total' => 'Razem',
    'bench.column.cold' => 'Zimna',
    'bench.column.input' => 'Wejście',
    'bench.column.state' => 'Stan',
    'bench.column.primitives' => 'Prymitywy',
    'bench.column.compose' => 'Złożenie',
    'bench.column.spread' => 'Rozrzut',
    'bench.column.blob' => 'Blob',
    'bench.column.memory' => 'Pamięć',

    'bench.scenario.empty' => 'puste płótno',
    'bench.scenario.text' => 'sam tekst',
    'bench.scenario.chrome' => 'same ramki',
    'bench.scenario.chrome-text' => 'ramki z tekstem',
    'bench.scenario.selection' => 'zaznaczenie',
    'bench.scenario.scrollbar' => 'suwak',
    'bench.scenario.thumbnail' => 'klatka z miniaturą',
    'bench.scenario.popup' => 'klatka z okienkiem',
    'bench.scenario.command' => 'okno komend',
    'bench.scenario.sections' => 'zwijane sekcje',
    'bench.scenario.progress' => 'paski postępu',
    'bench.scenario.split' => 'klatka podzielona',
    'bench.scenario.background' => 'klatka z pracą w tle',
    'bench.scenario.background-many' => 'klatka z kompletem prac w tle',
    'bench.scenario.columns' => 'lista w kolumnach',
    'bench.scenario.text-view' => 'podgląd tekstu',
    'bench.scenario.highlight' => 'lista z podświetleniem',
    'bench.scenario.settings' => 'ekran ustawień',
    'bench.scenario.tree' => 'drzewo katalogów',
    'bench.scenario.marked' => 'lista z zaznaczeniem',
    'bench.scenario.marquee' => 'prostokąt zaznaczenia',
    'bench.scenario.environments' => 'spis środowisk Dockera',

    'bench.transfer.title' => 'Przesył klatki do terminala',
    'bench.transfer.blob' => '  rozmiar klatki:     {kilobytes} kB',
    'bench.transfer.write' => '  czas zapisu:        {milliseconds} ms (min {minimum}, maks {maximum})',
    'bench.transfer.chunks' => '  iteracje fwrite():  {chunks}',
    'bench.transfer.throughput' => '  przepustowość:      {throughput} kB/s',
    'bench.transfer.roundTrip' => '  odpowiedź DA1 po:   {milliseconds} ms — wartość PRZYBLIŻONA: '
        . 'terminal może odpowiedzieć, zanim domaluje obraz.',
    'bench.transfer.roundTripMissing' => '  odpowiedź DA1:      brak w oknie czasowym — nie zmierzono.',
    'bench.transfer.skippedNoTerminal' => 'Fazy przesyłu NIE ZMIERZONO: wejście lub wyjście nie jest '
        . 'terminalem. Uruchom pod prawdziwym terminalem, np. ./bin/run-render-bench.sh --transfer',
    'bench.transfer.skippedNoSixel' => 'Fazy przesyłu NIE ZMIERZONO: ten terminal nie zgłasza obsługi '
        . 'Sixela, więc klatka nie miałaby gdzie się wyświetlić.',

    'bench.compare.title' => 'Porównanie z wzorcem {file}',
    'bench.compare.baseline' => 'Wzorzec',
    'bench.compare.current' => 'Teraz',
    'bench.compare.change' => 'Zmiana',
    'bench.compare.clean' => 'Bez regresji powyżej progu.',
    'bench.compare.regressions' => [
        'Regresja w {count} scenariuszu (oznaczona ▲).',
        'Regresje w {count} scenariuszach (oznaczone ▲).',
        'Regresje w {count} scenariuszach (oznaczone ▲).',
    ],
    'bench.compare.load' => 'Obciążenie maszyny: wzorzec {baseline} / teraz {current} (na rdzeń). '
        . 'Różne obciążenie = różne środowisko, nie różnica kodu.',
    'bench.compare.incomparable' => 'Wzorzec powstał przy innej konfiguracji, więc porównanie nic by nie '
        . 'znaczyło.' . "\n" . '  wzorzec: {baseline}' . "\n" . '  teraz:   {current}',

    'bench.image.title' => 'Porównanie zrzutów z wzorcami (próg {threshold} ‰ pikseli)',
    'bench.image.column.pixels' => 'Różne piksele',
    'bench.image.column.share' => 'Udział',
    'bench.image.column.verdict' => 'Werdykt',
    'bench.image.verdict.match' => 'zgodny',
    'bench.image.verdict.differs' => 'NIEZGODNY — obraz różnicy niżej',
    'bench.image.verdict.missing' => 'brak wzorca — zapisz: --png-save',
    'bench.image.verdict.resized' => 'inny rozmiar płótna niż wzorzec',
    'bench.image.verdict.incomparable' => 'wzorzec z innej konfiguracji — nieporównywalny',
    'bench.image.saved' => 'Wzorcowy zrzut zapisany: {file}',
    'bench.golden.saved' => 'Złota klatka zapisana: {file}',

    'bench.save.done' => 'Wzorzec zapisany: {file}',
    'bench.save.refusedUnstable' => 'Wzorca NIE ZAPISANO: część pomiarów była niestabilna. '
        . 'Zamknij inne programy i powtórz przebieg.',
    'bench.save.noisyLoad' => 'UWAGA: obciążenie maszyny wynosiło {load} na rdzeń (próg {limit}). '
        . 'Wzorzec zostanie zapisany,' . "\n"
        . '  ale zapamiętaj, że powstał na zajętym hoście — w kroku 22 taka para wzorców '
        . '„potaniała” o 8–18%' . "\n" . '  bez jednej zmiany w kodzie.',
    'bench.snapshot.saved' => 'Zrzut płótna ({scenario}, przed kwantyzacją) zapisany: {file}',
    'bench.progress.running' => 'Mierzę {scenarios} scenariuszy po {iterations} przebiegów…',

    'bench.help.usage' => 'Użycie: ./bin/render-bench [opcje]' . "\n\n"
        . '  Mierzy potok renderowania klatki: rysowanie, kwantyzację i kodowanie do Sixela,' . "\n"
        . '  osobno dla każdego scenariusza. Bez argumentów mierzy wszystkie scenariusze' . "\n"
        . '  w konfiguracji odtwarzającej punkt odniesienia z kroku 13.',
    'bench.help.axes' => 'Osie konfiguracji:' . "\n"
        . '  --size=1000x600      rozmiar płótna w pikselach (albo --width= i --height=)' . "\n"
        . '  --grid=166x46        siatka znakowa (albo --columns= i --rows=)' . "\n"
        . '  --palette=64         kolory palety Sixela: 16, 32, 64, 128, 256' . "\n"
        . '  --text-aa[=0|1]      wygładzanie tekstu' . "\n"
        . '  --stroke-aa[=0|1]    wygładzanie obrysów' . "\n"
        . '  --theme=grafit       motyw: grafit, nordyk, papier, indygo' . "\n"
        . '  --font=NAZWA         font z listy preferencji (domyślnie wybór automatyczny)' . "\n"
        . '  --iterations=15      liczba mierzonych przebiegów' . "\n"
        . '  --warmup=3           liczba przebiegów rozgrzewkowych',
    'bench.help.modes' => 'Tryby i wyniki:' . "\n"
        . '  --window             zmierz tor okienkowy (OpenGL, okno ukryte) zamiast Sixela' . "\n"
        . '  --text               zmierz tor tekstowy (ANSI, tryb zapasowy) zamiast Sixela' . "\n"
        . '  --loop               zmierz takt pętli (wejście, stan, złożenie klatki) bez renderera' . "\n"
        . '  --scenarios=a,b      zmierz tylko wybrane scenariusze' . "\n"
        . '  --transfer           zmierz też przesył klatki (wymaga prawdziwego terminala)' . "\n"
        . '  --save[=nazwa]       zapisz wzorzec do docs/pomiary/' . "\n"
        . '  --compare[=plik]     porównaj z wzorcem (bez wartości: z najnowszym)' . "\n"
        . '  --threshold=10       próg regresji w procentach' . "\n"
        . '  --png=PLIK           zapisz płótno do PNG zamiast mierzyć' . "\n"
        . '  --scenario=NAZWA     scenariusz do zrzutu PNG' . "\n"
        . '  --png-save           zapisz wzorcowe zrzuty do docs/pomiary/wzorce-png/' . "\n"
        . '  --png-compare        porównaj zrzuty z wzorcami (kod wyjścia 1 przy niezgodności)' . "\n"
        . '  --png-threshold=0.5  próg różnicy w promilach pikseli (domyślnie 0 ‰, w oknie 5 ‰)' . "\n"
        . '  --golden-save        zapisz złote klatki (prymitywy) do tests/Golden/ — PRZECZYTAJ różnicę',
    'bench.help.scenarios' => 'Scenariusze:',

    'bench.problem.emptySampleSet' => 'Pomiar nie dostarczył ani jednej próbki.',
    'bench.problem.unknownScenario' => 'Nieznany scenariusz "{detail}". Lista: ./bin/render-bench --help',
    'bench.problem.unknownTheme' => 'Nieznany motyw "{detail}". Dostępne: grafit, nordyk, papier, indygo.',
    'bench.problem.invalidArgument' => 'Niepoprawny argument "{detail}". Pomoc: ./bin/render-bench --help',
    'bench.problem.baselineUnreadable' => 'Plik "{detail}" nie jest zapisanym wzorcem pomiaru.',
    'bench.problem.baselineMissing' => 'Nie znaleziono wzorca w "{detail}". Zapisz pierwszy: --save',
    'bench.problem.writeFailed' => 'Nie udało się zapisać "{detail}".',
    'bench.problem.terminalUnavailable' => 'Pomiar przesyłu wymaga terminala na wejściu i na wyjściu.',
    'bench.problem.glfwUnavailable' => 'Pomiar toru okienkowego wymaga rozszerzenia "glfw". Instalacja: https://phpgl.net',
];
