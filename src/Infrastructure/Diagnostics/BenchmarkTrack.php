<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/**
 * Tor pomiaru — którym z trzech tłumaczy słownika prymitywów idzie klatka.
 *
 * Do kroku 38 tor był **przełącznikiem** (`windowed: bool`), bo tory były dwa.
 * Trzeci (tekstowy) zamienia go w enum, i to nie z upodobania do typów: każdy
 * tor ma inne fazy, więc pytanie „czy okno?” w trzech miejscach tabeli musiałoby
 * stać się pytaniem „a jeśli nie okno, to który z dwóch pozostałych?”.
 *
 * Wyniki torów są **z założenia nieporównywalne** — inne fazy, inna jednostka
 * pracy — i pilnuje tego podpis konfiguracji: wchodzi do niego przyrostek toru,
 * więc `--compare` odmówi zestawienia sixelowego wzorca z tekstowym, zamiast
 * pokazać „regresję” o dwa rzędy wielkości.
 */
enum BenchmarkTrack: string
{
    /** Potok Imagicka: rysowanie → kwantyzacja → kodowanie do Sixela. */
    case Sixel = 'sixel';

    /** Renderer OpenGL w ukrytym oknie GLFW (krok 35). */
    case Window = 'window';

    /** Renderer ANSI — tryb zapasowy, mierzony od kroku 38. */
    case Text = 'text';

    /**
     * Takt pętli **bez renderera** (krok 38): odczyt wejścia → aktualizacja
     * stanu → złożenie klatki.
     *
     * Czwarty tor nie jest czwartym rendererem i tak ma być czytany. Wchodzi
     * jako pomiar **dodatkowy** obok głównego (D64, rozstrzygnięcie nr 1):
     * rozstrzygnięcie nr 5 kroku 16 („mierzymy potok i przesył”) zostaje w mocy
     * dla tabeli głównej, a ten tor odpowiada na pytanie, którego tamta nigdy
     * nie zadała — ile kosztuje **złożenie** klatki, zanim ktokolwiek zacznie ją
     * rysować.
     */
    case Loop = 'loop';

    /**
     * Przyrostek podpisu konfiguracji.
     *
     * Tor sixelowy nie dokłada nic i **tak ma zostać**: wzorce sprzed kroku 35
     * mają podpis bez przyrostka, a zmiana formatu unieważniłaby je wszystkie.
     */
    public function signatureSuffix(): string
    {
        return match ($this) {
            self::Sixel => '',
            self::Window => ' window',
            self::Text => ' text',
            self::Loop => ' loop',
        };
    }

    /**
     * Czy tor ma **fazę środkową**. W torze sixelowym jest nią kwantyzacja,
     * w torze taktu — aktualizacja stanu; okno i tryb tekstowy nie mają żadnej,
     * a kolumna zer nie jest wynikiem pomiaru.
     */
    public function hasMiddlePhase(): bool
    {
        return $this === self::Sixel || $this === self::Loop;
    }

    /** Nazwa pierwszej fazy: w torze taktu to odczyt wejścia, gdzie indziej — rysowanie. */
    public function firstPhaseLabelKey(): string
    {
        return $this === self::Loop ? 'bench.column.input' : 'bench.column.draw';
    }

    /** Nazwa fazy środkowej: kwantyzacja albo aktualizacja stanu. */
    public function middlePhaseLabelKey(): string
    {
        return $this === self::Loop ? 'bench.column.state' : 'bench.column.quantize';
    }

    /**
     * Czy klatka opuszcza proces jako bajty. W oknie nie opuszcza go wcale;
     * w torze tekstowym opuszcza jako sekwencje ANSI, więc kolumna „Blob”
     * niesie tam prawdziwą liczbę.
     */
    public function producesBlob(): bool
    {
        return $this !== self::Window;
    }

    /**
     * Czy ostatnia kolumna liczy bajty. W torze taktu liczy **prymitywy**:
     * klatka nie ma tam jeszcze bajtów, ale ma objętość, i to ona rośnie razem
     * z tym, co ekran składa.
     */
    public function blobIsBytes(): bool
    {
        return $this !== self::Loop;
    }

    public function blobColumnLabelKey(): string
    {
        return $this === self::Loop ? 'bench.column.primitives' : 'bench.column.blob';
    }

    /**
     * Domyślny próg porównania zrzutów, w **promilach** różniących się pikseli.
     *
     * Potok Imagicka jest deterministyczny — ta sama klatka daje bajt w bajt ten
     * sam obraz — więc próg jest zerowy i każda różnica jest różnicą. Tor
     * okienkowy rysuje przez sterownik GPU, gdzie różnice subpikselowe są normą
     * między maszynami i wersjami sterownika, więc próg musi być luźniejszy.
     * To jest cena zapisana przy decyzji o wzorcach okienkowych w repozytorium
     * (D64), a nie odkrycie do zrobienia przy pierwszej „regresji”.
     */
    public function defaultImageThresholdPerMille(): float
    {
        return $this === self::Window ? 5.0 : 0.0;
    }

    /** Nazwa trzeciej fazy: zamiana buforów, złożenie klatki albo kodowanie. */
    public function lastPhaseLabelKey(): string
    {
        return match ($this) {
            self::Window => 'bench.column.swap',
            self::Loop => 'bench.column.compose',
            default => 'bench.column.encode',
        };
    }

    /**
     * Nagłówek raportu. Tor taktu nie mierzy potoku renderowania i tytuł ma to
     * mówić — inaczej wzorzec sprzed roku czytałoby się jako pomiar czegoś, czym
     * nie jest.
     */
    public function reportTitleKey(): string
    {
        return $this === self::Loop ? 'bench.report.title.loop' : 'bench.report.title';
    }
}
