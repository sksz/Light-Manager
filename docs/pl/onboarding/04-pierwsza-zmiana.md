# 4. Pierwsza zmiana

> Onboarding, przystanek 4 z 5 · **10 minut**. [Spis](README.md) ·
> [English](../../en/onboarding/04-first-change.md)

## Zadanie

Rdzeń aplikacji umie powiedzieć o sobie trzynaście rzeczy — wersję, rozszerzenia,
moduły, motyw, język, tor rysowania klatki. **Nie umie powiedzieć, od jak dawna
działa.** Dołożysz to jedno zdanie: moduł z jedną kwerendą `czas.dzialanie`,
którą zobaczysz w oknie `F12` po przełączeniu `Tab`em.

Materiał stoi w [`examples/zadanie-kwerenda/`](../../../examples/zadanie-kwerenda/):
w `start/` moduł z **jedną luką**, w `rozwiazanie/` to samo z luką wypełnioną.
Zaglądaj do rozwiązania, kiedy zechcesz — onboarding bez rozwiązania kończy się
dla części ludzi ciszą zamiast pytania.

**Zadanie ma jeden zaplanowany błąd.** Bramka jakości zaświeci się po drodze na
czerwono i powie dokładnie, czego brakuje. Tak ma być: pierwszym kontaktem
z regułami tego projektu jest komunikat bramki, nie spis reguł.

## Ruch 1 — skopiuj szkielet

```bash
cp -r examples/zadanie-kwerenda/start src/Module/Czas
sed -i 's/Examples\\ZadanieKwerenda\\Start/Module\\Czas/' \
    src/Module/Czas/Presentation/CzasModule.php \
    src/Module/Czas/Presentation/Query/CzasDzialaniaQuery.php
```

*(macOS: `sed -i '' …`.)* Nazwa katalogu i przestrzeń nazw muszą się zgadzać —
tego pilnuje PSR-4, a nie żadna reguła tego projektu.

## Ruch 2 — dopisz moduł do `Bootstrapu`

**Moduł kosztuje w rdzeniu jedną linię i to jest miara, nie życzenie.**
W [`src/Presentation/Cli/Bootstrap.php`](../../../src/Presentation/Cli/Bootstrap.php),
w metodzie `createModules()`, na końcu listy:

```php
new CzasModule($state, microtime(true)),
```

…oraz `use LightManager\Module\Czas\Presentation\CzasModule;` między pozostałymi
modułami (alfabetycznie: za `Browser`, przed `Docker`).

**Moment startu podaje `Bootstrap`, a nie moduł**, bo to `Bootstrap` wie, kiedy
start nastąpił. Ta sama zasada trzyma w tym projekcie komponenty i pasek
postępu: klasa z własnym `microtime()` przestaje być testowalna.

## Ruch 3 — wypełnij lukę w kwerendzie

