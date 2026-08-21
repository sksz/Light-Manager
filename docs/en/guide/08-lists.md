# 8. Guarded lists

> Developer guide, part 8 of 8. [Index](README.md) ·
> [polski](../../pl/przewodnik/08-spisy.md)

This chapter is **a copy of the state of the code**, not a description of an
intention. The rows below come from the very registries the application executes
commands and asks queries through — and the compliance tests in
`tests/Documentation/` **turn the quality gate red** when the two stop matching.
Adding a command or a query without a row here is an unfinished change, exactly
like adding one without a message in the catalogue.

The list is **not written from memory**: open `F12` in the running application,
switch to queries with `Tab` and ask `core.commands` or `core.queries` — it is
the same registry, so the answer is what belongs here.

## Commands

An action called **by name** from the `F12` window, from the `F9` menu or from
the history. The name starts with its owner's identifier: the core brings
`core.*`, a module brings only `<module id>.*`, and `CommandRegistry` watches the
prefix. An argument in angle brackets is required, one in square brackets is
optional.

<!-- spis:komendy -->
| Command | Arguments | What it does |
|---|---|---|
| `address-book.add` | `[name]` `[chapter]` | add an entry |
| `address-book.chapter` | `<chapter>` `[title key]` | announce the use of a chapter |
| `address-book.clear` | `<entry>` `<chapter>` `[field]` | clear a field or a chapter of an entry |
| `address-book.edit` | `<entry>` `<chapter>` | walk through the fields of a chapter |
| `address-book.field` | `<chapter>` `<field>` `<label key>` `<kind>` `[default value]` `[allowed values]` | announce the use of a chapter field |
| `address-book.forget` | `<chapter>` | remove the values of a chapter from every entry |
| `address-book.remove` | `<entry>` | remove an entry |
| `address-book.rename` | `<entry>` `[name]` | rename an entry |
| `address-book.set` | `<entry>` `<chapter>` `<field>` `[value]` | write a field value |
| `address-book.show` | `[chapter]` | open the address book |
| `audio.add` | `<path to an audio file>` | add a track to the playlist |
| `audio.hook` | `<event name>` `[path to an audio file]` | assign a sound to an event |
| `audio.music` | — | start the music or stop it |
| `audio.volume` | `<volume in percent>` | set the music volume |
| `browser.copy` | `[path]` | copy the selected entry into the given directory |
| `browser.delete` | `[name]` | delete the selected entry |
| `browser.hidden` | — | show or hide hidden entries |
| `browser.jump` | `<path>` | jump to the given directory |
| `browser.mkdir` | `[name]` | create a directory in the pane directory |
| `browser.move` | `[path]` | move the selected entry into the given directory |
| `browser.open` | — | enter the selected directory |
| `browser.rename` | `[name]` | rename the selected entry |
| `browser.tree` | — | pane as a tree or as a list |
| `core.help` | — | open the help |
| `core.quit` | — | quit the application |
| `core.settings` | — | open the settings |
| `core.theme` | `<theme>` | set the colour theme |
| `docker.build` | `[directory holding the Dockerfile]` | build an image from a directory |
| `docker.down` | `[compose file, or the directory it lives in]` | take a compose project down |
| `docker.images` | — | show images |
| `docker.ps` | — | show containers |
| `docker.pull` | `[image name along with its tag]` | Pulls an image from a registry |
| `docker.push` | `[image name along with its tag]` | push an image to the registry |
| `docker.up` | `[compose file, or the directory it lives in]` | bring a compose project up |
| `file-info.show` | — | show the description of the selected entry |
| `k8s.apply` | `[path]` | Apply a manifest file to the cluster |
| `k8s.context` | — | Pick a cluster (kubectl context) |
| `k8s.deploy-image` | — | Deploy a container image to the cluster |
| `k8s.get` | `<kind>` | Show resources of the given kind |
| `k8s.namespace` | — | Change the namespace |
| `ssh.connect` | `<name of an entry in the host book>` | connect to a host from the book |
| `ssh.disconnect` | — | close the remote session |
| `ssh.get` | `[target directory on this machine]` | download the selected remote file |
| `ssh.hosts` | — | show the host book |
| `ssh.put` | `[target directory on the host]` | upload the selected local file |
<!-- /spis -->

## Queries

**The query registry is the only path to reading data** (rule 11w). A query
reads and does not change — which is why it may be asked from anywhere and why
none of them can break anything.

