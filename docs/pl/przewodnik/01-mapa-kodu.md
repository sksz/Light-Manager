# 1. Mapa kodu

> Przewodnik dewelopera, część 1 z 7. [Spis](README.md) ·
> [English](../../en/guide/01-code-map.md)

## Trzy zdania, które rozstrzygają najwięcej sporów o miejsce

Zanim spojrzysz na drzewo katalogów, zapamiętaj trzy zdania. W tym projekcie to
one przesądzają, gdzie coś ma leżeć — częściej niż intuicja i częściej niż
podobieństwo nazw.

1. **Rdzeń nie wie, czym jest plik.** Cała domena plikowa — katalog, wpis,
   ścieżka, zaznaczenie — leży w `src/Module/Browser/`, a nie w `src/Domain/`.
   `Domain/` rdzenia jest przez to chudy i **tak ma być**: to słownik powłoki
   terminalowej, nie menadżera plików. Pilnuje tego
   `CoreKnowsNothingAboutFilesTest`.
2. **Moduł nie sięga do innego modułu.** To, co wygląda na wyjątek, idzie
   **komendą, kwerendą albo zdarzeniem** — trzema drogami rdzenia, nie czwartą.
   Pilnuje tego `NoModuleKnowsAnotherModuleTest`, patrząc na `use` w plikach.
3. **Interfejs graficzny stoi po dwóch stronach granicy.** Komponent wie, jak
   wyglądać; **prymityw jest tym, co z tej wiedzy zostaje po przekroczeniu
   portu**. Dlatego komponenty siedzą w `Presentation/Ui`, a prymitywy
   w `Application/Ui` — renderer implementuje port z `Application` i nie ma
   prawa zobaczyć klasy z `Presentation`.

## Drzewo repozytorium

```
Makefile     wejścia do wszystkich procesów projektu (`make` wypisuje spis)
bin/         skrypty wejściowe CLI (aplikacja, narzędzia diagnostyczne, budowa)
src/         kod aplikacji (PSR-4, namespace LightManager\)
src/Module/  siedem modułów — każdy z własnymi warstwami i własnymi napisami
tests/       testy PHPUnit (namespace LightManager\Tests\)
lang/        katalogi napisów interfejsu (rdzeń)
assets/      zasoby aplikacji (domyślny utwór, próbki efektów)
docs/        dokumentacja — wejściem jest docs/README.md (mapa)
examples/    przykłady wskazywane przez dokumentację, objęte bramką jakości
build/       wynik `make build` — poza repozytorium (.gitignore)
```

## Cztery warstwy rdzenia

Zależności biegną **tylko do środka**: `Presentation → Application → Domain`,
a `Infrastructure → Domain/Application` przez implementację interfejsów.
Strzałki w drugą stronę nie ma i nie będzie.

| Warstwa | Co tam leży | Czego tam nie ma |
|---|---|---|
| `Domain/` | Pojęcia powłoki: `Message`, `MessageTone`, `Preview`, `RendererMode`, `ScrollPosition`, hierarchia wyjątków | Singletonów, Imagicka, `pcntl`, terminala, napisów, **czegokolwiek o pliku** |
| `Application/` | Kontrakty i dane: porty, komendy, kwerendy, zdarzenia, kontrakt modułu, prymitywy klatki, DTO ustawień i wejścia | Implementacji technologii — zna wyłącznie interfejsy |
| `Infrastructure/` | Technologia: terminal, Imagick, GLFW, procesy potomne, pliki, katalogi napisów, diagnostyka | Decyzji o tym, co narysować |
| `Presentation/` | Pętla, ekrany, komponenty, okna nakładane, wiązania klawiszy, `Bootstrap` | Odczytu danych z pominięciem kwerendy |

Podkatalogi, które warto znać od razu:

