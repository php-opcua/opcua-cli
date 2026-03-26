<?php

declare(strict_types=1);

use PhpOpcua\Cli\NodeSetParser;

$fixturePath = __DIR__ . '/../Fixtures/TestNodeSet2.xml';

describe('NodeSetParser', function () use ($fixturePath) {

    it('parses aliases from XML', function () use ($fixturePath) {
        $parser = new NodeSetParser();
        $parser->parse($fixturePath);

        $aliases = $parser->getAliases();
        expect($aliases)->toHaveKey('Boolean');
        expect($aliases['Boolean'])->toBe('i=1');
        expect($aliases['Int32'])->toBe('i=6');
        expect($aliases['Double'])->toBe('i=11');
        expect($aliases['String'])->toBe('i=12');
        expect($aliases['HasEncoding'])->toBe('i=38');
    });

    it('parses node elements', function () use ($fixturePath) {
        $parser = new NodeSetParser();
        $parser->parse($fixturePath);

        $nodes = $parser->getNodes();
        expect($nodes)->toHaveKey('ns=1;i=1000');
        expect($nodes['ns=1;i=1000']['browseName'])->toBe('TestFolder');
        expect($nodes['ns=1;i=1000']['type'])->toBe('UAObject');

        expect($nodes)->toHaveKey('ns=1;i=2001');
        expect($nodes['ns=1;i=2001']['browseName'])->toBe('Temperature');
        expect($nodes['ns=1;i=2001']['type'])->toBe('UAVariable');

        expect($nodes)->toHaveKey('ns=1;i=7001');
        expect($nodes['ns=1;i=7001']['browseName'])->toBe('Reset');
        expect($nodes['ns=1;i=7001']['type'])->toBe('UAMethod');
    });

    it('excludes Default Binary encoding objects from nodes', function () use ($fixturePath) {
        $parser = new NodeSetParser();
        $parser->parse($fixturePath);

        $nodes = $parser->getNodes();
        expect($nodes)->not->toHaveKey('ns=1;i=5001');
        expect($nodes)->not->toHaveKey('ns=1;i=5002');
        expect($nodes)->not->toHaveKey('ns=1;i=5003');
    });

    it('parses structured DataTypes with fields', function () use ($fixturePath) {
        $parser = new NodeSetParser();
        $parser->parse($fixturePath);

        $dataTypes = $parser->getDataTypes();
        expect($dataTypes)->toHaveKey('ns=1;i=3001');

        $testPoint = $dataTypes['ns=1;i=3001'];
        expect($testPoint['name'])->toBe('TestPoint');
        expect($testPoint['encodingId'])->toBe('ns=1;i=5001');
        expect($testPoint['fields'])->toHaveCount(3);
        expect($testPoint['fields'][0]['name'])->toBe('X');
        expect($testPoint['fields'][0]['dataType'])->toBe('i=11');
    });

    it('resolves alias DataTypes in fields', function () use ($fixturePath) {
        $parser = new NodeSetParser();
        $parser->parse($fixturePath);

        $dataTypes = $parser->getDataTypes();
        $person = $dataTypes['ns=1;i=3003'];

        expect($person['fields'][0]['dataType'])->toBe('i=12');
        expect($person['fields'][1]['dataType'])->toBe('i=7');
        expect($person['fields'][2]['dataType'])->toBe('i=1');
    });

    it('finds encoding NodeId from HasEncoding reference', function () use ($fixturePath) {
        $parser = new NodeSetParser();
        $parser->parse($fixturePath);

        $dataTypes = $parser->getDataTypes();
        expect($dataTypes['ns=1;i=3001']['encodingId'])->toBe('ns=1;i=5001');
        expect($dataTypes['ns=1;i=3002']['encodingId'])->toBe('ns=1;i=5002');
        expect($dataTypes['ns=1;i=3003']['encodingId'])->toBe('ns=1;i=5003');
    });

    it('includes DataTypes in nodes list', function () use ($fixturePath) {
        $parser = new NodeSetParser();
        $parser->parse($fixturePath);

        $nodes = $parser->getNodes();
        expect($nodes)->toHaveKey('ns=1;i=3001');
        expect($nodes['ns=1;i=3001']['type'])->toBe('UADataType');
    });

    it('parses enumeration definitions', function () use ($fixturePath) {
        $parser = new NodeSetParser();
        $parser->parse($fixturePath);

        $enums = $parser->getEnumerations();
        expect($enums)->toHaveKey('ns=1;i=3010');

        $testEnum = $enums['ns=1;i=3010'];
        expect($testEnum['name'])->toBe('TestStatusEnum');
        expect($testEnum['values'])->toHaveCount(4);
        expect($testEnum['values'][0]['name'])->toBe('IDLE');
        expect($testEnum['values'][0]['value'])->toBe(0);
        expect($testEnum['values'][2]['name'])->toBe('ERROR');
        expect($testEnum['values'][2]['value'])->toBe(2);
    });

    it('does not mix enums with structured DataTypes', function () use ($fixturePath) {
        $parser = new NodeSetParser();
        $parser->parse($fixturePath);

        $dataTypes = $parser->getDataTypes();
        $enums = $parser->getEnumerations();

        expect($dataTypes)->not->toHaveKey('ns=1;i=3010');
        expect($enums)->not->toHaveKey('ns=1;i=3001');
    });

    it('parses UAObjectType, UAVariableType, UAReferenceType as nodes', function () use ($fixturePath) {
        $parser = new NodeSetParser();
        $parser->parse($fixturePath);

        $nodes = $parser->getNodes();
        expect($nodes)->toHaveKey('ns=1;i=4001');
        expect($nodes['ns=1;i=4001']['browseName'])->toBe('PumpType');
        expect($nodes['ns=1;i=4001']['type'])->toBe('UAObjectType');

        expect($nodes)->toHaveKey('ns=1;i=4002');
        expect($nodes['ns=1;i=4002']['type'])->toBe('UAVariableType');

        expect($nodes)->toHaveKey('ns=1;i=4003');
        expect($nodes['ns=1;i=4003']['type'])->toBe('UAReferenceType');
    });

    it('parses ValueRank and IsOptional from fields', function () use ($fixturePath) {
        $parser = new NodeSetParser();
        $parser->parse($fixturePath);

        $dataTypes = $parser->getDataTypes();
        expect($dataTypes)->toHaveKey('ns=1;i=3020');

        $ds = $dataTypes['ns=1;i=3020'];
        expect($ds['fields'])->toHaveCount(3);
        expect($ds['fields'][0]['valueRank'])->toBe(-1);
        expect($ds['fields'][0]['isOptional'])->toBeFalse();
        expect($ds['fields'][1]['valueRank'])->toBe(1);
        expect($ds['fields'][2]['isOptional'])->toBeTrue();
    });

    it('parses RequiredModel dependencies', function () use ($fixturePath) {
        $parser = new NodeSetParser();
        $parser->parse($fixturePath);

        $required = $parser->getRequiredModels();
        expect($required)->toHaveCount(2);
        expect($required[0]['modelUri'])->toBe('http://opcfoundation.org/UA/');
        expect($required[0]['version'])->toBe('1.05.02');
        expect($required[1]['modelUri'])->toBe('http://opcfoundation.org/UA/DI/');
    });

    it('throws on invalid file', function () {
        $parser = new NodeSetParser();

        expect(fn () => $parser->parse('/nonexistent/file.xml'))
            ->toThrow(RuntimeException::class);
    });

    it('parses DataType without Definition as a node only', function () {
        $tmpXml = tempnam(sys_get_temp_dir(), 'opcua-parser-') . '.xml';
        file_put_contents($tmpXml, '<?xml version="1.0" encoding="utf-8"?>
<UANodeSet xmlns="http://opcfoundation.org/UA/2011/03/UANodeSet.xsd">
  <UADataType NodeId="ns=1;i=9000" BrowseName="1:SimpleType">
    <DisplayName>SimpleType</DisplayName>
    <References>
      <Reference ReferenceType="HasSubtype" IsForward="false">i=22</Reference>
    </References>
  </UADataType>
</UANodeSet>');

        $parser = new NodeSetParser();
        $parser->parse($tmpXml);

        $nodes = $parser->getNodes();
        expect($nodes)->toHaveKey('ns=1;i=9000');
        expect($nodes['ns=1;i=9000']['type'])->toBe('UADataType');
        expect($nodes['ns=1;i=9000']['browseName'])->toBe('SimpleType');

        $dataTypes = $parser->getDataTypes();
        expect($dataTypes)->not->toHaveKey('ns=1;i=9000');

        @unlink($tmpXml);
    });

    it('returns null encoding when no HasEncoding reference', function () {
        $tmpXml = tempnam(sys_get_temp_dir(), 'opcua-parser-') . '.xml';
        file_put_contents($tmpXml, '<?xml version="1.0" encoding="utf-8"?>
<UANodeSet xmlns="http://opcfoundation.org/UA/2011/03/UANodeSet.xsd">
  <Aliases>
    <Alias Alias="HasSubtype">i=45</Alias>
    <Alias Alias="Double">i=11</Alias>
  </Aliases>
  <UADataType NodeId="ns=1;i=9001" BrowseName="1:NoEncodingType">
    <DisplayName>NoEncodingType</DisplayName>
    <References>
      <Reference ReferenceType="HasSubtype" IsForward="false">i=22</Reference>
    </References>
    <Definition Name="NoEncodingType">
      <Field Name="Value" DataType="Double" />
    </Definition>
  </UADataType>
</UANodeSet>');

        $parser = new NodeSetParser();
        $parser->parse($tmpXml);

        $dataTypes = $parser->getDataTypes();
        expect($dataTypes)->toHaveKey('ns=1;i=9001');
        expect($dataTypes['ns=1;i=9001']['encodingId'])->toBeNull();

        @unlink($tmpXml);
    });

    it('resolves builtin type names directly without alias', function () {
        $tmpXml = tempnam(sys_get_temp_dir(), 'opcua-parser-') . '.xml';
        file_put_contents($tmpXml, '<?xml version="1.0" encoding="utf-8"?>
<UANodeSet xmlns="http://opcfoundation.org/UA/2011/03/UANodeSet.xsd">
  <UADataType NodeId="ns=1;i=9002" BrowseName="1:DirectTypes">
    <DisplayName>DirectTypes</DisplayName>
    <Definition Name="DirectTypes">
      <Field Name="Id" DataType="Guid" />
      <Field Name="Data" DataType="ByteString" />
      <Field Name="Ts" DataType="DateTime" />
    </Definition>
  </UADataType>
</UANodeSet>');

        $parser = new NodeSetParser();
        $parser->parse($tmpXml);

        $dataTypes = $parser->getDataTypes();
        expect($dataTypes['ns=1;i=9002']['fields'][0]['dataType'])->toBe('i=14');  // Guid
        expect($dataTypes['ns=1;i=9002']['fields'][1]['dataType'])->toBe('i=15');  // ByteString
        expect($dataTypes['ns=1;i=9002']['fields'][2]['dataType'])->toBe('i=13');  // DateTime

        @unlink($tmpXml);
    });

    it('handles backward reference in findEncodingId', function () {
        $tmpXml = tempnam(sys_get_temp_dir(), 'opcua-parser-') . '.xml';
        file_put_contents($tmpXml, '<?xml version="1.0" encoding="utf-8"?>
<UANodeSet xmlns="http://opcfoundation.org/UA/2011/03/UANodeSet.xsd">
  <Aliases>
    <Alias Alias="HasEncoding">i=38</Alias>
    <Alias Alias="Double">i=11</Alias>
  </Aliases>
  <UADataType NodeId="ns=1;i=9003" BrowseName="1:BackwardEnc">
    <DisplayName>BackwardEnc</DisplayName>
    <References>
      <Reference ReferenceType="HasEncoding" IsForward="false">ns=1;i=9999</Reference>
    </References>
    <Definition Name="BackwardEnc">
      <Field Name="Val" DataType="Double" />
    </Definition>
  </UADataType>
</UANodeSet>');

        $parser = new NodeSetParser();
        $parser->parse($tmpXml);

        $dataTypes = $parser->getDataTypes();
        // Backward HasEncoding should not be picked up
        expect($dataTypes['ns=1;i=9003']['encodingId'])->toBeNull();

        @unlink($tmpXml);
    });

    it('resolves unknown data type reference as-is', function () {
        $tmpXml = tempnam(sys_get_temp_dir(), 'opcua-parser-') . '.xml';
        file_put_contents($tmpXml, '<?xml version="1.0" encoding="utf-8"?>
<UANodeSet xmlns="http://opcfoundation.org/UA/2011/03/UANodeSet.xsd">
  <UADataType NodeId="ns=1;i=9004" BrowseName="1:CustomRef">
    <DisplayName>CustomRef</DisplayName>
    <Definition Name="CustomRef">
      <Field Name="Custom" DataType="ns=2;i=5555" />
    </Definition>
  </UADataType>
</UANodeSet>');

        $parser = new NodeSetParser();
        $parser->parse($tmpXml);

        $dataTypes = $parser->getDataTypes();
        expect($dataTypes['ns=1;i=9004']['fields'][0]['dataType'])->toBe('ns=2;i=5555');

        @unlink($tmpXml);
    });

    it('sanitizes field names with special chars', function () {
        $tmpXml = tempnam(sys_get_temp_dir(), 'opcua-parser-') . '.xml';
        file_put_contents($tmpXml, '<?xml version="1.0" encoding="utf-8"?>
<UANodeSet xmlns="http://opcfoundation.org/UA/2011/03/UANodeSet.xsd">
  <Aliases>
    <Alias Alias="Double">i=11</Alias>
  </Aliases>
  <UADataType NodeId="ns=1;i=9005" BrowseName="1:SpecialFields">
    <DisplayName>SpecialFields</DisplayName>
    <Definition Name="SpecialFields">
      <Field Name="My-Field.Name" DataType="Double" />
    </Definition>
  </UADataType>
</UANodeSet>');

        $parser = new NodeSetParser();
        $parser->parse($tmpXml);

        $dataTypes = $parser->getDataTypes();
        expect($dataTypes['ns=1;i=9005']['fields'][0]['name'])->toBe('My_Field_Name');

        @unlink($tmpXml);
    });
});
