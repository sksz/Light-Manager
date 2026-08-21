# 8. Spisy pod pilnowaniem

> Przewodnik dewelopera, część 8 z 8. [Spis](README.md) ·
> [English](../../en/guide/08-lists.md)

Ten rozdział jest **kopią stanu kodu**, a nie opisem zamiaru. Wiersze poniżej
powstają z tych samych rejestrów, którymi aplikacja wykonuje komendy i zadaje
kwerendy — a testy zgodności z `tests/Documentation/` **czerwienią bramkę**,
gdy przestaną się zgadzać. Dopisanie komendy albo kwerendy bez wiersza tutaj
jest zmianą niedokończoną, dokładnie tak samo jak dopisanie jej bez napisu
w katalogu.

Spisu **nie pisze się z głowy**: otwórz `F12` w działającej aplikacji, przełącz
`Tab`em na kwerendy i zapytaj `core.commands` albo `core.queries` — to ten sam
rejestr, więc odpowiedź jest tym, co ma tu stanąć.

## Komendy

Czynność wywoływana **po nazwie** z okna `F12`, z menu `F9` albo z historii.
Nazwa zaczyna się od identyfikatora właściciela: rdzeń wnosi `core.*`, moduł —
wyłącznie `<id modułu>.*`, a przedrostka pilnuje `CommandRegistry`. Argument
w nawiasach ostrych jest wymagany, w kwadratowych — opcjonalny.

<!-- spis:komendy -->
| Komenda | Argumenty | Co robi |
|---|---|---|
| `address-book.add` | `[nazwa]` `[rozdział]` | dopisz wpis |
| `address-book.chapter` | `<rozdział>` `[klucz tytułu]` | zapowiedz użycie rozdziału |
| `address-book.clear` | `<wpis>` `<rozdział>` `[pole]` | wyczyść pole albo rozdział wpisu |
| `address-book.edit` | `<wpis>` `<rozdział>` | przejdź po polach rozdziału |
| `address-book.field` | `<rozdział>` `<pole>` `<klucz etykiety>` `<rodzaj>` `[wartość domyślna]` `[dopuszczalne wartości]` | zapowiedz użycie pola rozdziału |
| `address-book.forget` | `<rozdział>` | usuń wartości rozdziału ze wszystkich wpisów |
| `address-book.remove` | `<wpis>` | usuń wpis |
| `address-book.rename` | `<wpis>` `[nazwa]` | zmień nazwę wpisu |
| `address-book.set` | `<wpis>` `<rozdział>` `<pole>` `[wartość]` | zapisz wartość pola |
| `address-book.show` | `[rozdział]` | otwórz książkę adresową |
| `audio.add` | `<ścieżka pliku dźwiękowego>` | dopisz utwór do playlisty |
| `audio.hook` | `<nazwa zdarzenia>` `[ścieżka pliku dźwiękowego]` | przypisz dźwięk do zdarzenia |
| `audio.music` | — | włącz muzykę albo ją zatrzymaj |
| `audio.volume` | `<głośność w procentach>` | ustaw głośność muzyki |
| `browser.copy` | `[ścieżka]` | skopiuj zaznaczony wpis do wskazanego katalogu |
| `browser.delete` | `[nazwa]` | usuń zaznaczony wpis |
| `browser.hidden` | — | pokaż lub ukryj wpisy ukryte |
| `browser.jump` | `<ścieżka>` | przejdź do wskazanego katalogu |
| `browser.mkdir` | `[nazwa]` | utwórz katalog w katalogu panelu |
| `browser.move` | `[ścieżka]` | przenieś zaznaczony wpis do wskazanego katalogu |
| `browser.open` | — | wejdź do zaznaczonego katalogu |
| `browser.rename` | `[nazwa]` | zmień nazwę zaznaczonego wpisu |
| `browser.tree` | — | panel jako drzewo albo lista |
| `core.help` | — | otwórz pomoc |
| `core.quit` | — | zakończ pracę |
| `core.settings` | — | otwórz ustawienia |
| `core.theme` | `<motyw>` | ustaw motyw graficzny |
| `docker.build` | `[katalog z plikiem Dockerfile]` | zbuduj obraz z katalogu |
| `docker.down` | `[plik compose albo katalog, w którym leży]` | połóż projekt compose |
| `docker.images` | — | pokaż obrazy |
| `docker.ps` | — | pokaż kontenery |
| `docker.pull` | `[nazwa obrazu wraz z etykietą]` | Pobiera obraz z rejestru |
| `docker.push` | `[nazwa obrazu wraz z etykietą]` | wypchnij obraz do rejestru |
| `docker.up` | `[plik compose albo katalog, w którym leży]` | podnieś projekt compose |
| `file-info.show` | — | pokaż opis zaznaczonego wpisu |
| `k8s.apply` | `[ścieżka]` | Zastosuj plik manifestu w klastrze |
| `k8s.context` | — | Wybierz klaster (kontekst kubectl) |
| `k8s.deploy-image` | — | Wdróż obraz kontenera w klastrze |
| `k8s.get` | `<rodzaj>` | Pokaż zasoby wskazanego rodzaju |
| `k8s.namespace` | — | Zmień przestrzeń nazw |
| `ssh.connect` | `<nazwa wpisu w spisie hostów>` | połącz z hostem ze spisu |
| `ssh.disconnect` | — | zamknij sesję zdalną |
| `ssh.get` | `[katalog docelowy na tej maszynie]` | pobierz zaznaczony plik zdalny |
| `ssh.hosts` | — | pokaż spis hostów |
| `ssh.put` | `[katalog docelowy na hoście]` | wyślij zaznaczony plik lokalny |
<!-- /spis -->

