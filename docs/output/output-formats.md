---
eyebrow: 'Docs · Output'
lede:    'Two output backends: ConsoleOutput (default, ANSI record blocks and trees) and JsonOutput (--json, thin json_encode wrapper). Pick by intent — humans get console, scripts get JSON.'

see_also:
  - { href: './debug-logging.md',                       meta: '4 min' }
  - { href: '../commands/read.md',                      meta: '3 min' }
  - { href: '../recipes/inventory-with-dump-and-grep.md', meta: '4 min' }

prev: { label: 'Trust store workflow', href: '../connecting/trust-store-workflow.md' }
next: { label: 'Debug logging',        href: './debug-logging.md' }
---

# Output formats

Two output backends. Every command picks one based on whether
`--json` was passed.

| Backend          | Trigger      | Best for                                        |
| ---------------- | ------------ | ----------------------------------------------- |
| `ConsoleOutput`  | default      | Humans on a terminal — record blocks, trees, colours |
| `JsonOutput`     | `--json`     | Scripts, CI, log shipping — `json_encode` pass-through |

## Console output

The default. Designed to be readable at a glance. The backend has
five primitives:

- **`data(array)`** — single record, one `key: value` line per
  field, used by `read`/`write`/`trust`.
- **`table(array)`** — multiple records, one block per row, blank
  line between rows. Used by `endpoints`, `trust:list`.
- **`tree(array)`** — recursive ASCII tree, used by `browse`.
- **`writeln(string)`** — plain text line, used by `watch`,
  `dump:nodeset` progress lines, `trust:remove`.
- **`error(string)`** — red text on **stderr**.

### Record block (`data`)

A `read` prints a record block:

<!-- @code-block language="text" label="console — read" -->
```text
NodeId:    ns=2;s=PLC/Speed
Attribute: Value
Value:     42.5
Type:      Double
Status:    Good (0x00000000)
Source:    2026-05-15T10:30:00+00:00
Server:    2026-05-15T10:30:00+00:00
```
<!-- @endcode-block -->

Keys are padded to a common width and colored cyan; values follow.
There is no "header row + data rows" layout — each command always
emits the field names alongside the values.

### Multi-row table (`table`)

An `endpoints` invocation prints one record block per endpoint,
separated by blank lines:

<!-- @code-block language="text" label="console — endpoints" -->
```text

Endpoint: opc.tcp://plc.local:4840
Security: None (mode: None)
Auth:     Anonymous

Endpoint: opc.tcp://plc.local:4840
Security: Basic256Sha256 (mode: SignAndEncrypt)
Auth:     Anonymous, Username, Certificate
```
<!-- @endcode-block -->

Same shape as `data` but repeated. There is no aligned-column
layout (no `URL  Security  Mode  Auth` header) — the backend
prints field-by-field.

### Tree (`tree`)

A `browse --recursive` prints a tree of `name (nodeId) [class]`:

<!-- @code-block language="text" label="console — tree" -->
```text
├── Server (i=2253) [Object]
│   ├── ServerStatus (ns=0;i=2256) [Variable]
│   └── NamespaceArray (ns=0;i=2255) [Variable]
└── DeviceSet (ns=2;i=5001) [Object]
    └── PLC1 (ns=2;i=5002) [Object]
        ├── Speed (ns=2;i=5003) [Variable]
        └── Mode (ns=2;i=5004) [Variable]
```
<!-- @endcode-block -->

Each line shows the display name, the canonical NodeId in
parentheses, and the node class in brackets. The tree connectors
(`├──`, `└──`, `│`) are always emitted; colors are applied when
the output is a TTY.

### Colours

When stdout is a terminal (and `NO_COLOR` is unset), ANSI
sequences highlight keys and tree branches. Piped to another
command, the CLI detects the non-TTY and emits plain text.

`NO_COLOR=1` disables colour. There is no `FORCE_COLOR` override;
to force colour through a pipe, allocate a PTY (`script`,
`unbuffer`).

## JSON output

With `--json`, every command emits structured JSON. The backend
is intentionally thin — `JsonOutput` calls `json_encode` on the
same PHP arrays the console formatter would render, with **no**
key rewriting and **no** wrapping envelope. The schema therefore
matches the PascalCase keys baked into each command.

### Browse — single level

`BrowseCommand` builds `[{name, nodeId, class}, ...]` and passes it
to `tree()`. `JsonOutput::tree` emits it verbatim:

