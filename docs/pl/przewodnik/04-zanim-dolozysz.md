# 4. Zanim dołożysz

> Przewodnik dewelopera, część 4 z 8. [Spis](README.md) ·
> [English](../../en/guide/04-before-you-add.md)

Dwie rzeczy w tym projekcie mają własny rozdział nie dlatego, że są trudne, tylko
dlatego, że **odpowiedź na nie prawie zawsze brzmi „nie"** — a każda zgoda
kosztuje więcej, niż widać z miejsca, z którego się o nią prosi.

---

## Nowy prymityw

**Odpowiedź domyślna: nie.** Słownik prymitywów jest **zamknięty od kroku 18**
i przez dwanaście kroków nikt go nie ruszył. Otwarto go **raz**, w kroku 30, na
jawną zgodę użytkownika — i do dziś ma siedem kształtów.

### Dlaczego to jest drogie

Kształt nie jest jedną klasą. Jest **obowiązkiem dla trzech rendererów naraz**:
sixelowego (Imagick, kwantyzacja do palety), tekstowego (bufor komórek, bajty
ANSI) i okienkowego (OpenGL). Renderer bez tłumaczenia nowego kształtu przewraca
`PrimitiveTranslationTableTest`, a kształt narysowany w dwóch torach z trzech
znaczy aplikację, która wygląda inaczej w zależności od terminala.

### Próba, którą trzeba przejść

**Sprawdź, czy twój kształt nie jest którymś z istniejących pod inną nazwą.**
Projekt ma na to dwa precedensy i oba skończyły się „nie":

- **Karetka pola tekstowego (krok 19)** — udała podświetlenie parą
  „wypełnienie plus napis", z istniejących prymitywów.
- **Prostokąt zaznaczenia treści (krok 56)** — okazał się `TextMark`iem
  nałożonym na wiersz.

A gdy w kroku 30 słownik naprawdę otwarto, **wyjściowa propozycja i tak
upadła**: „samo tło pod fragmentem" było synonimem `Bar`a z `Weight::Fill`.
Kształt, który wszedł, musiał być czymś, czego żaden z pozostałych nie umie —
**związaniem pisma z tłem w jednej rzeczy**.

### Jeśli mimo to uważasz, że trzeba

1. Wypisz, czego **żaden z siedmiu** nie umie — jednym zdaniem, bez „byłoby
   wygodniej".
2. Policz, ile kosztuje w każdym z trzech torów.
3. **Zapytaj użytkownika.** To jest świadoma decyzja architektoniczna, nie
   szczegół implementacji.
4. Zgoda dotyczy **otwarcia słownika**, a nie kształtu — kształt rozstrzyga się
   dopiero przy rozpisaniu.

---

## Zmiana w rdzeniu zamiast modułu

**Odpowiedź domyślna: nie.** Reguła 15 brzmi: **nowa funkcja to moduł
w `src/Module/`, nie zmiana w rdzeniu**. Rdzeń jest powłoką — pętlą, klatką,
oknami i paskiem stanu — a nie miejscem, w którym rośnie funkcjonalność.

### Jedyny nazwany wyjątek

Reguła 15 ma **dokładnie jeden** wyjątek i jest on nazwany: **zapis na dysk**.
Rdzeń ma porty operacji na plikach, kopiowania i kosza, choć operacji
potrzebuje dziś głównie jeden moduł. Powód nie jest wygodą, tylko **drugą regułą
tej samej pary**: skoro moduł nie sięga do innego modułu, dwaj odbiorcy znaczą
dwie kopie kodu piszącego po dysku — a powtórzone `unlink()` kosztuje utratę
danych w dwóch miejscach zamiast w jednym.

Granica wyjątku jest wąska i wyznaczona wprost: rdzeń zna **ścieżkę jako napis**,
**nazwę jako napis** (bez oceny, czy jest poprawna), **dziewięć czynności**
i **stan tej pracy**. Nie zna wpisu, katalogu, sortowania, ukrywania,
zaznaczenia ani podglądu — `Entry`, `Directory`, `DirectoryPath` i `EntryType`
nie mają prawa trafić do sygnatury niczego w `src/Application` ani `src/Domain`.
Pilnuje tego `CoreKnowsNothingAboutFilesTest`.

Dla kontrastu: **powtórzony rachunek `permissionsAsText()` wolno było zostawić**
w dwóch modułach, bo kosztował dziesięć linii bez skutków ubocznych. Nie każde
powtórzenie jest długiem.

### Próba, którą trzeba przejść

Funkcja, która chce wejść do rdzenia tym samym argumentem, musi mieć **oba**:

1. **dwóch odbiorców** — nie jednego i nadziei na drugiego;
2. **powtórzenie o koszcie nieodwracalnym** — utrata danych, uszkodzenie stanu,
   sekret w niewłaściwym miejscu. Nie „dziesięć linii dwa razy".

Inaczej **jest modułem, jak wszystko inne**.

### Trzy rzeczy, które wyglądają na wyjątek, a nim nie są

| Wygląda na | Jest |
|---|---|
| „mój moduł potrzebuje danych innego modułu" | **kwerendą** — `QueryRegistry` jest jedyną drogą odczytu |
| „mój moduł musi kazać coś zrobić innemu" | **komendą** — moduł zamawia cudzą czynność rejestrem komend |
| „mój moduł musi wiedzieć, że coś się stało" | **zdarzeniem** — `EventRegistry`, ogłoszenie i odbiór |

Wzór, na który warto spojrzeć: **`k8s.deploy-image`** — jedyna czynność
przechodząca przez dwa moduły. Idzie komendą, zdarzeniem i kwerendą, a nie
wspólną klasą w rdzeniu.

### Wspólne miejsce a powtórzenie

Gdy ten sam wzorzec staje **po raz trzeci**, reguła każe zadać pytanie: czy to
nadal powtórzenie, czy już wspólne miejsce? Projekt przeszedł to raz — trzy
książki wpisów (`ssh.json`, `docker.json`, `k8s.json`) zeszły do **jednego
rejestru**, czyli do **modułu książki adresowej**, a nie do rdzenia.

To jest wzór odpowiedzi: wspólne miejsce zwykle jest **modułem**, nie rdzeniem.

---

## Dokąd dalej

- [3. Jak dodać swoją rzecz](03-jak-dodac.md) — osiem przewodników
- [5. Pułapki](05-pulapki.md) — dziesięć rzeczy, które projekt już zapłacił
