---
eyebrow: 'Docs · Getting started'
lede:    'Install via Composer for development; download a PHAR or static binary from GitHub Releases for deployment. Three install paths, one binary called opcua-cli.'

see_also:
  - { href: './first-browse.md',         meta: '3 min' }
  - { href: '../building/phar.md',       meta: '5 min' }
  - { href: '../building/static-binary.md', meta: '6 min' }

prev: { label: 'Overview',     href: '../overview.md' }
next: { label: 'First browse', href: './first-browse.md' }
---

# Installation

`opcua-cli` is distributed in three forms, ordered by use case:

| Form              | Best for                              | Where                                                  |
| ----------------- | ------------------------------------- | ------------------------------------------------------ |
| Composer package  | PHP developers, project-local install | [Packagist](https://packagist.org/packages/php-opcua/opcua-cli) |
| PHAR              | Single-file distribution, dev boxes   | GitHub Releases / [build it yourself](../building/phar.md) |
| Static binary     | Servers without PHP installed         | GitHub Releases / [build it yourself](../building/static-binary.md) |

## Composer

The path most application developers will pick. Adds the CLI as a
dev dependency of your PHP project, exposes `vendor/bin/opcua-cli`.

<!-- @code-block language="bash" label="terminal — composer" -->
```bash
composer require --dev php-opcua/opcua-cli
```
<!-- @endcode-block -->

The runtime is whichever PHP you use for the project (≥ 8.2,
`ext-openssl`). After install, the CLI is at:

<!-- @code-block language="bash" label="terminal — verify" -->
```bash
vendor/bin/opcua-cli --version
# → opcua-cli 4.3.0
```
<!-- @endcode-block -->

Add `vendor/bin` to `$PATH` if you want to invoke `opcua-cli`
directly without the prefix.

## PHAR

Single-file, self-contained, requires PHP 8.2+ on the host.
Suitable for dev workstations that already have PHP available.

<!-- @code-block language="bash" label="terminal — phar" -->
```bash
# From GitHub Releases
curl -L https://github.com/php-opcua/opcua-cli/releases/latest/download/opcua-cli.phar \
    -o /usr/local/bin/opcua-cli
chmod +x /usr/local/bin/opcua-cli

opcua-cli --version
```
<!-- @endcode-block -->

To build it from source, see [Building · PHAR](../building/phar.md).

## Static binary

A pre-compiled standalone executable bundling PHP + the OPC UA
stack. Targets hosts that do not have PHP — operator workstations,
field maintenance laptops, hardened servers.

Available on GitHub Releases for Linux x86_64, Linux aarch64, macOS
(Intel + Apple Silicon), Windows x86_64.

<!-- @code-block language="bash" label="terminal — static binary" -->
```bash
# Linux x86_64
curl -L https://github.com/php-opcua/opcua-cli/releases/latest/download/opcua-cli-linux-x86_64 \
    -o /usr/local/bin/opcua-cli
chmod +x /usr/local/bin/opcua-cli

opcua-cli --version
```
<!-- @endcode-block -->

To build it from source, see [Building · Static binary](../building/static-binary.md).

## Requirements

| Form              | Runtime requirement                                  |
| ----------------- | ---------------------------------------------------- |
| Composer          | PHP ≥ 8.2, `ext-openssl`                             |
| PHAR              | PHP ≥ 8.2, `ext-openssl`, `ext-phar`                 |
| Static binary     | OS only — Linux 5.x / macOS 11+ / Windows 10+        |

The Composer install brings these in transitively:

- [`php-opcua/opcua-client`](https://github.com/php-opcua/opcua-client)
  — the OPC UA stack
- `psr/log` — logging interfaces (Composer provides a null logger)
- `php-tui/php-tui` — terminal-UI library for the `explore` command

No additional configuration. No system service to install.

## Verify the install

<!-- @code-block language="bash" label="terminal — smoke test" -->
```bash
opcua-cli --version
opcua-cli --help
```
<!-- @endcode-block -->

`--version` prints the running build's version string. `--help`
prints the command catalogue and global options — your map of the
tool's surface.

## Updating

| Form              | Update command                                        |
| ----------------- | ----------------------------------------------------- |
| Composer          | `composer update php-opcua/opcua-cli`                |
| PHAR              | Re-download from GitHub Releases                      |
| Static binary     | Re-download from GitHub Releases                      |

The version-bump policy follows
[`opcua-client`](https://github.com/php-opcua/opcua-client) —
major and minor versions track the library. See the [release
notes](https://github.com/php-opcua/opcua-cli/releases) for what
changed.

## Next

Open [First browse](./first-browse.md) and run the canonical
"hello, OPC UA" against any server.
