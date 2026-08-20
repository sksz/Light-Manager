# 5. Dokąd dalej

> Onboarding, przystanek 5 z 5 · **5 minut**. [Spis](README.md) ·
> [English](../../en/onboarding/05-where-next.md)

Ścieżka się kończy. Ten przystanek nie uczy niczego nowego — **daje adresy**,
żebyś od jutra szukał od razu w dobrym miejscu.

## Mapa dokumentacji jednym akapitem

Dokumentacja tego projektu dzieli się wedle **pytania, na które odpowiada**, a
nie wedle tematu. **Architektura mówi, jak jest; przewodnik — jak to zrobić;
podręcznik — jak tego użyć; dziennik — dlaczego tak wyszło.** Reguła powstaje
w rozdziale architektury i tylko tam; skrót w `SKILL.md` ją streszcza i gdy oba
mówią co innego, rację ma rozdział. Tekst, który odpowiada na dwa z tych pytań
naraz, jest w złym miejscu — i to nie jest wada stylu, tylko wada adresu.
Pełna mapa: [`docs/README.md`](../../README.md).

## Trzy pytania, trzy odpowiedzi

**Gdzie jest reguła?** W [`docs/architektura/`](../../architektura/) — dziewięć
rozdziałów, spis w [`docs/architecture.md`](../../architecture.md). Osiemnaście
reguł twardych i sześciu strażników w testach. Reguły nie szuka się w Skillu ani
w `CLAUDE.md`: oba są skrótami, a skrót nie jest źródłem.

**Gdzie jest historia?** W [`docs/plans/`](../../plans/) — kroki planu wraz ze
statusami ([`00-index.md`](../../plans/00-index.md)) i dziennik decyzji
([`00-decyzje.md`](../../plans/00-decyzje.md)): ponad sto wpisów o tym, co
wybrano, **co odrzucono i dlaczego**. Jak go czytać, żeby się nie utopić —
[przewodnik, rozdz. 7](../przewodnik/07-dziennik-jak-czytac.md).

**Gdzie jest przewodnik?** W [`docs/pl/przewodnik/`](../przewodnik/README.md) —
siedem rozdziałów dla tego, kto dokłada swoją rzecz. Zacznij od tych trzech:

| Chcę… | Idź do |
|---|---|
| dołożyć coś i nie wiem gdzie | [mapa kodu](../przewodnik/01-mapa-kodu.md) → [jak dodać](../przewodnik/03-jak-dodac.md) |
| zrozumieć, dlaczego coś nie działa | [dziesięć pułapek](../przewodnik/05-pulapki.md) — pisane objawem |
| wiedzieć, czy wolno mi tknąć rdzeń | [zanim dołożysz](../przewodnik/04-zanim-dolozysz.md) — odpowiedź prawie zawsze brzmi „nie” |

## Cztery zdania, które warto zabrać ze sobą

1. **Nowa funkcja to moduł, nie zmiana w rdzeniu.** To, że rdzeń trzeba by
   tknąć, jest sygnałem błędu w projekcie modułu — nie powodem do wyjątku.
2. **Moduł nie sięga do innego modułu.** Trzy drogi rdzenia: komenda (zrób),
   kwerenda (powiedz), zdarzenie (stało się). Czwartej nie ma.
3. **Rejestr kwerend jest jedyną drogą odczytu danych.**
4. **Napis nie mieszka w kodzie**, tylko w katalogu — w obu językach naraz.

## Czego onboarding celowo nie zrobił

Nie pokazał ci ekranu ani komponentu, nie tknął pętli klatki, nie tłumaczył
prymitywów, nie wspomniał o pomiarze wydajności. To wszystko jest w przewodniku
i w architekturze — i przyjdzie wtedy, gdy będzie potrzebne. **Onboarding ma
jedno zadanie: doprowadzić cię do pierwszej zielonej bramki**, a nie zastąpić
resztę dokumentacji.

Jedno miejsce, o którym warto wiedzieć od razu, bo dotyczy sposobu pracy, a nie
kodu: [przewodnik, rozdz. 6](../przewodnik/06-workflow.md) — kolejność procesów,
bramka, testy, pomiar, budowa. `make` bez argumentów wypisuje wszystkie wejścia.

## Skąd wiesz, że skończyłeś

Umiesz odpowiedzieć bez zaglądania tutaj: **gdzie leży reguła, gdzie historia,
gdzie przewodnik.** Jeśli któraś z odpowiedzi nie przychodzi — wróć akapit
wyżej; to jedyna rzecz z tej ścieżki, którą warto pamiętać na pamięć.
