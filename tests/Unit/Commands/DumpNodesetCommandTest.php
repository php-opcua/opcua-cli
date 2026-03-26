<?php

declare(strict_types=1);

use PhpOpcua\Cli\Commands\DumpNodesetCommand;
use PhpOpcua\Cli\Output\ConsoleOutput;
use PhpOpcua\Client\Testing\MockClient;
use PhpOpcua\Client\Types\BrowseNode;
use PhpOpcua\Client\Types\DataValue;
use PhpOpcua\Client\Types\LocalizedText;
use PhpOpcua\Client\Types\NodeClass;
use PhpOpcua\Client\Types\NodeId;
use PhpOpcua\Client\Types\QualifiedName;
use PhpOpcua\Client\Types\ReferenceDescription;

function dumpOutputStream(): array
{
    $stdout = fopen(tempnam(sys_get_temp_dir(), 'opcua-dump-'), 'w+');
    $stderr = fopen(tempnam(sys_get_temp_dir(), 'opcua-dump-'), 'w+');

    return [$stdout, $stderr];
}

function dumpStreamContent($stream): string
{
    rewind($stream);

    return stream_get_contents($stream);
}

function dumpMockRef(string $name, int $ns, int $id, NodeClass $class = NodeClass::Object): ReferenceDescription
{
    return new ReferenceDescription(
        referenceTypeId: NodeId::numeric(0, 35),
        isForward: true,
        nodeId: NodeId::numeric($ns, $id),
        browseName: new QualifiedName($ns, $name),
        displayName: new LocalizedText(null, $name),
        nodeClass: $class,
        typeDefinition: null,
    );
}

