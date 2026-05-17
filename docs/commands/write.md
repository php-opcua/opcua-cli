---
eyebrow: 'Docs · Command · write'
lede:    'Write a value to a node. Auto-detects the OPC UA type from the node''s DataType; --type overrides. Exits 0 only when the server returns Good.'

see_also:
  - { href: './read.md',                       meta: '3 min' }
  - { href: '../connecting/credentials.md',    meta: '5 min' }
  - { href: '../reference/exit-codes.md',      meta: '3 min' }

prev: { label: 'read',      href: './read.md' }
next: { label: 'endpoints', href: './endpoints.md' }
---

# `write`

Write a single value to a single node.

## Usage

<!-- @code-block language="text" label="signature" -->
```text
opcua-cli write <endpoint> <nodeId> <value> [--type=TYPE] [global-options]
```
<!-- @endcode-block -->

| Argument          | Meaning                                                                 |
| ----------------- | ----------------------------------------------------------------------- |
| `<endpoint>`      | OPC UA server URL. Required.                                            |
| `<nodeId>`        | The target node. NodeId or browse path. Required.                       |
| `<value>`         | The value to write. Required. See type coercion below.                  |

| Option         | Default      | Effect                                                |
| -------------- | ------------ | ----------------------------------------------------- |
| `--type=TYPE`  | auto-detect  | Force the OPC UA `BuiltinType` for the value          |

Plus the [global options](../reference/global-options.md).

## Examples

### Auto-detected type

<!-- @code-block language="bash" label="terminal — auto-detect" -->
```bash
opcua-cli write opc.tcp://plc.local:4840 "ns=2;s=PLC/Setpoint" 42.5
opcua-cli write opc.tcp://plc.local:4840 "ns=2;s=PLC/Mode" 1
opcua-cli write opc.tcp://plc.local:4840 "ns=2;s=PLC/Name" "Conveyor1"
opcua-cli write opc.tcp://plc.local:4840 "ns=2;s=PLC/Enabled" true
```
<!-- @endcode-block -->

Without `--type`, the CLI reads the node's `DataType` attribute,
maps it to a PHP type, and coerces your shell string to match.

| You typed   | Node's DataType   | Encoded as                            |
| ----------- | ----------------- | ------------------------------------- |
| `42.5`      | `Double`          | `Double` 42.5                         |
| `42`        | `Int32`           | `Int32` 42                            |
| `42`        | `Double`          | `Double` 42.0 (auto-cast)             |
| `true`      | `Boolean`         | `Boolean` true                        |
| `1`         | `Boolean`         | `Boolean` true (auto-cast)            |
| `"text"`    | `String`          | `String` `"text"`                      |

The auto-detect costs one extra round-trip (to read the DataType
attribute) the first time the node is written. Subsequent writes
to the same node skip the read.

### Explicit type

<!-- @code-block language="bash" label="terminal — explicit type" -->
```bash
opcua-cli write opc.tcp://plc.local:4840 "ns=2;s=PLC/Setpoint" 42.5 --type=Float
opcua-cli write opc.tcp://plc.local:4840 "ns=2;s=PLC/Count"    42   --type=UInt32
opcua-cli write opc.tcp://plc.local:4840 "ns=2;s=PLC/Name"     "Conveyor1" --type=String
```
<!-- @endcode-block -->

The CLI's `TYPE_MAP` recognises these twelve names — anything else
returns `"Unknown type 'X'. Valid types: …"` and exits non-zero:

`Boolean`, `SByte`, `Byte`, `Int16`, `UInt16`, `Int32`, `UInt32`,
`Int64`, `UInt64`, `Float`, `Double`, `String`.

`ByteString`, `DateTime`, `Guid`, `NodeId`, `XmlElement` and other
OPC UA built-in types are **not** in the lookup table — to write
them, drop down to `opcua-client` directly.

### JSON output

