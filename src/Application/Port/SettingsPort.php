<?php

declare(strict_types=1);

namespace LightManager\Application\Port;

use LightManager\Application\Dto\LoadedSettings;
use LightManager\Application\Dto\Settings;

/**
 * Trwałe ustawienia aplikacji.
 *
 * Żadna z metod nie rzuca: ani nieczytelny plik przy starcie, ani nieudany
 * zapis nie mają prawa przerwać pętli głównej. Problem wraca opisem, który
 * warstwa wyżej stawia w pasku stanu — dzięki temu `Application` nie musi znać
 * hierarchii wyjątków infrastruktury, a użytkownik i tak się dowiaduje.
 */
interface SettingsPort
{
    /**
     * Wczytuje konfigurację; brak pliku to normalny stan, nie problem.
     *
     * Nazwy motywów przychodzą z zewnątrz, bo zakres tego jednego klucza zna
     * katalog palet, a nie nośnik konfiguracji. Pusta lista wyłącza sprawdzanie
     * — używa jej wyłącznie wewnętrzny odczyt awaryjny.
     *
     * @param list<string> $themeNames
     */
    public function load(array $themeNames): LoadedSettings;

    /**
     * Ustawienia obowiązujące w tej chwili — bez podawania zakresu i bez
     * komunikatu, bo pyta o nie ten, kto po prostu chce znać wartość.
     *
     * Metoda istniała po stronie usługi od kroku 14 (potrzebowało jej
     * renderowanie); do portu wchodzi w kroku 20, wraz z pierwszym użytkownikiem,
     * który nie ma dostępu do klasy `Infrastructure`: **moduł**. Bez niej moduł
     * czytałby własne ustawienia przez `load([])`, czyli przez metodę o zupełnie
     * innym znaczeniu.
     */
    public function current(): Settings;

    /** @return string|null opis problemu, gdy zapisu nie udało się dokonać */
    public function save(Settings $settings): ?string;

    /** Ścieżka pliku konfiguracyjnego — do pokazania na ekranie pomocy. */
    public function location(): string;
}
