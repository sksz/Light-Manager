# Krok 28 — Okno potwierdzenia jako mechanizm rdzenia

> **Skąd ten krok.** Powstał 2026-08-11, przy przeglądzie braków rdzenia po kroku
> 26. Wybrany przez użytkownika jako drugi z trzech, których odbiorca już siedzi
> w kodzie (D48).

## Status

**Nie rozpoczęty** (2026-08-11).

## Cel

Dać aplikacji **jeden sposób zadania pytania, na które trzeba odpowiedzieć,
zanim coś się stanie**.

Miarą powodzenia jest zdanie: **„Przywróć ustawienia domyślne” pyta, zanim
skasuje konfigurację, a ekran, który pytał, dostaje odpowiedź — nie domysł.**

## Zależności

- **Krok 19** (okno komend) — twardo i podwójnie. Stamtąd pochodzi
  `OverlayInterface` wraz z regułą „okno zużywa albo przepuszcza klawisz”, oraz
  `OverlayStack`. To jest **drugie okno nakładane w projekcie** i pierwsze, które
  czegoś chce od wołającego.
- **Krok 18** (komponenty) — `Dialog`, `Button`, `FocusableInterface`
  i `VStack` są całą jego zawartością. Komponent nie powstaje tu ani jeden;
  powstaje **okno**, czyli sposób ich użycia.
- **Krok 14** (ekran ustawień) — bo tam siedzi odbiorca.

## Model i wysiłek

**Opus / high.**

Kodu jest mało, a rozstrzygnięcie jedno i ciężkie: **jak okno oddaje decyzję**.
Dziś okno nakładane potrafi wyłącznie zużyć klawisz albo go przepuścić
(`OverlayOutcome`), więc odpowiedź „tak/nie” nie ma którędy wrócić do ekranu,
który pytał. Każde z możliwych rozwiązań — domknięcie wywoływane przy
potwierdzeniu, wynik w `OverlayOutcome`, ekran odpytujący okno po jego zamknięciu
— zostawia inny ślad w kontrakcie rdzenia, a kontrakt rdzenia zmienia się
w tym projekcie niechętnie i z nazwiskiem (krok 21: „`ScreenInterface` po raz
pierwszy zmienia kształt”).

## Stan zastany (do sprawdzenia w kodzie na starcie kroku)

| Element | Stan |
|---|---|
| `Presentation/Ui/OverlayInterface` | `bounds()`, rysowanie, obsługa klawisza; wynik przez `OverlayOutcome` — **zużyty albo przepuszczony**, nic ponadto |
| `Presentation/Cli/OverlayStack` | Trzyma okna nad ekranem; `MessageOverlay` i `CommandOverlay` to dzisiejsi mieszkańcy |
| `Presentation/Ui/Component/Dialog` | Obwódka okna modalnego wraz z tytułem — gotowa oprawa |
| `Presentation/Ui/Component/Button` | Przycisk z ogniskiem i domknięciem wywoływanym `Enter`em |
| `Presentation/Cli/Screen/SettingsScreen` | `restoreButton()` woła `RestoreDefaultSettingsUseCase` **natychmiast** — jedyna nieodwracalna akcja bez pytania |

## Zakres

### 1. Okno

```php
final class ConfirmOverlay implements OverlayInterface
{
    public function __construct(
        string $questionKey,          // klucz katalogu, nie napis
        array $parameters,            // dane do podstawienia w pytaniu
        Closure $onConfirm,           // co zrobić po „tak”
        bool $dangerous = false,      // rola koloru: ostrzeżenie zamiast tekstu
    ) {}
}
```

Dwa przyciski, ognisko wędruje `←`/`→` albo `Tab`, `Esc` znaczy „nie”, `Enter`
zatwierdza to, co pod ogniskiem.

**Domyślną odpowiedzią jest „nie”** i to nie jest kosmetyka: okno pojawia się
przed rzeczą nieodwracalną, a użytkownik przyzwyczajony do przytrzymywania
`Enter`a ma trafić w odmowę, nie w zgodę.

### 2. Droga powrotna decyzji

Najważniejsza część kroku i jedyna, która wychodzi poza samo okno — patrz
rozstrzygnięcie nr 1. Trzy drogi do rozważenia:

