---
eyebrow: 'Docs · Recipes'
lede:    'Trust dozens of servers in one script — and skip the hostile-network bootstrap. Bulk operator script + per-server check.'

see_also:
  - { href: '../connecting/trust-store-workflow.md',     meta: '5 min' }
  - { href: '../commands/trust.md',                      meta: '4 min' }
  - { href: 'https://github.com/php-opcua/opcua-client/blob/master/docs/security/trust-store.md', meta: 'external', label: 'opcua-client — trust store' }

prev: { label: 'CI smoke test', href: './ci-smoke-test.md' }
next: { label: 'Live monitoring', href: './live-monitoring-with-watch.md' }
---

# Batch trust rollout

Trusting many servers manually doesn't scale. This recipe is the
two-shape script: in trusted-network rollouts, use the CLI to
fetch each cert; in hostile-network rollouts, distribute certs
out-of-band and load them into the trust store directly.

## Shape 1 — trusted network rollout

For a closed plant network with no eavesdroppers, scripting the
`trust` command per server is fine:

<!-- @code-block language="bash" label="bash — trust-many.sh" -->
```bash
#!/usr/bin/env bash
set -euo pipefail

TRUST_STORE="${TRUST_STORE:-/etc/opcua/trust}"

mkdir -p "$TRUST_STORE/trusted" "$TRUST_STORE/rejected"

while IFS= read -r endpoint; do
    [[ "$endpoint" =~ ^#.*$ ]] && continue  # skip comments
    [[ -z "$endpoint" ]] && continue        # skip blanks

    echo "Trusting: $endpoint"
    if opcua-cli trust "$endpoint" --trust-store="$TRUST_STORE" --timeout=3; then
        echo "  OK"
    else
        echo "  FAILED" >&2
    fi
done < endpoints.txt
```
<!-- @endcode-block -->

`endpoints.txt` is a flat list:

<!-- @code-block language="text" label="endpoints.txt" -->
```text
# Plant 1 — line A
opc.tcp://plc-a1.line-a.plant1.local:4840
opc.tcp://plc-a2.line-a.plant1.local:4840
opc.tcp://hmi-a1.line-a.plant1.local:4840

# Plant 1 — line B
opc.tcp://plc-b1.line-b.plant1.local:4840
# ...
```
<!-- @endcode-block -->

The script logs successes and failures, doesn't stop on errors
(`set -e` with conditional check). Run it overnight; review the
failures in the morning.

## Shape 2 — hostile network rollout

Where the `trust` step itself isn't safe (downloading the cert
over an untrusted link), distribute certificates out-of-band and
drop them into the trust store directly:

<!-- @code-block language="bash" label="bash — install-certs.sh" -->
```bash
#!/usr/bin/env bash
set -euo pipefail

TRUST_STORE="${TRUST_STORE:-/etc/opcua/trust}"
CERTS_DIR="${1:-./vendor-certs}"

mkdir -p "$TRUST_STORE/trusted"

# Each cert file in CERTS_DIR is a DER-encoded server cert
for cert in "$CERTS_DIR"/*.der; do
    [ -f "$cert" ] || continue

    # SHA-1 fingerprint of the DER bytes (the format opcua-client's
    # FileTrustStore uses to name files on disk)
    fingerprint=$(openssl dgst -sha1 -hex < "$cert" | awk '{print $2}')

    # The trust store names files by fingerprint
    cp "$cert" "$TRUST_STORE/trusted/${fingerprint}.der"
    echo "Installed: $fingerprint  ($(basename "$cert"))"
done
```
<!-- @endcode-block -->

