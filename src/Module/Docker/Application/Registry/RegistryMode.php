<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application\Registry;

/**
 * Co widać w widoku zawartości rejestru (krok 61, etap 2).
 *
 * Postaci są dwie, bo **katalog jest rozszerzeniem opcjonalnym**: `/v2/_catalog`
 * należy do API v2 Dockera, ale specyfikacja OCI go nie wymaga i wielkie
 * rejestry go nie wystawiają. Widok musi więc umieć jedno i drugie — pełen spis
 * tam, gdzie jest, i „podaj nazwę obrazu, pokażę etykiety" wszędzie indziej.
 *
 * `NeedsName` **nie jest stanem błędu** i to jest cała ostrożność tej trójki:
 * rejestr bez katalogu ma nie wyglądać na zepsuty.
 */
enum RegistryMode
{
    /** Spis repozytoriów — rejestr katalog wystawia. */
    case Catalog;

    /** Etykiety jednego obrazu. */
    case Tags;

    /** Katalogu nie ma; czekamy na nazwę obrazu od użytkownika. */
    case NeedsName;
}
