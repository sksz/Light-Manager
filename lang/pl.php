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

    // Ekran ustawień.
    'settings.hints' => '↑↓ ruch · ←→ zmiana · Esc powrót',
    'settings.tab.appearance' => 'WYGLĄD',
    'settings.tab.graphics' => 'GRAFIKA',
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
    'settings.language.auto' => 'Automatyczny',
    'settings.language.pl' => 'Polski',
    'settings.language.en' => 'English',
    'settings.action.restore' => 'Przywróć ustawienia domyślne',
    'settings.restore.confirm' => 'Przywrócić ustawienia domyślne? Obecne przepadną bezpowrotnie.',
    'settings.restore.done' => 'Przywrócono ustawienia domyślne.',
    'settings.restore.unchanged' => 'Ustawienia są już domyślne.',
    'settings.value.yes' => 'tak',
    'settings.value.no' => 'nie',
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
    'help.key.tab' => 'zmiana zakładki',
    'help.key.restore' => 'przywróć ustawienia domyślne',
    'help.key.commands' => 'okno komend',
    'help.key.edit' => 'edycja wartości',
    'help.key.commit' => 'zatwierdź wartość',
    'help.key.cancel' => 'porzuć zmianę',
    'help.key.collapse' => 'zwiń lub rozwiń sekcję',

    // Zakładka modułu w oknie pomocy — nagłówki części składanej z deklaracji.
    'help.module.shortcut' => 'Skrót',
    'help.module.open' => 'otwórz okno modułu',
    'help.module.keys' => 'Klawisze okna',
    'help.module.settings' => 'Ustawienia',

    // Okno komend (krok 19).
    'command.key.run' => 'uruchom komendę',
    'command.key.complete' => 'uzupełnij nazwę',
    'command.key.pick' => 'wybór z listy',
    'command.key.close' => 'zamknij okno komend',
    'command.key.caret' => 'ruch karetki w wierszu',
    'command.key.erase' => 'kasowanie znaku',
    'command.key.dismiss' => 'zamknij okno',

    // Okno potwierdzenia (krok 28).
    'confirm.title' => 'PYTANIE',
    'confirm.title.dangerous' => 'UWAGA',
    'confirm.yes' => 'Tak',
    'confirm.no' => 'Nie',
    'confirm.key.move' => 'zmień odpowiedź',
    'confirm.key.answer' => 'potwierdź',
    'confirm.key.refuse' => 'odmów',
    'command.history' => 'historia',
    'command.problem.empty' => 'nie wpisano nazwy komendy',
    'command.problem.unknown' => 'nieznana komenda: {name}',
    'command.problem.missing' => 'brakuje argumentu: {argument}',
    'command.problem.extra' => 'komenda {name} przyjmuje najwyżej tyle argumentów: {count}',
    'command.problem.number' => 'argument {argument} ma być liczbą, a jest: {value}',
    'command.rejected.namespace' => 'nazwa spoza własnej przestrzeni',
    'command.rejected.duplicate' => 'nazwa już zajęta',
    'command.rejected' => 'komendy pominięte: {names}',
    'command.core.settings' => 'otwórz ustawienia',
    'command.core.help' => 'otwórz pomoc',
    'command.core.quit' => 'zakończ pracę',
    'command.core.theme' => 'ustaw motyw graficzny',
    'command.core.language' => 'ustaw język interfejsu',
    'command.argument.theme' => 'motyw',
    'command.argument.language' => 'język',
    'help.section.global' => 'Wszędzie',
    'help.tab.keys' => 'Sterowanie',
    'help.tab.about' => 'Aplikacja',
    'help.about.version' => 'Wersja',
    'help.about.renderer' => 'Tryb renderowania',
    'help.settings.location' => 'Ustawienia zapisywane są w pliku:',

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

    // Okno trybu okienkowego (krok 34).
    'window.title' => 'Light Manager',

    // Praca tłowa (krok 26). Powody należą do rdzenia, bo mówią o procesie,
    // a nie o tym, po co go uruchomiono — moduł dokłada do nich własne.
    'process.unavailable' => 'Uruchamianie procesów jest w tym środowisku wyłączone.',
    'process.failed' => 'Nie udało się uruchomić procesu.',
    'process.timedOut' => 'Praca w tle przekroczyła limit {seconds} s i została przerwana.',

    // Narzędzie pomiarowe `bin/render-bench` (krok 16). Napisy narzędzia idą
    // przez katalog jak reszta interfejsu — ale treść mierzonych klatek już nie,
    // bo jej długość w znakach jest częścią pomiaru (patrz `ScenarioFactory`).
    'bench.report.title' => 'Pomiar potoku renderowania',
    'bench.report.config' => 'Konfiguracja: {config}',
    'bench.report.environment' => 'Środowisko: PHP {php} · {imagick} · font {font}',
    'bench.report.iterations' => 'Przebiegi: {iterations} mierzonych, {warmup} na rozgrzewkę '
        . '(podana mediana, obok rozrzut min–max).',
    'bench.report.unstableNote' => 'Wiersze oznaczone „!” miały rozrzut większy niż {ratio}× — '
        . 'te liczby są niewiarygodne i nie trafią do wzorca.',

    'bench.column.scenario' => 'Scenariusz',
    'bench.column.draw' => 'Rysowanie',
    'bench.column.quantize' => 'Kwantyzacja',
    'bench.column.encode' => 'Kodowanie',
    'bench.column.swap' => 'Bufory',
    'bench.column.total' => 'Razem',
    'bench.column.spread' => 'Rozrzut',
    'bench.column.blob' => 'Blob',

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
    'bench.scenario.columns' => 'lista w kolumnach',
    'bench.scenario.text-view' => 'podgląd tekstu',
    'bench.scenario.highlight' => 'lista z podświetleniem',

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
    'bench.compare.incomparable' => 'Wzorzec powstał przy innej konfiguracji, więc porównanie nic by nie '
        . 'znaczyło.' . "\n" . '  wzorzec: {baseline}' . "\n" . '  teraz:   {current}',

    'bench.save.done' => 'Wzorzec zapisany: {file}',
    'bench.save.refusedUnstable' => 'Wzorca NIE ZAPISANO: część pomiarów była niestabilna. '
        . 'Zamknij inne programy i powtórz przebieg.',
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
        . '  --scenarios=a,b      zmierz tylko wybrane scenariusze' . "\n"
        . '  --transfer           zmierz też przesył klatki (wymaga prawdziwego terminala)' . "\n"
        . '  --save[=nazwa]       zapisz wzorzec do docs/pomiary/' . "\n"
        . '  --compare[=plik]     porównaj z wzorcem (bez wartości: z najnowszym)' . "\n"
        . '  --threshold=10       próg regresji w procentach' . "\n"
        . '  --png=PLIK           zapisz płótno do PNG zamiast mierzyć' . "\n"
        . '  --scenario=NAZWA     scenariusz do zrzutu PNG',
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
