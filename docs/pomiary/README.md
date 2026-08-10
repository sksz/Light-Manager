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

Z tego samego powodu próg regresji świadomie nie jest pilnowany testem w bramce
jakości ([00-decyzje.md](../plans/00-decyzje.md), D28).
