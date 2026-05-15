---
eyebrow: 'Docs · Building'
lede:    'PHAR and static-binary build failures cluster around five recurring causes. Each one has a known fix.'

see_also:
  - { href: './phar.md',           meta: '6 min' }
  - { href: './static-binary.md',  meta: '7 min' }
  - { href: 'https://github.com/php-opcua/opcua-cli/issues', meta: 'external', label: 'Issues' }

prev: { label: 'Static binary',  href: './static-binary.md' }
next: { label: 'Global options', href: '../reference/global-options.md' }
---

# Troubleshooting

Build failures fall into five buckets. This page walks each
through, with the canonical fix.

## 1. `phar.readonly` blocks PHAR write

<!-- @code-block language="text" label="symptom" -->
```text
PharException: Cannot write out phar archive, phar is read-only
```
<!-- @endcode-block -->

PHP defaults `phar.readonly` to `1`. Box needs to write the
PHAR; the setting must be disabled for the compile.

**Fix:**

<!-- @code-block language="bash" label="terminal" -->
```bash
php -d phar.readonly=0 box.phar compile -v
```
<!-- @endcode-block -->

The setting is per-invocation — the produced PHAR runs fine
under the default read-only mode.

## 2. Composer install pulls dev dependencies into the PHAR

<!-- @code-block language="text" label="symptom" -->
```text
# PHAR is unexpectedly large (5 MB+ instead of ~650 KB)
```
<!-- @endcode-block -->

A previous `composer install` (without `--no-dev`) left dev
packages in `vendor/` — Pest, PHPStan, php-cs-fixer. Box packs
whatever it finds in vendor.

**Fix:**

<!-- @code-block language="bash" label="terminal — clean install" -->
```bash
rm -rf vendor
composer install --no-dev --prefer-dist --classmap-authoritative
php -d phar.readonly=0 box.phar compile
```
<!-- @endcode-block -->

Always `rm -rf vendor` before the PHAR build if you've been
running tests.

## 3. SPC `doctor` reports missing native tools

<!-- @code-block language="text" label="symptom" -->
```text
[!] missing: autoconf
[!] missing: bison
[!] missing: re2c
```
<!-- @endcode-block -->

SPC compiles PHP + OpenSSL + libxml2 from source — it needs the
full C build toolchain.

**Fix on Debian / Ubuntu:**

<!-- @code-block language="bash" label="terminal — apt" -->
```bash
sudo apt-get install -y \
    build-essential autoconf bison re2c libtool pkg-config \
    libxml2-dev libssl-dev zlib1g-dev libicu-dev libonig-dev \
    git curl wget
```
<!-- @endcode-block -->

**Fix on Fedora / RHEL:**

<!-- @code-block language="bash" label="terminal — dnf" -->
```bash
sudo dnf install -y \
    @development-tools autoconf bison re2c libtool pkgconfig \
    libxml2-devel openssl-devel zlib-devel libicu-devel oniguruma-devel
```
<!-- @endcode-block -->

**Fix on Alpine:**

<!-- @code-block language="bash" label="terminal — apk" -->
```bash
apk add --no-cache \
    build-base autoconf bison re2c libtool pkgconfig \
    libxml2-dev openssl-dev zlib-dev icu-dev oniguruma-dev \
    git curl
```
<!-- @endcode-block -->

After that:

<!-- @code-block language="bash" label="terminal" -->
```bash
./spc-src/bin/spc doctor --auto-fix
```
<!-- @endcode-block -->

SPC re-checks and reports remaining issues.

## 4. SPC `download` hits GitHub API rate limit

<!-- @code-block language="text" label="symptom" -->
```text
Failed to fetch "https://api.github.com/repos/php/php-src/tags": HTTP 403
```
<!-- @endcode-block -->

GitHub's unauthenticated API rate limit is 60 requests per
hour. The first SPC download bursts through that easily.

**Fix:**

<!-- @code-block language="bash" label="terminal — token" -->
```bash
export GITHUB_TOKEN=ghp_yourPersonalAccessTokenHere
./spc-src/bin/spc download --with-php=8.4 --for-extensions="$SPC_EXTENSIONS"
```
<!-- @endcode-block -->

