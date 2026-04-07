# OPC UA CLI — AI Skills Reference

> Task-oriented recipes for AI coding assistants. Feed this file to your AI (Claude, Cursor, Copilot, GPT, etc.) so it knows how to use `php-opcua/opcua-cli` correctly.

## How to use this file

Add this file to your AI assistant's context:
- **Claude Code**: reference per-session with `--add-file vendor/php-opcua/opcua-cli/llms-skills.md`
- **Cursor**: copy into your project's rules directory — `cp vendor/php-opcua/opcua-cli/llms-skills.md .cursor/rules/opcua-cli.md`
- **GitHub Copilot**: copy or append the content into your project's `.github/copilot-instructions.md` file (create the file and directory if they don't exist). Copilot reads this file automatically for project-specific context
- **Other tools**: paste the content into your system prompt, project knowledge base, or context configuration

---

## What This Package Does

A standalone PHP command-line tool for interacting with OPC UA servers. No framework dependencies — just `composer require` and run. Built on `php-opcua/opcua-client`, it provides 10 composable commands with JSON output, security support, and Unix pipe compatibility.

**10 commands**: `browse`, `read`, `write`, `watch`, `endpoints`, `trust`, `trust:list`, `trust:remove`, `generate:nodeset`, `dump:nodeset`

---

## Skill: Install and Run Basic Commands

### When to use
The user wants to quickly interact with an OPC UA server from the terminal.

### Install
```bash
composer require php-opcua/opcua-cli
```

### Basic usage
```bash
# Browse the address space
opcua-cli browse opc.tcp://localhost:4840

# Browse a specific path
opcua-cli browse opc.tcp://localhost:4840 /Objects/MyPLC

# Browse a NodeId
opcua-cli browse opc.tcp://localhost:4840 "ns=2;i=1000"

# Read a value
opcua-cli read opc.tcp://localhost:4840 "i=2259"

# Read a specific attribute
opcua-cli read opc.tcp://localhost:4840 "ns=2;i=1001" --attribute=DisplayName

# Write a value
opcua-cli write opc.tcp://localhost:4840 "ns=2;i=1001" 42 --type=Int32

# Write with auto-detect type
opcua-cli write opc.tcp://localhost:4840 "ns=2;i=1001" 42

# Discover endpoints
opcua-cli endpoints opc.tcp://localhost:4840
```

### Important rules
- The endpoint format is always `opc.tcp://host:port`
- NodeIds can be string format (`"i=2259"`, `"ns=2;s=Temperature"`) or browse paths (`/Objects/Server`)
- All commands support `--json` (`-j`) for machine-readable JSON output
- All commands support `--debug` (`-d`) for verbose protocol logging
- Use `--debug-stderr` instead of `--debug` when combining with `--json` (debug on stderr, JSON on stdout)

---

## Skill: Browse the Address Space

### When to use
The user wants to explore what's available on an OPC UA server — nodes, variables, objects, methods.

### Code
```bash
# Browse root Objects folder
opcua-cli browse opc.tcp://localhost:4840

# Browse a specific path
opcua-cli browse opc.tcp://localhost:4840 /Objects/MyPLC

# Browse by NodeId
opcua-cli browse opc.tcp://localhost:4840 "ns=2;i=1000"

# Recursive browse with depth limit
opcua-cli browse opc.tcp://localhost:4840 /Objects --recursive --depth=3

# JSON output for scripting
opcua-cli browse opc.tcp://localhost:4840 --json

# Pipe to grep/jq
opcua-cli browse opc.tcp://localhost:4840 --json | jq '.[] | select(.nodeClass == "Variable")'
```

### Output format
- **Console**: tree view with box-drawing characters, node names, NodeIds, and node classes
- **JSON**: array of objects with `nodeId`, `displayName`, `browseName`, `nodeClass`, `referenceTypeId`

