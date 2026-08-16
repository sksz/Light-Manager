<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Application\Dto\OutputShape;
use LightManager\Module\Kubernetes\Domain\ValueObject\ContextName;
use LightManager\Module\Kubernetes\Domain\ValueObject\NamespaceName;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceKind;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceRef;

/**
 * Jedno wywołanie `kubectl` opisane **argumentami, a nie wierszem poleceń**
 * (krok 52).
 *
 * Różnica jest granicą warstw, nie stylem: wiersz poleceń to napis dla powłoki,
 * a powłoka mieszka w `Infrastructure`. Warstwa aplikacji mówi „wypisz pody
 * z tej przestrzeni”, a cytowanie (`escapeshellarg()`), nazwa pliku wykonywalnego
 * i limit czasu doklejane do polecenia należą do usługi. Ten sam podział, co
 * w module Dockera między `ComposeAction` a `ComposeCliService`.
 *
 * **Każde wywołanie zna swój kształt wypisu.** Prawie wszystkie są wynikiem
 * (`-o json` do rozczytania po zakończeniu), ale logi z `-f` są strumieniem
 * i muszą to o sobie powiedzieć **przy zamawianiu**, bo od tego zależy, co
 * bufor rdzenia zrobi po przekroczeniu granicy (`OutputShape`, D91 nr 12).
 *
 * **Wywołanie strumieniowe nie dostaje `--request-timeout`** i jest to jedyny
 * wyjątek od reguły „limit w każdym wywołaniu”. Powód jest wprost z narzędzia:
 * `kubectl logs -f --request-timeout=5s` zamyka strumień po pięciu sekundach,
 * czyli limit żądania zabiłby dokładnie tę pracę, która ma trwać. Limit procesu
 * zostaje — nad strumieniem czuwa on.
 */
