# 3. Oglądanie

> Onboarding, przystanek 3 z 5 · **5 minut**. [Spis](README.md) ·
> [English](../../en/onboarding/03-looking-around.md)

Jedna wycieczka, pięć przystanków w środku aplikacji. Celem nie jest nauczenie
się klawiszy — pełny spis jest pod `F1` i nie ma go po co przepisywać. Celem
jest **jedno zdanie na koniec: umiesz zapytać aplikację o jej własny stan.**

## 1. Przejdź katalog

| Klawisz | Co robi |
|---|---|
| `↑` / `↓` | zmiana zaznaczenia |
| `Enter` / `→` | wejście do katalogu |
| `Backspace` / `←` | katalog wyżej |
| `/` | zawężenie listy fragmentem nazwy (`Esc` zdejmuje) |

**Pasek stanu na dole jest ściągawką** i zmienia się razem z tym, co masz pod
ręką. Nie obiecuje klawiszy „w ogóle” — obiecuje te, które zadziałają teraz.

## 2. Otwórz okno komend (`F12`)

Czynność wywołuje się **po nazwie**, zamiast szukać dla niej wolnego klawisza.
Przy pustym polu lista pokazuje najpierw historię, a pod nią komplet komend.
Nazwy mają przestrzeń właściciela: rdzeń wnosi `core.*`, moduł — wyłącznie
`<id modułu>.*`.

Spróbuj `core.theme` i przejrzyj listę strzałkami. `Esc` zamyka okno.

## 3. Przełącz okno na kwerendy (`Tab` przy pustym wierszu)

**To jest ten moment, dla którego jest cały ten przystanek.** Kwerendy to
pytania, na które aplikacja odpowiada o samej sobie — i **rejestr kwerend jest
w tym projekcie jedyną drogą odczytu danych**, nie jedną z kilku.

Zadaj trzy:

| Kwerenda | Odpowiada na pytanie |
|---|---|
| `core.viewport` | jaki jest rozmiar okna i **którym torem** rysuje się klatka |
| `core.modules` | które moduły weszły, które są wyłączone, a które odrzucone **wraz z powodem** |
| `core.queries` | jakie źródła danych ma to uruchomienie — czyli spis wszystkich kwerend |

`Enter` zadaje pytanie, `Alt`+`C` kopiuje całą odpowiedź, `Tab` wraca do
komend. **Żadna kwerenda nie może niczego zepsuć**, bo kwerenda czyta i nie
zmienia — dlatego wolno je klikać bez namysłu.

Zwróć uwagę na `core.queries`: spis, który właśnie zobaczyłeś, **powstaje z tego
samego rejestru, którym aplikacja te kwerendy wykonuje**. Nie ma drugiego
miejsca, w którym ktoś by go przepisał — i o jedną pozycję na tej liście
rozszerzysz aplikację na następnym przystanku.

## 4. Zajrzyj do menu kontekstowego (`F9`)

Menu pokazuje czynności sensowne **tutaj i teraz** — dla tego wpisu, w tym
ekranie. To ta sama lista komend, co w `F12`, tylko zawężona kontekstem.

## 5. Wejdź do modułu (`Ctrl`+litera)

`Ctrl`+`D` — opis zaznaczonego pliku. `Ctrl`+`W` — książka adresowa. `Esc`
wraca do przeglądarki plików, z każdego ekranu, zawsze.

**Modułu, którego maszyna nie udźwignie, po prostu nie będzie na liście — wraz
z powodem.** Jeśli `Ctrl`+`O` albo `Ctrl`+`S` nie robi nic, to nie jest
usterka: zapytaj `core.modules`, a dostaniesz zdanie, dlaczego moduł odpadł.
Wszystko poza pętlą, klatką i oknami jest w tej aplikacji modułem — i to jest
też miejsce, w którym za chwilę dołożysz swój.

## Skąd wiesz, że skończyłeś

**Umiesz zapytać aplikację o jej własny stan.** Konkretnie: potrafisz otworzyć
`F12`, przełączyć `Tab`em na kwerendy, zadać `core.modules` i przeczytać z
odpowiedzi, które moduły weszły do tego uruchomienia, a które nie i dlaczego.

Dalej: [4. Pierwsza zmiana](04-pierwsza-zmiana.md).
