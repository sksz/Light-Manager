# 5. Where next

> Onboarding, stop 5 of 5 · **5 minutes**. [Index](README.md) ·
> [polski](../../pl/onboarding/05-dokad-dalej.md)

The path ends here. This stop teaches nothing new — **it hands you addresses**,
so that from tomorrow you look in the right place straight away.

## The documentation map in one paragraph

This project's documentation is divided by **the question it answers**, not by
topic. **Architecture says how things are; the guide — how to do it; the manual
— how to use it; the log — why it turned out this way.** A rule is born in an
architecture chapter and only there; the summary in `SKILL.md` restates it, and
when the two disagree, the chapter is right. A text that answers two of those
questions at once is in the wrong place — and that is not a flaw of style but a
flaw of address. The full map: [`docs/README.md`](../../README.md).

## Three questions, three answers

**Where is the rule?** In [`docs/architektura/`](../../architektura/) — nine
chapters, with the index in [`docs/architecture.md`](../../architecture.md).
Eighteen hard rules and six guards in the tests. You do not look for a rule in
the Skill or in `CLAUDE.md`: both are summaries, and a summary is not a source.
*(These two trees are Polish-only on purpose — they are documents about working
on the project, not documents of the project.)*

**Where is the history?** In [`docs/plans/`](../../plans/) — the plan steps with
their statuses ([`00-index.md`](../../plans/00-index.md)) and the decision log
([`00-decyzje.md`](../../plans/00-decyzje.md)): over a hundred entries about what
was chosen, **what was rejected and why**. How to read it without drowning —
[guide, ch. 7](../guide/07-decision-log.md).

**Where is the guide?** In [`docs/en/guide/`](../guide/README.md) — seven
chapters for whoever is adding their own thing. Start with these three:

| I want to… | Go to |
|---|---|
| add something and I do not know where | [code map](../guide/01-code-map.md) → [how to add](../guide/03-how-to-add.md) |
| understand why something does not work | [ten traps](../guide/05-traps.md) — written by symptom |
| know whether I may touch the core | [before you add](../guide/04-before-you-add.md) — the answer is almost always "no" |

## Four sentences worth taking with you

1. **A new feature is a module, not a change in the core.** Having to touch the
   core is a signal of a mistake in the module's design — not a reason for an
   exception.
2. **A module never reaches into another module.** Three paths through the core:
   a command (do this), a query (tell me), an event (this happened). There is no
   fourth.
3. **The query registry is the only path to reading data.**
4. **A message does not live in the code**, it lives in a catalogue — in both
   languages at once.

## What onboarding deliberately did not do

It did not show you a screen or a component, did not touch the frame loop, did
not explain the primitives, did not mention performance measurement. All of that
is in the guide and in the architecture — and it will come when it is needed.
**Onboarding has one job: to walk you to your first green quality gate**, not to
replace the rest of the documentation.

One place worth knowing right away, because it is about the way of working
rather than the code: [guide, ch. 6](../guide/06-workflow.md) — the order of the
processes, the gate, the tests, the measurements, the build. `make` with no
arguments prints every entry point.

## How you know you are done

You can answer without looking back here: **where the rule lives, where the
history lives, where the guide lives.** If one of those answers does not come —
go back one paragraph; it is the only thing from this path worth knowing by
heart.
