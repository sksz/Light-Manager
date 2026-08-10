#!/bin/sh
# XTerm z zasobami, bez których tryb graficzny nie działa poprawnie:
#   -ti vt340            terminal zgłasza Sixel w odpowiedzi DA1
#   maxGraphicSize       domyślny limit 1000x1000 px ucina większe klatki
#   disallowedWindowOps  lista domyślna bez "14" — dopuszcza wyłącznie raport
#                        rozmiaru okna w pikselach (bez niego aplikacja zgaduje
#                        rozmiar komórki); pozostałe operacje okienne zostają
#                        zablokowane tak jak domyślnie

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

xterm -ti vt340 -fa 'DejaVu Sans Mono' -fs 14 -geometry 100x30 \
  -xrm 'XTerm*maxGraphicSize: 4000x4000' \
  -xrm 'XTerm*disallowedWindowOps: 1,2,3,4,5,6,7,8,9,11,13,18,19,20,21,GetSelection,SetSelection,SetWinLines,SetXprop' \
  -e sh -c "cd '$ROOT' && ./bin/terminal-probe"
