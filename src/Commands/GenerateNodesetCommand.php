<?php

declare(strict_types=1);

namespace PhpOpcua\Cli\Commands;

use PhpOpcua\Cli\CodeGenerator;
use PhpOpcua\Cli\NodeSetParser;
use PhpOpcua\Cli\Output\OutputInterface;
use PhpOpcua\Client\ClientBuilder;
use PhpOpcua\Client\OpcUaClientInterface;

/**
 * Generates PHP classes (NodeId constants, Codecs, Registrar) from a NodeSet2.xml file.
 */
class GenerateNodesetCommand implements CommandInterface
{
    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'generate:nodeset';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Generate PHP classes from a NodeSet2.xml file';
    }

    /**
     * {@inheritDoc}
     */
    public function getUsage(): string
    {
        return 'generate:nodeset <xmlFile> [--output=./generated/] [--namespace=Generated\\OpcUa]';
    }

    /**
     * {@inheritDoc}
     */
    public function requiresConnection(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function execute(OpcUaClientInterface|ClientBuilder $client, array $arguments, array $options, OutputInterface $output): int
    {
        if (count($arguments) < 1) {
            $output->error('Usage: opcua-cli generate:nodeset <xmlFile> [--output=./generated/] [--namespace=Generated\\OpcUa]');

            return 1;
        }

        $xmlFile = $arguments[0];
        if (! file_exists($xmlFile)) {
            $output->error("File not found: {$xmlFile}");

            return 1;
        }

        $outputDir = rtrim((string) ($options['output'] ?? './generated'), '/');
        $namespace = (string) ($options['namespace'] ?? 'Generated\\OpcUa');
        $baseNamespace = (string) ($options['base-namespace'] ?? 'PhpOpcua\\Nodeset');

        $parser = new NodeSetParser();
        $parser->parse($xmlFile);

        $requiredModels = $parser->getRequiredModels();
        $dependencyClasses = [];
        if (! empty($requiredModels)) {
            $output->writeln('Dependencies:');
            foreach ($requiredModels as $req) {
                $ver = $req['version'] ? " v{$req['version']}" : '';
                $output->writeln("  - {$req['modelUri']}{$ver}");
                $depDir = $this->modelUriToDirName($req['modelUri']);
                if ($depDir !== null) {
                    $depClass = $this->resolveDependencyRegistrar($outputDir, $depDir);
                    $dependencyClasses[] = $baseNamespace . '\\' . $depDir . '\\' . $depClass;
                }
            }
            $output->writeln('');
        }

        $generator = new CodeGenerator();
        $nodes = $parser->getNodes();
        $dataTypes = $parser->getDataTypes();
        $enumerations = $parser->getEnumerations();

        if (empty($nodes) && empty($dataTypes) && empty($enumerations)) {
            $output->writeln('No nodes or data types found in the file.');

            return 0;
        }

        $this->ensureDirectory($outputDir);

        $baseName = $this->deriveBaseName($xmlFile);
        $nodeIdClassName = $baseName . 'NodeIds';
        $filesWritten = 0;

        $nodeIdConstMap = $this->buildConstNameMap($nodes);

        if (! empty($nodes)) {
            $nodeIdCode = $generator->generateNodeIdClass($nodeIdClassName, $nodes, $namespace);
            $this->writeFile($outputDir . '/' . $nodeIdClassName . '.php', $nodeIdCode);
            $output->writeln("Generated: {$outputDir}/{$nodeIdClassName}.php");
            $filesWritten++;
        }

        $enumFieldMap = [];
        $enumNodeMappings = [];

        if (! empty($enumerations)) {
            $this->ensureDirectory($outputDir . '/Enums');

            foreach ($enumerations as $enumNodeId => $enum) {
                $enumClassName = $this->safeClassName($enum['name']);
                $enumCode = $generator->generateEnumClass($enumClassName, $enum['values'], $namespace);
                $this->writeFile($outputDir . '/Enums/' . $enumClassName . '.php', $enumCode);
                $output->writeln("Generated: {$outputDir}/Enums/{$enumClassName}.php");
                $filesWritten++;

                $enumFieldMap[$enumNodeId] = $enumClassName;
                $enumNodeMappings[$enumNodeId] = [
                    'enumClass' => $enumClassName,
                    'constName' => $nodeIdConstMap[$enumNodeId] ?? null,
                ];
            }
        }

        $codecRegistrations = [];

        if (! empty($dataTypes)) {
            $this->ensureDirectory($outputDir . '/Types');
            $this->ensureDirectory($outputDir . '/Codecs');

            foreach ($dataTypes as $dt) {
                if (empty($dt['fields'])) {
                    continue;
                }

                $dtoName = $this->safeClassName($dt['name']);
                $codecName = $dtoName . 'Codec';
                $encodingId = $dt['encodingId'] ?? $dt['nodeId'];

                $dtoCode = $generator->generateDtoClass($dtoName, $dt['fields'], $namespace, $enumFieldMap);
                $this->writeFile($outputDir . '/Types/' . $dtoName . '.php', $dtoCode);
                $output->writeln("Generated: {$outputDir}/Types/{$dtoName}.php");
                $filesWritten++;

                $codecCode = $generator->generateCodecClass($codecName, $dtoName, $dt['fields'], $namespace, $enumFieldMap);
                $this->writeFile($outputDir . '/Codecs/' . $codecName . '.php', $codecCode);
                $output->writeln("Generated: {$outputDir}/Codecs/{$codecName}.php");
                $filesWritten++;

                $codecRegistrations[] = [
                    'encodingId' => $encodingId,
                    'codecClass' => $codecName,
                    'constName' => $nodeIdConstMap[$encodingId] ?? $nodeIdConstMap[$dt['nodeId']] ?? null,
                ];
            }
        }

        $registrarCode = $generator->generateRegistrarClass($baseName . 'Registrar', $codecRegistrations, $enumNodeMappings, $nodeIdClassName, $namespace, $dependencyClasses);
        $this->writeFile($outputDir . '/' . $baseName . 'Registrar.php', $registrarCode);
        $output->writeln("Generated: {$outputDir}/{$baseName}Registrar.php");
        $filesWritten++;

        $output->writeln('');
        $output->writeln("Done. {$filesWritten} file(s) generated in {$outputDir}/");

        return 0;
    }

    /**
     * @param array<string, array{nodeId: string, browseName: string, displayName: string, type: string}> $nodes
     * @return array<string, string>
     */
    private function buildConstNameMap(array $nodes): array
    {
        $map = [];
        $usedNames = [];
        foreach ($nodes as $node) {
            $constName = preg_replace('/[^a-zA-Z0-9_]/', '_', $node['browseName']) ?? $node['browseName'];
            if (is_numeric($constName[0] ?? '')) {
                $constName = '_' . $constName;
            }
            if (isset($usedNames[$constName])) {
                $usedNames[$constName]++;
                $constName .= '_' . $usedNames[$constName];
            } else {
                $usedNames[$constName] = 1;
            }
            $map[$node['nodeId']] = $constName;
        }

        return $map;
    }

    /**
     * @param string $xmlFile
     * @return string
     */
    /**
     * @param string $name
     * @return string
     */
    /**
     * @param string $modelUri
     * @return ?string
     */
    private function modelUriToDirName(string $modelUri): ?string
    {
        if ($modelUri === 'http://opcfoundation.org/UA/') {
            return null;
        }

        $path = rtrim(parse_url($modelUri, PHP_URL_PATH) ?? '', '/');
        $name = basename($path);
        $name = preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '';

        return $name !== '' ? $name : null;
    }

    /**
     * Resolve the registrar class name of an already-generated dependency.
     *
     * The directory is named after the model URI (`.../UA/DI/` -> `DI`) while the
     * registrar is named after the NodeSet2 file (`Opc.Ua.Di.NodeSet2.xml` ->
     * `DiRegistrar`), so the two disagree for any spec whose file casing differs
     * from its URI. When the dependency has already been generated next to this
     * one, take the name from the file that exists; otherwise fall back to the
     * directory-derived guess and leave it to the caller's cleanup pass.
     *
     * @param string $outputDir Output directory of the spec being generated.
     * @param string $depDir Directory name of the dependency spec.
     * @return string
     */
    private function resolveDependencyRegistrar(string $outputDir, string $depDir): string
    {
        $guess = $depDir . 'Registrar';
        $depPath = dirname($outputDir) . '/' . $depDir;

        if (! is_dir($depPath)) {
            return $guess;
        }

        // Resolution goes through the names glob() reports, never through
        // is_file() on a guessed name: on a case-insensitive filesystem the
        // latter confirms `DIRegistrar.php` while the file on disk — and so the
        // class — is `DiRegistrar`, which then fails to autoload everywhere else.
        $candidates = [];
        foreach (glob($depPath . '/*Registrar.php') ?: [] as $candidate) {
            $candidates[] = basename($candidate, '.php');
        }

        if (in_array($guess, $candidates, true)) {
            return $guess;
        }

        foreach ($candidates as $candidate) {
            if (strcasecmp($candidate, $guess) === 0) {
                return $candidate;
            }
        }

        // A spec split across several nodesets (AML -> AMLBaseTypes + AMLLibraries)
        // has no registrar named after the spec itself.
        $extending = [];
        foreach ($candidates as $candidate) {
            if (stripos($candidate, $depDir) === 0) {
                $extending[] = $candidate;
            }
        }

        return count($extending) === 1 ? $extending[0] : $guess;
    }

    private function safeClassName(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name) ?? $name;
        if (is_numeric($name[0] ?? '')) {
            $name = '_' . $name;
        }

        return $name;
    }

    private function deriveBaseName(string $xmlFile): string
    {
        $filename = basename($xmlFile, '.xml');
        $filename = str_replace(['Opc.Ua.', '.NodeSet2', 'NodeSet2'], '', $filename);

        if ($filename === '') {
            return 'OpcUa';
        }

        $filename = str_replace(['.', '-', ' '], '', $filename);

        return $filename;
    }

    /**
     * @param string $dir
     * @return void
     */
    private function ensureDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * @param string $path
     * @param string $content
     * @return void
     */
    private function writeFile(string $path, string $content): void
    {
        file_put_contents($path, $content);
    }
}
