# Mapa dokumentacji

Ten plik odpowiada na jedno pytanie: **gdzie to napisać** — i na jego
odwrotność, **gdzie tego szukać**. Powstał w kroku 62
([plans/archiwum/62-porzadek-dokumentacji.md](plans/archiwum/62-porzadek-dokumentacji.md)),
bo dokumentacji było wtedy prawie trzynaście tysięcy wierszy i ani jednego
zdania o tym, który dokument za co odpowiada — więc każdy nowy tekst miał trzy
równie dobre miejsca i lądował w czwartym.

## Zdanie graniczne

**Architektura mówi, jak jest; przewodnik — jak to zrobić; podręcznik — jak tego
użyć; dziennik — dlaczego tak wyszło.**

Tekst, który odpowiada na dwa z tych pytań naraz, jest w złym miejscu. To nie
jest wada stylu, tylko wada adresu: czytelnik szukający jednej z tych rzeczy
musi przeczytać drugą, a autor kolejnej zmiany nie wie, który z dwóch akapitów
poprawić.

## Cztery rodzaje i ich granice

| Rodzaj | Gdzie | Odpowiada na pytanie | Czego tam nie ma |
|---|---|---|---|
| **Reguła** | [architektura/](architektura/) | jak jest i dlaczego tak | jak to zrobić krok po kroku |
| **Historia** | [plans/](plans/) | dlaczego tak wyszło, co odrzucono | co obowiązuje dziś |
| **Podręcznik** | [pl/podrecznik/](pl/podrecznik/), [en/manual/](en/manual/) | jak tego użyć | dlaczego tak działa |
| **Przewodnik** | [pl/przewodnik/](pl/przewodnik/), [en/guide/](en/guide/) | jak dołożyć swoją rzecz | historia i uzasadnienia |

Do tego **onboarding** ([pl/onboarding/](pl/onboarding/),
[en/onboarding/](en/onboarding/)) — nie piąty rodzaj, tylko **ścieżka**:
przewodnik jest referencją, do której się wraca, a onboarding przechodzi się
raz. Dlatego onboarding niczego nie tłumaczy sam, tylko prowadzi i wskazuje.
**Kto jest tu pierwszy dzień, zaczyna właśnie tam** — a nie od tej mapy.

## Wszystko, co jest — po adresach

| Dokument | Rodzaj | Język |
|---|---|---|
| [architecture.md](architecture.md) | Spis rozdziałów dokumentu źródłowego — **tu powstaje reguła** | pl |
| [architektura/](architektura/) | Dziewięć rozdziałów; rozdz. 2 w sześciu częściach | pl |
| [`.claude/skills/light-manager-conventions/SKILL.md`](../.claude/skills/light-manager-conventions/SKILL.md) | **Skrót** reguł do pracy nad kodem — nie źródło | pl |
| [plans/00-index.md](plans/00-index.md) | Plan, statusy kroków, graf zależności | pl |
| [plans/00-decyzje.md](plans/00-decyzje.md) | Dziennik decyzji — dlaczego tak, co odrzucono | pl |
| [plans/](plans/) i [plans/archiwum/](plans/archiwum/) | Kroki przed pracą i kroki ukończone | pl |
| [KONWENCJE.md](KONWENCJE.md) | Jak rysować diagram i jak wskazywać przykład | pl |
| [konwencja-changelogu.md](konwencja-changelogu.md) | Jak powstaje [`CHANGELOG.md`](../CHANGELOG.md) | pl |
| [pomiary/README.md](pomiary/README.md) | Wzorce pomiarowe i spis scenariuszy | pl |
| [`README.md`](../README.md) w korzeniu | Wizytówka repozytorium — wymagania, uruchomienie, odnośniki dalej | pl |
| [`CLAUDE.md`](../CLAUDE.md) | Wskaźnik dla Claude Code — trzydzieści kilka wierszy i tak ma zostać | pl |
| [pl/podrecznik/](pl/podrecznik/README.md) · [en/manual/](en/manual/README.md) | **Podręcznik użytkownika** — siedem rozdziałów, od pierwszego uruchomienia po scenariusze | pl **i** en |
| [pl/przewodnik/](pl/przewodnik/README.md) · [en/guide/](en/guide/README.md) | **Przewodnik dewelopera** — mapa kodu, cykl klatki, osiem przewodników „jak dodać X", dziesięć pułapek, workflow | pl **i** en |
| [pl/onboarding/](pl/onboarding/README.md) · [en/onboarding/](en/onboarding/README.md) | **Onboarding** — pięć przystanków od `git clone` do własnej zmiany z zieloną bramką; **wejście dla nowych** | pl **i** en |

## Dwa języki — i co z tego wynika

Dwujęzyczne są **trzy rodzaje dokumentów**: podręcznik, przewodnik
i onboarding. Nazwa katalogu, nazwa pliku i treść są w **tym języku, którego
dotyczą** — `pl/podrecznik/01-czym-to-jest.md` ma odpowiednik
`en/manual/01-what-is-it.md`, a nie kopię polskiej ścieżki z angielskim
środkiem. Nazwy własne z kodu (`FrameComposer`, `KeyBinding`, `make qa`)
zostają w oryginale w obu drzewach — to identyfikatory, nie wyrazy.

**Polski jest źródłem, angielski tłumaczeniem.** Zmiana treści zaczyna się po
polsku; wersja angielska, która została w tyle, jest **usterką widoczną
w bramce jakości**, a nie stanem normalnym (od kroku 66 pilnuje tego
`DocumentationLanguagePairTest`).

**Jednojęzyczne zostają** architektura, dziennik decyzji, plany i pomiary — wraz
z powodem, który jest ich cechą, a nie zaniedbaniem: **to są dokumenty pracy nad
projektem, a nie dokumenty projektu.** Ich czytelnikiem jest ten, kto go
rozwija, a rozwija go dziś jedna osoba pisząca po polsku. Zmiana tego jest
osobną decyzją, nie dopiskiem.

## Jedno źródło reguły

**Reguła powstaje w rozdziale architektury; `SKILL.md` ją streszcza i nie tworzy
własnych.** Gdy skrót i rozdział mówią co innego, rację ma rozdział — a skrót
jest usterką do naprawienia, nie drugim zdaniem w sprawie. Pełne uzasadnienie:
[architecture.md](architecture.md), rozdział „Pierwszeństwo źródła”.

## Utrzymanie — kto co aktualizuje

**Krok planu, który zmienia klawisz, ustawienie, komendę, kwerendę albo moduł,
aktualizuje podręcznik i przewodnik w tym samym kroku.** Dług dokumentacyjny bez
właściciela jest długiem, którego nikt nie spłaci — ta sama reguła, którą Faza
XVI stosowała do kodu. Rozwinięcie: [plans/00-index.md](plans/00-index.md),
rozdział „Śledzenie postępu”.

## Gdzie to napisać — jednym akapitem

Zapisujesz **regułę albo pojęcie** („tak ma być, bo…”) → rozdział
w [architektura/](architektura/). Zapisujesz **dlaczego tak wyszło i czego nie
wybrano** → [plans/00-decyzje.md](plans/00-decyzje.md) albo dziennik kroku.
Piszesz **do kogoś, kto tej aplikacji używa** → podręcznik. Piszesz **do kogoś,
kto ją rozwija i chce coś dołożyć** → przewodnik. Piszesz **do kogoś, kto jest
tu pierwszy dzień** → onboarding. Nie wiesz, bo tekst pasuje do dwóch → to
znaczy, że to są dwa teksty.
