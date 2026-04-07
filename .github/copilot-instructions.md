# OPC UA CLI — Copilot Instructions

This repository contains `php-opcua/opcua-cli`, a standalone PHP command-line tool for interacting with OPC UA servers.

## Project context

For a full understanding of this tool, read these files in order:

1. **[llms.txt](../llms.txt)** — compact project summary: commands, options, architecture
2. **[llms-full.txt](../llms-full.txt)** — comprehensive technical reference: every command, option, class, code generator, output system
3. **[llms-skills.md](../llms-skills.md)** — task-oriented recipes: browse, read, write, watch, security, trust, code generation, scripting

## Architecture

```
bin/opcua-cli
    │
    ▼
Application (command dispatch)
    ├── ArgvParser (CLI argument parsing)
    ├── CommandRunner (client lifecycle, security config)
    │   └── ClientBuilder → Client (from opcua-client)
    ├── Commands/ (10 commands)
    │   ├── BrowseCommand
    │   ├── ReadCommand
    │   ├── WriteCommand
    │   ├── WatchCommand
    │   ├── EndpointsCommand
    │   ├── GenerateNodesetCommand
    │   ├── DumpNodesetCommand
    │   ├── TrustCommand
    │   ├── TrustListCommand
    │   └── TrustRemoveCommand
    └── Output/ (ConsoleOutput, JsonOutput)
```

## Key classes

- `src/Application.php` — main entry point, command routing, error handling
- `src/ArgvParser.php` — zero-dependency CLI argument parser
- `src/CommandRunner.php` — configures ClientBuilder with security, auth, trust, logging
- `src/Commands/CommandInterface.php` — contract for all commands
- `src/CodeGenerator.php` — generates PHP classes from parsed NodeSet2 data
- `src/NodeSetParser.php` — parses NodeSet2.xml files
- `src/NodeSetXmlBuilder.php` — builds NodeSet2.xml from server data
- `src/Output/ConsoleOutput.php` — human-readable ANSI output with tree rendering
- `src/Output/JsonOutput.php` — machine-readable JSON output

## Code conventions

- `declare(strict_types=1)` in every file
- Zero framework dependencies (no Symfony Console, no Laravel)
- Commands implement `CommandInterface` with `requiresConnection(): bool`
- PHPDoc on every class and public method
- **No comments inside function bodies**
- Tests use Pest PHP (not PHPUnit)
- Integration tests grouped with `->group('integration')`
- Coverage target: 99.5%+

## Dependencies

- `php-opcua/opcua-client` ^4.0 — OPC UA client (required)
- `ext-openssl` — cryptography
