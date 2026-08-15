<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application;

/**
 * Którędy płynie plik (krok 50).
 *
 * Dwie wartości, a nie dwie prace: pobranie i wysłanie różnią się **jednym
 * poleceniem w wsadzie `sftp`** i tym, po której stronie sprawdza się kolizję.
 * Reszta — kolejka, nazwa tymczasowa, zatwierdzenie zmianą nazwy, sprzątanie
 * połówki — jest wspólna, więc rozdzielenie tego na dwie klasy znaczyłoby dwa
 * miejsca, które trzeba poprawiać razem.
 */
enum TransferDirection
{
    /** Z hosta na dysk tej maszyny. */
    case Download;

    /** Z dysku tej maszyny na host. */
    case Upload;

    public function isDownload(): bool
    {
        return $this === self::Download;
    }
}
