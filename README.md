<h1 align="center"><strong>OPC UA CLI</strong></h1>

<div align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="assets/logo-dark.svg">
    <source media="(prefers-color-scheme: light)" srcset="assets/logo-light.svg">
    <img alt="OPC UA CLI" src="assets/logo-light.svg" width="435">
  </picture>
</div>

<p align="center">
  <a href="https://github.com/php-opcua/opcua-cli/actions/workflows/tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/php-opcua/opcua-cli/tests.yml?branch=master&label=tests&style=flat-square" alt="Tests"></a>
  <a href="https://codecov.io/gh/php-opcua/opcua-cli"><img src="https://img.shields.io/codecov/c/github/php-opcua/opcua-cli?style=flat-square&logo=codecov" alt="Coverage"></a>
  <a href="https://packagist.org/packages/php-opcua/opcua-cli"><img src="https://img.shields.io/packagist/v/php-opcua/opcua-cli?style=flat-square&label=packagist" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/php-opcua/opcua-cli"><img src="https://img.shields.io/packagist/php-v/php-opcua/opcua-cli?style=flat-square" alt="PHP Version"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/php-opcua/opcua-cli?style=flat-square" alt="License"></a>
</p>

---

Command-line tool for OPC UA servers. Browse, read, write, watch, discover endpoints, manage certificates, and generate PHP code from NodeSet2.xml -- all from the terminal.

