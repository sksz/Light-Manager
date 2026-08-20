# 2. Installation and first run

> User manual, part 2 of 7. [Contents](README.md) ·
> [polski](../../pl/podrecznik/02-instalacja.md)

## What you need

| Thing | Required? | Without it |
|---|---|---|
| PHP `^8.3` | **yes** | the application does not start |
| The `imagick` and `pcntl` extensions | **yes** | the application does not start |
| The `stty` command | **yes** | the terminal cannot be switched to raw mode (hence: Linux or macOS, not Windows) |
| Composer 2.x | **yes** | nothing to install the dependencies with |
| ImageMagick with the `SIXEL` coder | recommended | the application starts on the text path |
| A terminal that speaks Sixel | recommended | as above |
| The `glfw` extension | optional | no `--window` mode and no sound |
| An OpenSSH client | optional | the remote session module is absent along with the reason |
| The `curl` extension | optional | the Docker module is absent along with the reason |
| `kubectl` | optional | the Kubernetes module has nothing to talk to |
| The `intl` extension | optional | worse sorting and number formatting |

**A missing optional thing is a degradation, not a failure.** The application
starts, says in the status bar what was missing, and carries on without that
part.

## Installation

```bash
make check-env   # can this machine carry the project — works before installing
make install     # composer install; repeated it does nothing
```

`make check-env` distinguishes three kinds of requirement: **hard** ones end
with an error code, a missing `SIXEL` coder is a **warning**, and `glfw`, `intl`
and `xterm` are information. One thing it cannot check and says so plainly:
**whether the terminal itself speaks Sixel**, because answering that needs an
interactive session in raw mode. That is what `make probe` is for.

If Composer dies with a segmentation fault — see "When something does not work"
at the end of this chapter.

## First run

```bash
make run          # the same as ./bin/light-manager
make run-window   # windowed mode (--window)
make run-xterm    # XTerm with the full set of graphics-mode resources
```

The application switches to a separate screen, draws a frame and waits for
input. **To leave: `F10`** — or `Ctrl`+`C`; in both cases the terminal returns
to the state it was in before.

You should see the file list of your home directory, the path in the top bar and
a status bar with keys. If so — go to
[chapter 3](03-screen-and-controls.md).

### How to tell which path you are on

`F1`, the **Application** tab. If you expected a picture and see characters, the
path is text — and the reason is always one of three: the terminal has no Sixel,
ImageMagick has no coder, or the terminal's reply never arrived (a multiplexer).

## Windowed mode

```bash
./bin/light-manager --window
```

Instead of drawing in the terminal the application opens a **native window**
with an OpenGL context. The terminal you started it from is left untouched. The
keyboard uses the same vocabulary, `F10` quits, and dragging the window corner
changes the grid from the next frame.

The window size is set on the settings screen ("Window columns"/"Window rows",
100×30 cells by default). **The window remembers how you left it**: a size given
by dragging or maximizing is saved half a second after the last change, so the
next start finds the window as you left it. It is measured **in cells**, so
changing the font changes the window in pixels but leaves the grid.

**Fullscreen**: `F11` or the `core.fullscreen` command. Both exist in this mode
only — in a terminal `F11` does nothing and is not in the key list. A size
imposed by fullscreen does **not** reach the settings.

**A taskbar icon** needs a one-off `./bin/install-desktop-entry`. The route is
roundabout because there is no direct one: the PHP-GLFW extension does not
expose `glfwSetWindowIcon`, so the window introduces itself to the desktop by
its `WM_CLASS` and the desktop takes the icon from the entry.

## XTerm — three resources, each for a different reason

The simplest way: **`make run-xterm`**, which passes the whole set itself.
By hand — in `~/.Xresources`, then `xrdb -merge ~/.Xresources`:

```
XTerm*decTerminalID: 340
XTerm*maxGraphicSize: 4000x4000
XTerm*metaSendsEscape: true
XTerm*disallowedWindowOps: 1,2,3,4,5,6,7,8,9,11,13,18,19,20,21,GetSelection,SetSelection,SetWinLines,SetXprop
```

| Resource | Without it |
|---|---|
| `decTerminalID: 340` | XTerm does not report Sixel and the application drops to the text path |
| `maxGraphicSize: 4000x4000` | a frame larger than the limit **is not drawn at all**; a 200×50 window already exceeds it |
| `metaSendsEscape: true` | `Alt`+letter never reaches the application (`Alt`+`c` arrives as `ã`) |
| `disallowedWindowOps` without `14` | the application has to guess the cell size and leaves a margin at the edge |
| `disallowedWindowOps` without `GetSelection`/`SetSelection` | the clipboard does not work |

Other emulators (WezTerm, foot, mlterm) need nothing.

## When something does not work

This section is written **by symptom**, because the symptom is what you see.

### I see characters instead of a picture

The text path instead of the Sixel one. Three possible reasons:

1. **The terminal has no Sixel.** `gnome-terminal` will not do and no
   configuration fixes that — VTE removed Sixel support from the stable branch
   in 0.75.90; the `enable-sixel` profile key does nothing. Use XTerm
   (`-ti vt340`), WezTerm, foot or mlterm.
2. **You are inside a multiplexer.** `tmux` and `screen` can filter out the
   terminal's reply about its capabilities (the application waits 300 ms for
   it). Run outside the multiplexer.
3. **ImageMagick has no `SIXEL` coder.** `make check-env` will say so.

To see what your terminal actually answers, run **`make probe`**.

### The frame is cut off or empty under XTerm

`maxGraphicSize` — see the table above. The default `1000x1000` is no longer
enough for a 200×50 window.

### `Alt`+`c` and `Alt`+`v` do nothing

Under XTerm: `metaSendsEscape: true` is missing. In other terminals: clipboard
**read** (OSC 52) is often disabled by default — look for a setting like "allow
OSC 52 clipboard read". The application reads the clipboard only after `Alt`+`v`
or the `core.clipboard.paste` command, never at startup and never in the
background.

When a terminal **stays silent** instead of refusing, `Alt`+`v` ends after
a quarter of a second with "This terminal does not return the clipboard
content". In `--window` mode the question does not arise.

### `--window` does not start

The `glfw` extension is missing. Without the `--window` flag the extension **is
not needed at all** — the terminal paths work without it.

### A module is missing from the list

That is intended behaviour, and the reason is in the status bar and on the
"Modules" tab in the settings. A module is rejected when: something is missing
from the environment (an OpenSSH client, the `curl` extension), its shortcut
collides with another module, its identifier is a duplicate, or you disabled it
yourself.

### The music is silent

The audio engine comes from the `glfw` extension — without it the music commands
answer with a sentence about unavailability. It needs no window: the music plays
on both terminal paths too.

### Composer ends with a segmentation fault

It happens during parallel package downloads when `imagick` and `openswoole` are
both loaded. The workaround:

```bash
make install-safe COMPOSER_INI_SCAN_DIR=/path/to/conf.d-without-imagick
```

This concerns **Composer only** — running the application needs `imagick`
enabled normally.

### The terminal was left in raw mode

This happens only after `kill -9`, which cannot be caught. To fix it:

```bash
stty sane
```

After `F10`, `Ctrl`+`C` and every other signal the terminal restores itself.

## Where to go next

- [3. Screen and controls](03-screen-and-controls.md) — what to press
- [7. Scenarios](07-scenarios.md) — a first task end to end
