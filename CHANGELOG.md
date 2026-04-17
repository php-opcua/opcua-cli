# Changelog

## [4.2.0] - 2026-04-17

### Changed

- Bumped `php-opcua/opcua-client` dependency from `^4.1` to `^4.2.0`. The CLI is aligned with the `opcua-client` v4.2.0 release which introduces the Kernel + ServiceModule architecture (internal refactor; public API unchanged), `ClientBuilder::addModule()` / `replaceModule()`, and the new server BuildInfo convenience methods on `OpcUaClientInterface`. No CLI source change was required: all commands consume `ClientBuilder`, `OpcUaClientInterface`, and the `Types\` DTOs, none of which had breaking changes.
- Fixed `Application::VERSION` — was frozen at `1.0.0` since the v4.0.0 extraction from `opcua-client`. `opcua-cli --version` now reports the actual package version (`4.2.0`) and will stay aligned with the `opcua-client` release it bundles, per the versioning note at the top of `ROADMAP.md`.

### Fixed

- **`watch` (polling and subscription) and any read/write against NodeIds whose string identifier contains `/`.** The previous `opcua-client` v4.2.0 shipped with an overly permissive heuristic in `Client::resolveNodeId()` that routed every `/`-bearing string through `TranslateBrowsePathModule`, so real NodeIds such as `ns=1;s=TestServer/Dynamic/Counter` (routinely exposed by UA-.NETStandard-based servers) failed with `ServiceException: 0x806F0000 (BadNotFound)`. Fixed upstream in `opcua-client` v4.2.0; the CLI picks up the fix via the `^4.2.0` constraint. Two integration tests in `tests/Integration/CliTest.php` (`watches Counter node with polling mode` and `writes a value and watch CLI detects it via polling`) regained green status with no code change on the CLI side.

## [4.1.0] - 2026-04-13

### Added

- **ECC security policy support.** All 10 CLI commands now work transparently with the 4 new Elliptic Curve Cryptography policies introduced in `opcua-client` v4.1.0:
  - `--security-policy=ECC_nistP256` (NIST P-256, AES-128-CBC, SHA-256)
  - `--security-policy=ECC_nistP384` (NIST P-384, AES-256-CBC, SHA-384)
  - `--security-policy=ECC_brainpoolP256r1` (Brainpool P-256, AES-128-CBC, SHA-256)
  - `--security-policy=ECC_brainpoolP384r1` (Brainpool P-384, AES-256-CBC, SHA-384)
  - No `--cert`/`--key` required — ECC certificates are auto-generated when omitted.
  - Username/password authentication uses the `EccEncryptedSecret` protocol automatically.
  - **ECC disclaimer:** No commercial OPC UA vendor supports ECC endpoints yet. This implementation is tested exclusively against the OPC Foundation's UA-.NETStandard reference stack.
- **12 new ECC integration tests** against the `uanetstandard-test-suite` ECC servers:
  - 6 NIST ECC tests (port 4848): browse and read with P-256 Sign, P-256 SignAndEncrypt (anonymous + admin), P-384 SignAndEncrypt (anonymous + admin), P-384 Sign.
  - 6 Brainpool ECC tests (port 4849): browse and read with brainpoolP256r1 Sign, brainpoolP256r1 SignAndEncrypt (anonymous + admin), brainpoolP384r1 SignAndEncrypt (anonymous + admin), brainpoolP384r1 Sign.
- **4 new unit tests** for ECC security policy resolution in `CommandRunner` (short names and full URIs for all 4 ECC policies).

### Changed

- Bumped minimum `php-opcua/opcua-client` dependency from `^4.0` to `^4.1`.
- Security support expanded from 6 to **10 policies** (6 RSA + 4 ECC).
- Updated documentation (README, doc/, llms.txt, llms-full.txt, llms-skills.md) to reflect ECC support, add ECC examples, and include the ECC disclaimer.
- Updated CI test server suite from `php-opcua/uanetstandard-test-suite@v1.0.0` to `@v1.1.0`.

## [4.0.2] - 2026-04-07

### Added

- **AI-Ready documentation.** Added `llms-skills.md` with 11 task-oriented recipes for AI coding assistants (browse, read, write, watch, security, trust management, code generation, address space export, JSON scripting, endpoint discovery, global options). Designed to be fed to Claude, Cursor, Copilot, ChatGPT, and other AI tools so they can generate correct CLI commands from a user's intent.
- Added AI-Ready section to README with instructions for integrating with Claude Code, Cursor, GitHub Copilot, and other AI tools.

## [4.0.1] - 2026-04-02

### Changed

- **Migrated test infrastructure from `opcua-test-suite` to [`uanetstandard-test-suite`](https://github.com/php-opcua/uanetstandard-test-suite).** Integration tests now run against the OPC Foundation's UA-.NETStandard reference implementation instead of node-opcua.
- Updated GitHub Actions workflow to use `php-opcua/uanetstandard-test-suite@v1.0.0`.

### Fixed

- Fixed `trust` CLI integration test — the no-security server (.NET) correctly does not expose a certificate with `SecurityPolicy=None`. Test now uses the all-security server (port 4843).

## [4.0.0] - 2026-03-29

### Added

- Extracted CLI tool from [php-opcua/opcua-client](https://github.com/php-opcua/opcua-client) into a standalone package.
- **10 commands:** `browse`, `read`, `write`, `endpoints`, `watch`, `generate:nodeset`, `dump:nodeset`, `trust`, `trust:list`, `trust:remove`.
- Full security support (6 RSA policies, 3 auth modes), JSON output, debug logging.
- NodeSet2.xml code generator: typed DTOs, PHP enums, binary codecs, registrar with dependency resolution.
- Server address space dump to NodeSet2.xml.
- Server certificate trust management from the terminal.
- **272 tests** (253 unit + 19 integration), 592 assertions, **99.9% code coverage**.
