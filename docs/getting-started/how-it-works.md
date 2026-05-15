---
eyebrow: 'Docs · Getting started'
lede:    'opcua-cli is opcua-client run from the shell. Every command opens a connection, executes one OPC UA operation, prints, exits. No state, no daemon, no SDK to keep open.'

see_also:
  - { href: '../commands/browse.md',       meta: '4 min' }
  - { href: '../connecting/endpoint-urls.md', meta: '3 min' }
  - { href: 'https://github.com/php-opcua/opcua-client',  meta: 'external', label: 'opcua-client' }

prev: { label: 'First browse', href: './first-browse.md' }
next: { label: 'browse',       href: '../commands/browse.md' }
---

# How it works

`opcua-cli` is a one-shot wrapper around the pure-PHP
[`php-opcua/opcua-client`](https://github.com/php-opcua/opcua-client)
library. Every invocation:

<!-- @steps -->
- **Parses argv.**

  `ArgvParser` reads short (`-s`) and long (`--security-policy`)
  options, separates command from positional arguments, picks up
  `--key=value` and `--key value`.

- **Builds a `ClientBuilder`.**

  `CommandRunner::createClientBuilder()` translates parsed
  options into setters: `setTimeout()`, `setSecurityPolicy()`,
  `setSecurityMode()`, `setClientCertificate()`,
  `setUserCredentials()`, `setTrustStore()`, `setLogger()`.

- **Connects (if the command needs it).**

  Commands like `browse`, `read`, `write` set
  `requiresConnection()` to `true`. The application calls
  `$builder->connect($endpointUrl)` before dispatching.

  `generate:nodeset`, `trust:list`, `trust:remove` don't connect
  — they work on local files or the local trust store.

- **Executes one OPC UA operation.**

  The command's `execute()` method runs. Browse calls `browse()`,
  read calls `read()`, watch enters a polling loop. They use the
  `$client` instance directly.

- **Prints output.**

  `OutputInterface` is the abstraction. `ConsoleOutput` produces
  human-readable tables / trees; `JsonOutput` emits structured
  JSON when `--json` is set.

- **Disconnects and exits.**

  The connection closes, the PHP process terminates, the exit
  code is returned. No background state.
<!-- @endsteps -->

## What this means

- **Every command is independent.** There is no shared state
  between two invocations. The CLI does **not** keep connections
  alive — every command pays the OPC UA handshake (~1 s for
  secured channels).
- **Scripting is straightforward.** A shell `for` loop running
  the CLI per element is fine — connection pooling is your
  application's concern, not the CLI's.
- **No background processes.** The CLI is short-lived. For long-
  running sessions or shared connections across processes, use
  [`opcua-session-manager`](https://github.com/php-opcua/opcua-session-manager).

## Comparison with the library

| You want                                  | Reach for                                       |
| ----------------------------------------- | ----------------------------------------------- |
| One-off browse / read / write             | `opcua-cli`                                     |
| Long-running PHP application              | `opcua-client` directly                         |
| Persistent sessions across PHP requests   | `opcua-session-manager`                         |
| Quick check from a production shell       | `opcua-cli`                                     |
| CI smoke test ("is the server up?")       | `opcua-cli` ([recipe](../recipes/ci-smoke-test.md)) |

The CLI is the **terminal face**; the library is the **embedding
face**. Both run the same protocol; they differ in process
lifetime and ergonomics.

## When the CLI connects vs when it does not

Eight of the eleven commands require a server connection (the
`requiresConnection() === true` set):

| Connects                    | Stays local                              |
| --------------------------- | ---------------------------------------- |
| `browse`                    | `generate:nodeset` (consumes a local XML) |
| `read`                      | `trust:list` (reads the local trust store) |
| `write`                     | `trust:remove`                            |
| `endpoints`                 |                                          |
| `watch`                     |                                          |
| `explore`                   |                                          |
| `trust` (downloads the cert) |                                          |
| `dump:nodeset`              |                                          |

The application skips the connection step entirely for the three
local commands — useful when verifying a generated file or
listing trusted servers without touching the network.

## How options flow through

The option pipeline is wide but shallow:

<!-- @code-block language="text" label="option flow" -->
```text
argv  →  ArgvParser.parse()  →  ['command' => 'browse',
                                  'arguments' => ['opc.tcp://...'],
                                  'options'  => ['security-policy' => 'Basic256Sha256',
                                                 'cert'            => '/path/...',
                                                 'json'            => true, ...]]
        │
        ▼
       CommandRunner.createClientBuilder()
        │
        ▼
       ClientBuilder configured with the relevant setters
        │
        ▼
       Command.execute($client, $arguments, $options, $output)
                     ↑                                  ↑
              already connected                    final formatting choice
```
<!-- @endcode-block -->

The `$options` array also reaches the command itself — `browse`
reads `$options['recursive']` and `$options['depth']`; `read`
reads `$options['attribute']`; `watch` reads
`$options['interval']`. Every command page documents which
options it consumes.

## Why this design

- **Composable** — pipes, scripts, CI workflows expect this
  shape.
- **Reproducible** — same args twice = same result. No hidden
  state.
- **Auditable** — every option corresponds to one library
  setter, every connection paid up front, every error visible.

If you've used `psql`, `redis-cli`, or `aws s3 cp`, the model is
familiar.
