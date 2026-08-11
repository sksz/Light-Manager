<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application\Port;

use LightManager\Module\FileInfo\Application\Dto\ChecksumState;

/**
 * Suma kontrolna liczona **po kawałku, przez wiele klatek**.
 *
 * Kształt portu jest podyktowany jedną rzeczą: pętla główna rysuje klatkę
 * trzydzieści razy na sekundę i nie wolno jej zatrzymać na czas czytania pliku.
 * Stąd nie ma tu metody `checksum(path): string` — jest zaczynanie, posuwanie
 * o zadaną liczbę bajtów i przerywanie.
 *
 * **Jedna praca naraz** i to nie jest ograniczenie techniczne, tylko decyzja:
 * moduł opisuje jeden zaznaczony wpis, a krok 23 postawił dla paska postępu tę
 * samą zasadę — „jeden pasek, jedno miejsce”. Druga praca zaczęta bez przerwania
 * pierwszej byłaby wyciekiem uchwytu do pliku.
 *
 * Portu nie ma dla `du` i to jest świadome: policzenie zajętości katalogu wymaga
 * procesu potomnego doglądanego między klatkami, a ten mechanizm dostał własny
 * krok planu (26). Do tego czasu opis pliku nie pokazuje wiersza „zajęte na
 * dysku” w ogóle — zamiast pokazywać go z wartością, której nie ma jak policzyć.
 */
interface ChecksumPort
{
    /**
     * Zaczyna liczenie od zera. Poprzednia praca — jeśli trwała — zostaje
     * przerwana, bo port prowadzi tylko jedną.
     *
     * @return ChecksumState `Running` z zerowym postępem albo `Failed` z powodem
     */
    public function begin(string $path): ChecksumState;

    /**
     * Czyta kolejny kawałek i oddaje stan po nim. Wywołanie przy pracy, która
     * nie trwa, niczego nie zmienia — ekran nie musi pilnować, czy ma o co pytać.
     *
     * @param int $bytes ile najwyżej bajtów przeczytać w tym wywołaniu
     */
    public function advance(int $bytes): ChecksumState;

    public function state(): ChecksumState;

    /** Przerywa liczenie i zamyka uchwyt pliku. Wolno wołać zawsze. */
    public function stop(): void;
}
