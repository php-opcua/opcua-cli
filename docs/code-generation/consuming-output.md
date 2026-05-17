---
eyebrow: 'Docs · Code generation'
lede:    'Load the generated registrar on a ClientBuilder; opcua-client picks up codecs and enum mappings. After that, reads return typed enums and DTOs automatically.'

see_also:
  - { href: './generate-from-xml.md',  meta: '6 min' }
  - { href: 'https://github.com/php-opcua/opcua-client-nodeset', meta: 'external', label: 'opcua-client-nodeset' }
  - { href: 'https://github.com/php-opcua/opcua-client/blob/master/docs/reference/client-api.md', meta: 'external', label: 'opcua-client — loadGeneratedTypes()' }

prev: { label: 'Dump from a server', href: './dump-from-server.md' }
next: { label: 'PHAR build',         href: '../building/phar.md' }
---

# Consuming generated code

The output of `generate:nodeset` is **PHP that integrates with
`opcua-client`** through a single entry point:
`ClientBuilder::loadGeneratedTypes()`. One call wires every
generated codec and enum mapping onto the client.

## The integration

<!-- @code-block language="php" label="examples/consume.php" -->
```php
use PhpOpcua\Client\ClientBuilder;
use App\OpcUa\MyVendor\MyVendorRegistrar;
use App\OpcUa\MyVendor\MyVendorNodeIds;
use App\OpcUa\MyVendor\Enums\OperatingStateEnum;
use App\OpcUa\MyVendor\Types\PumpStatusType;

$client = ClientBuilder::create()
    ->loadGeneratedTypes(new MyVendorRegistrar())
    ->connect('opc.tcp://plc.local:4840');

// Auto-cast on enum reads
$state = $client->read(MyVendorNodeIds::PumpState)->getValue();
// $state is OperatingStateEnum::RUNNING, not int 2

// Auto-decode on structured reads
$status = $client->read(MyVendorNodeIds::PumpStatus)->getValue();
// $status is PumpStatusType — typed property access available
echo $status->FlowRate_m3h;
echo $status->State->name;

$client->disconnect();
```
<!-- @endcode-block -->

That's the full surface — one builder call, then typed PHP for
the rest of the session.

## What `loadGeneratedTypes()` does

<!-- @steps -->
- **Walks `dependencyRegistrars()`** recursively. Loads each
  dependency's codecs + enum mappings first.

- **Calls `registerCodecs($repository)`** on every registrar in
  the cascade. The codecs land in the client's per-instance
  `ExtensionObjectRepository`, keyed by DataType NodeId.

- **Merges `getEnumMappings()`** into the client's enum
  registry — a DataType NodeId → BackedEnum class map.

- **Returns the builder** for chaining.
<!-- @endsteps -->

After `connect()` runs, the client's read path consults these
registries:

- A `Variant<ExtensionObject>` response whose `typeId` matches a
  registered codec is decoded into the typed DTO.
- A `Variant<Int32>` response on a node whose DataType is in the
  enum registry is wrapped in the matching `BackedEnum::from()`.

Without the load, the same reads return raw `ExtensionObject`
and `int`.

## Loading multiple registrars

Stack them — the loader handles dedup:

<!-- @code-block language="php" label="multiple registrars" -->
```php
$client = ClientBuilder::create()
    ->loadGeneratedTypes(new App\OpcUa\Vendor1Registrar())
    ->loadGeneratedTypes(new App\OpcUa\Vendor2Registrar())
    ->loadGeneratedTypes(new \PhpOpcua\Nodeset\Robotics\RoboticsRegistrar())
    ->connect('opc.tcp://multi-spec.plant.local:4840');
```
<!-- @endcode-block -->

Common dependencies (`DI`, `IA`, `Machinery`) come along
transitively from each. The registrar registry is idempotent —
loading the same codec twice ends up registering the same
codec instance.

