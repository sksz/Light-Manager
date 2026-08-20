# 7. Scenarios

> User manual, part 7 of 7. [Contents](README.md) ·
> [polski](../../pl/podrecznik/07-scenariusze.md)

Eight paths from start to finish. Each has **what for**, **steps with keys**,
**what you see** and **what can go wrong**.

Every scenario describes the path walked by a **functional flow test** in
`tests/Functional/` — the name of the flow stands next to the scenario. That is
not trivia but a commitment: when the flow changes, the scenario lies, so both
are replaced in the same plan step.

---

## 1. Copy the marked files to the other pane and undo the mistake

*Flows: `FileOperationsFlowTest`, `MarkedEntriesFlowTest`, `UndoFlowTest`*

**What for.** A file manager's most common task: take a few files from one
directory and put them in another — and then discover it was the wrong one.

**Steps.**

1. `F2` → the "File browser" tab → **Split into two panes: yes** → `Esc`.
2. In the left pane walk into the source directory with `Enter`, in the right
   one (`Tab`) into the target; `Tab` back to the left.
3. Put the cursor on the first file and press **`Space`** as many times as you
   need — the cursor steps down by itself. A range is marked with `Shift`+`↓`.
4. **`F5`**. The overlay shows the **other pane's** directory as the target —
   `Enter` commits.
5. A mistake? **`Alt`+`U`** undoes the last reversible operation, and **`F3`**
   shows the whole stack.

**What you see.** The path strip summarises the set (`• 12 of 340 · 4.1 GB`).
While copying, the progress overlay gives the file name, an "N of M" counter and
a bar counted **in bytes**, not in files. After the operation, what stays marked
is **what it did not touch** — the skipped and the failed.

**What can go wrong.**

- **A copy cannot be undone** — it stands greyed out on the stack. `Alt`+`U`
  will undo a rename, a move, a trashing and an empty new directory.
- **A taken name in the target** stops the work with a six-answer question;
  "overwrite all" and "skip all" apply to the rest of the run.
- **`Esc` mid-work** interrupts, and a half-written file **disappears**.
- **A directory cannot be copied into its own inside** — the application refuses
  and says why.

---

## 2. Find a file with the filter, look at it and check its description

*Flows: `FilterFlowTest`, `TextPreviewFlowTest`, `FileDescriptionFlowTest`*

**What for.** The directory holds a thousand entries and you remember three
letters of the name.

**Steps.**

1. **`/`** and type a fragment of the name — the list narrows with every letter.
2. `↑`/`↓` along the narrowed list; **`Enter`** keeps it narrowed.
3. **`Ctrl`+`D`** opens the entry's description.
4. **`Tab`** moves the cursor into the content preview; `PgUp`/`PgDn` scroll by
   a panel, `Alt`+`Z` toggles wrapping.
5. **`s`** computes the checksum, **`d`** the directory disk usage.
6. `Esc` returns to the files, `Esc` again drops the filter.

**What you see.** The matched fragment is **highlighted**. The description is
four collapsible sections; the preview shows the content of a text file — also
`README` and `.gitignore`, because whether a file is text is decided by
a cascade, not by the extension alone.

**What can go wrong.**

- **The filter is not a pattern** — it is a case-insensitive substring. There
  are no wildcards and no regular expressions.
- **The checksum does not start on its own**, and above the configured size
  limit it does not start at all — it says why.
- **After `End` in the preview the line numbers disappear**; `Home` brings them
  back.
- **There is no preview for binaries**, and it says so plainly.

---

## 3. Connect to a host and download a file from it

*Flows: `SshSessionFlowTest`, `RemoteDirectoryFlowTest`, `FileTransferFlowTest`*

**What for.** Look at somebody else's machine and pull a file off it without
leaving the application.

**Steps.**

1. **`Ctrl`+`W`** → the "Remote session" tab → **`F7`** adds an entry → `F4`
   leads through the fields: address, port, user, authentication method.
