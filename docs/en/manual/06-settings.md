# 6. Settings and configuration

> User manual, part 6 of 7. [Contents](README.md) ·
> [polski](../../pl/podrecznik/06-ustawienia.md)

## The settings screen (`F2`)

`F2` opens the settings in place of the file list. The top bar holds the
**location of the configuration file** — the one thing that cannot be read off
this screen.

There are as many tabs as this version brings: two core ones (**Appearance**,
**Graphics**), **Resources**, the **Modules** list and one per module that
brings settings of its own.

The cursor starts **on the tab bar**: `←`/`→` then switch tabs and `↓` enters
the positions. On a position `←`/`→` change the value, `↑`/`↓` walk the list,
`Esc` returns to the files. A tab longer than the window scrolls —
`PgUp`/`PgDn` jump by a page, `Home`/`End` to the first and last position — and
the tab bar stays still while that happens, because it is the only indicator of
where you are.

A **textual** position behaves differently: `Enter` enters it and commits the
typed value, `Esc` discards the change. A value that does not meet the
position's requirements **does not overwrite the previous one** — the reason
appears in the status bar.

Below the positions of the core tabs stands the **Restore default settings**
button. `Enter` on it **deletes nothing straight away**: it opens a question in
which the answer starts on "No". This is the only place in the application where
a mistake costs data — and the only one that asks.

**Every change takes effect at once** and lands in the file immediately, so it
survives even killing the process with a signal. Two exceptions, which the
screen states plainly: the **module switch** and the **startup module** take
effect after a restart, because the shortcut map and the tab list are built
once.

## Core settings

<!-- spis:ustawienia:rdzen -->
| Tab | Position | Values | Default |
|---|---|---|---|
| Appearance | Language | Automatic, Polski, English | Automatic (`auto`) |
| Appearance | Theme | grafit, nordyk, papier, indygo | grafit |
| Appearance | Mouse | yes / no | **yes** |
| Graphics | Text antialiasing | yes / no | no |
| Graphics | Stroke antialiasing | yes / no | **yes** |
| Graphics | Sixel palette colours | 16, 32, 64, 128 | 64 |
| Graphics | Window columns (windowed mode) | 80, 100, 120, 140, 160, 200 | 100 |
| Graphics | Window rows (windowed mode) | 24, 30, 40, 50, 60 | 30 |
| Resources | Memory for background job output | 64, 256, 1024, 4096, 16384 KiB | 1024 |
| Resources | Concurrent background jobs | 1, 2, 4, 8, 16 | 8 |
| Modules | Module opened at startup | ids of modules that bring a screen | `browser` |
| Modules | *(each module)* | enabled / disabled | enabled |
<!-- /spis -->

The window size may also be set **outside the list** — by dragging the corner in
`--window` mode; anything from 20×5 to 1000×400 cells is accepted, and an arrow
from a value outside the list moves to the nearest stop.

**A palette below 64 colours**: the quantizer then sacrifices the border shade
in favour of the more numerous text pixels, and the panels vanish from the
screen leaving only the corner brackets. The setting is available, but the
application warns about it.

## Module settings

### File browser

<!-- spis:ustawienia:browser -->
| Position | Values | Default |
|---|---|---|
| Show hidden entries | yes / no | no |
| Split into two panes | yes / no | no |
| Panes side by side | yes / no | **yes** |
| Detail columns (date, permissions) | yes / no | **yes** |
| Column names above the list | yes / no | no |
| Tree levels (Ctrl+T) | 2, 3, 4, 5, 6, 8, 12, ∞ | 8 |
| Ask before deleting | yes / no | **yes** |
| Delete to trash (F8, Delete) | yes / no | **yes** |
| Trash directory (empty: system) | text | *(empty)* |
| Undo stack depth (F3) | 5, 10, 20, 50, 100 | 20 |
| Left pane width (%) | 20–80 | 50 |
<!-- /spis -->

### File info

<!-- spis:ustawienia:file-info -->
| Position | Values | Default |
|---|---|---|
| Command timeout (s) | 1, 2, 5, 10 | 2 |
| Extra arguments | text | *(empty)* |
| Time format | absolute, relative | absolute |
| Show inode and links | yes / no | no |
| sha256 checksum | yes / no | no |
| Checksum size limit (MiB) | 16, 64, 256, 1024 | 256 |
| Directory disk usage (du) | yes / no | no |
| Background work limit (s) | 5, 15, 30, 60 | 15 |
| Preview text file content | yes / no | **yes** |
| Line numbers in the preview | yes / no | no |
| Wrap lines in the preview | yes / no | **yes** |
| Description pane width (%) | 20–80 | 50 |
<!-- /spis -->

### Audio

<!-- spis:ustawienia:audio -->
| Position | Values | Default |
|---|---|---|
| After a track | list, once, repeat | list |
| Volume (%) | 0–100 by 10 | 50 |
| Play from startup | yes / no | no |
| Sound effects | yes / no | **yes** |
| Effects volume (%) | 0–100 by 10 | 70 |
| Effects pane width (%) | 20–80 | 50 |
<!-- /spis -->

### Remote session

<!-- spis:ustawienia:ssh -->
| Position | Values | Default |
|---|---|---|
| Connection timeout (s) | 5, 10, 15, 20, 30, 60 | 10 |
| Authentication method | agent, key, password | agent |
| Remember fingerprints of new hosts | yes / no | **yes** |
| Show hidden entries | yes / no | no |
<!-- /spis -->

