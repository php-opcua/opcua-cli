<?php

declare(strict_types=1);

use PhpOpcua\Cli\Commands\GenerateNodesetCommand;
use PhpOpcua\Cli\NodeSetParser;
use PhpOpcua\Cli\Output\ConsoleOutput;
use PhpOpcua\Client\ClientBuilder;

function makeTmpOutputDir(string $suffix): string
{
    $dir = sys_get_temp_dir() . '/opcua-cli-sec-' . $suffix . '-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);

    return $dir;
}

function rmTree(string $dir): void
{
    if (! is_dir($dir)) {
        if (is_file($dir)) {
            @unlink($dir);
        }

        return;
    }
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($path)) {
            rmTree($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function makeSilentOutput(): ConsoleOutput
{
    $stdout = fopen(tempnam(sys_get_temp_dir(), 'opcua-sec-stdout-'), 'w+b');
    $stderr = fopen(tempnam(sys_get_temp_dir(), 'opcua-sec-stderr-'), 'w+b');

    return new ConsoleOutput($stdout, $stderr);
}

function phpLint(string $file): array
{
    $cmd = escapeshellcmd(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1';
    exec($cmd, $out, $rc);

    return [$rc, implode("\n", $out)];
}

function runGenerate(string $fixture, string $outputDir): int
{
    $cmd = new GenerateNodesetCommand();
    $builder = new ClientBuilder();
    $output = makeSilentOutput();

    return $cmd->execute(
        $builder,
        [$fixture],
        ['output' => $outputDir, 'namespace' => 'Sec\\Test'],
        $output,
    );
}

describe('generate:nodeset — code injection hardening', function () {

    afterEach(function () {
        foreach (glob(sys_get_temp_dir() . '/opcua-cli-sec-*') ?: [] as $path) {
            rmTree($path);
        }
    });

    it('escapes single-quote in NodeId literal of NodeIds class', function () {
        $fixture = __DIR__ . '/../Fixtures/malicious/nodeset-quote-in-nodeid.xml';
        $dir = makeTmpOutputDir('nodeid');

        expect(runGenerate($fixture, $dir))->toBe(0);

        $files = glob($dir . '/*NodeIds.php');
        expect($files)->toHaveCount(1);
        $code = file_get_contents($files[0]);

        // NodeId value "ns=1;s=x';phpinfo();//" must appear as an escaped PHP literal,
        // never as bare executable code.
        $payload = "ns=1;s=x';phpinfo();//";
        expect($code)->toContain(var_export($payload, true));
        expect($code)->not->toMatch("/= *'ns=1;s=x';phpinfo\\(\\);/");

        [$rc] = phpLint($files[0]);
        expect($rc)->toBe(0);
    });

    it('escapes backslash in NodeId literal', function () {
        $fixture = __DIR__ . '/../Fixtures/malicious/nodeset-quote-in-nodeid.xml';
        $dir = makeTmpOutputDir('backslash');

        expect(runGenerate($fixture, $dir))->toBe(0);

        $files = glob($dir . '/*NodeIds.php');
        $code = file_get_contents($files[0]);

        $payload = 'ns=1;s=back\\slash';
        expect($code)->toContain(var_export($payload, true));

        [$rc] = phpLint($files[0]);
        expect($rc)->toBe(0);
    });

    it('escapes encoding id when codec entry has no const name (fallback branch)', function () {
        $gen = new PhpOpcua\Cli\CodeGenerator();

        $codecs = [[
            'encodingId' => "ns=1;s=enc';system('id');//",
            'codecClass' => 'EvilCodec',
            'constName' => null,
        ]];

        $code = $gen->generateRegistrarClass('EvilRegistrar', $codecs, [], 'EvilNodeIds', 'Sec\\Test', []);

        expect($code)->toContain(var_export("ns=1;s=enc';system('id');//", true));
        expect($code)->not->toMatch("/NodeId::parse\\('ns=1;s=enc';system/");

        $tmp = tempnam(sys_get_temp_dir(), 'opcua-sec-lint-') . '.php';
        file_put_contents($tmp, $code);
        try {
            [$rc, $out] = phpLint($tmp);
            expect($rc)->toBe(0)->and($out)->not->toContain('Parse error');
        } finally {
            @unlink($tmp);
        }
    });

    it('escapes enum node id when enum mapping has no const name (fallback branch)', function () {
        $gen = new PhpOpcua\Cli\CodeGenerator();

        $enumMappings = [
            "ns=1;s=enum';phpinfo();//" => [
                'enumClass' => 'EvilEnum',
                'constName' => null,
            ],
        ];

        $code = $gen->generateRegistrarClass('EvilRegistrar', [], $enumMappings, 'EvilNodeIds', 'Sec\\Test', []);

        expect($code)->toContain(var_export("ns=1;s=enum';phpinfo();//", true));
        expect($code)->not->toMatch("/=> *'ns=1;s=enum';phpinfo/");

        $tmp = tempnam(sys_get_temp_dir(), 'opcua-sec-lint-') . '.php';
        file_put_contents($tmp, $code);
        try {
            [$rc, $out] = phpLint($tmp);
            expect($rc)->toBe(0)->and($out)->not->toContain('Parse error');
        } finally {
            @unlink($tmp);
        }
    });

    it('sanitizes enum name used as file path', function () {
        $fixture = __DIR__ . '/../Fixtures/malicious/nodeset-traversal-enum-name.xml';
        $dir = makeTmpOutputDir('traversal');
        $parent = dirname($dir);
        $escapePath = $parent . '/pwned.php';

        if (file_exists($escapePath)) {
            unlink($escapePath);
        }

        try {
            expect(runGenerate($fixture, $dir))->toBe(0);

            // No file written outside the intended output directory.
            expect(file_exists($escapePath))->toBeFalse();
        } finally {
            if (file_exists($escapePath)) {
                unlink($escapePath);
            }
        }

        // Enum file written inside Enums/ with a sanitized name — no slashes or dots.
        $enums = glob($dir . '/Enums/*.php') ?: [];
        expect($enums)->not->toBeEmpty();
        foreach ($enums as $f) {
            $base = basename($f, '.php');
            expect($base)->toMatch('/^[A-Za-z0-9_]+$/');
        }
    });

    it('sanitizes ModelUri used as dependency registrar class', function () {
        $fixture = __DIR__ . '/../Fixtures/malicious/nodeset-injection-modeluri.xml';
        $dir = makeTmpOutputDir('modeluri');

        expect(runGenerate($fixture, $dir))->toBe(0);

        $files = glob($dir . '/*Registrar.php');
        expect($files)->toHaveCount(1);
        $code = file_get_contents($files[0]);

        // Every `new \...()` expression must contain only alphanumeric + backslash + underscore chars.
        if (preg_match_all('/new *\\\\([^()]*)\\(/', $code, $m) && ! empty($m[1])) {
            foreach ($m[1] as $fqcn) {
                expect($fqcn)->toMatch('/^[A-Za-z0-9_\\\\]+$/');
            }
        }

        [$rc, $out] = phpLint($files[0]);
        expect($rc)->toBe(0)
            ->and($out)->not->toContain('Parse error');
    });

    it('generates files whose PHP syntax is valid for every malicious fixture', function () {
        foreach (glob(__DIR__ . '/../Fixtures/malicious/*.xml') as $fixture) {
            $dir = makeTmpOutputDir('lint-' . basename($fixture, '.xml'));
            expect(runGenerate($fixture, $dir))->toBe(0);

            foreach (glob($dir . '/*.php') ?: [] as $f) {
                [$rc, $out] = phpLint($f);
                expect($rc)->toBe(0);
                expect($out)->not->toContain('Parse error');
            }
            foreach (glob($dir . '/Enums/*.php') ?: [] as $f) {
                [$rc] = phpLint($f);
                expect($rc)->toBe(0);
            }
            foreach (glob($dir . '/Types/*.php') ?: [] as $f) {
                [$rc] = phpLint($f);
                expect($rc)->toBe(0);
            }
            foreach (glob($dir . '/Codecs/*.php') ?: [] as $f) {
                [$rc] = phpLint($f);
                expect($rc)->toBe(0);
            }
        }
    });
});

describe('NodeSetParser — XML hardening', function () {

    it('passes LIBXML_NONET to simplexml_load_file', function () {
        // Defense-in-depth: ensure the parser never requests remote DOCTYPE/schema resolution.
        $source = file_get_contents(__DIR__ . '/../../src/NodeSetParser.php');
        expect($source)->toContain('LIBXML_NONET');
    });

    it('still parses a valid NodeSet2.xml', function () {
        $parser = new NodeSetParser();
        $parser->parse(__DIR__ . '/../Fixtures/TestNodeSet2.xml');
        expect($parser->getNodes())->not->toBeEmpty();
    });
});

describe('CommandRunner — debug-file robustness', function () {

    it('raises a clear error when debug-file path is not writable', function () {
        $runner = new PhpOpcua\Cli\CommandRunner();
        $output = makeSilentOutput();

        // A path under a file (not a dir) — fopen will fail.
        $bogus = '/proc/self/exe/nope.log';

        set_error_handler(static fn (): bool => true);
        $thrown = null;
        try {
            $runner->createClientBuilder(['debug-file' => $bogus], $output);
        } catch (RuntimeException $e) {
            $thrown = $e;
        } finally {
            restore_error_handler();
        }
        expect($thrown)->toBeInstanceOf(RuntimeException::class);
        expect($thrown->getMessage())->toContain('debug');
    });
});
