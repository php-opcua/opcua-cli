# Roadmap

> **Versioning:** This package follows the same version numbering as [`php-opcua/opcua-client`](https://github.com/php-opcua/opcua-client). Each CLI release is aligned with the corresponding client library release.

## v4.3.0 — 2026-04-23

- [x] Bumped `php-opcua/opcua-client` from `^4.2.0` to `^4.3.0` and `Application::VERSION` to `4.3.0`.
- [x] Bumped CI test-server suite from `uanetstandard-test-suite@v1.1.0` to `@v1.2.0`.
- [x] Fixed PHP code injection + path traversal in `generate:nodeset`; added 10 regression tests.
- [x] Fixed flaky integration tests on PHP 8.3 / 8.5 with a TCP readiness probe.
- [x] Standalone binary releases (`linux-x86_64`, `linux-aarch64`, `macos-arm64`, `windows-x86_64` experimental) via `static-php-cli` + Box, wired in `.github/workflows/release-binaries.yml`.
- [x] New `explore` command — interactive TUI browser of the server address space, built on `php-tui/php-tui`. Linux/macOS only in v4.3.0 (Windows prints a clear "not yet supported" error; upstream `php-tui` itself does not yet support Windows).

## v4.2.0 — 2026-04-17

- [x] Bumped `php-opcua/opcua-client` dependency from `^4.1` to `^4.2.0`
- [x] Aligned with the Kernel + ServiceModule architecture shipped in `opcua-client` v4.2.0 — no CLI source change was required, the public API (`ClientBuilder`, `OpcUaClientInterface`, `Types\*`) is backward compatible.
- [x] Fixed `Application::VERSION` (was stuck at `1.0.0` since the v4.0.0 extraction from `opcua-client`) — `opcua-cli --version` now reports `4.2.0` and will stay in sync with the bundled client release.
- [x] Verified the `Client::resolveNodeId()` regression discovered during the v4.2.0 bump is fixed upstream: NodeId strings whose identifier contains `/` (e.g. `ns=1;s=TestServer/Dynamic/Counter` on UA-.NETStandard-based servers) are no longer misrouted to the browse-path resolver. Two `watch` integration tests regain green status with no CLI-side change.
- [x] CI workflow aligned with `opcua-client`: `unit` / `integration` jobs split, unit matrix extended to `ubuntu-latest` / `macos-latest` / `windows-latest` × PHP 8.2–8.5, integration gated by `needs: unit`, `[DOC]` commits skip CI, `codecov-action` bumped to `v6`, triggers on `[main, master]`.

## v4.1.0 — 2026-04-13

- [x] **ECC security policy support** — all 10 CLI commands work with `ECC_nistP256`, `ECC_nistP384`, `ECC_brainpoolP256r1`, `ECC_brainpoolP384r1` (auto-generated ECC certificates, EccEncryptedSecret for username/password)
- [x] Bumped `php-opcua/opcua-client` dependency from `^4.0` to `^4.1`
- [x] Security support expanded from 6 to **10 policies** (6 RSA + 4 ECC)
- [x] **12 new ECC integration tests** against `uanetstandard-test-suite` ECC servers (ports 4848, 4849)
- [x] **4 new unit tests** for ECC security policy resolution in `CommandRunner`
- [x] Updated all documentation (README, doc/, llms.txt, llms-full.txt, llms-skills.md)

## v4.0.2 — 2026-04-07

- [x] **AI-Ready documentation** — added `llms-skills.md` with 11 task-oriented recipes for AI coding assistants
- [x] Added AI-Ready section to README

## v4.0.1 — 2026-04-02

- [x] **Migrated test infrastructure to [uanetstandard-test-suite](https://github.com/php-opcua/uanetstandard-test-suite)** — integration tests now run against the OPC Foundation's UA-.NETStandard reference implementation instead of node-opcua
- [x] Updated GitHub Actions workflow to `php-opcua/uanetstandard-test-suite@v1.0.0`
- [x] Fixed `trust` CLI integration test — the no-security server (.NET) correctly does not expose a certificate with `SecurityPolicy=None`, test now uses the all-security server (port 4843)

## v4.0.0 — 2026-03-29

- [x] Extracted CLI tool from [`php-opcua/opcua-client`](https://github.com/php-opcua/opcua-client) into a standalone package
- [x] **10 commands:** `browse`, `read`, `write`, `endpoints`, `watch`, `generate:nodeset`, `dump:nodeset`, `trust`, `trust:list`, `trust:remove`
- [x] Full security support (6 RSA policies, 3 auth modes)
- [x] JSON output mode (`--json`) for all commands
- [x] Debug logging (stdout, stderr, file)
- [x] NodeSet2.xml code generator: typed DTOs, PHP enums, binary codecs, registrar
- [x] Server address space dump to NodeSet2.xml
- [x] Server certificate trust management from the terminal
- [x] **272 tests** (253 unit + 19 integration), 99.9% code coverage

## Planned

### New Commands

- [ ] `call` — Call a method on a server node
- [ ] `history:read` — Read historical values for a node
- [ ] `subscribe` — Subscribe to data changes and print events as they arrive
- [ ] `info` — Show server info (ServerStatus, BuildInfo, ServerCapabilities)

### Enhancements

- [ ] `browse` — Filter by node class (`--node-class=Variable`)
- [ ] `browse` — Search by name pattern (`--filter=Temp*`)
- [ ] `read` — Multi-node read (`opcua-cli read <endpoint> "i=2259" "ns=2;i=1001"`)
- [ ] `write` — Multi-node write from JSON file (`--from-json=values.json`)
- [ ] `watch` — Watch multiple nodes in a single subscription
- [ ] `watch` — Output as CSV (`--csv`) for logging to file
- [ ] `generate:nodeset` — Multiple input files in a single invocation
- [ ] `dump:nodeset` — Filter by node class or reference type
- [ ] Shell completion (Bash, Zsh, Fish)
- [ ] Global configuration file (`~/.opcua-cli.yaml`) for default endpoint, credentials, and trust store
- [ ] **macOS code-signing & notarization** — the v4.3.0 macOS binary is unsigned; users must run `xattr -cr <binary>` to clear the Gatekeeper quarantine on first launch. Proper distribution needs an Apple Developer ID, `codesign --timestamp --options=runtime`, and `xcrun notarytool submit --wait`. Requires the signing identity and an app-specific password stored as GitHub secrets. Target: v4.4.0.
- [ ] **Re-introduce `macos-x86_64` target, if feasible** — dropped from v4.3.0 because GitHub retired the free `macos-13` runner pool during 2025. Options: (a) budget a paid `macos-13-large` runner, (b) cross-compile from `macos-latest` via `spc build … --arch=x86_64` (unclear whether SPC Apple-Silicon cross-to-Intel tooling is mature), (c) accept that Intel Mac is a composer/source-build target only and remove the TODO. Target: v4.4.0, pending decision.
- [ ] **Windows binary promotion to first-class support** — v4.3.0 ships `windows-x86_64.exe` as an **experimental** leg (`continue-on-error: true`), so a broken Windows build does not block the rest of the release. Promoting it means: (a) drop the `continue-on-error` flag in `.github/workflows/release-binaries.yml`, (b) ship the live OPC UA smoke test on Windows too — the `uanetstandard-test-suite` composite action is docker-only (Linux-only), so this requires either a cross-runner artifact download/execute pattern (build on windows, smoke-test on ubuntu via artifact upload) or standing up the UA-.NETStandard server natively on windows-latest via a `services:` block, (c) document any MSYS2 / mingw-w64 toolchain quirks discovered along the way. Target: v4.4.0.
- [ ] **Live server-driven smoke test for the compiled binaries** — today only `--version` and `--help` are exercised. A full live smoke (`endpoints` / `browse` / `read` / `--json` against `uanetstandard-test-suite`) requires docker on each runner: trivial on Linux, needs `docker/setup-docker-action@v4` + colima on macOS (2-3 min VM boot, occasional flake), and needs a Linux-containers switch on Windows (the composite action assumes a Linux host). Cleanest path forward is a dedicated `smoke` job that downloads only the `linux-x86_64` binary artefact and runs it against the composite action on `ubuntu-latest` — single OS, minimal moving parts, still catches every SPC-recipe regression that matters. Target: v4.4.0.

---

## Won't Do (by design)

### Persistent REPL shell

Single-shot commands remain the primary UX — they compose with Unix pipes (`| jq`, `| grep`, `> file`) and are scriptable. A persistent REPL shell with its own sub-command language, tab completion, and session history is out of scope. One-off interactive commands like `explore` (single invocation, single TUI, single disconnect) are an acceptable exception when they fit the pipe-vs-interactive boundary cleanly.

### Server-Side Features

This is a client-side CLI tool. Server features (hosting an address space, handling incoming connections) belong in a separate package.

---

Have a suggestion? Open an [issue](https://github.com/php-opcua/opcua-cli/issues) or check the [contributing guide](CONTRIBUTING.md).
