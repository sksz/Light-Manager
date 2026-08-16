<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

/** Rodzaj zdania powiedzianego przez budowę (krok 51). */
enum BuildMessageKind
{
    /** Wiersz wypisu — „Step 3/7 : RUN …”, „Downloading”. */
    case Step;

    /** Skrót zbudowanego obrazu — po to była cała praca. */
    case Built;

    /** Powód niepowodzenia; kod HTTP takiej odpowiedzi to mimo wszystko 200. */
    case Failure;
}
