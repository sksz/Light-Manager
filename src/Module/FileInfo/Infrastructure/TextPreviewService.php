<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Infrastructure;

use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\FileInfo\Application\Dto\TextAnchor;
use LightManager\Module\FileInfo\Application\Dto\TextWindow;
use LightManager\Module\FileInfo\Application\Port\TextPreviewPort;

/**
 * Czyta z pliku tyle, ile widać w panelu — i ani bajta więcej.
 *
 * Cała trudność kroku 29 siedzi w tej klasie, a rysowanie jest przy niej
 * drobiazgiem. Plik tekstowy to napis o nieznanej długości, nieznanym kodowaniu
 * i nieznanej zawartości: potrafi mieć wiersz o milionie znaków, bajty spoza
 * kodowania, znaki sterujące i tabulatory, których szerokość zależy od tego,
 * gdzie stoją. Każda z tych rzeczy ma tu prostą odpowiedź:
 *
 * - **Długość** — czytamy okno o budżecie liczonym z geometrii panelu, nie plik.
 *   Pół gigabajta i pół kilobajta kosztują tyle samo, bo `fseek` nie czyta
 *   niczego po drodze.
 * - **Kodowanie** — rozpoznajemy je z nagłówka (`mb_detect_encoding` w trybie
 *   ścisłym) i konwertujemy do UTF-8; brak jednoznacznej odpowiedzi to UTF-8
 *   z podmianą bajtów, których nie da się zdekodować. **UTF-16 i UTF-32 też**,
 *   wraz z całym rachunkiem bajtów: podział na wiersze szuka znaku nowej linii
 *   **w kodowaniu źródła i wyłącznie na granicy jednostki kodowej**, bo w UTF-16
 *   bajty `0A 00` potrafią wypaść w środku pary innych znaków i wzięte za koniec
 *   wiersza rozjechałyby kotwicę o bajt — czyli o pół znaku.
 * - **Znaki sterujące** — dostają widoczny znacznik, bo wiersz, w którym coś
 *   zniknęło bez śladu, kłamie o pliku.
 * - **Tabulator** — rozwija się do **najbliższego przystanku**, a nie do stałej
 *   liczby spacji: inaczej wcięcia w kodzie przestałyby się zgadzać w kolumnach.
 *
 * Usługa pamięta rozpoznanie **jednego** pliku, bo moduł opisuje jeden
 * zaznaczony wpis — ta sama zasada, co w `PreviewEntryUseCase`. Podpisem jest
 * ścieżka wraz z rozmiarem i czasem zmiany, więc plik podmieniony pod tą samą
 * nazwą rozpoznaje się na nowo.
 */
final class TextPreviewService extends AbstractSingleton implements TextPreviewPort
{
    /** Ile bajtów wystarczy, żeby rozstrzygnąć o kodowaniu i o tekstowości. */
    private const SNIFF_BYTES = 4096;

    private const TAB_WIDTH = 4;

    private const MINIMUM_WINDOW_BYTES = 4096;

    /**
     * Górna granica jednego odczytu.
     *
     * Nie ma jej po co przekraczać: budżet liczy się z geometrii panelu, a panel
     * większy niż ćwierć mebibajta znaków nie istnieje. Stała jest tu bezpiecznikiem
     * na wypadek nieprawdopodobnej geometrii, nie regulatorem.
     */
    private const MAXIMUM_WINDOW_BYTES = 256 * 1024;

    /** Znacznik znaku sterującego — konwencja edytorów, nie wymysł tego kroku. */
    private const CONTROL_MARK = '·';

    /**
     * Znacznik kolejności bajtów → kodowanie. **Kolejność ma znaczenie**: BOM
     * UTF-32LE zaczyna się dokładnie tak, jak cały BOM UTF-16LE, więc dłuższe
     * muszą być sprawdzane pierwsze.
     */
    private const BYTE_ORDER_MARKS = [
        "\xFF\xFE\x00\x00" => 'UTF-32LE',
        "\x00\x00\xFE\xFF" => 'UTF-32BE',
        "\xFF\xFE" => 'UTF-16LE',
        "\xFE\xFF" => 'UTF-16BE',
        "\xEF\xBB\xBF" => 'UTF-8',
    ];