Built on top of [`php-opcua/opcua-client`](https://github.com/php-opcua/opcua-client), the pure PHP OPC UA client.

## Installation

```bash
composer require php-opcua/opcua-cli
```

The binary is installed at `vendor/bin/opcua-cli`.

For global installation:

```bash
composer global require php-opcua/opcua-cli
```

## Quick Start

```bash
# Discover what's available
opcua-cli endpoints opc.tcp://localhost:4840

# Browse the address space
opcua-cli browse opc.tcp://localhost:4840

# Read a value
opcua-cli read opc.tcp://localhost:4840 "i=2259"

# Write a value
opcua-cli write opc.tcp://localhost:4840 "ns=2;i=1001" 42 --type=Int32

# Watch a value in real time
opcua-cli watch opc.tcp://localhost:4840 "ns=2;i=1001"
```

## Commands

### `browse` -- Browse the address space

```bash
opcua-cli browse opc.tcp://localhost:4840
opcua-cli browse opc.tcp://localhost:4840 /Objects/MyPLC
opcua-cli browse opc.tcp://localhost:4840 "ns=2;i=1000"
opcua-cli browse opc.tcp://localhost:4840 /Objects --recursive --depth=3
opcua-cli browse opc.tcp://localhost:4840 /Objects --json
```

```
├── Server (i=2253) [Object]
├── MyPLC (ns=2;i=1000) [Object]
│   ├── Temperature (ns=2;i=1001) [Variable]
│   └── Pressure (ns=2;i=1002) [Variable]
└── DeviceSet (ns=3;i=5001) [Object]
```

| Option | Description |
|--------|-------------|
| `--recursive` | Browse recursively (tree view) |
| `--depth=N` | Maximum depth for recursive browse (default: 3) |

### `read` -- Read a node value

```bash
opcua-cli read opc.tcp://localhost:4840 "i=2259"
opcua-cli read opc.tcp://localhost:4840 "ns=2;i=1001" --attribute=DisplayName
opcua-cli read opc.tcp://localhost:4840 "ns=2;i=1001" --json
```

```
NodeId:     ns=2;i=1001
Attribute:  Value
Value:      23.5
Type:       Double
Status:     Good (0x00000000)
Source:     2026-03-24T15:30:00+00:00
Server:     2026-03-24T15:30:00+00:00
```

| Option | Description |
|--------|-------------|
| `--attribute=NAME` | Attribute to read: Value (default), DisplayName, BrowseName, DataType, NodeClass, Description, AccessLevel, NodeId |

### `write` -- Write a value to a node

```bash
opcua-cli write opc.tcp://localhost:4840 "ns=2;i=1001" 42
opcua-cli write opc.tcp://localhost:4840 "ns=2;i=1001" 42 --type=Int32
opcua-cli write opc.tcp://localhost:4840 "ns=2;i=2000" true --type=Boolean
```

| Option | Description |
|--------|-------------|
| `--type=TYPE` | Explicit OPC UA type: Boolean, SByte, Byte, Int16, UInt16, Int32, UInt32, Int64, UInt64, Float, Double, String |

### `endpoints` -- Discover server endpoints

```bash
opcua-cli endpoints opc.tcp://localhost:4840
```

```
Endpoint: opc.tcp://localhost:4840
Security: None (mode: None)
Auth:     Anonymous, UserName

Endpoint: opc.tcp://localhost:4840
Security: Basic256Sha256 (mode: SignAndEncrypt)
Auth:     Anonymous, UserName, Certificate
```

### `watch` -- Watch a value in real time

```bash
# Subscription mode (default) -- server pushes changes
opcua-cli watch opc.tcp://localhost:4840 "ns=2;i=1001"

# Polling mode -- read every 250ms
opcua-cli watch opc.tcp://localhost:4840 "ns=2;i=1001" --interval=250
```

```
[15:30:00.123] 23.5
[15:30:00.625] 23.6
[15:30:01.127] 23.4
^C
```

### `generate:nodeset` -- Generate PHP classes from NodeSet2.xml

```bash
opcua-cli generate:nodeset path/to/Opc.Ua.Di.NodeSet2.xml \
  --output=src/Generated/Di/ --namespace=App\\OpcUa\\Di
```

Generates:
- **NodeId constants** -- one class with all node IDs as string constants
- **PHP enums** -- `BackedEnum` for each OPC UA enumeration type
- **DTOs** -- readonly classes with typed properties for structured DataTypes
- **Codecs** -- `ExtensionObjectCodec` implementations for binary encoding/decoding
- **Registrar** -- batch-registers all codecs with the client

| Option | Description |
|--------|-------------|
| `--output=PATH` | Output directory (default: `./generated/`) |
| `--namespace=NS` | PHP namespace (default: `Generated\\OpcUa`) |

No server connection required -- reads the XML file locally.

### `dump:nodeset` -- Export server address space to NodeSet2.xml

```bash
opcua-cli dump:nodeset opc.tcp://192.168.1.100:4840 --output=MyPLC.NodeSet2.xml
opcua-cli dump:nodeset opc.tcp://192.168.1.100:4840 --output=MyPLC.NodeSet2.xml --namespace=2
```

| Option | Description |
|--------|-------------|
| `--output=FILE` | Output XML file path (required) |
| `--namespace=N` | Export only this namespace index (default: all non-zero) |

The exported file can be fed directly to `generate:nodeset`.

### `trust` -- Trust a server certificate

```bash
opcua-cli trust opc.tcp://server:4840 --trust-store=~/.opcua
```

### `trust:list` -- List trusted certificates

```bash
opcua-cli trust:list --trust-store=~/.opcua
```

### `trust:remove` -- Remove a trusted certificate

```bash
opcua-cli trust:remove ab:cd:12:34:... --trust-store=~/.opcua
```

| Option | Description |
|--------|-------------|
| `--trust-store=<path>` | Custom trust store path |
| `--trust-policy=<policy>` | Validation policy (fingerprint, fingerprint+expiry, full) |
| `--no-trust-policy` | Disable trust validation for this command |

## Security Options

All commands support full security configuration:

```bash
# Username/password authentication
opcua-cli read opc.tcp://server:4840 "i=2259" -u admin -p secret

# Full security with certificates
opcua-cli read opc.tcp://server:4840 "i=2259" \
  --security-policy=Basic256Sha256 \
  --security-mode=SignAndEncrypt \
  --cert=/path/to/client.pem \
  --key=/path/to/client.key \
  --ca=/path/to/ca.pem \
  -u operator -p secret
```

| Option | Short | Description |
|--------|-------|-------------|
| `--security-policy=<policy>` | `-s` | None, Basic256Sha256, Aes256Sha256RsaPss, etc. |
| `--security-mode=<mode>` | `-m` | None, Sign, SignAndEncrypt |
| `--cert=<path>` | | Client certificate path |
| `--key=<path>` | | Client private key path |
| `--ca=<path>` | | CA certificate path |
| `--username=<user>` | `-u` | Username |
| `--password=<pass>` | `-p` | Password |
| `--timeout=<seconds>` | `-t` | Connection timeout (default: 5) |

## Output Options

### JSON

Add `--json` (or `-j`) to any command for machine-readable output:

```bash
opcua-cli browse opc.tcp://localhost:4840 --json | jq '.[].name'
opcua-cli read opc.tcp://localhost:4840 "i=2259" --json | jq '.Value'
```

### Debug Logging

```bash
# Log to stdout (incompatible with --json)
opcua-cli read opc.tcp://localhost:4840 "i=2259" --debug

# Log to stderr (compatible with --json)
opcua-cli read opc.tcp://localhost:4840 "i=2259" --debug-stderr --json

# Log to file
opcua-cli read opc.tcp://localhost:4840 "i=2259" --debug-file=/tmp/opcua.log --json
```

## Global Options

| Option | Short | Description |
|--------|-------|-------------|
| `--json` | `-j` | Output in JSON format |
| `--debug` | `-d` | Debug logging on stdout |
| `--debug-stderr` | | Debug logging on stderr |
| `--debug-file=<path>` | | Debug logging to file |
| `--help` | `-h` | Show help |
| `--version` | `-v` | Show version |

## Ecosystem

| Package | Description |
|---------|-------------|
| [`php-opcua/opcua-client`](https://github.com/php-opcua/opcua-client) | Pure PHP OPC UA client library |
| **`php-opcua/opcua-cli`** | **CLI tool (this package)** |
| [`php-opcua/opcua-client-nodeset`](https://github.com/php-opcua/opcua-client-nodeset) | Pre-built NodeSet2 types for 51 OPC UA companion specifications |

## Requirements

- PHP >= 8.2
- [`php-opcua/opcua-client`](https://github.com/php-opcua/opcua-client)

## Versioning

This package follows the same version numbering as [`php-opcua/opcua-client`](https://github.com/php-opcua/opcua-client). Each release of `opcua-cli` is aligned with the corresponding release of the client library to ensure full compatibility.

## License

MIT. See [LICENSE](LICENSE).
