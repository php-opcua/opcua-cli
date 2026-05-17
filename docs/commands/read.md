---
eyebrow: 'Docs · Command · read'
lede:    'Read one attribute of one node. Defaults to Value; --attribute reads any of the standard OPC UA attributes. Prints scalar value (console) or full DataValue (JSON).'

see_also:
  - { href: './write.md',                      meta: '3 min' }
  - { href: './watch.md',                      meta: '3 min' }
  - { href: '../output/output-formats.md',     meta: '4 min' }

prev: { label: 'browse', href: './browse.md' }
next: { label: 'write',  href: './write.md' }
---

# `read`

Read a single attribute of a single node.

## Usage

<!-- @code-block language="text" label="signature" -->
```text
opcua-cli read <endpoint> <nodeId> [--attribute=Value] [global-options]
```
<!-- @endcode-block -->

| Argument          | Meaning                                                                |
| ----------------- | ---------------------------------------------------------------------- |
| `<endpoint>`      | The OPC UA server URL. Required.                                       |
| `<nodeId>`        | The node to read. Accepts string NodeId (`ns=N;…`) or browse path (`/Objects/…`). Required. |

| Option              | Default | Effect                                            |
| ------------------- | ------- | ------------------------------------------------- |
| `--attribute=<id>`  | `Value` | Which attribute to read. Accepts a name from the table below; unrecognised values silently fall back to `Value`. |

Plus all the [global options](../reference/global-options.md).

## Examples

### The default — Value

<!-- @code-block language="bash" label="terminal — read value" -->
```bash
opcua-cli read opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed"
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="console output" -->
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

The console output is a record block — every field (NodeId,
Attribute, Value, Type, Status, Source/Server timestamps) on its
own line. To get just the unwrapped value, use `--json` and pipe
to `jq -r .Value`.

### Different attribute

<!-- @code-block language="bash" label="terminal — read other attributes" -->
```bash
opcua-cli read opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed" --attribute=DisplayName
opcua-cli read opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed" --attribute=DataType
opcua-cli read opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed" --attribute=NodeClass
```
<!-- @endcode-block -->

Recognised attribute names (the eight the CLI's lookup table
covers):

| Name          | OPC UA attribute                                |
| ------------- | ----------------------------------------------- |
| `NodeId`      | The node's own NodeId                            |
| `NodeClass`   | `Object`, `Variable`, `Method`, …                |
| `BrowseName`  | Qualified name used for path resolution          |
| `DisplayName` | Localised display name                            |
| `Description` | Localised description                             |
| `Value`       | The value attribute (default)                    |
| `DataType`    | NodeId of the value's DataType                    |
| `AccessLevel` | Bitmask of allowed access (read / write / …)     |

Other OPC UA attribute names (`WriteMask`, `UserAccessLevel`,
`MinimumSamplingInterval`, `Historizing`, `Executable`,
`IsAbstract`, `Symmetric`, `InverseName`, `ContainsNoLoops`,
`EventNotifier`, `ValueRank`, `ArrayDimensions`, …) are **not**
in the CLI's lookup table — passing them silently falls back to
reading `Value`. Numeric attribute IDs are not parsed either; for
those attributes, drop down to the library directly.

### JSON output — full DataValue

<!-- @code-block language="bash" label="terminal — JSON" -->
```bash
opcua-cli read opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed" --json
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="JSON output" -->
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

The JSON output is the same record the console mode prints,
serialised verbatim with `json_encode` — PascalCase keys, all
fields stringified:

| Field         | Meaning                                                                                  |
| ------------- | ---------------------------------------------------------------------------------------- |
| `NodeId`      | The NodeId you passed                                                                    |
| `Attribute`   | The `--attribute` you requested (`Value` by default)                                     |
| `Value`       | Stringified value (scalars → string, arrays → JSON, booleans → `"true"`/`"false"`)        |
| `Type`        | The `BuiltinType` **name** (`"Double"`, `"String"`, …), never the numeric ID              |
| `Status`      | Combined status string `"<Name> (0x<hex>)"`, e.g. `"Good (0x00000000)"`                   |
| `Source`      | `sourceTimestamp` formatted with `format('c')`, or `"N/A"`                                |
| `Server`      | `serverTimestamp` formatted likewise                                                       |

See [Output formats](../output/output-formats.md) for the
complete JSON schema.

### Browse path instead of NodeId

<!-- @code-block language="bash" label="terminal — by path" -->
```bash
opcua-cli read opc.tcp://plc.local:4840 /Objects/Server/ServerStatus
```
<!-- @endcode-block -->

The CLI dispatches on the same `NodeId|string` rule as the
library: a leading `/` triggers path resolution, otherwise it
parses as a NodeId. See [Endpoint URLs](../connecting/endpoint-urls.md).

## What the exit code means

| Exit code | Meaning                                                    |
| --------- | ---------------------------------------------------------- |
| `0`       | Read succeeded with a Good status                          |
| `1`       | Read failed (transport error, OPC UA bad status, etc.)     |

For per-item bad statuses (the `Status` field starting with `Bad`
on a read whose call itself succeeded), the CLI still exits `1`
— the read was unsuccessful from the user's perspective. See
[Exit codes](../reference/exit-codes.md).

## How it maps to the library

| You ran                                                       | The CLI calls                                          |
| ------------------------------------------------------------- | ------------------------------------------------------ |
| `opcua-cli read <endpoint> <nodeId>`                          | `$client->read($nodeId)`                              |
| `opcua-cli read <endpoint> <nodeId> --attribute=DisplayName`  | `$client->read($nodeId, AttributeId::DisplayName)`    |

See [`opcua-client` — reading attributes](https://github.com/php-opcua/opcua-client/blob/master/docs/operations/reading-attributes.md).

## Common patterns

- **Read the product name** — `opcua-cli read <endpoint> i=2261`.
  Every server publishes this; useful for the cheapest possible
  health probe ([CI smoke test](../recipes/ci-smoke-test.md)).
- **Read DisplayName before Value** — verify the node is what you
  think it is. Cheap pre-check.
- **`--json | jq -r .Value`** — extract just the value for shell
  pipelines. Note the PascalCase `Value`.

## Common pitfalls

- **No quotes on the NodeId** — `ns=2;s=…` includes a semicolon
  the shell treats as a command separator.
- **Reading the Value of a non-Variable node** — for `Object` or
  `Method` nodes, `Value` is undefined; the server returns
  `BadAttributeIdInvalid`. Read `DisplayName` or `NodeClass`
  instead.
- **Reading from a server that requires auth** — without
  `--username` / `--cert`, the read fails with
  `BadUserAccessDenied`. Provide credentials.