    /** Szerokość jednostki kodowej; kodowania spoza tablicy są jednobajtowe. */
    private const UNIT_WIDTHS = [
        'UTF-16LE' => 2,
        'UTF-16BE' => 2,
        'UTF-32LE' => 4,
        'UTF-32BE' => 4,
    ];

    /**
     * Ile jednostek nagłówka musi trzymać wzorzec, żeby uznać plik bez BOM-u za
     * UTF-16. Cztery piąte, a nie wszystkie: polski tekst ma „ż” i „ó”, których
     * jednostki zera nie zawierają.
     */
    private const WIDE_PATTERN_RATIO = 0.8;

    /** Ułamek znaków sterujących w nagłówku, powyżej którego plik uznajemy za binarny. */
    private const BINARY_CONTROL_RATIO = 0.3;

    /**
     * Pierwszy stopień kaskady: rozszerzenie. Rozstrzyga **bez dotykania dysku**
     * i dlatego stoi na początku — lista opisuje formaty, nie moduł.
     */
    private const TEXT_EXTENSIONS = [
        'ada', 'asc', 'asm', 'awk', 'bash', 'bat', 'c', 'cfg', 'cmake', 'conf', 'cpp', 'cs', 'css',
        'csv', 'diff', 'dockerfile', 'editorconfig', 'env', 'go', 'gitignore', 'h', 'hpp', 'htm',
        'html', 'ini', 'java', 'js', 'json', 'jsx', 'kt', 'less', 'lock', 'log', 'lua', 'm4', 'md',
        'neon', 'patch', 'php', 'phtml', 'pl', 'po', 'pot', 'properties', 'ps1', 'py', 'rb', 'rs',
        'rst', 'scss', 'sh', 'sql', 'svg', 'swift', 'tex', 'toml', 'ts', 'tsv', 'tsx', 'txt', 'vim',
        'xml', 'yaml', 'yml', 'zsh',
    ];

    /**
     * Drugi stopień kaskady: opis od polecenia `file`.
     *
     * Wzorzec jest po angielsku i po polsku naraz, bo `file` mówi językiem
     * systemu. Rozstrzyga **wyłącznie twierdząco**: opis, w którym tych słów nie
     * ma, nie znaczy „binarny” — znaczy „nie wiem”, i wtedy pyta się bajtów.
     */
    private const TEXT_DESCRIPTION_PATTERN = '/\b(text|script|source|tekst|skrypt|empty|pusty)\b/i';

    /**
     * Kandydaci rozpoznania kodowania, w kolejności rozstrzygania.
     *
     * UTF-8 stoi pierwszy, bo jego sprawdzenie jest **prawdziwym testem**:
     * sekwencje wielobajtowe albo się układają, albo nie. Kodowanie
     * jednobajtowe przyjmuje dowolne bajty, więc może stać wyłącznie na końcu —
     * jako odpowiedź „to nie jest UTF-8, a skoro tekst, to najpewniej to”.
     */
    private const CANDIDATE_ENCODINGS = ['UTF-8', 'ISO-8859-2'];

    /** Podpis rozpoznanego pliku; `null` — jeszcze niczego nie rozpoznano. */
    private ?string $profiled = null;

    private ?string $refusal = null;

    private string $encoding = 'UTF-8';

    /** Znacznik kolejności bajtów rozpoznanego pliku; `''` — plik go nie ma. */
    private string $bom = '';

    public function refuse(string $path, ?string $description): ?string
    {
        $this->profile($path, $description);

        return $this->refusal;
    }