Even a token with no scopes works — it just lifts the rate limit
to 5000/hour. Generate one at
[github.com/settings/tokens](https://github.com/settings/tokens).

## 5. Out of memory during SPC build

<!-- @code-block language="text" label="symptom" -->
```text
g++: internal compiler error: Killed (program cc1plus)
```
<!-- @endcode-block -->

Common on 1 GB / 2 GB VMs. The PHP compile step is memory-
hungry — especially with `-j` parallelism.

**Fix — limit parallelism:**

<!-- @code-block language="bash" label="terminal — single-threaded build" -->
```bash
./spc-src/bin/spc build "$SPC_EXTENSIONS" --build-micro -j 1
```
<!-- @endcode-block -->

`-j 1` does a single-threaded build — slower but uses less RAM.
Try `-j 2` if `-j 1` succeeds.

**Or — bigger host.** 4 GB RAM + 4 CPUs is a comfortable build
configuration.

## Specific extension failures

### `openssl` fails to build

OpenSSL headers missing. Install `libssl-dev` (Debian) / `openssl-devel`
(Fedora) / `openssl-dev` (Alpine).

### `simplexml` / `dom` / `xml*` fail

libxml2 headers missing. Install `libxml2-dev` (Debian) /
`libxml2-devel` (Fedora) / `libxml2-dev` (Alpine).

### `mbstring` fails

Oniguruma headers missing. Install `libonig-dev` (Debian) /
`oniguruma-devel` (Fedora) / `oniguruma-dev` (Alpine).

These three cover ~90% of "extension X fails to build" cases.

## Resulting binary fails to run

### "missing shared object"

<!-- @code-block language="text" label="symptom" -->
```text
./opcua-cli: error while loading shared libraries: libssl.so.1.1: cannot open shared object file
```
<!-- @endcode-block -->

The build was not fully static — some library linked
dynamically. Verify with:

<!-- @code-block language="bash" label="terminal — ldd" -->
```bash
ldd ./opcua-cli
# Should print "not a dynamic executable"
```
<!-- @endcode-block -->

If `ldd` reports dependencies, rebuild — SPC's static linking
went wrong. The common cause is a missing static-lib `.a` for
one of the dependencies; check the SPC build log.

### "PHP version mismatch"

The bundled PHP differs from what was expected. SPC's `combine`
step uses whatever PHP was built last; if you built 8.4 then 8.3,
the combine uses 8.3.

**Fix — start fresh:**

<!-- @code-block language="bash" label="terminal — clean build" -->
```bash
rm -rf spc-src/buildroot spc-src/source spc-src/downloads
./spc-src/bin/spc download --with-php=8.4 --for-extensions="$SPC_EXTENSIONS"
./spc-src/bin/spc build "$SPC_EXTENSIONS" --build-micro
./spc-src/bin/spc micro:combine build/opcua-cli.phar --output=opcua-cli
```
<!-- @endcode-block -->

## Windows-specific issues

### MSYS2 not in PATH

The Windows build uses MSYS2's bash. Open the "MSYS2 MinGW 64-bit"
shell from the Start menu rather than `cmd.exe` or PowerShell.

### Long paths

Windows' 260-character path limit catches the deeply-nested
SPC build directories. Enable long-path support
(`fsutil behavior set DisableLongPaths 0` as Administrator,
plus a registry tweak — see Microsoft docs).

### Antivirus quarantines the binary

Real-time scanners sometimes flag unsigned `.exe` files. Whitelist
the build directory or sign the binary.

## When to escalate

If you've tried the fixes above and the build still fails:

1. **Check the issue tracker.** Many of these failures have
   been reported; the issue may already have an answer:
   [github.com/php-opcua/opcua-cli/issues](https://github.com/php-opcua/opcua-cli/issues)
2. **Check SPC's issues** for SPC-specific build problems:
   [github.com/crazywhalecc/static-php-cli/issues](https://github.com/crazywhalecc/static-php-cli/issues)
3. **Open a new issue** with:
   - Full output of `./spc-src/bin/spc doctor --auto-fix`
   - The failing command and its stderr
   - Host OS / arch / PHP version
   - The version of `opcua-cli` you're building (`git rev-parse HEAD`)

Most build failures have a known cause within the five buckets
above. The escalation path is for the genuine novel ones.
