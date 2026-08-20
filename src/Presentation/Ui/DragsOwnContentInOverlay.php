<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

/**
 * Okno nakładane, które **samo prowadzi przeciągnięcie** — i przez to zabiera
 * je zaznaczaniu treści (krok 77, D106 nr 2).
 *
 * Bliźniak `DragsOwnContent`, różniący się od niego **wyłącznie tym, kogo
 * dotyczy**: tamten jest zdolnością ekranu, ten — okna nakładanego. Sygnatura
 * jest ta sama, bo odpowiedź jest w obu wypadkach **stanem, a nie skutkiem
 * zdarzenia**: przeciągane chwyta się naciśnięciem, więc zanim przyjdzie
 * pierwsze przeciągnięcie, deklarujący już wie, że coś trzyma. Wariant przez
 * `OverlayOutcome::handled === false` — czyli oddawanie niezużytego
 * przeciągnięcia skutkiem — był rozważany i **odrzucony** rozstrzygnięciem
 * użytkownika: `handled` mówi o **klawiszu i kliknięciu**, czyli o pojedynczym
 * zdarzeniu, a przeciągnięcie jest ciągiem zdarzeń, którego pierwsze i ostatnie
 * należą do tego samego chwytu.
 *
 * **Zdolności nie deklaruje dziś ani jedno z dwunastu okien** i jest to **trzeci
 * jawny wyjątek od reguły 13** — po `ProgressBar`ze (krok 23) i samym zaznaczaniu
 * (krok 56), z taką samą jawną zgodą jak tamte dwa i tak samo nie będący
 * precedensem. Wyjątek jest przy tym węższy: zdolność nie jest nowym
 * mechanizmem, tylko **drugą połową mechanizmu, który odbiorcę ma**. Rdzeń pyta
 * o przeciągnięcie ekran (granica podziału, `SplitState`) i nie ma jak zapytać
 * okna — więc pierwsze okno z własnym przeciągnięciem musiałoby ruszyć
 * `InputHandler`a, czyli to samo miejsce, w którym krok 56 pomylił się co do
 * pierwszeństwa. Odbiorcy nie dorobiono świadomie: przeciągnięcie dołożone oknu
 * po to, żeby interfejs miał deklarującego, byłoby funkcją bez użytkownika —
 * tą samą regułą złamaną z drugiej strony.
 *
 * Pytanie pada w jednym miejscu (`InputHandler`) i wyłącznie przy
 * przeciągnięciu. Gdy okno stoi, **ekran nie jest pytany w ogóle**: mógł zostać
 * w stanie „trzymam granicę podziału”, bo okno otwarło się w środku jego chwytu,
 * a wtedy odpowiadałby o przeciągnięciu, którego użytkownik już nie prowadzi.
 */
interface DragsOwnContentInOverlay
{
    /** Czy okno jest w trakcie **własnego** przeciągnięcia. */
    public function isDraggingOwn(): bool;
}
