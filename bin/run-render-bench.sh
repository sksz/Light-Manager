#!/bin/sh
# Pomiar wydajności pod prawdziwym XTermem — jedyna droga do zmierzenia fazy
# przesyłu klatki (--transfer). Zasoby te same, co w bin/run.sh:
#   -ti vt340            terminal zgłasza Sixel w odpowiedzi DA1
#   maxGraphicSize       domyślny limit 1000x1000 px ucina większe klatki
#   disallowedWindowOps  lista domyślna bez "14" — dopuszcza raport rozmiaru
#                        okna w pikselach
#
# Argumenty tego skryptu trafiają wprost do bin/render-bench, np.:
#   ./bin/run-render-bench.sh --transfer --save
#
# Okno zostaje otwarte po zakończeniu pomiaru, żeby dało się przeczytać tabelę.

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

xterm -ti vt340 -fa 'DejaVu Sans Mono' -fs 14 -geometry 166x46 \
  -xrm 'XTerm*maxGraphicSize: 4000x4000' \
  -xrm 'XTerm*disallowedWindowOps: 1,2,3,4,5,6,7,8,9,11,13,18,19,20,21,GetSelection,SetSelection,SetWinLines,SetXprop' \
  -e sh -c "cd '$ROOT' && ./bin/render-bench $*; printf '\n[Enter zamyka okno] '; read _"