    public function forward(string $path, TextAnchor $anchor, int $lines, int $characters): TextWindow
    {
        $this->profile($path, null);

        $size = $this->sizeOf($path);
        $start = max(0, min($anchor->byte, $size));
        $buffer = $this->read($path, $start, $this->budget($lines, $characters));

        if ($buffer === null) {
            return TextWindow::problem('module.file-info.preview.unreadable');
        }

        $skipped = 0;

        if ($start === 0 && $this->bom !== '' && str_starts_with($buffer, $this->bom)) {
            $buffer = substr($buffer, strlen($this->bom));
            $skipped = strlen($this->bom);
        }

        $atEnd = $start + $skipped + strlen($buffer) >= $size;

        if (!$atEnd) {
            // Bufor urwany budżetem musi kończyć się na granicy jednostki
            // kodowej — połówka znaku UTF-16 przesunęłaby wyrównanie wszystkiego,
            // co po niej. Ogon pliku zostawiamy jaki jest: jeśli plik kończy się
            // połówką jednostki, to jest cecha pliku, a nie odczytu.
            $buffer = substr($buffer, 0, strlen($buffer) - strlen($buffer) % $this->unitWidth());
        }

        [$pieces, $offsets, $consumed, $completed] = $this->split($buffer, $lines, $atEnd);

        $displayed = [];
        $starts = [];

        foreach ($pieces as $index => $piece) {
            $displayed[] = $this->present($piece, $characters);
            $starts[] = $start + $skipped + $offsets[$index];
        }

        return TextWindow::of(
            $displayed,
            $starts,
            new TextAnchor($start, $anchor->line),
            new TextAnchor(min($size, $start + $skipped + $consumed), $anchor->line + $completed),
            $size,
        );
    }

    public function backward(string $path, TextAnchor $anchor, int $lines, int $characters): TextAnchor
    {
        if ($anchor->byte <= 0) {
            return new TextAnchor();
        }

        $this->profile($path, null);

        $newline = $this->newline();
        $width = $this->unitWidth();
        $budget = $this->budget($lines, $characters);
        $from = max(0, $anchor->byte - $budget);
        $from -= $from % $width;
        $buffer = $this->read($path, $from, $anchor->byte - $from);

        if ($buffer === null) {
            return $anchor;
        }

        // Znak nowej linii kończący wiersz **przed** kotwicą nie należy do
        // żadnego z wierszy, których szukamy — inaczej ostatni z nich wyszedłby
        // pusty i przewinięcie w górę stawałoby o jeden wiersz za wcześnie.
        if (str_ends_with($buffer, $newline)) {
            $buffer = substr($buffer, 0, -strlen($newline));
        }

        // Początki wierszy widoczne w oknie. Początek pliku liczy się tylko wtedy,
        // gdy okno do niego sięgnęło — w przeciwnym razie pierwszy kawałek jest
        // urwany w połowie wiersza i wierszem nie jest.
        $starts = $from === 0 ? [0] : [];
        $at = 0;

        while (($break = $this->findNewline($buffer, $at, $newline)) !== null) {
            $starts[] = $break + strlen($newline);
            $at = $break + strlen($newline);
        }

        $step = min($lines, count($starts));

        if ($step < 1) {
            // Ani jednego znaku nowej linii w całym budżecie: jesteśmy w środku
            // wiersza dłuższego niż okno. Cofamy się o budżet i zostajemy w tym
            // samym wierszu — numer się nie zmienia, bo wiersz się nie zmienił.
            return new TextAnchor($from, $anchor->line);
        }

        return new TextAnchor($from + $starts[count($starts) - $step], max(1, $anchor->line - $step));
    }