<!-- @code-block language="text" label="JSON — browse" -->
```text
[
  {"name": "Server", "nodeId": "i=2253", "class": "Object"},
  {"name": "DeviceSet", "nodeId": "ns=2;i=5001", "class": "Object"}
]
```
<!-- @endcode-block -->

### Browse — recursive

For `--recursive`, the same array is nested; each node grows a
`children` key when it has descendants. There is **no** top-level
wrapping object with the parent's metadata:

<!-- @code-block language="text" label="JSON — browse recursive" -->
```text
[
  {
    "name": "Server",
    "nodeId": "i=2253",
    "class": "Object",
    "children": [
      {"name": "ServerStatus", "nodeId": "ns=0;i=2256", "class": "Variable"}
    ]
  },
  {"name": "DeviceSet", "nodeId": "ns=2;i=5001", "class": "Object"}
]
```
<!-- @endcode-block -->

### Read

<!-- @code-block language="text" label="JSON — read" -->
```text
{
  "NodeId": "ns=2;s=PLC/Speed",
  "Attribute": "Value",
  "Value": "42.5",
  "Type": "Double",
  "Status": "Good (0x00000000)",
  "Source": "2026-05-15T10:30:00+00:00",
  "Server": "2026-05-15T10:30:00+00:00"
}
```
<!-- @endcode-block -->

| Field        | Meaning                                                                                                  |
| ------------ | -------------------------------------------------------------------------------------------------------- |
| `NodeId`     | The NodeId you passed (string)                                                                            |
| `Attribute`  | The `--attribute` name resolved on the CLI side                                                           |
| `Value`      | Stringified value — scalars become their string form; arrays/objects become JSON; booleans → `"true"`/`"false"` |
| `Type`       | The `BuiltinType` **name** (e.g. `"Double"`, `"String"`), not the numeric ID                              |
| `Status`     | Combined status string `"<Name> (0x<hex>)"`, never a bare integer                                          |
| `Source`     | `sourceTimestamp` formatted with `DateTimeInterface::format('c')`, or `"N/A"`                              |
| `Server`     | `serverTimestamp` formatted likewise                                                                       |

### Write

<!-- @code-block language="text" label="JSON — write" -->
```text
{
  "NodeId": "ns=2;s=PLC/Setpoint",
  "Value": "42.5",
  "Type": "Double",
  "Status": "Good (0x00000000)"
}
```
<!-- @endcode-block -->

Four fields, all strings. There is no separate `statusCode` /
`statusName` integer — the human-readable `Status` string carries
both. `Type` is the `BuiltinType` name (or `"Auto-detected"` when
no `--type` was supplied and the cached read result is unavailable).

### Endpoints

`EndpointsCommand` builds an array of `{Endpoint, Security, Auth}`
rows and passes it to `table()`. `JsonOutput::table` emits the
array verbatim — **no** wrapping `endpoints` key:

<!-- @code-block language="text" label="JSON — endpoints" -->
```text
[
  {
    "Endpoint": "opc.tcp://plc.local:4840",
    "Security": "None (mode: None)",
    "Auth": "Anonymous"
  },
  {
    "Endpoint": "opc.tcp://plc.local:4840",
    "Security": "Basic256Sha256 (mode: SignAndEncrypt)",
    "Auth": "Anonymous, Username, Certificate"
  }
]
```
<!-- @endcode-block -->

`Security` is one combined string in the form
`"<policyName> (mode: <modeName>)"`. There are no separate
`securityPolicyUri`, `securityLevel`, or `userIdentityTokens`
fields — only the `Auth` summary string with a comma-separated list
of token type names.

To filter the `Basic256Sha256 + SignAndEncrypt` rows with `jq`:

<!-- @code-block language="bash" label="bash — jq filter" -->
```bash
opcua-cli endpoints opc.tcp://plc.local:4840 --json \
    | jq -r '.[] | select(.Security == "Basic256Sha256 (mode: SignAndEncrypt)") | .Endpoint'
```
<!-- @endcode-block -->

### Watch

NDJSON — one JSON object per change notification (or per poll
tick). The backend wraps the same plain-text line the console
mode prints in `{"message": "<line>"}`:

<!-- @code-block language="text" label="JSON — watch" -->
```text
{"message":"[10:30:00.123] 42.5"}
{"message":"[10:30:01.487] 42.7"}
```
<!-- @endcode-block -->

There is no separate `value`, `statusCode`, or structured
`timestamp` field. Parse the string inside `.message` to extract
either. See [`watch`](../commands/watch.md) for details.

### Trust list

`TrustListCommand` builds an array of `{Fingerprint, Subject, Expires}`
rows. `JsonOutput::table` emits it verbatim:

