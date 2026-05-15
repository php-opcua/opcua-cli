---
eyebrow: 'Docs · Reference'
lede:    'Every flag the CLI accepts on every command. Short vs long forms, defaults, scopes — the flat table you grep for when memory fails.'

see_also:
  - { href: './exit-codes.md',                  meta: '3 min' }
  - { href: '../connecting/security-policies.md', meta: '5 min' }
  - { href: '../commands/browse.md',            meta: '4 min' }

prev: { label: 'Troubleshooting', href: '../building/troubleshooting.md' }
next: { label: 'Exit codes',     href: './exit-codes.md' }
---

# Global options

Every command accepts the flags below. Command-specific flags
(e.g. `--recursive` on `browse`) are documented on the relevant
command page.

## Security

| Short | Long                       | Default      | Accepts                                                        |
| ----- | -------------------------- | ------------ | -------------------------------------------------------------- |
| `-s`  | `--security-policy=POLICY` | `None`       | `None`, `Basic128Rsa15`, `Basic256`, `Basic256Sha256`, `Aes128Sha256RsaOaep`, `Aes256Sha256RsaPss`, `EccNistP256`, `EccNistP384`, `EccBrainpoolP256r1`, `EccBrainpoolP384r1`, or the full URI |
| `-m`  | `--security-mode=MODE`     | `None`       | `None`, `Sign`, `SignAndEncrypt`                              |
|       | `--cert=PATH`              | none         | PEM-encoded client certificate                                |
|       | `--key=PATH`               | none         | PEM-encoded client private key                                |
|       | `--ca=PATH`                | none         | PEM-encoded CA bundle for chain validation                    |

See [Connecting · Security policies](../connecting/security-policies.md).

## Authentication

| Short | Long                  | Default       | Effect                                                |
| ----- | --------------------- | ------------- | ----------------------------------------------------- |
| `-u`  | `--username=USER`     | (anonymous)   | Username for session-level identity                  |
| `-p`  | `--password=PASS`     | (none)        | Password for session-level identity                  |

See [Connecting · Credentials](../connecting/credentials.md).

## Trust store

| Long                       | Default       | Effect                                                              |
| -------------------------- | ------------- | ------------------------------------------------------------------- |
| `--trust-store=PATH`       | `~/.opcua/`   | Override the trust store directory                                  |
| `--trust-policy=POLICY`    | (none)        | Validation policy: `fingerprint`, `fingerprint+expiry`, `full`     |
| `--no-trust-policy`        | off           | Disable trust validation for this command (insecure — use sparingly) |

See [Connecting · Trust store workflow](../connecting/trust-store-workflow.md).

## Network

| Short | Long                  | Default | Effect                              |
| ----- | --------------------- | ------- | ----------------------------------- |
| `-t`  | `--timeout=SECONDS`   | `5`     | Connection / per-call timeout       |

## Output

| Short | Long                  | Default     | Effect                                          |
| ----- | --------------------- | ----------- | ----------------------------------------------- |
| `-j`  | `--json`              | off         | Emit JSON instead of console output             |

See [Output formats](../output/output-formats.md).

## Debug logging

| Short | Long                  | Default     | Effect                                                 |
| ----- | --------------------- | ----------- | ------------------------------------------------------ |
| `-d`  | `--debug`             | off         | Write debug logs to stdout                              |
|       | `--debug-stderr`      | off         | Write debug logs to stderr                              |
|       | `--debug-file=PATH`   | off         | Append debug logs to a file                             |

`--debug` and `--json` cannot be combined — use `--debug-stderr`
or `--debug-file` when piping JSON. See [Debug
logging](../output/debug-logging.md).

## Meta

| Short | Long          | Effect                                              |
| ----- | ------------- | --------------------------------------------------- |
| `-h`  | `--help`      | Show the command catalogue (or per-command help)   |
| `-v`  | `--version`   | Print the CLI version and exit                      |

## Long-option syntax

`--key=value` and `--key value` both work:

<!-- @code-block language="bash" label="terminal — equivalent forms" -->
```bash
opcua-cli browse opc.tcp://plc.local:4840 --security-policy=Basic256Sha256
opcua-cli browse opc.tcp://plc.local:4840 --security-policy Basic256Sha256
```
<!-- @endcode-block -->

The parser is permissive — it accepts both. Pick whichever is
readable in your script.

## Short-option syntax

Short options take their value as the next argument:

<!-- @code-block language="bash" label="terminal — short options" -->
```bash
opcua-cli browse opc.tcp://plc.local:4840 -s Basic256Sha256 -m SignAndEncrypt
opcua-cli read   opc.tcp://plc.local:4840 i=2261 -j
```
<!-- @endcode-block -->

Short flags **without** values (boolean-style: `-j`, `-h`, `-v`)
can be combined though the parser. Mixing combined boolean flags
with valued flags in one argument is not supported — `-ju` is
not the same as `-j -u …`.

## Order of arguments

Options can appear before, after, or interleaved with positional
arguments. The CLI sorts them out:

<!-- @code-block language="bash" label="terminal — order doesn't matter" -->
```bash
opcua-cli browse opc.tcp://plc.local:4840 /Objects --recursive --depth=3
opcua-cli browse --recursive --depth=3 opc.tcp://plc.local:4840 /Objects
opcua-cli --recursive browse opc.tcp://plc.local:4840 /Objects --depth=3
```
<!-- @endcode-block -->

All three are equivalent. The parser identifies the command by
"first non-option argument" and collects everything else as
positional arguments or options.

## Environment variables

The CLI itself does **not** read environment variables for
configuration — every option is on the command line. The
typical pattern for secret-bearing flags is to read the env in
your shell:

<!-- @code-block language="bash" label="terminal — env-sourced password" -->
```bash
OPCUA_PASSWORD=$(cat /etc/opcua/secret.txt) \
    opcua-cli read opc.tcp://plc.local:4840 i=2261 \
      -u operator -p "$OPCUA_PASSWORD"
```
<!-- @endcode-block -->

`NO_COLOR=1` and `FORCE_COLOR=1` are recognised by the
console output backend per de-facto standard. See [Output
formats](../output/output-formats.md).

## What's *not* a global option

The flags below are command-specific — see the command's page:

- `--recursive`, `--depth` → [`browse`](../commands/browse.md)
- `--attribute` → [`read`](../commands/read.md)
- `--type` → [`write`](../commands/write.md)
- `--interval` → [`watch`](../commands/watch.md)
- `--output`, `--namespace` → [`generate:nodeset`](../commands/generate-nodeset.md), [`dump:nodeset`](../commands/dump-nodeset.md)