    /**
     * Bufor pocięty na wiersze wraz z rachunkiem, ile bajtów i ile **całych**
     * wierszy zostało zużyte.
     *
     * Rozróżnienie „zużyte bajty” i „całe wiersze” nie jest pedanterią: wiersz
     * dłuższy od budżetu zużywa bajty, ale nie kończy się — więc przewinięcie
     * w dół idzie w głąb tego samego wiersza i **numer wiersza nie rośnie**.
     * Bez tego rozdziału zrzut JSON-a w jednym wierszu numerowałby się przy
     * każdym przewinięciu jak nowy wiersz pliku.
     *
     * Trzecia liczona rzecz to **początek każdego wiersza**, w bajtach od
     * początku bufora. Bez niej przewijanie o linijkę panelu musiałoby szukać
     * początku wiersza osobnym odczytem przy każdym naciśnięciu strzałki
     * (poprawka z 2026-08-12).
     *
     * @return array{list<string>, list<int>, int, int}
     */
    private function split(string $buffer, int $lines, bool $atEnd): array
    {
        $newline = $this->newline();
        $length = strlen($buffer);
        $wanted = max(1, $lines);
        $pieces = [];
        $offsets = [];
        $consumed = 0;
        $completed = 0;
        $start = 0;

        while (count($pieces) < $wanted) {
            $break = $this->findNewline($buffer, $start, $newline);

            if ($break !== null) {
                $pieces[] = substr($buffer, $start, $break - $start);
                $offsets[] = $start;
                $consumed += $break - $start + strlen($newline);
                ++$completed;
                $start = $break + strlen($newline);

                continue;
            }

            // Ogon bez znaku nowej linii. Trzy przypadki i każdy inny:
            $tail = substr($buffer, $start, $length - $start);

            if ($tail === '' && $start > 0) {
                // Plik kończył się znakiem nowej linii — pustego wiersza po nim nie ma.
                break;
            }

            if (!$atEnd && $pieces !== []) {
                // Wiersz urwany budżetem, a nie plikiem. Pokazany byłby kłamstwem,
                // więc zostawiamy go następnemu oknu — kotwica staje na jego początku.
                break;
            }

            // Ostatni wiersz pliku bez znaku nowej linii **albo** wiersz dłuższy
            // od całego budżetu. W obu wypadkach pokazujemy, co mamy, i nie
            // liczymy wiersza jako zamkniętego: przewinięcie w dół wejdzie
            // w jego głąb, a numer wiersza nie ma prawa urosnąć.
            $pieces[] = $tail;
            $offsets[] = $start;
            $consumed += strlen($tail);

            break;
        }

        return [$pieces, $offsets, $consumed, $completed];
    }

    /**
     * Najbliższy znak nowej linii **na granicy jednostki kodowej**.
     *
     * Wyrównanie jest tu całą treścią metody i bez niego UTF-16 by się rozjechał:
     * bajty `0A 00`, których szuka się w UTF-16LE, wypadają także w środku pary
     * „znak z zakresu U+0Axx, po nim znak o młodszym bajcie zerowym” — na przykład
     * w tekście gudżarackim obok znaku ramki. Wzięte za koniec wiersza przesunęłyby
     * kotwicę o bajt, czyli o pół znaku, i wszystko dalej byłoby śmieciem.
     */
    private function findNewline(string $buffer, int $from, string $newline): ?int
    {
        $width = $this->unitWidth();
        $at = $from;

        while (true) {
            $found = strpos($buffer, $newline, $at);

            if ($found === false) {
                return null;
            }

            if ($found % $width === 0) {
                return $found;
            }

            $at = $found + 1;
        }
    }

    /** Znak nowej linii w kodowaniu źródła — tym, którego szukamy w bajtach. */
    private function newline(): string
    {
        return match ($this->encoding) {
            'UTF-16LE' => "\x0A\x00",
            'UTF-16BE' => "\x00\x0A",
            'UTF-32LE' => "\x0A\x00\x00\x00",
            'UTF-32BE' => "\x00\x00\x00\x0A",
            default => "\n",
        };
    }

    private function unitWidth(): int
    {
        return self::UNIT_WIDTHS[$this->encoding] ?? 1;
    }

    /**
     * Surowy wiersz zamieniony na to, co widać: UTF-8, rozwinięte tabulatory,
     * oznaczone znaki sterujące i przycięcie do tego, co panel pokaże.
     */
    private function present(string $raw, int $characters): string
    {
        $characters = max(1, $characters);

        // Konwersja i rozwijanie tabulatorów kosztują tyle, ile dostaną — więc
        // dostają wyłącznie to, co ma szansę trafić na ekran. Czterokrotność
        // jest zapasem na znaki wielobajtowe.
        $text = $this->toUtf8(substr($raw, 0, $characters * 4));
        $text = rtrim($text, "\r");
        $text = $this->markControls($this->expandTabs($text));

        return mb_strlen($text) > $characters ? mb_substr($text, 0, $characters) : $text;
    }