<!-- @code-block language="text" label="JSON — trust:list" -->
```text
[
  {
    "Fingerprint": "a1:b2:c3:d4:...",
    "Subject": "CN=PLC-1, O=ACME",
    "Expires": "2027-01-01T00:00:00+00:00"
  }
]
```
<!-- @endcode-block -->

- **`Fingerprint`** — SHA-1 hex pairs joined by `:` (the same
  format the CLI prints from `trust`).
- **`Subject`** — the X.509 subject CN, or `"Unknown"`.
- **`Expires`** — `notAfter` formatted with
  `DateTimeInterface::format('c')` (ISO 8601 datetime with
  timezone), or `"N/A"`.

There is no wrapping `trustedCertificates` envelope.

### Trust

`trust` emits a single record (`data()`):

<!-- @code-block language="text" label="JSON — trust" -->
```text
{
  "Status": "Trusted",
  "Fingerprint": "a1:b2:c3:d4:...",
  "Subject": "CN=PLC-Server, O=ACME",
  "Expires": "2027-01-01T00:00:00+00:00"
}
```
<!-- @endcode-block -->

### Errors

When a command fails, the error is emitted as JSON on **stderr**
(not stdout), with a single `error` key — no additional fields:

<!-- @code-block language="text" label="JSON — error" -->
```text
{"error":"Server certificate not trusted."}
```
<!-- @endcode-block -->

The fingerprint of the offending certificate is sent on the next
`error()` call:

<!-- @code-block language="text" label="JSON — error follow-up" -->
```text
{"error":"  Fingerprint: a1:b2:c3:d4:..."}
```
<!-- @endcode-block -->

Each `error()` call produces one `{"error":"..."}` line. The CLI
never serialises structured error metadata (`fingerprint`,
`statusCode`, `statusName`) as keys alongside `error` — that is
purely the human message.

### Writeln in JSON mode

When a command calls `writeln()` (progress lines, `trust:remove`,
empty-result notices), `JsonOutput::writeln` wraps it as
`{"message": "<text>"}`:

<!-- @code-block language="text" label="JSON — writeln" -->
```text
{"message":"Removed certificate: a1:b2:c3:d4:..."}
```
<!-- @endcode-block -->

This is why `watch` NDJSON is `{"message": "[10:30:00.123] 42.5"}`
rather than a structured object.

## When to use which

| Context                                | Format     |
| -------------------------------------- | ---------- |
| Operator at a terminal                 | Console    |
| Shell pipeline (`opcua-cli read … \| awk …`) | JSON (`\| jq`) |
| CI smoke test                          | Either; JSON parses cleaner |
| Log shipping                           | JSON       |
| Demoing to a non-technical audience    | Console    |

A reasonable script pattern:

<!-- @code-block language="bash" label="bash — pipe to jq" -->
```bash
opcua-cli read opc.tcp://plc.local:4840 i=2261 --json | jq -r .Value
# → open62541 OPC UA Server
```
<!-- @endcode-block -->

`jq -r` strips quotes; the result is the raw string suitable for
further shell processing. Note the PascalCase `.Value`.

## Combining `--json` with `--debug`

These two flags conflict — `--debug` adds log lines to stdout,
which would corrupt the JSON. The CLI rejects the combination
with an explicit error.

Use `--debug-stderr` or `--debug-file` instead:

<!-- @code-block language="bash" label="bash — debug + json" -->
```bash
opcua-cli read opc.tcp://plc.local:4840 i=2261 --json --debug-stderr 2>debug.log
```
<!-- @endcode-block -->

Stdout is clean JSON; stderr captures debug detail. See
[Debug logging](./debug-logging.md).

## What the JSON output is *not*

The JSON output is a thin `json_encode` of the same PHP arrays
the console formatter would render. That means:

- **No key rewriting.** Field names are PascalCase
  (`NodeId`, `Status`, `Fingerprint`) — they are not the camelCase
  names from the OPC UA specification.
- **No wrapping envelope.** Multi-row commands return a JSON
  array, not an object with a `data` / `endpoints` /
  `trustedCertificates` key.
- **No separate numeric and named status fields.** `Status` is
  always a combined string `"<Name> (0x<hex>)"`.
- **No nested DataValue.** `read` returns the formatted
  `Value`/`Type`/`Status`/`Source`/`Server` flat fields, not a
  nested OPC UA `DataValue` structure.

Scripts that need a different shape should re-shape after `jq`,
or embed `opcua-client` directly for typed access.
