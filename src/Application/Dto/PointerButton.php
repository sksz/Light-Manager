<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

/**
 * Przycisk wskaźnika — trzy pozycje i ani jednej więcej (krok 55).
 *
 * Przycisków bocznych i kółka poziomego w słowniku nie ma, bo nie mają w
 * aplikacji ani jednego odbiorcy (reguła 13). Protokół SGR i GLFW podają je
 * oba; odsiewa je tłumacz toru, a nie ten enum.
 *
 * Kółko **nie jest** tu przyciskiem, choć protokół terminala koduje je bitami
 * przycisku: obrót niczego nie naciska i nie ma zwolnienia, więc mieszka
 * w `PointerAction`, gdzie stoi obok naciśnięcia i przeciągnięcia.
 */
enum PointerButton
{
    case Left;
    case Middle;
    case Right;
}
