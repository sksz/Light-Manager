<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Infrastructure;

use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Module\Ssh\Domain\ValueObject\RemotePath;

/**
 * Wiersz polecenia, którym czyta się zdalny katalog (krok 49).
 *
 * Klasa **czysta w całości** — nie uruchamia niczego i niczego nie czyta.
 * Powód jest ten sam, dla którego czysty jest `KnownHostsReader` z kroku 48:
 * to jedyny sposób, żeby budowa polecenia dała się sprawdzić testem **bez ani
 * jednego bajtu w sieci**, a jest to miejsce, w którym pomyłka kosztuje albo
 * cudzy plik, albo drugi uścisk dłoni.
 *
 * **Dlaczego `sftp`, a nie `ssh ls`.** `ls` po drugiej stronie zakłada powłokę
 * POSIX, a serwer SFTP nie musi jej mieć — kontener, na którym sprawdzano ten
 * krok, ma `ForceCommand internal-sftp` i żadnej powłoki do wołania. Wypis
 * `sftp ls -l` składa przy tym **klient**, a nie serwer (sprawdzone: pole
 * dowiązań pokazuje `?`, bo protokół go nie niesie, a właściciel jest zawsze
 * liczbą), więc jego postać nie zależy od tego, co stoi po drugiej stronie.
 *
 * **Wsad idzie przez potok, bo `BackgroundProcessPort` nie umie podać potomkowi
 * wejścia** — granica postawiona świadomie w kroku 26. Stąd `printf … | sftp -b -`
 * zamiast pliku wsadowego: plik trzeba by utworzyć, upilnować i sprzątnąć,
 * a potok kosztuje jeden znak.
 *
 * Cytowań jest **dwa i są różne**: powłoka cytuje `escapeshellarg()`, a `sftp`
 * czyta swój wsad własnym parserem, w którym nazwę z odstępem obejmuje się
 * cudzysłowem, a cudzysłów w nazwie poprzedza odwrotnym ukośnikiem (sprawdzone
 * na żywym serwerze). Pomylenie ich kończy się listowaniem nie tego katalogu,
 * o który prosił użytkownik.
 */
final class SftpCommand
{
    /** Napis, którym `sftp` odpowiada na `pwd`; klient nie tłumaczy go na żaden język. */
    public const WORKING_DIRECTORY_PREFIX = 'Remote working directory: ';

    /**
     * Odczyt katalogu — jedno wywołanie, w którym mieści się wszystko.
     *
     * `null` w miejscu ścieżki znaczy **katalog startowy**: wsad pyta wtedy
     * najpierw `pwd`, a dopiero potem listuje, więc jedno otwarcie kanału
     * odpowiada na oba pytania. Przy zmierzonym koszcie (otwarcie kanału jest
     * o rząd wielkości droższe niż pytanie zadane w jego środku) rozdzielenie
     * tego na dwa wywołania byłoby zapłaceniem dwa razy za to samo.
     */
    public static function listing(
        HostProfile $host,
        ?RemotePath $path,
        bool $includeHidden,
        string $socket,
    ): string {
        // `f` znaczy „nie sortuj”: porządek nadaje `RemoteEntryComparator`
        // regułami języka, a klient sortowałby bajtami — czyli dwa razy tę samą
        // pracę, z gorszym skutkiem.
        $flags = $includeHidden ? '-laf' : '-lf';

        $batch = $path === null
            ? ['pwd', 'ls ' . $flags]
            : ['ls ' . $flags . ' ' . self::quote($path->value)];

        return self::pipeline($batch, $host, $socket);
    }

    /**
     * Pobranie jednego pliku **pod nazwę tymczasową** (krok 50).
     *
     * Jedno wywołanie na plik — rozstrzygnięcie użytkownika ze startu kroku
     * (D89 nr 3) wraz z ceną: otwarcie kanału kosztuje tyle samo, co cały odczyt
     * katalogu. Kupuje za to pytanie o kolizję zadane w naturalnej chwili
     * i niepowodzenie jednego pliku, które nie ubija reszty — wsad `sftp`
     * **przerywa się na pierwszym błędzie** (sprawdzone: kod 1, reszta poleceń
     * nie wykonana).
     *
     * Zatwierdzenie idzie **osobno, po stronie PHP** (`FileOperationsPort`), bo
     * dopiero tam wiadomo, czy potomek skończył pomyślnie.
     */
    public static function download(
        HostProfile $host,
        RemotePath $source,
        string $localTemporary,
        string $socket,
    ): string {
        return self::pipeline(
            ['get ' . self::quote($source->value) . ' ' . self::quote($localTemporary)],
            $host,
            $socket,
        );
    }

