# Changelog

## [4.0.0] - 2026-03-29

### Added

- Extracted CLI tool from [php-opcua/opcua-client](https://github.com/php-opcua/opcua-client) into a standalone package.
- **10 commands:** `browse`, `read`, `write`, `endpoints`, `watch`, `generate:nodeset`, `dump:nodeset`, `trust`, `trust:list`, `trust:remove`.
- Full security support (6 policies, 3 auth modes), JSON output, debug logging.
- NodeSet2.xml code generator: typed DTOs, PHP enums, binary codecs, registrar with dependency resolution.
- Server address space dump to NodeSet2.xml.
- Server certificate trust management from the terminal.