### Important rules
- Default browse starts at the Objects folder (`i=85`)
- `--recursive` can generate a lot of output on large servers — use `--depth` to limit
- Browse results show: display name, NodeId, node class (Object, Variable, Method, etc.)

---

## Skill: Read and Write Values

### When to use
The user wants to read sensor values, status, or write setpoints and commands.

### Read
```bash
# Read value (default attribute)
opcua-cli read opc.tcp://localhost:4840 "i=2259"

# Read specific attributes
opcua-cli read opc.tcp://localhost:4840 "ns=2;i=1001" --attribute=DisplayName
opcua-cli read opc.tcp://localhost:4840 "ns=2;i=1001" --attribute=DataType
opcua-cli read opc.tcp://localhost:4840 "ns=2;i=1001" --attribute=NodeClass
opcua-cli read opc.tcp://localhost:4840 "ns=2;i=1001" --attribute=Description
opcua-cli read opc.tcp://localhost:4840 "ns=2;i=1001" --attribute=AccessLevel
opcua-cli read opc.tcp://localhost:4840 "ns=2;i=1001" --attribute=BrowseName
opcua-cli read opc.tcp://localhost:4840 "ns=2;i=1001" --attribute=NodeId

# JSON output
opcua-cli read opc.tcp://localhost:4840 "i=2259" --json
```

### Write
```bash
# Write with explicit type
opcua-cli write opc.tcp://localhost:4840 "ns=2;i=1001" 42 --type=Int32
opcua-cli write opc.tcp://localhost:4840 "ns=2;i=2000" true --type=Boolean
opcua-cli write opc.tcp://localhost:4840 "ns=2;i=2001" 3.14 --type=Double
opcua-cli write opc.tcp://localhost:4840 "ns=2;s=Label" "hello" --type=String

# Write with auto-detect (reads the node's DataType first)
opcua-cli write opc.tcp://localhost:4840 "ns=2;i=1001" 42
```

### Available write types
`Boolean`, `SByte`, `Byte`, `Int16`, `UInt16`, `Int32`, `UInt32`, `Int64`, `UInt64`, `Float`, `Double`, `String`

### Read output
Shows: NodeId, Attribute, Value, Type, Status (Good/Bad), Source timestamp, Server timestamp.

### Important rules
- Read output shows status code — `Good` means success, anything else indicates a problem
- Write without `--type` auto-detects by reading the node's DataType first (extra round-trip)
- String values with spaces need quoting: `"hello world"`

---

## Skill: Watch Values in Real Time

### When to use
The user wants to monitor a value continuously — see changes as they happen.

### Code
```bash
# Subscription mode (server pushes changes) — most efficient
opcua-cli watch opc.tcp://localhost:4840 "ns=2;i=1001"

# Polling mode — read every 250ms
opcua-cli watch opc.tcp://localhost:4840 "ns=2;i=1001" --interval=250

# JSON output for piping
opcua-cli watch opc.tcp://localhost:4840 "ns=2;i=1001" --json

# With debug logging to file (keeps stdout clean for data)
opcua-cli watch opc.tcp://localhost:4840 "ns=2;i=1001" --json --debug-file=/tmp/opcua.log
```

### Important rules
- **Without `--interval`**: uses OPC UA subscriptions (server push) — most efficient, lowest latency
- **With `--interval=N`**: polling mode — reads every N milliseconds, useful when subscriptions aren't supported
- Press `Ctrl+C` to stop watching
- `--json` outputs one JSON object per line (NDJSON) — good for piping to `jq` or log aggregators
- The `watch` command runs indefinitely until interrupted

---

## Skill: Connect with Security

### When to use
The user needs to connect to a secured OPC UA server with encryption and/or authentication.

