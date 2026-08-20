# Zadanie ćwiczebne — kwerenda, która mówi, od jak dawna aplikacja działa

Materiał do przystanku czwartego onboardingu:
[`docs/pl/onboarding/04-pierwsza-zmiana.md`](../../docs/pl/onboarding/04-pierwsza-zmiana.md)
· [English](../../docs/en/onboarding/04-first-change.md).

**Instrukcja stoi w onboardingu, nie tutaj.** Ten plik mówi wyłącznie, co
w którym katalogu leży — żeby ktoś, kto trafił tu z listy katalogów, wiedział,
na co patrzy.

| Katalog | Co w nim jest |
|---|---|
| [`start/`](start/) | Punkt wyjścia: moduł gotowy, kwerenda z **jedną luką** (`ask()`), katalog napisów **tylko po polsku**. |
| [`rozwiazanie/`](rozwiazanie/) | To samo z wypełnioną luką i z `en.php` obok. Do porównania — albo gdy utkniesz. |

## Dlaczego akurat czas działania

Rdzeń aplikacji umie dziś powiedzieć o sobie trzynaście rzeczy — wersję, rozszerzenia,
moduły, motyw, język, tor rysowania klatki, ostatni komunikat. **Nie umie
powiedzieć, od jak dawna działa.** Zadanie polega na dołożeniu tego jednego
zdania, a wybrane zostało, bo spełnia naraz cztery warunki:

- **skutek widać w aplikacji**, nie tylko w teście — kwerenda odpowiada w oknie
  `F12` po przełączeniu `Tab`em;
- **dotyka trzech warstw** — danej (moment startu i chwila bieżąca), kwerendy
  i rejestracji w `Bootstrapie`;
- **nie może niczego zepsuć** — kwerenda czyta i nie zmienia;
- **przechodzi przez bramkę wraz z jej strażnikami**, a jeden z nich zaświeci
  się po drodze na czerwono, i to jest zaplanowane.

## Dwie różnice wobec modułu przykładowego

[`modul-przykladowy/`](../modul-przykladowy/) jest **referencją**: pokazuje
komendę, kwerendę, ustawienie i napisy naraz, i nie ma być uruchamiany. To
zadanie jest **ćwiczeniem**: ma jedną lukę, jeden brakujący plik i kończy się
uruchomieniem aplikacji.

Drugą różnicę widać w `Bootstrapie`: modułu przykładowego **nie ma** w spisie
i mieć nie będzie, bo byłby modułem bez odbiorcy. Moduł z tego zadania do spisu
wchodzi — na czas ćwiczenia, w twoim klonie repozytorium.
