# 1. Environment

> Onboarding, stop 1 of 5 · **5 minutes**. [Index](README.md) ·
> [polski](../../pl/onboarding/01-srodowisko.md)

## What you do

```bash
git clone <repository-address> lm
cd lm
make check-env
make install
```

`make` with no arguments prints the list of every entry point into the project's
processes — and at this stage it is the only list you need to know. **Every
process in this project has a `make` target**; if you are looking for a command
that is not there, you are almost certainly looking in the wrong place.

## What you will see

`make check-env` splits the requirements into four groups, and **the grouping is
the information here**, not the result alone:

```
Required:      any of them missing → the application will not start
Recommended:   missing → the application runs, but not the way the docs picture it
Optional:      missing → part of the features disappear, the rest works
Not checkable: the one thing make cannot answer
```

The last group says outright what it does not know: **whether the terminal
itself speaks Sixel**. Answering that needs an interactive session in raw mode,
which `make` does not have. That is what `make probe` is for — and it will come
in handy at stop two.

## A map of the environment — what is optional and what happens without it

**A missing optional part is a degradation here, not a failure.** This is the
sentence a newcomer has no way of knowing, and it decides whether they call the
application broken. It starts, says in the status bar what was missing, and
carries on without that part.

| Part | Required? | What happens without it |
|---|---|---|
| PHP `^8.3` | **yes** | the application does not start |
| `ext-imagick` | **yes** | the application does not start (the check sits in `bin/light-manager`, before anything else) |
| `ext-pcntl` | **yes** | the application does not start |
| `stty` | **yes** | the terminal cannot be put into raw mode — hence Linux or macOS, not Windows |
| Composer 2.x | **yes** | there is nothing to install the dependencies with |
| ImageMagick with the `SIXEL` coder | recommended | **the text path**: characters instead of a picture |
| A terminal that speaks Sixel | recommended | same as above |
| `ext-glfw` | optional | no `--window` mode, and the audio module answers with a sentence about unavailability |
| An OpenSSH client | optional | the remote session module **disappears from the list together with the reason** |
| `ext-curl` | optional | the Docker module disappears from the list together with the reason |
| `kubectl` | optional | the Kubernetes module is there but has nobody to talk to |
| `ext-intl` | optional | worse sorting and number formatting |
| `xterm` | optional | `make run-xterm` and `make bench-xterm` will not work |

Three of those absences show up inside the application as **a module that is not
there, together with a stated reason**. That is deliberate: a module with nobody
to talk to disappears from the list instead of staying on it and refusing at
every click.

## When `make install` falls over

One environment defect happens often enough to have its own target: Composer
ending in a **segmentation fault** with `imagick` loaded. Then:

```bash
make install-safe COMPOSER_INI_SCAN_DIR=/path/to/conf.d-without-imagick
```

More: [manual, "When something does not work"](../manual/02-installation.md#when-something-does-not-work).

## How you know you are done

`make check-env` ends with **`Environment is ready.`** — or it reports gaps and
you **can name every one of them** as optional together with its consequence
from the table above. A gap in the `Required` group is the only one that stops
the path; every other one lets you through.

Next: [2. Running](02-running.md).
