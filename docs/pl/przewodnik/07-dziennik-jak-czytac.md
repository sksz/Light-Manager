# 7. Jak czytać dziennik decyzji

> Przewodnik dewelopera, część 7 z 7. [Spis](README.md) ·
> [English](../../en/guide/07-decision-log.md)

## Czym dziennik jest, a czym nie

[`docs/plans/00-decyzje.md`](../../plans/00-decyzje.md) to **110 wpisów
i 8666 wierszy** — największy dokument w repozytorium, większy od architektury
czterokrotnie. Nie czyta się go od deski do deski i nikt tego nie oczekuje.

**Zdanie graniczne: dziennik mówi, dlaczego tak wyszło, a nie co obowiązuje
dziś.** Obowiązujące stoi w [rozdziałach architektury](../../architektura/)
i w [`SKILL.md`](../../../.claude/skills/light-manager-conventions/SKILL.md).
Wpis sprzed czterdziestu kroków opisuje stan świata z tamtego dnia — łącznie
z wariantami, które **odrzucono**, i z założeniami, które później upadły.

| Pytanie | Miejsce |
|---|---|
| Co obowiązuje dziś? | architektura, `SKILL.md` |
| Dlaczego tak, a nie inaczej? Co odrzucono i dlaczego? | **dziennik decyzji** |
| Co jest zrobione, co w planie? | [plan](../../plans/00-index.md) |
| Co aplikacja dostała i kiedy? | [`CHANGELOG.md`](../../../CHANGELOG.md) |

## Kształt wpisu

Każdy wpis ma tę samą budowę i warto ją znać, bo pozwala czytać **wybiórczo**:

| Sekcja | Co w niej jest | Kiedy ją czytać |
|---|---|---|
| **Dotyczy** | pliki, kroki i mechanizmy, których wpis sięga | żeby sprawdzić, czy to o twojej sprawie |
| **Data** | kiedy zapadła i **czy przed pierwszą linią kodu** | żeby wiedzieć, czy to decyzja projektowa, czy skutek |
| **Co rozstrzygnęło rozpoznanie** | liczby policzone w repozytorium przed pytaniem | to jest najgęstsza część — **fakty, nie opinie** |
| **Decyzje użytkownika** | rozstrzygnięcia, każde z odrzuconymi wariantami | to jest właściwa treść wpisu |
| **Co z tego wynika** | zobowiązania dla kolejnych kroków | jeśli piszesz krok, który ten wpis wiąże |

**Odrzucone warianty są w tym dzienniku najcenniejsze.** Wpis mówi nie tylko, co
wybrano, ale **czego nie i za jaką cenę** — a to jest dokładnie ta wiedza, której
nie da się odtworzyć z kodu.

## Trzy wpisy do przeczytania przed pierwszą większą zmianą

### D40 — rdzeń przestaje wiedzieć o plikach

*„Menadżer plików jako moduł domyślny: rdzeń przestaje wiedzieć o plikach"*

Wpis, po którym cała domena plikowa wyprowadziła się z `src/Domain/` do
`src/Module/Browser/`. Czytaj go, jeśli zastanawiasz się, **dlaczego rdzeń jest
tak chudy** i dlaczego `Entry`, `Directory` i `DirectoryPath` nie mają prawa
pojawić się w sygnaturze niczego w `Application` ani `Domain`.

### D92 — rejestr kwerend jedyną drogą odczytu

*„Kwerendy obejmują wszystkie źródła danych rdzenia i sześciu modułów, rejestr
staje się jedyną drogą odczytu"*

Wpis, który zamknął pytanie „skąd wziąć cudze dane". Czytaj go, zanim sięgniesz
po obiekt innego modułu — a zwłaszcza wtedy, gdy wydaje ci się, że w twoim
przypadku kwerenda jest przesadą.

### D48 — otwarcie zamkniętego słownika prymitywów

*„Sześć nowych komponentów rdzenia, rytm «jeden komponent — jeden krok»
i otwarcie zamkniętego słownika prymitywów"*

Jedyny raz, kiedy słownik prymitywów otwarto. Czytaj go, zanim zaproponujesz
nowy kształt — zobaczysz tam, **ile kosztowała zgoda** i dlaczego wyjściowa
propozycja i tak upadła jako synonim istniejącego prymitywu. Zobacz też
[rozdz. 4](04-zanim-dolozysz.md).

## Jak w nim szukać

Dziennik nie ma spisu treści i **nie potrzebuje go**: numery wpisów są
chronologiczne, a wyszukiwanie działa lepiej.

```bash
grep -n "^### D" docs/plans/00-decyzje.md          # spis wszystkich wpisów
grep -n "kwerend" docs/plans/00-decyzje.md | head  # wpisy o kwerendach
grep -n "^### D92" -A 40 docs/plans/00-decyzje.md  # jeden wpis
```

Trzy drogi dojścia, których warto używać zamiast czytania po kolei:

- **Od reguły.** Rozdział architektury i `SKILL.md` podają przy regule numer
  decyzji (`D42`, `D87`, `D101`) — to jest najkrótsza droga.
- **Od kroku.** Plik kroku w [`docs/plans/archiwum/`](../../plans/archiwum/) ma
  sekcję „Rozstrzygnięcia startowe" wskazującą swój wpis.
- **Od objawu.** [Spis pułapek](05-pulapki.md) podaje przy każdej pozycji krok,
  w którym projekt za nią zapłacił — a dziennik tego kroku niesie całą historię.

## Czego w dzienniku nie zmieniać

Wpisy są **dokumentami zamkniętymi**. Nie poprawia się ich, gdy świat się
zmieni — to samo dotyczy plików kroków w archiwum. Ustalenie, które przestało
obowiązywać, odwołuje się **nowym wpisem**, a nie przepisaniem starego; inaczej
projekt traci to, co w dzienniku najcenniejsze: **ślad, że ktoś kiedyś myślał
inaczej i dlaczego**.

Ta sama zasada dotyczy zastrzeżeń: zastrzeżenie przy kroku ukończonym opisuje
**granicę dowiezionego zakresu**, a nie dług do spłacenia w tym pliku.

## Dokąd dalej

- [1. Mapa kodu](01-mapa-kodu.md) — gdzie co leży
- [3. Jak dodać swoją rzecz](03-jak-dodac.md) — osiem przewodników
- [Mapa dokumentacji](../../README.md) — który dokument za co odpowiada
