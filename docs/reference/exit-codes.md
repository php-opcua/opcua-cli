---
eyebrow: 'Docs · Reference'
lede:    'Two exit codes — 0 for success, 1 for any failure. Simple enough to test in CI: opcua-cli <cmd> && echo "ok" || echo "failed".'

see_also:
  - { href: './exceptions-and-errors.md',     meta: '4 min' }
  - { href: '../recipes/ci-smoke-test.md',    meta: '4 min' }
  - { href: './global-options.md',            meta: '4 min' }

prev: { label: 'Global options',         href: './global-options.md' }
next: { label: 'Exceptions and errors', href: './exceptions-and-errors.md' }
---

# Exit codes

The CLI uses a minimal exit-code convention.

## The two values

| Exit code | Meaning                                                       |
| --------- | ------------------------------------------------------------- |
| `0`       | The command succeeded                                          |
| `1`       | The command failed for any reason                              |

Bash idiom:

<!-- @code-block language="bash" label="bash — check exit" -->
```bash
if opcua-cli endpoints opc.tcp://plc.local:4840 >/dev/null 2>&1; then
    echo "Server is up"
else
    echo "Server is down or unreachable"
fi
```
<!-- @endcode-block -->

## What counts as success

A command exits `0` when:

- For **`browse`**, **`read`**, **`endpoints`**, **`explore`**,
  **`dump:nodeset`**, **`generate:nodeset`**, **`trust`**,
  **`trust:remove`**, **`trust:list`** — the OPC UA operation
  completed and the result is in stdout.
- For **`read`** specifically — the response's `statusCode` was
  `Good` (`0`). A read whose response is `BadNodeIdUnknown`
  exits `1` even though no exception was raised.
- For **`write`** specifically — the server returned a `Good`
  status code. A `BadTypeMismatch` exits `1`.
- For **`watch`** — only on the rare "graceful end" path (e.g.
  when wrapped with a test harness that constrains the iteration
  count). On `Ctrl-C` it does **not** exit `0` — see below.

## What counts as failure

Any of:

- **Transport failure** — TCP refused, timeout, host not
  resolved.
- **Security failure** — untrusted certificate, key load
  failed, OpenSSL error.
- **Service failure** — server returned a bad status for the
  operation.
- **CLI argument error** — unknown command, missing required
  argument, invalid combination of flags.
- **Local file error** — `generate:nodeset` on a missing XML,
  `dump:nodeset` writing to a read-only path.
- **Interrupt** — `Ctrl-C` from any command. The CLI does not
  install a `pcntl_signal` handler, so PHP terminates with the
  default exit code (typically `130` = `128 + SIGINT`). `watch`
  is no exception to this — see below.

## What's *not* captured by the exit code

The exit code is a **success/fail** signal. It does not
distinguish between failure reasons. Two failures with the same
exit code:

- The server returned `BadServiceUnsupported`.
- The CLI's own argument parser rejected an unknown flag.

Both exit `1`. The reason lives in **stderr** (human-readable)
or **stdout** (when `--json` is set, structured).

For scripts that need to discriminate, parse stderr or `--json`
output:

<!-- @code-block language="bash" label="bash — discriminating failures" -->
```bash
stdout=$(opcua-cli read opc.tcp://plc.local:4840 i=99999 --json 2>/tmp/err)
rc=$?

if [ $rc -eq 0 ]; then
    value=$(echo "$stdout" | jq -r .Value)
    echo "Got: $value"
else
    # Error lines arrive on stderr as {"error":"..."} objects (one per call)
    err=$(jq -rs '[.[].error] | join(" / ")' < /tmp/err 2>/dev/null)
    echo "Read failed: $err"
fi
```
<!-- @endcode-block -->

For the full mapping from server-side errors to CLI output, see
[Exceptions and errors](./exceptions-and-errors.md).

## Why not richer exit codes?

The Unix convention (`0` = success, non-zero = failure) is the
default that every shell tool, every CI system, every monitoring
agent understands. Differentiating "service unsupported" (4)
from "no such node" (5) from "trust failure" (6) would be
nicer in theory; in practice it forces consumers to encode
those numbers, breaks on upgrade, and creates a per-tool
exit-code dialect.

If you need to discriminate, the `--json` output is the right
contract — stable field names, structured payload, parseable.

## Watch and the SIGINT case

`opcua-cli watch` is the one command that runs indefinitely.
`Ctrl-C` (SIGINT) is the expected way to stop it, but the CLI
does **not** install a signal handler — PHP terminates the
process with the conventional `128 + signal` exit code (typically
`130` for SIGINT). The OS reclaims the TCP socket; on the server
side, the subscription is closed at the next keep-alive timeout.

A non-zero exit from `watch` after a clean Ctrl-C is therefore
**expected**, not an error. If you script around `watch`, treat
exit codes `130` (SIGINT) and `143` (SIGTERM) as graceful stops
and any other non-zero as a real failure.

## Implications for CI

A canonical CI smoke test:

<!-- @code-block language="bash" label="bash — CI smoke" -->
```bash
opcua-cli endpoints opc.tcp://plc-test.local:4840 \
    --timeout=2 \
    --trust-policy=fingerprint+expiry \
    --trust-store=$CI_TRUST_STORE \
  || exit 1
```
<!-- @endcode-block -->

The `|| exit 1` is belt-and-braces — the CLI already exits `1`
on failure, and the surrounding script picks that up. Pipeline
status reflects the OPC UA server's reachability.

See [Recipes · CI smoke test](../recipes/ci-smoke-test.md) for
the full workflow.

## When the exit code is "wrong"

Two cases users have asked about:

- **`watch` exits `130` after `Ctrl-C`.** That's the OS-default
  for SIGINT — the CLI does not install a signal handler that
  would normalise it to `0`.
- **`read` exits `1` on a `BadNodeIdUnknown` response.** That's
  correct — the read was unsuccessful from the user's
  perspective, even though the network round-trip itself worked.

If you disagree with these defaults, wrap the command in a
script that interprets the JSON output (and / or filters
`$?==130`) and applies your own exit-code convention.
