---
eyebrow: 'Docs · Code generation'
lede:    'No NodeSet2.xml from the vendor? dump:nodeset exports the running server. Then generate:nodeset turns the dump into typed PHP. Round-trip in two commands.'

see_also:
  - { href: '../commands/dump-nodeset.md', meta: '4 min' }
  - { href: './generate-from-xml.md',      meta: '6 min' }
  - { href: './consuming-output.md',       meta: '5 min' }

prev: { label: 'Generate from XML',   href: './generate-from-xml.md' }
next: { label: 'Consuming generated', href: './consuming-output.md' }
---

# Dump from a server

Most OPC Foundation companion specs are published as
NodeSet2.xml. Vendor extensions rarely are. When the device
documentation doesn't include a NodeSet2.xml — but the server
implements custom types your application needs — `dump:nodeset`
is the way out.

The flow: dump the server's namespace to XML, then generate from
that XML.

## The two-command flow

<!-- @code-block language="bash" label="terminal — round-trip" -->
```bash
# 1. Export from the live server
opcua-cli dump:nodeset opc.tcp://plc.local:4840 \
    --output=MyPLC.NodeSet2.xml \
    --namespace=2

# 2. Generate PHP from the dump
opcua-cli generate:nodeset MyPLC.NodeSet2.xml \
    --output=src/Generated/MyPLC/ \
    --namespace="App\\OpcUa\\MyPLC"

# 3. (in PHP code) loadGeneratedTypes(new MyPLCRegistrar())
```
<!-- @endcode-block -->

Step 1 needs a live server. Steps 2 and 3 are local.

## Step 1 — dump

`dump:nodeset` walks the server's address space and serialises it
to a NodeSet2.xml. The flags:

| Flag              | Effect                                                       |
| ----------------- | ------------------------------------------------------------ |
| `--output=PATH`   | Where to write the XML (required)                            |
| `--namespace=N`   | Restrict to one namespace index. Without this, every non-zero namespace is dumped |

For most uses, restrict to the vendor's namespace
(typically `2`). The default — every non-zero namespace —
produces a large XML containing many specs you may not need.

See [Commands · dump:nodeset](../commands/dump-nodeset.md) for
the per-flag detail.

## Step 2 — generate

Feed the dump back into `generate:nodeset`:

<!-- @code-block language="bash" label="terminal — generate from dump" -->
```bash
opcua-cli generate:nodeset MyPLC.NodeSet2.xml \
    --output=src/Generated/MyPLC/ \
    --namespace="App\\OpcUa\\MyPLC"
```
<!-- @endcode-block -->

Output layout identical to generating from any other XML — see
[Generate from XML](./generate-from-xml.md).

## What survives the round-trip

| Element                                  | Survives the dump? | Survives the generate? |
| ---------------------------------------- | ------------------ | ---------------------- |
| NodeIds + BrowseNames                    | Yes                | → `<Spec>NodeIds`      |
| NodeClass (Object, Variable, Method, …)   | Yes                | Type-definition NodeIds |
| References (HasComponent, HasProperty, …) | Yes               | Cross-NodeId pointers in the XML — not emitted as PHP |
| DataType definitions (enums, structures)  | If the server publishes `DataTypeDefinition` attribute (OPC UA 1.04+) | → `Enums/`, `Types/`, `Codecs/` |
| Display names, descriptions, units        | Yes                | Available in NodeIds class (BrowseName); not emitted as separate metadata |
| Runtime values                            | **No** — dump is a structural snapshot, not a data dump | n/a |

The DataType definition step is the make-or-break: if the server
publishes proper `DataTypeDefinition` attributes (every OPC UA
1.04+ server does), the dump captures them, and the generator
emits real DTOs + codecs. If the server pre-dates 1.04 or
doesn't expose the attribute, the dump captures the type's
NodeId but the generator falls back to "unknown structure" — you
get a NodeId constant and that's it.

## Use cases

### Vendor-specific PLC

A PLC vendor ships a custom address space without publishing a
NodeSet2.xml. Their integration docs say "implement the
following nodes" with no machine-readable spec.

`dump:nodeset` extracts what the server actually publishes —
which usually matches the vendor's docs and gives you a
machine-readable source.

### Reverse-engineering

You inherited an integration with no documentation. The server
is running but no one knows what it exposes. Dump, generate, use
the typed output to navigate it from PHP code.

### Snapshot-based testing

A test harness needs a deterministic server. Dump the real
server once, use the XML as a fixture, replay against a mock
server that serves the same shape.

### Documentation

The NodeSet2.xml is grep-able and diff-able. Periodic dumps
across firmware revisions track what changed in the server's
exposed surface.

## Caveats

- **Large servers take time.** A dump of a 50 000-node server
  runs for minutes. Use `--namespace=N` to narrow.
- **Some servers throttle.** If the server limits operations
  per session, the dump may fail partway. Split by namespace
  and run multiple dumps; concatenate the XMLs if needed.
- **Method signatures lose information.** Methods are dumped as
  nodes, but their `InputArguments` / `OutputArguments` are
  `ExtensionObject`s the generator currently doesn't decode into
  typed signatures.

## When to skip the dump

- The vendor publishes a NodeSet2.xml. Use it directly.
- The OPC Foundation publishes the spec — use
  [`opcua-client-nodeset`](https://github.com/php-opcua/opcua-client-nodeset).
- You only need NodeIds — hand-write a few `const`s instead of
  generating a full package for one tag.

`dump:nodeset` + `generate:nodeset` is the right workflow when
the typed shape matters and no XML is available upstream.

## Round-trip integrity

A dump → generate → load → read against the same server should
return values your application expects. The wire-level shape is
preserved across the round-trip because:

- The dump captures DataType NodeIds exactly as the server
  publishes them.
- The generator emits codecs whose binary encoding matches the
  server's.
- The library decodes server responses using those codecs.

If you observe value mismatches after the round-trip, the cause
is almost always:

1. The server's `DataTypeDefinition` attribute is incomplete /
   buggy. The generator emits a codec that doesn't match the
   server's wire format.
2. The dump captured a previous version of the spec; the server
   has since been updated.
3. The XML was manually edited and a field was dropped or
   reordered.

Diagnose with `--debug-file` on the consuming command and
inspect the wire payload.
