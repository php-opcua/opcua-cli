---
eyebrow: 'Docs · Connecting'
lede:    'First contact with a secured server fails until you trust its certificate. Run trust, then re-run your command. The CLI prints the exact follow-up needed.'

see_also:
  - { href: '../commands/trust.md',                     meta: '4 min' }
  - { href: './security-policies.md',                   meta: '5 min' }
  - { href: '../recipes/batch-trust-rollout.md',        meta: '4 min' }

prev: { label: 'Credentials',   href: './credentials.md' }
next: { label: 'Output formats', href: '../output/output-formats.md' }
---

# Trust store workflow

The CLI's trust store is a directory of accepted server
certificates, identified by SHA-256 fingerprint. Most secured
OPC UA servers will be rejected on first connect — their cert
isn't in the store yet. This page is the canonical workflow to
get past that.

## The path

<!-- @code-block language="text" label="flow" -->
```text
1. Connect with security on        →  Fails: UntrustedCertificateException
2. CLI prints follow-up commands   →  "opcua-cli trust <endpoint>"
3. Run trust                       →  Server certificate stored
4. Re-run the original command     →  Succeeds
```
<!-- @endcode-block -->

The CLI puts the follow-up commands directly in the error
output — you don't memorise the sequence.

## In action

### Step 1 — first connect fails

<!-- @code-block language="bash" label="terminal — first attempt" -->
```bash
$ opcua-cli browse opc.tcp://plc.local:4840 \
      -s Basic256Sha256 -m SignAndEncrypt \
      --cert=/etc/opcua/client.pem --key=/etc/opcua/client.key

Error: Server certificate not trusted.
  Fingerprint: a1b2c3d4e5f6...

To trust this certificate, run:
  opcua-cli trust opc.tcp://plc.local:4840

To list trusted certificates:
  opcua-cli trust:list

To skip trust validation for this command:
  opcua-cli browse ... --no-trust-policy
```
<!-- @endcode-block -->

Exit code: `1`. The CLI exits non-zero with a non-empty stderr
— suitable for CI failure detection.

### Step 2 — trust the cert

<!-- @code-block language="bash" label="terminal — trust" -->
```bash
$ opcua-cli trust opc.tcp://plc.local:4840

Server certificate stored.
  Fingerprint:  a1b2c3d4e5f6...
  Subject:      CN=PLC-Server, O=ACME
  Valid:        2026-01-01 to 2027-01-01
  Stored at:    /home/operator/.opcua/trusted/a1b2c3d4e5f6.der
```
<!-- @endcode-block -->