- **domknięcie podane przy tworzeniu okna** (jak `Button` od kroku 18) — zero
  zmian w kontrakcie, za to decyzja wykonuje się „w środku” okna;
- **wynik w `OverlayOutcome`** — jawny, ale rozszerza kontrakt **każdego** okna,
  także tych, które o żadnej decyzji nie wiedzą;
- **ekran pyta okno po zamknięciu** — wymaga, żeby ekran pamiętał, że coś
  otworzył, czyli przenosi stan tam, gdzie krok 19 świadomie go nie zostawił.

### 3. Odbiorca: przywracanie ustawień domyślnych

`SettingsScreen` przestaje kasować konfigurację po jednym `Enter`. To jedyne
miejsce w aplikacji, w którym pomyłka kosztuje dane, i dlatego jest właściwym
pierwszym odbiorcą — a nie operacje na plikach, których jeszcze nie ma.

### 4. Pomiar

Okno potwierdzenia rysuje się rzadko i krótko, więc scenariusz `popup` z kroku 18
**wystarcza** — nowego nie ma. Krok rozlicza się natomiast z tego, czego się nie
spodziewamy: klatka z oknem potwierdzenia ma kosztować tyle, co klatka z oknem
modalnym, bo składa się z tych samych prymitywów.

## Poza zakresem

- **Okno z trzema odpowiedziami** („tak / nie / do wszystkich”) — potrzebne przy
  operacjach zbiorczych, których nie ma.
- **Pytanie o tekst** (nazwa nowego katalogu) — to `TextInput` w oknie, czyli
  osobna rzecz, mimo podobnego wyglądu.
- **Kolejka pytań** — jedno okno naraz, jak wszystko na stosie okien od kroku 19.
- **Potwierdzanie czegokolwiek poza przywracaniem ustawień** — reszta akcji
  w aplikacji jest odwracalna i pytanie byłoby w nich hałasem.

## Planowane zmiany w plikach

| Plik | Warstwa | Zmiana |
|---|---|---|
| `Presentation/Ui/Overlay/ConfirmOverlay.php` | Presentation | Nowe — pytanie, dwa przyciski, ognisko. |
| `Presentation/Ui/OverlayOutcome.php` | Presentation | Ewentualnie — zależnie od rozstrzygnięcia nr 1. |
| `Presentation/Cli/Screen/SettingsScreen.php` | Presentation | Przywracanie ustawień przez pytanie. |
| `lang/pl.php`, `lang/en.php` | Napisy | Pytanie, „tak”, „nie”. |
| `docs/architecture.md`, `SKILL.md`, `README.md` | Dokumentacja | Okno oddające decyzję jako wzorzec. |
| testy | Testy | Domyślna odmowa, `Esc` jako „nie”, wędrówka ogniska, akcja **niewykonana** po odmowie, wykonana po zgodzie. |

## Do rozstrzygnięcia na starcie kroku

1. **Którą drogą wraca decyzja** (punkt 2 zakresu).
2. **Czy `Esc` znaczy „nie”, czy „zamknij bez odpowiedzi”** — i czy to w ogóle
   różne rzeczy.
3. **Czy okno przepuszcza klawisze globalne** (`F10`, `F1`) — reguła kroku 19
   mówi „przepuszczony trafia wyłącznie do klawiszy globalnych”, ale wyjście
   z aplikacji w trakcie pytania o rzecz nieodwracalną jest przypadkiem
   granicznym.
4. **Czy rola „niebezpieczne” maluje okno inaczej**, czy wystarczy treść pytania.

## Kryteria ukończenia

- Przywracanie ustawień domyślnych pyta, a odmowa **niczego nie zmienia** —
  sprawdza to test, nie oglądanie.
- Ognisko startuje na „nie”.
- Kontrakt okna nakładanego urósł **najwyżej o jedną rzecz**, nazwaną
  w dzienniku decyzji.
- Klatka z oknem potwierdzenia mieści się w koszcie scenariusza `popup`.
- PHPStan `max` bez błędów, PHP-CS-Fixer bez uwag, testy zielone.

## Dziennik realizacji

*(pusty — krok nierozpoczęty)*
