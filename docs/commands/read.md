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
| `--attribute=<id>`  | `Value` | Which attribute to read. Accepts name (`Value`, `DisplayName`, …) or numeric ID (`13`, `4`, …). |

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
42.5
```
<!-- @endcode-block -->

The default console output is the unwrapped value — what
`DataValue::getValue()` returns in code. Scalars print directly,
arrays as `[1, 2, 3]`, strings without quotes.

### Different attribute

<!-- @code-block language="bash" label="terminal — read other attributes" -->
```bash
opcua-cli read opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed" --attribute=DisplayName
opcua-cli read opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed" --attribute=DataType
opcua-cli read opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed" --attribute=NodeClass
```
<!-- @endcode-block -->

Recognised attribute names: `Value`, `DisplayName`, `BrowseName`,
`Description`, `DataType`, `NodeClass`, `WriteMask`,
`UserWriteMask`, `AccessLevel`, `UserAccessLevel`,
`MinimumSamplingInterval`, `Historizing`, `Executable`,
`UserExecutable`, `IsAbstract`, `Symmetric`, `InverseName`,
`ContainsNoLoops`, `EventNotifier`, `ValueRank`,
`ArrayDimensions`.

You can also pass the numeric attribute ID directly
(`--attribute=14` for DataType).

### JSON output — full DataValue

<!-- @code-block language="bash" label="terminal — JSON" -->
```bash
opcua-cli read opc.tcp://plc.local:4840 "ns=2;s=PLC/Speed" --json
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="JSON output" -->
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

The JSON form carries the full `DataValue`:

| Field             | Meaning                                                |
| ----------------- | ------------------------------------------------------ |
| `value`           | The PHP-native value (scalar, array, decoded structure) |
| `type`            | The OPC UA `BuiltinType` integer (`11` = Double, `12` = String, …) |
| `statusCode`      | `0` = Good. Non-zero indicates a per-item problem.     |
| `sourceTimestamp` | When the device sampled the value (ISO 8601, UTC)      |
| `serverTimestamp` | When the server packaged the response (ISO 8601, UTC)  |

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

For per-item bad statuses (the `statusCode` field non-zero on a
read whose call itself succeeded), the CLI still exits `1` —
the read was unsuccessful from the user's perspective. See
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
- **`--json | jq .value`** — extract just the value for shell
  pipelines.

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
