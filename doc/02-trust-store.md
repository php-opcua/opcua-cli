# Trust Store CLI Commands

## Overview

The CLI provides commands for managing server certificate trust from the terminal. These commands interact with the `FileTrustStore` from `php-opcua/opcua-client`.

## Commands

### `trust <endpoint>`

Downloads the server certificate and adds it to the trust store:

```bash
opcua-cli trust opc.tcp://server:4840 --trust-store=~/.opcua
```

### `trust:list`

Lists all trusted certificates:

```bash
opcua-cli trust:list --trust-store=~/.opcua
```

### `trust:remove <fingerprint>`

Removes a certificate from the trust store:

```bash
opcua-cli trust:remove ab:cd:12:34:... --trust-store=~/.opcua
```

## CLI Options

| Option | Description |
|--------|-------------|
| `--trust-store=<path>` | Custom trust store path |
| `--trust-policy=<policy>` | Set validation policy (fingerprint, fingerprint+expiry, full) |
| `--no-trust-policy` | Disable trust validation for this command |

When a command fails with `UntrustedCertificateException`, the CLI shows a helpful message suggesting `trust` and `--no-trust-policy`.
