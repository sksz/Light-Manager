#!/bin/sh
# XTerm z zasobami, bez których tryb graficzny nie działa poprawnie:
#   -ti vt340            terminal zgłasza Sixel w odpowiedzi DA1
#   maxGraphicSize       domyślny limit 1000x1000 px ucina większe klatki
#   metaSendsEscape      Alt+litera ma przychodzić jako ESC+litera. BEZ TEGO
#                        ZASOBU Alt+litera NIE DZIAŁA POD XTERMEM: domyślnie
#                        metaSendsEscape jest "false", a wtedy rozstrzyga
#                        eightBitInput i Alt+c przychodzi jako pojedynczy znak
#                        0x63|0x80, czyli "ã" (zmierzone bin/terminal-probe:
#                        bajty c3 a3). Parser wejścia czyta to jako zwykłą literę
#                        — i nie ma jak czytać inaczej, bo użytkownik piszący "ã"
#                        w nazwie pliku wysyła DOKŁADNIE te same bajty. Zasób
#                        naprawia terminal, nie aplikację; inne emulatory
#                        (WezTerm, foot, mlterm) wysyłają ESC+literę domyślnie.
#   disallowedWindowOps  lista domyślna pomniejszona o trzy pozycje — patrz
#                        objaśnienie poniżej; pozostałe operacje okienne zostają
#                        zablokowane tak jak domyślnie
#
# Lista `disallowedWindowOps` była zwężana **dwa razy i za każdym razem
# świadomie**. Ten komentarz tłumaczy oba rozstrzygnięcia, bo bez nich zmiana
# wygląda jak przeoczenie i następny czytający ją cofnie.
#
#   krok 34: z listy domyślnej wypada "14" — raport rozmiaru okna w pikselach.
#            Bez niego aplikacja zgaduje rozmiar komórki znakowej, więc klatka
#            sixelowa nie trafia w siatkę terminala. Reszta operacji okiennych
#            (przenoszenie, zmiana rozmiaru, odczyt tytułu) zostaje zablokowana,
#            bo aplikacja ich nie używa, a każda z nich da się nadużyć skryptem.
#
#   krok 57: z listy wypadają "GetSelection" i "SetSelection" — czyli dokładnie
#            te operacje, którymi OSC 52 zapisuje i czyta schowek systemowy.
#            Bez **obu** schowek w terminalu nie istnieje: sam zapis daje
#            kopiowanie bez wklejania, a XTerm trzyma je na liście domyślnej
#            razem (`man xterm`: „i.e., no operations are allowed").
#
#            Cena jest nazwana i przyjęta (00-decyzje.md, D95 nr 5):
#            odblokowanie "GetSelection" pozwala aplikacji działającej w tym
#            terminalu **przeczytać cudzy schowek**. Dlatego aplikacja czyta go
#            wyłącznie na wyraźne polecenie użytkownika (Alt+v albo komenda
#            core.clipboard.paste), a odczytana treść ma jedno miejsce docelowe:
#            pole tekstowe z ogniskiem. Pilnuje tego kształt kodu, nie obietnica
#            — treść wychodzi z parsera wejścia jedną drogą, przez zdolność
#            Presentation\Ui\AcceptsPaste, i nikt inny jej nie dostaje.

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

# Rozmiar okna jest argumentem, a nie stałą (krok 47): wysokość terminala bywa
# treścią sprawdzenia, a nie tłem — zakładka ustawień przewija się dopiero
# poniżej pewnej liczby wierszy, a pasek stanu rośnie do dwóch dopiero powyżej.
GEOMETRY=${1:-100x30}

xterm -ti vt340 -fa 'DejaVu Sans Mono' -fs 14 -geometry "$GEOMETRY" \
  -xrm 'XTerm*maxGraphicSize: 4000x4000' \
  -xrm 'XTerm*metaSendsEscape: true' \
  -xrm 'XTerm*disallowedWindowOps: 1,2,3,4,5,6,7,8,9,11,13,18,19,20,21,SetWinLines,SetXprop' \
  -e sh -c "cd '$ROOT' && ./bin/light-manager"
