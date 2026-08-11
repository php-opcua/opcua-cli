# Changelog

## [4.5.0] - 2026-08-11

Lock-step release with `php-opcua/opcua-client` v4.5.0, its security-hardening release. One CLI command needed migrating — `watch`, for the typed subscription notifications. Separately, this release fixes three defects in `generate:nodeset`'s output that were found while verifying the bump on Linux.

### Changed

- Bumped `php-opcua/opcua-client` from `^4.4.0` to `^4.5.0`.
- Bumped `Application::VERSION` to `4.5.0`. `opcua-cli --version` now reports `4.5.0`.
- **`watch` migrated to the typed notifications.** The core's `PublishResult::$notifications` now holds `DataChangeNotification` / `EventNotification` objects instead of `['type' => 'DataChange', …]` arrays, so `WatchCommand` matches with `instanceof DataChangeNotification` and reads `$notif->dataValue` instead of array offsets. Without the change the command silently printed nothing: the array offset on an object never matched. Output format is unchanged.

### Fixed — `generate:nodeset` output

All three are pre-existing, and all three produced code that fails only on a case-sensitive filesystem or at the point where PHP resolves a relative class name. They are the source of the corresponding fixes in `php-opcua/opcua-client-nodeset` v4.5.0.

- **DTOs with an enum field were unconstructible, and their codecs could not decode.** Codecs are generated into `<Namespace>\Codecs` and DTOs into `<Namespace>\Types`, while both address enums relatively as `Enums\Foo` — which PHP resolves against the *current* namespace, i.e. `<Namespace>\Codecs\Enums` and `<Namespace>\Types\Enums`. Neither exists. In a codec that raised `Error: Class not found` on decode; in a DTO the declared property type resolved to a non-existent class, so **no value could satisfy it** and construction always threw `TypeError`. `CodeGenerator::generateDtoClass()` and `generateCodecClass()` now emit `use <Namespace>\Enums;` whenever a generated enum is referenced, and omit it otherwise.
- **Dependency registrar names were guessed from the model URI.** `GenerateNodesetCommand` derived the dependency class from the directory name (`http://opcfoundation.org/UA/DI/` → `DI` → `DIRegistrar`) while the registrar is actually named after the NodeSet2 file (`Opc.Ua.Di.NodeSet2.xml` → `DiRegistrar`). Any spec whose file casing differs from its URI got an unloadable reference. The new `resolveDependencyRegistrar()` takes the name from the registrar file that exists next to the output directory: exact match first, then case-insensitive, then — for a spec split across several nodesets, such as AML → `AMLBaseTypes` + `AMLLibraries` — the single registrar whose name extends the referenced one. When the dependency has not been generated yet it keeps the directory-derived guess, leaving it to the consumer's cleanup pass.

  Resolution reads the names `glob()` reports and compares them case-sensitively, rather than probing `is_file()` with the guessed name. On a case-insensitive filesystem (Windows, default macOS) the probe confirms `DIRegistrar.php` while the file — and therefore the class — is `DiRegistrar`, so generating on Windows would emit a reference that fails to autoload on Linux. The generated output is now identical on every platform.

### Tests

- New `tests/Unit/GeneratedEnumResolutionTest.php` (5 tests): the `Enums` import is emitted for DTOs and codecs that need it and omitted for those that don't, plus an end-to-end case that generates an enum, a DTO and a codec, loads them, and round-trips a value through `BinaryEncoder` / `BinaryDecoder`. That last test reproduces the exact `TypeError` when the import is removed.
- New `tests/Unit/DependencyRegistrarResolutionTest.php` (5 tests): exact match, exact-over-case-insensitive precedence, split-spec resolution, the not-yet-generated fallback, and the ambiguous-candidates fallback. The exact-match test also asserts that the emitted class name matches a file on disk byte-for-byte, which is what fails when resolution goes through `is_file()` on a case-insensitive filesystem. The precedence test needs two names differing only in case, so it skips where the filesystem cannot hold both.
- `tests/Integration/CliTest.php` migrated to the typed notifications alongside `WatchCommand`.

### Compatibility

- **No CLI interface change.** Same commands, same flags, same output formats.
- Applications embedding `CodeGenerator` directly will see the extra `use` line in generated DTOs and codecs that reference enums. Previously generated code keeps working; regenerating is what picks up the fix.

## [4.4.0] - 2026-05-28

Lock-step release with `php-opcua/opcua-client` v4.4.0. The CLI consumes the core's `OpcUaClientInterface` plus `ClientBuilder` / `Types\*` surface, all of which is additive in v4.4 — every command keeps working as-is. New core capabilities (`AggregateModule`, `HistoryUpdate`, `FileTransferModule`, the pluggable `ClientTransportInterface`) are reachable via the underlying client; surfacing them as dedicated CLI sub-commands is roadmap (see `ROADMAP.md`).

### Changed

- Bumped `php-opcua/opcua-client` from `^4.3.0` to `^4.4.0`.
- Bumped `Application::VERSION` to `4.4.0`. `opcua-cli --version` now reports `4.4.0`.
- Bumped CI test-server suite from `uanetstandard-test-suite@v1.2.0` to `@v1.5.0` (adds the HTTPS Binary server on `:4852`, the Security Key Service on `:4851`, ECC NIST / Brainpool servers on `:4848` / `:4849`, and the open62541-backed `historizing` server on `:24842` that the new HistoryUpdate integration tests target).
- `composer.json` `support.docs` now points at the canonical docs site (`https://www.php-opcua.com/documentation/opcua-cli`) instead of the GitHub `tree/master/doc` URL.

### Compatibility