2. **`Ctrl`+`S`** → the cursor on the entry → **`Enter`** connects.
3. Host unknown? An overlay asks about the **`SHA256:…` fingerprint** — read it
   and confirm.
4. After connecting the screen shows the **remote directory**: `Enter` goes in,
   `Backspace` goes back, `/` narrows, `Ctrl`+`H` shows hidden entries.
5. Cursor on the file → **`F5`** downloads it into the directory the browser
   stands in. `F6` sends one the other way.

**What you see.** The remote list has the same columns as the local one. The top
bar says who the session stands with. The progress bar counts bytes **when
downloading**; when uploading it only shows that work is going on — how much went
into the network the `sftp` client on a pipe does not say.

**What can go wrong.**

- **The module is not on the list** → the OpenSSH client is missing.
- **A key that differs from the remembered one** is not a question but
  a **refusal**.
- **The session state does not refresh by itself** — a session dropped by the
  network may show as alive for a while; **`F5`** is what checks it.
- **Files are transferred, not directories.**
- **An interruption leaves no file that looks complete** — the content lies
  under the working name `.name.lm-part` until it arrives in full.

---

## 4. Bring a compose project up and watch a container log

*Flow: `DockerFlowTest`*

**What for.** Start a project and see what it says when it will not come up.

**Steps.**

1. In the browser, enter the directory holding `compose.yaml`.
2. **`F12`** → `docker.up` → `Enter`. Without an argument the command takes the
   file from the directory the browser stands in.
3. **`Ctrl`+`O`** opens the container list; **`F5`** narrows it to the project.
4. Cursor on a container → **`Enter`** opens the **live log**.
5. `↑` holds the view, **`End`** returns to the end, `Esc` closes.
6. **`F4`** stops or starts the container, `Shift`+`F4` restarts it.

**What you see.** The lists come straight from the daemon and refresh every few
seconds while the screen is visible. The logs keep flowing **even while you look
elsewhere**.

**What can go wrong.**

- **The module is not on the list** → the PHP `curl` extension is missing.
- **A missing local socket does not take the module away** — it is a state of
  the environment, said in a sentence on the screen.
- **`docker.up` in a remote environment asks first**: the compose file is read
  by the client on this side, but `volumes:` point at paths on the daemon's
  machine.

---

## 5. Build an image and deploy it to a cluster

*Flows: `DeployImageFlowTest`, `ClusterFlowTest`*

**What for.** Walk the road from a directory with a `Dockerfile` to a running
deployment, with a private registry along the way.

**Steps.**

1. **`Ctrl`+`O`** → **`F7`** builds an image: it asks for the directory with the
   `Dockerfile`, then for a name.
2. **`F12`** → `docker.push` → choose the registry (with one it does not ask).
3. **`Ctrl`+`K`** → **`c`** picks the cluster, **`n`** the namespace.
4. **`F12`** → `k8s.deploy-image` → give the image name and the deployment.
5. `Enter` on a resource opens the details, **`y`** shows the raw YAML, **`l`**
   the pod logs.

**What you see.** The tree on the left: API groups, their resource kinds, their
resources — **a branch is read only when expanded**. The header shows both
versions when the client and the server differ by more than one release.

**What can go wrong.**

- **A private registry needs nothing on the cluster side**: the deployment
  **creates the secret itself** (`lm-registry-<name>`) and attaches it without
  removing the ones the deployment already had.
- **The credential never passes through the command line** — it goes in a file
  with mode `0600`, deleted right after use.
- **An unreachable cluster** leaves a screen with three keys: `c` the cluster
  list, `k` the context, `Enter` to ask again.
- **The `kubeconfig` file is left untouched** — the application does not write
  to it.

---

## 6. Ask the application about its own state

*Flows: `QueryWindowFlowTest`, `QueryCatalogueTest`*

**What for.** Find out what the application knows about itself: what is
selected, which jobs run in the background, what is playing, which containers
the daemon sees.

**Steps.**

