<?php

declare(strict_types=1);

use PhpOpcua\Cli\Application;
use PhpOpcua\Client\Tests\Integration\Helpers\TestHelper;

describe('CLI ECC NIST Integration', function () {

    it('browses with ECC_nistP256 Sign', function () {
        $app = new Application();
        $code = $app->run([
            'opcua-cli', 'browse', TestHelper::ENDPOINT_ECC_NIST,
            '--security-policy=ECC_nistP256',
            '--security-mode=Sign',
        ]);
        expect($code)->toBe(0);
    })->group('integration');

    it('reads with ECC_nistP256 SignAndEncrypt anonymous', function () {
        $app = new Application();
        $code = $app->run([
            'opcua-cli', 'read', TestHelper::ENDPOINT_ECC_NIST, 'i=2259',
            '--security-policy=ECC_nistP256',
            '--security-mode=SignAndEncrypt',
        ]);
        expect($code)->toBe(0);
    })->group('integration');

    it('reads with ECC_nistP256 SignAndEncrypt admin', function () {
        $app = new Application();
        $code = $app->run([
            'opcua-cli', 'read', TestHelper::ENDPOINT_ECC_NIST, 'i=2259',
            '--security-policy=ECC_nistP256',
            '--security-mode=SignAndEncrypt',
            '--username=' . TestHelper::USER_ADMIN['username'],
            '--password=' . TestHelper::USER_ADMIN['password'],
        ]);
        expect($code)->toBe(0);
    })->group('integration');

    it('browses with ECC_nistP384 Sign', function () {
        $app = new Application();
        $code = $app->run([
            'opcua-cli', 'browse', TestHelper::ENDPOINT_ECC_NIST,
            '--security-policy=ECC_nistP384',
            '--security-mode=Sign',
        ]);
        expect($code)->toBe(0);
    })->group('integration');

    it('reads with ECC_nistP384 SignAndEncrypt anonymous', function () {
        $app = new Application();
        $code = $app->run([
            'opcua-cli', 'read', TestHelper::ENDPOINT_ECC_NIST, 'i=2259',
            '--security-policy=ECC_nistP384',
            '--security-mode=SignAndEncrypt',
        ]);
        expect($code)->toBe(0);
    })->group('integration');

    it('reads with ECC_nistP384 SignAndEncrypt admin', function () {
        $app = new Application();
        $code = $app->run([
            'opcua-cli', 'read', TestHelper::ENDPOINT_ECC_NIST, 'i=2259',
            '--security-policy=ECC_nistP384',
            '--security-mode=SignAndEncrypt',
            '--username=' . TestHelper::USER_ADMIN['username'],
            '--password=' . TestHelper::USER_ADMIN['password'],
        ]);
        expect($code)->toBe(0);
    })->group('integration');

})->group('integration');

describe('CLI ECC Brainpool Integration', function () {

    it('browses with ECC_brainpoolP256r1 Sign', function () {
        $app = new Application();
        $code = $app->run([
            'opcua-cli', 'browse', TestHelper::ENDPOINT_ECC_BRAINPOOL,
            '--security-policy=ECC_brainpoolP256r1',
            '--security-mode=Sign',
        ]);
        expect($code)->toBe(0);
    })->group('integration');

    it('reads with ECC_brainpoolP256r1 SignAndEncrypt anonymous', function () {
        $app = new Application();
        $code = $app->run([
            'opcua-cli', 'read', TestHelper::ENDPOINT_ECC_BRAINPOOL, 'i=2259',
            '--security-policy=ECC_brainpoolP256r1',
            '--security-mode=SignAndEncrypt',
        ]);
        expect($code)->toBe(0);
    })->group('integration');

    it('reads with ECC_brainpoolP256r1 SignAndEncrypt admin', function () {
        $app = new Application();
        $code = $app->run([
            'opcua-cli', 'read', TestHelper::ENDPOINT_ECC_BRAINPOOL, 'i=2259',
            '--security-policy=ECC_brainpoolP256r1',
            '--security-mode=SignAndEncrypt',
            '--username=' . TestHelper::USER_ADMIN['username'],
            '--password=' . TestHelper::USER_ADMIN['password'],
        ]);
        expect($code)->toBe(0);
    })->group('integration');

    it('browses with ECC_brainpoolP384r1 Sign', function () {
        $app = new Application();
        $code = $app->run([
            'opcua-cli', 'browse', TestHelper::ENDPOINT_ECC_BRAINPOOL,
            '--security-policy=ECC_brainpoolP384r1',
            '--security-mode=Sign',
        ]);
        expect($code)->toBe(0);
    })->group('integration');

    it('reads with ECC_brainpoolP384r1 SignAndEncrypt anonymous', function () {
        $app = new Application();
        $code = $app->run([
            'opcua-cli', 'read', TestHelper::ENDPOINT_ECC_BRAINPOOL, 'i=2259',
            '--security-policy=ECC_brainpoolP384r1',
            '--security-mode=SignAndEncrypt',
        ]);
        expect($code)->toBe(0);
    })->group('integration');

    it('reads with ECC_brainpoolP384r1 SignAndEncrypt admin', function () {
        $app = new Application();
        $code = $app->run([
            'opcua-cli', 'read', TestHelper::ENDPOINT_ECC_BRAINPOOL, 'i=2259',
            '--security-policy=ECC_brainpoolP384r1',
            '--security-mode=SignAndEncrypt',
            '--username=' . TestHelper::USER_ADMIN['username'],
            '--password=' . TestHelper::USER_ADMIN['password'],
        ]);
        expect($code)->toBe(0);
    })->group('integration');

})->group('integration');
