---
eyebrow: 'Docs · Code generation'
lede:    'generate:nodeset takes a NodeSet2.xml and emits five categories of PHP file. Per-DTO codecs, per-enum BackedEnums, per-spec NodeId constants and a registrar that wires it all into ClientBuilder.'

see_also:
  - { href: '../commands/generate-nodeset.md',     meta: '5 min' }
  - { href: './consuming-output.md',                meta: '5 min' }
  - { href: 'https://github.com/php-opcua/opcua-client-nodeset', meta: 'external', label: 'opcua-client-nodeset' }

prev: { label: 'Debug logging',     href: '../output/debug-logging.md' }
next: { label: 'Dump from a server', href: './dump-from-server.md' }
---

# Generate from XML

The `generate:nodeset` command reads an OPC UA NodeSet2.xml and
writes a directory of PHP classes that integrate directly with
`opcua-client`. Five categories of file per spec.

## In one command

<!-- @code-block language="bash" label="terminal" -->
```bash
opcua-cli generate:nodeset path/to/MyVendor.NodeSet2.xml \
    --output=src/Generated/MyVendor/ \
    --namespace="App\\OpcUa\\MyVendor"
```
<!-- @endcode-block -->

No server connection. Pure XML → PHP transformation: the output
files are written exactly as the heredoc-based templates in
`CodeGenerator` produce them. The CLI does **not** shell out to
`php-cs-fixer` or any other formatter on the generated output —
if you need a project-specific style, run your formatter against
the `--output` directory afterwards.

## The five categories

| File                            | Always emitted?    | What it is                                                            |
| ------------------------------- | ------------------ | --------------------------------------------------------------------- |
| `<Spec>NodeIds.php`             | Yes                | Class of `public const Name = 'ns=N;i=M';` constants                  |
| `<Spec>Registrar.php`           | Yes                | Implements `GeneratedTypeRegistrar`; wires codecs + enum mappings    |
| `Enums/<Enum>.php`              | When spec has enums | `enum Foo: int { case BAR = 1; … }`                                  |
| `Types/<Type>.php`              | When spec has DTOs  | `readonly class Foo { public function __construct(...) {} }`         |
| `Codecs/<Type>Codec.php`        | When spec has DTOs  | `class FooCodec implements ExtensionObjectCodec { decode/encode }`   |

A spec with only enums (Robotics, ADI) ships `<Spec>NodeIds`,
`<Spec>Registrar`, and `Enums/`. A spec with no custom DataTypes
(Machinery, LaserSystems) ships only `<Spec>NodeIds` and a
no-op registrar.

## The five files in detail

### NodeIds

<!-- @code-block language="php" label="generated NodeIds" -->
```php
namespace App\OpcUa\MyVendor;

class MyVendorNodeIds
{
    public const Server               = 'ns=1;i=1001';
    public const DeviceCollection     = 'ns=1;i=1002';
    public const TemperatureSensor    = 'ns=1;i=1003';
    public const Status               = 'ns=1;i=2001';
    // …
}
```
<!-- @endcode-block -->

