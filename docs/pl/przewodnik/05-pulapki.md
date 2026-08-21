# 5. Pułapki

> Przewodnik dewelopera, część 5 z 8. [Spis](README.md) ·
> [English](../../en/guide/05-traps.md)

Dziesięć rzeczy, za które projekt **już raz zapłacił** — pomiarem, żywym
serwerem, dwoma dniami szukania albo utraconą treścią. Każda ma **objaw**,
**przyczynę** i **miejsce, w którym rachunek przyszedł**.

To jest najdroższa wiedza w tym repozytorium i zarazem najgorzej dostępna: leży
w dziennikach kroków, czyli tam, gdzie nikt nie zajrzy, **zanim nie wpadnie
w tę samą**.

Siedem pierwszych pochodzi z kroków 26–55; trzy ostatnie dołożyły Fazy XIX–XXI.

---

## 1. `2>&1` przy poleceniu, którego wyjściem jest treść

**Objaw.** Lista zdalnego katalogu urywa się w połowie. Bez błędu, bez
ostrzeżenia — proces kończy się **kodem zero**.

**Przyczyna.** Scalenie strumieni sprawia, że strumień błędów potomka i potok
z treścią to **ten sam opis pliku**. `sftp` uruchamia `ssh`, a ten przy
`ControlPath` jest klientem multipleksera i przekazuje deskryptory mistrzowi
połączenia, który ustawia im **tryb nieblokujący**. Tryb jest własnością opisu
pliku, więc wraca tym samym potokiem na wyjście `sftp`; gdy potok się zapełni,
`write()` zwraca `EAGAIN`, a OpenSSH **porzuca porcję wypisu** i kończy się
pomyślnie.

**Gdzie zapłacono.** Krok 49. Komplet wypisu to **418 922 B**; przez potok
czytany co 33 ms przychodziła **jedna trzecia**. Przyczynę odtworzono bez PHP,
samą powłoką z pauzami — żeby wykluczyć język.

**Reguła.** Polecenie, którego **wyjściem jest treść**, nie scala strumieni.
Lista idzie wyjściem, powód niepowodzenia — osobnym polem portu. Pilnują tego
`SftpCommandTest::testStreamsAreNeverMerged`
i `BackgroundProcessServiceTest::testLargeOutputSurvivesFrameRateDraining`.

---

## 2. `kubectl patch --type=merge` podmienia całą tablicę

**Objaw.** Wdrożenie o dwóch kontenerach po załataniu ma jeden.

**Przyczyna.** Łata scalająca (`--type=merge`) traktuje tablicę JSON jako
**wartość**, nie jako zbiór — więc podana lista zastępuje istniejącą w całości,
zamiast się do niej dopisać.

**Gdzie zapłacono.** Krok 54. Krok 61 wracał do tego pytania przy
`imagePullSecrets` i **sprawdził tezę na żywym klastrze, zanim napisał kod**:
łata **strategiczna** (domyślna dla zasobów wbudowanych) ma dla tego pola klucz
scalania po nazwie, więc dopisuje. Różnica między „dopisze" a „podmieni" to
w tym miejscu utrata dostępu wdrożenia do własnego obrazu.

**Reguła.** Rodzaj łaty rozstrzyga się **sprawdzeniem, nie pamięcią** — i robi
się to przed pierwszą linią kodu.

---

## 3. `base64_encode()` w `X-Registry-Auth`

**Objaw.** Demon Dockera odrzuca wypchnięcie obrazu z kodem `401`, choć
poświadczenie jest poprawne.

**Przyczyna.** Nagłówek chce base64 **wedle URL i bez dopełnienia**. Zwykłe
`base64_encode()` daje napis ze znakami `+`, `/` i `=`, którego demon nie
przyjmuje.

**Gdzie zapłacono.** Kroki 54 i 61 (`RegistryAuth`).

**Reguła.** Kodowanie w nagłówku sprawdza się w specyfikacji, a nie zakłada
z nazwy funkcji.

---

## 4. `rename()` nie zawsze jest operacją na metadanych

**Objaw.** Przeniesienie „natychmiastowe" zajmuje minutę i blokuje klatkę,
a przerwane zostawia plik w połowie.

**Przyczyna.** PHP obsługuje `EXDEV` **sam** — gdy źródło i cel leżą na różnych
systemach plików, kopiuje plik **w środku wywołania `rename()`**. Z zewnątrz
wygląda to jak jedna szybka operacja, a jest pełnym kopiowaniem bez postępu
i bez możliwości przerwania.

**Gdzie zapłacono.** Krok 42.

**Reguła.** „Ten sam system plików" sprawdza się **numerem urządzenia** przed
wywołaniem, a nie po skutkach. Między systemami idzie się drogą jawną:
skopiuj, potem usuń źródło — i **źródło znika dopiero po zapisaniu celu
w całości**.

---

## 5. Tryb surowy zostawia włączone `isig` i `iexten`

**Objaw.** `Ctrl`+`C` kończy aplikację, zanim ta przeczyta cokolwiek.
`Ctrl`+`V` połyka następny bajt — i klawisz po nim ginie.

**Przyczyna.** Projekt **nie używa pełnego `stty raw`**: `isig` zostaje
włączone celowo, żeby `Ctrl`+`C` nadal był sygnałem, a `iexten` — bo wyłączenie
go zabiera inne rzeczy. Skutkiem ubocznym `lnext = ^V` połyka bajt następujący
po nim.

