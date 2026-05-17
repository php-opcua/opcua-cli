---
eyebrow: 'Docs · Command · watch'
lede:    'Watch a node value in real time. Subscription-based by default (OPC UA CreateSubscription + monitored items); --interval=N switches to a polling loop. Press Ctrl-C to stop.'

see_also:
  - { href: './read.md',                              meta: '3 min' }
  - { href: '../recipes/live-monitoring-with-watch.md', meta: '4 min' }
  - { href: '../output/output-formats.md',            meta: '4 min' }

prev: { label: 'endpoints', href: './endpoints.md' }
next: { label: 'explore',   href: './explore.md' }
---

# `watch`

Watch a node value in real time. Two modes:

- **Default (no `--interval`)** — opens a real OPC UA
  **subscription** (`CreateSubscription` + `CreateMonitoredItems`)
  and prints every notification the server pushes.
- **Polling (`--interval=N`)** — falls back to a `read()` loop at
  the given interval.

Long-running until interrupted with `Ctrl-C`.

## Usage

<!-- @code-block language="text" label="signature" -->
```text
opcua-cli watch <endpoint> <nodeId> [--interval=N] [global-options]
```
<!-- @endcode-block -->

| Argument          | Meaning                                                   |
| ----------------- | --------------------------------------------------------- |
| `<endpoint>`      | OPC UA server URL. Required.                              |
| `<nodeId>`        | Node to watch. NodeId or browse path. Required.            |

| Option              | Default                | Effect                                                                                                          |
| ------------------- | ---------------------- | --------------------------------------------------------------------------------------------------------------- |
| `--interval=N`      | (unset → subscription) | Switch to polling and sleep `N` **milliseconds** between reads. `N` is cast to `int`, so non-integer values truncate. |

Plus the [global options](../reference/global-options.md).

## Examples

### Default — subscription

<!-- @code-block language="bash" label="terminal — subscription" -->
```bash
opcua-cli watch opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed"
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="console output" -->
```text
[10:30:00.123] 42.5
[10:30:00.487] 42.7
[10:30:01.012] 43.1
[10:30:02.901] 43.5
^C
```
<!-- @endcode-block -->

One line per change notification the server pushes (publishing
interval defaults to 250 ms inside the CLI). Each line: local
timestamp `HH:MM:SS.mmm` + the new value. `Ctrl-C` interrupts
the process; PHP exits with `130` (128 + SIGINT) since the CLI
does not install a signal handler.

### Polling — explicit interval

<!-- @code-block language="bash" label="terminal — polling" -->
```bash
opcua-cli watch opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed" --interval=1000
```
<!-- @endcode-block -->

`--interval=1000` polls once per second. The interval is
**milliseconds**, not seconds — the source code casts it to `int`
and sleeps `interval * 1000` microseconds. A few examples:

| You passed         | What happens                                                  |
| ------------------ | ------------------------------------------------------------- |
| `--interval=1000`  | One `read()` per second                                       |
| `--interval=250`   | Four `read()`s per second                                     |
| `--interval=1`     | ~1000 `read()`s per second (likely to overload a small server) |
| `--interval=0.25`  | Cast to int → `0` → unbounded polling                          |

Cap how aggressively you poll based on the server's tolerance —
some PLCs throttle excessive reads on the same NodeId.

### JSON output

<!-- @code-block language="bash" label="terminal — JSON" -->
```bash
opcua-cli watch opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed" --json
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="JSON output" -->
```text
{"message":"[10:30:00.123] 42.5"}
{"message":"[10:30:00.487] 42.7"}
{"message":"[10:30:01.012] 43.1"}
```
<!-- @endcode-block -->

NDJSON — one JSON object per notification (or per poll), separated
by `\n`. The `JsonOutput` backend wraps the same console line in
`{"message": "<line>"}`; there is **no** separate `value`,
`statusCode`, or structured `timestamp` field. To extract the
value or timestamp, parse the string inside `.message`.

### Pipe to a file

<!-- @code-block language="bash" label="terminal — to file" -->
```bash
opcua-cli watch opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed" --interval=500 --json \
    > /var/log/speed.ndjson
```
<!-- @endcode-block -->

The file grows for the lifetime of the watch — rotate / cap by
external tooling (logrotate, journal piping, …) if you let it run
overnight.

## Subscription vs polling

`watch` defaults to a **real OPC UA subscription**. `--interval=N`
opts into polling. Trade-offs:

| Aspect                    | Default (subscription)                                  | `--interval=N` (polling)                                  |
| ------------------------- | ------------------------------------------------------- | --------------------------------------------------------- |
| Setup cost                | `CreateSubscription` + `CreateMonitoredItems` once       | None — just `read()` in a loop                            |
| Server resources used     | Long-lived subscription state on the server              | Per-read transient                                         |
| Captures changes between ticks | Yes — server pushes every change                    | No — only the value at each poll tick                      |
| Bandwidth                 | One round-trip per notification (efficient on stable values) | One round-trip per poll, regardless of changes        |
| Suitable when             | Long-running monitoring, dense change streams             | Servers that reject subscriptions, or rate-limited probing |

For richer subscription control (custom publishing interval per
subscription, multiple monitored items per request) drop down to
[`opcua-client`](https://github.com/php-opcua/opcua-client/blob/master/docs/operations/subscriptions.md)
or use `opcua-session-manager` with auto-publish.

## How it maps to the library

| You ran                                                  | The CLI does                                                                                  |
| -------------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| `opcua-cli watch <endpoint> <nodeId>`                    | `$sub = $client->createSubscription(...)` + `$client->createMonitoredItems(...)` + `publish()` loop |
| `opcua-cli watch <endpoint> <nodeId> --interval=N`       | `while (true) { $dv = $client->read($nodeId); print(...); usleep(N * 1000); }`                |

The polling path uses the same `read()` call as `opcua-cli read`,
repeated.

## Stopping

`Ctrl-C` (SIGINT) is the stop signal. The CLI does **not** install
a `pcntl_signal` handler, so PHP terminates with the default
exit code (typically `130`) on interrupt. The OS reclaims the TCP
socket; on the server side, the subscription is closed at the
next keep-alive timeout.

`Ctrl-Z` (SIGTSTP) suspends; resume with `fg`. Tight polling
intervals tolerate suspension poorly — values may bunch up on
resume.

## When the connection drops mid-watch

The CLI does not automatically reconnect. If the server restarts
or the network blip lasts longer than the read / publish timeout,
the underlying client throws and the command exits non-zero.

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

- **Confusing `--interval` units.** It is **milliseconds**, not
  seconds. `--interval=1` polls 1000 times per second, not once.
- **Float intervals.** The source casts `--interval` to `int`, so
  `--interval=0.25` becomes `0` (unbounded loop). Pass whole
  milliseconds.
- **Subscription rejected by the server.** Some sandbox servers
  do not implement the subscription service set. Switch to
  polling with `--interval=1000`.
- **Watching a complex structure.** The console output prints
  PHP's default representation, which is ugly for arrays / DTOs.
  Use `--json` and parse the `message` string yourself.
- **Forgetting to `Ctrl-C`.** Running with `nohup` or in `screen`
  and forgetting about it produces a multi-gigabyte log. Set a
  max line count externally if running unattended.
