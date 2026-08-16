<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\ImageList;
use LightManager\Module\Docker\Application\ImageView;
use LightManager\Module\Docker\Domain\ValueObject\Image;
use LightManager\Module\Docker\Presentation\Component\ImageSize;

/**
 * `docker.images` — obrazy znane demonowi.
 *
 * **To jest ta kwerenda, dla której cała Faza XVIII ma trzy kroki zamiast dwóch.**
 * Pytanie z D85 brzmiało: jak moduł Kubernetesa ma wdrożyć obraz zbudowany przez
 * moduł Dockera, skoro reguła 15 zabrania mu sięgnąć do tamtego modułu. Odpowiedź
 * jest tutaj i sprowadza się do napisu: `k8s.deploy-image` pyta **nazwą**
 * `docker.images`, dostaje wiersze i nie wie, kto odpowiedział.
 *
 * Wiersze niosą przez to **nazwę użyteczną w klastrze** (`ghcr.io/…:1.2`), a nie
 * skrót treści: obraz wskazuje się we wdrożeniu etykietą, więc pole `tag` musi
 * być tym, co da się wpisać w `kubectl set image`. Obraz osierocony — bez ani
 * jednej etykiety — oddaje w tym polu **pusty napis**, bo skrótu treści wdrożyć
 * się nie da, a podanie go udawałoby możliwość, której nie ma.
 *
 * Pokolenie liczy odświeżenia listy: `ImageList` wie, kiedy odpowiedź demona
 * przyszła, więc źródło umie powiedzieć, że się zmieniło (D93 nr 1) — a lista
 * obrazów rysuje się co klatkę, dopóki ekran modułu stoi otwarty.
 */
final class ImagesQuery implements QueryInterface
{
    /** Ten sam zapis czasu, co w `browser.entries` i `ssh.entries` (D93 nr 2). */
    private const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly ImageList $images,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return DockerSettings::ID . '.images';
    }

    public function descriptionKey(): string
    {
        return 'module.' . DockerSettings::ID . '.query.images';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return $this->images->revision();
    }

    public function ask(CommandInput $input): QueryResult
    {
        $view = new ImageView(
            $this->images->entries(),
            $this->images->cursor(),
            $this->images->isLoaded(),
            $this->images->problemKey(),
        );
        $translator = $this->translator;

        return QueryResult::owned(DockerSettings::ID, $view, static function () use ($view, $translator): array {
            $rows = [];
            $selected = $view->selected();

            foreach ($view->entries as $image) {
                $rows[] = self::describe($image, $selected !== null && $selected->id->equals($image->id), $translator);
            }

            // Pusta lista dostaje **wiersz z samym etapem** — tak samo, jak
            // w `ssh.entries` i `k8s.resources`, i z tego samego powodu. Moduł
            // pyta demona o obrazy dopiero wtedy, gdy ktoś na nie patrzy (D90
            // nr 7), więc „nie ma obrazów" i „nikt jeszcze nie pytał" wyglądają
            // dla obcego identycznie — a różnią się tym, czy warto coś zrobić.
            // Wykrył to test czynności `k8s.deploy-image`, nie przegląd.
            return $rows === [] ? [[
                'id' => '',
                'tag' => '',
                'tags' => '',
                'size' => '',
                'created' => '',
                'dangling' => false,
                'selected' => false,
                'loaded' => $view->loaded,
                'problem' => $view->problemKey ?? '',
            ]] : $rows;
        });
    }

    /** @return array<string, string|int|bool> */
    private static function describe(Image $image, bool $selected, TranslatorPort $translator): array
    {
        return [
            'id' => $image->id->short(),
            // Pierwsza etykieta, bo nią obraz się wskazuje; **pusto** przy obrazie
            // osieroconym — patrz opis klasy.
            'tag' => $image->tags[0] ?? '',
            'tags' => implode(' ', $image->tags),
            'size' => $image->sizeInBytes === null ? '' : ImageSize::of($translator, $image->sizeInBytes),
            'created' => $image->createdAt === null ? '' : date(self::TIMESTAMP_FORMAT, $image->createdAt),
            'dangling' => $image->isDangling(),
            'selected' => $selected,
            'loaded' => true,
            'problem' => '',
        ];
    }
}
