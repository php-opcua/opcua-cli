---
eyebrow: 'Docs · Command · endpoints'
lede:    'Probe what the server publishes — every (URL, security policy, security mode, auth type) combination. The first thing to run against an unfamiliar server.'

see_also:
  - { href: '../connecting/security-policies.md',     meta: '5 min' }
  - { href: '../connecting/trust-store-workflow.md',  meta: '5 min' }
  - { href: '../output/output-formats.md',            meta: '4 min' }

prev: { label: 'write', href: './write.md' }
next: { label: 'watch', href: './watch.md' }
---

# `endpoints`

Probe the server's `GetEndpoints` reply.

## Usage

<!-- @code-block language="text" label="signature" -->
```text
opcua-cli endpoints <endpoint> [global-options]
```
<!-- @endcode-block -->

The simplest command in the catalogue. One required argument
(the endpoint URL), no flags beyond the global ones.

## Examples

<!-- @code-block language="bash" label="terminal" -->
```bash
opcua-cli endpoints opc.tcp://plc.local:4840
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="console output" -->
```text

Endpoint: opc.tcp://plc.local:4840
Security: None (mode: None)
Auth:     Anonymous

Endpoint: opc.tcp://plc.local:4840
Security: Basic256Sha256 (mode: Sign)
Auth:     Anonymous, Username

Endpoint: opc.tcp://plc.local:4840
Security: Basic256Sha256 (mode: SignAndEncrypt)
Auth:     Anonymous, Username, Certificate
```
<!-- @endcode-block -->

Each endpoint is rendered as a record block (three lines:
`Endpoint`, `Security`, `Auth`) separated by a blank line.
`Security` combines the policy name and the security mode into a
single string. `Auth` is a comma-separated list of token type
names (`Anonymous`, `UserName`, `Certificate`).

A real server typically exposes 4-10 endpoints — one per
(policy × mode) combination, with the auth types each supports.

## JSON output

<!-- @code-block language="bash" label="terminal — JSON" -->
```bash
opcua-cli endpoints opc.tcp://plc.local:4840 --json
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="JSON output" -->
```text
[
  {
    "Endpoint": "opc.tcp://plc.local:4840",
    "Security": "None (mode: None)",
    "Auth": "Anonymous"
  },
  {
    "Endpoint": "opc.tcp://plc.local:4840",
    "Security": "Basic256Sha256 (mode: SignAndEncrypt)",
    "Auth": "Anonymous, Username, Certificate"
  }
]
```
<!-- @endcode-block -->

The output is a JSON array (no wrapping `endpoints` envelope) of
the same record blocks the console mode prints. Three fields each:

- **`Endpoint`** — the URL the server returned.
- **`Security`** — combined string `"<policyName> (mode: <modeName>)"`.
- **`Auth`** — comma-separated token type names.

There are no separate `securityPolicyUri`, `securityLevel`, or
`userIdentityTokens` fields — those live only inside the
underlying `EndpointDescription` and are not exposed by the CLI.

For scripting, match on the combined `Security` string:

<!-- @code-block language="bash" label="terminal — scripted pick" -->
```bash
opcua-cli endpoints opc.tcp://plc.local:4840 --json \
    | jq -r '.[] | select(.Security == "Basic256Sha256 (mode: SignAndEncrypt)") | .Endpoint'
```
<!-- @endcode-block -->

See [Output formats](../output/output-formats.md) for the full
JSON schema.

## What it actually does

`endpoints` calls the OPC UA `GetEndpoints` service over a
**transient unsecured channel** — no certificate, no
authentication. The server's reply enumerates what it accepts;
discovering it requires nothing more than reachability.

This is the same call `opcua-cli browse` makes internally to
decide which security parameters to negotiate when the user has
specified them. Calling `endpoints` directly is just exposing
that discovery step.

## Reading the output

Three things to look for:

- **Available security policies.** Match your application's
  configuration. If the server only offers `Basic128Rsa15`
  (deprecated), you may need to upgrade the server.
- **Security modes per policy.** A policy with only `Sign`
  (no `SignAndEncrypt`) means data integrity but no confidentiality
  — fine for some uses, not others.
- **Identity token policies.** Some servers gate certain operations
  behind certificate auth. The `Auth` field tells you which token
  types the server accepts on each endpoint.

## When to run it

- **First contact with any unfamiliar server.** The `endpoints`
  reply is the map of "what does this server let me do?".
- **Before deploying secured config.** Verify the policy /
  mode / auth combination your application targets is actually
  available.
- **In CI as a smoke test.** Wraps a single round-trip; if
  `endpoints` succeeds, the server is reachable and responding.
  See [Recipes · CI smoke test](../recipes/ci-smoke-test.md).

## How it maps to the library

| You ran                                       | The CLI calls                                              |
| --------------------------------------------- | ---------------------------------------------------------- |
| `opcua-cli endpoints <endpoint>`              | `$client->getEndpoints($url)`                              |

See [`opcua-client` — endpoints and discovery](https://github.com/php-opcua/opcua-client/blob/master/docs/connection/opening-and-closing.md).

## Common pitfalls

- **`endpoints` itself may need security.** Rare, but some
  hardened servers refuse `GetEndpoints` over an unsecured
  channel. Pass `--security-policy` and `--cert` if `endpoints`
  alone fails with a security error.
- **Multiple URLs on one server.** Some servers publish a
  different URL than the one you connected to (load balancer,
  multi-homed server). Use the URL the server actually returned
  for subsequent commands, not the one you used to probe.
- **Empty list.** If the server returns an empty endpoint array,
  it is misconfigured or in a degraded state. Diagnose with
  `--debug` to see the wire-level exchange.
