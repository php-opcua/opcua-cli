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
files keyed by **SHA-1** fingerprint (hex pairs joined by `:`,
e.g. `a1:b2:c3:...`).

## The flow

The canonical first-contact pattern:

<!-- @steps -->
- **`trust <endpoint>`** downloads the server's certificate and
  records it in the trust store. Required only the first time
  you connect to a secured server. The trust store must be
  configured (via `--trust-store=<path>` or `--trust-policy=...`);
  otherwise the command exits with
  `"No trust store configured."`.

- **`trust:list`** enumerates what's in the store — fingerprint,
  subject, expiry. Useful for auditing.

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
| `--trust-store=<path>`   | (no default — must be set on the command line, or the trust store stays unconfigured) | Path to the trust-store directory |

The CLI does not install a default trust store automatically.
Pass `--trust-store=<path>` (or `--trust-policy=...`, which also
implicitly enables the store) to opt in. Without either, the
trust commands print `"No trust store configured."` and exit
non-zero.

When `--trust-store=<path>` is omitted, the underlying
`FileTrustStore` resolves the directory itself — `~/.opcua/` on
POSIX, `%APPDATA%\opcua\` on Windows. That default applies only
**after** the CLI instantiates the store with one of the trust
flags. See
[`opcua-client`'s `FileTrustStore`](https://github.com/php-opcua/opcua-client/blob/master/docs/security/trust-store.md).

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
opcua-cli trust opc.tcp://plc.local:4840 --trust-store=/etc/opcua/trust
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="output" -->
```text
Status:      Trusted
Fingerprint: a1:b2:c3:d4:e5:f6:78:90:12:34:56:78:90:12:34:56:78:90:ab:cd
Subject:     PLC-Server
Expires:     2027-01-01T00:00:00+00:00
```
<!-- @endcode-block -->

Four fields on stdout. The fingerprint is the **SHA-1 hash of the
DER bytes**, rendered as hex pairs joined by `:` — 20 bytes / 40
hex chars + 19 colons. `Subject` is the X.509 subject CN (or
`Unknown` if parsing fails). `Expires` is the certificate's
`notAfter`, formatted with `DateTimeInterface::format('c')`
(ISO 8601 with timezone), or `"N/A"`.

The command does **not** print where the file was written. The
trust store keys files by the same SHA-1 fingerprint shown above
(see [`opcua-client`'s `FileTrustStore::computeFingerprint`](https://github.com/php-opcua/opcua-client/blob/master/src/TrustStore/FileTrustStore.php)).

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

Enumerate trusted certificates. No connection required (but the
trust store must be configured — see above).

<!-- @code-block language="text" label="signature" -->
```text
opcua-cli trust:list [--trust-store=path] [global-options]
```
<!-- @endcode-block -->

<!-- @code-block language="bash" label="terminal" -->
```bash
opcua-cli trust:list --trust-store=/etc/opcua/trust
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="output" -->
```text

Fingerprint: a1:b2:c3:d4:e5:f6:78:90:12:34:56:78:90:12:34:56:78:90:ab:cd
Subject:     PLC-1
Expires:     2027-01-01T00:00:00+00:00

Fingerprint: 89:a0:b1:c2:d3:e4:f5:67:89:01:23:45:67:89:01:23:45:67:89:0a
Subject:     PLC-2
Expires:     2027-03-15T00:00:00+00:00
```
<!-- @endcode-block -->

One record block per certificate, separated by a blank line.
There is no aligned-column header row; each block always shows
the field name and value.

With `--json`:

<!-- @code-block language="text" label="JSON" -->
```text
[
  {
    "Fingerprint": "a1:b2:c3:d4:e5:f6:78:90:12:34:56:78:90:12:34:56:78:90:ab:cd",
    "Subject": "PLC-1",
    "Expires": "2027-01-01T00:00:00+00:00"
  },
  {
    "Fingerprint": "89:a0:b1:c2:d3:e4:f5:67:89:01:23:45:67:89:01:23:45:67:89:0a",
    "Subject": "PLC-2",
    "Expires": "2027-03-15T00:00:00+00:00"
  }
]
```
<!-- @endcode-block -->

A JSON array — no wrapping `trustedCertificates` envelope. Field
names match the console output (PascalCase). `Expires` is an ISO
8601 datetime (`format('c')`), not a date-only string.

Pipe to `jq` to filter (e.g. find certs expiring within 30 days
for proactive rotation).

## `trust:remove <fingerprint>`

Drop a cert by its **SHA-1** fingerprint. No connection required.

<!-- @code-block language="text" label="signature" -->
```text
opcua-cli trust:remove <fingerprint> [--trust-store=path] [global-options]
```
<!-- @endcode-block -->

<!-- @code-block language="bash" label="terminal" -->
```bash
opcua-cli trust:remove a1:b2:c3:d4:e5:f6:78:90:12:34:56:78:90:12:34:56:78:90:ab:cd \
    --trust-store=/etc/opcua/trust
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="output" -->
```text
Removed certificate: a1:b2:c3:d4:e5:f6:78:90:12:34:56:78:90:12:34:56:78:90:ab:cd
```
<!-- @endcode-block -->

The argument is the **full** fingerprint as printed by `trust:list`
— no partial matching, no aliases.

## How they map to the library

| You ran                                  | The library call                                          |
| ---------------------------------------- | --------------------------------------------------------- |
| `trust <endpoint>`                       | `$client->trustCertificate($serverDer)` after `getEndpoints()` |
| `trust:list`                             | `$client->getTrustStore()->getTrustedCertificates()`       |
| `trust:remove <fingerprint>`             | `$client->untrustCertificate($fingerprint)`                |

See [`opcua-client` — trust store](https://github.com/php-opcua/opcua-client/blob/master/docs/security/trust-store.md).

## Exit codes

| Command           | `0` means                          | `1` means                                  |
| ----------------- | ---------------------------------- | ------------------------------------------ |
| `trust`           | Certificate added to store         | Connection failed, no cert on endpoint, no trust store configured |
| `trust:list`      | Listed successfully (even if empty)| No trust store configured, store unreadable |
| `trust:remove`    | `untrustCertificate()` returned     | Bad fingerprint argument, store unwriteable |

## Common pitfalls

- **Custom `--trust-store` mismatch.** If the daemon side / your
  application uses `--trust-store=/etc/opcua`, the CLI must use
  the same — otherwise the cert lands somewhere the application
  doesn't read.
- **First-connect on hostile networks.** See the warning above.
  Prefer out-of-band certificate distribution for production.
- **Forgetting to trust after a server cert rotation.** The old
  fingerprint stays in the store; the new one is unknown. `trust`
  the new endpoint, then `trust:remove` the old fingerprint.
- **Using SHA-256 to derive a fingerprint manually.** The store
  uses **SHA-1**. To match what's on disk, run
  `openssl dgst -sha1 -hex < cert.der` and uppercase / colon-pair
  the result — not `openssl dgst -sha256 ...`.
