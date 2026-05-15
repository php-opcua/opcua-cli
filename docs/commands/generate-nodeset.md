---
eyebrow: 'Docs · Command · generate:nodeset'
lede:    'Render PHP classes from a NodeSet2.xml. Five categories of file per spec — NodeIds, Enums, Types, Codecs, Registrar. No server connection needed.'

see_also:
  - { href: './dump-nodeset.md',                            meta: '4 min' }
  - { href: '../code-generation/generate-from-xml.md',      meta: '6 min' }
  - { href: '../code-generation/consuming-output.md',       meta: '5 min' }

prev: { label: 'trust',         href: './trust.md' }
next: { label: 'dump:nodeset',  href: './dump-nodeset.md' }
---

# `generate:nodeset`

Read a NodeSet2.xml file and render the matching PHP types.

## Usage

<!-- @code-block language="text" label="signature" -->
```text
opcua-cli generate:nodeset <xmlFile> [--output=./generated/] [--namespace=Generated\\OpcUa] [global-options]
```
<!-- @endcode-block -->

| Argument          | Meaning                                                                |
| ----------------- | ---------------------------------------------------------------------- |
| `<xmlFile>`       | Path to a `NodeSet2.xml` file. Required.                               |

| Option              | Default              | Effect                                            |
| ------------------- | -------------------- | ------------------------------------------------- |
| `--output=PATH`     | `./generated/`        | Output directory (created if missing)             |
| `--namespace=NS`    | `Generated\OpcUa`     | PHP namespace for the rendered classes            |

**Does not connect to any server** — fully local processing.

## Examples

<!-- @code-block language="bash" label="terminal" -->
```bash
opcua-cli generate:nodeset path/to/Robotics.NodeSet2.xml \
    --output=src/Generated/Robotics/ \
    --namespace="App\\OpcUa\\Robotics"
```
<!-- @endcode-block -->

Backslashes in the namespace need shell-escaping (the example
above uses `"App\\OpcUa\\Robotics"` for bash). On Windows
`cmd.exe`, plain `App\OpcUa\Robotics` works.

## Output layout

For a spec with enums + DTOs + codecs:

<!-- @code-block language="text" label="generated tree" -->
```text
src/Generated/Robotics/
├── RoboticsNodeIds.php                   NodeId string constants
├── RoboticsRegistrar.php                 GeneratedTypeRegistrar wiring
├── Enums/
│   ├── OperationalModeEnumeration.php
│   ├── MotionDeviceCategoryEnumeration.php
│   ├── AxisMotionProfileEnumeration.php
│   └── ExecutionModeEnumeration.php
├── Types/                                (when the spec has structured DataTypes)
│   ├── SomeDtoType.php
│   └── …
└── Codecs/                               (one per Type)
    ├── SomeDtoTypeCodec.php
    └── …
```
<!-- @endcode-block -->

For a spec with only enums (Robotics, ADI, …), `Types/` and
`Codecs/` are absent. For a spec with no custom DataTypes at all
(Machinery, LaserSystems, …), only `<Spec>NodeIds.php` and
`<Spec>Registrar.php` are emitted.

See [Code generation · Generate from XML](../code-generation/generate-from-xml.md)
for the per-artefact format.

## How it maps to the library

The output integrates with
[`php-opcua/opcua-client`](https://github.com/php-opcua/opcua-client)
via `ClientBuilder::loadGeneratedTypes()`. The registrar
implements the `GeneratedTypeRegistrar` contract; consuming the
output is a one-line builder call. See [Code generation ·
Consuming generated code](../code-generation/consuming-output.md).

## Where to get NodeSet2.xml files

| Source                                                                                     | What                                                          |
| ------------------------------------------------------------------------------------------ | ------------------------------------------------------------- |
| [OPC Foundation UA-Nodeset](https://github.com/OPCFoundation/UA-Nodeset)                   | Official companion specs (Robotics, MachineTool, BACnet, …)  |
| [`php-opcua/opcua-client-nodeset`](https://github.com/php-opcua/opcua-client-nodeset)      | 51 pre-generated specs — install instead of regenerating      |
| Device manufacturer                                                                        | Vendor-specific extensions in their documentation             |
| Live server (`opcua-cli dump:nodeset`)                                                     | Extract what a specific server actually publishes             |

For the official spec catalogue, the pre-generated package is
faster than running `generate:nodeset` yourself.

## When to use `generate:nodeset` vs the pre-generated package

| Use `generate:nodeset` directly when                              | Use the pre-generated package when                                  |
| ----------------------------------------------------------------- | ------------------------------------------------------------------- |
| The spec is vendor-specific (not in UA-Nodeset)                   | The spec is one of the 51 official OPC Foundation specs            |
| You forked a UA-Nodeset XML with custom edits                     | You want to track the official spec set                            |
| You generated from a live server's `dump:nodeset` export          | You want zero CI cost on dependency upgrades                       |
| You need a custom namespace / output structure                    | The standard `PhpOpcua\Nodeset\*` layout is fine                   |

## What's *not* generated

- **Method signatures.** OPC UA method NodeIds appear in
  `<Spec>NodeIds`; the parameter / return shape is not currently
  emitted as a typed wrapper.
- **Browse paths.** The output is NodeIds, not navigation
  helpers.
- **Event-type DTOs.** Event NodeIds are in `<Spec>NodeIds`; the
  event payload is decoded as raw `Variant[]` by the library, not
  by the generator.
- **XML-encoded ExtensionObjects.** Codecs emit binary
  (`encoding = 1`) only.

## How it maps to the library

The generator inside `opcua-cli` produces the same shape that
[`php-opcua/opcua-client-nodeset`](https://github.com/php-opcua/opcua-client-nodeset)
distributes. Internally:

| You ran                                       | The CLI uses                                                          |
| --------------------------------------------- | --------------------------------------------------------------------- |
| `generate:nodeset <xml>`                      | `NodeSetParser::parse($xml)` → `CodeGenerator::generate*Class(...)`   |

## Common pitfalls

- **Namespace casing.** OPC UA URIs are case-sensitive. The
  generator names files after the spec's URI segment — `DI/`
  becomes `DiRegistrar`, `ISA95/` becomes `OpcISA95Registrar`.
  Rename if you want consistent PascalCase, but expect
  regeneration to overwrite.
- **Missing dependencies.** A NodeSet2.xml that imports
  `Machinery` types fails to generate if `Machinery` is not
  available. Generate dependencies first.
- **Stale output.** `generate:nodeset` does not detect what's
  already in `--output`. It writes files; pre-existing files
  are overwritten silently. Clean the directory if you want a
  pristine regeneration.
