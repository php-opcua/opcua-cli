# Roadmap

> **Versioning:** This package follows the same version numbering as [`php-opcua/opcua-client`](https://github.com/php-opcua/opcua-client). Each CLI release is aligned with the corresponding client library release.

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

---

## Won't Do (by design)

### Interactive / REPL Mode

The CLI is designed for single-shot commands that are composable with Unix pipes (`| jq`, `| grep`, `> file`). An interactive shell would require a different UX paradigm (tab completion, state management, history) and is better served by dedicated tools. Use `watch` for continuous monitoring.

### GUI / TUI

A terminal UI (ncurses, Textual, etc.) is out of scope. This package is a command-line tool, not a terminal application. Graphical OPC UA clients already exist (UaExpert, Prosys Browser, etc.).

### Server-Side Features

This is a client-side CLI tool. Server features (hosting an address space, handling incoming connections) belong in a separate package.

---

Have a suggestion? Open an [issue](https://github.com/php-opcua/opcua-cli/issues) or check the [contributing guide](CONTRIBUTING.md).
