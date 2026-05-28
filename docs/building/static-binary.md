---
eyebrow: 'Docs · Building'
lede:    'A self-contained executable that runs on hosts with no PHP installed. Built by combining the PHAR with phpmicro from static-php-cli. ~20-25 MB. Linux, macOS, Windows.'

see_also:
  - { href: './phar.md',                meta: '6 min' }
  - { href: './troubleshooting.md',     meta: '5 min' }
  - { href: 'https://github.com/crazywhalecc/static-php-cli', meta: 'external', label: 'static-php-cli' }

prev: { label: 'PHAR build',    href: './phar.md' }
next: { label: 'Troubleshooting', href: './troubleshooting.md' }
---

# Building a static binary

A static binary bundles the CLI's PHAR with a statically linked
PHP interpreter (`phpmicro`). The result is one file you can ship
to a Linux server, a Windows workstation, or a maintenance
laptop — no PHP install required on the target.

Built by [static-php-cli (SPC)](https://github.com/crazywhalecc/static-php-cli).
Expect ~20-25 MB depending on extension set.

## Prerequisites

| Requirement                          | Note                                              |
| ------------------------------------ | ------------------------------------------------- |
| Everything from the PHAR build       | The static binary needs the PHAR as input         |
| A C toolchain                        | `gcc` or `clang`, `make`, `autoconf`, `bison`, `re2c`, `libtool` |
| ~2 GB free disk                      | SPC compiles PHP + OpenSSL + libxml2 + libzip from source |
| ~2 GB RAM during build               | Compiler memory footprint                          |

First build is **20-40 minutes**. Subsequent builds with a warm
SPC cache are **3-5 minutes**.

SPC's `doctor --auto-fix` installs the missing native
dependencies on Debian / Ubuntu / Fedora / Arch automatically.

## Build steps

<!-- @steps -->
- **Build the PHAR first.** See [PHAR build](./phar.md). The
  output `build/opcua-cli.phar` is the input to the next steps.

- **Install static-php-cli.**

  ```bash
  git clone --depth=1 https://github.com/crazywhalecc/static-php-cli.git spc-src
  cd spc-src
  composer install --no-dev --prefer-dist
  cd ..

  ./spc-src/bin/spc doctor --auto-fix
  ```

- **Download PHP + extension sources.**

  ```bash
  export SPC_EXTENSIONS="openssl,simplexml,dom,phar,posix,mbstring,tokenizer,sockets,ctype,fileinfo,filter,pcre,xml,xmlwriter,xmlreader,libxml"

  ./spc-src/bin/spc download \
      --with-php=8.4 \
      --for-extensions="$SPC_EXTENSIONS"
  ```

  The extension list matches the official release builds. Trim
  for a smaller binary (see [Customising](#section-customising-the-build)).

- **Compile the `phpmicro` SAPI.**

  ```bash
  ./spc-src/bin/spc build "$SPC_EXTENSIONS" --build-micro
  ```

  Output: `spc-src/buildroot/bin/micro.sfx` — a self-extracting
  PHP runtime with the chosen extensions baked in.

- **Combine PHAR + micro SAPI.**

  ```bash
  ./spc-src/bin/spc micro:combine build/opcua-cli.phar --output=opcua-cli
  chmod +x opcua-cli
  ./opcua-cli --version
  ```

  Result: `opcua-cli` (single file, 20-25 MB, no external runtime
  dependencies).
<!-- @endsteps -->

## Per-platform notes

### Linux x86_64

Default target. The official release pipeline uses
`ubuntu-latest` runners; reproduce locally on any glibc-based
Linux with PHP 8.2+ available.

### Linux aarch64

Two strategies, ranked:

1. **Native build on an aarch64 host** — same commands as
   x86_64, on a Raspberry Pi 4/5, AWS Graviton, or
   `ubuntu-24.04-arm` runner. Reliable and fast.
2. **Cross-compile from x86_64** — pass `--arch=aarch64` to
   `spc build`. SPC fetches a cross-toolchain on first use;
   the first build is ~40 minutes.

The official CI builds aarch64 natively; expect the same to be
the smoother path.

### macOS

| Host                                     | Produces                  |
| ---------------------------------------- | ------------------------- |
| Apple Silicon (M1/M2/M3/M4)              | `macos-arm64`              |
| Intel                                     | `macos-x86_64` (Rosetta 2 won't run an arm64 binary on Intel) |

`xcode-select --install` covers the toolchain. `spc doctor
--auto-fix` handles the rest.

**Code signing:** SPC does not sign. If you ship to non-technical
macOS users, sign with `codesign` + `notarytool` yourself —
otherwise Gatekeeper blocks the first launch.

### Linux musl / Alpine

For container images (`FROM alpine`, `FROM scratch`):

<!-- @code-block language="bash" label="terminal — musl" -->
```bash
./spc-src/bin/spc build "$SPC_EXTENSIONS" --build-micro --with-libc=musl
```
<!-- @endcode-block -->

The musl binary is ~15-18 MB (slightly smaller than glibc) and
runs unchanged on Alpine, minimal containers, and any glibc
system.

### Windows x86_64

Experimental but wired into CI (`continue-on-error: true` on the
release workflow). Requires the MSYS2 + mingw-w64 toolchain. See
the
[SPC Windows build guide](https://github.com/crazywhalecc/static-php-cli/blob/main/docs/guide/windows-build.md).

The CLI itself runs fine on Windows — `posix_isatty` is guarded
by `function_exists()` in `ConsoleOutput.php`, with a `TERM`
env-var fallback.

## Customising the build

### Different PHP version

Pass `--with-php=8.5` (or 8.3, 8.2) to the `download` step. The
combine step uses whatever PHP version was built most recently.

### Smaller binary — trim extensions

The runtime minimum is shorter than the official build's set:

<!-- @code-block language="text" label="minimum SPC_EXTENSIONS" -->
```text
openssl,simplexml,dom,phar,mbstring,ctype,pcre,xml,xmlwriter,libxml
```
<!-- @endcode-block -->

| Extension     | Status            | Effect of removing                            |
| ------------- | ----------------- | --------------------------------------------- |
| `posix`       | Optional          | TTY detection falls back to `TERM` env var    |
| `sockets`     | Transitive pin    | Not used directly by the CLI                  |
| `fileinfo`    | Defensive         | Some Composer packages probe for it           |
| `tokenizer`   | Defensive         | Same                                          |
| `filter`      | Defensive         | Same                                          |
| `xmlreader`   | Defensive         | Same                                          |

Dropping the optional / defensive set trims ~3 MB. Update
`SPC_EXTENSIONS` and re-run `download` + `build` (the cache is
per-extension-set).

### Custom PHAR contents

Edit `box.json.dist` at the project root. See [PHAR build ·
Configuration](./phar.md#section-configuration).

## Distribution

| Where                                | How                                                  |
| ------------------------------------ | ---------------------------------------------------- |
| GitHub Releases                      | Attach per-platform binaries to a tagged release    |
| Internal artefact server             | Same — upload binaries as build artefacts            |
| Docker `FROM scratch`                | `COPY opcua-cli /opcua-cli`, `ENTRYPOINT ["/opcua-cli"]` — ~22 MB image |
| Operator USB sticks                  | Single file, works without install                   |

For container images, the musl variant produces a ~20 MB image
including the `opcua-cli` binary. With `FROM scratch`, the
image **is** the binary plus its dependencies — minimal attack
surface.

## Verifying after build

<!-- @code-block language="bash" label="terminal — verification" -->
```bash
./opcua-cli --version
# → opcua-cli 4.4.0

./opcua-cli --help
# Should print the command catalogue

# End-to-end smoke test:
./opcua-cli endpoints opc.tcp://localhost:4840
```
<!-- @endcode-block -->

If `--help` works but `endpoints` fails, the issue is likely
networking, not the binary. If `--version` fails outright, the
binary is broken — review the build logs.

## CI workflow

The repo's `.github/workflows/release-binaries.yml` runs this
flow on tag push, producing binaries for:

- `linux-x86_64`
- `linux-aarch64`
- `macos-arm64`
- `linux-musl` (Alpine-compatible)
- `windows-x86_64` (best-effort, `continue-on-error: true`)

The artefacts are attached to the GitHub Release matching the
tag. Most users download from there rather than building.

## Comparison with the PHAR

| Aspect                          | PHAR                                | Static binary                                |
| ------------------------------- | ----------------------------------- | -------------------------------------------- |
| Requires PHP on host            | Yes (8.2+)                          | No                                            |
| Size                            | ~650 KB                              | ~22 MB                                        |
| Build time                      | Seconds                              | 20-40 min first build                         |
| Architecture-portable           | Yes                                  | Per-architecture                              |
| Startup cost                    | ~50 ms                               | ~10 ms                                        |
| Best for                        | Dev workstations                    | Servers, appliances, USB sticks                |

For deployments where you control the host (you can install PHP),
the PHAR is the right pick. The static binary is for hosts where
PHP is not allowed or available.

## What can go wrong

| Symptom                             | Likely cause                                          |
| ----------------------------------- | ----------------------------------------------------- |
| `spc doctor` reports missing tools  | Install via distro package manager                    |
| `spc download` hits GitHub rate limit | Set `GITHUB_TOKEN`                                  |
| Build OOMs on a small VM             | Need ≥ 2 GB RAM; rent a beefier build host           |
| Binary runs but exits with library error | Missing extension in `SPC_EXTENSIONS` — rebuild with the full set |
| Binary too large                     | Trim extensions; switch to musl                       |

For deeper diagnostics see [Troubleshooting](./troubleshooting.md).