### Code
```bash
# Username/password authentication
opcua-cli read opc.tcp://server:4840 "i=2259" -u operator -p secret

# Encrypted connection
opcua-cli read opc.tcp://server:4840 "i=2259" \
    --security-policy=Basic256Sha256 \
    --security-mode=SignAndEncrypt

# Full security with certificates and credentials
opcua-cli read opc.tcp://server:4840 "i=2259" \
    -s Basic256Sha256 \
    -m SignAndEncrypt \
    --cert=/certs/client.pem \
    --key=/certs/client.key \
    --ca=/certs/ca.pem \
    -u operator \
    -p secret

# Discover what security the server supports
opcua-cli endpoints opc.tcp://server:4840
```

### Security options reference

| Option | Short | Values |
|--------|-------|--------|
| `--security-policy` | `-s` | `None`, `Basic128Rsa15`, `Basic256`, `Basic256Sha256`, `Aes128Sha256RsaOaep`, `Aes256Sha256RsaPss` |
| `--security-mode` | `-m` | `None`, `Sign`, `SignAndEncrypt` (or `1`, `2`, `3`) |
| `--cert` | | Client certificate path (PEM) |
| `--key` | | Client private key path (PEM) |
| `--ca` | | CA certificate path (PEM) |
| `--username` | `-u` | Username |
| `--password` | `-p` | Password |
| `--timeout` | `-t` | Connection timeout in seconds (default: 5) |

### Important rules
- Security options are **global** — they work with all commands (browse, read, write, watch, etc.)
- If `--cert`/`--key` are omitted but policy/mode are set, a self-signed cert is auto-generated
- Use `opcua-cli endpoints` first to discover what security configurations the server supports
- Short flags save typing: `-s Basic256Sha256 -m SignAndEncrypt -u admin -p secret`

---

## Skill: Manage Server Certificate Trust

### When to use
The user encounters certificate trust errors, or wants to manage which server certificates are trusted.

### Code
```bash
# Download and trust a server's certificate
opcua-cli trust opc.tcp://server:4840

# Trust with custom trust store location
opcua-cli trust opc.tcp://server:4840 --trust-store=/var/opcua/trust

# List all trusted certificates
opcua-cli trust:list
opcua-cli trust:list --trust-store=/var/opcua/trust

# Remove a trusted certificate by fingerprint
opcua-cli trust:remove AB:CD:12:34:56:78:...

# Connect with trust policy
opcua-cli read opc.tcp://server:4840 "i=2259" \
    --trust-store=/var/opcua/trust \
    --trust-policy=fingerprint

# Disable trust validation for a single command
opcua-cli read opc.tcp://server:4840 "i=2259" --no-trust-policy
```

### Trust options

| Option | Values |
|--------|--------|
| `--trust-store=<path>` | Directory for trusted certificates (default: `~/.opcua/trusted/`) |
| `--trust-policy=<policy>` | `fingerprint`, `fingerprint+expiry`, `full` |
| `--no-trust-policy` | Disable certificate validation for this command |

### Important rules
- `trust` downloads the server certificate and saves it to the trust store
- `trust:list` and `trust:remove` are offline commands — no server connection needed
- Default trust store is `~/.opcua/trusted/`
- Certificates are stored as DER files named by SHA-256 fingerprint

---

## Skill: Generate PHP Code from NodeSet2.xml

### When to use
The user has a NodeSet2.xml file (from a vendor, the OPC Foundation, or exported from a server) and wants typed PHP classes for those OPC UA types.

### Code
```bash
# Generate from a NodeSet2.xml file
opcua-cli generate:nodeset path/to/Opc.Ua.Di.NodeSet2.xml \
    --output=src/Generated/Di/ \
    --namespace=App\\OpcUa\\Di

# Generate with base namespace for dependencies
opcua-cli generate:nodeset path/to/Opc.Ua.Robotics.NodeSet2.xml \
    --output=src/Generated/Robotics/ \
    --namespace=App\\OpcUa\\Robotics \
    --base-namespace=App\\OpcUa
```

### What gets generated

```
src/Generated/Di/
├── DiNodeIds.php           # Node ID string constants
├── DiRegistrar.php         # Batch codec registration with dependency resolution
├── Enums/
│   └── SomeEnum.php        # PHP BackedEnum for each OPC UA enumeration
└── Codecs/
    └── SomeTypeCodec.php   # ExtensionObjectCodec for each structured type
```