<!-- spis:kwerendy -->
| Query | Arguments | What it returns |
|---|---|---|
| `address-book.chapters` | — | chapters: declared and present in the data |
| `address-book.entries` | `[chapter]` | book entries, optionally with the values of a chapter |
| `address-book.entry` | `<entry>` `[chapter]` | a single book entry |
| `address-book.fields` | `<chapter>` | fields of a chapter |
| `address-book.last` | — | id of the entry added most recently |
| `address-book.value` | `<entry>` `<chapter>` `<field>` | value of a single field, masked ones included |
| `audio.effects` | — | sounds assigned to application events |
| `audio.now-playing` | — | what plays, in which mode and whether there is an engine |
| `audio.playlist` | — | playlist entries along with missing files |
| `browser.cwd` | — | paths of both panes along with the active one |
| `browser.entries` | `[pane (0 or 1)]` | entries of the directory shown in the pane |
| `browser.marked` | `[pane (0 or 1)]` | names and paths of marked entries |
| `browser.panes` | — | pane layout: view, filter, marks |
| `browser.selection` | `[pane (0 or 1)]` | entry under the cursor with its attributes |
| `browser.tree` | `[pane (0 or 1)]` | flattened directory tree of the pane |
| `browser.undo` | — | operation stack along with reversibility |
| `core.commands` | — | list of actions callable by name |
| `core.context` | — | where the user stands and what is marked |
| `core.events` | — | dictionary of application events |
| `core.jobs` | — | background jobs: stage, exit code, output size |
| `core.language` | — | active language and languages to choose from |
| `core.module-settings` | `<module>` | settings of the given module |
| `core.modules` | — | modules: accepted, disabled and rejected |
| `core.queries` | — | list of data sources of this run |
| `core.settings` | — | core settings with their values |
| `core.status` | — | last message with its tone |
| `core.theme` | — | active theme and themes to choose from |
| `core.version` | — | application and PHP version, extensions present |
| `core.viewport` | — | window size and frame rendering track |
| `docker.build` | — | build state: stage, tag, latest message |
| `docker.catalog` | — | Registry contents: images or tags |
| `docker.compose` | — | compose projects along with the work stage |
| `docker.containers` | — | containers along with their compose project |
| `docker.environments` | — | environments: kind, address, choice, tunnel state |
| `docker.images` | — | images known to the daemon along with their tags |
| `docker.pull` | — | Image pull state |
| `docker.push` | — | state of pushing an image to the registry |
| `docker.registries` | — | Image registries: address, user, token present? |
| `docker.registry-secret` | `[registry]` | Registry credentials for the cluster |
| `file-info.description` | — | full description of the selected entry |
| `file-info.digest` | — | sha256 checksum along with its stage |
| `file-info.preview` | — | entry thumbnail or the reason there is none |
| `file-info.usage` | — | disk usage along with the stage of the measurement |
| `k8s.cluster` | — | cluster and client versions along with the session stage |
| `k8s.clusters` | — | cluster list from the book and from kubeconfig files |
| `k8s.contexts` | — | contexts from kubeconfig along with the current one |
| `k8s.deployments` | — | deployments along with each container image |
| `k8s.kinds` | — | resource kinds reported by the cluster |
| `k8s.namespaces` | — | namespaces known to the session |
| `k8s.resources` | `<kind>` | rows of the given resource kind along with the stage |
| `ssh.entries` | — | remote directory along with the listing stage |
| `ssh.session` | — | session stage, host and failure reason |
| `ssh.transfer` | — | transfer state: direction, file, bytes, stage |
<!-- /spis -->

## How to put a list under guard

A list is **a markdown table wrapped in an HTML marker**. The marker tells the
test where to look, and the author that the rows below are a copy of the state
of the code:

```markdown
<!-- spis:kwerendy -->
| Query | Arguments | What it returns |
|---|---|---|
| `core.theme` | — | active theme and themes to choose from |
<!-- /spis -->
```

Four things worth knowing before you add one of your own:

1. **The marker name is the identity of the list**, not the title of a section.
   The same list carries **the same name** in both language trees —
   `DocumentationLanguagePairTest` compares exactly those names, not headings.
2. **The number of columns is fixed** and the test takes cells by index. A column
   inserted in the middle shifts every column after it.
3. **Not every column is guarded**, and that is a decision rather than an
   oversight: the "Values" column of the settings list writes eighty-one slider
   stops as `20–80`, because the table is for a reader. The machine guards what
   can be compared without guessing — the name, the argument, the default, the
   key.
4. **The test is written together with the list.** A list without a test is
   a table that looks just as correct on the day it stops being true — which is
   exactly what this chapter is meant to defend against.

## What to do when a compliance test goes red

**The first answer is: fix the list, not the test.** This is the same road the
project closed for the layer rules — a guard you silence is a guard not worth
having.

| What the gate says | What happened | What to do |
|---|---|---|
| the command list drifted from the registry | A command appeared or disappeared | Add or remove the row — in both languages |
| default value of the position … | A default changed in the code | Fix the "Default" column |
| the key list … drifted from the bindings | A key appeared, disappeared or moved | Fix the table of that place |
| links leading nowhere | A file changed its name or place | Fix the link; when a plan step moves — in the index too |
| the trees … have different documents | A file was created in one language only | Add its counterpart; the file name is **in the language of its tree** |
| a diagram without a describing sentence | A diagram arrived without a paragraph before it | Add a sentence saying the same in words |

One case remains in which the documentation is **not** the truth: when the test
shows that the code does something it was never meant to do. That is how the
letter `r` in the Docker module came out in step 63 — it worked, but it was
neither in the status bar nor in the list under `F1`. Then you fix the code, not
the table; you recognise the case by this: **the corrected documentation would
describe behaviour nobody wanted**.

## Where to go next

- [1. Code map](01-code-map.md) — where what you have just added belongs
- [3. How to add your own thing](03-how-to-add.md) — eight guides
- [6. Workflow](06-workflow.md) — the order of the processes and the gate
