<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application\Port;

/**
 * Manifest sekretu położony na dysku na czas jednego zastosowania (krok 61,
 * etap 3).
 *
 * **Port istnieje, bo `kubectl` nie przyjmuje wejścia.** Reguła 11v unieważniła
 * `apply -f -` już w kroku 52 — potomek uruchamiany rdzeniowym portem pracy
 * tłowej nie dostaje strumienia wejściowego — więc treść manifestu **musi**
 * trafić na dysk, zanim się ją zastosuje. To jedyny powód istnienia tej klasy
 * i cała jej odpowiedzialność.
 *
 * Trzy reguły, których nie wolno tu poluzować:
 *
 * 1. **Prawa `0600` nadane przy tworzeniu**, a nie po nim: plik utworzony
 *    domyślną maską i poprawiony chwilę później jest przez tę chwilę czytelny
 *    dla wszystkich.
 * 2. **Katalog prywatny** — `XDG_RUNTIME_DIR`, a w jego braku
 *    `~/.light-manager` (D102 nr 1); nigdy `/tmp`.
 * 3. **Ginie także po niepowodzeniu.** Kryterium ukończenia kroku mówi to
 *    wprost i mówi tak dlatego, że plik z poświadczeniem zostawiony po błędzie
 *    jest gorszy niż brak sekretu: nikt go nie szuka, bo czynność się nie udała.
 */
interface SecretFilePort
{
    /**
     * Kładzie treść w pliku o prawach `0600` i oddaje jego ścieżkę.
     *
     * @return string ścieżka albo pusty napis, gdy pliku nie udało się założyć
     */
    public function write(string $name, string $content): string;

    /** Kasuje plik; wołane **zawsze**, także po nieudanym zastosowaniu. */
    public function forget(string $path): void;
}
