---
eyebrow: 'Docs · Recipes'
lede:    'Long-running observation of one or many nodes. watch + shell + log rotation = a "good-enough" monitoring rig built on the CLI''s built-in subscription mode (or its polling fallback).'

see_also:
  - { href: '../commands/watch.md',          meta: '4 min' }
  - { href: '../output/output-formats.md',   meta: '5 min' }
  - { href: 'https://github.com/php-opcua/opcua-client/blob/master/docs/operations/subscriptions.md', meta: 'external', label: 'opcua-client — subscriptions' }

prev: { label: 'Batch trust rollout',       href: './batch-trust-rollout.md' }
next: { label: 'Inventory with dump and grep', href: './inventory-with-dump-and-grep.md' }
---

# Live monitoring with watch

`opcua-cli watch` observes a single node. By default it opens a
real OPC UA subscription and prints every notification the server
pushes; pass `--interval=N` (in **milliseconds**) to switch to a
polling loop instead. Three common patterns get more out of it:
NDJSON to file with rotation, restart-on-disconnect loops, and
multi-node observation by spawning watchers in parallel.

## Pattern 1 — NDJSON to file with rotation

For a "leave this running" monitor:

<!-- @code-block language="bash" label="bash — monitor.sh" -->
```bash
#!/usr/bin/env bash
set -euo pipefail

ENDPOINT="opc.tcp://plc.local:4840"
NODE="ns=2;s=PLC/Speed"
LOGDIR="/var/log/opcua-monitor"

mkdir -p "$LOGDIR"

while true; do
    today=$(date -u +%Y%m%d)
    logfile="$LOGDIR/speed-${today}.ndjson"

    # Default: a real OPC UA subscription. Drop --interval to keep
    # subscription mode; add --interval=1000 (ms) for polling.
    opcua-cli watch "$ENDPOINT" "$NODE" --json >> "$logfile" || true

    # If watch died (connection drop, server restart), wait and retry
    sleep 5
done
```
<!-- @endcode-block -->

Daily-rotated log files, restart-on-fail loop. The `|| true` plus
`sleep 5` keep the script alive across transient OPC UA
failures.

For more sophisticated rotation, pipe through `cronolog` or
`logrotate`:

<!-- @code-block language="bash" label="bash — with cronolog" -->
```bash
opcua-cli watch "$ENDPOINT" "$NODE" --json \
    | cronolog "$LOGDIR/speed-%Y%m%d.ndjson"
```
<!-- @endcode-block -->

## Pattern 2 — alert on threshold

For "wake me up if speed drops":

<!-- @code-block language="bash" label="bash — threshold-alert.sh" -->
```bash
#!/usr/bin/env bash
ENDPOINT="opc.tcp://plc.local:4840"
NODE="ns=2;s=PLC/Speed"
THRESHOLD=10.0

# `watch --json` emits {"message":"[10:30:00.123] 42.5"} per tick.
# Extract the numeric value from the message string.
opcua-cli watch "$ENDPOINT" "$NODE" --json \
    | while IFS= read -r line; do
        msg=$(echo "$line" | jq -r .message)
        # Strip the "[HH:MM:SS.mmm] " prefix; whatever's left is the value
        value=${msg##\[*\] }

        # Skip null / non-numeric ticks
        if ! [[ "$value" =~ ^-?[0-9]+(\.[0-9]+)?$ ]]; then
            echo "Non-numeric tick: $value"
            continue
        fi

        if (( $(echo "$value < $THRESHOLD" | bc -l) )); then
            echo "ALERT: speed=$value below threshold=$THRESHOLD"
            # send to pagerduty / slack / etc.
        fi
    done
```
<!-- @endcode-block -->