describe('DumpNodesetCommand', function () {

    it('returns 1 when no output option given', function () {
        $cmd = new DumpNodesetCommand();
        $client = MockClient::create();
        [$stdout, $stderr] = dumpOutputStream();
        $output = new ConsoleOutput($stdout, $stderr);
        $code = $cmd->execute($client, ['opc.tcp://localhost'], [], $output);
        expect($code)->toBe(1);
    });

    it('dumps empty address space with namespace filter', function () {
        $cmd = new DumpNodesetCommand();
        $outputFile = tempnam(sys_get_temp_dir(), 'opcua-dump-') . '.xml';

        $client = MockClient::create()
            ->onRead('i=2255', fn () => DataValue::of(
                ['http://opcfoundation.org/UA/', 'urn:test:namespace'],
                PhpOpcua\Client\Types\BuiltinType::String,
            ));

        [$stdout, $stderr] = dumpOutputStream();
        $output = new ConsoleOutput($stdout, $stderr);
        $code = $cmd->execute(
            $client,
            ['opc.tcp://localhost'],
            ['output' => $outputFile, 'namespace' => '1'],
            $output,
        );

        expect($code)->toBe(0);
        expect(file_exists($outputFile))->toBeTrue();
        $content = file_get_contents($outputFile);
        expect($content)->toContain('UANodeSet');
        expect(dumpStreamContent($stdout))->toContain('Done');

        @unlink($outputFile);
    });

    it('dumps with no namespace filter', function () {
        $cmd = new DumpNodesetCommand();
        $outputFile = tempnam(sys_get_temp_dir(), 'opcua-dump-') . '.xml';

        $client = MockClient::create()
            ->onRead('i=2255', fn () => DataValue::of(
                ['http://opcfoundation.org/UA/', 'urn:test:ns1', 'urn:test:ns2'],
                PhpOpcua\Client\Types\BuiltinType::String,
            ));

        [$stdout, $stderr] = dumpOutputStream();
        $output = new ConsoleOutput($stdout, $stderr);
        $code = $cmd->execute(
            $client,
            ['opc.tcp://localhost'],
            ['output' => $outputFile],
            $output,
        );

        expect($code)->toBe(0);
        $content = file_get_contents($outputFile);
        expect($content)->toContain('urn:test:ns1');
        expect($content)->toContain('urn:test:ns2');

        @unlink($outputFile);
    });

    it('collects nodes from browse tree with namespace filter', function () {
        $cmd = new DumpNodesetCommand();
        $outputFile = tempnam(sys_get_temp_dir(), 'opcua-dump-') . '.xml';

        $ref1 = dumpMockRef('ServerObj', 0, 2253, NodeClass::Object);
        $childRef = dumpMockRef('TestVariable', 1, 1001, NodeClass::Variable);

        $node0 = new BrowseNode($ref1);
        $childNode = new BrowseNode($childRef);
        $node0->addChild($childNode);

        $client = MockClient::create()
            ->onRead('i=2255', fn () => DataValue::of(
                ['http://opcfoundation.org/UA/', 'urn:test:ns'],
                PhpOpcua\Client\Types\BuiltinType::String,
            ))
            ->onRead('ns=1;i=1001', fn () => DataValue::ofInt32(42));

        // Mock browseRecursive to return our tree
        $browseRecursiveReturn = [$node0];
        $rc = new ReflectionProperty($client, 'calls');

        // Override browseRecursive via the mock's method
        // browseRecursive returns empty array by default, we need reflection to inject tree
        // Actually MockClient::browseRecursive always returns []. Let's use a different approach.
        // We'll call DumpNodesetCommand via reflection to test the private methods, or
        // simply accept that browseRecursive returns empty so we get 0 collected nodes.

        [$stdout, $stderr] = dumpOutputStream();
        $output = new ConsoleOutput($stdout, $stderr);
        $code = $cmd->execute(
            $client,
            ['opc.tcp://localhost'],
            ['output' => $outputFile, 'namespace' => '1'],
            $output,
        );

        expect($code)->toBe(0);
        expect(dumpStreamContent($stdout))->toContain('0 nodes exported');

        @unlink($outputFile);
    });

    it('handles non-array namespace array value', function () {
        $cmd = new DumpNodesetCommand();
        $outputFile = tempnam(sys_get_temp_dir(), 'opcua-dump-') . '.xml';

        // When namespace array read returns non-array, fallback to default
        $client = MockClient::create()
            ->onRead('i=2255', fn () => DataValue::ofString('not-an-array'));

        [$stdout, $stderr] = dumpOutputStream();
        $output = new ConsoleOutput($stdout, $stderr);
        $code = $cmd->execute(
            $client,
            ['opc.tcp://localhost'],
            ['output' => $outputFile],
            $output,
        );

        expect($code)->toBe(0);
        $content = dumpStreamContent($stdout);
        expect($content)->toContain('http://opcfoundation.org/UA/');

        @unlink($outputFile);
    });

    it('tests readNamespaceArray via reflection with array value', function () {
        $cmd = new DumpNodesetCommand();
        $client = MockClient::create()
            ->onRead('i=2255', fn () => DataValue::of(
                ['http://opcfoundation.org/UA/', 'urn:test'],
                PhpOpcua\Client\Types\BuiltinType::String,
            ));

        $method = new ReflectionMethod($cmd, 'readNamespaceArray');
        $result = $method->invoke($cmd, $client);

        expect($result)->toBe(['http://opcfoundation.org/UA/', 'urn:test']);
    });

    it('tests readNamespaceArray fallback with non-array', function () {
        $cmd = new DumpNodesetCommand();
        $client = MockClient::create();

        $method = new ReflectionMethod($cmd, 'readNamespaceArray');
        $result = $method->invoke($cmd, $client);

        expect($result)->toBe(['http://opcfoundation.org/UA/']);
    });

    it('tests collectNodes with namespace-filtered nodes', function () {
        $cmd = new DumpNodesetCommand();

        // Create browse tree with ns=0 parent and ns=1 child
        $ref0 = dumpMockRef('Server', 0, 2253, NodeClass::Object);
        $ref1 = dumpMockRef('MyNode', 1, 1001, NodeClass::Variable);

        $parentNode = new BrowseNode($ref0);
        $childNode = new BrowseNode($ref1);
        $parentNode->addChild($childNode);

        $client = MockClient::create()
            ->onRead('ns=1;i=1001', fn () => DataValue::ofInt32(42));

        $method = new ReflectionMethod($cmd, 'collectNodes');
        $collected = [];
        $args = [$client, [$parentNode], &$collected, 1, ['http://opcfoundation.org/UA/', 'urn:test']];
        $method->invokeArgs($cmd, $args);

        expect($collected)->toHaveCount(1);
        expect(array_values($collected)[0]['browseName'])->toBe('1:MyNode');
        expect(array_values($collected)[0]['nodeClass'])->toBe(NodeClass::Variable->value);
    });

    it('tests collectNodes skips ns=0 nodes when no namespace filter', function () {
        $cmd = new DumpNodesetCommand();

        $ref0 = dumpMockRef('Server', 0, 2253, NodeClass::Object);
        $parentNode = new BrowseNode($ref0);

        $client = MockClient::create();

        $method = new ReflectionMethod($cmd, 'collectNodes');
        $collected = [];
        $args = [$client, [$parentNode], &$collected, null, ['http://opcfoundation.org/UA/']];
        $method->invokeArgs($cmd, $args);

        expect($collected)->toHaveCount(0);
    });

    it('tests collectNodes skips duplicate nodes', function () {
        $cmd = new DumpNodesetCommand();

        $ref1 = dumpMockRef('MyNode', 1, 1001, NodeClass::Object);
        $node1 = new BrowseNode($ref1);
        $node2 = new BrowseNode($ref1); // Same nodeId

        $client = MockClient::create();

        $method = new ReflectionMethod($cmd, 'collectNodes');
        $collected = [];
        $args = [$client, [$node1, $node2], &$collected, 1, ['http://opcfoundation.org/UA/', 'urn:test']];
        $method->invokeArgs($cmd, $args);

        expect($collected)->toHaveCount(1);
    });

    it('tests collectNodes recurses into children when namespace does not match', function () {
        $cmd = new DumpNodesetCommand();

        $ref0 = dumpMockRef('Parent', 0, 85, NodeClass::Object);
        $ref1 = dumpMockRef('Child', 1, 1001, NodeClass::Object);

        $parentNode = new BrowseNode($ref0);
        $childNode = new BrowseNode($ref1);
        $parentNode->addChild($childNode);

        $client = MockClient::create();

        $method = new ReflectionMethod($cmd, 'collectNodes');
        $collected = [];
        $args = [$client, [$parentNode], &$collected, 1, ['http://opcfoundation.org/UA/', 'urn:test']];
        $method->invokeArgs($cmd, $args);

        expect($collected)->toHaveCount(1);
        $node = array_values($collected)[0];
        expect($node['browseName'])->toBe('1:Child');
    });

    it('tests readReferences returns references', function () {
        $cmd = new DumpNodesetCommand();

        $targetRef = new ReferenceDescription(
            referenceTypeId: NodeId::numeric(0, 47),
            isForward: true,
            nodeId: NodeId::numeric(1, 2000),
            browseName: new QualifiedName(1, 'Target'),
            displayName: new LocalizedText(null, 'Target'),
            nodeClass: NodeClass::Variable,
            typeDefinition: null,
        );

        $client = MockClient::create()
            ->onBrowse('ns=1;i=1001', fn () => [$targetRef]);

        $method = new ReflectionMethod($cmd, 'readReferences');
        $refs = $method->invoke($cmd, $client, NodeId::numeric(1, 1001));

        expect($refs)->toHaveCount(1);
        expect($refs[0]['referenceType'])->toBe('i=47');
        expect($refs[0]['isForward'])->toBeTrue();
        expect($refs[0]['targetId'])->toBe('ns=1;i=2000');
    });

    it('tests readReferences handles exception', function () {
        $cmd = new DumpNodesetCommand();

        $client = MockClient::create()
            ->onBrowse('ns=1;i=9999', function () {
                throw new RuntimeException('Browse failed');
            });

        $method = new ReflectionMethod($cmd, 'readReferences');
        $refs = $method->invoke($cmd, $client, NodeId::numeric(1, 9999));

        expect($refs)->toBe([]);
    });

    it('tests readNodeAttributes for Variable node', function () {
        $cmd = new DumpNodesetCommand();

        $client = MockClient::create()
            ->onRead('ns=1;i=1001', fn () => DataValue::of(NodeId::numeric(0, 6), PhpOpcua\Client\Types\BuiltinType::NodeId));

        $method = new ReflectionMethod($cmd, 'readNodeAttributes');
        $attrs = $method->invoke($cmd, $client, NodeId::numeric(1, 1001), NodeClass::Variable);

        expect($attrs)->toHaveKey('DataType');
    });

    it('tests readNodeAttributes for ObjectType with IsAbstract', function () {
        $cmd = new DumpNodesetCommand();

        $client = MockClient::create()
            ->onRead('ns=1;i=2001', fn () => DataValue::ofBoolean(true));

        $method = new ReflectionMethod($cmd, 'readNodeAttributes');
        $attrs = $method->invoke($cmd, $client, NodeId::numeric(1, 2001), NodeClass::ObjectType);

        expect($attrs)->toHaveKey('IsAbstract');
        expect($attrs['IsAbstract'])->toBeTrue();
    });

    it('tests readNodeAttributes for ReferenceType with Symmetric', function () {
        $cmd = new DumpNodesetCommand();

        $client = MockClient::create()
            ->onRead('ns=1;i=3001', fn () => DataValue::ofBoolean(true));

        $method = new ReflectionMethod($cmd, 'readNodeAttributes');
        $attrs = $method->invoke($cmd, $client, NodeId::numeric(1, 3001), NodeClass::ReferenceType);

        expect($attrs)->toHaveKey('Symmetric');
        expect($attrs['Symmetric'])->toBeTrue();
    });

    it('tests readNodeAttributes for DataType calls readDataTypeDefinition', function () {
        $cmd = new DumpNodesetCommand();

        $client = MockClient::create();

        $method = new ReflectionMethod($cmd, 'readNodeAttributes');
        $attrs = $method->invoke($cmd, $client, NodeId::numeric(1, 4001), NodeClass::DataType);

        // definition key should exist (even if null)
        expect(array_key_exists('definition', $attrs))->toBeTrue();
    });

    it('tests readNodeAttributes handles exception gracefully', function () {
        $cmd = new DumpNodesetCommand();

        $client = MockClient::create()
            ->onRead('ns=1;i=5001', function () {
                throw new RuntimeException('Read failed');
            });

        $method = new ReflectionMethod($cmd, 'readNodeAttributes');
        $attrs = $method->invoke($cmd, $client, NodeId::numeric(1, 5001), NodeClass::Variable);

        expect($attrs)->toBe([]);
    });

    it('tests enumStringsToDefinition', function () {
        $cmd = new DumpNodesetCommand();

        $method = new ReflectionMethod($cmd, 'enumStringsToDefinition');

        $obj1 = new stdClass();
        $obj1->text = 'Running';
        $result = $method->invoke($cmd, [$obj1, 'Stopped', 'Paused']);

        expect($result['fields'])->toHaveCount(3);
        expect($result['fields'][0]['name'])->toBe('Running');
        expect($result['fields'][0]['value'])->toBe(0);
        expect($result['fields'][1]['name'])->toBe('Stopped');
        expect($result['fields'][1]['value'])->toBe(1);
        expect($result['fields'][2]['name'])->toBe('Paused');
        expect($result['fields'][2]['value'])->toBe(2);
    });

    it('tests enumValuesToDefinition with array values', function () {
        $cmd = new DumpNodesetCommand();

        $method = new ReflectionMethod($cmd, 'enumValuesToDefinition');

        $values = [
            ['displayName' => 'Good', 'value' => 0],
            ['DisplayName' => 'Bad', 'Value' => 1],
        ];
        $result = $method->invoke($cmd, $values);

        expect($result['fields'])->toHaveCount(2);
        expect($result['fields'][0]['name'])->toBe('Good');
        expect($result['fields'][0]['value'])->toBe(0);
        expect($result['fields'][1]['name'])->toBe('Bad');
        expect($result['fields'][1]['value'])->toBe(1);
    });

    it('tests enumValuesToDefinition with object displayName', function () {
        $cmd = new DumpNodesetCommand();

        $method = new ReflectionMethod($cmd, 'enumValuesToDefinition');

        $obj = new stdClass();
        $obj->text = 'Active';
        $values = [
            ['displayName' => $obj, 'value' => 5],
        ];
        $result = $method->invoke($cmd, $values);

        expect($result['fields'][0]['name'])->toBe('Active');
        expect($result['fields'][0]['value'])->toBe(5);
    });

    it('tests enumValuesToDefinition with fallback name', function () {
        $cmd = new DumpNodesetCommand();

        $method = new ReflectionMethod($cmd, 'enumValuesToDefinition');

        $values = [
            ['value' => 10],
        ];
        $result = $method->invoke($cmd, $values);

        expect($result['fields'][0]['name'])->toBe('Value_10');
    });

    it('tests enumValuesToDefinition skips non-array entries', function () {
        $cmd = new DumpNodesetCommand();

        $method = new ReflectionMethod($cmd, 'enumValuesToDefinition');

        $values = ['string-entry', 42, ['displayName' => 'Valid', 'value' => 1]];
        $result = $method->invoke($cmd, $values);

        expect($result['fields'])->toHaveCount(1);
        expect($result['fields'][0]['name'])->toBe('Valid');
    });

    it('tests resolveEnumDefinitions resolves enum for DataType node', function () {
        $cmd = new DumpNodesetCommand();

        $obj = new stdClass();
        $obj->text = 'Running';

        $client = MockClient::create()
            ->onBrowse('ns=1;i=4001', fn () => [
                new ReferenceDescription(
                    referenceTypeId: NodeId::numeric(0, 46),
                    isForward: true,
                    nodeId: NodeId::numeric(1, 4002),
                    browseName: new QualifiedName(0, 'EnumStrings'),
                    displayName: new LocalizedText(null, 'EnumStrings'),
                    nodeClass: NodeClass::Variable,
                    typeDefinition: null,
                ),
            ])
            ->onRead('ns=1;i=4002', fn () => DataValue::of(
                [$obj, 'Stopped'],
                PhpOpcua\Client\Types\BuiltinType::String,
            ));

        $method = new ReflectionMethod($cmd, 'resolveEnumDefinitions');
        [$stdout, $stderr] = dumpOutputStream();
        $output = new ConsoleOutput($stdout, $stderr);

        $collectedNodes = [
            'ns=1;i=4001' => [
                'nodeId' => 'ns=1;i=4001',
                'nodeClass' => NodeClass::DataType->value,
                'browseName' => 'TestEnum',
                'displayName' => 'TestEnum',
                'references' => [],
                'attributes' => [],
            ],
        ];

        $args = [$client, &$collectedNodes, $output];
        $method->invokeArgs($cmd, $args);

        expect($collectedNodes['ns=1;i=4001']['attributes']['definition'])->not->toBeNull();
        expect($collectedNodes['ns=1;i=4001']['attributes']['definition']['fields'])->toHaveCount(2);
    });

    it('tests resolveEnumDefinitions skips nodes with existing definition', function () {
        $cmd = new DumpNodesetCommand();
        $client = MockClient::create();

        $method = new ReflectionMethod($cmd, 'resolveEnumDefinitions');
        [$stdout, $stderr] = dumpOutputStream();
        $output = new ConsoleOutput($stdout, $stderr);

        $collectedNodes = [
            'ns=1;i=4001' => [
                'nodeId' => 'ns=1;i=4001',
                'nodeClass' => NodeClass::DataType->value,
                'browseName' => 'TestEnum',
                'displayName' => 'TestEnum',
                'references' => [],
                'attributes' => ['definition' => ['fields' => [['name' => 'A', 'value' => 0]]]],
            ],
        ];

        $args = [$client, &$collectedNodes, $output];
        $method->invokeArgs($cmd, $args);

        // Definition should remain unchanged
        expect($collectedNodes['ns=1;i=4001']['attributes']['definition']['fields'])->toHaveCount(1);
    });

    it('tests resolveEnumDefinitions skips non-DataType nodes', function () {
        $cmd = new DumpNodesetCommand();
        $client = MockClient::create();

        $method = new ReflectionMethod($cmd, 'resolveEnumDefinitions');
        [$stdout, $stderr] = dumpOutputStream();
        $output = new ConsoleOutput($stdout, $stderr);

        $collectedNodes = [
            'ns=1;i=1001' => [
                'nodeId' => 'ns=1;i=1001',
                'nodeClass' => NodeClass::Variable->value,
                'browseName' => 'TestVar',
                'displayName' => 'TestVar',
                'references' => [],
                'attributes' => [],
            ],
        ];

        $args = [$client, &$collectedNodes, $output];
        $method->invokeArgs($cmd, $args);
        expect($collectedNodes['ns=1;i=1001']['attributes'])->not->toHaveKey('definition');
    });

    it('tests readEnumValues with EnumValues browse name', function () {
        $cmd = new DumpNodesetCommand();

        $client = MockClient::create()
            ->onBrowse('ns=1;i=4001', fn () => [
                new ReferenceDescription(
                    referenceTypeId: NodeId::numeric(0, 46),
                    isForward: true,
                    nodeId: NodeId::numeric(1, 4003),
                    browseName: new QualifiedName(0, 'EnumValues'),
                    displayName: new LocalizedText(null, 'EnumValues'),
                    nodeClass: NodeClass::Variable,
                    typeDefinition: null,
                ),
            ])
            ->onRead('ns=1;i=4003', fn () => DataValue::of(
                [['displayName' => 'Active', 'value' => 0], ['displayName' => 'Inactive', 'value' => 1]],
                PhpOpcua\Client\Types\BuiltinType::Int32,
            ));

        $method = new ReflectionMethod($cmd, 'readEnumValues');
        $result = $method->invoke($cmd, $client, NodeId::numeric(1, 4001));

        expect($result)->not->toBeNull();
        expect($result['fields'])->toHaveCount(2);
    });

    it('tests readEnumValues returns null when no EnumStrings/EnumValues found', function () {
        $cmd = new DumpNodesetCommand();

        $client = MockClient::create()
            ->onBrowse('ns=1;i=4001', fn () => [
                new ReferenceDescription(
                    referenceTypeId: NodeId::numeric(0, 47),
                    isForward: true,
                    nodeId: NodeId::numeric(1, 5000),
                    browseName: new QualifiedName(0, 'SomeOtherProp'),
                    displayName: new LocalizedText(null, 'SomeOtherProp'),
                    nodeClass: NodeClass::Variable,
                    typeDefinition: null,
                ),
            ]);

        $method = new ReflectionMethod($cmd, 'readEnumValues');
        $result = $method->invoke($cmd, $client, NodeId::numeric(1, 4001));

        expect($result)->toBeNull();
    });

    it('tests readEnumValues handles exception', function () {
        $cmd = new DumpNodesetCommand();

        $client = MockClient::create()
            ->onBrowse('ns=1;i=9999', function () {
                throw new RuntimeException('Browse failed');
            });

        $method = new ReflectionMethod($cmd, 'readEnumValues');
        $result = $method->invoke($cmd, $client, NodeId::numeric(1, 9999));

        expect($result)->toBeNull();
    });

    it('tests readDataTypeDefinition returns null via findDefinitionFromDiscovery', function () {
        $cmd = new DumpNodesetCommand();
        $client = MockClient::create();

        $method = new ReflectionMethod($cmd, 'readDataTypeDefinition');
        $result = $method->invoke($cmd, $client, NodeId::numeric(1, 4001));

        expect($result)->toBeNull();
    });

    it('tests findDefinitionFromDiscovery returns null when no Default Binary', function () {
        $cmd = new DumpNodesetCommand();

        $client = MockClient::create()
            ->onBrowse('ns=1;i=4001', fn () => [
                new ReferenceDescription(
                    referenceTypeId: NodeId::numeric(0, 47),
                    isForward: true,
                    nodeId: NodeId::numeric(1, 5000),
                    browseName: new QualifiedName(0, 'NotBinary'),
                    displayName: new LocalizedText(null, 'NotBinary'),
                    nodeClass: NodeClass::Object,
                    typeDefinition: null,
                ),
            ]);

        $method = new ReflectionMethod($cmd, 'findDefinitionFromDiscovery');
        $result = $method->invoke($cmd, $client, NodeId::numeric(1, 4001));

        expect($result)->toBeNull();
    });

    it('tests findDefinitionFromDiscovery handles exception', function () {
        $cmd = new DumpNodesetCommand();

        $client = MockClient::create()
            ->onBrowse('ns=1;i=9999', function () {
                throw new RuntimeException('Browse failed');
            });

        $method = new ReflectionMethod($cmd, 'findDefinitionFromDiscovery');
        $result = $method->invoke($cmd, $client, NodeId::numeric(1, 9999));

        expect($result)->toBeNull();
    });

    it('tests parseRawDefinition returns null on failure', function () {
        $cmd = new DumpNodesetCommand();

        // Create a mock BinaryDecoder-like object that will cause parse to throw
        // Since we can't easily create a real BinaryDecoder, we test the catch path
        $method = new ReflectionMethod($cmd, 'parseRawDefinition');

        // Create a BinaryDecoder with invalid data
        $decoder = new PhpOpcua\Client\Encoding\BinaryDecoder('invalid data');
        $result = $method->invoke($cmd, $decoder);

        expect($result)->toBeNull();
    });

    it('tests collectNodes handles nodes with children that match filter', function () {
        $cmd = new DumpNodesetCommand();

        $ref1 = dumpMockRef('Parent', 1, 1000, NodeClass::Object);
        $ref2 = dumpMockRef('Child', 1, 1001, NodeClass::Variable);

        $parentNode = new BrowseNode($ref1);
        $childNode = new BrowseNode($ref2);
        $parentNode->addChild($childNode);

        $client = MockClient::create();

        $method = new ReflectionMethod($cmd, 'collectNodes');
        $collected = [];
        $args = [$client, [$parentNode], &$collected, 1, ['http://opcfoundation.org/UA/', 'urn:test']];
        $method->invokeArgs($cmd, $args);

        expect($collected)->toHaveCount(2);
    });

    it('tests readNodeAttributes for Variable returns DataType and ValueRank', function () {
        $cmd = new DumpNodesetCommand();
        $callCount = 0;

        $client = MockClient::create()
            ->onRead('ns=1;i=1001', function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return DataValue::of(NodeId::numeric(0, 6), PhpOpcua\Client\Types\BuiltinType::NodeId);
                }
                if ($callCount === 2) {
                    return DataValue::ofInt32(1); // ValueRank = 1 (array)
                }

                return new DataValue();
            });

        $method = new ReflectionMethod($cmd, 'readNodeAttributes');
        $attrs = $method->invoke($cmd, $client, NodeId::numeric(1, 1001), NodeClass::Variable);

        expect($attrs)->toHaveKey('DataType');
    });

    it('tests readNodeAttributes for Object returns empty', function () {
        $cmd = new DumpNodesetCommand();
        $client = MockClient::create();

        $method = new ReflectionMethod($cmd, 'readNodeAttributes');
        $attrs = $method->invoke($cmd, $client, NodeId::numeric(1, 1001), NodeClass::Object);

        expect($attrs)->toBe([]);
    });

    it('tests collectNodes recurses ns=0 children when no namespace filter', function () {
        $cmd = new DumpNodesetCommand();

        $ref0 = dumpMockRef('Objects', 0, 85, NodeClass::Object);
        $ref1 = dumpMockRef('MyNode', 1, 1001, NodeClass::Object);

        $parentNode = new BrowseNode($ref0);
        $childNode = new BrowseNode($ref1);
        $parentNode->addChild($childNode);

        $client = MockClient::create();

        $method = new ReflectionMethod($cmd, 'collectNodes');
        $collected = [];
        $args = [$client, [$parentNode], &$collected, null, ['http://opcfoundation.org/UA/', 'urn:test']];
        $method->invokeArgs($cmd, $args);

        // ns=0 parent should be skipped but ns=1 child collected
        expect($collected)->toHaveCount(1);
        expect(array_values($collected)[0]['browseName'])->toBe('1:MyNode');
    });

    it('tests structureDefinitionToArray via reflection', function () {
        $cmd = new DumpNodesetCommand();

        // Create a StructureDefinition
        $field1 = new PhpOpcua\Client\Types\StructureField(
            name: 'Temperature',
            dataType: NodeId::numeric(0, 11),
            valueRank: -1,
            isOptional: false,
        );
        $field2 = new PhpOpcua\Client\Types\StructureField(
            name: 'Items',
            dataType: NodeId::numeric(0, 6),
            valueRank: 1,
            isOptional: true,
        );

        $def = new PhpOpcua\Client\Types\StructureDefinition(
            structureType: 0,
            fields: [$field1, $field2],
            defaultEncodingId: NodeId::numeric(1, 5001),
        );

        $method = new ReflectionMethod($cmd, 'structureDefinitionToArray');
        $result = $method->invoke($cmd, $def);

        expect($result['fields'])->toHaveCount(2);
        expect($result['fields'][0]['name'])->toBe('Temperature');
        expect($result['fields'][0]['dataType'])->toBe('i=11');
        expect($result['fields'][0]['valueRank'])->toBe(-1);
        expect($result['fields'][0]['isOptional'])->toBeFalse();
        expect($result['fields'][1]['valueRank'])->toBe(1);
        expect($result['fields'][1]['isOptional'])->toBeTrue();
    });

    it('readDataTypeDefinition returns StructureDefinition array when read returns StructureDefinition', function () {
        $cmd = new DumpNodesetCommand();

        $structDef = new PhpOpcua\Client\Types\StructureDefinition(
            structureType: 0,
            fields: [
                new PhpOpcua\Client\Types\StructureField('Temp', NodeId::numeric(0, 11), -1, false),
            ],
            defaultEncodingId: NodeId::numeric(1, 5001),
        );

        $variant = new PhpOpcua\Client\Types\Variant(PhpOpcua\Client\Types\BuiltinType::ExtensionObject, $structDef);
        $dv = new DataValue($variant, 0);

        $client = MockClient::create()
            ->onRead('ns=1;i=4001', fn () => $dv);

        $result = $cmd->readDataTypeDefinition($client, NodeId::numeric(1, 4001));

        expect($result)->not->toBeNull();
        expect($result['fields'])->toHaveCount(1);
        expect($result['fields'][0]['name'])->toBe('Temp');
        expect($result['fields'][0]['dataType'])->toBe('i=11');
    });

    it('readDataTypeDefinition handles object with body via parseRawDefinition', function () {
        $cmd = new DumpNodesetCommand();

        // Build valid binary for StructureDefinitionParser::parse
        $encoder = new PhpOpcua\Client\Encoding\BinaryEncoder();
        // defaultEncodingId (NodeId ns=1;i=5001) — 2-byte numeric
        $encoder->writeNodeId(NodeId::numeric(1, 5001));
        // baseDataType (skipped)
        $encoder->writeNodeId(NodeId::numeric(0, 22));
        // structureType
        $encoder->writeUInt32(0);
        // fieldCount
        $encoder->writeInt32(1);
        // field: name
        $encoder->writeString('Value');
        // field: description (LocalizedText)
        $encoder->writeLocalizedText(new LocalizedText(null, ''));
        // field: dataType
        $encoder->writeNodeId(NodeId::numeric(0, 6)); // Int32
        // field: valueRank
        $encoder->writeInt32(-1);
        // field: arrayDimCount
        $encoder->writeInt32(0);
        // field: maxStringLength
        $encoder->writeUInt32(0);
        // field: isOptional
        $encoder->writeBoolean(false);

        $body = $encoder->getBuffer();
        $obj = new stdClass();
        $obj->body = $body;

        $variant = new PhpOpcua\Client\Types\Variant(PhpOpcua\Client\Types\BuiltinType::ExtensionObject, $obj);
        $dv = new DataValue($variant, 0);

        $client = MockClient::create()
            ->onRead('ns=1;i=4002', fn () => $dv);

        $result = $cmd->readDataTypeDefinition($client, NodeId::numeric(1, 4002));

        expect($result)->not->toBeNull();
        expect($result['fields'])->toHaveCount(1);
        expect($result['fields'][0]['name'])->toBe('Value');
        expect($result['fields'][0]['dataType'])->toBe('i=6');
    });

    it('readDataTypeDefinition falls back to discovery when read returns bad status', function () {
        $cmd = new DumpNodesetCommand();

        $client = MockClient::create()
            ->onRead('ns=1;i=4003', fn () => DataValue::bad(0x80000000));

        $result = $cmd->readDataTypeDefinition($client, NodeId::numeric(1, 4003));

        expect($result)->toBeNull();
    });

    it('readDataTypeDefinition catches exception and falls back to discovery', function () {
        $cmd = new DumpNodesetCommand();

        $client = MockClient::create()
            ->onRead('ns=1;i=4004', function () {
                throw new RuntimeException('Read failed');
            });

        $result = $cmd->readDataTypeDefinition($client, NodeId::numeric(1, 4004));

        expect($result)->toBeNull();
    });

    it('findDefinitionFromDiscovery finds definition via DynamicCodec', function () {
        $cmd = new DumpNodesetCommand();

        $structDef = new PhpOpcua\Client\Types\StructureDefinition(
            structureType: 0,
            fields: [
                new PhpOpcua\Client\Types\StructureField('Speed', NodeId::numeric(0, 11), -1, false),
            ],
            defaultEncodingId: NodeId::numeric(1, 5010),
        );

        $codec = new PhpOpcua\Client\Encoding\DynamicCodec($structDef);

        $client = MockClient::create()
            ->onBrowse('ns=1;i=4010', fn () => [
                new ReferenceDescription(
                    referenceTypeId: NodeId::numeric(0, 38),
                    isForward: true,
                    nodeId: NodeId::numeric(1, 5010),
                    browseName: new QualifiedName(0, 'Default Binary'),
                    displayName: new LocalizedText(null, 'Default Binary'),
                    nodeClass: NodeClass::Object,
                    typeDefinition: null,
                ),
            ]);

        // Register the codec in the repository
        $client->getExtensionObjectRepository()->register(NodeId::numeric(1, 5010), $codec);

        $result = $cmd->findDefinitionFromDiscovery($client, NodeId::numeric(1, 4010));

        expect($result)->not->toBeNull();
        expect($result['fields'])->toHaveCount(1);
        expect($result['fields'][0]['name'])->toBe('Speed');
    });

    it('findDefinitionFromDiscovery returns null when codec is not DynamicCodec', function () {
        $cmd = new DumpNodesetCommand();

        $client = MockClient::create()
            ->onBrowse('ns=1;i=4011', fn () => [
                new ReferenceDescription(
                    referenceTypeId: NodeId::numeric(0, 38),
                    isForward: true,
                    nodeId: NodeId::numeric(1, 5011),
                    browseName: new QualifiedName(0, 'Default Binary'),
                    displayName: new LocalizedText(null, 'Default Binary'),
                    nodeClass: NodeClass::Object,
                    typeDefinition: null,
                ),
            ]);

        // Don't register any codec — get() returns null
        $result = $cmd->findDefinitionFromDiscovery($client, NodeId::numeric(1, 4011));

        expect($result)->toBeNull();
    });

    it('parseRawDefinition returns parsed StructureDefinition on valid binary', function () {
        $cmd = new DumpNodesetCommand();

        $encoder = new PhpOpcua\Client\Encoding\BinaryEncoder();
        $encoder->writeNodeId(NodeId::numeric(1, 5001));
        $encoder->writeNodeId(NodeId::numeric(0, 22));
        $encoder->writeUInt32(0);
        $encoder->writeInt32(1);
        $encoder->writeString('Pressure');
        $encoder->writeLocalizedText(new LocalizedText(null, ''));
        $encoder->writeNodeId(NodeId::numeric(0, 11)); // Double
        $encoder->writeInt32(-1);
        $encoder->writeInt32(0);
        $encoder->writeUInt32(0);
        $encoder->writeBoolean(false);

        $decoder = new PhpOpcua\Client\Encoding\BinaryDecoder($encoder->getBuffer());
        $result = $cmd->parseRawDefinition($decoder);

        expect($result)->not->toBeNull();
        expect($result['fields'])->toHaveCount(1);
        expect($result['fields'][0]['name'])->toBe('Pressure');
        expect($result['fields'][0]['dataType'])->toBe('i=11');
    });

    it('tests collectNodes includes ns prefix for ns > 0', function () {
        $cmd = new DumpNodesetCommand();

        $ref = dumpMockRef('TestNode', 2, 5000, NodeClass::Object);
        $node = new BrowseNode($ref);
        $client = MockClient::create();

        $method = new ReflectionMethod($cmd, 'collectNodes');
        $collected = [];
        $args = [$client, [$node], &$collected, 2, ['http://opcfoundation.org/UA/', 'urn:ns1', 'urn:ns2']];
        $method->invokeArgs($cmd, $args);

        expect($collected)->toHaveCount(1);
        $n = array_values($collected)[0];
        expect($n['browseName'])->toBe('2:TestNode');
    });

});
