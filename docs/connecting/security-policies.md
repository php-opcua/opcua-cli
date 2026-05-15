---
eyebrow: 'Docs · Connecting'
lede:    'Two flags pick the security: -s/--security-policy and -m/--security-mode. Defaults to None+None (development); production needs Basic256Sha256+SignAndEncrypt or stronger.'

see_also:
  - { href: './credentials.md',              meta: '5 min' }
  - { href: './trust-store-workflow.md',     meta: '5 min' }
  - { href: '../commands/endpoints.md',      meta: '3 min' }

prev: { label: 'Endpoint URLs',           href: './endpoint-urls.md' }
next: { label: 'Credentials',             href: './credentials.md' }
---

# Security policies

The OPC UA secure channel is configured with two parameters: a
**security policy** (the algorithm suite) and a **security
mode** (None / Sign / SignAndEncrypt). The CLI exposes them as
two flags.

## The flags

| Short | Long                       | Default | Effect                                                            |
| ----- | -------------------------- | ------- | ----------------------------------------------------------------- |
| `-s`  | `--security-policy=POLICY` | `None`  | Algorithm suite name                                              |
| `-m`  | `--security-mode=MODE`     | `None`  | `None`, `Sign`, or `SignAndEncrypt`                              |

## Accepted policy values

The `--security-policy` flag accepts a policy **name** (short form)
or its full URI:

| Name                       | URI suffix (after `…SecurityPolicy#`)    |
| -------------------------- | ---------------------------------------- |
| `None`                     | `None`                                   |
| `Basic128Rsa15`            | `Basic128Rsa15` (deprecated)             |
| `Basic256`                 | `Basic256` (deprecated)                  |
| `Basic256Sha256`           | `Basic256Sha256`                         |
| `Aes128Sha256RsaOaep`      | `Aes128_Sha256_RsaOaep`                  |
| `Aes256Sha256RsaPss`       | `Aes256_Sha256_RsaPss`                   |
| `EccNistP256`              | `ECC_nistP256`                           |
| `EccNistP384`              | `ECC_nistP384`                           |
| `EccBrainpoolP256r1`       | `ECC_brainpoolP256r1`                    |
| `EccBrainpoolP384r1`       | `ECC_brainpoolP384r1`                    |

The CLI tries the short form first (`--security-policy=Basic256Sha256`)
and falls back to parsing the full URI. Either works.

ECC policies are implemented per OPC UA 1.05.4 but are
experimental in practice — see [`opcua-client` —
security](https://github.com/php-opcua/opcua-client/blob/master/docs/security/overview.md).
For production, prefer the RSA policies.

## Accepted mode values

| Mode               | Meaning                                                |
| ------------------ | ------------------------------------------------------ |
| `None`             | No signing, no encryption                              |
| `Sign`             | Every message signed; nothing encrypted                |
| `SignAndEncrypt`   | Every message signed and encrypted                     |

## Examples

### Unsecured (default)

<!-- @code-block language="bash" label="terminal — no security" -->
```bash
opcua-cli browse opc.tcp://plc.local:4840
```
<!-- @endcode-block -->

Equivalent to `-s None -m None`. Use only for development /
local test servers.

### Recommended production default

<!-- @code-block language="bash" label="terminal — basic256sha256" -->
```bash
opcua-cli browse opc.tcp://plc.local:4840 \
    -s Basic256Sha256 \
    -m SignAndEncrypt \
    --cert=/etc/opcua/client.pem \
    --key=/etc/opcua/client.key
```
<!-- @endcode-block -->

`Basic256Sha256` is the most broadly-supported strong policy.
`SignAndEncrypt` mode prevents both tampering and eavesdropping
on the wire. The `--cert` and `--key` flags supply the client
certificate the channel needs; see [Credentials](./credentials.md).

### Strongest RSA

<!-- @code-block language="bash" label="terminal — strongest RSA" -->
```bash
opcua-cli browse opc.tcp://plc.local:4840 \
    -s Aes256Sha256RsaPss \
    -m SignAndEncrypt \
    --cert=/etc/opcua/client.pem \
    --key=/etc/opcua/client.key
```
<!-- @endcode-block -->

`Aes256Sha256RsaPss` is the strongest RSA combination defined in
the spec. Same security model as `Basic256Sha256`, slightly newer
primitives. Use it when the server advertises it.

### Discovering what's available

Before configuring policy + mode, probe:

<!-- @code-block language="bash" label="terminal — discover" -->
```bash
opcua-cli endpoints opc.tcp://plc.local:4840
```
<!-- @endcode-block -->

The reply lists every (policy, mode) the server advertises. Pick
from that list — anything else will fail with
`BadSecurityPolicyRejected`.

## What happens under the covers

When you pass `-s Basic256Sha256 -m SignAndEncrypt`:

<!-- @steps -->
- **The CLI builds the client** with `setSecurityPolicy(SecurityPolicy::Basic256Sha256)`
  and `setSecurityMode(SecurityMode::SignAndEncrypt)`.

- **`connect()` runs discovery** if the server certificate or
  required identity-token policy IDs are unknown.

- **`OpenSecureChannel` (OPN)** uses RSA-OAEP asymmetric
  encryption for the initial exchange and derives AES-CBC + HMAC
  symmetric keys.

- **Every subsequent message** (CreateSession, ActivateSession,
  Read, Write, …) goes through the encrypted channel.
<!-- @endsteps -->

The CLI's `--debug` flag prints each step's wire-level summary.
Useful when debugging a failed handshake.

## What changes when you switch policies

| You change                    | Server-side impact                                                  |
| ----------------------------- | ------------------------------------------------------------------- |
| `None` → `Basic256Sha256 Sign`| Per-message HMAC; ~5% throughput penalty                            |
| `Sign` → `SignAndEncrypt`     | AES-CBC encryption added; ~10% additional CPU                       |
| RSA → ECC                     | Faster handshake (smaller signatures), smaller bandwidth — but server support is rare |
| `Basic256Sha256` → `Aes256Sha256RsaPss` | Newer primitives — server must support both ends         |

For most deployments, the right choice is **`Basic256Sha256` +
`SignAndEncrypt`**. Upgrade to `Aes256Sha256RsaPss` when every
relevant server supports it.

## Failure modes

| Wire error                          | Cause                                                |
| ----------------------------------- | ---------------------------------------------------- |
| `BadSecurityPolicyRejected`         | Server doesn't offer the policy you requested        |
| `BadSecurityChecksFailed`           | Server's certificate trust failed — see [Trust store workflow](./trust-store-workflow.md) |
| `BadCertificateUntrusted`           | The server's cert is not in your trust store         |
| `BadCertificateInvalid`             | Client certificate is malformed                      |
| `BadIdentityTokenInvalid`           | Username/password or X.509 identity token rejected   |
| `BadCertificateTimeInvalid`         | Certificate not yet valid or expired                 |

The CLI surfaces these as stderr messages and exits non-zero.
For untrusted-certificate errors specifically, it prints
helpful follow-up commands. See [Exceptions and
errors](../reference/exceptions-and-errors.md).

## Without a client certificate

If you set a non-`None` policy but **don't** pass `--cert` /
`--key`, the library generates a self-signed certificate on the
fly. Fine for dev; not for production — the certificate changes
every CLI invocation, the server's trust store sees a new
identity each time. See
[Credentials · Auto-generated certificate](./credentials.md#section-auto-generated-certificate).
