---
eyebrow: 'Docs · Connecting'
lede:    'Three identity flavours: anonymous, username/password, X.509. Plus a client certificate the channel itself needs when security is on. Flags for all four, environment patterns for production.'

see_also:
  - { href: './security-policies.md',     meta: '5 min' }
  - { href: './trust-store-workflow.md',  meta: '5 min' }
  - { href: 'https://github.com/php-opcua/opcua-client/blob/master/docs/security/overview.md', meta: 'external', label: 'opcua-client — security' }

prev: { label: 'Security policies',   href: './security-policies.md' }
next: { label: 'Trust store workflow', href: './trust-store-workflow.md' }
---

# Credentials

OPC UA has two independent identity axes:

1. **Application identity** — proved by the client certificate.
   Needed whenever the channel uses any non-`None` security
   policy.
2. **User identity** — proved by username/password or by an
   X.509 user certificate. Configured per session.

The CLI flags cover both.

## The flags

| Flag                          | Purpose                                                       |
| ----------------------------- | ------------------------------------------------------------- |
| `--cert=<path>`               | Client (application) certificate, PEM                         |
| `--key=<path>`                | Client (application) private key, PEM                         |
| `--ca=<path>`                 | CA bundle for chain validation                                |
| `-u, --username=<user>`       | Username for session-level identity                           |
| `-p, --password=<pass>`       | Password for session-level identity                           |

The CLI does **not** currently have a flag for user X.509
certificate — that path is library-only.

## Anonymous (default)

The default. No `--username` / `--password` flags.

<!-- @code-block language="bash" label="terminal — anonymous" -->
```bash
opcua-cli browse opc.tcp://plc.local:4840 \
    -s Basic256Sha256 \
    -m SignAndEncrypt \
    --cert=/etc/opcua/client.pem \
    --key=/etc/opcua/client.key
```
<!-- @endcode-block -->

The channel is secured (cert + key) but the session is
anonymous. Most servers allow anonymous reads on published
metrics; writes typically require a real identity.

## Username / password

Add `-u` and `-p`:

<!-- @code-block language="bash" label="terminal — username" -->
```bash
opcua-cli write opc.tcp://plc.local:4840 "ns=2;s=PLC/Setpoint" 42.5 \
    -s Basic256Sha256 \
    -m SignAndEncrypt \
    --cert=/etc/opcua/client.pem \
    --key=/etc/opcua/client.key \
    -u operator \
    -p "$OPCUA_PASSWORD"
```
<!-- @endcode-block -->

The password is **encrypted under the channel's asymmetric keys**
before transit — using whichever `SecurityPolicy` is active on
the relevant `UserTokenPolicy` (the server publishes it via
discovery). Cleartext never crosses the wire when the channel is
`Sign` or `SignAndEncrypt`.

<!-- @callout variant="warning" -->
Username over a `-s None` channel transmits the password in
**cleartext**. The CLI does not prevent the combination but it
is unsafe. If your server requires username auth, also require
at least `Sign` mode.
<!-- @endcallout -->

## Sourcing secrets

Three options, from worst to best:

| Source                                          | Notes                                       |
| ----------------------------------------------- | ------------------------------------------- |
| `-p literalvalue` on the command line           | **Visible in `ps`/`top`/shell history**. Use only for ad-hoc one-offs |
| `-p "$OPCUA_PASSWORD"` from environment         | Not visible in `ps`; visible in `printenv` and the shell's exported env |
| Read from a file at invocation time             | Cleanest: `--password "$(cat /etc/opcua/secret)"` |

In CI, prefer secrets injected as environment variables (GitHub
Actions secrets, GitLab masked variables, Vault sidecar
injection). Avoid checking literals into command lines in
scripts that go to source control.

## Client certificate

Required whenever `--security-policy` is anything other than
`None`. PEM-formatted certificate and matching private key.

<!-- @code-block language="bash" label="terminal — cert + key" -->
```bash
opcua-cli browse opc.tcp://plc.local:4840 \
    -s Basic256Sha256 \
    -m SignAndEncrypt \
    --cert=/etc/opcua/client.pem \
    --key=/etc/opcua/client.key
```
<!-- @endcode-block -->