For details: [`opcua-client-nodeset` — Loading
registrars](https://github.com/php-opcua/opcua-client-nodeset/blob/master/docs/usage/loading-registrars.md).

## Without auto-cast — the no-load fallback

If you skip `loadGeneratedTypes()` entirely, the generated code
is still useful as constants:

<!-- @code-block language="php" label="no-load — constants only" -->
```php
$client = ClientBuilder::create()
    ->connect('opc.tcp://plc.local:4840');

// NodeId constants — type-safe NodeIds, no auto-cast
$dv = $client->read(MyVendorNodeIds::PumpState);
$rawValue = $dv->getValue();  // int (raw value the server sent)

// You can still use the enum class directly:
$state = OperatingStateEnum::from($rawValue);
if ($state === OperatingStateEnum::RUNNING) { … }
```
<!-- @endcode-block -->

Useful when the registrar wiring isn't possible (e.g. third-
party code controls the `ClientBuilder` and you only get to use
constants).

## With opcua-session-manager

`opcua-session-manager`'s `ManagedClient` does **not** expose a
`loadGeneratedTypes()` method directly — that entry point lives on
`opcua-client`'s `ClientBuilder`, which the managed client wraps
but does not surface. To get typed decoding through the daemon
today, configure the registrar on the daemon side (in the worker
process that constructs the underlying `ClientBuilder`) rather
than via a per-`ManagedClient` call.

See the session-manager docs for the current pattern; if your
application needs in-process typed decoding, drop down to
`opcua-client` directly and call `loadGeneratedTypes()` there.

## Where to put the generated code

| Repository layout option                                                  | Pros                                            | Cons                                              |
| -------------------------------------------------------------------------- | ----------------------------------------------- | ------------------------------------------------- |
| `src/Generated/<Spec>/` — checked into your application repo                | Versioned with the app                          | Diffs explode on regeneration                     |
| Separate package — `composer require yourorg/opcua-<vendor>`                | Clean separation, reusable across apps          | Extra repo to maintain                            |
| Generated in CI, not committed                                              | No diffs to review                              | CI must have access to the XML; gen is a build step |
| Pre-generated published package (for OPC Foundation specs)                  | Zero generation cost                            | Limited to specs the publisher covers             |

For vendor-specific code, the second option (a private package)
scales best when multiple applications share the vendor.

## Updating after a regeneration

When the source NodeSet2.xml changes — vendor publishes a new
revision, the server's `dump:nodeset` differs after a firmware
update — regenerate and re-deploy:

<!-- @steps -->
- **Regenerate** in the same `--output` dir. The generator
  overwrites existing files.

- **Diff `src/Generated/<Spec>/`** — review what changed
  (renamed enums, new fields, deprecated NodeIds).

- **Adapt your application code** to any breaking changes.

- **Re-deploy** with the new code.
<!-- @endsteps -->

The breakage surface for breaking spec changes is your
application code referencing the renamed / removed names. The
underlying `loadGeneratedTypes()` mechanism is stable.

## PSR-4 autoload

Whatever `--namespace=…` you used at generation, point your
project's `composer.json` PSR-4 entry at the matching directory:

<!-- @code-block language="text" label="composer.json" -->
```text
{
    "autoload": {
        "psr-4": {
            "App\\OpcUa\\MyVendor\\": "src/Generated/MyVendor/"
        }
    }
}
```
<!-- @endcode-block -->

Run `composer dump-autoload` after generating into a new
directory — Composer's classmap optimisation makes the loaded
class set discoverable without re-scanning on every request.

## When the auto-cast doesn't trigger

A loaded registrar should produce typed values out of every
matching read. When it doesn't:

| Symptom                                              | Cause                                                |
| ---------------------------------------------------- | ---------------------------------------------------- |
| Read returns raw `int` instead of enum               | `getEnumMappings()` doesn't include this DataType — server's namespace index differs from the XML's |
| Read returns `ExtensionObject` instead of DTO        | Codec not registered for this DataType — same root cause |
| `EnumClass::from(N)` throws `ValueError`              | Server sent a value outside the enum's case range — the library catches this and returns raw `int` |

Diagnose with `--debug` to see the wire payload. The
[opcua-client-nodeset NodeId-constants page](https://github.com/php-opcua/opcua-client-nodeset/blob/master/docs/concepts/node-id-constants.md#section-namespace-indices-are-runtime-dependent)
covers the namespace-index mismatch case.
