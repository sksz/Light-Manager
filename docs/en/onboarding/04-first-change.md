# 4. First change

> Onboarding, stop 4 of 5 · **10 minutes**. [Index](README.md) ·
> [polski](../../pl/onboarding/04-pierwsza-zmiana.md)

## The exercise

The core of the application can say thirteen things about itself — its version, extensions,
modules, theme, language, the path it draws the frame on. **It cannot say how
long it has been running.** You will add that one sentence: a module with a
single query, `czas.dzialanie`, which you will see in the `F12` window after
switching with `Tab`.

The material sits in [`examples/zadanie-kwerenda/`](../../../examples/zadanie-kwerenda/):
`start/` holds the module with **one gap**, `rozwiazanie/` ("solution") holds
the same thing with the gap filled. Look at the solution whenever you like —
onboarding without one ends in silence rather than a question for some people.

*(The identifiers stay Polish in both language trees, exactly like
`examples/modul-przykladowy/`: they are names from the repository, not words.)*

**The exercise has one planned mistake in it.** The quality gate will go red
along the way and tell you exactly what is missing. That is the point: your
first contact with this project's rules is a message from the gate, not a list
of rules.

## Move 1 — copy the skeleton

```bash
cp -r examples/zadanie-kwerenda/start src/Module/Czas
sed -i 's/Examples\\ZadanieKwerenda\\Start/Module\\Czas/' \
    src/Module/Czas/Presentation/CzasModule.php \
    src/Module/Czas/Presentation/Query/CzasDzialaniaQuery.php
```

*(macOS: `sed -i '' …`.)* The directory name and the namespace have to match —
that is PSR-4, not a rule of this project.

## Move 2 — add the module to `Bootstrap`

**A module costs one line in the core, and that is a measure, not a wish.**
In [`src/Presentation/Cli/Bootstrap.php`](../../../src/Presentation/Cli/Bootstrap.php),
in the `createModules()` method, at the end of the list:

```php
new CzasModule($state, microtime(true)),
```

…plus `use LightManager\Module\Czas\Presentation\CzasModule;` among the other
modules (alphabetically: after `Browser`, before `Docker`).

**The moment of startup is handed in by `Bootstrap`, not taken by the module**,
because `Bootstrap` is what knows when the start happened. The same principle
holds the components and the progress bar in this project: a class with its own
`microtime()` stops being testable.

## Move 3 — fill the gap in the query

In the file you have just copied,
`src/Module/Czas/Presentation/Query/CzasDzialaniaQuery.php`
(the original: [`examples/zadanie-kwerenda/start/…`](../../../examples/zadanie-kwerenda/start/Presentation/Query/CzasDzialaniaQuery.php)),
the `ask()` method returns an empty answer today. It should return the number of
seconds — one field, named `seconds`. The number is ready for you in `seconds()`;
the shape of the result is one of five, described in the
[guide, "A new query"](../guide/03-how-to-add.md#a-new-query).

`generation()` is already written, and **it is worth reading why**: a generation
is a counter of changes, not a timestamp. Had `microtime()` been put there, the
registry would recompute this query every frame in order to return a value that
changes once a second.

## Move 4 — ask the gate

```bash
make qa
```

The gate runs in a known order — `cs-check`, `stan`, `test` — and stops at the
first error. You will see:

```
1) …TranslatorServiceTest::testEveryModuleCarriesTheSameKeysInEveryLanguage@Czas
moduł  ma plik języka zapasowego
```

That is the planned mistake. Your module has `lang/pl.php` and no `lang/en.php`
— and **a module catalogue translated into one language only is the same defect
here as a message written straight into the code**. The empty identifier in the
message is not a bug in the test: the identifier is derived from the fallback
language catalogue, and that is exactly the file that is missing.

Write `src/Module/Czas/lang/en.php` with the same three keys as `pl.php` (a
ready one sits in `examples/zadanie-kwerenda/rozwiazanie/lang/en.php`) and run
`make qa` again. It should be green.

## Move 5 — see the effect in the application

```bash
make run
```

`F12` → `Tab` on an empty line → type `czas` → `Enter`. The answer is a single
row: the field `seconds` and a number that is larger the next time you ask. Your
module is also in the answer of `core.modules` and on the "Modules" tab in the
settings (`F2`).

## Move 6 — add the module to the lists in the documentation

**A change you cannot see in the documentation is not a finished change in this
project.** You have added an eighth module, so four sentences stopped being
true:

- [`docs/pl/podrecznik/05-moduly.md`](../../pl/podrecznik/05-moduly.md) — add a
  row to the module table: name **Czas działania**, shortcut "—", needs "—",
  without it "—" (a module without a screen has no shortcut and needs nothing).
- [`docs/en/manual/05-modules.md`](../manual/05-modules.md) — the same in
  English, name **Uptime**.
- [`docs/pl/przewodnik/08-spisy.md`](../../pl/przewodnik/08-spisy.md#kwerendy)
  and [`docs/en/guide/08-lists.md`](../guide/08-lists.md#queries) — a row in the
  query list: the name `czas.dzialanie`, arguments "—", and the description
  **exactly as it stands in the message catalogue**.
- [`docs/pl/przewodnik/01-mapa-kodu.md`](../../pl/przewodnik/01-mapa-kodu.md) —
  "siedem modułów" in the repository tree is no longer true.
- [`docs/en/guide/01-code-map.md`](../guide/01-code-map.md) — "seven modules",
  likewise.

One thing here is not obvious and the gate says it outright: **the test fixture
must know the same list of modules as `Bootstrap`**. Add your module to the list
in `tests/Support/ScreenFixture.php` — without it the compliance tests would be
guarding an application that does not exist, and your query would pass through
a green gate unnoticed.

**You do not have to memorise any of this.** Run `make qa` and fix what it
reports — every one of those files will announce itself, by name.

The rule behind this is one and it applies to everyone: **a step that changes a
key, a setting, a command, a query or a module updates the documentation in that
same step.** Documentation debt without an owner is debt nobody will ever pay.

## The five things most often broken here

You do not have to remember them — **you have to recognise the message**,
because the message comes first:

| What is broken | What the gate will say |
|---|---|
| A message written straight into the code | The catalogue test: a key with no translation, or a translation with no key — exactly the one you have just seen. |
| Reading data around the query registry | `QueryIsTheOnlyReadPathTest` — together with what to use instead. |
| Reaching into another module | `NoModuleKnowsAnotherModuleTest` — it shows in the `use` statements. |
| A file type in the core | `CoreKnowsNothingAboutFilesTest` — the file domain lives in the `Browser` module. |
| A key that works but is not declared in `bindings()` | `StatusHintsFlowTest` — the status bar has to promise exactly what works. |

## How you know you are done

**`make qa` is green**, and `F12` → `Tab` → `czas.dzialanie` answers with a
number that grows. The change stays in your clone — and when you want it gone,
`rm -rf src/Module/Czas` plus undoing the additions in `Bootstrap` and in the
documentation is all it takes.

Next: [5. Where next](05-where-next.md).
