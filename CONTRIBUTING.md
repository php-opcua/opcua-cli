# Contributing to OPC UA CLI

## Welcome!

Thank you for considering contributing to this project! Every contribution matters, whether it's a bug report, a feature suggestion, a documentation fix, or a code change. This project is open to everyone, you're welcome here.

If you have any questions or need help getting started, don't hesitate to open an issue. We're happy to help.

## Development Setup

### Requirements

- PHP >= 8.2
- `ext-openssl`
- Composer
- [opcua-test-suite](https://github.com/php-opcua/opcua-test-suite) (for integration tests)

### Installation

```bash
git clone https://github.com/php-opcua/opcua-cli.git
cd opcua-cli
composer install
```

### Test Server

Integration tests require the OPC UA test server suite running locally:

```bash
git clone https://github.com/php-opcua/opcua-test-suite.git
cd opcua-test-suite
docker compose up -d
```

## Running Tests

The project currently has **272 tests** (253 unit + 19 integration) with 592 assertions and **99.9% code coverage**.

```bash
# All tests
./vendor/bin/pest

# Unit tests only
./vendor/bin/pest tests/Unit/

# Integration tests only
./vendor/bin/pest tests/Integration/ --group=integration

# A specific test file
./vendor/bin/pest tests/Unit/ArgvParserTest.php

# With coverage report (requires pcov)
php -d pcov.enabled=1 ./vendor/bin/pest --coverage
```

All tests must pass before submitting a pull request.

## Project Structure

```
src/
├── Application.php            # Main entry point, command registration
├── ArgvParser.php             # CLI argument and option parsing
├── CommandRunner.php          # Command execution and client lifecycle
├── CodeGenerator.php          # NodeSet2.xml → PHP code generation
├── NodeSetParser.php          # NodeSet2.xml parsing
├── NodeSetXmlBuilder.php      # Address space → NodeSet2.xml export
├── StreamLogger.php           # PSR-3 logger writing to stderr
├── Commands/                  # CLI command implementations
│   ├── CommandInterface.php   # Command contract
│   ├── BrowseCommand.php      # Browse address space
│   ├── ReadCommand.php        # Read node values
│   ├── WriteCommand.php       # Write values to nodes
│   ├── EndpointsCommand.php   # Discover server endpoints
│   ├── WatchCommand.php       # Watch value changes in real time
│   ├── GenerateNodesetCommand.php  # Generate PHP from NodeSet2.xml
│   ├── DumpNodesetCommand.php      # Dump address space to NodeSet2.xml
│   ├── TrustCommand.php       # Trust a server certificate
│   ├── TrustListCommand.php   # List trusted certificates
│   └── TrustRemoveCommand.php # Remove a trusted certificate
└── Output/                    # Output formatting
    ├── OutputInterface.php    # Output contract
    ├── ConsoleOutput.php      # Human-readable tree/text output
    └── JsonOutput.php         # Machine-readable JSON output

tests/
├── Unit/                      # Unit tests (no server required)
├── Integration/               # Integration tests (require test server)
└── Fixtures/                  # Test data (e.g. TestNodeSet2.xml)
```

## Design Principles

### Thin CLI Layer

This package is a CLI wrapper around [php-opcua/opcua-client](https://github.com/php-opcua/opcua-client). Business logic, protocol handling, and OPC UA types belong in the client library — this package handles argument parsing, command dispatch, and output formatting.

### No Framework Dependencies

The CLI uses no framework (no Symfony Console, no Laravel). Argument parsing, command routing, and output are implemented from scratch with zero runtime dependencies beyond `php-opcua/opcua-client` and `ext-openssl`.

### Command Pattern

Each CLI command implements `CommandInterface` and is registered in `Application`. Commands receive parsed arguments and an output adapter, delegating OPC UA operations to the client library.

### Output Strategy

All commands write through `OutputInterface`. Console output produces human-readable tree/text; JSON output produces machine-parseable structures. Commands must not write directly to stdout.

## Guidelines

### Code Style

The project enforces a Laravel-style coding standard (PSR-12 + opinionated rules) via [php-cs-fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer). Configuration lives in `.php-cs-fixer.php`.

```bash
# Format all files
composer format

# Check without modifying (CI mode)
composer format:check
```

**You must run `composer format` before committing.** Pull requests with unformatted code will fail the CI check. Make it a habit: write code, run `composer format`, then commit.

**Key rules:**

- `declare(strict_types=1)` required
- Single quotes for strings
- Trailing commas in multiline arrays, arguments, and parameters
- `not_operator_with_successor_space` (space after `!`)
- Ordered imports (alphabetical)
- No unused imports
- No blank lines after class opening brace
- Type declarations for parameters, return types, and properties

**IDE integration:**

- **PhpStorm**: Settings > PHP > Quality Tools > PHP CS Fixer — point to `vendor/bin/php-cs-fixer` and `.php-cs-fixer.php`. Enable "On Save" for automatic formatting.
- **VSCode**: Install the `junstyle.php-cs-fixer` extension. It reads `.php-cs-fixer.php` automatically.

### Documentation & Comments

- Every class, trait, interface, and enum must have a PHPDoc description
- Every public method must have a PHPDoc block with `@param`, `@return`, `@throws`, and `@see` where applicable
- `@return` and `@param` must be on their own line, not inline with the description
- **Do not add comments inside function bodies.** No `//`, no `/* */`, no section headers. If the code needs a comment to be understood, the method is too complex — split it into smaller, well-named methods instead. The method name and its PHPDoc should be enough to understand what it does.
- Update relevant files in `doc/` for new or changed commands
- Update `CHANGELOG.md` with your changes
- Update `README.md` command reference if adding or modifying a command

### Adding a New Command

1. Create a class in `src/Commands/` implementing `CommandInterface`
2. Register it in `Application::__construct()`
3. Output through the `OutputInterface` — support both console and JSON formats
4. Write unit tests (mock the client) and integration tests (against the test server)
5. Document the command in `doc/01-cli.md` and `README.md`

### Testing

- Write unit tests for all new functionality
- Write integration tests for commands that interact with an OPC UA server
- Use Pest PHP syntax (not PHPUnit)
- Group integration tests with `->group('integration')`
- Use `TestHelper::safeDisconnect()` in `finally` blocks for integration tests
- **Code coverage must remain >= 99.5%.** Run `php -d pcov.enabled=1 ./vendor/bin/pest --coverage` and verify the total before submitting. Pull requests that drop coverage below this threshold will not be merged

### Commits

- Use descriptive commit messages
- Prefix with `[ADD]`, `[UPD]`, `[PATCH]`, `[REF]`, `[DOC]`, `[TEST]` as appropriate

## Pull Request Process

1. Fork the repository and create a feature branch
2. Write your code and tests
3. Run `composer format` to format your code
4. Ensure all tests pass
5. Update documentation and changelog
6. Submit a pull request
7. Wait for review — a maintainer will review your PR, may request changes or ask questions
8. Once approved, your PR will be merged

## Reporting Issues

Use the [issue tracker](https://github.com/php-opcua/opcua-cli/issues) to report bugs, request features, or ask questions.