`./vendor-certs/` is the directory containing the
DER-encoded certificates the vendor delivered (encrypted email,
signed bundle, USB stick). Each file becomes a trusted cert,
keyed by its **SHA-1** fingerprint (this is what
`opcua-client`'s `FileTrustStore` expects; using SHA-256 here
would write files the trust store can't find at lookup time).

After running:

<!-- @code-block language="bash" label="bash — verify" -->
```bash
opcua-cli trust:list --trust-store="$TRUST_STORE"
```
<!-- @endcode-block -->

Confirms what's in the store.

## Verifying a fingerprint before installing

For shape 2, the trust depends on the fingerprint matching what
the vendor documented. Verify each file:

<!-- @code-block language="bash" label="bash — fingerprint verification" -->
```bash
for cert in ./vendor-certs/*.der; do
    fp=$(openssl dgst -sha1 -hex < "$cert" | awk '{print $2}')
    name=$(basename "$cert" .der)
    echo "$name: $fp"
done
```
<!-- @endcode-block -->

Compare each printed fingerprint against the vendor's
documentation. Only proceed with `install-certs.sh` after
manual verification.

## Removing certs in bulk

When servers are decommissioned or rotate their certs:

<!-- @code-block language="bash" label="bash — bulk untrust" -->
```bash
#!/usr/bin/env bash
set -euo pipefail

while IFS= read -r fingerprint; do
    [[ "$fingerprint" =~ ^#.*$ ]] && continue
    [[ -z "$fingerprint" ]] && continue

    echo "Removing: $fingerprint"
    opcua-cli trust:remove "$fingerprint" --trust-store=/etc/opcua/trust
done < fingerprints-to-remove.txt
```
<!-- @endcode-block -->

## Smoke-testing after rollout

After trusting, smoke-test every server to confirm the trust
chain works end-to-end:

<!-- @code-block language="bash" label="bash — post-rollout smoke" -->
```bash
#!/usr/bin/env bash
set -euo pipefail

failed=0
while IFS= read -r endpoint; do
    [[ "$endpoint" =~ ^#.*$ ]] && continue
    [[ -z "$endpoint" ]] && continue

    if opcua-cli endpoints "$endpoint" \
            --trust-policy=fingerprint+expiry \
            --trust-store=/etc/opcua/trust \
            --timeout=2 \
            >/dev/null 2>&1; then
        echo "OK    $endpoint"
    else
        echo "FAIL  $endpoint"
        failed=$((failed + 1))
    fi
done < endpoints.txt

exit $failed
```
<!-- @endcode-block -->

Exit code = number of failed servers. Wire into monitoring or a
post-rollout check.

## Listing expiring certificates

Catch upcoming expirations before they bite:

<!-- @code-block language="bash" label="bash — expiring soon" -->
```bash
opcua-cli trust:list --trust-store=/etc/opcua/trust --json \
    | jq -r '.[]
        | select(
            (.Expires
                | sub("\\..*";"")
                | sub("\\+.*";"")
                | strptime("%Y-%m-%dT%H:%M:%S")
                | mktime) < (now + 30*24*3600)
          )
        | "\(.Fingerprint)  \(.Subject)  expires \(.Expires)"'
```
<!-- @endcode-block -->

Lists every cert expiring within 30 days. Run weekly, alert
the operator before the cert dies.

## Storing the trust store in version control

The trust store is a directory of `.der` files keyed by
fingerprint. Version-controllable as long as the files
themselves are small (typical: 1-2 KB each).

<!-- @code-block language="text" label="git-tracked trust store" -->
```text
opcua-trust/
├── trusted/
│   ├── a1b2c3d4e5f6...der
│   ├── b2c3d4e5f6a1...der
│   └── ...
└── rejected/
    └── ff03c2a7...der
```
<!-- @endcode-block -->

Treat changes as auditable events — each PR adding a cert is a
"we trusted this server" record. Pair with the `trust:list`
output as a human-readable index.

## Why the trust store is per-host

For per-user installs pass `--trust-store="$HOME/.opcua/trust"`
(the underlying `FileTrustStore` defaults to `~/.opcua/` on POSIX,
but only when the CLI instantiates the store — and the CLI only
instantiates it when a trust flag is on the command line).
Production deployments use `--trust-store=/etc/opcua/trust` for a
host-wide store. Two implications:

- **Permissions matter.** The store directory should be readable
  by the user running `opcua-cli` but not by anyone else
  (`chmod 750`, `chown opcua:opcua`).
- **Distribution scales.** Push the trust store to every host
  via configuration management (Ansible, Puppet, Salt). The
  `.der` files are stateless — no merge conflicts, no per-host
  customisation.

## What the recipe does *not* cover

- **Client-certificate distribution.** This page covers
  **server**-cert trust. The client cert (`--cert`/`--key`) is
  a separate concern — distributed alongside, but not via the
  trust store.
- **CA-chain validation.** `--trust-policy=full` requires a
  CA bundle in the trust store. If you're using a PKI rather
  than per-cert trust, see [`opcua-client` — trust
  store](https://github.com/php-opcua/opcua-client/blob/master/docs/security/trust-store.md).
- **Programmatic trust manipulation.** For applications that
  manage the trust store at runtime, use the library directly —
  `$client->trustCertificate()` / `untrustCertificate()`.
