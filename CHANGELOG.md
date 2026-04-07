# Changelog

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
- Full security support (6 policies, 3 auth modes), JSON output, debug logging.
- NodeSet2.xml code generator: typed DTOs, PHP enums, binary codecs, registrar with dependency resolution.
- Server address space dump to NodeSet2.xml.
- Server certificate trust management from the terminal.
- **272 tests** (253 unit + 19 integration), 592 assertions, **99.9% code coverage**.