| Katalog | Po co |
|---|---|
| `Application/Port/` | 16 portów rdzenia — wejście, widok, renderer, ustawienia, praca tłowa, schowek, operacje na plikach, kosz, podglądy |
| `Application/Command/` | Kontrakt komendy, argumenty, parser wiersza, rejestr, historia |
| `Application/Query/` | Kontrakt kwerendy, rejestr, wynik, pokolenie, właściciel |
| `Application/Module/` | Kontrakt modułu i zdolności mówiące **danymi** |
| `Application/Ui/` | Klatka, płaszczyzny, prostokąty, role motywu i **osiem prymitywów** |
| `Presentation/Ui/Component/` | 27 komponentów — lista, tabela, drzewo, widok tekstu, pola, zakładki |
| `Presentation/Ui/Overlay/` | Okna nakładane: pytanie, wybór, pole tekstowe, spis, postęp, komunikat |
| `Presentation/Ui/Module/` | Zdolności modułu wymieniające typ z `Presentation` |
| `Presentation/Cli/` | `GameLoop`, `InputHandler`, `FrameComposer`, `LoopState`, `Bootstrap`, ekrany rdzenia |
| `Infrastructure/Rendering/` | Trzej tłumacze prymitywów: sixelowy, tekstowy, OpenGL |

## Moduł powtarza ten sam podział

Moduł nie jest wtyczką o innym kształcie — jest **tą samą architekturą
w mniejszej skali**:

```
src/Module/AddressBook/
    Application/           dane, porty i logika modułu
    Application/Port/      to, czego moduł potrzebuje od świata
    Domain/                pojęcia modułu wraz z wyjątkami
    Domain/ValueObject/
    Infrastructure/        implementacje portów modułu
    Presentation/          moduł, ekran, komponenty
    Presentation/Command/  komendy modułu — dostają stan pętli
    Presentation/Query/    kwerendy modułu
    lang/                  pl.php i en.php, scalane z katalogiem rdzenia
```

**Modułu nie musi być w każdej warstwie.** Moduł przykładowy
([`examples/modul-przykladowy/`](../../../examples/modul-przykladowy/)) ma
komendę, kwerendę, ustawienie i napisy — i ani jednego pliku w `Domain/`, bo
nie ma własnych pojęć. To jest poprawne, a nie niedokończone.

## Gdzie leżą reguły

| Rodzaj wiedzy | Miejsce |
|---|---|
| **Reguła** — jak jest i dlaczego tak | [dokument źródłowy](../../architecture.md) i rozdziały w [`docs/architektura/`](../../architektura/) |
| **Skrót reguł do pracy nad kodem** | [`SKILL.md`](../../../.claude/skills/light-manager-conventions/SKILL.md) — streszczenie, **nie źródło** |
| **Dlaczego tak wyszło, co odrzucono** | [dziennik decyzji](../../plans/00-decyzje.md) — zobacz [rozdz. 7](07-dziennik-jak-czytac.md) |
| **Co jest zrobione, co w planie** | [plan](../../plans/00-index.md) |
| **Jak tego użyć** | [podręcznik](../podrecznik/README.md) |

Gdy skrót i rozdział mówią co innego, **rację ma rozdział**.

## Pięciu strażników reguł w testach

Reguły z tego rozdziału nie są prośbą — pilnują ich testy, które czytają kod
i przewracają bramkę:

| Test | Czego pilnuje |
|---|---|
| `CoreKnowsNothingAboutFilesTest` | żaden plik rdzenia nie odwołuje się do typu plikowego |
| `NoModuleKnowsAnotherModuleTest` | żaden moduł nie ma w `use` klasy z innego modułu |
| `QueryIsTheOnlyReadPathTest` | dane czyta się rejestrem kwerend, nie po cudzych obiektach |
| `PrimitiveTranslationTableTest` | każdy prymityw ma tłumaczenie w każdym z trzech torów |
| `StatusHintsFlowTest` | pasek stanu obiecuje dokładnie te klawisze, które są ogłoszone |

Ostatni ma granicę wartą zapamiętania: pilnuje klawiszy **ogłoszonych**, a nie
**obsługiwanych** — klawisz działający bez `KeyBinding`u przechodzi mu przez
palce. Zobacz [pułapkę 10](05-pulapki.md).

## Dokąd dalej

- [2. Cykl życia klatki](02-cykl-klatki.md) — jak to się kręci
- [3. Jak dodać swoją rzecz](03-jak-dodac.md) — osiem przewodników
