# 5. Traps

> Developer guide, part 5 of 8. [Contents](README.md) ·
> [polski](../../pl/przewodnik/05-pulapki.md)

Ten things the project has **already paid for once** — with a benchmark, a live
server, two days of searching or lost content. Each has a **symptom**, a
**cause** and **the place where the bill arrived**.

This is the most expensive knowledge in this repository and at the same time the
least accessible: it lives in the step journals, which is to say in the place
nobody looks **before falling into the same one**.

The first seven come from steps 26–55; the last three were added by phases
XIX–XXI.

---

## 1. `2>&1` on a command whose output is content

**Symptom.** The listing of a remote directory stops halfway. No error, no
warning — the process ends with **exit code zero**.

**Cause.** Merging the streams makes the child's error stream and the pipe
carrying the content **the same file description**. `sftp` runs `ssh`, and with
a `ControlPath` that one is a multiplexer client: it hands its descriptors to the
connection master, which sets them to **non-blocking mode**. The mode is
a property of the file description, so it comes back through the same pipe onto
`sftp`'s output; once the pipe fills up, `write()` returns `EAGAIN`, and OpenSSH
**drops a chunk of the output** and exits successfully.

**Where the bill arrived.** Step 49. The complete output is **418,922 B**;
through a pipe drained every 33 ms **one third** arrived. The cause was
reproduced without PHP, in the shell alone with pauses — to rule out the
language.

**The rule.** A command **whose output is content** does not merge streams. The
listing goes on the output, the reason for failure on a separate field of the
port. `SftpCommandTest::testStreamsAreNeverMerged` and
`BackgroundProcessServiceTest::testLargeOutputSurvivesFrameRateDraining` watch
over it.

---

## 2. `kubectl patch --type=merge` replaces the whole array

**Symptom.** A deployment with two containers has one after the patch.

**Cause.** A merge patch treats a JSON array as **a value**, not as a set — so
the list you give replaces the existing one in full instead of being added to
it.

**Where the bill arrived.** Step 54. Step 61 came back to this question for
`imagePullSecrets` and **checked the thesis on a live cluster before writing the
code**: a **strategic** patch (the default for built-in resources) has a merge
key by name for that field, so it appends. The difference between "appends" and
"replaces" is, in that place, a deployment losing access to its own image.

**The rule.** The patch type is settled **by checking, not by memory** — and it
is done before the first line of code.

---

## 3. `base64_encode()` in `X-Registry-Auth`

**Symptom.** The Docker daemon rejects an image push with `401`, even though the
credential is correct.

**Cause.** The header wants base64 **URL-safe and without padding**. Plain
`base64_encode()` produces a string with `+`, `/` and `=`, which the daemon does
not accept.

**Where the bill arrived.** Steps 54 and 61 (`RegistryAuth`).

**The rule.** The encoding in a header is checked in the specification, not
assumed from a function name.

---

## 4. `rename()` is not always a metadata operation

**Symptom.** An "instant" move takes a minute and blocks the frame, and an
interrupted one leaves a half-written file.

**Cause.** PHP handles `EXDEV` **itself** — when the source and the target are on
different filesystems, it copies the file **inside the `rename()` call**. From
the outside it looks like one fast operation, and it is a full copy with no
progress and no way to interrupt it.

**Where the bill arrived.** Step 42.

**The rule.** "The same filesystem" is checked by **device number** before the
call, not by the aftermath. Across filesystems you take the explicit road: copy,
then remove the source — and **the source disappears only after the target is
written in full**.

---

## 5. Raw mode leaves `isig` and `iexten` on

**Symptom.** `Ctrl`+`C` ends the application before it reads anything.
`Ctrl`+`V` swallows the next byte — and the key after it vanishes.

**Cause.** The project **does not use full `stty raw`**: `isig` stays on
deliberately, so that `Ctrl`+`C` remains a signal, and `iexten` — because turning
it off takes other things away. As a side effect `lnext = ^V` swallows the byte
that follows it.