    /**
     * Wysłanie jednego pliku: **treść pod nazwę tymczasową, potem zmiana nazwy**.
     *
     * Zatwierdzenie mieści się w tym samym wsadzie i to jest cała odpowiedź na
     * kryterium „przerwanie nie zostawia połówki": przerwany potomek zostawia
     * plik o nazwie, która mówi, czym jest, a nie plik wyglądający na gotowy.
     *
     * **`rename -l`, a nie `rename`** — i to jest linia, której nie wolno tu
     * uprościć. Zwykłe `rename` idzie rozszerzeniem `posix-rename@openssh.com`
     * i **nadpisuje cicho** (sprawdzone: kod 0 na zajętej nazwie); `-l` wymusza
     * `SSH_FXP_RENAME`, które na zajętej nazwie odmawia (kod 1). Nadpisanie ma
     * być skutkiem odpowiedzi użytkownika, a nie właściwością protokołu, więc
     * cel usuwa się **jawnie** — i tylko wtedy, gdy o to poprosił.
     *
     * `-rm` z myślnikiem na początku znaczy w wsadzie „nie przerywaj, jeśli się
     * nie uda": cel mógł w międzyczasie zniknąć, a to nie jest powód, żeby nie
     * dokończyć przesyłu.
     */
    public static function upload(
        HostProfile $host,
        string $localPath,
        RemotePath $temporary,
        RemotePath $target,
        bool $overwrite,
        string $socket,
    ): string {
        $batch = ['put ' . self::quote($localPath) . ' ' . self::quote($temporary->value)];

        if ($overwrite) {
            $batch[] = '-rm ' . self::quote($target->value);
        }

        $batch[] = 'rename -l ' . self::quote($temporary->value) . ' ' . self::quote($target->value);

        return self::pipeline($batch, $host, $socket);
    }

    /** Sprzątanie zdalnej połówki po przerwaniu albo niepowodzeniu (krok 50). */
    public static function remove(HostProfile $host, RemotePath $path, string $socket): string
    {
        return self::pipeline(['rm ' . self::quote($path->value)], $host, $socket);
    }

    /**
     * Cytowanie **dla parsera `sftp`**, nie dla powłoki.
     *
     * Cudzysłów obejmuje całość, a odwrotny ukośnik chroni cudzysłów i sam
     * siebie. Odstęp, apostrof i znaki spoza ASCII nie wymagają niczego więcej —
     * sprawdzone na żywym serwerze nazwami `kat ze spacja`
     * i `cudzysłów"i'apostrof.txt`.
     */
    public static function quote(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    /**
     * `printf … | sftp -b - …` wraz z opcjami wejścia przez gniazdo mistrza.
     *
     * `-o BatchMode=yes` jest tu **konieczne, a nie ozdobne**: gniazdo zamknięte
     * w międzyczasie (sesja zerwana) kazałoby klientowi zestawić połączenie
     * od nowa, a więc zapytać o hasło — na terminalu sterującym, którego potomek
     * nie ma, czyli stanąć do limitu czasu. Z nim odmawia od razu, a moduł
     * pokazuje „sesja zerwana”.
     *
     * **Strumieni nie scalamy — i to jest najważniejsza linia tego pliku.**
     * Pierwsza wersja kończyła polecenie na `2>&1`, wzorem kroku 48, i gubiła
     * przez to dwie trzecie dużego katalogu: `ssh` przy `ControlPath` jest
     * klientem multipleksera, więc **przekazuje swoje deskryptory mistrzowi
     * połączenia**, a ten — obsługując wiele sesji w jednej pętli — ustawia im
     * tryb nieblokujący. Tryb jest własnością **opisu pliku**, więc scalony
     * strumień błędów przenosił go na wyjście `sftp`; odkąd potok się zapełnił
     * (a pętla klatek opróżnia go raz na 33 ms), `write()` zwracał `EAGAIN`,
     * a OpenSSH porzucał tę porcję wypisu i kończył się **kodem zero**.
     * Zmierzone: 130 KB z 419 KB, bez śladu w kodzie wyjścia.
     *
     * Powód niepowodzenia idzie przez to **osobnym strumieniem**, który
     * `BackgroundState` niesie od kroku 49 własnym polem.
     *
     * @param list<string> $batch
     */
    private static function pipeline(array $batch, HostProfile $host, string $socket): string
    {
        $lines = implode(' ', array_map(escapeshellarg(...), $batch));

        return sprintf(
            'printf %s %s | sftp -b - -o %s -o %s -o %s -P %d %s',
            escapeshellarg('%s\n'),
            $lines,
            escapeshellarg('ControlPath=' . $socket),
            escapeshellarg('ControlMaster=no'),
            escapeshellarg('BatchMode=yes'),
            $host->port,
            escapeshellarg($host->target()),
        );
    }
}