`watch --json` wraps each tick in `{"message": "[HH:MM:SS.mmm] <value>"}`
— there is no separate structured `value` / `statusCode` field
(the JSON backend is a thin wrapper, see [Output
formats](../output/output-formats.md#section-watch)). Parse the
string inside `.message` to recover the value or timestamp.

## Pattern 3 — multi-node observation

`watch` handles one node at a time. For several nodes in
parallel, spawn watchers as background jobs:

<!-- @code-block language="bash" label="bash — multi-watch.sh" -->
```bash
#!/usr/bin/env bash
ENDPOINT="opc.tcp://plc.local:4840"

NODES=(
    "ns=2;s=PLC/Speed"
    "ns=2;s=PLC/Mode"
    "ns=2;s=PLC/Health"
)

PIDS=()
for node in "${NODES[@]}"; do
    safe_name=$(echo "$node" | tr -c '[:alnum:]' '_')
    logfile="/var/log/opcua-monitor/${safe_name}.ndjson"

    opcua-cli watch "$ENDPOINT" "$node" --json >> "$logfile" &
    PIDS+=($!)
done

# Wait for any to exit (or trap Ctrl-C to clean up)
trap 'kill ${PIDS[@]} 2>/dev/null; wait; exit' INT TERM
wait
```
<!-- @endcode-block -->

Each node gets its own watch process, its own session, and its
own log file. Even though each invocation opens a real
subscription, you still end up with **N sessions** on the server
— most servers cap sessions at 100 or fewer. For dozens of
nodes, switch to a single-process subscription with N monitored
items via
[`opcua-client`](https://github.com/php-opcua/opcua-client/blob/master/docs/operations/subscriptions.md)
or `opcua-session-manager`'s auto-publish.

## Pattern 4 — feed a metrics system

Pipe to a Telegraf/Prometheus pushgateway/InfluxDB shovel:

<!-- @code-block language="bash" label="bash — to InfluxDB" -->
```bash
opcua-cli watch opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed" --json \
  | while IFS= read -r line; do
        msg=$(echo "$line" | jq -r .message)
        # msg = "[HH:MM:SS.mmm] <value>"
        value=${msg##\[*\] }

        # Use the host clock; the tick timestamp inside .message is local
        ns=$(date +%s%N)

        curl -s -XPOST "http://influxdb:8086/write?db=plant" \
            --data-binary "speed,line=A value=$value $ns"
    done
```
<!-- @endcode-block -->

The shell is the slowest part — for high-frequency monitoring
(sub-100ms ticks), this pattern bottlenecks on `jq` invocations.
Acceptable for ~1 Hz monitoring.

For lower-latency metrics, embed `opcua-client` directly and
push to your metrics system from PHP. The CLI is for ad-hoc
monitoring and prototyping.

## Pattern 5 — debug mode + watch

For diagnostic captures over time:

<!-- @code-block language="bash" label="bash — watch with debug" -->
```bash
opcua-cli watch opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed" \
    --json \
    --debug-file=/var/log/opcua-watch-debug.log \
    >> /var/log/opcua-watch-data.ndjson
```
<!-- @endcode-block -->

Two streams: data on stdout (→ data NDJSON), debug on a file.
Rotate both with logrotate.

## When `watch` is the wrong tool

- **Sub-100ms polling.** `--interval=N` only sleeps; under tight
  intervals the shell + per-tick overhead dominates. Stay in the
  default subscription mode (which is push-driven), or drop down
  to `opcua-client` for fine-grained control.
- **Many nodes.** N parallel watchers means N sessions on the
  server (one per process). A real subscription with N monitored
  items in a single session batches everything.
- **`--interval=N` capturing every change.** A polling interval
  larger than the change frequency misses changes. Use the
  default (subscription) mode, which pushes every change.
- **Production monitoring.** A long-running shell script is a
  fragile production-grade monitor. Use a proper subscription-
  consuming worker (`opcua-session-manager` auto-publish +
  Redis queue, or a PHP daemon).

For all of those, drop to the library / session manager. `watch`
is for **operator** observation and **development**.

## Robustness tips

- **Always pair `watch` with a restart loop.** OPC UA
  connections drop. Without a restart loop, your monitoring
  goes silent.
- **Rotate logs.** `watch` outputs continuously; an unrotated
  log fills the disk overnight.
- **Cap concurrency.** N parallel watchers means N sessions on
  the server — most servers cap sessions at 100 or fewer.
  Aggregate by spec, not by node.
- **Trap signals.** `Ctrl-C` should clean up child watchers,
  flush logs, close connections.

## A complete "production-grade" wrapper

<!-- @code-block language="bash" label="bash — wrapper" -->
```bash
#!/usr/bin/env bash
set -euo pipefail

ENDPOINT="${OPCUA_ENDPOINT:?required}"
NODE="${OPCUA_NODE:?required}"
# Polling mode opt-in: set OPCUA_INTERVAL_MS to a positive integer
# (in milliseconds). Leave unset to use the default subscription mode.
INTERVAL_MS="${OPCUA_INTERVAL_MS:-}"
LOGFILE="${OPCUA_LOGFILE:-/var/log/opcua-watch.ndjson}"

mkdir -p "$(dirname "$LOGFILE")"

interval_flag=()
if [ -n "$INTERVAL_MS" ]; then
    interval_flag=(--interval="$INTERVAL_MS")
fi

while true; do
    echo "$(date -u +%FT%TZ) starting watch" >&2

    opcua-cli watch "$ENDPOINT" "$NODE" \
        "${interval_flag[@]}" \
        --json \
        --timeout=5 \
      >> "$LOGFILE" \
      || echo "$(date -u +%FT%TZ) watch exited" >&2

    sleep 5
done
```
<!-- @endcode-block -->

Drop into a systemd service file (`Type=simple`, `Restart=always`)
for a "real" production monitor. Still not a proper OPC UA
subscription, but operates reliably enough for many cases.
