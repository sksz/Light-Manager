# 4. Working with files

> User manual, part 4 of 7. [Contents](README.md) ·
> [polski](../../pl/podrecznik/04-praca-z-plikami.md)

## Moving around

`↑` and `↓` move the cursor, `Enter` or `→` enters a directory, `Backspace` or
`←` goes back up. On a file `Enter` does nothing — the file description has its
own module (`Ctrl`+`D`), because two places doing the same thing would mean
reading the same file twice.

The list has **four columns**: name, size, modification date and permissions. In
a narrow window the columns **give way in order** — permissions first, then the
date, then the size; the name never gives way. A column that does not fit in
full **disappears in full**: a cut date (`2026-08-…`) says nothing while taking
characters away from the name, which would have used them.

Hidden entries are shown and hidden by **`.`** — the setting is persistent and
applies to both panes.

## Two panes

The browser can be split into **two panes**: two directories, two cursors,
independent of each other. You switch it on in the module settings ("Split into
two panes"), and `Tab` moves the focus. The active pane is recognisable by the
accent in its border and by the path in the top bar; the inactive pane's
directory is shown in its frame label. Panes stand side by side by default —
the "Panes side by side" position puts one above the other, and the border can
be dragged with the mouse.

The split **does not appear in a window narrower than 72 columns** (or, in the
horizontal layout, shorter than 14 rows in the list zone). Below the threshold
you see one pane: two panes do fit there arithmetically, but file names cannot
be read in them.

## The tree

`Ctrl`+`T` turns the focused pane into a **tree** — and back into a list. The
view belongs to the pane, so with the split on you may have a tree on one side
and a list on the other.

| Key | In the tree |
|---|---|
| `→` | expand the branch; on an expanded one — step down to the first child |
| `←` | collapse; on a collapsed one — jump to the parent; at the first level — parent directory |
| `Enter` | the directory under the cursor becomes the pane's directory |
| `Backspace` | parent directory |

A branch is read from disk **only when expanded**, so an untouched tree costs
not a single extra read. A branch once read stays in memory — and that is where
the one price of this switch comes from, worth knowing: **the tree shows what it
read**, not what is on the disk this second.

How many levels may be expanded is decided by the "Tree levels (Ctrl+T)"
position in the module settings; `∞` means no limit. At the limit `→` reports
with a sentence in the status bar instead of doing nothing.

## The filter

**`/`** opens a filter field at the bottom edge, and the list narrows with every
letter, **in the same frame**. The matched fragment is highlighted.

Matching is a **case-insensitive substring**, beyond ASCII too (`Ł` finds `ł`).
There are no patterns and no regular expressions.

Arrows in the open field walk the narrowed list, `Enter` keeps it narrowed,
`Esc` drops the filter and returns to the entry from before it was opened. The
filter belongs to the **focused pane**, is marked in the path strip and
disappears when the directory changes.

## Multiple marks

**Space** marks the entry under the cursor and steps one row down, so a run of
files is marked with one finger. `Shift`+arrows mark a range, and `*` inverts
the marks on what is visible.

Marked rows have their own marker in the column before the name **and** their
own text colour — so you see them both when the cursor stands elsewhere and when
it stands on them. The path strip summarises the set: `• 12 of 340 · 4.1 GB`.
Directories may be marked on equal terms with files, but nobody knows their
size — the sum skips them and says so plainly (`without 2 dirs`).

**An empty set means "the entry under the cursor", not "nothing".** Without
marks every action works exactly as if marking did not exist.

The set **survives narrowing by the filter** — an entry the filter does not show
still belongs to it — and dies together with the directory. `Esc` peels the
layers in order: the filter first, then the marks. Every pane has its own set,
and the tree neither shows it nor acts on it.

## Five actions that change the disk

| Key | Action | Works on |
|---|---|---|
| `F4` | rename | one entry (a name is singular by definition) |
| `F5` | copy | the marked entries, or the one under the cursor |
| `F6` | move | the marked entries, or the one under the cursor |
| `F7` | new directory | — |
| `F8` / `Del` | delete | the marked entries, or the one under the cursor |

`F4` opens a field with the **current name** in it, `F7` with an empty one;
`Enter` commits, `Esc` refuses and does not touch the disk. **A name is a name,
not a path**: a slash in it is an error, not an invitation to create a directory
one level down. A taken name **does not close the window** — there is something
to correct.

Every action but deletion has a second entry point in the command window
(`browser.rename`, `browser.mkdir`, `browser.copy`, `browser.move`);
`browser.delete` is there too, but it always asks first. A name with a space
goes in quotes, and a command without an argument opens the same window as the
key.

### Copying and moving

`F5` and `F6` open a window with the **target directory** filled in with the
other pane's directory; the path may be corrected or typed in full, relative
paths included. The window works with the split off too, so the target is never
a surprise.

A move within **one filesystem** happens instantly — it costs one rename, no
matter how many files the directory holds. Across filesystems there is no other
way than copying and removing the source, and then one rule holds without
exception: **the source disappears only after the target is written in full**.

The work proceeds **piece by piece, one per frame**: the application first
counts how many bytes and entries will arrive, then copies — so the progress bar
tells the truth from the first byte. `Esc` interrupts, and **a half-written file
disappears**: a file that looks complete is worse than no file.

When something already stands under that name in the target, the application
asks — with six answers: overwrite, overwrite all, skip, skip all, save under
a different name, abort. **A directory of the same name is not a collision**,
but a merge. A symbolic link is copied **as a link**, and the copy gets the
original's permissions and modification time; the owner it does not — that needs
privileges the application does not have and should not have.

A directory cannot be copied into its own inside, nor into the directory it
already lies in — the application refuses and says why.

### Trash, permanent deletion and the two routes

`F8` (or `Del`) does what the **"Delete to trash"** position says — by default
it moves to the desktop environment's trash, along with a freedesktop.org info
file, so the entry is visible and can be restored from the desktop as well.
`Shift`+`F8` always does **the other thing**.

Permanent deletion **always asks**, in the dangerous variant: a red frame, the
focus on the refusal, so a held `Enter` hits "no". The question before the trash
follows the "Ask before deleting" position, because the trash is reversible.
With multiple marks the question speaks in **numbers**, not in the name of the
first entry.

A directory is deleted permanently along with its content, but not silently: the
application first counts how many entries will disappear. With a large tree the
counting and the deleting **do not stop the application**, and `Esc` interrupts
and says honestly how much is already gone. Into the trash a directory goes
**whole and at once** — one rename, no counting and no overlays.

The trash directory may be changed in the settings (empty means: the system
one). An entry on a different filesystem than the trash gets a question: copy to
the trash, delete permanently, or abort.

### Undo

`Alt`+`U` undoes **the last reversible operation**, and `F3` opens the **undo
stack** — a list of performed operations from which any position may be undone.

| Operation | Reversible? |
|---|---|
| rename | yes |
| move | yes |
| trash | yes |
| new directory | yes, while it stayed empty |
| copy | **no** — undoing it would mean deleting the copy |
| permanent deletion | **no** |

Irreversible operations stand on the list **greyed out**, so you can see they
happened. A failed undo says why and does not remove the record. The stack
**does not survive closing the application**; its depth is set by the "Undo
stack depth" position.

## What you see after an operation

What stays marked is **what the operation did not touch**: entries that
disappeared drop out of the set, while those skipped on a collision and those
that failed **stay marked** — that is the only way to see what did not work.

The effect is visible in the same frame **in both panes**, if both look at the
same directory; a pane whose directory was removed from under it enters the
nearest readable one above. A change made **outside the application** still
requires re-entering the directory: the application refreshes the list after its
own operation.

## The context menu

`F9` opens, in the middle of the frame, a list of actions **for whatever is
selected** — with no key to remember and no name to type. On a directory you see
entering it, the entry description and the five file operations; on a file only
the entering disappears.

The menu is **a second entry point into the command registry, not a second set
of actions**: the positions come from the same registry as the list in the
command window, and choosing one does exactly what the command of that name
does — the name stands on the left of the row, its description on the right.

Only actions **on the selection** reach the menu. `browser.hidden` and
`browser.tree` are in the registry but not in the menu: they concern the pane,
not the entry. When not a single action fits — in an empty directory, say — the
menu **does not open at all** and says so with a sentence, rather than asking
you to close an empty window.

## Where to go next

- [5. Modules](05-modules.md) — what each of the seven screens can do
- [7. Scenarios](07-scenarios.md) — copying and undoing, step by step