    private function toUtf8(string $raw): string
    {
        $previous = mb_substitute_character();
        mb_substitute_character(0xFFFD);

        try {
            if ($this->encoding === 'UTF-8') {
                return mb_scrub($raw, 'UTF-8');
            }

            $converted = mb_convert_encoding($raw, 'UTF-8', $this->encoding);

            // Konwersja odmawia wyłącznie przy kodowaniu, którego `mbstring` nie
            // zna — a nasze pochodzi z jego własnego rozpoznania. Odpowiedź na
            // wszelki wypadek jest ta sama, co przy nierozpoznanym kodowaniu:
            // czytamy jak UTF-8 i podmieniamy, czego nie da się zdekodować.
            return $converted === false ? mb_scrub($raw, 'UTF-8') : $converted;
        } finally {
            mb_substitute_character($previous);
        }
    }

    /** Tabulator do najbliższego przystanku — szerokość zależy od tego, gdzie stoi. */
    private function expandTabs(string $text): string
    {
        if (!str_contains($text, "\t")) {
            return $text;
        }

        $expanded = '';
        $column = 0;

        foreach (mb_str_split($text) as $character) {
            if ($character !== "\t") {
                $expanded .= $character;
                ++$column;

                continue;
            }

            $width = self::TAB_WIDTH - $column % self::TAB_WIDTH;
            $expanded .= str_repeat(' ', $width);
            $column += $width;
        }

        return $expanded;
    }

    private function markControls(string $text): string
    {
        return preg_replace(
            '/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}]/u',
            self::CONTROL_MARK,
            $text,
        ) ?? $text;
    }

    /** Rozpoznanie pliku — raz na plik, nie raz na klatkę. */
    private function profile(string $path, ?string $description): void
    {
        $signature = $this->signatureOf($path);

        if ($this->profiled === $signature) {
            return;
        }

        $this->profiled = $signature;
        $this->encoding = 'UTF-8';
        $this->bom = '';
        $this->refusal = $this->inspect($path, $description);
    }

    /**
     * Kaskada trzech metod ze startu kroku, w kolejności od najtańszej.
     *
     * Dwa stopnie rozstrzygają **wyłącznie twierdząco**, a ostatni zawsze:
     * rozszerzenie i opis od `file` potrafią powiedzieć „to tekst”, ale ich
     * milczenie nic nie znaczy — `README` nie ma rozszerzenia, a `file` bywa
     * nieobecne albo mówi językiem, którego wzorzec nie zna.
     *
     * Przed kaskadą stoi **rozpoznanie kodowania** i to nie jest kwestia
     * porządku: gdyby szło po niej, plik `.txt` w UTF-16 przeszedłby pierwszym
     * stopniem jako tekst i pokazał się jako śmieci, bo czytano by go jak UTF-8.
     * Znacznik kolejności bajtów przerywa kaskadę od razu — BOM jest **dowodem**,
     * że plik jest tekstem, a nie poszlaką, więc podejrzewanie go o binaria
     * (co drugi bajt zerowy!) nie miałoby sensu.
     */
    private function inspect(string $path, ?string $description): ?string
    {
        $sample = $this->read($path, 0, self::SNIFF_BYTES);

        if ($sample === null) {
            return 'module.file-info.preview.unreadable';
        }

        $this->bom = $this->bomOf($sample);

        if ($this->bom !== '') {
            $this->encoding = self::BYTE_ORDER_MARKS[$this->bom];

            return null;
        }

        $wide = $this->wideWithoutBom($sample);

        if ($wide !== null) {
            $this->encoding = $wide;

            return null;
        }

        $this->encoding = $this->encodingOf($sample);

        if ($this->hasTextExtension($path) || $this->describesText($description)) {
            return null;
        }

        return $this->looksBinary($sample) ? 'module.file-info.preview.binary' : null;
    }

    private function hasTextExtension(string $path): bool
    {
        $name = basename($path);
        $dot = strrpos($name, '.');
        $extension = $dot === false || $dot === 0 ? $name : substr($name, $dot + 1);

        return in_array(strtolower($extension), self::TEXT_EXTENSIONS, true);
    }

    private function describesText(?string $description): bool
    {
        return $description !== null && preg_match(self::TEXT_DESCRIPTION_PATTERN, $description) === 1;
    }

