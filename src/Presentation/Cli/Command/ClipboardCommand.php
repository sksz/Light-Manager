<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Command;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Presentation\Cli\InputHandler;
use LightManager\Presentation\Cli\LoopState;

/**
 * `core.clipboard.copy` i `core.clipboard.paste` — schowek jako czynność
 * z nazwą (krok 57, punkt 6 zakresu).
 *
 * **Jedna klasa na dwie komendy**, bo różnią się dokładnie jednym słowem, a nie
 * przebiegiem: obie oddają robotę temu samemu miejscu, w którym stoi obsługa
 * klawisza. To jest ta sama reguła, którą zapisał krok 32 o menu kontekstowym —
 * *czynność mająca dwa wejścia mieszka w jednym miejscu, bo dwie implementacje
 * rozjeżdżają się przy pierwszej poprawce*. Wejściami są tu klawisz (`Alt`+`c`,
 * `Alt`+`v`) i nazwa; miejscem — `InputHandler`, do którego wracamy **udając
 * naciśnięcie**, dokładnie tak, jak krok 55 zamienił kliknięcie w klawisz.
 *
 * Skutkiem czynności jest przez to `CommandOutcome::done()` **bez zdania**: zdanie
 * powiedział już `InputHandler` — o tym, co skopiowano, o pustym schowku albo
 * o tym, że nie ma gdzie wkleić. Drugie zdanie o tym samym byłoby powtórzeniem,
 * a pasek stanu ma jedno miejsce.
 *
 * Wklejenie przez komendę ma jedną cechę, o której trzeba wiedzieć: **okno komend
 * zamyka się razem z wykonaniem**, więc odbiorcą treści jest to, co zostanie na
 * wierzchu po jego zamknięciu. Zwykle nie ma tam pola tekstowego i wtedy komenda
 * kończy się zdaniem „nie ma gdzie wkleić" — co jest odpowiedzią prawdziwą, nie
 * usterką. Komenda istnieje dla drugiego wejścia (menu `F9`) i dla spisu, a nie
 * jako droga wygodniejsza od klawisza.
 */
final class ClipboardCommand implements CommandInterface
{
    public const COPY = 'core.clipboard.copy';

    public const PASTE = 'core.clipboard.paste';

    private function __construct(
        private readonly string $name,
        private readonly string $descriptionKey,
        private readonly KeyPress $press,
        private readonly InputHandler $input,
        private readonly LoopState $state,
        private readonly TranslatorPort $translator,
    ) {
    }

    public static function copy(InputHandler $input, LoopState $state, TranslatorPort $translator): self
    {
        return new self(
            self::COPY,
            'command.core.clipboard.copy',
            KeyPress::alt(InputHandler::COPY_CHARACTER),
            $input,
            $state,
            $translator,
        );
    }

    public static function paste(InputHandler $input, LoopState $state, TranslatorPort $translator): self
    {
        return new self(
            self::PASTE,
            'command.core.clipboard.paste',
            KeyPress::alt(InputHandler::PASTE_CHARACTER),
            $input,
            $state,
            $translator,
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function descriptionKey(): string
    {
        return $this->descriptionKey;
    }

    public function arguments(): array
    {
        return [];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $this->input->handle($this->press, $this->state, $this->state->now());

        $message = $this->state->message();

        // Zdanie już padło i jest w stanie — oddajemy je z powrotem, bo okno
        // komend zamyka się po wykonaniu i pasek stanu dostaje treść od skutku
        // komendy, a nie od tego, co w stanie stało wcześniej.
        return CommandOutcome::done($message ?? Message::info(
            $this->translator->translate('command.clipboard.done'),
        ));
    }
}
