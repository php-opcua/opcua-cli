---
eyebrow: 'Docs · Getting started'
lede:    'One command against any OPC UA server: opcua-cli browse opc.tcp://… — and you''re inside the address space.'

see_also:
  - { href: './how-it-works.md',           meta: '4 min' }
  - { href: '../commands/browse.md',       meta: '4 min' }
  - { href: '../connecting/trust-store-workflow.md', meta: '5 min' }

prev: { label: 'Installation',  href: './installation.md' }
next: { label: 'How it works',  href: './how-it-works.md' }
---

# First browse

The fastest verification that everything works. Three to five
seconds from the prompt to a list of root folders.

## Pick a server

Any OPC UA server will do. If you don't have one handy:

<!-- @tabs labels="Docker (open62541), Docker (UA-.NETStandard), Real server" -->
<!-- @tab index="0" -->
```bash
docker run --rm -d --name opcua-test -p 4840:4840 open62541/open62541:latest
```
<!-- @endtab -->
<!-- @tab index="1" -->
```bash
# The php-opcua test suite ships a Docker stack with several endpoints
git clone https://github.com/php-opcua/uanetstandard-test-suite
cd uanetstandard-test-suite
docker compose up -d
# Server on opc.tcp://localhost:4840 (no security)
```
<!-- @endtab -->
<!-- @tab index="2" -->
```text
Use the endpoint your PLC publishes — typically opc.tcp://<host>:4840.
```
<!-- @endtab -->
<!-- @endtabs -->

## Browse the Objects folder

<!-- @code-block language="bash" label="terminal" -->
```bash
opcua-cli browse opc.tcp://localhost:4840
```
<!-- @endcode-block -->

Output (against a vanilla open62541 instance):

<!-- @code-block language="text" label="output" -->
```text
├── Server (i=2253) [Object]
├── DeviceSet (ns=2;i=5001) [Object]
├── Aliases (ns=2;i=5002) [Object]
└── PublishSubscribe (ns=0;i=14443) [Object]
```
<!-- @endcode-block -->

That is the canonical first browse — the root `Objects` folder
(`i=85`) and its immediate children, rendered as a tree with the
node display name, the canonical NodeId in parentheses, and the
node class in brackets. Every OPC UA server exposes some variant
of this.

## Drill down

<!-- @code-block language="bash" label="terminal" -->
```bash
opcua-cli browse opc.tcp://localhost:4840 /Objects/Server
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="output" -->
```text
├── ServerArray (i=2254) [Variable]
├── NamespaceArray (i=2255) [Variable]
├── ServerStatus (i=2256) [Variable]
├── ServiceLevel (i=2267) [Variable]
├── Auditing (i=2994) [Variable]
├── ServerCapabilities (i=2268) [Object]
├── ServerDiagnostics (i=2274) [Object]
├── VendorServerInfo (i=2295) [Object]
├── ServerRedundancy (i=2296) [Object]
└── Namespaces (i=11715) [Object]
```
<!-- @endcode-block -->

`/Objects/Server` is the standard well-known node every OPC UA
server publishes — covering its product name, status, capabilities,
namespace table.

## Read a value

<!-- @code-block language="bash" label="terminal" -->
```bash
opcua-cli read opc.tcp://localhost:4840 i=2261
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="output" -->
```text
NodeId:    i=2261
Attribute: Value
Value:     open62541 OPC UA Server
Type:      String
Status:    Good (0x00000000)
Source:    2026-05-15T10:30:00+00:00
Server:    2026-05-15T10:30:00+00:00
```
<!-- @endcode-block -->

`i=2261` is the well-known NodeId for the server's product name —
the value you would have read in code as
`$client->getServerProductName()` from `opcua-client`. Same
result, no PHP.

## Get the endpoints map

<!-- @code-block language="bash" label="terminal" -->
```bash
opcua-cli endpoints opc.tcp://localhost:4840
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="output" -->
```text

Endpoint: opc.tcp://localhost:4840
Security: None (mode: None)
Auth:     Anonymous

Endpoint: opc.tcp://localhost:4840
Security: Basic256Sha256 (mode: Sign)
Auth:     Anonymous, Username

Endpoint: opc.tcp://localhost:4840
Security: Basic256Sha256 (mode: SignAndEncrypt)
Auth:     Anonymous, Username, Certificate
```
<!-- @endcode-block -->

That is the "what does this server support?" probe. You now know
which policies + modes + auth combinations the server publishes —
the starting point for any real production setup.

## Output as JSON

Every command supports `--json` for scripting:

<!-- @code-block language="bash" label="terminal" -->
```bash
opcua-cli endpoints opc.tcp://localhost:4840 --json | jq -r '.[].Security'
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="output" -->
```text
None (mode: None)
Basic256Sha256 (mode: Sign)
Basic256Sha256 (mode: SignAndEncrypt)
Aes128Sha256RsaOaep (mode: Sign)
```
<!-- @endcode-block -->

See [Output formats](../output/output-formats.md).

## Where to go next

You have run four real OPC UA commands. The next reads, in order
of usefulness:

1. [How it works](./how-it-works.md) — twenty seconds of mental
   model.
2. [Commands · browse](../commands/browse.md) — the full
   `browse` command page (recursive, depth, JSON, filters).
3. [Connecting · Trust store workflow](../connecting/trust-store-workflow.md)
   — what to do the first time a server requires a trusted
   certificate (which is most production servers).
