<?php

declare(strict_types=1);

use PhpOpcua\Cli\Application;
use PhpOpcua\Cli\Output\ConsoleOutput;
use PhpOpcua\Client\Exception\OpcUaException;
use PhpOpcua\Client\Exception\UntrustedCertificateException;

function appOutputStream(): array
{
    $stdout = fopen(tempnam(sys_get_temp_dir(), 'opcua-app-'), 'w+');
    $stderr = fopen(tempnam(sys_get_temp_dir(), 'opcua-app-'), 'w+');

    return [$stdout, $stderr];
}

function appStreamContent($stream): string
{
    rewind($stream);

    return stream_get_contents($stream);
}

describe('Application', function () {

    it('handles OpcUaException when connection fails', function () {
        $app = new Application();
        // Try to connect to a non-existent server - should trigger OpcUaException
        $code = $app->run(['opcua-cli', 'read', 'opc.tcp://127.0.0.1:19999', 'i=2259', '--timeout=0.1']);
        expect($code)->toBe(1);
    });

    it('handles OpcUaException with JSON output', function () {
        $app = new Application();
        $code = $app->run(['opcua-cli', 'read', 'opc.tcp://127.0.0.1:19999', 'i=2259', '--timeout=0.1', '--json']);
        expect($code)->toBe(1);
    });

    it('handles endpoints command with connection failure', function () {
        $app = new Application();
        $code = $app->run(['opcua-cli', 'endpoints', 'opc.tcp://127.0.0.1:19999', '--timeout=0.1']);
        expect($code)->toBe(1);
    });

    it('shows version when --version is passed', function () {
        $app = new Application();
        $code = $app->run(['opcua-cli', '--version']);
        expect($code)->toBe(0);
    });

    it('shows help when --help is passed', function () {
        $app = new Application();
        $code = $app->run(['opcua-cli', '--help']);
        expect($code)->toBe(0);
    });

    it('shows help when no command is given', function () {
        $app = new Application();
        $code = $app->run(['opcua-cli']);
        expect($code)->toBe(0);
    });

    it('shows help for a specific known command', function () {
        $app = new Application();
        $code = $app->run(['opcua-cli', 'browse', '--help']);
        expect($code)->toBe(0);
    });

    it('returns 1 for unknown command', function () {
        $app = new Application();
        $code = $app->run(['opcua-cli', 'nonexistent']);
        expect($code)->toBe(1);
    });

    it('returns 1 when --debug and --json are used together', function () {
        $app = new Application();
        $code = $app->run(['opcua-cli', 'read', '--debug', '--json']);
        expect($code)->toBe(1);
    });

    it('returns 1 when connection command has no endpoint URL', function () {
        $app = new Application();
        $code = $app->run(['opcua-cli', 'read']);
        expect($code)->toBe(1);
    });

    it('returns version with -v flag', function () {
        $app = new Application();
        $code = $app->run(['opcua-cli', '-v']);
        expect($code)->toBe(0);
    });

    it('shows help with -h flag', function () {
        $app = new Application();
        $code = $app->run(['opcua-cli', '-h']);
        expect($code)->toBe(0);
    });

    it('handles non-connection command without endpoint (generate:nodeset no args)', function () {
        $app = new Application();
        $code = $app->run(['opcua-cli', 'generate:nodeset']);
        expect($code)->toBe(1);
    });

    it('handles trust:list command without trust store', function () {
        $app = new Application();
        $code = $app->run(['opcua-cli', 'trust:list']);
        expect($code)->toBe(1);
    });

    it('handles trust:remove with no fingerprint', function () {
        $app = new Application();
        $code = $app->run(['opcua-cli', 'trust:remove']);
        expect($code)->toBe(1);
    });

    it('handleUntrustedCertificate outputs fingerprint and trust instructions', function () {
        $app = new Application();
        [$stdout, $stderr] = appOutputStream();
        $output = new ConsoleOutput($stdout, $stderr);

        $exception = new UntrustedCertificateException(
            'aa:bb:cc:dd',
            'cert-der-data',
            'Server certificate not trusted',
        );

        $code = $app->handleUntrustedCertificate($exception, $output, 'opc.tcp://localhost:4840', 'read');

        expect($code)->toBe(1);
        $stdoutContent = appStreamContent($stdout);
        $stderrContent = appStreamContent($stderr);

        expect($stderrContent)->toContain('Server certificate not trusted');
        expect($stderrContent)->toContain('aa:bb:cc:dd');
        expect($stdoutContent)->toContain('opcua-cli trust opc.tcp://localhost:4840');
        expect($stdoutContent)->toContain('opcua-cli trust:list');
        expect($stdoutContent)->toContain('opcua-cli read ... --no-trust-policy');
    });

    it('handleUntrustedCertificate uses default endpoint placeholder', function () {
        $app = new Application();
        [$stdout, $stderr] = appOutputStream();
        $output = new ConsoleOutput($stdout, $stderr);

        $exception = new UntrustedCertificateException('ff:ee', 'data', 'Not trusted');

        $code = $app->handleUntrustedCertificate($exception, $output, '<endpoint>', '');

        expect($code)->toBe(1);
        expect(appStreamContent($stdout))->toContain('opcua-cli trust <endpoint>');
    });

    it('handleOpcUaException outputs error message', function () {
        $app = new Application();
        [$stdout, $stderr] = appOutputStream();
        $output = new ConsoleOutput($stdout, $stderr);

        $exception = new OpcUaException('Connection timed out');

        $code = $app->handleOpcUaException($exception, $output);

        expect($code)->toBe(1);
        expect(appStreamContent($stderr))->toContain('Error: Connection timed out');
    });

});
