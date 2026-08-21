# Konwencja changeloga

Dokument źródłowy dla [`CHANGELOG.md`](../CHANGELOG.md) w korzeniu repozytorium.
Tamten plik zawiera **wyłącznie listę zmian** — wszystko, co mówi „jak go pisać
i jak go czytać”, stoi tutaj.

## Po co jest changelog

Changelog odpowiada na pytanie **„co ta aplikacja dostała i kiedy”** — zadawane
przez kogoś, kto jej używa albo o niej decyduje, a nie przez kogoś, kto ją pisze.
Historia techniczna ma trzy inne miejsca i changelog ich nie zastępuje:

| Pytanie | Miejsce |
|---------|---------|
| Co aplikacja umie i od kiedy | `CHANGELOG.md` |
| Co jest zrobione, co w planie | [`docs/plans/00-index.md`](plans/00-index.md) |
| Dlaczego tak, a nie inaczej | [`docs/plans/00-decyzje.md`](plans/00-decyzje.md) |
| Jak to działa w środku | [`docs/architecture.md`](architecture.md) |

Wpisy w commitach są zbiorcze („Rozbudowa” dwadzieścia razy) i **nie są źródłem
changeloga** — źródłem są ukończone kroki planu wraz z ich statusami i datami.

## Numeracja: `faza.krok.poprawka`

- **Pierwsza liczba — faza planu.** Faza XVIII to `18.x`.
- **Druga — kolejny krok w obrębie fazy**, liczony od 1, wedle kolejności
  numerów kroków w planie, a nie kolejności wykonania. Faza XVIII złożona
  z kroków 51–54 daje `18.1.0`–`18.4.0`.
- **Trzecia — poprawka do wydanego już kroku.** Zostaje na zmiany, które nie są
  nowym krokiem: naprawiony błąd, uzupełniony napis, poprawiony skutek uboczny.

Numeracja idzie **wedle numerów faz, nie kalendarza**. Fazy nie zawsze
wykonywały się po kolei (VIII i IX przed VII, XVI przed XIV), więc porządek
w pliku jest numeryczny, a prawdę o czasie mówią daty przy wpisach.

## Nazwy wydań

Każda faza dostaje **nazwę instrumentu muzycznego, dobraną do tego, co
przyniosła** — Katarynka dla fazy, w której jedno polecenie uruchamia resztę,
Akordeon dla okna, które się rozciąga, Kamerton dla fazy bez nowych funkcji,
spłacającej długi. Nazwa jest własnością fazy, nie pojedynczego kroku.

Nazwy **nie wymyśla się samodzielnie** — tak samo jak numeru wydania. Jedno
i drugie uzgadnia się z użytkownikiem (zobacz [CLAUDE.md](../CLAUDE.md)).

## Sekcja „Niewydane”

Krok ukończony w fazie, która **jeszcze się nie domknęła**, trafia do sekcji
„Niewydane” na początku pliku — z datą, bez numeru. Sekcja:

- nosi już nazwę instrumentu i numer fazy, żeby było wiadomo, czego dotyczy,
- kończy się zdaniem o tym, co w tej fazie jeszcze przed nami,
- **nie podnosi wersji w `composer.json`**.

Domknięcie fazy zamienia sekcję w zwykłe wydanie: wpisy dostają numery,
a wersja w `composer.json` skacze na najwyższy z nich.

## Wersja w `composer.json`

Pole `version` w [`composer.json`](../composer.json) ma się zgadzać
z **najwyższym wydanym numerem** w `CHANGELOG.md` — dziś `21.5.0`. Sekcja
„Niewydane” tego pola nie rusza.

## Jak pisać wpisy

- **Jeden wpis na krok planu**, jedno–dwa zdania.
- **Maksimum treści biznesowej, minimum technicznej.** Wpis mówi, co użytkownik
  dostał, a nie jaka klasa powstała. Nazwy klas, wzorców i warstw nie wchodzą do
  changeloga w ogóle — od tego jest `docs/architecture.md`.
- **Język potoczny.** „Kopiowanie płyty nie zacina obrazu”, nie „zoptymalizowano
  potok operacji blokujących”.
- **Klawisze i widoczne skutki wolno podawać** — to jest treść dla użytkownika,
  a nie szczegół implementacji.
- **Liczba wchodzi, gdy coś znaczy dla użytkownika** — „32 MB w sekundę”,
  „dziesięciokrotne przyspieszenie”, „katalog z dziesięcioma tysiącami wpisów”.
- **Wewnątrz fazy wpisy idą malejąco**, jak cały plik: najnowszy krok na górze.
- **Faza może dostać jedno zdanie wprowadzenia** pod nagłówkiem, jeśli bez niego
  nie widać, o co w niej chodziło.

## Skąd brać daty

Data wpisu to **data ukończenia kroku**, wzięta ze statusu w pliku kroku
(`docs/plans/archiwum/NN-*.md`, wiersz „Ukończony …” albo ostatni wpis
dziennika kroku). Nie z commitów — te są zbiorcze i mówią o dniu wypchnięcia
zmian, a nie o dniu, w którym funkcja zaczęła działać.
