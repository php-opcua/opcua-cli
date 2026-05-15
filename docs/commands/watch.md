---
eyebrow: 'Docs · Command · watch'
lede:    'Poll a node and print every change. Polling-based (not OPC UA subscription) — simple, predictable, line-per-change output. Press Ctrl-C to stop.'

see_also:
  - { href: './read.md',                              meta: '3 min' }
  - { href: '../recipes/live-monitoring-with-watch.md', meta: '4 min' }
  - { href: '../output/output-formats.md',            meta: '4 min' }

prev: { label: 'endpoints', href: './endpoints.md' }
next: { label: 'explore',   href: './explore.md' }
---

# `watch`

Poll a node at a fixed interval and print each value (or each
change). Long-running until interrupted with `Ctrl-C`.

## Usage

<!-- @code-block language="text" label="signature" -->
```text
opcua-cli watch <endpoint> <nodeId> [--interval=N] [global-options]
```
<!-- @endcode-block -->

| Argument          | Meaning                                                   |
| ----------------- | --------------------------------------------------------- |
| `<endpoint>`      | OPC UA server URL. Required.                              |
| `<nodeId>`        | Node to poll. NodeId or browse path. Required.            |

| Option              | Default      | Effect                                  |
| ------------------- | ------------ | --------------------------------------- |
| `--interval=N`      | `1` (seconds)| Poll interval in seconds                |

Plus the [global options](../reference/global-options.md).

## Examples

### Basic — poll every second

<!-- @code-block language="bash" label="terminal — default interval" -->
```bash
opcua-cli watch opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed"
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="console output" -->
```text
[2026-05-15 10:30:00] 42.5
[2026-05-15 10:30:01] 42.7
[2026-05-15 10:30:02] 43.1
[2026-05-15 10:30:03] 43.1
[2026-05-15 10:30:04] 43.5
^C
```
<!-- @endcode-block -->

One line per poll. Each line: timestamp + the current value.
`Ctrl-C` stops the loop and exits 0.

### Faster polling

<!-- @code-block language="bash" label="terminal — fast interval" -->
```bash
opcua-cli watch opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed" --interval=0.25
```
<!-- @endcode-block -->

Sub-second intervals work — `0.25` polls four times a second.
Cap how aggressively you poll based on the server's tolerance;
some PLCs throttle excessive reads on the same NodeId.

### JSON output

<!-- @code-block language="bash" label="terminal — JSON" -->
```bash
opcua-cli watch opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed" --json
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="JSON output" -->
```text
{"timestamp":"2026-05-15T10:30:00+00:00","value":42.5,"statusCode":0}
{"timestamp":"2026-05-15T10:30:01+00:00","value":42.7,"statusCode":0}
{"timestamp":"2026-05-15T10:30:02+00:00","value":43.1,"statusCode":0}
```
<!-- @endcode-block -->

NDJSON — one JSON object per poll, separated by `\n`. Pipe to
`jq` or a log collector.

### Pipe to a file

<!-- @code-block language="bash" label="terminal — to file" -->
```bash
opcua-cli watch opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed" --interval=0.5 --json \
    > /var/log/speed.ndjson
```
<!-- @endcode-block -->

The file grows for the lifetime of the watch — rotate / cap by
external tooling (logrotate, journal piping, …) if you let it run
overnight.

## Polling vs subscriptions

`watch` polls — it calls `read()` every `--interval` seconds. It
does **not** create an OPC UA subscription. Trade-offs:

| Aspect                    | `watch` (polling)                                     | OPC UA subscription                              |
| ------------------------- | ----------------------------------------------------- | ------------------------------------------------ |
| Setup cost                | None — just `read()` in a loop                        | `CreateSubscription` + `CreateMonitoredItems`   |
| Server resources used     | Per-read transient                                    | Long-lived subscription state on the server      |
| Captures changes between polls | No — only the value at each poll tick             | Yes — server pushes every change                 |
| Bandwidth                 | One round-trip per poll, regardless of changes        | One round-trip per notification (more efficient on stable values) |
| Suitable when             | Short-lived observation, simple cases, debugging      | Long-running monitoring, dense change streams    |

For real subscriptions, drop down to
[`opcua-client`](https://github.com/php-opcua/opcua-client/blob/master/docs/operations/subscriptions.md)
or use `opcua-session-manager` with auto-publish. The CLI's
`watch` is for **interactive observation** and **simple scripts**.

## How it maps to the library

| You ran                                                  | The CLI loops                                             |
| -------------------------------------------------------- | --------------------------------------------------------- |
| `opcua-cli watch <endpoint> <nodeId> --interval=N`       | `while (true) { $dv = $client->read($nodeId); print(...); sleep(N); }` |

Same OPC UA reads as `opcua-cli read`, repeated.

## Stopping

`Ctrl-C` (SIGINT) is the clean stop. The application closes the
connection, prints any final summary line, exits `0`.

`Ctrl-Z` (SIGTSTP) suspends; resume with `fg`. Sub-second
intervals tolerate suspension poorly — values may bunch up on
resume.

## When the connection drops mid-watch

The CLI does not automatically reconnect. If the server restarts
or the network blip lasts longer than the read timeout, the next
poll fails and the command exits non-zero.

For resilient monitoring, wrap the command in a shell loop:

<!-- @code-block language="bash" label="terminal — auto-restart" -->
```bash
while ! opcua-cli watch opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed"; do
    sleep 5
done
```
<!-- @endcode-block -->

For more robust patterns, see
[Recipes · Live monitoring with watch](../recipes/live-monitoring-with-watch.md).

## Common pitfalls

- **Polling a server that throttles** — some servers reject
  repeated reads of the same NodeId from the same session.
  Increase `--interval`.
- **Polling a node whose `Value` is a complex structure** —
  the console output prints PHP's default representation, which
  is ugly for arrays / DTOs. Use `--json` instead.
- **Forgetting to `Ctrl-C`** — running with `nohup` or in
  `screen` and forgetting about it produces a multi-gigabyte log.
  Set a max line count externally if running unattended.
