---
eyebrow: 'Docs · Reference'
lede:    'Three exception families surface as CLI errors. UntrustedCertificateException gets a helpful follow-up; OpcUaException prints the message; RuntimeException is the catch-all.'

see_also:
  - { href: './exit-codes.md',                  meta: '3 min' }
  - { href: '../connecting/trust-store-workflow.md', meta: '5 min' }
  - { href: 'https://github.com/php-opcua/opcua-client/blob/master/docs/reference/exceptions.md', meta: 'external', label: 'opcua-client — exceptions' }

prev: { label: 'Exit codes',   href: './exit-codes.md' }
next: { label: 'CI smoke test', href: '../recipes/ci-smoke-test.md' }
---

# Exceptions and errors

The CLI's error handling is intentionally simple: three exception
families, three response styles, always exit `1`.

## How the application handles exceptions

`Application::run()` wraps the command dispatch in three `catch`
blocks, in order:

<!-- @steps -->
- **`UntrustedCertificateException`.**

  The server's certificate is not in the trust store. The CLI
  prints a structured message with the fingerprint and three
  follow-up commands (trust, list, skip).

- **`OpcUaException` (and subclasses).**

  Any other OPC UA-side error — `ConnectionException`,
  `ServiceException`, `SecurityException`,
  `ServiceUnsupportedException`, etc. The CLI prints
  `Error: <exception message>`.

- **`RuntimeException`.**

  CLI-side failures: invalid arguments, missing files, unknown
  flags. Same shape — `Error: <message>`.
<!-- @endsteps -->

## `UntrustedCertificateException`

The most common error users see — and the only one that gets a
multi-line response.

<!-- @code-block language="text" label="stderr" -->
```text
Error: Server certificate not trusted.
  Fingerprint: a1b2c3d4e5f6789012345678901234567890abcdef12345678901234567890abcd

To trust this certificate, run:
  opcua-cli trust opc.tcp://plc.local:4840

To list trusted certificates:
  opcua-cli trust:list

To skip trust validation for this command:
  opcua-cli browse ... --no-trust-policy
```
<!-- @endcode-block -->

Three actionable follow-ups. Most users hit this once, run
`trust`, never see it again.

For the workflow narrative, see [Trust store
workflow](../connecting/trust-store-workflow.md).

## `OpcUaException`

The broad family of OPC UA-side failures, all surfaced as
`Error: <message>` on stderr:

| OPC UA subclass                  | Typical cause                                            |
| -------------------------------- | -------------------------------------------------------- |
| `ConnectionException`            | TCP refused, host unreachable, timeout, channel dropped  |
| `ServiceException`               | Server returned a bad status code                        |
| `ServiceUnsupportedException`    | Server doesn't implement the requested service set (e.g. NodeManagement on UA-.NETStandard) |
| `SecurityException`              | Certificate or key load failure, OpenSSL primitive failure |
| `HandshakeException`             | HEL/ACK or OPN handshake failed                          |
| `MessageTypeException`           | Server sent an unexpected message type                   |
| `EncodingException`              | Wire-level decode error                                   |
| `InvalidNodeIdException`         | NodeId argument didn't parse                              |
| `ConfigurationException`         | Builder configuration is internally inconsistent          |

`UntrustedCertificateException` is also a subclass — caught
earlier for the dedicated message.

The CLI does not distinguish between subclasses in its output.
All produce `Error: <message>` and exit `1`. To discriminate in
scripts, capture stderr and grep the text:

<!-- @code-block language="bash" label="bash — discriminate" -->
```bash
output=$(opcua-cli browse opc.tcp://plc.local:4840 2>&1) || {
    case "$output" in
        *"Connection refused"*)    echo "down" ;;
        *"BadServiceUnsupported"*) echo "no service" ;;
        *"BadNodeIdUnknown"*)      echo "no such node" ;;
        *)                          echo "other" ;;
    esac
}
```
<!-- @endcode-block -->

For richer error discrimination, drop down to the library
directly — `OpcUaException` has typed subclasses your PHP code
can `catch`.

## `RuntimeException`

CLI-side failures fall into this bucket:

| Trigger                                              | Message shape                                |
| ---------------------------------------------------- | -------------------------------------------- |
| Unknown command                                       | `Unknown command: <name>` + help banner      |
| Missing required argument                             | `Error: endpoint URL is required.` + usage  |
| Bad option combination (`--debug` + `--json`)         | Error explaining the conflict                 |
| `--debug-file` to an unwritable path                  | `Cannot open debug file for writing: <path>` |
| `generate:nodeset` on a missing XML                   | `XML file not found: <path>`                 |
| `dump:nodeset` writing to a read-only output           | `Cannot write output file: <path>`           |

Same `Error: <message>` shape on stderr. Exit `1`.

## With `--json`

When `--json` is set, the CLI emits errors as JSON on **stderr**
(`JsonOutput::error()` writes to the stderr stream). The shape is
a single-key object:

<!-- @code-block language="text" label="JSON — error (stderr)" -->
```text
{"error":"Server certificate not trusted."}
```
<!-- @endcode-block -->

`error` is the only key. Additional context (the fingerprint of
an untrusted certificate, follow-up commands, the bad status name)
is emitted as separate `error()` calls — i.e. **separate JSON
lines** on stderr:

<!-- @code-block language="text" label="JSON — untrusted cert, stderr" -->
```text
{"error":"Error: Server certificate not trusted."}
{"error":"  Fingerprint: a1:b2:c3:d4:..."}
```
<!-- @endcode-block -->

The CLI does **not** combine the fingerprint, status code, or
status name into the same JSON object as `error`. Each
`error()` call is wrapped independently.

To capture and parse, redirect stderr to stdout (or to a file)
and feed the lines through `jq`:

<!-- @code-block language="bash" label="bash — JSON error" -->
```bash
out=$(opcua-cli read opc.tcp://plc.local:4840 i=99999 --json 2>&1 >/dev/null)
err=$(echo "$out" | jq -rs '[.[].error] | join(" / ")' 2>/dev/null)
if [ -n "$err" ]; then
    echo "Failed: $err"
    exit 1
fi
```
<!-- @endcode-block -->

See [Output formats](../output/output-formats.md#section-errors).

## Errors that *don't* throw

Per-item bad statuses from multi-operation services (none
currently exposed at the CLI level — `read` and `write` are
single-node) don't reach the exception handlers. They surface in
the JSON output's `Status` field for `read` and `write` as a
combined `"Bad… (0x...)"` string. The exit code is still `1` for
non-Good statuses.

## Sanitisation

The CLI does **not** sanitise messages on its own — neither the
human-readable stderr output nor the JSON `error` line is filtered
inside `opcua-cli`. Whatever the upstream `opcua-client` exception
contains is forwarded verbatim.

In practice, the library's exception messages do not surface
authentication tokens or password bodies, but this depends
entirely on the library's own conventions. Review the error text
before sharing it externally, and refer to [`opcua-client` error
handling](https://github.com/php-opcua/opcua-client/blob/master/docs/reference/exceptions.md)
for the upstream contract.

## When you need typed errors

The CLI's text-based error contract is fine for shells, CI,
operators. When your application needs typed error handling —
matching on `ServiceUnsupportedException` to fall back to a
different code path, distinguishing `ConnectionException` from
`SecurityException` — embed
[`opcua-client`](https://github.com/php-opcua/opcua-client)
directly. The CLI is the operator interface; the library is
the integration interface.
