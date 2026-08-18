<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Domain\ValueObject;

use LightManager\Module\Kubernetes\Domain\Exception\InvalidClusterNameException;

/**
 * Miejsce, z którym rozmawia moduł: plik `kubeconfig` **i** kontekst (krok 59).
 *
 * Do kroku 59 miejsce miało jedną współrzędną — nazwę kontekstu — a flaga
 * `--kubeconfig` nie padała nigdzie, choć klient 1.25 ją ma (sprawdzone,
 * D96). Dwa pliki z kontekstem tej samej nazwy mieszałyby przez to dane po
 * cichu. Odtąd **każde** wywołanie `kubectl` niesie obie współrzędne, a para
 * jest jedną wartością, bo połowa miejsca nie wskazuje niczego.
 *
 * **Kontekst bywa pusty i to jest miejsce niepełne, nie błędne**: `config view`
 * pyta sam plik o to, jakie konteksty w nim stoją, więc pyta go **przed**
 * wyborem któregokolwiek. Wywołania do klastra biorą miejsce pełne — pilnuje
 * tego `isTargeted()` w sesji, nie samowalidacja tutaj.
 *
 * Ścieżka przechodzi ten sam odsiew, co nazwy (reguła 11r): wartość zaczynająca
 * się od `-` jest opcją, nie argumentem, i żadne `escapeshellarg()` przed tym
 * nie chroni. Istnienia pliku **nie sprawdzamy** — plik na dysku sieciowym bywa
 * chwilowo nieobecny, a miejsce nie ma przez to przestać istnieć; o brakującym
 * pliku mówi stan modułu, nie samowalidacja.
 */
final readonly class ClusterPlace
{
    private const SUBJECT = 'kubeconfig';

    /** Tyle, ile przyjmują systemy plików z zapasem — granica przeciw śmieciom, nie realna. */
    private const MAXIMUM_LENGTH = 4096;

    private function __construct(
        public string $kubeconfig,
        public ?ContextName $context,
    ) {
    }

    public static function of(string $kubeconfig, ContextName $context): self
    {
        return new self(self::path($kubeconfig), $context);
    }

    /** Sam plik — dla `config view`, czyli pytania zadawanego przed wyborem kontekstu. */
    public static function forFile(string $kubeconfig): self
    {
        return new self(self::path($kubeconfig), null);
    }

    public function equals(self $other): bool
    {
        return $this->kubeconfig === $other->kubeconfig
            && $this->context?->value === $other->context?->value;
    }

    /** Odcisk miejsca — klucz stanu i składnik pokolenia sesji. */
    public function fingerprint(): string
    {
        return $this->kubeconfig . '|' . ($this->context->value ?? '');
    }

    /** Ten sam odsiew dla samej ścieżki — używa go też samowalidacja wpisu książki. */
    public static function path(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidClusterNameException::forEmptyValue(self::SUBJECT);
        }

        if (str_starts_with($trimmed, '-')) {
            throw InvalidClusterNameException::forOptionLike(self::SUBJECT, $trimmed);
        }

        if (strlen($trimmed) > self::MAXIMUM_LENGTH) {
            throw InvalidClusterNameException::forTooLongValue(self::SUBJECT, $trimmed, self::MAXIMUM_LENGTH);
        }

        // Znaki sterujące w ścieżce nie mają czego szukać, a w wypisie
        // wyglądałyby jak nic; biały znak **w środku** jest legalny — katalogi
        // ze spacją istnieją, a cytowanie należy do usługi.
        if (preg_match('/[\p{Cc}\p{Cf}]/u', $trimmed) === 1) {
            throw InvalidClusterNameException::forMalformedValue(self::SUBJECT, $trimmed);
        }

        return $trimmed;
    }
}