Names come from the BrowseName of each node in the XML.
Sanitised to PHP identifiers (special chars replaced with
underscores). The full grammar:
[`opcua-client-nodeset` — NodeId constants](https://github.com/php-opcua/opcua-client-nodeset/blob/master/docs/concepts/node-id-constants.md).

### Enums

<!-- @code-block language="php" label="generated Enum" -->
```php
namespace App\OpcUa\MyVendor\Enums;

enum OperatingStateEnum: int
{
    case OFF           = 0;
    case STARTING      = 1;
    case RUNNING       = 2;
    case STOPPING      = 3;
    case ALARM         = 4;
}
```
<!-- @endcode-block -->

`SCREAMING_SNAKE_CASE` case names. Integer backing type — OPC UA
enumerations are always `Int32` on the wire.

### Types (readonly DTOs)

<!-- @code-block language="php" label="generated DTO" -->
```php
namespace App\OpcUa\MyVendor\Types;

readonly class PumpStatusType
{
    public function __construct(
        public float                 $FlowRate_m3h,
        public float                 $DischargePressure_bar,
        public float                 $FluidTemperature_C,
        public OperatingStateEnum    $State,
        public ?string               $LastErrorMessage,
        public \DateTimeImmutable    $AcquisitionTimestamp,
    ) {}
}
```
<!-- @endcode-block -->

Constructor-promoted public readonly properties. Field types are
inferred from the spec's `<Field DataType="…">`:

- Built-in OPC UA types → native PHP scalar or `opcua-client` value object
- Custom enum → the generated `BackedEnum`
- Custom structure → the generated DTO
- Optional → nullable, default `null`
- Array → `array`

### Codecs

<!-- @code-block language="php" label="generated Codec" -->
```php
namespace App\OpcUa\MyVendor\Codecs;

class PumpStatusTypeCodec implements ExtensionObjectCodec
{
    public function decode(BinaryDecoder $decoder): PumpStatusType
    {
        return new PumpStatusType(
            $decoder->readDouble(),
            $decoder->readDouble(),
            $decoder->readDouble(),
            OperatingStateEnum::from($decoder->readInt32()),
            // ... optional field handling ...
            $decoder->readDateTime(),
        );
    }

    public function encode(BinaryEncoder $encoder, mixed $value): void
    {
        assert($value instanceof PumpStatusType);
        $encoder->writeDouble($value->FlowRate_m3h);
        $encoder->writeDouble($value->DischargePressure_bar);
        $encoder->writeDouble($value->FluidTemperature_C);
        $encoder->writeInt32($value->State->value);
        $encoder->writeDateTime($value->AcquisitionTimestamp);
    }
}
```
<!-- @endcode-block -->

One codec per DTO. The codec reads fields **in declaration order**
— the same order as the spec's `<Field>` list and the DTO's
constructor.

### Registrar

<!-- @code-block language="php" label="generated Registrar" -->
```php
namespace App\OpcUa\MyVendor;

class MyVendorRegistrar implements GeneratedTypeRegistrar
{
    public function __construct(public bool $only = false) {}

    public function registerCodecs(ExtensionObjectRepository $repository): void
    {
        $repository->register(
            NodeId::parse(MyVendorNodeIds::PumpStatusType),
            new Codecs\PumpStatusTypeCodec(),
        );
    }

    public function getEnumMappings(): array
    {
        return [
            MyVendorNodeIds::OperatingStateEnum => Enums\OperatingStateEnum::class,
        ];
    }

    public function dependencyRegistrars(): array
    {
        return [
            new \PhpOpcua\Nodeset\DI\DiRegistrar(),   // (if the XML imports DI)
        ];
    }
}
```
<!-- @endcode-block -->

The single entry point for consumers — pass it to
`ClientBuilder::loadGeneratedTypes()`.

`dependencyRegistrars()` reflects the spec's `<Models>` /
`<RequiredModel>` declarations from the XML. The generator scans
for those and emits the appropriate `new`s.

## How the namespace argument flows

`--namespace=App\\OpcUa\\MyVendor` produces:

- `App\OpcUa\MyVendor\MyVendorNodeIds`
- `App\OpcUa\MyVendor\MyVendorRegistrar`
- `App\OpcUa\MyVendor\Enums\OperatingStateEnum`
- `App\OpcUa\MyVendor\Types\PumpStatusType`
- `App\OpcUa\MyVendor\Codecs\PumpStatusTypeCodec`

Backslashes need shell escaping; `cmd.exe` accepts them
unescaped. PSR-4 autoload should point the namespace prefix at
the `--output` directory.

## Naming inconsistencies

The generator follows the spec's own URI casing. A few specs
produce non-PascalCase names:

| Spec directory  | Generated registrar       |
| --------------- | ------------------------- |
| `DI/`           | `DiRegistrar`             |
| `ISA95/`        | `OpcISA95Registrar`       |
| `MDIS/`         | `OpcMDISRegistrar`        |
| `PROFINET/`     | `PnRegistrar`             |
| `WoT/`          | `WotConRegistrar`         |

For internal consistency, you can `class_alias` these in your
application's autoload — see
[`opcua-client-nodeset` — naming inconsistencies](https://github.com/php-opcua/opcua-client-nodeset/blob/master/docs/getting-started/what-gets-generated.md#section-naming-inconsistencies-you-may-see).

## When to prefer the pre-generated package

For the 51 official OPC Foundation companion specs, the
[`php-opcua/opcua-client-nodeset`](https://github.com/php-opcua/opcua-client-nodeset)
package ships pre-built output. Use it instead of running
`generate:nodeset` yourself unless:

- You're generating from a **custom** NodeSet2.xml (vendor-
  specific, or your own).
- You're targeting a **specific upstream commit** of UA-Nodeset
  with custom edits.
- You're testing the generator itself.

For everything else: `composer require php-opcua/opcua-client-nodeset`
gives you all 51 specs without running the generator.

## Limitations

- **Binary encoding only.** Codecs emit `ExtensionObject`
  encoding `1` (binary). XML-encoded structures
  (`encoding = 2`) are not generated.
- **No XSD-defined custom types** outside the NodeSet2.xml
  itself. A spec that references types from an unbundled XSD
  schema fails to fully generate; the missing types fall back
  to raw `ExtensionObject`.
- **Method signatures not generated.** Methods are nodes — they
  appear in `<Spec>NodeIds` — but the parameter / return shape
  is not emitted as a typed wrapper.
