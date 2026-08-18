# Krok 75 — Moduł `team`: obraz zespołu policzony z tego, co już jest zapisane

> **Skąd ten krok.** Powstał 2026-08-16 jako drugi krok **Fazy XXIV**
> ([00-decyzje.md](00-decyzje.md), D98). Jest **zarysem, a nie planem**, i ma
> w sobie jedno pytanie, które nie jest techniczne — patrz „Zastrzeżenie".

## Status

**Nie rozpoczęty — zarys.** Rozstrzygnięcia startowe **nie powstały**.

## Cel

Moduł pokazuje **obraz pracy zespołu wokół repozytorium**: kto pracował nad
czym w danym okresie, które obszary kodu nie mają świeżego opiekuna i jak długo
żyją prośby o scalenie.

Miarą powodzenia jest zdanie: **na pytanie „kto ostatnio dotykał tego katalogu"
odpowiada się w aplikacji, a nie poleceniem wpisanym z pamięci.**

## Dlaczego ten krok jest ciekawy dla architektury, a nie tylko dla użytkownika

Bo byłby **pierwszym modułem zbudowanym w całości na cudzych kwerendach**.
Danych własnych nie ma żadnych: wkład liczy się z `git.log` (krok 67), a czas
życia prośby o scalenie — z `forge.pulls` (krok 70). To jest próba mechanizmu
z kroku 53 na wypadku, dla którego go nie pisano: moduł, który **niczego nie
czyta ze świata**, tylko składa cudze odpowiedzi.

Z tego bierze się drugie zdanie, ważniejsze: **moduł musi umieć żyć bez
odpowiedzi** (reguła 15g). Bez modułu `forge` pokazuje mniej, ale nie przestaje
działać; bez `git` nie ma sensu i wtedy odrzuca się z powodem.

## Zastrzeżenie — liczby o ludziach

Moduł liczy pracę **imiennie**. To jest jedyna pozycja w całej propozycji, która
może zostać użyta przeciwko komuś, kto nie wie, że jest liczony, i zarysu nie
wolno domykać bez odpowiedzi na pytanie **po co te liczby są pokazywane**.
Rekomendacja planu: miary **opisujące kod** (obszary bez świeżego opiekuna,
rozłożenie wiedzy, wąskie gardła przeglądu) wchodzą, a rankingi osób —
nie. Rozstrzyga użytkownik na starcie kroku.

## Zarys zakresu

- **Wkład w okresie** — zatwierdzenia i zmienione pliki wedle autora, dla
  wybranego zakresu dat.
- **Opieka nad kodem** — katalogi wedle daty ostatniej zmiany i liczby osób,
  które ich dotykały; obszary „jednej osoby" mają własny ton.
- **Pokrycie `CODEOWNERS`** — jeśli plik istnieje: co jest przypisane, a co nie.
- **Kolejka przeglądów w czasie** — czas życia próśb o scalenie; wyłącznie
  wtedy, gdy `forge` odpowiada.
- **Kwerendy** — `team.contributors`, `team.ownership`.

## Czym płaci rdzeń

**Zero zmian** — jedna pozycja w `Bootstrapie`.

## Pytania do rozstrzygnięcia

1. **Po co te liczby** — patrz „Zastrzeżenie". Rozstrzygnięcie wyznacza zakres,
   a nie sposób wykonania.
2. **Tożsamość autora** — ten sam człowiek bywa w historii pod trzema adresami.
   Mapowanie z pliku (`.mailmap`, który `git` czyta sam), z ustawień, czy
   wcale?
3. **Skąd okres** — stała (30 dni), pozycja ustawień, czy wybór na ekranie?
4. **Co robi moduł, gdy `forge` milczy** — pokazuje część i mówi o tym, czy
   ukrywa sekcję? Reguła 15g wymaga tylko tego, żeby nie padł.
5. **Czy liczy się także repozytorium spoza katalogu bieżącego** — jedno na raz
   czy spis?

## Stan zastany (sprawdzony 2026-08-16)

| Element | Stan |
|---|---|
| Materiał w tym repozytorium | **25 zatwierdzeń, jeden autor, jedna gałąź** — czyli odbiorcy tego modułu **w tym projekcie dziś nie ma**. Jest nim projekt cudzy, oglądany przeglądarką. |
| Źródła danych | Wyłącznie kwerendy kroków 67 i 70; własnego rozmówcy moduł nie ma. |
| Reguła 15g | Moduł pytający musi umieć żyć bez odpowiedzi — `QueryRegistry::ask()` oddaje wynik z powodem, nie `null`. |

## Zależności

- **Krok 67** — `git.log` i `git.head`; bez niego moduł nie ma treści.
- **Krok 70** — `forge.pulls`; bez niego moduł ma treść mniejszą, ale ma.
- **Kroki 27 i 30** — tabela i filtr.
- **Kroki 53 i 54** — kwerendy i wzór modułu pytającego inny moduł nazwą.

## Model i wysiłek (wstępnie)

**Opus / high.** Kodu jest w nim niewiele i to jest **najmniejszy krok pod
względem powierzchni** w Fazie XXIV; wysiłek trzymają dwie rzeczy nietechniczne
— rozstrzygnięcie o zakresie miar — oraz jedna techniczna: bycie pierwszym
modułem bez własnego źródła danych, czyli pierwszym prawdziwym sprawdzianem
reguły „umie żyć bez odpowiedzi".

## Poza zarysem

- Miary wydajności osób i cokolwiek, co daje się ułożyć w ranking.
- Integracja z systemami HR, kalendarzem i planowaniem urlopów.
- Dyżury i obsługa incydentów — osobna dziedzina, osobny moduł, jeśli w ogóle.
- Prognozowanie i szacowanie pracochłonności.

## Dziennik realizacji

*(Krok nie rozpoczęty — wpisy pojawią się przy wykonaniu.)*
