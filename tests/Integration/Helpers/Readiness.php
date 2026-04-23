<?php

declare(strict_types=1);

namespace PhpOpcua\Cli\Tests\Integration\Helpers;

final class Readiness
{
    public static function waitForEndpoint(
        string $endpointUrl,
        int $timeoutSeconds = 30,
        float $pollSeconds = 0.5,
        float $settleSeconds = 1.0,
    ): void {
        $parsed = parse_url($endpointUrl);
        if ($parsed === false || ! isset($parsed['host'], $parsed['port'])) {
            throw new \RuntimeException("Invalid endpoint URL: {$endpointUrl}");
        }

        $host = (string) $parsed['host'];
        $port = (int) $parsed['port'];
        $deadline = microtime(true) + $timeoutSeconds;
        $lastError = '';

        while (microtime(true) < $deadline) {
            $errno = 0;
            $errstr = '';
            $sock = @fsockopen($host, $port, $errno, $errstr, 1.0);
            if ($sock !== false) {
                fclose($sock);
                usleep((int) ($settleSeconds * 1_000_000));

                return;
            }
            $lastError = trim("{$errno} {$errstr}");
            usleep((int) ($pollSeconds * 1_000_000));
        }

        throw new \RuntimeException(
            "Timeout waiting {$timeoutSeconds}s for endpoint to accept TCP connections: "
            . "{$endpointUrl} (last error: {$lastError})",
        );
    }
}
