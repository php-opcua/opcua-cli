---
eyebrow: 'Docs · Output'
lede:    'Two output backends: ConsoleOutput (default, ANSI tables and trees) and JsonOutput (--json, structured JSON). Pick by intent — humans get console, scripts get JSON.'

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
| `ConsoleOutput`  | default      | Humans on a terminal — tables, trees, colours    |
| `JsonOutput`     | `--json`     | Scripts, CI, log shipping — machine-readable    |

## Console output

The default. Designed to be readable at a glance.

Three primitives:

- **Tables** — for tabular data (browse children, endpoint
  catalogue, trust list)
- **Trees** — for hierarchical data (recursive browse)
- **Single values** — for `read`-like commands

A `browse` looks like a table:

<!-- @code-block language="text" label="console — table" -->
```text
DisplayName              NodeId                        NodeClass
Server                   i=2253                        Object
DeviceSet                ns=2;i=5001                   Object
```
<!-- @endcode-block -->

A `browse --recursive` looks like a tree:

<!-- @code-block language="text" label="console — tree" -->
```text
Objects
├── Server
│   ├── ServerStatus
│   └── NamespaceArray
└── DeviceSet
    └── PLC1
        ├── Speed
        └── Mode
```
<!-- @endcode-block -->

A `read` prints just the value:

<!-- @code-block language="text" label="console — value" -->
```text
42.5
```
<!-- @endcode-block -->

### Colours

When stdout is a terminal (and `NO_COLOR` is unset), ANSI
sequences highlight headers and tree branches. Piped to another
command, the CLI detects the non-TTY and emits plain text.

To force colour on through a pipe (e.g. `| less -R`), set
`FORCE_COLOR=1`. To disable, set `NO_COLOR=1`. Standard
conventions.

## JSON output

With `--json`, every command emits structured JSON. The schema
varies by command but follows a consistent shape.

### Browse — single level

<!-- @code-block language="text" label="JSON — browse" -->
```text
[
  {"displayName": "Server", "nodeId": "i=2253", "nodeClass": "Object"},
  {"displayName": "DeviceSet", "nodeId": "ns=2;i=5001", "nodeClass": "Object"}
]
```
<!-- @endcode-block -->

### Browse — recursive

<!-- @code-block language="text" label="JSON — browse recursive" -->
```text
{
  "nodeId": "i=85",
  "displayName": "Objects",
  "nodeClass": "Object",
  "children": [
    {
      "nodeId": "i=2253",
      "displayName": "Server",
      "nodeClass": "Object",
      "children": [...]
    }
  ]
}
```
<!-- @endcode-block -->

### Read

<!-- @code-block language="text" label="JSON — read" -->
```text
{
  "value": 42.5,
  "type": 11,
  "statusCode": 0,
  "sourceTimestamp": "2026-05-15T10:30:00.000000+00:00",
  "serverTimestamp": "2026-05-15T10:30:00.123456+00:00"
}
```
<!-- @endcode-block -->

### Write

<!-- @code-block language="text" label="JSON — write" -->
```text
{
  "nodeId": "ns=2;s=PLC/Setpoint",
  "value": 42.5,
  "type": "Double",
  "statusCode": 0,
  "statusName": "Good"
}
```
<!-- @endcode-block -->

### Endpoints

<!-- @code-block language="text" label="JSON — endpoints" -->
```text
{
  "endpoints": [
    {
      "endpointUrl": "opc.tcp://plc.local:4840",
      "securityPolicy": "Basic256Sha256",
      "securityPolicyUri": "http://opcfoundation.org/UA/SecurityPolicy#Basic256Sha256",
      "securityMode": "SignAndEncrypt",
      "securityLevel": 50,
      "userIdentityTokens": [
        {"policyId": "anonymous", "tokenType": "Anonymous"}
      ]
    }
  ]
}
```
<!-- @endcode-block -->

### Watch

NDJSON — one JSON object per poll, separated by `\n`:

<!-- @code-block language="text" label="JSON — watch" -->
```text
{"timestamp":"2026-05-15T10:30:00+00:00","value":42.5,"statusCode":0}
{"timestamp":"2026-05-15T10:30:01+00:00","value":42.7,"statusCode":0}
```
<!-- @endcode-block -->

### Trust list

<!-- @code-block language="text" label="JSON — trust:list" -->
```text
{
  "trustedCertificates": [
    {"fingerprint": "a1b2c3d4...", "subject": "CN=PLC-1, O=ACME", "validUntil": "2027-01-01"}
  ]
}
```
<!-- @endcode-block -->

### Errors

When a command fails with `--json` set, the error is also JSON:

<!-- @code-block language="text" label="JSON — error" -->
```text
{"error": "Server certificate not trusted.", "fingerprint": "a1b2c3..."}
```
<!-- @endcode-block -->

Field names depend on the error type — `error`, `fingerprint`,
`statusCode`, `statusName` as applicable.

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
opcua-cli read opc.tcp://plc.local:4840 i=2261 --json | jq -r .value
# → "open62541 OPC UA Server"
```
<!-- @endcode-block -->

`jq -r` strips quotes; the result is the raw string suitable
for further shell processing.

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

## What the JSON schema does *not* guarantee

- **Field order.** JSON is unordered. Don't rely on key order
  when parsing.
- **Extra fields.** Future versions may add fields. Tolerate
  unknown keys in your parser.
- **Removed fields.** Within a major version, fields are stable.
  Across major versions, expect potential renames — see the
  CHANGELOG.

For a stable contract, restrict yourself to the fields shown in
this page; ignore unknown ones; check the CHANGELOG on each
upgrade.
