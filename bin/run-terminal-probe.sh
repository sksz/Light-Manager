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
#   disallowedWindowOps  ta sama lista, co w bin/run.sh — pełne objaśnienie obu
#                        zwężeń (krok 34: "14"; krok 57: "GetSelection"
#                        i "SetSelection") stoi tam
#
# Listy nie wolno tu zawężać bardziej niż w bin/run.sh, i to jest cały powód
# istnienia tego zdania: podgląd wejścia jest **jedynym** narzędziem, którym da
# się zobaczyć, co terminal naprawdę przysyła (reguła 18) — więc terminal
# pokazujący tu mniej niż aplikacji odpowiadałby na inne pytanie niż zadane.
# Odpowiedź na pytanie o schowek (OSC 52 z pytajnikiem) widać dokładnie tędy.

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

xterm -ti vt340 -fa 'DejaVu Sans Mono' -fs 14 -geometry 100x30 \
  -xrm 'XTerm*maxGraphicSize: 4000x4000' \
  -xrm 'XTerm*metaSendsEscape: true' \
  -xrm 'XTerm*disallowedWindowOps: 1,2,3,4,5,6,7,8,9,11,13,18,19,20,21,SetWinLines,SetXprop' \
  -e sh -c "cd '$ROOT' && ./bin/terminal-probe"
