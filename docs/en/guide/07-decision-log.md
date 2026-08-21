# 7. How to read the decision log

> Developer guide, part 7 of 8. [Contents](README.md) ·
> [polski](../../pl/przewodnik/07-dziennik-jak-czytac.md)

## What the log is and what it is not

[`docs/plans/00-decyzje.md`](../../plans/00-decyzje.md) is **110 entries and
8,666 lines** — the largest document in the repository, four times the size of
the architecture. Nobody reads it cover to cover and nobody expects that.

**The boundary sentence: the log says why it turned out this way, not what
holds today.** What holds stands in the [architecture
chapters](../../architektura/) and in
[`SKILL.md`](../../../.claude/skills/light-manager-conventions/SKILL.md). An
entry from forty steps ago describes the state of the world on that day —
including variants that were **rejected** and assumptions that later fell.

| Question | Place |
|---|---|
| What holds today? | the architecture, `SKILL.md` |
| Why this way and not another? What was rejected and why? | **the decision log** |
| What is done, what is planned? | the [plan](../../plans/00-index.md) |
| What did the application get, and when? | [`CHANGELOG.md`](../../../CHANGELOG.md) |

Note: the log and the plan are **Polish-only**, on purpose — they are documents
about *working on* the project, not documents *of* the project. The reasoning is
in the [documentation map](../../README.md).

## The shape of an entry

Every entry has the same structure, and it is worth knowing, because it lets you
read **selectively**:

| Section | What is in it | When to read it |
|---|---|---|
| **Dotyczy** (Concerns) | the files, steps and mechanisms the entry reaches | to check whether it is about your problem |
| **Data** (Date) | when it was decided and **whether before the first line of code** | to know whether it is a design decision or a consequence |
| **Co rozstrzygnęło rozpoznanie** (What the survey settled) | numbers counted in the repository before the question was asked | the densest part — **facts, not opinions** |
| **Decyzje użytkownika** (The user's decisions) | the rulings, each with the rejected variants | the actual content of the entry |
| **Co z tego wynika** (What follows) | obligations for the steps that come after | if you are writing a step this entry binds |

**The rejected variants are the most valuable thing in this log.** An entry says
not only what was chosen, but **what was not and at what price** — and that is
exactly the knowledge that cannot be recovered from the code.

## Three entries to read before your first larger change

### D40 — the core stops knowing about files

*"Menadżer plików jako moduł domyślny: rdzeń przestaje wiedzieć o plikach"*

The entry after which the whole file domain moved out of `src/Domain/` into
`src/Module/Browser/`. Read it if you wonder **why the core is so thin** and why
`Entry`, `Directory` and `DirectoryPath` have no right to appear in the
signature of anything in `Application` or `Domain`.

### D92 — the query registry as the only read path

*"Kwerendy obejmują wszystkie źródła danych rdzenia i sześciu modułów, rejestr
staje się jedyną drogą odczytu"*

The entry that closed the question "where do I get another module's data".
Read it before you reach for another module's object — and especially when it
seems to you that in your case a query is overkill.

### D48 — opening the closed vocabulary of primitives

*"Sześć nowych komponentów rdzenia, rytm «jeden komponent — jeden krok»
i otwarcie zamkniętego słownika prymitywów"*

The one time the vocabulary of primitives was opened. Read it before you propose
a new shape — you will see there **what the consent cost** and why the original
proposal fell anyway as a synonym of an existing primitive. See also
[ch. 4](04-before-you-add.md).

## How to search it

The log has no table of contents and **does not need one**: entry numbers are
chronological, and searching works better.

```bash
grep -n "^### D" docs/plans/00-decyzje.md          # a list of every entry
grep -n "kwerend" docs/plans/00-decyzje.md | head  # entries about queries
grep -n "^### D92" -A 40 docs/plans/00-decyzje.md  # a single entry
```

Three ways in, worth using instead of reading in order:

- **From a rule.** The architecture chapter and `SKILL.md` give a decision number
  next to a rule (`D42`, `D87`, `D101`) — that is the shortest way.
- **From a step.** A step file in [`docs/plans/archiwum/`](../../plans/archiwum/)
  has a "Rozstrzygnięcia startowe" section pointing at its entry.
- **From a symptom.** The [list of traps](05-traps.md) names, for every entry,
  the step in which the project paid for it — and that step's journal carries the
  whole story.

## What not to change in the log

Entries are **closed documents**. They are not corrected when the world changes
— the same goes for the step files in the archive. A ruling that has stopped
holding is revoked by **a new entry**, not by rewriting the old one; otherwise
the project loses the most valuable thing in the log: **the trace that somebody
once thought differently, and why**.

The same principle covers caveats: a caveat on a finished step describes **the
boundary of what was delivered**, not a debt to be paid inside that file.

## Where to go next

- [1. Code map](01-code-map.md) — where things live
- [3. How to add your own thing](03-how-to-add.md) — eight guides
- [Documentation map](../../README.md) — which document answers what