## Kwerendy

**Rejestr kwerend jest jedyną drogą odczytu danych** (reguła 11w). Kwerenda
czyta i nie zmienia — dlatego wolno ją zadać z każdego miejsca i dlatego żadna
z nich nie może niczego zepsuć.

<!-- spis:kwerendy -->
| Kwerenda | Argumenty | Co oddaje |
|---|---|---|
| `address-book.chapters` | — | rozdziały: zadeklarowane i obecne w danych |
| `address-book.entries` | `[rozdział]` | wpisy książki, opcjonalnie z wartościami rozdziału |
| `address-book.entry` | `<wpis>` `[rozdział]` | jeden wpis książki |
| `address-book.fields` | `<rozdział>` | pola rozdziału |
| `address-book.last` | — | identyfikator wpisu dopisanego ostatnio |
| `address-book.value` | `<wpis>` `<rozdział>` `<pole>` | wartość jednego pola, także maskowanego |
| `audio.effects` | — | przypisania dźwięków do zdarzeń aplikacji |
| `audio.now-playing` | — | co gra, w jakim trybie i czy jest czym grać |
| `audio.playlist` | — | pozycje playlisty wraz z brakującymi plikami |
| `browser.cwd` | — | ścieżki obu paneli wraz z czynnym |
| `browser.entries` | `[panel (0 albo 1)]` | wpisy katalogu widocznego w panelu |
| `browser.marked` | `[panel (0 albo 1)]` | nazwy i ścieżki zaznaczonych wpisów |
| `browser.panes` | — | układ paneli: widok, filtr, zaznaczenie |
| `browser.selection` | `[panel (0 albo 1)]` | wpis pod kursorem wraz z jego atrybutami |
| `browser.tree` | `[panel (0 albo 1)]` | drzewo katalogów panelu, spłaszczone |
| `browser.undo` | — | stos operacji wraz z odwracalnością |
| `core.commands` | — | spis czynności wywoływanych po nazwie |
| `core.context` | — | gdzie użytkownik stoi i co ma zaznaczone |
| `core.events` | — | słownik zdarzeń aplikacji |
| `core.jobs` | — | prace tłowe: etap, kod wyjścia, rozmiar wypisu |
| `core.language` | — | język czynny i języki do wyboru |
| `core.module-settings` | `<moduł>` | ustawienia wskazanego modułu |
| `core.modules` | — | moduły: przyjęte, wyłączone i odrzucone |
| `core.queries` | — | spis źródeł danych tego uruchomienia |
| `core.settings` | — | ustawienia rdzenia wraz z wartościami |
| `core.status` | — | ostatni komunikat wraz z tonem |
| `core.theme` | — | motyw czynny i motywy do wyboru |
| `core.version` | — | wersja aplikacji, PHP i obecność rozszerzeń |
| `core.viewport` | — | rozmiar okna i tor rysowania klatki |
| `docker.build` | — | stan budowy: etap, znacznik, ostatni komunikat |
| `docker.catalog` | — | Zawartość rejestru: obrazy albo etykiety |
| `docker.compose` | — | projekty compose wraz z etapem pracy |
| `docker.containers` | — | kontenery wraz z projektem compose |
| `docker.environments` | — | środowiska: nazwa, rodzaj, adres, wybór i stan tunelu |
| `docker.images` | — | obrazy znane demonowi wraz z etykietami |
| `docker.pull` | — | Stan pobierania obrazu |
| `docker.push` | — | stan wypychania obrazu do rejestru |
| `docker.registries` | — | Rejestry obrazów: adres, użytkownik, czy jest token |
| `docker.registry-secret` | `[rejestr]` | Poświadczenie rejestru dla klastra |
| `file-info.description` | — | pełny opis zaznaczonego wpisu |
| `file-info.digest` | — | suma sha256 wraz z etapem liczenia |
| `file-info.preview` | — | miniatura wpisu albo powód jej braku |
| `file-info.usage` | — | zajętość na dysku wraz z etapem pomiaru |
| `k8s.cluster` | — | wersja klastra i klienta oraz etap sesji |
| `k8s.clusters` | — | spis klastrów z książki i z plików kubeconfig |
| `k8s.contexts` | — | konteksty z kubeconfig wraz z bieżącym |
| `k8s.deployments` | — | wdrożenia wraz z obrazem każdego kontenera |
| `k8s.kinds` | — | rodzaje zasobów zgłoszone przez klaster |
| `k8s.namespaces` | — | przestrzenie nazw znane sesji |
| `k8s.resources` | `<rodzaj>` | wiersze zasobu wskazanego rodzaju wraz z etapem |
| `ssh.entries` | — | zdalny katalog wraz z etapem odczytu |
| `ssh.session` | — | etap sesji, host i powód niepowodzenia |
| `ssh.transfer` | — | stan przesyłu: kierunek, plik, bajty, etap |
<!-- /spis -->