- No CLI source change was required: every command consumes `ClientBuilder`, `OpcUaClientInterface`, and the shared `Types\*` DTOs, none of which had breaking changes in v4.4.
- `tests/Integration/` benefits transparently from the bumped test-suite (all 12 servers available; existing integration tests still run against the same `opcua-no-security` baseline server on `:4840`).

## [4.3.0] - 2026-04-24

### Security

- Fixed PHP code injection in `generate:nodeset` via unescaped `NodeId` / `encodingId` / `RequiredModel.ModelUri` (`src/CodeGenerator.php`, `src/Commands/GenerateNodesetCommand.php`).
- Fixed path traversal in `generate:nodeset` via unsanitized enum `Name` attribute.
- Hardened XML parsing with `LIBXML_NONET` in `NodeSetParser`.
- 10 regression tests added in `tests/Unit/SecurityTest.php` with 5 malicious fixture XMLs.

### Changed

- Bumped `php-opcua/opcua-client` from `^4.2.0` to `^4.3.0`.
- Bumped `Application::VERSION` to `4.3.0`.
- Bumped CI test-server suite from `uanetstandard-test-suite@v1.1.0` to `@v1.2.0`.
- Clearer error message for failed `--debug-file` open and malformed NodeSet XML, via a new `\RuntimeException` handler in `Application::run()`.

### Added

- Integration-test readiness probe (`tests/Integration/Helpers/Readiness.php`) to fix flaky first-test-after-container-boot on PHP 8.3 / 8.5 runners.
- Standalone binary releases for `linux-x86_64`, `linux-aarch64`, `macos-arm64`, and `windows-x86_64` (experimental), produced on tag push by `.github/workflows/release-binaries.yml` via `static-php-cli` + Box. See [README](README.md) and [`doc/04-build-from-source.md`](doc/04-build-from-source.md).
- New `explore` command — interactive TUI browser of the server address space (tree + details + log panes), built on `php-tui/php-tui`. Linux/macOS only; Windows prints a clear "not yet supported" error (upstream `php-tui` does not yet support Windows).

## [4.2.0] - 2026-04-17

### Changed

- Bumped `php-opcua/opcua-client` dependency from `^4.1` to `^4.2.0`. The CLI is aligned with the `opcua-client` v4.2.0 release which introduces the Kernel + ServiceModule architecture (internal refactor; public API unchanged), `ClientBuilder::addModule()` / `replaceModule()`, and the new server BuildInfo convenience methods on `OpcUaClientInterface`. No CLI source change was required: all commands consume `ClientBuilder`, `OpcUaClientInterface`, and the `Types\` DTOs, none of which had breaking changes.
- Fixed `Application::VERSION` — was frozen at `1.0.0` since the v4.0.0 extraction from `opcua-client`. `opcua-cli --version` now reports the actual package version (`4.2.0`) and will stay aligned with the `opcua-client` release it bundles, per the versioning note at the top of `ROADMAP.md`.
- **CI workflow aligned with `opcua-client`.** `.github/workflows/tests.yml` now splits `unit` and `integration` jobs: unit tests run cross-OS on `ubuntu-latest`, `macos-latest`, and `windows-latest` × PHP 8.2–8.5 (12 combinations, 258 tests each), integration tests run Ubuntu-only against `php-opcua/uanetstandard-test-suite@v1.1.0` with `needs: unit` gating × PHP 8.2–8.5 (4 combinations). `[DOC]`-prefixed commits skip CI on both jobs. Code-style check (`composer format:check`) runs once on Ubuntu/PHP 8.5 instead of every matrix slot. Triggers expanded from `[master]` to `[main, master]`. `codecov/codecov-action` bumped from `v5` to `v6` to silence Node.js 20 deprecation warnings on GitHub Actions runners.

### Fixed

- **`watch` (polling and subscription) and any read/write against NodeIds whose string identifier contains `/`.** The previous `opcua-client` v4.2.0 shipped with an overly permissive heuristic in `Client::resolveNodeId()` that routed every `/`-bearing string through `TranslateBrowsePathModule`, so real NodeIds such as `ns=1;s=TestServer/Dynamic/Counter` (routinely exposed by UA-.NETStandard-based servers) failed with `ServiceException: 0x806F0000 (BadNotFound)`. Fixed upstream in `opcua-client` v4.2.0; the CLI picks up the fix via the `^4.2.0` constraint. Two integration tests in `tests/Integration/CliTest.php` (`watches Counter node with polling mode` and `writes a value and watch CLI detects it via polling`) regained green status with no code change on the CLI side.
- **Windows compatibility for the output classes.** `ConsoleOutput::writeln()` / `error()`, every `JsonOutput` writer, and `StreamLogger::log()` now emit a literal `"\n"` line separator instead of `PHP_EOL`. On Windows `PHP_EOL` expands to `"\r\n"`, which broke every byte-exact assertion on CLI output (`"Hello\n"` vs `"Hello\r\n"` — "Strings contain different line endings") and produced `\r\n`-terminated lines in piped/redirected output that downstream tools (`jq`, `grep`, JSON NDJSON parsers, shell redirection into files) would not handle cleanly. Converging on `\n` also matches the convention of every other mainstream CLI (`git`, `node`, `python`, Unix coreutils) on Windows, where the Console subsystem renders `\n` correctly without needing CRLF at the source. `tests/Unit/OutputTest.php` also opens scratch streams in binary mode (`'w+b'`) so that Windows text-mode `fopen()` does not silently re-introduce the `\n` → `\r\n` translation on the round-trip through the temp file. Only the dedicated non-memory fallback test (`it falls back to TERM env when posix_isatty not available on non-memory stream`) still uses default text mode because it never reads back its contents.

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