- **NodeIds**: class with string constants (`const SomeNode = 'ns=2;i=1001'`)
- **Enums**: PHP 8.1+ `BackedEnum` for each OPC UA `EnumeratedType`
- **Codecs**: `ExtensionObjectCodec` implementations with binary encode/decode
- **Registrar**: `GeneratedTypeRegistrar` that registers all codecs at once, with automatic dependency loading

### Using generated types
```php
use App\OpcUa\Di\DiRegistrar;
use PhpOpcua\Client\ClientBuilder;

$client = ClientBuilder::create()
    ->loadGeneratedTypes(new DiRegistrar())
    ->connect('opc.tcp://192.168.1.100:4840');

// Structured values are now auto-decoded
$value = $client->read('ns=2;i=5001')->getValue();
// Returns typed DTO instead of raw bytes
```

### Important rules
- `generate:nodeset` is an **offline** command — no server connection needed
- NodeSet2.xml files can be found on the OPC Foundation's website or vendor documentation
- The `--namespace` must be a valid PHP namespace (double-escape backslashes in shell)
- Dependencies between NodeSets (e.g., Robotics depends on DI) must be generated separately — use `--base-namespace` for resolution

---

## Skill: Export Server Address Space to XML

### When to use
The user wants to capture a server's address space as a NodeSet2.xml file — for documentation, code generation, or offline analysis.

### Code
```bash
# Dump entire non-zero namespace
opcua-cli dump:nodeset opc.tcp://192.168.1.100:4840 --output=MyPLC.NodeSet2.xml

# Dump only namespace 2
opcua-cli dump:nodeset opc.tcp://192.168.1.100:4840 --output=MyPLC.NodeSet2.xml --namespace=2

# With security
opcua-cli dump:nodeset opc.tcp://server:4840 --output=server.xml \
    -s Basic256Sha256 -m SignAndEncrypt -u admin -p secret
```

### End-to-end workflow: server to typed PHP
```bash
# 1. Export from server
opcua-cli dump:nodeset opc.tcp://192.168.1.100:4840 --output=MyPLC.NodeSet2.xml --namespace=2

# 2. Generate PHP code
opcua-cli generate:nodeset MyPLC.NodeSet2.xml --output=src/OpcUa/MyPLC/ --namespace=App\\OpcUa\\MyPLC

# 3. Use in your code
# $client = ClientBuilder::create()
#     ->loadGeneratedTypes(new MyPLCRegistrar())
#     ->connect('opc.tcp://192.168.1.100:4840');
```

### Important rules
- `dump:nodeset` requires a live connection to the server
- The output is a valid NodeSet2.xml file that can be fed to `generate:nodeset`
- Large address spaces take time — use `--namespace` to limit scope
- `--output` is required — the file path where the XML will be written

---

## Skill: Use JSON Output for Scripting

### When to use
The user wants to integrate OPC UA data into scripts, pipelines, monitoring tools, or parse output programmatically.

### Code
```bash
# JSON output for any command
opcua-cli browse opc.tcp://localhost:4840 --json
opcua-cli read opc.tcp://localhost:4840 "i=2259" --json
opcua-cli endpoints opc.tcp://localhost:4840 --json

# Pipe to jq
opcua-cli browse opc.tcp://localhost:4840 --json | jq '.[].displayName'
opcua-cli read opc.tcp://localhost:4840 "i=2259" --json | jq '.value'

# Watch with NDJSON output
opcua-cli watch opc.tcp://localhost:4840 "ns=2;i=1001" --json

# Debug to stderr while keeping JSON on stdout
opcua-cli read opc.tcp://localhost:4840 "i=2259" --json --debug-stderr

# Debug to file
opcua-cli read opc.tcp://localhost:4840 "i=2259" --json --debug-file=/tmp/debug.log
```