Verify the fingerprint matches the one you expect (per device
documentation, vendor email, out-of-band channel). On a hostile
network, the download itself is the attack surface; see
[Securing the bootstrap](#section-securing-the-bootstrap) below.

### Step 3 — retry

<!-- @code-block language="bash" label="terminal — retry succeeds" -->
```bash
$ opcua-cli browse opc.tcp://plc.local:4840 \
      -s Basic256Sha256 -m SignAndEncrypt \
      --cert=/etc/opcua/client.pem --key=/etc/opcua/client.key

Server (Object)
DeviceSet (Object)
Aliases (Object)
…
```
<!-- @endcode-block -->

The trust store has the cert; subsequent connects validate
successfully.

## Trust policies

The CLI sends `--trust-policy=...` to control how strict the
validation is:

| Policy                   | Validation                                                          |
| ------------------------ | ------------------------------------------------------------------- |
| (default — not set)      | Accept anything (insecure; equivalent to `--no-trust-policy`)       |
| `fingerprint`            | Cert's SHA-256 fingerprint must be in the trust store               |
| `fingerprint+expiry`     | Fingerprint match **and** cert within its validity window           |
| `full`                   | Full X.509 chain validation against the CA bundle in the trust store |

Default is no trust policy — the CLI does not validate the cert
unless you set `--trust-policy`. This is convenient for dev but
unsafe for production:

<!-- @code-block language="bash" label="terminal — production posture" -->
```bash
opcua-cli browse opc.tcp://plc.local:4840 \
    -s Basic256Sha256 -m SignAndEncrypt \
    --cert=/etc/opcua/client.pem --key=/etc/opcua/client.key \
    --trust-policy=fingerprint+expiry
```
<!-- @endcode-block -->

`fingerprint+expiry` is the production default — strict
fingerprint matching plus expiry check.

## Custom trust store location

Both `trust` and the connect-time validation accept
`--trust-store=PATH`:

<!-- @code-block language="bash" label="terminal — custom store" -->
```bash
opcua-cli trust opc.tcp://plc.local:4840 --trust-store=/etc/opcua/trust
opcua-cli browse opc.tcp://plc.local:4840 ... --trust-store=/etc/opcua/trust
```
<!-- @endcode-block -->

Use a system-wide path (`/etc/opcua/trust`) for shared deployments;
use the default `~/.opcua/` for per-user installs.

## Skipping validation temporarily

`--no-trust-policy` disables trust validation for one command —
useful for one-off diagnostic invocations against a server you
don't intend to trust permanently:

<!-- @code-block language="bash" label="terminal — skip" -->
```bash
opcua-cli endpoints opc.tcp://unknown-server:4840 --no-trust-policy
```
<!-- @endcode-block -->

The `endpoints` command runs over a transient unsecured channel
anyway, so this is fine for discovery. **Do not** ship
`--no-trust-policy` to production for non-discovery commands —
it eliminates the protection that trust store provides.

## Securing the bootstrap

`opcua-cli trust` downloads the cert *over an unsecured
connection*. On a trusted network this is fine; on a hostile
one, an attacker between you and the server can substitute their
cert and you record the attacker's identity as trusted.

Two safer alternatives:

1. **Out-of-band cert delivery.** Have the vendor / operator
   bring you the server cert via USB / signed email / encrypted
   bundle. Verify the fingerprint matches their documentation.
   Then `trust:add` it directly (currently library-only — for
   the CLI workflow, copy the `.der` into the trust-store
   directory by hand).

2. **Bootstrap over a known-good network.** Run `trust` from a
   commissioning workstation on a physically-controlled segment.
   Lock down the trust store afterwards.

For production deployments, treat the trust step like a
package signing key — pinned out-of-band, audited.

## Listing and removing

| Command                                              | What                                |
| ---------------------------------------------------- | ----------------------------------- |
| `opcua-cli trust:list`                               | Print every trusted cert            |
| `opcua-cli trust:list --json`                        | Same, machine-readable              |
| `opcua-cli trust:remove <fingerprint>`               | Drop a cert by fingerprint          |

Use `trust:remove` when:

- The server rotates its certificate (the new fingerprint
  arrives; the old one is dead weight).
- A device is decommissioned.
- A cert was added by mistake.

See [Commands · trust](../commands/trust.md) for the full
reference.

## CI pattern

Trust rollouts in CI are typically scripted:

<!-- @code-block language="bash" label="ci pattern" -->
```bash
# In the CI setup step:
mkdir -p ~/.opcua/trusted
# Drop the pre-vetted cert files into ~/.opcua/trusted/

# Test runs without needing online trust calls
opcua-cli browse opc.tcp://test-server:4840 \
    -s Basic256Sha256 -m SignAndEncrypt \
    --cert=$CLIENT_CERT --key=$CLIENT_KEY \
    --trust-policy=fingerprint+expiry
```
<!-- @endcode-block -->

The CI runner gets the trusted certs pre-installed; the test
runs in `--trust-policy=fingerprint+expiry` posture. No live
`trust` call needed — and no hostile-network exposure during
bootstrap.

See [Recipes · Batch trust rollout](../recipes/batch-trust-rollout.md)
for the operator-side script.
