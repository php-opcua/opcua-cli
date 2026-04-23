# Build from source

This guide covers how to produce a standalone `opcua-cli` executable yourself — useful when your target platform is not covered by the official release binaries, when you need a custom extension set, or when you want to bundle the CLI inside a container image.

Two independent steps are involved:

1. **PHAR build** — pack the project code + vendor into a single `.phar` archive with [Box](https://github.com/box-project/box).
2. **Static binary build** — combine the PHAR with a statically linked PHP runtime (`phpmicro`) produced by [static-php-cli (SPC)](https://github.com/crazywhalecc/static-php-cli).

If you already have PHP installed on the target machine you can stop after step 1 and run `php opcua-cli.phar <cmd>`. Step 2 is only needed when you want a self-contained binary with zero runtime dependencies.

## Common prerequisites

On the build host:

- PHP 8.2–8.5 with `openssl`, `phar`, `mbstring`, `tokenizer`, `simplexml`, `dom` enabled — any distro package will do, it only needs to run Composer and Box.
- [Composer 2.x](https://getcomposer.org/)
- `git`, `curl`, `tar`, `make`, a working C toolchain (`gcc` / `clang`).
- Around **2 GB free disk** and **2 GB RAM** during the SPC build (compiles PHP + OpenSSL + libxml2 + libzip + others from source).
- First build is slow (20–40 minutes); subsequent builds with a warm SPC cache take 3–5 minutes.

Clone the repository and install production-only dependencies (Composer dev deps like Pest are not needed for the binary):

```bash
git clone https://github.com/php-opcua/opcua-cli.git
cd opcua-cli
composer install --no-dev --no-interaction --prefer-dist --classmap-authoritative
```

## Step 1 — Build the PHAR

Box is the only piece of dev-time tooling this step needs.

```bash
curl -LsS -o box.phar https://github.com/box-project/box/releases/latest/download/box.phar
php -d phar.readonly=0 box.phar compile -v
```

Output: `build/opcua-cli.phar` (≈ 650 KB, 230+ files — `src/` + `vendor/` + the generated Composer autoloader). The PHAR is self-describing and runs under any PHP ≥ 8.2:

```bash
php build/opcua-cli.phar --version
php build/opcua-cli.phar browse opc.tcp://localhost:4840
```

This is a valid distribution form on its own — ship `opcua-cli.phar` alongside any machine that already has PHP. Stop here if that's your target.

## Step 2 — Build the static binary

### 2.1 — Install static-php-cli

```bash
git clone --depth=1 https://github.com/crazywhalecc/static-php-cli.git spc-src
cd spc-src
composer install --no-dev --no-interaction --prefer-dist
cd ..
```

Confirm the toolchain is complete — SPC auto-installs missing native build prerequisites (gcc, autoconf, bison, re2c, libtool…) on supported distros:

```bash
./spc-src/bin/spc doctor --auto-fix
```

### 2.2 — Download PHP + extension sources

The extension set used by the official release is:

```
openssl,simplexml,dom,phar,posix,mbstring,tokenizer,sockets,ctype,fileinfo,filter,pcre,xml,xmlwriter,xmlreader,libxml
```

Download sources for PHP 8.4 plus every extension in that list:

```bash
export SPC_EXTENSIONS="openssl,simplexml,dom,phar,posix,mbstring,tokenizer,sockets,ctype,fileinfo,filter,pcre,xml,xmlwriter,xmlreader,libxml"

./spc-src/bin/spc download \
  --with-php=8.4 \
  --for-extensions="$SPC_EXTENSIONS"
```

### 2.3 — Compile the `phpmicro` SAPI

```bash
./spc-src/bin/spc build "$SPC_EXTENSIONS" --build-micro
```

Output: `spc-src/buildroot/bin/micro.sfx` — a statically linked PHP interpreter with every extension baked in.

### 2.4 — Combine PHAR + micro SAPI

```bash
./spc-src/bin/spc micro:combine build/opcua-cli.phar --output=opcua-cli
chmod +x opcua-cli
./opcua-cli --version
```

Result: a single executable file, typically **20–25 MB**, with no external dependencies.

## Platform-specific notes

### Linux x86_64 (`ubuntu-latest` in CI)

Default target. Nothing special — follow the steps above.

### Linux aarch64 (ARM, Raspberry Pi, edge devices)

Two supported strategies, in decreasing order of reliability:

- **Native build** on an aarch64 machine — run the exact same commands as for x86_64. This is what the official release workflow does, on GitHub's `ubuntu-24.04-arm` runner. Tested on Raspberry Pi 4 / 5 and AWS Graviton.
- **Cross-compile** from x86_64:
  ```bash
  ./spc-src/bin/spc download --with-php=8.4 --for-extensions="$SPC_EXTENSIONS"
  ./spc-src/bin/spc build "$SPC_EXTENSIONS" --build-micro --arch=aarch64
  ```
  SPC will fetch a cross-toolchain on first use. Expect a longer first build (~40 minutes). Only choose this path if you do not have an aarch64 host available.

### macOS (Intel + Apple Silicon)

Use native machines whenever possible:

- **Apple Silicon (M1/M2/M3/M4)** → produces `macos-arm64`. This is the only macOS target the official release workflow builds, because GitHub retired the free `macos-13` runner pool during 2025.
- **Intel** → produces `macos-x86_64`. No longer built by CI; if you want this binary you must produce it yourself on an Intel Mac, or on a paid `macos-13-large` runner. The Apple Silicon binary **will not** run on Intel — Rosetta 2 only translates in the other direction.

The Homebrew Xcode command-line tools (`xcode-select --install`) satisfy all prerequisites. `spc doctor --auto-fix` handles the rest.

Code-signing and notarization are **not** covered by SPC. If you ship the binary to non-technical macOS users, sign it yourself with `codesign` + `notarytool`, otherwise it will be blocked by Gatekeeper on first launch.

### Linux musl / Alpine

SPC supports musl-libc targets for smaller container images (ideal for `FROM scratch` or `FROM alpine`):

```bash
./spc-src/bin/spc build "$SPC_EXTENSIONS" --build-micro --with-libc=musl
```

Expect the resulting binary to be marginally smaller (~15–18 MB vs. 20–25 MB) and to run unchanged on Alpine, minimal container images, and any glibc system.

### Windows x86_64

Experimental — but now wired into CI. The `windows-x86_64` leg of `.github/workflows/release-binaries.yml` runs with `continue-on-error: true`, so Windows `.exe` builds are attempted on every tag push but do not block the other four artefacts. When the leg succeeds, the `.exe` is attached to the release alongside the Linux and macOS binaries.

To reproduce locally: SPC requires a dedicated Windows toolchain (MSYS2 + mingw-w64). Follow [SPC's Windows build guide](https://github.com/crazywhalecc/static-php-cli/blob/main/docs/guide/windows-build.md) to set up the environment, then run the same build commands from an MSYS2 / Git Bash shell — the CI workflow uses `defaults.run.shell: bash` so the step definitions are identical across all five OS legs. The CLI itself runs fine on Windows: `posix_isatty` is already guarded by `function_exists()` in `src/Output/ConsoleOutput.php`, with a fall-through to the `TERM` env var.

Promotion to first-class (dropping `continue-on-error`, adding a dedicated smoke-test matrix leg) is tracked in [ROADMAP.md](../ROADMAP.md) for v4.4.0. Contributions to shake out any remaining MSYS2 / mingw-w64 issues are welcome.

## Customising the build

### Changing the bundled PHP version

Pass `--with-php=8.5` (or `8.3`, `8.2`) to the `download` step. The combine step uses whatever was built last.

### Trimming the extension set for a smaller binary

Every extension listed in `$SPC_EXTENSIONS` adds to the final binary size. The minimum set the CLI actually needs at runtime is:

```
openssl,simplexml,dom,phar,mbstring,ctype,pcre,xml,xmlwriter,libxml
```

`posix` is guarded by `function_exists()` in `src/Output/ConsoleOutput.php` and only affects TTY detection — safe to drop. `sockets` is a transitive pin and not used directly; `fileinfo`, `tokenizer`, `filter`, `xmlreader` are defensive inclusions for third-party composer packages. Dropping all of them trims the binary by ~3 MB.

Update `SPC_EXTENSIONS` before the `download` step and rebuild from scratch (cached sources are per-extension-set).

### Changing the PHAR contents

Edit `box.json.dist` in the project root. Box auto-discovers `src/` and `vendor/` from `composer.json`, so most tuning is done via:

- `"compactors"` — add/remove source minification passes
- `"files-bin"` / `"directories-bin"` — embed additional binary resources
- `"main"` — point at a different entry script

See the [Box configuration reference](https://github.com/box-project/box/blob/master/doc/configuration.md) for the full schema.

## Verification

After any build, verify the binary end-to-end:

```bash
./opcua-cli --version                              # prints "opcua-cli X.Y.Z"
./opcua-cli --help                                 # prints help text
./opcua-cli endpoints opc.tcp://localhost:4840     # against a live server
```

If you have Docker, the fastest way to reach a live server is:

```bash
docker run --rm -p 4840:4840 open62541/open62541-min
./opcua-cli endpoints opc.tcp://localhost:4840
```

For a full integration-test pass, use [`php-opcua/uanetstandard-test-suite`](https://github.com/php-opcua/uanetstandard-test-suite) which ships a docker-compose stack of UA-.NETStandard test servers.

## Troubleshooting

### `Failed to fetch "https://api.github.com/repos/..."` / HTTP 403

SPC queries the GitHub REST API to resolve the latest release/tag of each upstream library (zlib, libxml2, openssl, …). Unauthenticated, `api.github.com` allows ~60 requests/hour per IP — a single SPC `download` step can exceed that, especially on shared runner pools. Symptom on the `download sources` step:

```
curl: (56) The requested URL returned error: 403
Download failed: Failed to fetch "https://api.github.com/repos/madler/zlib/releases"
```

Fix: export a personal access token (PAT) with `public_repo` scope and re-run:

```bash
export GITHUB_TOKEN=ghp_yourTokenHere
./spc-src/bin/spc download --with-php=8.4 --for-extensions="$SPC_EXTENSIONS"
```

`gh auth token` prints a PAT for the currently logged-in GitHub CLI session. Inside GitHub Actions, `secrets.GITHUB_TOKEN` is always available — the official workflow passes it as a job-level env var, and that is why CI builds are not affected.

### `openssl.cnf` not found

SPC embeds a minimal OpenSSL config inside the binary, but some installations still look for `/etc/ssl/openssl.cnf` or the `OPENSSL_CONF` env var. If `--security-policy=Basic256Sha256` or ECC commands fail with `unable to load config`, export `OPENSSL_CONF=/dev/null` before invoking the binary.

### `ECC_brainpoolP256r1` returns `no matching curve`

Some distros strip Brainpool curves from their system OpenSSL. The static build shipped by SPC includes them, so this should not affect standalone binaries — but if you built with `--with-shared-openssl` to reuse the system library, you may hit this. Rebuild without that flag.

### `Killed (SIGKILL)` during SPC build

Usually means the build host ran out of memory. Reduce parallelism (`MAKEFLAGS='-j2'`) before running `spc build`, or provide more RAM / swap.

### `undefined reference` / linker errors

Almost always a `spc doctor` miss. Run `./spc-src/bin/spc doctor --auto-fix` again and, if it reports no problem, install the SPC dependency for your distro manually (for example `apk add build-base libtool automake autoconf` on Alpine).

### Binary runs but `generate:nodeset` fails with `class ... not found`

The PHAR was built with `composer install --dev` installed. Rebuild with `--no-dev --classmap-authoritative` — Box uses the live autoloader, and dev-only packages shadow production class paths.

## Distribution

Producing the binary is only half of the job. For public distribution, also:

- Generate a SHA-256 checksum: `sha256sum opcua-cli > opcua-cli.sha256`
- Optionally sign with GPG: `gpg --detach-sign --armor opcua-cli`
- Compress for bandwidth: `gzip -9 < opcua-cli > opcua-cli.gz`
- Ship as a GitHub Release asset, a `Dockerfile FROM scratch COPY opcua-cli /`, or a system package (deb/rpm) with your distribution's tooling.

The official CI workflow (`.github/workflows/release-binaries.yml`) does exactly this for `linux-x86_64` on every `v*` tag push — use it as a reference when automating your own builds.