**Where the bill arrived.** Step 55, while introducing the pointer.

**The rule.** Six letters are **taken by the terminal** and must not be
shortcut-grabbed: `c` and `z` are signals, and `h`, `i`, `j` and `m` arrive as
the same byte as `Backspace`, `Tab` and `Enter`.

---

## 6. A child process gets no input

**Symptom.** `kubectl apply -f -` does not work. Passing a password to an `ssh`
process on its input does not work. Neither reports a readable error.

**Cause.** The background work port **does not give the child any input** — and
`ssh` reads a password from the **controlling terminal** anyway, not from
standard input.

**Where the bill arrived.** Steps 48, 52 and 58.

**The rule.** Content reaches a child **through a file** (`kubectl apply -f
file`, a registry credential in a `0600` file deleted after use), and a password
— through **`SSH_ASKPASS` and an environment variable**. Never through the
command line: `ps` sees the command line.

---

## 7. An exit code other than zero is not in itself a failure

**Symptom.** Directory disk usage does not appear, even though `du` computed and
printed a result.

**Cause.** `du` exits with **1** for every directory it could not read (no
permissions) — and still reports the sum of what it did read.

**Where the bill arrived.** Step 26.

**The rule.** The exit code is interpreted by **whoever ordered the work**,
because only they know what it means for their command. The port returns the
code, not a verdict.

---

## 8. A query called on every tick

**Symptom.** Nothing to see. The application works normally — and authentication
material passes through the query registry **thirty times a second**.

**Cause.** A module tick called the query returning the registry credential on
**every** tick and discarded the answer on all but the one where the registry
had actually changed. The code looked innocent: "take the current credential and
check whether it changed".

**Where the bill arrived.** Step 61 — found **during a benchmark**, not during
a code review. The same trap as in step 59, where a module asked the cluster for
its server version every frame.

**The rule.** Anything costly or sensitive travels **as a closure, not as
a value**: the tick calls it only when it really changes the endpoint. A separate
flow **counting the calls** watches over it — because a module that asks once
looks exactly like a module that asks endlessly.

---

## 9. A "take once" channel has one recipient

**Symptom.** One of two recipients never sees anything. **Silently** — because
`null` there means "nothing has happened yet", not "somebody already took it".

**Cause.** The outcome of the work is taken **once** (`takeOutcome()`). When
a second recipient is wired to the same channel, the first takes the result and
the second gets `null` and concludes the work is still running.

**Where the bill arrived.** Step 61 — two things at once: an action coordinator
running **one action at a time** received a second `begin()`, which abandoned the
first, and the result channel had two recipients.

**The rule.** **A new recipient means a new channel, not a split of the existing
one.** The symptom of a second recipient is always the same: something works
"sometimes".

---

## 10. A key handled without a `KeyBinding`

**Symptom.** The key works, but **it is not in the status bar or in the `F1`
list** — so for the user it does not exist.

**Cause.** Handling a key (`handle()`) and declaring it (`bindings()`) are two
different places. `StatusHintsFlowTest` makes sure the status bar does **not
promise** a key nobody handles — but it does **not** watch the other direction.

**Where the bill arrived.** Step 63, while deriving the key list for the manual.
The letter `r` (registry content in the Docker module) had worked since step 61
and survived two reviews; next to it the letter `e` was declared correctly.

**The rule.** A key is added **in two places at once** — in `handle()` and in
`bindings()`. Today's check is manual:

```bash
grep -n "raw === self::" src/Module/<Name>/Presentation/*.php
grep -n "KeyBinding::character" src/Module/<Name>/Presentation/*.php
```

---

## Where to go next

- [3. How to add your own thing](03-how-to-add.md) — eight guides
- [6. Workflow](06-workflow.md) — the order of processes and the gate
- [7. How to read the decision log](07-decision-log.md) — where these stories come from