### Important rules
- `--json` (`-j`) outputs JSON to stdout for all commands
- `--debug` (`-d`) writes debug logs to stdout — **incompatible with `--json`**
- Use `--debug-stderr` or `--debug-file` when combining debug with `--json`
- `watch --json` outputs one JSON object per line (NDJSON format)
- Errors are always written to stderr (JSON or plain text depending on `--json`)

---

## Skill: Discover Server Endpoints

### When to use
The user wants to see what security configurations, authentication methods, and endpoints a server supports before connecting.

### Code
```bash
# Discover endpoints
opcua-cli endpoints opc.tcp://192.168.1.100:4840

# JSON output
opcua-cli endpoints opc.tcp://192.168.1.100:4840 --json
```

### Output shows
- Endpoint URL
- Security policy (None, Basic256Sha256, etc.)
- Security mode (None, Sign, SignAndEncrypt)
- Available authentication methods (Anonymous, Username, Certificate)
- Server certificate info

### Important rules
- This is usually the first command to run against an unknown server
- The endpoint URL in the results may differ from the one you connected to (servers can advertise different URLs)
- Use the discovered policy and mode with `-s` and `-m` flags on subsequent commands

---

## Global Options Reference

### Security
| Option | Short | Description |
|--------|-------|-------------|
| `--security-policy=<policy>` | `-s` | Security policy name |
| `--security-mode=<mode>` | `-m` | `None`, `Sign`, `SignAndEncrypt` (or `1`, `2`, `3`) |
| `--cert=<path>` | | Client certificate (PEM) |
| `--key=<path>` | | Client private key (PEM) |
| `--ca=<path>` | | CA certificate (PEM) |
| `--username=<user>` | `-u` | Username authentication |
| `--password=<pass>` | `-p` | Password authentication |

### Trust
| Option | Description |
|--------|-------------|
| `--trust-store=<path>` | Trust store directory |
| `--trust-policy=<policy>` | `fingerprint`, `fingerprint+expiry`, `full` |
| `--no-trust-policy` | Disable certificate validation |

### Connection
| Option | Short | Description |
|--------|-------|-------------|
| `--timeout=<sec>` | `-t` | Connection timeout (default: 5) |

### Output
| Option | Short | Description |
|--------|-------|-------------|
| `--json` | `-j` | JSON output |
| `--debug` | `-d` | Debug logging to stdout |
| `--debug-stderr` | | Debug logging to stderr |
| `--debug-file=<path>` | | Debug logging to file |

### Help
| Option | Short | Description |
|--------|-------|-------------|
| `--help` | `-h` | Show help |
| `--version` | `-v` | Show version |

---

## Common Mistakes to Avoid

### 1. Using --debug with --json
```bash
# WRONG — debug output corrupts JSON on stdout
opcua-cli read opc.tcp://localhost:4840 "i=2259" --json --debug

# CORRECT — debug to stderr or file
opcua-cli read opc.tcp://localhost:4840 "i=2259" --json --debug-stderr
opcua-cli read opc.tcp://localhost:4840 "i=2259" --json --debug-file=/tmp/debug.log
```

### 2. Forgetting quotes on NodeIds with semicolons
```bash
# WRONG — shell interprets ; as command separator
opcua-cli read opc.tcp://localhost:4840 ns=2;i=1001

# CORRECT — quote the NodeId
opcua-cli read opc.tcp://localhost:4840 "ns=2;i=1001"
```

### 3. Using --auth-token on command line
```bash
# The CLI tool doesn't connect to the session manager daemon.
# --username/-u and --password/-p are for OPC UA server authentication.
# There is no --auth-token option on opcua-cli.
```

### 4. Expecting persistent connections
```bash
# Each opcua-cli command opens a fresh connection and closes it after.
# For persistent sessions, use opcua-session-manager with ManagedClient in PHP code.
# opcua-cli is designed for single-shot operations.
```
