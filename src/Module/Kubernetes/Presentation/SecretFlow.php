<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Kubernetes\Application\ClusterActions;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;
use LightManager\Module\Kubernetes\Application\ResourceDetail;
use LightManager\Module\Kubernetes\Infrastructure\SecretPatch;
use LightManager\Presentation\Ui\Overlay\ChoiceOverlay;
use LightManager\Presentation\Ui\Overlay\ConfirmOverlay;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * Odsłanianie i zmiana wartości w sekrecie (krok 52, D91 nr 10).
 *
 * **To jest miejsce, w którym zniesiono punkt „Poza zakresem” planu** („edycja
 * zasobu — aplikacja nie ma edytora tekstu”), i zniesiono go wąsko: zmienia się
 * *wartość pod kluczem*, a nie dowolne pole dowolnego zasobu. Edytora nadal nie
 * ma i nie powstaje — jest okno wyboru i pole tekstowe.
 *
 * Czynności są trzy i **wszystkie idą jednym poleceniem**: `kubectl patch
 * --type=merge`. Klucz z wartością kasuje albo zmienia, klucz z wartością `null`
 * — usuwa. To nie jest sprytna sztuczka, tylko reguła scalającej zmiany, więc
 * dodanie klucza i zmiana wartości to dosłownie ta sama linia kodu.
 *
 * **Wartość wpisuje się polem zamaskowanym** (tryb `TextInput` z kroku 48):
 * hasło wpisywane na oczach sali szkoleniowej jest tym samym problemem, co hasło
 * pokazane w klatce, a maskowanie mamy od kroku 48 za darmo.
 *
 * Okna łączą się **zamianą** (`OverlayOutcome::replace()`, krok 41): stos ma
 * jedno piętro, więc „zamknij i otwórz” musi stać się naraz.
 */
final class SecretFlow
{
    /** Identyfikator odpowiedzi „nowy klucz” — nie może zderzyć się z nazwą klucza. */
    private const NEW_KEY = "\0new";

    public function __construct(
        private readonly ResourceDetail $detail,
        private readonly ClusterActions $actions,
        private readonly TranslatorPort $translator,
    ) {
    }

    /** Czy zasób pod kursorem w ogóle ma czego odsłaniać. */
    public function isSecret(): bool
    {
        return $this->detail->secretSizes() !== [];
    }

    /**
     * Wybór klucza do odsłonięcia.
     *
     * Odsłonięcie dotyczy **jednego klucza naraz** — sekret z pięcioma hasłami
     * pokazany w całości byłby jednym `core.dump`em od kompletu poświadczeń
     * zapisanego na dysk.
     */
    public function reveal(): ?OverlayInterface
    {
        $keys = $this->keyOptions();

        if ($keys === []) {
            return null;
        }

        return new ChoiceOverlay(
            $this->key('secret.reveal.title'),
            [],
            $keys,
            function (string $choice): OverlayOutcome {
                $this->detail->reveal($choice === self::NEW_KEY ? null : $choice);

                return OverlayOutcome::close();
            },
            $this->translator,
        );
    }

    /**
     * Menu zmiany: wartość tekstem, wartość zapisem base64, nowy klucz, skasowanie.
     *
     * Dwie osobne pozycje na wartość zamiast jednej i pytania „czym to jest”
     * — bo pytanie po wpisaniu wydłużyłoby drogę o okno, a odpowiedź i tak trzeba
     * znać **przed** wpisywaniem: base64 wpisuje się z pliku, tekst z głowy.
     */
    public function edit(): ?OverlayInterface
    {
        if (!$this->isSecret()) {
            return null;
        }

        return new ChoiceOverlay(
            $this->key('secret.edit.title'),
            [],
            [
                'text' => $this->key('secret.edit.text'),
                'base64' => $this->key('secret.edit.base64'),
                'add' => $this->key('secret.edit.add'),
                'remove' => $this->key('secret.edit.remove'),
                // Ostatnia odpowiedź jest tą, którą znaczy `Esc` — kontrakt
                // `ChoiceOverlay` z kroku 42.
                'cancel' => $this->key('secret.edit.cancel'),
            ],
            fn (string $choice): OverlayOutcome => $this->dispatch($choice),
            $this->translator,
        );
    }

