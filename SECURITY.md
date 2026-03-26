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
- [opcua-test-suite](https://github.com/php-opcua/opcua-test-suite)

## Security Considerations

OPC UA is used in industrial environments where security matters. This CLI tool inherits the full OPC UA security stack from [`php-opcua/opcua-client`](https://github.com/php-opcua/opcua-client) (6 security policies, 3 security modes, X.509 certificate authentication). When using in production:

- Use `--security-policy=Basic256Sha256` or stronger
- Use `--security-mode=SignAndEncrypt`
- Provide proper CA-signed certificates via `--cert`, `--key`, and `--ca` (don't rely on auto-generated self-signed certs)
- Use `--trust-store` with an explicit path and appropriate `--trust-policy`
- Avoid passing passwords directly on the command line (`-p`); prefer environment variables or interactive prompts where possible, as command-line arguments may be visible in process listings
- Keep PHP and OpenSSL up to date