### Docker

<!-- spis:ustawienia:docker -->
| Position | Values | Default |
|---|---|---|
| Log lines kept in memory | 500, 1000, 2000, 5000, 10000 | 2000 |
| List pane width (%) | 20–80 | 50 |
<!-- /spis -->

### Kubernetes

<!-- spis:ustawienia:k8s -->
| Position | Values | Default |
|---|---|---|
| Call timeout (s) | 2, 5, 10, 30, 60 | 10 |
| List refresh (s) | 10, 30, 60, 300 | 30 |
| Log lines kept | 500, 1000, 2000, 5000 | 1000 |
| Build wait limit (s) | 60, 300, 600, 1800 | 600 |
| Resource tree width (%) | 20–80 | 40 |
<!-- /spis -->

### Address book

<!-- spis:ustawienia:address-book -->
| Position | Values | Default |
|---|---|---|
| List order | added, name | added |
<!-- /spis -->

## Files on disk

Everything lives in **`~/.light-manager/`**, and the directory is created **only
on the first write** — merely starting the application creates nothing on disk.

| File | What it holds | When it appears |
|---|---|---|
| `settings.json` | core and module settings | on the first settings change |
| `state.json` | the address book and module sections (chosen environment, cluster, remote directories) | on the first write by any module |
| `audio.json` | the playlist and the sound assignments for events | on adding the first track |
| `history` | the last twenty lines of the command window | on running the first command |
| `ssh.json` | **a record from an older version** — moves into `state.json` and is left untouched | — |

`state.json` is mode **`0600`**, because it holds registry tokens. Masking
a secret on screen protects against a glance, not against reading the file —
**there is no encryption and the application does not pretend otherwise**.

## `settings.json`

```json
{
    "language": "auto",
    "theme": "grafit",
    "startupModule": "browser",
    "textAntialias": false,
    "strokeAntialias": true,
    "paletteColors": 64,
    "windowColumns": 100,
    "windowRows": 30,
    "backgroundOutputKib": 1024,
    "backgroundJobs": 8,
    "mouse": true,
    "modules": {
        "browser": { "enabled": true, "showHidden": false, "split": false },
        "file-info": { "enabled": true, "timeout": 2, "textPreview": true },
        "audio": { "enabled": true, "mode": "list", "volume": 50 },
        "ssh": { "enabled": true, "auth": "agent", "timeout": 10 },
        "docker": { "enabled": true, "logLines": 2000 },
        "k8s": { "enabled": true, "timeoutSeconds": 10 },
        "address-book": { "enabled": true, "order": "added" }
    }
}
```

The `modules` sub-object is written only once some module setting has been
touched, and **the settings of an unknown module are left untouched** — a module
that was disabled or removed from the list gets its configuration back when it
returns.

Editing by hand is possible, but **the file is read once, at startup**. The
reading rules:

| Situation | What happens |
|---|---|
| No file | defaults, without a word — that is the normal state of a first run |
| Unreadable file or invalid JSON | defaults and a warning; **the application does not overwrite a file it did not understand** |
| Unknown key | skipped silently — a file from a newer version has no right to alarm |
| Known key with a value out of range | the default for that key, the rest of the file stays, plus a warning naming the position |
| `startupModule` with no match in the registry | starts with the browser and says why in the status bar |

The write goes through a temporary file and a rename within the same directory,
so an interrupted write leaves **the previous, correct version** rather than
truncated JSON.

## Interface language

The application speaks Polish or English. The default **Automatic** takes the
language from the environment — it checks `LC_ALL`, `LC_MESSAGES` and `LANG`, in
that order, and accepts the first value with a recognisable code (`pl_PL.UTF-8`
and `pl` mean the same). When none of them says anything, English remains.

A choice saved in the settings is **stronger than the environment** and takes
effect at once, without a restart. The `core.language <code>` command does the
same.

The messages of the exceptions themselves are technical and always in English:
they are written for whoever reads the stack trace. What the user sees — a
failed startup included — goes through the message catalogue.

## The command window and queries

`F12` opens a strip with an input field and a list of suggestions above it. An
action is invoked **by name**, instead of hunting for a free key for it.

Commands are named with their owner's namespace: the core brings `core.*` and
every module only `<module id>.*`. The registry enforces the prefix, so
a collision between modules is impossible by construction.

With an empty field the list shows **the history first**, and the full set of
commands below it — repeating the last call needs no separate key, and someone
who does not know the names sees them all at once.

Arguments are separated by a space, and a value with a space goes in quotes
(`core.theme "my theme"`). A missing required argument, a surplus value and an
unknown name **leave the window open** along with the typed line — the reason
appears in the status bar, so a typo need not be retyped from scratch.

**`Tab` on an empty line switches the window to queries** — questions the
application answers about itself: what is selected, which jobs run in the
background, what modules it has, what is playing, which containers the daemon
sees. A query **reads and does not change**, so none of them can break anything.
`Alt`+`C` in the query window copies the whole answer.

The list of commands and queries is always in the window itself — and that is
the source of truth, because it is built from the same registry the application
runs them with.

## Where to go next

- [7. Scenarios](07-scenarios.md) — eight paths end to end