The cert is the client application's identity; the server's
trust store decides whether to accept it.

### Generating a client certificate

A minimal RSA client cert with OpenSSL:

<!-- @code-block language="bash" label="terminal — generate" -->
```bash
# 1. Generate the private key
openssl genpkey -algorithm RSA -out client.key -pkeyopt rsa_keygen_bits:2048

# 2. Generate the certificate (self-signed, 1 year validity)
openssl req -new -x509 \
    -key client.key \
    -out client.pem \
    -days 365 \
    -subj "/CN=opcua-cli/O=YourOrg" \
    -addext "subjectAltName=URI:urn:opcua-cli"
```
<!-- @endcode-block -->

The `URI:urn:opcua-cli` Subject Alternative Name (SAN) is what
OPC UA uses as the **Application URI** — the server's audit log
references it. Pick something meaningful per environment.

For full certificate generation patterns (CA-signed, ECC, …),
see [`opcua-client` — security](https://github.com/php-opcua/opcua-client/blob/master/docs/security/overview.md).

## Auto-generated certificate

If you pass `-s Basic256Sha256` without `--cert` / `--key`, the
library generates a self-signed certificate on the fly. **Each
invocation generates a new one** — the fingerprint changes every
time, and the server's trust store sees a new identity per
invocation.

This is convenient for development but disastrous for production:

| Aspect                              | Auto-generated   | Provided via `--cert`               |
| ----------------------------------- | ---------------- | ----------------------------------- |
| Cost on first connect               | Zero setup        | Generate once, distribute            |
| Stable identity across invocations | **No**            | Yes                                  |
| Server-side trust manageable        | Painful (rotating fingerprints) | One fingerprint to trust |
| Production-suitable                | No                | Yes                                  |

For any non-trivial use, generate a cert once and pass it.

## CA bundle (`--ca`)

When the server expects full chain validation on the client cert
(rare in OPC UA, common in HTTP-like protocols), supply the CA
bundle:

<!-- @code-block language="bash" label="terminal — CA chain" -->
```bash
opcua-cli browse opc.tcp://plc.local:4840 \
    -s Basic256Sha256 -m SignAndEncrypt \
    --cert=/etc/opcua/client.pem \
    --key=/etc/opcua/client.key \
    --ca=/etc/opcua/ca-bundle.pem
```
<!-- @endcode-block -->

Most OPC UA servers do not require this — the trust model on
the OPC UA side is typically per-certificate trust, not chain-
based. Use `--ca` only when the server documentation says so.

## Worked combinations

| Server requires                      | CLI flags                                                   |
| ------------------------------------ | ----------------------------------------------------------- |
| Unsecured anonymous                  | (none)                                                      |
| Sign + anonymous                     | `-s Basic256Sha256 -m Sign --cert=... --key=...`           |
| SignAndEncrypt + anonymous           | `-s Basic256Sha256 -m SignAndEncrypt --cert=... --key=...` |
| SignAndEncrypt + username            | `-s Basic256Sha256 -m SignAndEncrypt --cert=... --key=... -u user -p pass` |
| SignAndEncrypt + X.509 user identity | Library-only (no CLI flag yet)                              |
| Internal CA chain validation         | Add `--ca=...`                                              |

## Common pitfalls

- **`--cert` without `--key`** — every non-`None` policy needs
  both. The CLI exits with a configuration error.
- **Mismatched cert and key.** The library refuses to load the
  pair. Verify with
  `openssl x509 -modulus -in client.pem | openssl md5` and
  `openssl rsa -modulus -in client.key | openssl md5` —
  the two MD5s must match.
- **Cert expired.** Most servers refuse expired certs with
  `BadCertificateTimeInvalid`. Generate a fresh one.
- **Spaces in paths on Windows.** `cmd.exe` needs the path
  in quotes: `--cert="C:\Program Files\opcua\client.pem"`.
- **Forgetting to `chmod 600` the key file.** Not enforced by
  the CLI, but the standard secret-handling discipline applies.