final readonly class KubectlCall
{
    /**
     * @param list<string> $arguments argumenty w kolejności; **niecytowane** —
     *                                cytuje je usługa, bo cytowanie jest sprawą
     *                                powłoki
     */
    private function __construct(
        public array $arguments,
        public ?ContextName $context = null,
        public OutputShape $shape = OutputShape::Result,
    ) {
    }

    /**
     * Spis kontekstów wraz z bieżącym — **jedyne wywołanie, które nie potrzebuje
     * klastra**.
     *
     * `config view` czyta plik konfiguracyjny i nic poza nim, więc odpowiada
     * także wtedy, gdy nie ma czego zapytać po sieci. To dlatego stan „nie ma
     * bieżącego kontekstu” daje się narysować, zamiast kończyć się zdaniem
     * „connection refused” — a plan kroku żąda dokładnie tego.
     */
    public static function contexts(): self
    {
        return new self(['config', 'view', '-o', 'json']);
    }

    /**
     * Wersje klienta i serwera.
     *
     * Bez klastra kończy się kodem niezerowym, a mimo to **wypisuje wersję
     * klienta** — dlatego wynik czyta się z wyjścia, a nie z kodu wyjścia.
     */
    public static function version(?ContextName $context): self
    {
        return new self(['version', '-o', 'json'], $context);
    }

    /**
     * Katalog rodzajów zasobów — **jedyne wywołanie modułu oddające tekst**.
     *
     * `api-resources` nie umie JSON-a w kliencie 1.25 (`-o` przyjmuje `wide`
     * i `name`; sprawdzone przy rozstrzyganiu, D91). Tekst rozczytuje
     * `ApiResourcesParser`, a wszystko inne w module idzie `-o json`.
     */
    public static function apiResources(?ContextName $context): self
    {
        return new self(['api-resources', '-o', 'wide', '--no-headers'], $context);
    }

    /** Lista zasobów rodzaju — w przestrzeni nazw albo bez niej, wedle rodzaju. */
    public static function list(ResourceKind $kind, ?NamespaceName $namespace, ?ContextName $context): self
    {
        return new self(
            [...self::addressed($kind, $namespace), '-o', 'json'],
            $context,
        );
    }

    /** Jeden zasób w całości — treść prawego panelu i źródło sekcji. */
    public static function describe(ResourceRef $reference, ?ContextName $context): self
    {
        return new self([...self::pointing('get', $reference), '-o', 'json'], $context);
    }

    /** Ten sam zasób w postaci, w której ogląda się go w dokumentacji. */
    public static function yaml(ResourceRef $reference, ?ContextName $context): self
    {
        return new self([...self::pointing('get', $reference), '-o', 'yaml'], $context);
    }

    /**
     * Logi poda — praca **bez końca**, więc strumień.
     *
     * `--tail` podaje się zawsze: bez niego `-f` zaczyna od pierwszego wiersza,
     * jaki kontener kiedykolwiek napisał, i przy długo żyjącym podzie pierwsze
     * kilkanaście sekund idzie na przewijanie historii.
     */
    public static function logs(ResourceRef $reference, ?string $container, int $tail, ?ContextName $context): self
    {
        $arguments = ['logs', $reference->name, '-f', '--tail=' . $tail];

        if ($reference->namespace !== null) {
            $arguments[] = '-n';
            $arguments[] = $reference->namespace->value;
        }

        if ($container !== null && $container !== '') {
            $arguments[] = '-c';
            $arguments[] = $container;
        }

        return new self($arguments, $context, OutputShape::Stream);
    }

    /**
     * Zastosowanie pliku — **ścieżką, nigdy wejściem standardowym**.
     *
     * `kubectl apply -f -` jest w tym module niewykonalne i nie jest to
     * przeoczenie: rdzeniowy port pracy tłowej **nie podaje potomkowi wejścia**
     * (reguła 11d, granica postawiona świadomie w kroku 26).
     */
    public static function apply(string $path, ?NamespaceName $namespace, ?ContextName $context): self
    {
        $arguments = ['apply', '-f', $path];

        if ($namespace !== null) {
            $arguments[] = '-n';
            $arguments[] = $namespace->value;
        }

        return new self($arguments, $context);
    }

    public static function delete(ResourceRef $reference, ?ContextName $context): self
    {
        return new self(self::pointing('delete', $reference), $context);
    }

    /**
     * Zmiana zasobu w miejscu — droga edycji sekretu (D91 nr 10).
     *
     * `--type=merge` scala podany fragment z tym, co jest; klucz z wartością
     * `null` **kasuje** wpis. Fragment idzie **argumentem** (`-p`), więc i tutaj
     * obowiązuje reguła „potomek nie dostaje wejścia”.
     */
    public static function patch(ResourceRef $reference, string $patch, ?ContextName $context): self
    {
        return new self([...self::pointing('patch', $reference), '--type=merge', '-p', $patch], $context);
    }

    /**
     * Podmiana obrazu kontenera we wdrożeniu (krok 54).
     *
     * `set image` zamiast `patch`, i to jest różnica warta zapisania: `patch`
     * kazałby złożyć fragment dokumentu, w którym kontener wskazuje się
     * **nazwą wewnątrz tablicy** — a scalanie tablic w `--type=merge` podmienia
     * całą tablicę, więc wdrożenie o dwóch kontenerach straciłoby jeden.
     * `set image` zna tę strukturę od środka i zmienia dokładnie jedno pole.
     *
     * Kontener wskazuje się **nazwą**, stąd `k8s.deployments` oddaje wiersz na
     * kontener, a nie na wdrożenie.
     */
    public static function setImage(
        ResourceRef $reference,
        string $container,
        string $image,
        ?ContextName $context,
    ): self {
        // `set image` jest czasownikiem **dwuczłonowym**, więc `pointing()` tu nie
        // wystarcza: składa `<czasownik> <adres>`, a tutaj adres stoi dopiero
        // trzeci. Reszta — przestrzeń nazw — dokłada się tak samo.
        $arguments = ['set', 'image', $reference->address()];

        if ($reference->namespace !== null) {
            $arguments[] = '-n';
            $arguments[] = $reference->namespace->value;
        }

        $arguments[] = $container . '=' . $image;

        return new self($arguments, $context);
    }

    /** Czy wywołanie jest strumieniem — pyta usługa, składając limity. */
    public function isStreaming(): bool
    {
        return $this->shape === OutputShape::Stream;
    }

    /**
     * Czasownik z adresem rodzaju i przestrzenią nazw.
     *
     * @return list<string>
     */
    private static function addressed(ResourceKind $kind, ?NamespaceName $namespace): array
    {
        $arguments = ['get', $kind->address()];

        if ($kind->namespaced && $namespace !== null) {
            $arguments[] = '-n';
            $arguments[] = $namespace->value;
        }

        return $arguments;
    }

    /**
     * Czasownik wskazujący jeden zasób postacią `rodzaj/nazwa`.
     *
     * @return list<string>
     */
    private static function pointing(string $verb, ResourceRef $reference): array
    {
        $arguments = [$verb, $reference->address()];

        if ($reference->namespace !== null) {
            $arguments[] = '-n';
            $arguments[] = $reference->namespace->value;
        }

        return $arguments;
    }
}
