---
eyebrow: 'Docs · Output'
lede:    'Three flags for debug logging: --debug writes to stdout, --debug-stderr writes to stderr, --debug-file writes to a path. Captures every protocol step from the underlying client.'

see_also:
  - { href: './output-formats.md',                meta: '5 min' }
  - { href: '../reference/exceptions-and-errors.md', meta: '4 min' }
  - { href: 'https://github.com/php-opcua/opcua-client/blob/master/docs/connection/opening-and-closing.md', meta: 'external', label: 'opcua-client — connection' }

prev: { label: 'Output formats',  href: './output-formats.md' }
next: { label: 'Generate from XML', href: '../code-generation/generate-from-xml.md' }
---

# Debug logging

The CLI wires the underlying `opcua-client` library to a PSR-3
logger when a debug flag is set. Every protocol step — handshake,
secure channel open, session create, every read/write/browse
call — produces a log line at `debug` level.

## The three flags

| Flag                  | Destination                                            |
| --------------------- | ------------------------------------------------------ |
| `-d, --debug`         | Stdout                                                  |
| `--debug-stderr`      | Stderr                                                  |
| `--debug-file=PATH`   | Append to the given file                                |

Only one of the three should be active at a time. `--debug`
mixes with regular command output on stdout, so it conflicts with
`--json` (which expects clean JSON on stdout).

The CLI rejects `--debug` + `--json` together:

<!-- @code-block language="text" label="conflict" -->
```text
Error: --debug and --json cannot be used together. Use --debug-stderr or --debug-file instead.
```
<!-- @endcode-block -->

## What gets logged

Per-call detail. A `browse` invocation with `--debug` looks like:

<!-- @code-block language="text" label="sample debug output" -->
```text
[2026-05-15T10:30:00Z] DEBUG opcua.transport.hello {"endpoint":"opc.tcp://plc.local:4840"}
[2026-05-15T10:30:00Z] DEBUG opcua.transport.ack
[2026-05-15T10:30:00Z] DEBUG opcua.discovery.start
[2026-05-15T10:30:00Z] DEBUG opcua.discovery.endpoints {"count":4}
[2026-05-15T10:30:00Z] DEBUG opcua.opn.request {"requestId":1,"policy":"None"}
[2026-05-15T10:30:00Z] DEBUG opcua.opn.response {"requestId":1,"duration_ms":12}
[2026-05-15T10:30:00Z] DEBUG opcua.session.create {"requestId":2}
[2026-05-15T10:30:00Z] DEBUG opcua.session.activate {"requestId":3}
[2026-05-15T10:30:00Z] DEBUG opcua.browse.request {"requestId":4,"nodeId":"i=85"}
[2026-05-15T10:30:00Z] DEBUG opcua.browse.response {"requestId":4,"refs":4,"duration_ms":3}
[2026-05-15T10:30:00Z] DEBUG opcua.session.close
[2026-05-15T10:30:00Z] DEBUG opcua.transport.close
```
<!-- @endcode-block -->

The format is one line per event: ISO timestamp, level, channel,
JSON-shaped context.

## What does *not* get logged

The CLI is conservative about what it logs:

- **Authentication tokens** — `getAuthToken()` is opaque; never
  serialised into the context.
- **Username / password values** — credentials never appear in
  log output.
- **Certificate bodies** — only fingerprints, at trust
  decisions.
- **Variant `value` fields** — read responses log only
  `statusCode` and the NodeId, not the value. (The value is
  printed by the command itself to stdout, separately.)

The trace is safe to share with vendors and support teams.

## `--debug` on stdout

<!-- @code-block language="bash" label="terminal — stdout debug" -->
```bash
opcua-cli browse opc.tcp://plc.local:4840 --debug
```
<!-- @endcode-block -->

Mixes debug output with the command's normal output. Useful for
quick interactive diagnostics; not useful when the output is
being parsed.

## `--debug-stderr`

