---
eyebrow: 'Docs · Building'
lede:    'A single .phar archive bundling the CLI source + Composer vendor + autoloader. Runs on any PHP 8.2+ host. Two commands to produce; ~650 KB.'

see_also:
  - { href: './static-binary.md',     meta: '7 min' }
  - { href: './troubleshooting.md',   meta: '5 min' }
  - { href: 'https://github.com/box-project/box', meta: 'external', label: 'Box (PHAR compiler)' }

prev: { label: 'Consuming generated', href: '../code-generation/consuming-output.md' }
next: { label: 'Static binary',       href: './static-binary.md' }
---

# Building a PHAR

A PHAR (PHP archive) bundles the CLI project into one file:
`opcua-cli.phar`. Runs on any host with PHP ≥ 8.2 — copy the
file, run `php opcua-cli.phar <cmd>`. Easier to distribute than
a full Composer install; lighter than a static binary.

## Prerequisites

| Requirement                          | Why                                          |
| ------------------------------------ | -------------------------------------------- |
| PHP 8.2–8.5                          | The CLI's runtime requirement                |
| `ext-openssl`, `ext-phar`, `ext-mbstring`, `ext-tokenizer`, `ext-simplexml`, `ext-dom` | Composer + Box + runtime |
| Composer 2.x                         | Dependency install                           |
| ~100 MB free disk                    | Vendor directory + Box                       |

The build host's PHP version does not need to match the deployment
host's, as long as both are ≥ 8.2.

## Build steps

<!-- @steps -->
- **Clone and install production dependencies.**

  ```bash
  git clone https://github.com/php-opcua/opcua-cli.git
  cd opcua-cli
  composer install --no-dev --prefer-dist --classmap-authoritative
  ```

  `--no-dev` keeps Pest, PHPStan, and other dev-only packages
  out of the PHAR. `--classmap-authoritative` collapses all
  autoload to a single classmap — fastest at runtime.

- **Download Box.**

  ```bash
  curl -LsS -o box.phar https://github.com/box-project/box/releases/latest/download/box.phar
  ```

  Box is the PHAR compiler — a single PHAR itself. No
  installation, no global state.

- **Compile.**

  ```bash
  php -d phar.readonly=0 box.phar compile -v
  ```

  Box reads `box.json.dist`, packs `src/` and `vendor/` into a
  PHAR with a generated bootstrap stub. The `-v` flag prints the
  file list as it goes.

- **Verify.**

  ```bash
  php build/opcua-cli.phar --version
  ```

  Should print the version. Confirms the PHAR is well-formed.
<!-- @endsteps -->

## What ends up inside

The PHAR bundles four broad chunks: Box's stub, the CLI's `src/`,
`vendor/php-opcua/opcua-client/`, and Composer's autoload + the
remaining transitive dependencies. Exact sizes shift with each
upstream release; check your built artefact with `ls -lh
build/opcua-cli.phar` after running `composer build:phar`.

The PHAR is self-describing — inspect it with
`php -r "var_dump(new Phar('opcua-cli.phar'));"`, or list its
contents with `php -r "foreach (new RecursiveIteratorIterator(new
Phar('build/opcua-cli.phar')) as \$f) echo \$f, PHP_EOL;"`.

## Why `phar.readonly=0`

PHP's `phar.readonly` setting defaults to `1` (forbidding PHAR
modification at runtime). Building the PHAR is a write
operation; the flag disables that protection for the build PHP
process only. The produced PHAR is read-only at runtime — no
security concern downstream.

## Running the built PHAR

<!-- @code-block language="bash" label="terminal — run" -->
```bash
php build/opcua-cli.phar browse opc.tcp://localhost:4840
```
<!-- @endcode-block -->

Make it directly executable:

<!-- @code-block language="bash" label="terminal — install" -->
```bash
chmod +x build/opcua-cli.phar
sudo install -m 0755 build/opcua-cli.phar /usr/local/bin/opcua-cli
opcua-cli --version
```
<!-- @endcode-block -->

The PHAR's shebang (`#!/usr/bin/env php`) makes it run as a
regular script — same behaviour as `php opcua-cli.phar`.

## What about the dev dependencies?

`composer install --no-dev` skips PHPUnit, Pest, PHPStan,
php-cs-fixer. The runtime doesn't need them — only the test
suite does.

If you produce the PHAR for an internal release that also runs
tests, do two installs:

<!-- @code-block language="bash" label="bash — dev for tests, prod for phar" -->
```bash
# Tests
composer install --prefer-dist
vendor/bin/pest

# PHAR build
rm -rf vendor
composer install --no-dev --prefer-dist --classmap-authoritative
php -d phar.readonly=0 box.phar compile
```
<!-- @endcode-block -->

The CI workflow in this repo does exactly that.

## Configuration

`box.json.dist` at the project root drives Box. Notable settings:

| Setting              | Default value                                         | Effect                                          |
| -------------------- | ----------------------------------------------------- | ----------------------------------------------- |
| `output`             | `build/opcua-cli.phar`                                | Output path                                     |
| `main`               | `bin/opcua-cli`                                       | Entry script                                    |
| `directories`        | `["src"]`                                             | Source directories to pack                      |
| `composer`           | `{"package-paths": ["vendor"]}`                       | Pack the vendor directory                       |
| `compression`        | (none)                                                 | The PHAR is uncompressed (`Phar::NONE`)         |

Compression would shave ~100 KB off the size but adds runtime
decompression cost. Uncompressed is the right default for this
PHAR's size — the I/O is negligible.

To override Box settings, copy `box.json.dist` to `box.json` and
edit. Box uses `box.json` when present, falling back to
`.dist`.

## Distribution

The PHAR is a single file. Distribution paths:

- **GitHub Releases** — attach `opcua-cli.phar` to a tagged
  release. Operators download with `curl`.
- **Internal package repo** — host on your artefact server
  (Artifactory, Nexus). Same single-file convenience.
- **Docker image** — `COPY opcua-cli.phar /usr/local/bin/opcua-cli`
  in a slim base image with PHP installed. ~80 MB image total.

The PHAR is **architecture-independent** — same file runs on
Linux / macOS / Windows / ARM as long as PHP 8.2+ is present.

## Comparison with the static binary

| Aspect                          | PHAR                                | Static binary                                |
| ------------------------------- | ----------------------------------- | -------------------------------------------- |
| Requires PHP on host            | Yes (8.2+)                          | No                                            |
| Size                            | ~650 KB                              | ~25 MB                                        |
| Build complexity                | Low (one Box call)                   | High (compile PHP from source)                |
| Architecture-portable           | Yes — any PHP host                   | Per-architecture binary                       |
| Startup cost                    | ~50 ms (PHP bootstrap + PHAR open)   | ~10 ms                                        |
| Best for                        | Dev workstations with PHP            | Servers / appliances without PHP              |

For most teams, the PHAR is enough. The static binary is the
escape hatch for hosts that cannot install PHP.

## What can go wrong

- **`phar.readonly=1` at compile time** — Box can't write the
  PHAR. Use `php -d phar.readonly=0 box.phar compile`.
- **Stale `vendor/` from a previous dev install** — leftover
  dev packages bloat the PHAR. `rm -rf vendor` and reinstall
  with `--no-dev`.
- **Missing extensions on the build host** — Box itself uses
  `phar`, `tokenizer`, `dom`, `simplexml`. Some minimal PHP
  installs lack these; install via the distro package manager.
- **Output directory not writable** — `build/` must exist and
  be writable. Box does not create it.

For deeper issues see [Troubleshooting](./troubleshooting.md).
