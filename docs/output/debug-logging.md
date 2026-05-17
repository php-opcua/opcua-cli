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

Whatever `opcua-client` chooses to log at PSR-3 `debug` level —
the CLI sets up the sink and passes it through. Each log call
becomes one line via `StreamLogger`:

<!-- @code-block language="text" label="sample debug output" -->
```text
[10:30:00.123] [debug] Hello sent to opc.tcp://plc.local:4840
[10:30:00.137] [debug] Ack received
[10:30:00.150] [debug] OpenSecureChannel request 1 with policy None
[10:30:00.162] [debug] OpenSecureChannel response 1 in 12 ms
[10:30:00.171] [debug] CreateSession request 2
[10:30:00.190] [debug] Browse request 4 for nodeId i=85
[10:30:00.193] [debug] Browse response 4 with 4 refs in 3 ms
```
<!-- @endcode-block -->

The exact format is `[HH:MM:SS.mmm] [<level>] <message>` —
local-time timestamp with millisecond resolution, the level
lowercased and bracketed (`[debug]`, `[info]`, `[warning]`),
then the interpolated message. PSR-3 placeholders (`{key}`) are
substituted from the context array; **any context keys that
aren't referenced in the message are dropped** (the formatter
does not append a JSON blob).

## What does *not* get logged

`StreamLogger` itself does no redaction — it formats whatever the
upstream client emits. Whether credentials, tokens or value
payloads appear in the log depends entirely on `opcua-client`'s
own logging conventions:

- Authentication tokens, username / password values, and
  certificate bodies are not expected in `opcua-client`'s log
  calls, but the CLI doesn't enforce this. If you change PHP
  versions or upgrade the library and a log line surfaces a new
  field, the CLI will pass it through verbatim.
- Variant `value` fields from read / publish responses are not
  generally logged either, but again the CLI cannot guarantee
  this on behalf of the upstream library.

Treat the debug trace as **possibly sensitive** — review it
before attaching to vendor support tickets, especially when the
session involved a username / password handshake or a write call.

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

The file contains every protocol step `opcua-client` chooses to
emit. Before attaching to a support ticket, scan it for anything
sensitive — the CLI does not redact log output on its own.

**Tail the log live during a watch:**

<!-- @code-block language="bash" label="bash — tail during watch" -->
```bash
# Terminal 1 — default subscription mode + debug to file
opcua-cli watch opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed" \
    --debug-file=/tmp/watch.log &

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
