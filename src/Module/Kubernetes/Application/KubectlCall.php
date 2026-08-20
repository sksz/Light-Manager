<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Application\Dto\OutputShape;
use LightManager\Module\Kubernetes\Domain\ValueObject\ClusterPlace;
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
 *
 * **Miejsce ma od kroku 59 dwie współrzędne** (plik i kontekst, `ClusterPlace`)
 * i idą one do **każdego** wywołania — także do `config view`, które do tamtego
 * kroku było jedynym „bez miejsca". To właśnie ono ma wypisać zawartość
 * **wskazanego** pliku, więc spis kontekstów daje się odtąd pobrać z każdego,
 * nie tylko z domyślnego.
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
        public ?ClusterPlace $place = null,
        public OutputShape $shape = OutputShape::Result,
        /**
         * Czy dołożyć `--context`. Spis kontekstów pyta **sam plik**, więc
         * kontekst jest tam bez znaczenia — a przy pliku, w którym akurat go
         * nie ma, `--context` zamieniłby odpowiedź w odmowę.
         */
        public bool $withContext = true,
    ) {
    }

    /**
     * Spis kontekstów wskazanego pliku wraz z jego bieżącym — **jedyne
     * wywołanie, które nie potrzebuje klastra**.
     *
     * `config view` czyta plik konfiguracyjny i nic poza nim, więc odpowiada
     * także wtedy, gdy nie ma czego zapytać po sieci. To dlatego stan „nie ma
     * bieżącego kontekstu” daje się narysować, zamiast kończyć się zdaniem
     * „connection refused” — a plan kroku żąda dokładnie tego.
     *
     * **Plik podaje się tu ścieżką, nie miejscem**: pytamy o to, jakie
     * konteksty w nim są, więc żaden nie jest jeszcze wybrany.
     */
    public static function contexts(string $kubeconfig): self
    {
        return new self(
            ['config', 'view', '-o', 'json'],
            ClusterPlace::forFile($kubeconfig),
            withContext: false,
        );
    }

    /**
     * Wersje klienta i serwera.
     *
     * Bez klastra kończy się kodem niezerowym, a mimo to **wypisuje wersję
     * klienta** — dlatego wynik czyta się z wyjścia, a nie z kodu wyjścia.
     */
    public static function version(?ClusterPlace $place): self
    {
        return new self(['version', '-o', 'json'], $place);
    }

    /**
     * Katalog rodzajów zasobów — **jedyne wywołanie modułu oddające tekst**.
     *
     * `api-resources` nie umie JSON-a w kliencie 1.25 (`-o` przyjmuje `wide`
     * i `name`; sprawdzone przy rozstrzyganiu, D91). Tekst rozczytuje
     * `ApiResourcesParser`, a wszystko inne w module idzie `-o json`.
     */
    public static function apiResources(?ClusterPlace $place): self
    {
        return new self(['api-resources', '-o', 'wide', '--no-headers'], $place);
    }

    /** Lista zasobów rodzaju — w przestrzeni nazw albo bez niej, wedle rodzaju. */
    public static function list(ResourceKind $kind, ?NamespaceName $namespace, ?ClusterPlace $place): self
    {
        return new self(
            [...self::addressed($kind, $namespace), '-o', 'json'],
            $place,
        );
    }

    /** Jeden zasób w całości — treść prawego panelu i źródło sekcji. */
    public static function describe(ResourceRef $reference, ?ClusterPlace $place): self
    {
        return new self([...self::pointing('get', $reference), '-o', 'json'], $place);
    }

    /** Ten sam zasób w postaci, w której ogląda się go w dokumentacji. */
    public static function yaml(ResourceRef $reference, ?ClusterPlace $place): self
    {
        return new self([...self::pointing('get', $reference), '-o', 'yaml'], $place);
    }

    /**
     * Logi poda — praca **bez końca**, więc strumień.
     *
     * `--tail` podaje się zawsze: bez niego `-f` zaczyna od pierwszego wiersza,
     * jaki kontener kiedykolwiek napisał, i przy długo żyjącym podzie pierwsze
     * kilkanaście sekund idzie na przewijanie historii.
     */
    public static function logs(ResourceRef $reference, ?string $container, int $tail, ?ClusterPlace $place): self
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

        return new self($arguments, $place, OutputShape::Stream);
    }

    /**
     * Zastosowanie pliku — **ścieżką, nigdy wejściem standardowym**.
     *
     * `kubectl apply -f -` jest w tym module niewykonalne i nie jest to
     * przeoczenie: rdzeniowy port pracy tłowej **nie podaje potomkowi wejścia**
     * (reguła 11d, granica postawiona świadomie w kroku 26).
     */
    public static function apply(string $path, ?NamespaceName $namespace, ?ClusterPlace $place): self
    {
        $arguments = ['apply', '-f', $path];

        if ($namespace !== null) {
            $arguments[] = '-n';
            $arguments[] = $namespace->value;
        }

        return new self($arguments, $place);
    }

    public static function delete(ResourceRef $reference, ?ClusterPlace $place): self
    {
        return new self(self::pointing('delete', $reference), $place);
    }

    /**
     * Zmiana zasobu w miejscu — droga edycji sekretu (D91 nr 10).
     *
     * `--type=merge` scala podany fragment z tym, co jest; klucz z wartością
     * `null` **kasuje** wpis. Fragment idzie **argumentem** (`-p`), więc i tutaj
     * obowiązuje reguła „potomek nie dostaje wejścia”.
     */
    public static function patch(ResourceRef $reference, string $patch, ?ClusterPlace $place): self
    {
        return new self([...self::pointing('patch', $reference), '--type=merge', '-p', $patch], $place);
    }

    /**
     * Dopięcie sekretu rejestru do wdrożenia — **łata strategiczna, nie
     * scalająca** (krok 61, etap 3).
     *
     * Rodzaj łaty jest tu **wynikiem pomiaru, nie założeniem**. Sprawdzone na
     * żywym klastrze (minikube, serwer v1.25.0) na wdrożeniu, które już miało
     * jeden sekret:
     *
     * - łata **strategiczna** (domyślna) dała `[nowy, istniejący]` — **dopisała**;
     * - ta sama łata powtórzona **nie zdublowała** wpisu (klucz scalania po nazwie);
     * - `--type=merge` dał **`[nowy]`** — **skasował istniejący**.
     *
     * Trzeci wiersz jest tą samą pułapką, którą krok 54 zapłacił przy
     * kontenerach, i **lekcja jest ogólniejsza, niż ją wtedy zapisano**:
     * `--type=merge` podmienia **każdą** tablicę, a nie tylko tablicę
     * kontenerów. Rodzaj łaty dobiera się do **pola**, nie do zasobu.
     *
     * Druga zmierzona własność ma skutek w kodzie: skoro powtórzenie nie
     * dubluje, **nie sprawdzamy, czy sekret już jest dopięty** — kod, który by
     * to robił, byłby drugim rachunkiem obok tego, który klaster prowadzi sam.
     */
    public static function addPullSecret(ResourceRef $reference, string $secret, ?ClusterPlace $place): self
    {
        $patch = json_encode([
            'spec' => ['template' => ['spec' => ['imagePullSecrets' => [['name' => $secret]]]]],
        ]);

        return new self(
            [...self::pointing('patch', $reference), '-p', $patch === false ? '{}' : $patch],
            $place,
        );
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
        ?ClusterPlace $place,
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

        return new self($arguments, $place);
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
