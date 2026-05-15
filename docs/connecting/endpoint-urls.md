---
eyebrow: 'Docs · Connecting'
lede:    'Every command takes an endpoint URL as its first positional argument. opc.tcp://host:port — port 4840 by default, IPv4 or IPv6, no path component.'

see_also:
  - { href: './security-policies.md',     meta: '5 min' }
  - { href: '../commands/endpoints.md',   meta: '3 min' }
  - { href: '../commands/browse.md',      meta: '4 min' }

prev: { label: 'dump:nodeset',     href: '../commands/dump-nodeset.md' }
next: { label: 'Security policies', href: './security-policies.md' }
---

# Endpoint URLs

OPC UA over TCP uses a single URL scheme: `opc.tcp://`. The CLI
accepts the canonical form everywhere the first positional
argument expects an endpoint.

## Anatomy

<!-- @code-block language="text" label="URL parts" -->
```text
opc.tcp://plc.factory.local:4840
~~~~~~~~  ~~~~~~~~~~~~~~~~~ ~~~~
   │            │             │
   │            │             └── port (default 4840)
   │            └── host (DNS name, IPv4, or [IPv6])
   └── scheme (always opc.tcp://)
```
<!-- @endcode-block -->

| Part        | Value                                                    |
| ----------- | -------------------------------------------------------- |
| Scheme      | `opc.tcp://` — the OPC UA binary protocol                |
| Host        | DNS name, IPv4, IPv6 (bracketed)                         |
| Port        | `4840` by default — IANA-assigned for OPC UA              |
| Path        | Empty — no `/path` component on standard servers         |

The default port is **4840**. Most servers stay on it, but
vendors can pick anything; check the device documentation.

## Examples

<!-- @code-block language="bash" label="terminal — URL variants" -->
```bash
# DNS hostname, default port
opcua-cli browse opc.tcp://plc.factory.local

# Explicit port
opcua-cli browse opc.tcp://plc.factory.local:4840

# IPv4
opcua-cli browse opc.tcp://192.168.1.100:4840

# IPv6 — brackets required for the host
opcua-cli browse opc.tcp://[fe80::1]:4840

# Custom port (some servers run on 26543, 48010, …)
opcua-cli browse opc.tcp://plc.factory.local:26543
```
<!-- @endcode-block -->

The CLI passes the URL through to the library's `connect()`
method unchanged.

## Some servers publish a different URL than the one you connect to

When you run `endpoints`, the server's reply may carry an
`endpointUrl` different from the one you used to probe:

<!-- @code-block language="bash" label="terminal — discovery URL vs endpoint URL" -->
```bash
$ opcua-cli endpoints opc.tcp://plc.factory.local:4840 --json | jq '.endpoints[0].endpointUrl'
"opc.tcp://10.0.5.100:4840"
```
<!-- @endcode-block -->

That happens when the server is behind a load balancer or runs
on a multi-homed host with separate "discovery" and "service"
addresses. For subsequent commands (`browse`, `read`, `write`),
**use the URL the server actually returned**, not the one you
used to discover.

## Port 4840 in practice

- **Inbound port range.** Most industrial firewalls allow only
  outbound 4840 from the application zone to the OT zone. Check
  the firewall before debugging "connection refused".
- **Multiple servers on one host.** Use distinct ports
  (`:4840`, `:4841`, …). Some vendors put 10+ servers on one
  IP.
- **Reserved-port issues.** Below 1024 requires root on POSIX
  — rare for OPC UA, but if you see `:50` etc., you're likely
  looking at a configuration mistake.

## URL forms the CLI does *not* accept

| Not supported                          | Use instead                              |
| -------------------------------------- | ---------------------------------------- |
| `opc.tcp://user:pass@host:4840`        | `--username` + `--password` flags        |
| `https://host:443/opcua`               | `opc.tcp://` only — OPC UA HTTPS is a different transport profile, not supported |
| `tcp://host:4840` (no `opc.`)          | `opc.tcp://` — the scheme is part of the protocol identity |
| `opc.tcp://host:4840/some/path`        | Drop the path — no servers in the wild expose path-based endpoints in the standard binary scheme |

If the server you're targeting requires HTTPS-tunnelled OPC UA
(rare), it's outside this CLI's scope — the library's transport
layer is TCP-only.

## Quoting and shells

`opc.tcp://` URLs are shell-safe — no special characters, no
quoting needed. The exceptions are NodeIds (which contain `;`)
and browse paths with spaces — those need quoting. See
[browse · Common pitfalls](../commands/browse.md#section-common-pitfalls).

## How the CLI uses the URL

The URL feeds directly into `ClientBuilder::connect($url)`. The
client:

<!-- @steps -->
- **Parses the URL** — extracts the host and port.

- **Opens a TCP socket** — three-way handshake.

- **Sends `Hello`** — OPC UA's transport-layer handshake.

- **Receives `Acknowledge`** — buffer-size negotiation.

- **Discovers endpoints** (if security or auth needs them).

- **Opens the secure channel** — `OpenSecureChannel`.

- **Creates and activates a session** — `CreateSession` +
  `ActivateSession`.
<!-- @endsteps -->

By the time your command starts executing, the session is live
on the server. The endpoint URL is the entry point to all of
that.

## When discovery picks a different URL

The library's discovery (triggered automatically when the client
doesn't yet know the server's certificate or auth policy IDs)
calls `GetEndpoints`. The server's response may list its
endpoints under different URLs — and the library uses **those**,
not your original URL.

This is normally transparent. If you ever need to override it
(strict firewall configuration), use the URL the server actually
publishes by checking `opcua-cli endpoints <url>` first.