<!-- @code-block language="bash" label="terminal — JSON" -->
```bash
opcua-cli write opc.tcp://plc.local:4840 "ns=2;s=PLC/Setpoint" 42.5 --json
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="JSON output" -->
```text
{
  "NodeId": "ns=2;s=PLC/Setpoint",
  "Value": "42.5",
  "Type": "Double",
  "Status": "Good (0x00000000)"
}
```
<!-- @endcode-block -->

Four fields, all strings, PascalCase keys (`JsonOutput` is a thin
`json_encode` wrapper — see [Output formats](../output/output-formats.md)).
There is no separate `statusCode` integer or `statusName` —
`Status` is a combined string `"<Name> (0x<hex>)"`.

For scripting, branch on `.Status` directly:

<!-- @code-block language="bash" label="bash — branch on status" -->
```bash
status=$(opcua-cli write opc.tcp://plc.local:4840 "ns=2;s=PLC/Setpoint" 42.5 --json | jq -r .Status)
case "$status" in
    Good*)              echo "write OK" ;;
    Bad*)               echo "write failed: $status" ;;
    *)                  echo "unexpected: $status" ;;
esac
```
<!-- @endcode-block -->

Or just rely on the exit code — `0` only when the status is `Good`.

### Writing an array

<!-- @code-block language="bash" label="terminal — array" -->
```bash
opcua-cli write opc.tcp://plc.local:4840 "ns=2;s=Setpoints" "[10.0, 20.0, 30.0]"
```
<!-- @endcode-block -->

Quote the JSON-style array. The CLI parses it into a PHP array
before passing to the library; the library encodes it as a
`Variant<Double[]>` if the node's DataType is `Double`.

For multi-dimensional or structured-type writes, drop down to the
library — the CLI's shell-string interface cannot express them
unambiguously.

### Securely

<!-- @code-block language="bash" label="terminal — secured" -->
```bash
opcua-cli write opc.tcp://plc.local:4840 "ns=2;s=PLC/Setpoint" 42.5 \
    --security-policy=Basic256Sha256 \
    --security-mode=SignAndEncrypt \
    --cert=/etc/opcua/client.pem \
    --key=/etc/opcua/client.key \
    --username=operator \
    --password="$OPCUA_PASSWORD"
```
<!-- @endcode-block -->

Most production servers do not accept anonymous writes — supply
credentials or an X.509 user certificate. See
[Credentials](../connecting/credentials.md).

## What the exit code means

| Exit code | Meaning                                                    |
| --------- | ---------------------------------------------------------- |
| `0`       | Server returned Good                                       |
| `1`       | Server returned a bad status, or transport failed          |

The exit code reflects the OPC UA server's response to the write
— not the local validity of the value. A type-mismatched value
that the auto-detect coerces incorrectly still exits `1` from
the server's `BadTypeMismatch`.

## How it maps to the library

| You ran                                                          | The CLI calls                                                       |
| ---------------------------------------------------------------- | ------------------------------------------------------------------- |
| `opcua-cli write <endpoint> <nodeId> <value>`                    | `$client->write($nodeId, $coercedValue)`                            |
| `opcua-cli write <endpoint> <nodeId> <value> --type=Int32`       | `$client->write($nodeId, $value, BuiltinType::Int32)`               |

See [`opcua-client` — writing values](https://github.com/php-opcua/opcua-client/blob/master/docs/operations/writing-values.md).

## Common bad statuses

| StatusCode                 | What it means                                            |
| -------------------------- | -------------------------------------------------------- |
| `BadNodeIdUnknown`         | The node does not exist on this server                   |
| `BadUserAccessDenied`      | The session is not authorised to write this node         |
| `BadTypeMismatch`          | The value's type does not match the node's `DataType`    |
| `BadOutOfRange`            | The value is outside the engineering range               |
| `BadWriteNotSupported`     | The attribute / node is read-only                        |

Catch these in scripts via `--json | jq -r .Status` (matches on
the combined `"BadXxx (0x...)"` string) or by inspecting the exit
code + stderr.

## Common pitfalls

- **Shell-escaping booleans** — `true` works, `True` does not.
  Match PHP's `filter_var(..., FILTER_VALIDATE_BOOL)` semantics.
- **Writing a string that parses as a number** — auto-detect
  reads the node's DataType. If the node is `String` but you
  wrote `42`, it works. If the node is `Int32` but you wrote
  `"42"`, also works (auto-detect uses the DataType, not your
  string). Be explicit with `--type` in scripts.
- **Forgetting to quote the NodeId** — the `;` in `ns=N;…` is a
  shell separator.
