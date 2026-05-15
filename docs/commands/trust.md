---
eyebrow: 'Docs · Command · trust'
lede:    'Three subcommands for the trust store: trust adds a server''s cert, trust:list shows what''s trusted, trust:remove removes a fingerprint. The first-contact workflow for every secured server.'

see_also:
  - { href: '../connecting/trust-store-workflow.md', meta: '5 min' }
  - { href: '../recipes/batch-trust-rollout.md',     meta: '4 min' }
  - { href: 'https://github.com/php-opcua/opcua-client/blob/master/docs/security/trust-store.md', meta: 'external', label: 'opcua-client — trust store' }

prev: { label: 'explore',           href: './explore.md' }
next: { label: 'generate:nodeset',  href: './generate-nodeset.md' }
---

# `trust` (and `trust:list`, `trust:remove`)

Three commands that share a state — the on-disk **trust store**
of accepted server certificates. The store is a directory of DER
files keyed by SHA-256 fingerprint.

## The flow

The canonical first-contact pattern:

<!-- @steps -->
- **`trust <endpoint>`** downloads the server's certificate and
  records it in the trust store. Required only the first time
  you connect to a secured server.

- **`trust:list`** enumerates what's in the store — fingerprint,
  subject, validity window. Useful for auditing.

- **`trust:remove <fingerprint>`** drops a cert from the store.
  Use it when rotating a server's certificate or when
  decommissioning.
<!-- @endsteps -->

For the operational narrative — when each fits in a deployment —
see [Connecting · Trust store
workflow](../connecting/trust-store-workflow.md).

## Shared options

All three commands accept:

| Option                   | Default                | Effect                          |
| ------------------------ | ---------------------- | ------------------------------- |
| `--trust-store=<path>`   | `~/.opcua/`            | Override the trust-store directory |

The default location follows
[`opcua-client`'s `FileTrustStore`](https://github.com/php-opcua/opcua-client/blob/master/docs/security/trust-store.md)
— `~/.opcua/` on POSIX, `%APPDATA%\opcua\` on Windows.

## `trust <endpoint>`

Connects to the server **without** certificate validation,
fetches its certificate, and stores it.

<!-- @code-block language="text" label="signature" -->
```text
opcua-cli trust <endpoint> [--trust-store=path] [global-options]
```
<!-- @endcode-block -->

<!-- @code-block language="bash" label="terminal" -->
```bash
opcua-cli trust opc.tcp://plc.local:4840
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="output" -->
```text
Server certificate stored.
  Fingerprint:  a1b2c3d4e5f6...
  Subject:      CN=PLC-Server, O=ACME
  Valid:        2026-01-01 to 2027-01-01
  Stored at:    /home/operator/.opcua/trusted/a1b2c3d4e5f6.der
```
<!-- @endcode-block -->

After this, normal commands (`browse`, `read`, `write`) against
the same server work — the trust store has the cert, validation
succeeds.

<!-- @callout variant="warning" -->
`trust` skips trust validation **for the download step itself**.
Anyone on the network between you and the server could substitute
their certificate at that moment, and you'd record the attacker's
cert as trusted. In hostile networks, deliver the certificate
out-of-band (operator USB stick, vendor-signed bundle) and use
the `--trust-store` directly with that file.
<!-- @endcallout -->

## `trust:list`

Enumerate trusted certificates. No connection required.

<!-- @code-block language="text" label="signature" -->
```text
opcua-cli trust:list [--trust-store=path] [global-options]
```
<!-- @endcode-block -->

<!-- @code-block language="bash" label="terminal" -->
```bash
opcua-cli trust:list
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="output" -->
```text
Fingerprint                                                              Subject                       Valid until
a1b2c3d4e5f6789012345678901234567890abcdef12345678901234567890abcd      CN=PLC-1, O=ACME              2027-01-01
89a0b1c2d3e4f56789012345678901234567890abcdef12345678901234567890ab     CN=PLC-2, O=ACME              2027-03-15
ff03c2a7b6e5d4c3b2a190817263544536271809abcdef01234567890123456789      CN=HMI-Gateway, O=Contoso     2026-12-20

3 trusted certificates.
```
<!-- @endcode-block -->

With `--json`:

<!-- @code-block language="text" label="JSON" -->
```text
{
  "trustedCertificates": [
    {"fingerprint": "a1b2c3d4...", "subject": "CN=PLC-1, O=ACME", "validUntil": "2027-01-01"},
    {"fingerprint": "89a0b1c2...", "subject": "CN=PLC-2, O=ACME", "validUntil": "2027-03-15"},
    {"fingerprint": "ff03c2a7...", "subject": "CN=HMI-Gateway, O=Contoso", "validUntil": "2026-12-20"}
  ]
}
```
<!-- @endcode-block -->

Pipe to `jq` to filter (e.g. find certs expiring within 30 days
for proactive rotation).

## `trust:remove <fingerprint>`

Drop a cert by SHA-256 fingerprint. No connection required.

<!-- @code-block language="text" label="signature" -->
```text
opcua-cli trust:remove <fingerprint> [--trust-store=path] [global-options]
```
<!-- @endcode-block -->

<!-- @code-block language="bash" label="terminal" -->
```bash
opcua-cli trust:remove a1b2c3d4e5f6789012345678901234567890abcdef12345678901234567890abcd
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="output" -->
```text
Removed: a1b2c3d4e5f6...
```
<!-- @endcode-block -->

The argument is the **full** fingerprint as printed by `trust:list`
— no partial matching, no aliases.

## How they map to the library

| You ran                                  | The library call                                          |
| ---------------------------------------- | --------------------------------------------------------- |
| `trust <endpoint>`                       | `$trustStore->trust($serverDer)` after `getEndpoints()`   |
| `trust:list`                             | `$trustStore->getTrustedCertificates()`                   |
| `trust:remove <fingerprint>`             | `$trustStore->untrust($fingerprint)`                      |

See [`opcua-client` — trust store](https://github.com/php-opcua/opcua-client/blob/master/docs/security/trust-store.md).

## Exit codes

| Command           | `0` means                          | `1` means                                  |
| ----------------- | ---------------------------------- | ------------------------------------------ |
| `trust`           | Certificate added to store         | Connection failed, write failed            |
| `trust:list`      | Listed successfully (even if empty)| Trust-store path unreadable                |
| `trust:remove`    | Cert removed (or absent)            | Bad fingerprint argument, file unwriteable |

## Common pitfalls

- **Custom `--trust-store` mismatch.** If the daemon side / your
  application uses `--trust-store=/etc/opcua`, the CLI must use
  the same — otherwise the cert ends up in `~/.opcua` and the
  application doesn't see it.
- **First-connect on hostile networks.** See the warning above.
  Prefer out-of-band certificate distribution for production.
- **Forgetting to trust after a server cert rotation.** The old
  fingerprint stays in the store; the new one is unknown. `trust`
  the new endpoint, then `trust:remove` the old fingerprint.