    /**
     * Bajt zerowy albo gęstwina znaków sterujących — tak wygląda plik binarny
     * widziany z pierwszych czterech kilobajtów.
     */
    private function looksBinary(string $sample): bool
    {
        if ($sample === '') {
            return false;
        }

        if (str_contains($sample, "\0")) {
            return true;
        }

        $controls = preg_match_all('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $sample);

        return $controls !== false && $controls / strlen($sample) > self::BINARY_CONTROL_RATIO;
    }

    /** Znacznik kolejności bajtów pliku albo `''` — jedyne rozpoznanie bez zgadywania. */
    private function bomOf(string $sample): string
    {
        foreach (array_keys(self::BYTE_ORDER_MARKS) as $bom) {
            if (str_starts_with($sample, $bom)) {
                return $bom;
            }
        }

        return '';
    }

    /**
     * UTF-16 bez znacznika kolejności bajtów, rozpoznany po wzorcu zer.
     *
     * Rozpoznanie jest **umyślnie ciasne**, bo pomyłka w drugą stronę jest tu
     * droga: plik binarny wzięty za UTF-16 wysypałby się na ekran udając tekst.
     * Trzy warunki muszą zajść naraz i każdy odsiewa inną klasę pomyłek:
     *
     * 1. **Żadna jednostka nie jest parą zer.** To odrzuca praktycznie każdy plik
     *    wykonywalny i każdy format z wyrównywanymi nagłówkami — tam ciągi zer są
     *    regułą, a w tekście UTF-16 para zer byłaby znakiem U+0000.
     * 2. **Zera stoją zawsze po tej samej stronie jednostki.** To właśnie odróżnia
     *    kolejność bajtów i zarazem jest wzorcem, którego przypadkowe dane nie
     *    trzymają.
     * 3. **Wzorzec obejmuje cztery piąte jednostek.** Nie wszystkie, bo znak spoza
     *    Latin-1 — „ż” w polskim tekście — zera nie zawiera.
     *
     * UTF-32 bez BOM-u tu nie ma i nie będzie: jest rzadki jak rzadko co, a jego
     * wzorzec (trzy zera na cztery bajty) trafiałby w pliki, które tekstem nie są.
     */
    private function wideWithoutBom(string $sample): ?string
    {
        $units = intdiv(strlen($sample), 2);

        if ($units < 8) {
            return null;
        }

        $little = 0;
        $big = 0;

        for ($at = 0; $at < $units * 2; $at += 2) {
            $first = $sample[$at] === "\0";
            $second = $sample[$at + 1] === "\0";

            if ($first && $second) {
                return null;
            }

            $little += $second ? 1 : 0;
            $big += $first ? 1 : 0;
        }

        $threshold = (int) ($units * self::WIDE_PATTERN_RATIO);

        return match (true) {
            $little > $big && $little >= $threshold => 'UTF-16LE',
            $big > $little && $big >= $threshold => 'UTF-16BE',
            default => null,
        };
    }

    private function encodingOf(string $sample): string
    {
        $detected = mb_detect_encoding($sample, self::CANDIDATE_ENCODINGS, true);

        return $detected === false ? 'UTF-8' : $detected;
    }

    /** @return ?string `null` — pliku nie da się otworzyć albo przeczytać */
    private function read(string $path, int $offset, int $bytes): ?string
    {
        if ($bytes < 1) {
            return '';
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        try {
            if ($offset > 0 && fseek($handle, $offset) !== 0) {
                return null;
            }

            $buffer = fread($handle, $bytes);

            return $buffer === false ? null : $buffer;
        } finally {
            fclose($handle);
        }
    }

    private function budget(int $lines, int $characters): int
    {
        $wanted = max(1, $lines) * max(1, $characters);

        return max(self::MINIMUM_WINDOW_BYTES, min(self::MAXIMUM_WINDOW_BYTES, $wanted));
    }

    private function sizeOf(string $path): int
    {
        $size = @filesize($path);

        return $size === false ? 0 : $size;
    }

    private function signatureOf(string $path): string
    {
        $stat = @stat($path);

        return $stat === false
            ? $path
            : $path . ':' . $stat['size'] . ':' . $stat['mtime'];
    }
}