    private function dispatch(string $choice): OverlayOutcome
    {
        return match ($choice) {
            'text' => $this->chooseKey(encoded: false),
            'base64' => $this->chooseKey(encoded: true),
            'add' => $this->askForNewKey(),
            'remove' => $this->chooseKeyToRemove(),
            default => OverlayOutcome::close(),
        };
    }

    /** Wybór klucza, którego wartość zmieniamy. */
    private function chooseKey(bool $encoded): OverlayOutcome
    {
        $keys = $this->keyOptions();

        if ($keys === []) {
            return OverlayOutcome::close();
        }

        return OverlayOutcome::replace(new ChoiceOverlay(
            $this->key('secret.key.title'),
            [],
            $keys,
            fn (string $key): OverlayOutcome => OverlayOutcome::replace($this->askForValue($key, $encoded)),
            $this->translator,
        ));
    }

    private function chooseKeyToRemove(): OverlayOutcome
    {
        $keys = $this->keyOptions();

        if ($keys === []) {
            return OverlayOutcome::close();
        }

        return OverlayOutcome::replace(new ChoiceOverlay(
            $this->key('secret.remove.title'),
            [],
            $keys,
            fn (string $key): OverlayOutcome => OverlayOutcome::replace($this->confirmRemoval($key)),
            $this->translator,
        ));
    }

    private function askForNewKey(): OverlayOutcome
    {
        return OverlayOutcome::replace(new PromptOverlay(
            $this->key('secret.add.title'),
            [],
            '',
            fn (string $key): OverlayOutcome => trim($key) === ''
                ? OverlayOutcome::close()
                : OverlayOutcome::replace($this->askForValue(trim($key), encoded: false)),
            $this->translator,
            $this->key('secret.add.prompt'),
        ));
    }

    /**
     * Pole na wartość — **zamaskowane**, bo wpisuje się w nie tajemnicę.
     */
    private function askForValue(string $key, bool $encoded): PromptOverlay
    {
        return new PromptOverlay(
            $this->key($encoded ? 'secret.value.base64' : 'secret.value.text'),
            ['key' => $key],
            '',
            fn (string $value): OverlayOutcome => $this->store($key, $value, $encoded),
            $this->translator,
            $this->key('secret.value.prompt'),
            masked: true,
        );
    }

    private function store(string $key, string $value, bool $encoded): OverlayOutcome
    {
        if ($value === '') {
            return OverlayOutcome::close();
        }

        // Zapis base64 sprawdzamy **ściśle**: `base64_decode()` w trybie
        // pobłażliwym przyjąłby zdanie po polsku, wyrzucając z niego wszystko
        // spoza alfabetu — i do klastra trafiłoby coś, czego nikt nie wpisał.
        if ($encoded && !SecretPatch::isBase64($value)) {
            return OverlayOutcome::close(Message::error(
                $this->translator->translate($this->key('secret.value.notBase64')),
            ));
        }

        $reference = $this->detail->reference();

        if ($reference === null) {
            return OverlayOutcome::close();
        }

        $this->actions->patchSecret($reference, $key, $encoded ? $value : SecretPatch::encode($value));

        return OverlayOutcome::close();
    }

    private function confirmRemoval(string $key): ConfirmOverlay
    {
        return new ConfirmOverlay(
            $this->key('secret.remove.confirm'),
            ['key' => $key],
            function () use ($key): OverlayOutcome {
                $reference = $this->detail->reference();

                if ($reference !== null) {
                    $this->actions->patchSecret($reference, $key, null);
                }

                return OverlayOutcome::close();
            },
            $this->translator,
            dangerous: true,
        );
    }

    /**
     * Klucze sekretu jako odpowiedzi okna wyboru.
     *
     * Etykietą jest **sam klucz**, a nie klucz katalogu napisów — i jest to
     * jedyne miejsce w module, gdzie okno dostaje napis wprost. Powód: nazwy
     * kluczy pochodzą z klastra, więc katalogu dla nich nie ma i być nie może.
     *
     * @return array<string, string>
     */
    private function keyOptions(): array
    {
        $options = [];

        foreach (array_keys($this->detail->secretSizes()) as $key) {
            $options[$key] = $key;
        }

        return $options;
    }

    private function key(string $suffix): string
    {
        return 'module.' . KubernetesSettings::ID . '.' . $suffix;
    }
}