<!-- @code-block language="bash" label="terminal — stderr debug" -->
```bash
opcua-cli browse opc.tcp://plc.local:4840 --debug-stderr
# Debug on stderr, browse output on stdout — pipe-friendly
opcua-cli browse opc.tcp://plc.local:4840 --debug-stderr 2>browse-debug.log
```
<!-- @endcode-block -->

The cleanest debug path for scripting. Stdout stays usable for
piping; stderr captures the trace.

## `--debug-file=PATH`

<!-- @code-block language="bash" label="terminal — file debug" -->
```bash
opcua-cli browse opc.tcp://plc.local:4840 --debug-file=/var/log/opcua-cli.log
```
<!-- @endcode-block -->

Appends to the file. Useful for long-running sessions
(`watch`, `explore`) where you want the trace persisted without
polluting either standard stream.

The file must be writeable. The CLI exits `1` with a clear error
if it cannot open the path.

## With `--json`

When the output backend is JSON, debug **must** go to a
non-stdout sink. The CLI enforces this:

<!-- @code-block language="bash" label="terminal — debug + json" -->
```bash
# Allowed: clean JSON on stdout, debug on stderr
opcua-cli read opc.tcp://plc.local:4840 i=2261 --json --debug-stderr

# Allowed: clean JSON on stdout, debug to file
opcua-cli read opc.tcp://plc.local:4840 i=2261 --json --debug-file=/tmp/debug.log

# Rejected: --debug + --json
opcua-cli read opc.tcp://plc.local:4840 i=2261 --json --debug
# → Error: --debug and --json cannot be used together. Use --debug-stderr or --debug-file instead.
```
<!-- @endcode-block -->

## How it maps to the library

The CLI builds a `StreamLogger` (from
`PhpOpcua\Cli\StreamLogger`) pointing at the configured sink, and
passes it to `ClientBuilder::setLogger()`. From that point, every
log call from inside `opcua-client` writes to your sink.

| Flag                | StreamLogger sink                                  |
| ------------------- | -------------------------------------------------- |
| `--debug`           | `php://stdout`                                     |
| `--debug-stderr`    | `php://stderr`                                     |
| `--debug-file=PATH` | The file at PATH (append mode)                     |

No log level override — the CLI logs at `debug` (most verbose)
when any flag is set. To filter, post-process with `grep`.

## Common patterns

**Diagnose a failing connect:**

<!-- @code-block language="bash" label="bash — connect debugging" -->
```bash
opcua-cli endpoints opc.tcp://plc.local:4840 --debug-stderr 2>&1 \
    | tail -30
```
<!-- @endcode-block -->

Shows the discovery flow, OPN handshake, any error before the
service call.

**Capture a session for a vendor support ticket:**

<!-- @code-block language="bash" label="bash — vendor capture" -->
```bash
opcua-cli read opc.tcp://plc.local:4840 i=2261 \
    -s Basic256Sha256 -m SignAndEncrypt \
    --cert=client.pem --key=client.key \
    --debug-file=/tmp/opcua-trace.log
# Attach /tmp/opcua-trace.log to the support ticket.
```
<!-- @endcode-block -->

The file contains every protocol step. Sanitised by the CLI's
own log conventions (no credentials, no values), so it's safe
to attach.

**Tail the log live during a watch:**

<!-- @code-block language="bash" label="bash — tail during watch" -->
```bash
# Terminal 1
opcua-cli watch opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed" \
    --interval=0.5 --debug-file=/tmp/watch.log &

# Terminal 2
tail -f /tmp/watch.log
```
<!-- @endcode-block -->

## Performance

Debug logging is verbose. On a busy `watch` with sub-second
intervals, the log can grow to multi-MB per minute. For
production-style diagnostic captures, prefer **short windows
with focused commands** over leaving `--debug-file` on forever.

If the log volume becomes an issue, the right answer is the
library-level logging API directly — embed `opcua-client` and
wire a more selective logger. The CLI's `--debug` is the
all-or-nothing flag.
