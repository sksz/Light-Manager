# 4. Before you add

> Developer guide, part 4 of 7. [Contents](README.md) ·
> [polski](../../pl/przewodnik/04-zanim-dolozysz.md)

Two things in this project have a chapter of their own, not because they are
hard, but because **the answer to them is almost always "no"** — and every
consent costs more than it looks from the place the request is made.

---

## A new primitive

**The default answer: no.** The vocabulary of primitives has been **closed since
step 18** and for twelve steps nobody touched it. It was opened **once**, in
step 30, with explicit consent — and to this day it holds seven shapes.

### Why it is expensive

A shape is not one class. It is **an obligation for three renderers at once**:
the Sixel one (Imagick, quantization to a palette), the text one (a cell buffer,
ANSI bytes) and the windowed one (OpenGL). A renderer without a translation for
the new shape turns `PrimitiveTranslationTableTest` red, and a shape drawn on two
paths out of three means an application that looks different depending on the
terminal.

### The test you have to pass

**Check whether your shape is not one of the existing ones under a different
name.** The project has two precedents and both ended in "no":

- **The text field's caret (step 19)** — it faked highlighting with a pair of
  "fill plus text", out of existing primitives.
- **The content selection rectangle (step 56)** — it turned out to be a
  `TextMark` laid over a row.

And when the vocabulary really was opened in step 30, **the original proposal
fell anyway**: "just a background under the fragment" was a synonym for a `Bar`
with `Weight::Fill`. The shape that made it in had to be something none of the
others could do — **binding text to a background in one thing**.

### If you still think it is needed

1. Write down what **none of the seven** can do — in one sentence, without "it
   would be more convenient".
2. Count what it costs on each of the three paths.
3. **Ask the user.** This is a deliberate architectural decision, not an
   implementation detail.
4. The consent concerns **opening the vocabulary**, not the shape — the shape is
   settled only when it is written out.

---

## A change in the core instead of a module

**The default answer: no.** Rule 15 says: **a new feature is a module in
`src/Module/`, not a change in the core**. The core is a shell — the loop, the
frame, the overlays and the status bar — not a place where functionality grows.

### The single named exception

Rule 15 has **exactly one** exception and it is named: **writing to disk**. The
core has ports for file operations, copying and the trash, even though mainly one
module needs the operations. The reason is not convenience but **the second rule
of the same pair**: since a module never reaches into another module, two
recipients mean two copies of code that writes to disk — and a duplicated
`unlink()` costs data loss in two places instead of one.

The exception's boundary is narrow and stated outright: the core knows **a path
as a string**, **a name as a string** (without judging whether it is valid),
**nine actions** and **the state of that work**. It does not know an entry,
a directory, sorting, hiding, marking or previews — `Entry`, `Directory`,
`DirectoryPath` and `EntryType` have no right to appear in the signature of
anything in `src/Application` or `src/Domain`.
`CoreKnowsNothingAboutFilesTest` watches over it.

By contrast: **a duplicated `permissionsAsText()` calculation was allowed to
stay** in two modules, because it cost ten lines with no side effects. Not every
duplication is a debt.

### The test you have to pass

A feature that wants into the core on the same argument must have **both**:

1. **two recipients** — not one and a hope for the second;
2. **duplication with an irreversible cost** — data loss, a corrupted state,
   a secret in the wrong place. Not "ten lines twice".

Otherwise **it is a module, like everything else**.

### Three things that look like an exception and are not

| Looks like | Actually is |
|---|---|
| "my module needs another module's data" | **a query** — `QueryRegistry` is the only read path |
| "my module must make another one act" | **a command** — a module orders somebody else's action through the command registry |
| "my module must know that something happened" | **an event** — `EventRegistry`, announcing and receiving |

A pattern worth looking at: **`k8s.deploy-image`** — the only action that passes
through two modules. It goes by a command, an event and a query, not by a shared
class in the core.

### A shared place versus duplication

When the same pattern appears **for the third time**, the rule demands the
question: is this still duplication, or already a shared place? The project went
through this once — three books of entries (`ssh.json`, `docker.json`,
`k8s.json`) came down into **one registry**, that is into **the address book
module**, not into the core.

That is the shape of the answer: a shared place is usually **a module**, not the
core.

---

## Where to go next

- [3. How to add your own thing](03-how-to-add.md) — eight guides
- [5. Traps](05-traps.md) — ten things the project has already paid for
