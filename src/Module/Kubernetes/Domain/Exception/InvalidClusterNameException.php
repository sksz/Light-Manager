<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Domain\Exception;

use LightManager\Domain\Exception\DescribesProblem;
use LightManager\Domain\Exception\DomainException;

/**
 * Nazwa, której nie da się podać `kubectl`owi (krok 52).
 *
 * Wyjątek jest jeden na cztery obiekty wartości modułu — kontekst, przestrzeń
 * nazw, rodzaj zasobu i wskazanie zasobu — bo wszystkie cztery odsiewają to samo
 * i z tego samego powodu: **każda z tych wartości ląduje w wierszu polecenia
 * procesu potomnego**. Rozdzielenie na cztery klasy dałoby cztery komunikaty
 * mówiące to samo zdanie o innym rzeczowniku.
 *
 * Powód najostrzejszy stoi przy `forOptionLike()` i jest wprost regułą 11r:
 * **wartość zaczynająca się od `-` jest opcją, nie argumentem**, a żadne
 * `escapeshellarg()` przed tym nie chroni — cytowanie broni przed powłoką,
 * nie przed programem, który sam rozbiera swoje argumenty. Namespace nazwany
 * `--all-namespaces` przeszedłby przez cytowanie nietknięty i zmienił znaczenie
 * polecenia.
 */
final class InvalidClusterNameException extends DomainException implements DescribesProblem
{
    /** @param array<string, string> $problemParameters */
    private function __construct(
        string $message,
        private readonly string $problemKey,
        private readonly array $problemParameters,
    ) {
        parent::__construct($message);
    }

    public static function forEmptyValue(string $subject): self
    {
        return new self(
            sprintf('Cluster %s is empty.', $subject),
            'module.k8s.name.empty',
            ['subject' => $subject],
        );
    }

    /**
     * Wartość wyglądająca na opcję wiersza poleceń.
     *
     * Osobny powód, a nie gałąź „zły znak”, bo jest to jedyny przypadek, w którym
     * **poprawnie zbudowany** napis zmienia znaczenie polecenia zamiast być jego
     * argumentem.
     */
    public static function forOptionLike(string $subject, string $value): self
    {
        return new self(
            sprintf('Cluster %s "%s" starts with a dash and would be read as an option.', $subject, $value),
            'module.k8s.name.optionLike',
            ['subject' => $subject, 'value' => $value],
        );
    }

    public static function forMalformedValue(string $subject, string $value): self
    {
        return new self(
            sprintf('Cluster %s "%s" carries characters that do not belong in one.', $subject, $value),
            'module.k8s.name.malformed',
            ['subject' => $subject, 'value' => $value],
        );
    }

    public static function forTooLongValue(string $subject, string $value, int $limit): self
    {
        return new self(
            sprintf('Cluster %s "%s" is longer than %d characters.', $subject, $value, $limit),
            'module.k8s.name.tooLong',
            ['subject' => $subject, 'value' => $value, 'limit' => (string) $limit],
        );
    }

    public function problemKey(): string
    {
        return $this->problemKey;
    }

    public function problemParameters(): array
    {
        return $this->problemParameters;
    }
}