## Jak dopisać spis pod pilnowanie

Spis jest **tabelą markdown objętą znacznikiem HTML**. Znacznik mówi testowi,
gdzie patrzeć, a autorowi — że wiersze poniżej są kopią stanu kodu:

```markdown
<!-- spis:kwerendy -->
| Kwerenda | Argumenty | Co oddaje |
|---|---|---|
| `core.theme` | — | motyw czynny i motywy do wyboru |
<!-- /spis -->
```

Cztery rzeczy, o których trzeba wiedzieć, zanim dołożysz własny:

1. **Nazwa znacznika jest tożsamością spisu**, nie tytułem sekcji. Ten sam spis
   w obu drzewach językowych nosi **tę samą nazwę** —
   `DocumentationLanguagePairTest` porównuje właśnie nazwy, a nie nagłówki.
2. **Liczba kolumn jest ustalona** i test bierze komórki po numerze. Kolumna
   dołożona w środku przesuwa wszystkie następne.
3. **Nie każda kolumna jest pilnowana** i to jest rozstrzygnięcie, nie
   przeoczenie: „Wartości" w spisie ustawień zapisuje osiemdziesiąt jeden
   przystanków suwaka jako `20–80`, bo tabela jest dla czytelnika. Maszyna
   pilnuje tego, co da się porównać bez zgadywania — nazwy, argumentu, wartości
   domyślnej, klawisza.
4. **Test do spisu dopisuje się razem z nim.** Spis bez testu jest tabelą, która
   wygląda tak samo poprawnie w dniu, w którym przestaje być prawdą — czyli
   dokładnie tym, przed czym ten rozdział ma bronić.

## Co zrobić, gdy test zgodności się czerwieni

**Pierwsza odpowiedź brzmi: poprawić spis, nie test.** To jest ta sama droga,
którą projekt zamknął dla reguł warstw — strażnika, którego się wycisza, nie ma
po co mieć.

| Co mówi bramka | Co się stało | Co zrobić |
|---|---|---|
| spis komend rozjechał się z rejestrem | Doszła albo zniknęła komenda | Dopisz albo usuń wiersz — w obu językach |
| wartość domyślna pozycji … | Zmieniła się wartość domyślna w kodzie | Popraw kolumnę „Domyślnie" |
| spis klawiszy … rozjechał się z wiązaniami | Doszedł, zniknął albo przeniósł się klawisz | Popraw tabelę tego miejsca |
| odnośniki donikąd | Plik zmienił nazwę albo miejsce | Popraw odnośnik; przy przenosinach kroku planu — także w indeksie |
| drzewa … mają inne dokumenty | Powstał plik tylko w jednym języku | Dopisz odpowiednik; nazwa pliku jest **w języku swojego drzewa** |
| diagram bez zdania opisowego | Diagram wszedł bez akapitu przed nim | Dopisz zdanie mówiące to samo słowami |

Zostaje jeden przypadek, w którym prawdą **nie jest** dokumentacja: gdy test
pokaże, że kod robi coś, czego robić nie miał. Tak wyszła w kroku 63 litera `r`
w module Dockera — działała, ale nie było jej ani w pasku stanu, ani w spisie
pod `F1`. Wtedy poprawia się kod, a nie tabelę; poznaje się to po tym, że
**poprawiona dokumentacja opisywałaby zachowanie, którego nikt nie chciał**.

## Dokąd dalej

- [1. Mapa kodu](01-mapa-kodu.md) — gdzie leży to, co właśnie dopisałeś
- [3. Jak dodać swoją rzecz](03-jak-dodac.md) — osiem przewodników
- [6. Workflow pracy](06-workflow.md) — kolejność procesów i bramka
