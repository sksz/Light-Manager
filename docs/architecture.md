# Architektura i konwencje kodowania — Light Manager

Dokument źródłowy projektu: **tu powstaje reguła i tu stoi jej uzasadnienie.**
Ustalenia wypracowane w fazie planowania architektury i stylu (kroki 01–04
w [docs/plans/00-index.md](plans/00-index.md)) oraz wszystko, co doszło do nich
później. Pełna historia decyzji, uzasadnień i odrzuconych alternatyw:
[docs/plans/00-decyzje.md](plans/00-decyzje.md).

Od kroku 62 ten plik jest **spisem rozdziałów**, a rozdziały leżą
w [docs/architektura/](architektura/). Adres się nie zmienił, bo wskazuje na
niego pięćdziesiąt kilka miejsc w repozytorium, w tym czterdzieści siedem
w archiwum planów — czyli w dokumentach zamkniętych, których nie przepisuje się
bez zmieniania historii.

## Pierwszeństwo źródła

**Reguła powstaje w rozdziale architektury. `SKILL.md` ją streszcza i nie tworzy
własnych.** Operacyjny skrót dla Claude Code
(`.claude/skills/light-manager-conventions/SKILL.md`) jest **streszczeniem, nie
wyciągiem**: pisze się go ręcznie, bo wyciąg byłby dłuższy i gorszy od źródła.
Gdy skrót i rozdział mówią co innego, **rację ma rozdział** — a skrót jest
usterką do naprawienia.

Z tego wynika obowiązek przy każdej zmianie konwencji: **najpierw rozdział,
potem skrót**, w tym samym kroku planu. Numer reguły w skrócie ma mieć rozdział
w architekturze i odwrotnie; od kroku 66 pilnuje tego test bramki jakości.

Gdzie mieszka który rodzaj dokumentu i czego w którym pisać nie wolno:
[docs/README.md](README.md) — mapa dokumentacji.

## Rozdziały

| # | Rozdział | O czym mówi |
|---|---|---|
| 1 | [Warstwy (Domain-Driven Design)](architektura/01-warstwy.md) | Cztery warstwy rdzenia, reguła „strzałki tylko do środka”, moduł powtarzający ten sam podział i jedyny nazwany wyjątek — zapis na dysk. |
| 2 | [Słownik domenowy](architektura/02-slownik/slownik.md) | Pojęcia rdzenia i modułów wraz z blokiem DDD i katalogiem; do tego **słownik interfejsu** w sześciu częściach tematycznych. |
| 3 | [Wzorzec Singleton, porty i bootstrap](architektura/03-singleton.md) | Każda usługa osobnym Singletonem, bez centralnego kontenera; porty, ich implementacje i kolejność sprzątania przy wyjściu. |
| 4 | [Standardy PHP i narzędzia](architektura/04-standardy-php.md) | Wersja PHP, `strict_types`, PSR-12, PHPStan `max`, PHPUnit — oraz miejsce diagnostyki i pomiaru. |
| 5 | [Konwencje nazewnictwa](architektura/05-nazewnictwo.md) | Sufiksy (`…Port`, `…Service`, `…UseCase`, `…RepositoryInterface`) i to, która nazwa do której warstwy należy. |
| 6 | [Wzorce kodu — przykłady](architektura/06-wzorce-kodu.md) | Value Object, wyjątek domenowy, interfejs repozytorium — **wskazaniem na prawdziwy plik**, nie kopią w markdownie. |
| 7 | [Napisy i języki interfejsu](architektura/07-napisy.md) | Katalog napisów, sięganie po napis z każdej warstwy, wybór języka i liczby. |
| 8 | [Procesy projektu](architektura/08-procesy.md) | Spis „proces → wejście”, reguła pierwszeństwa narzędzi repozytorium, zawartość archiwum budowy. |
| 9 | [Co dalej](architektura/09-co-dalej.md) | Mapa kroków wdrożenia na warstwy i katalogi; bieżący status realizacji. |

### Rozdział 2 w częściach

| Część | Co trzyma |
|---|---|
| [Pojęcia](architektura/02-slownik/slownik.md) | Tabele terminów rdzenia i modułów — rzecz, do której się zagląda. |
| [Klatka, komponenty i podział ekranu](architektura/02-slownik/klatka-i-komponenty.md) | Co żyje między klatkami, czego komponentowi nie wolno, jak dzieli się ekran. |
| [Wejście, ognisko i schowek](architektura/02-slownik/wejscie-i-ognisko.md) | Kto dostaje klawisz, kto o tym decyduje, którędy wchodzi cudza treść. |
| [Okno terminala, tryb okienkowy i trzeci tłumacz](architektura/02-slownik/okno-i-tory.md) | Rozmiar okna jako wielkość zmienna, prezentacja poza terminalem, renderer OpenGL. |
| [Zdarzenia, kwerendy i cudze czynności](architektura/02-slownik/rejestry.md) | Trzy drogi rozmowy modułu z aplikacją i z innym modułem — i to, że czwartej nie ma. |
| [Praca poza klatką](architektura/02-slownik/praca-poza-klatka.md) | Dźwięk, takt modułu, praca dłuższa niż jedna klatka. |
| [Miejsca: host, demon, klaster, książka, kosz](architektura/02-slownik/moduly-i-miejsca.md) | Moduły rozmawiające z czymś poza maszyną — wraz z kosztem, jaki każdy położył na rdzeniu. |

## Odwołania spoza tego pliku

Dokumenty starsze niż krok 62 — w tym czterdzieści siedem plików
w [docs/plans/archiwum/](plans/archiwum/) — wskazują rozdziały **numerem**
(„`docs/architecture.md`, rozdz. 8”, „`architecture.md` §2”). Numeracja
rozdziałów jest przez to **trwała**: rozdziału nie przenumerowuje się ani nie
usuwa, a rozdział nowy dostaje kolejny wolny numer. Kotwic (`#…`) nie używa
w repozytorium ani jeden odnośnik, więc podział rozdziałów na pliki nie zabrał
żadnej działającej ścieżki.