W skopiowanym przed chwilą pliku `src/Module/Czas/Presentation/Query/CzasDzialaniaQuery.php`
(oryginał: [`examples/zadanie-kwerenda/start/…`](../../../examples/zadanie-kwerenda/start/Presentation/Query/CzasDzialaniaQuery.php))
metoda `ask()` oddaje dziś pustą odpowiedź. Ma oddać liczbę sekund — jedno pole
o nazwie `seconds`. Liczbę masz gotową w `seconds()`; postać wyniku wybierasz
z pięciu, a opisuje je [przewodnik, „Nowa kwerenda”](../przewodnik/03-jak-dodac.md#nowa-kwerenda).

`generation()` jest już napisane i **warto przeczytać dlaczego**: pokolenie jest
licznikiem zmian, nie znacznikiem czasu. Gdyby stało tam `microtime()`, rejestr
przeliczałby tę kwerendę co klatkę, żeby oddać wartość zmieniającą się raz na
sekundę.

## Ruch 4 — zapytaj bramkę

```bash
make qa
```

Bramka idzie w znanej kolejności — `cs-check`, `stan`, `test` — i zatrzymuje się
na pierwszym błędzie. Zobaczysz:

```
1) …TranslatorServiceTest::testEveryModuleCarriesTheSameKeysInEveryLanguage@Czas
moduł  ma plik języka zapasowego
```

To jest ten zaplanowany błąd. Twój moduł ma `lang/pl.php`, a nie ma `lang/en.php`
— **katalog modułu, który przetłumaczył się na jeden język, jest w tym projekcie
takim samym błędem, co napis wpisany wprost w kod**. Pusty identyfikator
w komunikacie nie jest usterką testu: identyfikator wyprowadza się z katalogu
języka odniesienia, a to właśnie jego brakuje.

Napisz `src/Module/Czas/lang/en.php` z tymi samymi trzema kluczami co `pl.php`
(gotowy leży w `examples/zadanie-kwerenda/rozwiazanie/lang/en.php`) i puść
`make qa` jeszcze raz. Ma być zielone.

## Ruch 5 — zobacz skutek w aplikacji

```bash
make run
```

`F12` → `Tab` przy pustym wierszu → wpisz `czas` → `Enter`. Odpowiedź to jeden
wiersz: pole `seconds` i liczba, która przy kolejnym pytaniu jest większa.
Twój moduł jest też w odpowiedzi kwerendy `core.modules` i na zakładce „Moduły”
w ustawieniach (`F2`).

## Ruch 6 — dopisz moduł do spisów w dokumentacji

**Zmiana, której nie widać w dokumentacji, nie jest w tym projekcie zmianą
skończoną.** Dołożyłeś ósmy moduł, więc cztery zdania przestały być prawdziwe:

- [`docs/pl/podrecznik/05-moduly.md`](../podrecznik/05-moduly.md) — dopisz
  wiersz do tabeli modułów: nazwa **Czas działania**, skrót „—”, wymaga „—”,
  bez tego „—” (moduł bez ekranu nie ma skrótu i niczego nie wymaga).
- [`docs/en/manual/05-modules.md`](../../en/manual/05-modules.md) — to samo
  po angielsku, nazwa **Uptime**.
- [`docs/pl/przewodnik/01-mapa-kodu.md`](../przewodnik/01-mapa-kodu.md) —
  „siedem modułów” w drzewie repozytorium jest już nieprawdą.
- [`docs/en/guide/01-code-map.md`](../../en/guide/01-code-map.md) — „seven
  modules”, tak samo.

Zasada, z której to wynika, jest jedna i obowiązuje wszystkich: **krok, który
zmienia klawisz, ustawienie, komendę, kwerendę albo moduł, aktualizuje
dokumentację w tym samym kroku.** Dług dokumentacyjny bez właściciela jest
długiem, którego nikt nie spłaci.

## Pięć rzeczy, które łamie się tu najczęściej

Nie musisz ich pamiętać — **musisz umieć rozpoznać komunikat**, bo to on
przychodzi pierwszy:

| Złamanie | Co powie bramka |
|---|---|
| Napis wpisany wprost w kod | Test katalogów napisów: klucz bez tłumaczenia albo tłumaczenie bez klucza — dokładnie ten, który właśnie widziałeś. |
| Odczyt danych z pominięciem kwerendy | `QueryIsTheOnlyReadPathTest` — wraz ze wskazaniem, czym zastąpić. |
| Sięgnięcie do innego modułu | `NoModuleKnowsAnotherModuleTest` — widać to w `use`. |
| Typ plikowy w rdzeniu | `CoreKnowsNothingAboutFilesTest` — domena plikowa mieszka w module `Browser`. |
| Klawisz działający, ale niewymieniony w `bindings()` | `StatusHintsFlowTest` — pasek stanu ma obiecywać dokładnie to, co działa. |

## Skąd wiesz, że skończyłeś

**`make qa` jest zielone**, a `F12` → `Tab` → `czas.dzialanie` odpowiada liczbą,
która rośnie. Zmiana zostaje w twoim klonie — a gdy zechcesz ją zdjąć, wystarczy
`rm -rf src/Module/Czas` i cofnięcie dopisków w `Bootstrapie` i w dokumentacji.

Dalej: [5. Dokąd dalej](05-dokad-dalej.md).
