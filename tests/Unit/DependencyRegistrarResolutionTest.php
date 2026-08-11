<?php

declare(strict_types=1);

use PhpOpcua\Cli\Commands\GenerateNodesetCommand;
use PhpOpcua\Cli\Output\ConsoleOutput;
use PhpOpcua\Client\ClientBuilder;

// The fixture declares a RequiredModel of http://opcfoundation.org/UA/DI/, so the
// generated registrar always emits a dependency on the DI spec. The directory is
// named after the model URI ("DI") while the registrar of a real DI nodeset is
// named after its file (Opc.Ua.Di.NodeSet2.xml -> "DiRegistrar"), which is the
// mismatch these tests pin down.
function rmSpecTree(string $dir): void
{
    if (! is_dir($dir)) {
        @unlink($dir);

        return;
    }
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            rmSpecTree($dir . DIRECTORY_SEPARATOR . $entry);
        }
    }
    @rmdir($dir);
}

/**
 * Two registrars differing only in case cannot coexist on Windows/macOS, so the
 * scenario that needs them is not expressible there.
 */
function filesystemIsCaseSensitive(): bool
{
    $probe = sys_get_temp_dir() . '/opcua-cli-case-' . bin2hex(random_bytes(4));
    file_put_contents($probe . 'a', '');
    $sensitive = ! file_exists($probe . 'A');
    @unlink($probe . 'a');

    return $sensitive;
}

function makeSpecTree(): string
{
    $root = sys_get_temp_dir() . '/opcua-cli-dep-' . bin2hex(random_bytes(4));
    mkdir($root . '/Target', 0755, true);

    return $root;
}

function generateInto(string $root): string
{
    $command = new GenerateNodesetCommand();
    $stdout = fopen(tempnam(sys_get_temp_dir(), 'opcua-dep-stdout-'), 'w+b');
    $stderr = fopen(tempnam(sys_get_temp_dir(), 'opcua-dep-stderr-'), 'w+b');

    $code = $command->execute(
        new ClientBuilder(),
        [__DIR__ . '/../Fixtures/TestNodeSet2.xml'],
        ['output' => $root . '/Target', 'namespace' => 'Dep\\Test\\Target', 'base-namespace' => 'Dep\\Test'],
        new ConsoleOutput($stdout, $stderr),
    );

    expect($code)->toBe(0);

    $registrars = glob($root . '/Target/*Registrar.php') ?: [];
    expect($registrars)->toHaveCount(1);

    return file_get_contents($registrars[0]);
}

describe('generate:nodeset — dependency registrar resolution', function () {

    afterEach(function () {
        foreach (glob(sys_get_temp_dir() . '/opcua-cli-dep-*') ?: [] as $path) {
            rmSpecTree($path);
        }
    });

    // The emitted name must match the file on disk byte-for-byte, on every
    // platform. Resolving through is_file() on the directory-derived guess
    // ("DIRegistrar") satisfies a case-insensitive filesystem and emits a class
    // name that then fails to autoload on a case-sensitive one.
    it('takes the dependency class name from the registrar that exists', function () {
        $root = makeSpecTree();
        mkdir($root . '/DI', 0755, true);
        file_put_contents($root . '/DI/DiRegistrar.php', "<?php\n");

        $registrar = generateInto($root);

        expect($registrar)->toContain('new \\Dep\\Test\\DI\\DiRegistrar()');
        expect($registrar)->not->toContain('new \\Dep\\Test\\DI\\DIRegistrar()');

        preg_match('/new \\\\Dep\\\\Test\\\\DI\\\\(\w+)\(\)/', $registrar, $m);
        expect(glob($root . '/DI/*Registrar.php'))->toContain($root . '/DI/' . $m[1] . '.php');
    });

    it('prefers an exact match over a case-insensitive one', function () {
        if (! filesystemIsCaseSensitive()) {
            $this->markTestSkipped('needs a case-sensitive filesystem to hold both names');
        }

        $root = makeSpecTree();
        mkdir($root . '/DI', 0755, true);
        file_put_contents($root . '/DI/DIRegistrar.php', "<?php\n");
        file_put_contents($root . '/DI/DiRegistrar.php', "<?php\n");

        expect(generateInto($root))->toContain('new \\Dep\\Test\\DI\\DIRegistrar()');
    });

    it('resolves a spec split across nodesets to its single extending registrar', function () {
        $root = makeSpecTree();
        mkdir($root . '/DI', 0755, true);
        file_put_contents($root . '/DI/DIBaseTypesRegistrar.php', "<?php\n");

        expect(generateInto($root))->toContain('new \\Dep\\Test\\DI\\DIBaseTypesRegistrar()');
    });

    it('stays with the directory-derived guess when the dependency is not generated yet', function () {
        expect(generateInto(makeSpecTree()))->toContain('new \\Dep\\Test\\DI\\DIRegistrar()');
    });

    it('stays with the guess when several registrars could match', function () {
        $root = makeSpecTree();
        mkdir($root . '/DI', 0755, true);
        file_put_contents($root . '/DI/DIBaseTypesRegistrar.php', "<?php\n");
        file_put_contents($root . '/DI/DILibrariesRegistrar.php', "<?php\n");

        expect(generateInto($root))->toContain('new \\Dep\\Test\\DI\\DIRegistrar()');
    });

});
