# Developer guide

Answers the question **how do I add my own thing**. It does not answer **why it
turned out this way** — that is the [decision log](../../plans/00-decyzje.md) —
nor **how it is** — that is the [source document](../../architecture.md).

First day here → [onboarding](../onboarding/README.md). You want to **use** the
application rather than develop it → [the manual](../manual/README.md).

Polish original: [../../pl/przewodnik/](../../pl/przewodnik/README.md).

| # | Chapter | Answers the question |
|---|---|---|
| 1 | [Code map](01-code-map.md) | where things live and why there |
| 2 | [The frame lifecycle](02-frame-cycle.md) | how it turns and what is allowed in which phase |
| 3 | [How to add your own thing](03-how-to-add.md) | **eight guides**: module, command, query, setting, component, overlay, background work, messages |
| 4 | [Before you add](04-before-you-add.md) | two things the answer to is almost always "no" |
| 5 | [Traps](05-traps.md) | **ten** things the project has already paid for |
| 6 | [Workflow](06-workflow.md) | the order of processes, the gate, tests, benchmarks, the build |
| 7 | [How to read the decision log](07-decision-log.md) | 110 entries and three ways to the right one |
| 8 | [Guarded lists](08-lists.md) | **commands, queries** and what to do when a compliance test goes red |

## The three shortest routes

- **I want to add something and do not know where** → [1](01-code-map.md) →
  [3](03-how-to-add.md).
- **Something does not work and I do not understand why** → [5](05-traps.md),
  written by symptom.
- **I want to know whether I may touch the core** → [4](04-before-you-add.md).

## Examples

The guides point at **real files**, not at blocks in markdown
([the convention](../../KONWENCJE.md)). A pattern that **exists** in the
application is pointed at in `src/`; a teaching pattern —
in [`examples/`](../../../examples/).

A complete micro-module — a command, a query, a setting and messages in two
languages — stands in
[`examples/modul-przykladowy/`](../../../examples/modul-przykladowy/) and passes
PHPStan `max` together with `src/`.
