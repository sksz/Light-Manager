<?php

declare(strict_types=1);

/*
 * Napisy modułu „Opis pliku” — polski.
 *
 * Plik leży w katalogu modułu, a nie w `lang/` rdzenia, i to jest reguła, nie
 * wygoda: moduł ma dać się dopisać bez dotykania rdzenia, a napisy są jego
 * częścią tak samo jak kod.
 *
 * **Każdy klucz musi zaczynać się od `module.file-info.`** — katalog przyjmuje
 * wyłącznie takie i pomija resztę. Kolizja z kluczem rdzenia jest przez to
 * niemożliwa z konstrukcji, a źródło napisu widać po samej nazwie klucza.
 */

return [
    'module.file-info.name' => 'Opis pliku',
    'module.file-info.description' => 'Pełny obraz stanu wpisu: czym jest, ile zajmuje, do kogo należy '
        . 'i kiedy był ruszany.',

    // Zbiór zaznaczony w przeglądarce (krok 43). Zdanie zastępuje ścieżkę
    // w górnym pasie, gdy kontekst sesji niesie niepuste zaznaczenie — to jest
    // **odbiorca** pojęcia zbioru w rdzeniu (D80, rozstrzygnięcie 1), bez którego
    // rdzeń urósłby o mechanizm bez użytkownika.
    'module.file-info.marked' => [
        'Zaznaczono {count} wpis · razem {size}',
        'Zaznaczono {count} wpisy · razem {size}',
        'Zaznaczono {count} wpisów · razem {size}',
    ],
    // Suma pomija katalogi, bo ich rozmiaru nikt nie zna — i mówi to wprost.
    'module.file-info.marked.dirs' => [
        'Zaznaczono {count} wpis · razem {size} bez {dirs} kat.',
        'Zaznaczono {count} wpisy · razem {size} bez {dirs} kat.',
        'Zaznaczono {count} wpisów · razem {size} bez {dirs} kat.',
    ],

    // Pozycje zakładki ustawień modułu.
    'module.file-info.setting.timeout' => 'Limit czasu polecenia (s)',
    'module.file-info.setting.arguments' => 'Dodatkowe argumenty',
    'module.file-info.setting.timeFormat' => 'Zapis czasu',
    'module.file-info.setting.inode' => 'Pokazuj i-węzeł i dowiązania',
    'module.file-info.setting.checksum' => 'Suma kontrolna sha256',
    'module.file-info.setting.checksumLimit' => 'Limit rozmiaru sumy (MiB)',
    'module.file-info.setting.diskUsage' => 'Zajętość katalogu na dysku (du)',
    'module.file-info.setting.backgroundTimeout' => 'Limit czasu pracy w tle (s)',
    'module.file-info.setting.textPreview' => 'Podgląd treści plików tekstowych',
    'module.file-info.setting.lineNumbers' => 'Numery wierszy w podglądzie',
    'module.file-info.setting.textWrap' => 'Zawijanie wierszy w podglądzie',
    'module.file-info.setting.splitFraction' => 'Szerokość panelu opisu (%)',

    // Nagłówki sekcji.
    'module.file-info.section.identity' => 'TOŻSAMOŚĆ',
    'module.file-info.section.size' => 'ROZMIAR',
    'module.file-info.section.permissions' => 'UPRAWNIENIA',
    'module.file-info.section.times' => 'CZASY',
    // Sekcja wpisu zdalnego (krok 49) — stoi pierwsza, bo odpowiada na pytanie
    // zadawane przed wszystkimi innymi: na co ja właściwie patrzę.
    'module.file-info.section.remote' => 'MIEJSCE',

    // Etykiety wierszy.
    'module.file-info.row.name' => 'Nazwa',
    'module.file-info.row.host' => 'Host',
    'module.file-info.row.remotePath' => 'Katalog zdalny',
    'module.file-info.row.limits' => 'Uwaga',
    'module.file-info.row.kind' => 'Rodzaj',
    'module.file-info.row.content' => 'Zawartość',
    'module.file-info.row.target' => 'Prowadzi do',
    'module.file-info.row.targetState' => 'Cel',
    'module.file-info.row.entries' => 'Wpisów',
    'module.file-info.row.size' => 'Rozmiar',
    'module.file-info.row.sizeExact' => 'Dokładnie',
    'module.file-info.row.blocks' => 'Bloki i-węzła',
    'module.file-info.row.diskUsage' => 'Zajęte na dysku',
    'module.file-info.row.checksum' => 'sha256',
    'module.file-info.row.mode' => 'Prawa',
    'module.file-info.row.owner' => 'Właściciel',
    'module.file-info.row.group' => 'Grupa',
    'module.file-info.row.inode' => 'I-węzeł',
    'module.file-info.row.links' => 'Dowiązań twardych',
    'module.file-info.row.modified' => 'Zmiana treści',
    'module.file-info.row.changed' => 'Zmiana i-węzła',
    'module.file-info.row.accessed' => 'Odczyt',

    // Rodzaje wpisów — nazwy z `lstat`, nie z listy plików.
    'module.file-info.kind.file' => 'plik zwykły',
    'module.file-info.kind.directory' => 'katalog',
    'module.file-info.kind.symlink' => 'dowiązanie symboliczne',
    'module.file-info.kind.block' => 'urządzenie blokowe',
    'module.file-info.kind.character' => 'urządzenie znakowe',
    'module.file-info.kind.fifo' => 'kolejka nazwana',
    'module.file-info.kind.socket' => 'gniazdo',
    'module.file-info.kind.unknown' => 'nieznany',

    'module.file-info.target.exists' => 'istnieje',
    'module.file-info.target.missing' => 'nie istnieje',

    // Właściciel i grupa. Brak nazwy jest informacją o systemie, nie o pliku.
    'module.file-info.principal' => '{name} ({id})',
    'module.file-info.principal.numeric' => '{id} (bez rozszerzenia posix)',

    // Liczby odmieniają zdanie, stąd trzy formy.
    'module.file-info.entries' => ['{count} wpis', '{count} wpisy', '{count} wpisów'],
    'module.file-info.bytes' => ['{count} bajt', '{count} bajty', '{count} bajtów'],

    // Suma kontrolna — liczona po kawałku na klatkę, na żądanie klawiszem.
    'module.file-info.checksum.idle' => '(klawisz s liczy)',
    'module.file-info.checksum.working' => 'liczę sha256',
    'module.file-info.checksum.disabled' => 'Suma kontrolna jest wyłączona w ustawieniach modułu.',
    // Wpis zdalny (krok 49): opis powstaje z kontekstu, bo wpis leży na innej
    // maszynie — a sieć nie pada w rysowaniu klatki.
    'module.file-info.remote.limits' => 'wpis leży na zdalnym hoście — opis pochodzi z listy katalogu',
    'module.file-info.remote.refused' => 'Wpis leży na zdalnym hoście — tej pracy nie da się na nim wykonać.',
    'module.file-info.checksum.notAFile' => 'Sumę kontrolną liczymy tylko dla zwykłych plików.',
    'module.file-info.checksum.tooLarge' => 'Plik przekracza ustawiony limit rozmiaru sumy kontrolnej.',
    'module.file-info.checksum.unreadable' => 'Nie udało się odczytać pliku.',

    // Zajętość katalogu — liczona poleceniem `du` w procesie tłowym (krok 26).
    // Powody wspólne dla każdej pracy tłowej (limit czasu, brak proc_open) idą
    // przez klucze `process.*` rdzenia; tutaj zostaje to, co dotyczy `du`.
    'module.file-info.diskUsage.idle' => '(klawisz d liczy)',
    'module.file-info.diskUsage.working' => 'liczę zajętość',
    'module.file-info.diskUsage.disabled' => 'Liczenie zajętości jest wyłączone w ustawieniach modułu.',
    'module.file-info.diskUsage.notADirectory' => 'Zajętość liczymy tylko dla katalogów — dla pliku mówią '
        . 'o niej bloki i-węzła.',
    'module.file-info.diskUsage.failed' => 'Polecenie „du” nie podało wyniku.',

    // Prawy panel: miniatura, treść pliku tekstowego albo powód, dla którego
    // nie ma ani jednego.
    'module.file-info.preview.none' => '(brak podglądu)',
    'module.file-info.preview.unreadable' => 'Nie udało się odczytać obrazu.',
    'module.file-info.preview.tooLarge' => 'Plik przekracza limit {limit} MB — bez podglądu.',
    'module.file-info.preview.binary' => '(plik binarny — bez podglądu treści)',

    // Zapis „ile temu”.
    'module.file-info.ago.now' => 'przed chwilą',
    'module.file-info.ago.minutes' => ['{count} minutę temu', '{count} minuty temu', '{count} minut temu'],
    'module.file-info.ago.hours' => ['{count} godzinę temu', '{count} godziny temu', '{count} godzin temu'],
    'module.file-info.ago.days' => ['{count} dzień temu', '{count} dni temu', '{count} dni temu'],
    'module.file-info.ago.months' => ['{count} miesiąc temu', '{count} miesiące temu', '{count} miesięcy temu'],
    'module.file-info.ago.years' => ['{count} rok temu', '{count} lata temu', '{count} lat temu'],

    // Treść ekranu.
    'module.file-info.nothing' => '(nie zaznaczono wpisu)',
    'module.file-info.empty' => 'Brak opisu.',
    'module.file-info.execDisabled' => 'Funkcja proc_open() jest wyłączona — nie można uruchomić '
        . 'polecenia „file”.',
    'module.file-info.failed' => 'Polecenie „file” zakończyło się błędem.',
    'module.file-info.failedWith' => 'Polecenie „file” zakończyło się błędem: {detail}',
    'module.file-info.timedOut' => 'Polecenie „file” nie odpowiedziało w ciągu {seconds} s — przerwano.',

    // Komenda modułu.
    'module.file-info.command.show' => 'pokaż opis zaznaczonego wpisu',
    'module.file-info.query.description' => 'pełny opis zaznaczonego wpisu',
    'module.file-info.query.preview' => 'miniatura wpisu albo powód jej braku',
    'module.file-info.query.digest' => 'suma sha256 wraz z etapem liczenia',
    'module.file-info.query.usage' => 'zajętość na dysku wraz z etapem pomiaru',

    // Własna część zakładki pomocy — to, czego z deklaracji wyczytać się nie da.
    'module.file-info.help.checksum' => 'policz sumę kontrolną',
    'module.file-info.help.diskUsage' => 'policz zajętość katalogu',
    'module.file-info.help.scrollPreview' => 'przewiń podgląd o panel',
    'module.file-info.help.scrollLine' => 'przewiń podgląd o linijkę',
    'module.file-info.help.edges' => 'początek i koniec pliku',
    'module.file-info.help.sectionEdges' => 'pierwsza i ostatnia sekcja',
    'module.file-info.help.focus' => 'przejście między opisem a podglądem',
    'module.file-info.help.wrap' => 'zawijanie wierszy w podglądzie',

    // Krótkie opisy dla paska stanu i nazwy obu miejsc ogniska (krok 40). Ten ekran
    // jest jedynym, w którym `↑↓` znaczy po lewej co innego niż po prawej — stąd
    // nazwa miejsca jest tu potrzebniejsza niż gdziekolwiek indziej.
    'module.file-info.help.checksum.short' => 'suma',
    'module.file-info.help.diskUsage.short' => 'zajętość',
    'module.file-info.help.scrollPreview.short' => 'o panel',
    'module.file-info.help.scrollLine.short' => 'linijka',
    'module.file-info.help.edges.short' => 'krańce pliku',
    'module.file-info.help.sectionEdges.short' => 'krańce',
    'module.file-info.help.focus.short' => 'panel',
    'module.file-info.help.wrap.short' => 'zawijanie',
    'module.file-info.focus.sections' => 'Opis',
    'module.file-info.focus.preview' => 'Podgląd',
    'module.file-info.help.enter' => 'Opis dotyczy wpisu zaznaczonego na liście plików — także katalogu.',
    'module.file-info.help.sections' => 'Sekcje zwija się Enterem; suma kontrolna liczy się dopiero po '
        . 'naciśnięciu s, bo czyta cały plik, a zajętość katalogu po naciśnięciu d, bo przechodzi całe '
        . 'drzewo.',
    'module.file-info.help.preview' => 'W prawym panelu stoi treść pliku tekstowego: PgUp i PgDn przewijają '
        . 'ją o panel, Home wraca na początek, a Alt+Z przełącza zawijanie wierszy. Czytany jest wyłącznie '
        . 'widoczny fragment, więc plik o dowolnym rozmiarze otwiera się natychmiast.',
];
