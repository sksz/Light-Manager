<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

use LightManager\Domain\ValueObject\Message;

/**
 * Co skopiować i co o tym powiedzieć (krok 57).
 *
 * Dwa pola, bo pasek stanu ma mówić **co** skopiowano, a nie „skopiowano”:
 * trzy różne źródła, po których naciska się ten sam klawisz, są dla użytkownika
 * nierozróżnialne, dopóki zdanie jest jedno (punkt 5 zakresu kroku).
 *
 * Zdanie przychodzi **gotowe**, a nie kluczem katalogu z liczbą, i jest to
 * rozstrzygnięcie, nie skrót: trzy źródła mówią o trzech różnych rzeczach —
 * o liczbie wierszy, o liczbie nazw i o ścieżce — więc jeden wspólny kształt
 * klucza musiałby albo kłamać przy dwóch z nich, albo urosnąć do tablicy
 * parametrów, której rdzeń nie umiałby wypełnić. Ten, kto zna treść, zna też
 * jej nazwę; a napisu w kodzie tu nie ma, bo `Message` powstaje z katalogu
 * u wołającego (reguła 7).
 */
final readonly class CopyContent
{
    public function __construct(
        /** Treść dla schowka — dokładnie to, co ma się wkleić, bez ozdób. */
        public string $text,
        /** Zdanie do paska stanu, mówiące **co** skopiowano. */
        public Message $announcement,
    ) {
    }
}
