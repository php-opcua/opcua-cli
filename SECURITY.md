# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 4.x     | Yes       |
| 3.x     | No        |
| 2.x     | No        |
| 1.x     | No        |

## Reporting a Vulnerability

If you discover a security vulnerability in this package, please report it responsibly.

**Do not open a public issue.** Instead, send an email to [gianfri.aur@gmail.com](mailto:gianfri.aur@gmail.com) with:

- A description of the vulnerability
- Steps to reproduce
- The affected version(s)
- Any potential impact assessment

You should receive an acknowledgment within 48 hours. From there, we'll work together to understand the scope and develop a fix before any public disclosure.

## Scope

This policy covers the `php-opcua/opcua-cli` package itself. For vulnerabilities in dependencies or related packages, please report them to the respective maintainers:

- [opcua-client](https://github.com/php-opcua/opcua-client)
- [opcua-session-manager](https://github.com/php-opcua/opcua-session-manager)
- [laravel-opcua](https://github.com/php-opcua/laravel-opcua)
- [uanetstandard-test-suite](https://github.com/php-opcua/uanetstandard-test-suite)

## Security Considerations

OPC UA is used in industrial environments where security matters. This CLI tool inherits the full OPC UA security stack from [`php-opcua/opcua-client`](https://github.com/php-opcua/opcua-client) (10 security policies including ECC, 3 security modes, X.509 certificate authentication). When using in production:

- Use `--security-policy=Basic256Sha256` or stronger
- Use `--security-mode=SignAndEncrypt`
- Provide proper CA-signed certificates via `--cert`, `--key`, and `--ca` (don't rely on auto-generated self-signed certs)
- Use `--trust-store` with an explicit path and appropriate `--trust-policy`
- Avoid passing passwords directly on the command line (`-p`); prefer environment variables or interactive prompts where possible, as command-line arguments may be visible in process listings
- Keep PHP and OpenSSL up to date

### `generate:nodeset` and untrusted `NodeSet2.xml` files

`generate:nodeset` consumes a `NodeSet2.xml` file provided by the user and emits PHP source into `--output`. As of **v4.3.0** every attacker-controllable string from the XML is emitted through `var_export()` (NodeId values, encoding IDs, enum-node IDs) or through the `safeClassName()` / `[^A-Za-z0-9]` filters (enum names used as file paths, `RequiredModel.ModelUri` used as a dependency-registrar class name), making the generator safe to run against untrusted input in principle. That said, the generated PHP is ultimately loaded into your application; treat `generate:nodeset` as a code-production step and:

- Prefer `NodeSet2.xml` files published by the OPC Foundation or by vendors you trust.
- Inspect the diff of the generated directory before committing it to your repository.
- Run `generate:nodeset` inside a short-lived CI job, not on a developer workstation with broad filesystem access, when processing third-party NodeSets.