1. **`F12`** opens the command window.
2. **`Tab` on an empty line** switches it to **queries**.
3. `↑`/`↓` along the list, or type a fragment of the name; `Enter` asks.
4. **`Alt`+`C`** copies the whole answer to the clipboard.
5. `Tab` returns to the commands, `Esc` closes the window.

**What you see.** The answer line by line, in an overlay above the screen.
Queries are named with their owner's namespace — `core.*`, `browser.*`,
`docker.*` and so on.

**What can go wrong.**

- **A query reads and does not change** — none of them can break anything.
- **A query with an argument** will ask for it rather than answer; a missing
  argument **leaves the window open** along with the typed line.
- **An answer that has not arrived yet** (background work) says the work is
  going on, instead of waiting.

---

## 7. Add a place to the address book and connect from it

*Flows: `AddressBookFlowTest`, `ClusterBookFlowTest`*

**What for.** One address is sometimes needed by three modules at once — and
then it is worth having in one place rather than three.

**Steps.**

1. **`Ctrl`+`W`** opens the book; `←`/`→` walk the **chapters**.
2. **`F7`** adds an entry — give it a name.
3. The "Remote session" tab → **`F4`** leads through the fields: address, port,
   user, authentication.
4. **The same entry**, the "Docker" tab → `F4` → kind `tunnel`, a target
   pointing at this entry, the socket path.
5. The "Kubernetes" tab → `F4` → the `kubeconfig` file and the context.
6. `Ctrl`+`S`, `Ctrl`+`O`, `Ctrl`+`K` — every module sees that entry in its own
   place.

**What you see.** The "All" tab shows entries with their ids, each of the others
the columns of one chapter. Fields marked as **secret** are masked on screen.

**What can go wrong.**

- **The entry's identity is its id, not its name** — the name may be changed,
  repeated or left empty; references held by other modules will not notice.
- **A chapter belongs to nobody** — every module reads and changes every
  chapter.
- **The token lies in the file in plain text**, mode `0600`. There is no
  encryption.
- **Lists from older versions move over by themselves** on first run; the old
  record is left untouched.

---

## 8. Select an error in a log with the mouse and copy it

*Flows: `ClipboardFlowTest`, `SelectionInOverlayFlowTest`, `PointerFlowTest`*

**What for.** Take content off the screen that you never typed — a log line,
a key fingerprint, a query answer — and paste it elsewhere.

**Steps.**

1. Open the content: a container log (`Ctrl`+`O`, `Enter`), the query window
   (`F12`, `Tab`) or the host key fingerprint question.
2. **Drag with the mouse** across the frame — the rectangle covers what you
   want.
3. **`Alt`+`C`** copies. The status bar says **what** was copied.
4. Paste with **`Alt`+`V`** — into the focused text field: a file name,
   a command line, a setting value.

**What you see.** A rectangle over the content and a sentence such as "14 frame
rows selected", and after copying — "Selection copied: 14 rows". Selecting works
**inside an overlay** exactly as it does on the screen beneath it.

**What can go wrong.**

- **Without a selection `Alt`+`C` copies something else**: the paths of the
  entries marked with Space, and when there are none — the path of the entry
  under the cursor.
- **`Alt`+`V` above the file list** says there is nowhere to paste and **does
  not ask the terminal** for the clipboard content.
- **A terminal that does not return the clipboard** stays silent instead of
  refusing — `Alt`+`V` ends after a quarter of a second with a sentence. See
  [chapter 2](02-installation.md), "When something does not work".
- **Content longer than 64 kB ends with a refusal** and a sentence, rather than
  a silent cut in the middle.
- **Under XTerm `Alt` needs `metaSendsEscape: true`** — without it the shortcut
  never arrives.

---

## Where to go next

The full key list: [chapter 3](03-screen-and-controls.md). What each module can
do: [chapter 5](05-modules.md). Want to **develop** the application rather than
just use it → [developer guide](../guide/README.md).
