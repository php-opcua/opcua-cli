---
eyebrow: 'Docs · Command · browse'
lede:    'Walk the address space at a node. One level by default; --recursive descends; --depth bounds the descent. Outputs a tree (console) or an array of references (JSON).'

see_also:
  - { href: './read.md',                      meta: '3 min' }
  - { href: './explore.md',                   meta: '4 min' }
  - { href: '../output/output-formats.md',    meta: '4 min' }

prev: { label: 'How it works', href: '../getting-started/how-it-works.md' }
next: { label: 'read',         href: './read.md' }
---

# `browse`

Walk the OPC UA address space from a given node.

## Usage

<!-- @code-block language="text" label="signature" -->
```text
opcua-cli browse <endpoint> [path|nodeId] [--recursive] [--depth=N] [global-options]
```
<!-- @endcode-block -->

| Argument          | Meaning                                                                |
| ----------------- | ---------------------------------------------------------------------- |
| `<endpoint>`      | The OPC UA server URL (`opc.tcp://host:port`). Required.               |
| `[path\|nodeId]`  | Browse path (`/Objects/Server`) or NodeId (`i=85`, `ns=2;s=Tag`). Defaults to `i=85` (the Objects folder). |

| Option            | Default | Effect                                          |
| ----------------- | ------- | ----------------------------------------------- |
| `--recursive`     | off     | Descend into children                           |
| `--depth=N`       | unbounded with `--recursive` | Limit recursion depth        |

Plus all the [global options](../reference/global-options.md) for
security, credentials, output format, debug logging.

## Examples

### One level

<!-- @code-block language="bash" label="terminal" -->
```bash
opcua-cli browse opc.tcp://plc.local:4840
# (defaults to /Objects)

opcua-cli browse opc.tcp://plc.local:4840 /Objects/Server
opcua-cli browse opc.tcp://plc.local:4840 i=85
opcua-cli browse opc.tcp://plc.local:4840 "ns=2;s=Devices/PLC1"
```
<!-- @endcode-block -->

The second argument is the browse target. Three accepted forms:

- **Path** — slash-separated browse names starting at the Root
  folder: `/Objects/Server/ServerStatus`.
- **String NodeId** — canonical OPC UA encoding:
  `ns=N;i=M` (numeric), `ns=N;s=…` (string), `ns=N;g=…` (GUID).
- **Quotes around NodeIds with semicolons** — the shell would
  otherwise interpret the `;` as a command separator.

### Recursive

<!-- @code-block language="bash" label="terminal — recursive" -->
```bash
# Full recursion (be careful — can be huge on industrial servers)
opcua-cli browse opc.tcp://plc.local:4840 /Objects --recursive

# Bounded recursion
opcua-cli browse opc.tcp://plc.local:4840 /Objects --recursive --depth=3
```
<!-- @endcode-block -->

Cap recursion with `--depth=N` for any non-trivial server.
Industrial OPC UA address spaces with tens of thousands of nodes
will produce overwhelming output and slow round-trips at full
depth.

### JSON

<!-- @code-block language="bash" label="terminal — JSON" -->
```bash
opcua-cli browse opc.tcp://plc.local:4840 /Objects --json
opcua-cli browse opc.tcp://plc.local:4840 /Objects --recursive --depth=2 --json | jq -r '.[].name'
```
<!-- @endcode-block -->

JSON output is an array of `{ name, nodeId, class }` objects, with
an optional `children` array for `--recursive` results. There is
no wrapping object with the parent's metadata — the root is the
array itself. See [Output formats](../output/output-formats.md)
for the schema.

### Securely

<!-- @code-block language="bash" label="terminal — secured" -->
```bash
opcua-cli browse opc.tcp://plc.local:4840 /Objects \
    --security-policy=Basic256Sha256 \
    --security-mode=SignAndEncrypt \
    --cert=/etc/opcua/client.pem \
    --key=/etc/opcua/client.key \
    --username=integrations \
    --password="$OPCUA_PASSWORD"
```
<!-- @endcode-block -->

See [Connecting · Security policies](../connecting/security-policies.md)
and [Credentials](../connecting/credentials.md) for the full
flow.

## What it prints

### Default (console output)

A single-level browse renders the children as a tree, one line
per child (`├──` / `└──` connectors, display name + NodeId +
class):

<!-- @code-block language="text" label="console — single level" -->
```text
├── Server (i=2253) [Object]
├── DeviceSet (ns=2;i=5001) [Object]
└── Aliases (ns=2;i=5002) [Object]
```
<!-- @endcode-block -->

A `--recursive` browse prints a deeper tree using the same
connectors:

<!-- @code-block language="text" label="console — tree" -->
```text
├── Server (i=2253) [Object]
│   ├── ServerArray (i=2254) [Variable]
│   ├── NamespaceArray (i=2255) [Variable]
│   └── ServerStatus (i=2256) [Variable]
└── DeviceSet (ns=2;i=5001) [Object]
    └── PLC1 (ns=2;i=5002) [Object]
        └── …
```
<!-- @endcode-block -->

### JSON

<!-- @code-block language="text" label="JSON — single level" -->
```text
[
  {"name": "Server", "nodeId": "i=2253", "class": "Object"},
  {"name": "DeviceSet", "nodeId": "ns=2;i=5001", "class": "Object"}
]
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="JSON — recursive" -->
```text
[
  {
    "name": "Server",
    "nodeId": "i=2253",
    "class": "Object",
    "children": [
      {"name": "ServerStatus", "nodeId": "i=2256", "class": "Variable"}
    ]
  },
  {"name": "DeviceSet", "nodeId": "ns=2;i=5001", "class": "Object"}
]
```
<!-- @endcode-block -->

The `--recursive` output is just the same flat array, nested via
`children` — there is **no** wrapping object carrying the parent
node's metadata. Field names are `name` / `nodeId` / `class`
(PascalCase / camelCase as shown), not `displayName` /
`nodeClass`.

## How it maps to the library

Under the covers:

| You ran                                          | The CLI calls                                    |
| ------------------------------------------------ | ------------------------------------------------ |
| `opcua-cli browse <endpoint> [/path]`            | `$client->resolveNodeId($path)` then `$client->browseAll()` |
| `opcua-cli browse <endpoint> <nodeId>`           | `$client->browseAll(NodeId::parse(...))`         |
| with `--recursive`                                | `$client->browseRecursive(...)`                  |
| with `--recursive --depth=N`                      | `$client->browseRecursive(..., maxDepth: N)`     |

See [`opcua-client` browsing reference](https://github.com/php-opcua/opcua-client/blob/master/docs/operations/browsing.md)
for the underlying semantics.

## Common pitfalls

- **Unquoted NodeIds.** `ns=2;i=1000` contains a `;` — your shell
  splits it. Quote: `"ns=2;i=1000"`.
- **Path with spaces.** Browse names can contain spaces. Quote
  the whole path: `"/Objects/PLC 1/Status"`.
- **Server's namespace 2 ≠ your assumption.** Default `--depth`
  unbounded against a 50 000-node server prints 50 000 lines.
  Always bound for unknown servers.
- **No matching child for a path component.** Returns
  `BadNoMatch`. The CLI surfaces this as a stderr error; the exit
  code is non-zero.
