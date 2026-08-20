# 3. Looking around

> Onboarding, stop 3 of 5 · **5 minutes**. [Index](README.md) ·
> [polski](../../pl/onboarding/03-ogladanie.md)

One tour, five stops inside the application. The goal is not to learn the keys —
the full list is under `F1` and there is no point in copying it out. The goal is
one sentence at the end: **you can ask the application about its own state.**

## 1. Walk a directory

| Key | What it does |
|---|---|
| `↑` / `↓` | move the selection |
| `Enter` / `→` | enter the directory |
| `Backspace` / `←` | one directory up |
| `/` | narrow the list by a fragment of a name (`Esc` clears it) |

**The status bar at the bottom is a cheat sheet** and changes together with what
you have at hand. It does not promise keys "in general" — it promises the ones
that will work right now.

## 2. Open the command window (`F12`)

An action is called **by name** instead of hunting for a free key for it. With
an empty field the list shows the history first and the complete set of commands
below it. Names carry their owner's namespace: the core brings `core.*`, a
module brings only `<module id>.*`.

Try `core.theme` and walk the list with the arrows. `Esc` closes the window.

## 3. Switch the window to queries (`Tab` on an empty line)

**This is the moment the whole stop exists for.** Queries are questions the
application answers about itself — and **the query registry is the only path to
reading data in this project**, not one of several.

Ask three:

| Query | Answers the question |
|---|---|
| `core.viewport` | what the window size is and **which path** the frame is drawn on |
| `core.modules` | which modules came in, which are switched off, which were rejected **and why** |
| `core.queries` | what data sources this run has — the list of every query |

`Enter` asks the question, `Alt`+`C` copies the whole answer, `Tab` goes back to
commands. **No query can break anything**, because a query reads and does not
change — which is why you may click through them without thinking twice.

Look at `core.queries` for a second: the list you just saw **comes from the very
registry the application executes those queries through**. There is no second
place where somebody copied it out — and it is that list you will make one item
longer at the next stop.

## 4. Look into the context menu (`F9`)

The menu shows the actions that make sense **here and now** — for this entry, on
this screen. It is the same set of commands as in `F12`, narrowed by context.

## 5. Enter a module (`Ctrl`+letter)

`Ctrl`+`D` — a description of the selected file. `Ctrl`+`W` — the address book.
`Esc` returns to the file browser, from any screen, always.

**A module the machine cannot carry simply will not be on the list — together
with the reason.** If `Ctrl`+`O` or `Ctrl`+`S` does nothing, that is not a
defect: ask `core.modules` and you get a sentence saying why the module dropped
out. Everything outside the loop, the frame and the overlays is a module in this
application — and that is also where you are about to add yours.

## How you know you are done

**You can ask the application about its own state.** Concretely: you can open
`F12`, switch to queries with `Tab`, ask `core.modules` and read from the answer
which modules made it into this run, and which did not, and why.

Next: [4. First change](04-first-change.md).
