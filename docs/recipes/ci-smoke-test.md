---
eyebrow: 'Docs · Recipes'
lede:    'A 5-line CI step that fails the build when the OPC UA server is unreachable. endpoints command, tight timeout, exit code as the signal.'

see_also:
  - { href: '../commands/endpoints.md',         meta: '3 min' }
  - { href: '../reference/exit-codes.md',       meta: '3 min' }
  - { href: '../connecting/trust-store-workflow.md', meta: '5 min' }

prev: { label: 'Exceptions and errors', href: '../reference/exceptions-and-errors.md' }
next: { label: 'Batch trust rollout',  href: './batch-trust-rollout.md' }
---

# CI smoke test

The cheapest possible CI check: confirm an OPC UA server is up,
reachable, and answering. Five seconds, one command, one exit
code.

## The minimum

<!-- @code-block language="bash" label="bash — minimum smoke" -->
```bash
opcua-cli endpoints opc.tcp://plc.local:4840 --timeout=2
```
<!-- @endcode-block -->

Exit `0` ⇒ server up. Exit `1` ⇒ check failed. That's the
whole contract.

## Why `endpoints` and not `browse`

`endpoints` is the cheapest probe in the catalogue:

- Opens a transient unsecured channel (no auth, no cert).
- Calls `GetEndpoints` — one request, one response.
- Closes the channel.

No browse round-trip, no service-call retry, no session
creation. Pure reachability check.

`browse` adds session creation; `read` adds session + a target
node check. For "is the server up?" both are overkill.

## GitHub Actions

<!-- @code-block language="text" label=".github/workflows/integration.yml" -->
```text
jobs:
  opcua-smoke:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: openssl

      - name: Install opcua-cli
        run: |
          curl -L \
            https://github.com/php-opcua/opcua-cli/releases/latest/download/opcua-cli.phar \
            -o /usr/local/bin/opcua-cli
          chmod +x /usr/local/bin/opcua-cli

      - name: Verify OPC UA server reachability
        run: opcua-cli endpoints opc.tcp://${{ secrets.OPCUA_HOST }}:4840 --timeout=2
```
<!-- @endcode-block -->

Three steps: install PHP (for the PHAR), download the CLI,
run the smoke. ~30 seconds total runtime.

For static-binary distribution (no PHP required), swap the
PHP setup and CLI install for a direct binary download:

<!-- @code-block language="text" label="static-binary CI install" -->
```text
- name: Install opcua-cli (static binary)
  run: |
    curl -L \
      https://github.com/php-opcua/opcua-cli/releases/latest/download/opcua-cli-linux-x86_64 \
      -o /usr/local/bin/opcua-cli
    chmod +x /usr/local/bin/opcua-cli

- name: Verify OPC UA server reachability
  run: opcua-cli endpoints opc.tcp://${{ secrets.OPCUA_HOST }}:4840 --timeout=2
```
<!-- @endcode-block -->

## GitLab CI

<!-- @code-block language="text" label=".gitlab-ci.yml" -->
```text
opcua-smoke:
  stage: test
  image: php:8.4-cli
  before_script:
    - apt-get update && apt-get install -y curl
    - curl -L https://github.com/php-opcua/opcua-cli/releases/latest/download/opcua-cli.phar -o /usr/local/bin/opcua-cli
    - chmod +x /usr/local/bin/opcua-cli
  script:
    - opcua-cli endpoints opc.tcp://$OPCUA_HOST:4840 --timeout=2
```
<!-- @endcode-block -->

## With trust validation

For secured servers, pre-stage the trusted cert and run with
`--trust-policy=fingerprint+expiry`:

<!-- @code-block language="text" label="secured CI" -->
```text
- name: Stage trust store
  run: |
    mkdir -p ~/.opcua/trusted
    echo "$SERVER_CERT_B64" | base64 -d > ~/.opcua/trusted/$SERVER_CERT_FINGERPRINT.der

- name: Smoke test with trust validation
  run: |
    opcua-cli browse opc.tcp://plc.local:4840 \
        -s Basic256Sha256 -m SignAndEncrypt \
        --cert=$CLIENT_CERT --key=$CLIENT_KEY \
        --trust-policy=fingerprint+expiry \
        --timeout=3
```
<!-- @endcode-block -->

`SERVER_CERT_B64` and `SERVER_CERT_FINGERPRINT` come from CI
secrets. The trusted cert is dropped onto the runner before the
test runs; the `trust-policy=fingerprint+expiry` posture catches
both fingerprint mismatches and expired certs.

See [Trust store workflow · CI
pattern](../connecting/trust-store-workflow.md#section-ci-pattern).

## Failing the build

The CLI exits `1` on any failure. CI runners pick that up as a
failed step:

<!-- @code-block language="bash" label="bash — failure visibility" -->
```bash
$ opcua-cli endpoints opc.tcp://nonexistent.local:4840 --timeout=2
Error: Connection failed: …
$ echo $?
1
```
<!-- @endcode-block -->

The error message reaches the CI logs verbatim. No special
log formatting needed.

## Multiple servers — one workflow step per server

For a fleet smoke test:

<!-- @code-block language="text" label="matrix-based smoke" -->
```text
jobs:
  opcua-smoke:
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        server:
          - plc-1.factory.local:4840
          - plc-2.factory.local:4840
          - plc-3.factory.local:4840
    steps:
      - run: |
          curl -L .../opcua-cli.phar -o /tmp/opcua-cli
          chmod +x /tmp/opcua-cli
      - run: /tmp/opcua-cli endpoints opc.tcp://${{ matrix.server }} --timeout=2
```
<!-- @endcode-block -->

Each server is a separate job — failures are isolated, `fail-fast: false`
keeps the matrix going.

## What this *doesn't* cover

The smoke test confirms the server **answers**. It does **not**
confirm:

- The server's data is current
- The server's security configuration is correct
- The server's address space matches a previous state

For richer checks, follow `endpoints` with a `read` against a
known-good NodeId, parse the value, assert.

## With debug for diagnostics

When the smoke fails, debug logging captures the protocol detail:

<!-- @code-block language="text" label="failure diagnostic" -->
```text
- name: Smoke + diagnostics on failure
  run: |
    opcua-cli endpoints opc.tcp://$OPCUA_HOST:4840 \
        --timeout=2 \
        --debug-file=/tmp/opcua-trace.log
  continue-on-error: true

- name: Upload trace
  if: failure()
  uses: actions/upload-artifact@v4
  with:
    name: opcua-trace
    path: /tmp/opcua-trace.log
```
<!-- @endcode-block -->

The artefact captures the wire-level exchange for postmortem.

## Cadence

Smoke tests are cheap — run them:

- **Every CI build** against staging.
- **Every deploy** against production (post-deploy smoke).
- **On a schedule** (every 5 min in monitoring) against
  production endpoints.

The CLI's startup cost (~50ms for PHAR, ~10ms for static binary)
is negligible compared to typical CI overhead.
