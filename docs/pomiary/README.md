# Wzorce pomiarów wydajności

Pliki w tym katalogu to zapisane przebiegi narzędzia `bin/render-bench`
(krok 16). Powstają poleceniem `./bin/render-bench --save`, a odczytuje je
`--compare`.

## Po co leżą w repozytorium

Krok 17 (optymalizacja) ma rozliczyć każdą dźwignię „przed i po”. Punkt
odniesienia trzymany poza repozytorium przepadłby razem z maszyną, na której
powstał — a wtedy rozliczenie sprowadza się do wrażeń. Decyzja o miejscu:
[00-decyzje.md](../plans/00-decyzje.md), D33.

## Nazwa pliku

`RRRR-MM-DD-nazwa.json`. Data w nazwie układa katalog chronologicznie;
`--compare` bez wskazanego pliku bierze **ostatni po nazwie**, a nie po czasie
modyfikacji — kopiowanie plików potrafi przestawić znacznik systemu plików.

Od kroku 35 katalog mieszczą **dwa tory naraz**: terminalowy (Sixel) i okienkowy
(OpenGL, wzorce z `--window`, rozpoznawalne po słowie `window` w podpisie).
Ich liczby są z założenia nieporównywalne — inne fazy, inna jednostka pracy —
więc wybór bez wskazanego pliku bierze najnowszy wzorzec **porównywalny
z bieżącym przebiegiem**, a nie najnowszy w ogóle. Dzięki temu `--compare`
i `--window --compare` trafiają każdy do swojego, choć leżą obok siebie.

## Jak czytać zawartość

| Klucz | Znaczenie |
|---|---|
| `signature` | konfiguracja pomiaru; dwa wzorce o różnych podpisach są **nieporównywalne** i narzędzie odmówi ich zestawienia |
| `environment` | wersja PHP, wersja ImageMagicka, użyty font, data — bez tego liczby nie mają kontekstu |
| `options` | pełny zestaw osi, łącznie z liczbą przebiegów |
| `scenarios.<nazwa>` | mediany trzech faz i sumy, rozmiar bloba, znacznik niestabilności |
| `transfer` | pomiar przesyłu; `null`, gdy przebieg szedł bez terminala |

Czasy są w milisekundach, rozmiary w bajtach.

## Czego wzorzec NIE gwarantuje

**Porównywalność między maszynami.** Wynik zależy od procesora, wersji
ImageMagicka i — co najdotkliwsze — od tego, czym maszyna była zajęta w trakcie
pomiaru. Ten sam kod na tej samej maszynie potrafi dać różnicę kilkudziesięciu
procent, gdy w tle chodzi coś jeszcze. Porównanie ma sens **na tej samej
maszynie, przy tej samej konfiguracji, przy porównywalnym obciążeniu**.

Dwa zabezpieczenia są wbudowane w narzędzie, ale żadne nie usuwa tego
ograniczenia:

- przebieg, w którym `max/min` przekroczy 1,35×, jest oznaczany jako
  niewiarygodny i **nie zostaje zapisany** jako wzorzec;
- wiersz oznaczony jako niestabilny nigdy nie podnosi alarmu o regresji.

Rozrzut wewnątrz przebiegu potrafi być wąski, mimo że cały przebieg jest
równomiernie wolniejszy od wzorca — wtedy narzędzie pokaże „regresję”, której nie
ma. Dlatego wynik `--compare` jest **przesłanką, nie werdyktem**; przy
podejrzeniu regresji powtórz oba przebiegi obok siebie.

## Zanim uruchomisz pomiar

**Zwolnij maszynę** — zatrzymaj kompilacje, kontenery i przeglądarkę — i dopiero
wtedy uruchamiaj `bin/render-bench`. Nie chodzi o kosmetykę wyniku: wzorzec
zapisany na obciążonym hoście **psuje każde następne porównanie**, bo różnica
środowiska udaje wtedy różnicę kodu.

Widać to na parze `2026-08-10-po-kroku-21.json` i `2026-08-10-po-kroku-22.json`:
wszystkie dziewięć wspólnych scenariuszy „potaniało” o 8–18%, choć krok 22 nie
tknął ani jednej klasy, przez którą przechodzą. Cała różnica siedzi w tym, czym
maszyna była zajęta.

Reguła jest zapisana w `.claude/skills/light-manager-conventions/SKILL.md`
(punkt 17) i w `CLAUDE.md`. Para `2026-08-10-po-kroku-22.json`
i `2026-08-10-po-kroku-23.json` pokazuje, jak wygląda to samo porównanie, gdy oba
przebiegi szły na zwolnionym hoście: wspólne scenariusze mieszczą się w ±5%,
a jedyna prawdziwa różnica siedzi tam, gdzie zmienił się kod.

## Po co porównywać, skoro kod „i tak tego nie dotyka”

Bo bywa, że dotyka. Krok 23 dołożył pierwszy w projekcie użytkownik prymitywu
`Bar` o grubości `Fill` — gałęzi, która **istniała od kroku 18, a nigdy nie
została zmierzona**, bo nikt jej nie wołał. Kosztowała 73 ms rysowania na klatkę.
Pomiar był jedynym miejscem, w którym mogło to wyjść na jaw ([00-decyzje.md](../plans/00-decyzje.md), D44).

Krok 24 powtórzył tę lekcję w drugim wariancie: obwódka panelu kosztuje ~13 ms,
ale **nikt tego nie wiedział**, bo obwódki rysowały się wyłącznie na płaszczyźnie
spodniej, pamiętanej między klatkami — w tabeli pomiaru pokazywały się jako
0,0 ms („same ramki”). Dopiero przeniesienie ich do treści wyceniło je na 27 ms za
dwie (D45). Wspólny mianownik obu przypadków: **gałąź wykonywana dotąd raz na
uruchomienie po przeniesieniu do klatki kosztuje tyle, ile nikt nie sprawdził.**

Z tego samego powodu próg regresji świadomie nie jest pilnowany testem w bramce
jakości ([00-decyzje.md](../plans/00-decyzje.md), D28).
