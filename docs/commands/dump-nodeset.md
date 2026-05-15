---
eyebrow: 'Docs · Command · dump:nodeset'
lede:    'Export a live server''s namespace to a NodeSet2.xml file. Reverse of generate:nodeset — start from the server, end with a file you can feed to the code generator.'

see_also:
  - { href: './generate-nodeset.md',                meta: '5 min' }
  - { href: '../code-generation/dump-from-server.md', meta: '5 min' }
  - { href: '../recipes/inventory-with-dump-and-grep.md', meta: '4 min' }

prev: { label: 'generate:nodeset', href: './generate-nodeset.md' }
next: { label: 'Endpoint URLs',    href: '../connecting/endpoint-urls.md' }
---

# `dump:nodeset`

Walk a server's address space and emit a NodeSet2.xml.

## Usage

<!-- @code-block language="text" label="signature" -->
```text
opcua-cli dump:nodeset <endpoint> --output=<file.xml> [--namespace=<index>] [global-options]
```
<!-- @endcode-block -->

| Argument          | Meaning                                                                |
| ----------------- | ---------------------------------------------------------------------- |
| `<endpoint>`      | The OPC UA server URL. Required.                                       |

| Option              | Default              | Effect                                            |
| ------------------- | -------------------- | ------------------------------------------------- |
| `--output=PATH`     | required             | Output file path (`.xml`)                         |
| `--namespace=N`     | all non-zero namespaces | Dump only namespace index `N`                  |

Connects to the server, browses recursively, reads node attributes
in batches, writes them as a NodeSet2.xml.

## Examples

### Dump everything

<!-- @code-block language="bash" label="terminal — full dump" -->
```bash
opcua-cli dump:nodeset opc.tcp://plc.local:4840 --output=PLC.NodeSet2.xml
```
<!-- @endcode-block -->

Walks every node in every non-zero namespace. On a server with
50 000 nodes this takes a few minutes; the output file is
megabytes.

### Dump a single namespace

<!-- @code-block language="bash" label="terminal — namespace-filtered" -->
```bash
opcua-cli dump:nodeset opc.tcp://plc.local:4840 --output=PLC-ns2.NodeSet2.xml --namespace=2
```
<!-- @endcode-block -->

Most production deployments care only about the vendor's custom
namespace (typically `2`). Filter to it for a much smaller dump.

### Then generate from it

<!-- @code-block language="bash" label="terminal — round-trip" -->
```bash
# 1. Export from the server
opcua-cli dump:nodeset opc.tcp://plc.local:4840 \
    --output=MyPLC.NodeSet2.xml --namespace=2

# 2. Generate PHP types from the dump
opcua-cli generate:nodeset MyPLC.NodeSet2.xml \
    --output=src/Generated/MyPLC/ \
    --namespace="App\\OpcUa\\MyPLC"

# 3. Use in your code (loadGeneratedTypes)
```
<!-- @endcode-block -->

This is the canonical "server has no published NodeSet2.xml"
workflow. See [Code generation · Dump from a
server](../code-generation/dump-from-server.md).

## What ends up in the XML

The dump captures:

- **Nodes** — every node visited, with NodeId, BrowseName,
  DisplayName, Description, NodeClass.
- **Attributes** — DataType, ValueRank, AccessLevel,
  Historizing, IsAbstract, etc. (per the OPC UA Attribute
  catalogue).
- **References** — every HasComponent, HasProperty, HasTypeDefinition,
  Organizes, … pointing out of each node.
- **Aliases** — namespace 0 well-known references referenced by
  the dumped nodes (e.g. `HasComponent = i=47`).
- **Required models** — declares the spec's namespace URIs the
  dump depends on.

What the dump **does not** capture:

- **Node values.** The dump records *structure*, not *runtime
  values*. A node's Value attribute is not dumped (it changes
  continuously).
- **Custom DataType definitions** that the server has not
  itself encoded as standard `DataTypeDefinition` attributes.
  The generator can still produce NodeId constants and enums,
  but custom structures may need manual work.

## How it interacts with auto-generation

The XML the dump produces is **plain NodeSet2** — feed it to
`generate:nodeset` and the output is regular generated PHP.
This is how you get typed PHP for a server whose vendor never
published a NodeSet2.xml.

## How it maps to the library

| You ran                                                  | The CLI does                                                          |
| -------------------------------------------------------- | --------------------------------------------------------------------- |
| `dump:nodeset <endpoint> --output=…`                     | `$client->browseRecursive(...)` + per-node `readMulti()` + `NodeSetXmlBuilder::build(...)` |

The internal flow is browse-then-read-then-build. For very large
address spaces, the read step dominates — most of the runtime is
batched attribute reads.

## When to use it

- **Vendor-specific PLC with no published spec.** Dump, generate,
  use the generated PHP types in your application.
- **Reverse-engineering a new server.** A grep-able XML is
  easier to inspect than running `browse --recursive` repeatedly.
- **Snapshot for testing.** Dump once; replay in a test
  environment against a faked server that serves the same shape.
- **Documentation.** The XML can be diffed across firmware
  revisions to track namespace changes.

## What the XML is *not* good for

- **Live values.** Use `read`, `watch`, or subscriptions.
- **Method call signatures.** Methods are nodes (dumped), but
  their `InputArguments` / `OutputArguments` properties are
  ExtensionObjects — the dump captures them but they may
  decode incompletely.
- **As-published spec.** A dump is *what this specific server
  publishes*, which may differ from the OPC Foundation's
  canonical spec.

## Common pitfalls

- **Output file overwritten silently.** No confirmation prompt.
  Don't dump on top of a file you wanted to keep.
- **Long-running on huge address spaces.** Plan for it — a
  50 000-node server takes minutes. Use `--namespace=N` to
  narrow.
- **Server times out.** Some servers limit per-session
  operation count. If the dump fails partway, narrow the
  namespace filter and run multiple dumps.
- **The dump captures attribute values you might not expect.**
  ValueRank, ArrayDimensions, AccessLevel — these are static-
  per-node and useful. But `MinimumSamplingInterval` is also
  captured and may surprise you.
