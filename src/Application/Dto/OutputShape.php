<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

/**
 * Czym jest wypis pracy tłowej: **wynikiem czy strumieniem** (krok 52).
 *
 * Pojęcie weszło razem z pierwszym odbiorcą, który potrzebował drugiej
 * odpowiedzi — `kubectl logs -f` ([00-decyzje.md](../../../docs/plans/00-decyzje.md),
 * D91 nr 12). Do tego kroku port pracy tłowej znał wyłącznie wynik i miał wobec
 * niego jedną regułę: **zbieraj do granicy, nadmiar czytaj i wyrzucaj**. Reguła
 * jest dla wyniku prawidłowa — suma z `du` stoi na początku wypisu, więc
 * przycięcie końca niczego jej nie odbiera, a czytanie nadmiaru trzyma potomka
 * przy życiu.
 *
 * Dla strumienia ta sama reguła znaczy jednak **ciszę po osiągnięciu limitu**:
 * logi kontenera dobiłyby do granicy w kilkanaście sekund i od tej chwili ani
 * jeden nowy wiersz nie dotarłby do aplikacji, choć potomek nadal by je pisał.
 * Strumień musi więc zapominać **najstarsze**, a nie odrzucać najnowsze — czyli
 * dokładnie odwrotnie niż wynik.
 *
 * Różnica jest własnością **zamówienia, nie polecenia**: to samo `kubectl logs`
 * bywa jednym i drugim, zależnie od tego, czy dostało `-f`. Dlatego kształt
 * podaje się przy `start()`, a nie zgaduje z wiersza polecenia.
 */
enum OutputShape
{
    /**
     * Wypis jest **treścią do odczytania po zakończeniu**: JSON, lista, suma.
     *
     * Zbierany do granicy, nadmiar czytany i wyrzucany; przy `Done` przycięty
     * z białych znaków. Zachowanie portu sprzed kroku 52 w każdym szczególe —
     * i wartość domyślna, żeby żaden dotychczasowy odbiorca nie musiał wiedzieć,
     * że pojawił się wybór.
     */
    case Result;

    /**
     * Wypis jest **rzeką bez końca**: logi, `tail -f`, `journalctl -f`.
     *
     * Bufor przesuwa się — po przekroczeniu granicy wypadają wiersze
     * **najstarsze**, a ile bajtów wypadło, mówi `BackgroundState::$droppedBytes`.
     * Treści **nie przycinamy z białych znaków** w żadnym stanie, bo czytający
     * liczy pozycje w bajtach i przycięcie przesunęłoby mu wszystkie naraz.
     */
    case Stream;
}