**Gdzie zapłacono.** Krok 55, przy wprowadzaniu wskaźnika.

**Reguła.** Sześć liter jest **zajętych przez terminal** i nie wolno ich brać na
skróty: `c` i `z` są sygnałami, a `h`, `i`, `j` i `m` przychodzą tym samym
bajtem, co `Backspace`, `Tab` i `Enter`.

---

## 6. Potomek nie dostaje wejścia

**Objaw.** `kubectl apply -f -` nie działa. Podanie hasła procesowi `ssh` przez
wejście nie działa. Żadne z nich nie zgłasza czytelnego błędu.

**Przyczyna.** Port pracy tłowej **nie podaje potomkowi wejścia** — a `ssh`
i tak czyta hasło z **terminala sterującego**, nie ze standardowego wejścia.

**Gdzie zapłacono.** Kroki 48, 52 i 58.

**Reguła.** Treść wchodzi do potomka **plikiem** (`kubectl apply -f plik`,
poświadczenie rejestru plikiem `0600` kasowanym po użyciu), a hasło —
**`SSH_ASKPASS` i zmienną środowiskową**. Nigdy wierszem polecenia: `ps` widzi
wiersz polecenia.

---

## 7. Kod wyjścia różny od zera nie jest sam z siebie niepowodzeniem

**Objaw.** Zajętość katalogu nie pokazuje się, choć `du` policzyło i wypisało
wynik.

**Przyczyna.** `du` kończy się **jedynką** za każdy katalog, którego nie
przeczytało (brak uprawnień) — i mimo to podaje sumę tego, co przeczytało.

**Gdzie zapłacono.** Krok 26.

**Reguła.** Kod wyjścia interpretuje **zamawiający pracę**, bo tylko on wie, co
dla jego polecenia znaczy. Port oddaje kod, a nie werdykt.

---

## 8. Kwerenda wołana co takt

**Objaw.** Nic nie widać. Aplikacja działa normalnie — a materiał
uwierzytelnienia przechodzi przez rejestr kwerend **trzydzieści razy na
sekundę**.

**Przyczyna.** Takt modułu wołał kwerendę oddającą poświadczenie rejestru przy
**każdym** takcie i odrzucał odpowiedź przy wszystkich poza tym jednym,
w którym rejestr się zmienił. Kod wyglądał niewinnie: „weź bieżące
poświadczenie i sprawdź, czy się zmieniło".

**Gdzie zapłacono.** Krok 61 — znalezione **przy pomiarze**, nie przy przeglądzie
kodu. Ta sama pułapka co w kroku 59, gdzie moduł pytał klaster o wersję serwera
co klatkę.

**Reguła.** Rzecz kosztowna albo wrażliwa idzie **domknięciem, nie wartością**:
takt woła je wyłącznie wtedy, gdy naprawdę zmienia punkt końcowy. Pilnuje tego
osobny przebieg **liczący wywołania** — bo moduł pytający raz wygląda dokładnie
tak samo, jak moduł pytający bez końca.

---

## 9. Kanał „zabierz raz" ma jednego odbiorcę

**Objaw.** Jeden z dwóch odbiorców nigdy nic nie widzi. **Po cichu** — bo
`null` znaczy tam „jeszcze nic się nie stało", a nie „ktoś już to zabrał".

**Przyczyna.** Skutek pracy zabiera się **raz** (`takeOutcome()`). Gdy do tego
samego kanału podepnie się drugi odbiorca, pierwszy zabiera wynik, a drugi
dostaje `null` i uznaje, że praca trwa.

**Gdzie zapłacono.** Krok 61 — dwie rzeczy naraz: koordynator czynności
prowadzący **jedną czynność naraz** dostał drugie `begin()`, które porzuciło
pierwsze, a kanał wyniku miał dwóch odbiorców.

**Reguła.** **Nowy odbiorca znaczy nowy kanał, nie podział istniejącego.**
Objaw drugiego odbiorcy jest zawsze ten sam: coś działa „czasami".

---

## 10. Klawisz obsługiwany bez `KeyBinding`u

**Objaw.** Klawisz działa, ale **nie ma go w pasku stanu ani w spisie pod
`F1`** — więc dla użytkownika nie istnieje.

**Przyczyna.** Obsługa klawisza (`handle()`) i jego ogłoszenie (`bindings()`) to
dwa różne miejsca. `StatusHintsFlowTest` pilnuje, żeby pasek stanu **nie
obiecywał** klawisza, którego nikt nie obsługuje — ale **nie** pilnuje kierunku
odwrotnego.

**Gdzie zapłacono.** Krok 63, przy wywodzeniu spisu klawiszy do podręcznika.
Litera `r` (zawartość rejestru w module Dockera) działała od kroku 61 i przeżyła
dwa przeglądy; obok niej litera `e` była ogłoszona poprawnie.

**Reguła.** Klawisz dopisuje się **w dwóch miejscach naraz** — w `handle()`
i w `bindings()`. Sprawdzenie na dziś jest ręczne:

```bash
grep -n "raw === self::" src/Module/<Nazwa>/Presentation/*.php
grep -n "KeyBinding::character" src/Module/<Nazwa>/Presentation/*.php
```

---

## Dokąd dalej

- [3. Jak dodać swoją rzecz](03-jak-dodac.md) — osiem przewodników
- [6. Workflow](06-workflow.md) — kolejność procesów i bramka
- [7. Jak czytać dziennik decyzji](07-dziennik-jak-czytac.md) — skąd pochodzą te historie
