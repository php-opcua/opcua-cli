---
eyebrow: 'Docs · Overview'
lede:    'A terminal tool for OPC UA — browse, read, write, watch, trust, generate. Eleven commands on top of the pure-PHP opcua-client library; no daemon, no SDK, no JVM.'

see_also:
  - { href: './getting-started/installation.md', meta: '2 min' }
  - { href: './getting-started/first-browse.md', meta: '3 min' }
  - { href: 'https://github.com/php-opcua/opcua-client', meta: 'external', label: 'php-opcua/opcua-client' }

prev: { label: 'No previous page', href: '#' }
next: { label: 'Installation',     href: './getting-started/installation.md' }
---

# Overview

`opcua-cli` is the command-line companion to
[`php-opcua/opcua-client`](https://github.com/php-opcua/opcua-client).
Eleven commands cover the everyday operator tasks against an OPC
UA server: browse, read, write, subscribe, manage trust, generate
PHP types from NodeSet2.xml.

It is **the same OPC UA stack** as the library, exposed as a
terminal tool. No additional native dependencies — pure PHP, ships
either as a Composer dependency, a PHAR, or a static binary.

## In one example

<!-- @code-block language="bash" label="terminal" -->
```bash
$ opcua-cli browse opc.tcp://plc.local:4840

  Objects
  ├── Server
  ├── DeviceSet
  └── Boilers
```
<!-- @endcode-block -->

One line, one round-trip, two readable columns. That is the whole
shape — every command takes an endpoint URL, optional flags, and
prints either a human-readable table / tree or JSON.

## Eleven commands

| Command            | Use                                              |
| ------------------ | ------------------------------------------------ |
| `browse`           | Walk the address space at a node                 |
| `read`             | Read a node attribute                            |
| `write`            | Write a value to a node                          |
| `endpoints`        | Discover server endpoints, policies, security    |
| `watch`            | Poll a node and print changes                    |
| `explore`          | Interactive terminal UI for browsing             |
| `trust`            | Add the server's certificate to the trust store  |
| `trust:list`       | List trusted certificates                        |
| `trust:remove`     | Remove a trusted certificate                     |
| `generate:nodeset` | Render PHP classes from a NodeSet2.xml           |
| `dump:nodeset`     | Export a server's namespace to a NodeSet2.xml    |

The first six are runtime tools; the next three are trust-store
management; the last two are the code-generation pipeline.

## When to reach for it

Use `opcua-cli` when:

- **You're on a PLC commissioning** — connect to a fresh server,
  verify it's reachable, list its endpoints, accept its cert,
  read a tag.
- **You're debugging from the field** — SSH into a server, browse
  the address space, watch a value live.
- **You're integrating** — generate PHP types from the vendor's
  NodeSet2.xml so your application code is type-safe.
- **You're scripting** — every command supports `--json` for
  pipelines (`opcua-cli endpoints … --json | jq …`).

Skip it when:

- **You need long-running sessions** — use
  [`opcua-session-manager`](https://github.com/php-opcua/opcua-session-manager).
- **You need to embed OPC UA in a service** — use
  [`opcua-client`](https://github.com/php-opcua/opcua-client)
  directly.

## What's in the box

<!-- @code-block language="text" label="install layout" -->
```text
vendor/bin/opcua-cli       launcher
src/
├── Application.php        argv → command dispatcher
├── ArgvParser.php         short/long option parsing
├── CommandRunner.php      options → ClientBuilder translator
├── Commands/              11 commands, one file each
├── Output/                Console + JSON output backends
├── Tui/                   ExploreApp (PhpTui-based TUI)
├── NodeSetParser.php      NodeSet2.xml → array
├── NodeSetXmlBuilder.php  array → NodeSet2.xml
├── CodeGenerator.php      PHP class rendering
└── StreamLogger.php       --debug log target
```
<!-- @endcode-block -->

Zero magic. Every command is one PHP class implementing the
`CommandInterface` contract (`getName`, `getDescription`,
`getUsage`, `requiresConnection`, `execute`).

## How the documentation is organised

- **Getting started** — install, first browse, mental model.
- **Commands** — one page per command, with examples and flags.
- **Connecting** — endpoint URLs, security, credentials, trust.
- **Output** — console vs JSON, debug logging.
- **Code generation** — generate / dump / consume the typed output.
- **Building** — package the CLI as PHAR or static binary.
- **Reference** — global options, exit codes, exceptions.
- **Recipes** — practical patterns (CI checks, trust rollout,
  monitoring, inventory).

## Ecosystem

| Package                                                       | Role                                                          |
| ------------------------------------------------------------- | ------------------------------------------------------------- |
| [`php-opcua/opcua-client`](https://github.com/php-opcua/opcua-client)         | The OPC UA client library this CLI wraps    |
| **`php-opcua/opcua-cli`** (this package)                       | Terminal tool                                                |
| [`php-opcua/opcua-client-nodeset`](https://github.com/php-opcua/opcua-client-nodeset) | Pre-generated PHP for 51 companion specs |
| [`php-opcua/opcua-session-manager`](https://github.com/php-opcua/opcua-session-manager) | Daemon-based session persistence       |
| [OPC Foundation UA-Nodeset](https://github.com/OPCFoundation/UA-Nodeset)      | Source NodeSet2.xml files                  |
