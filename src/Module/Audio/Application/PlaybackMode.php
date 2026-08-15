<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Application;

/**
 * Co się dzieje, kiedy utwór się skończy (krok 45).
 *
 * Następca przełącznika `loop` z kroku 36. Tamten odpowiadał na pytanie „czy
 * w kółko”, bo utwór był jeden; przy playliście pytanie ma trzy odpowiedzi,
 * a dwustanowy przełącznik nie umie ich unieść.
 *
 * Wartości są napisami w konfiguracji, bo pozycją wyboru w zakładce modułu jest
 * `ModuleSetting::choice()`, a ta bierze listę napisów. Napisy są **językowo
 * neutralne** i widać je w zakładce takimi, jakie są — dokładnie tak, jak
 * `absolute`/`relative` w module opisu pliku (krok 25).
 */
enum PlaybackMode: string
{
    /** Po utworze rusza następny, a po ostatnim — pierwszy. */
    case LoopList = 'list';

    /** Po utworze cisza; playlista czeka na `Enter`. */
    case StopAfterTrack = 'once';

    /** Ten sam utwór w kółko — zapętleniem po stronie silnika, bez udziału taktu. */
    case RepeatTrack = 'repeat';

    /** @return list<string> wartości do deklaracji pozycji ustawień */
    public static function choices(): array
    {
        return array_map(static fn (self $mode): string => $mode->value, self::cases());
    }

    /**
     * Czy zapętlenie robi **silnik**, a nie playlista.
     *
     * Powtarzanie utworu jest jedynym trybem, w którym po skończeniu niczego nie
     * trzeba zauważać: `Sound::setLoop(true)` gra dalej sam. Takt i tak przychodzi,
     * ale nie ma co robić — i to jest tańsze niż wczytywanie tego samego pliku
     * od nowa co pięć minut.
     */
    public function repeatsInEngine(): bool
    {
        return $this === self::RepeatTrack;
    }

    /** Czy po skończonym utworze playlista sięga po następny. */
    public function continuesToNext(): bool
    {
        return $this === self::LoopList;
    }
}
