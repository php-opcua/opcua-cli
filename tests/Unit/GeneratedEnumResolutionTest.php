<?php

declare(strict_types=1);

use PhpOpcua\Cli\CodeGenerator;
use PhpOpcua\Client\Encoding\BinaryDecoder;
use PhpOpcua\Client\Encoding\BinaryEncoder;

describe('CodeGenerator — enum namespace resolution', function () {

    it('imports the spec Enums namespace into a DTO that has enum fields', function () {
        $code = (new CodeGenerator())->generateDtoClass(
            'Snapshot',
            [['name' => 'Status', 'dataType' => 'ns=1;i=100']],
            'App\\Spec',
            ['ns=1;i=100' => 'StatusEnum'],
        );

        expect($code)->toContain('namespace App\\Spec\\Types;');
        expect($code)->toContain('use App\\Spec\\Enums;');
        expect($code)->toContain('public Enums\\StatusEnum $Status');
    });

    it('leaves a DTO without enum fields free of the import', function () {
        $code = (new CodeGenerator())->generateDtoClass(
            'Point',
            [['name' => 'X', 'dataType' => 'i=11']],
            'App\\Spec',
        );

        expect($code)->not->toContain('use App\\Spec\\Enums;');
    });

    it('imports the spec Enums namespace into a codec that decodes enums', function () {
        $code = (new CodeGenerator())->generateCodecClass(
            'SnapshotCodec',
            'Snapshot',
            [['name' => 'Status', 'dataType' => 'ns=1;i=100']],
            'App\\Spec',
            ['ns=1;i=100' => 'StatusEnum'],
        );

        expect($code)->toContain('namespace App\\Spec\\Codecs;');
        expect($code)->toContain('use App\\Spec\\Enums;');
        expect($code)->toContain('Enums\\StatusEnum::from($decoder->readInt32())');
    });

    it('leaves a codec without enum fields free of the import', function () {
        $code = (new CodeGenerator())->generateCodecClass(
            'PointCodec',
            'Point',
            [['name' => 'X', 'dataType' => 'i=11']],
            'App\\Spec',
        );

        expect($code)->not->toContain('use App\\Spec\\Enums;');
    });

    it('generates an enum DTO and codec that actually load and round-trip', function () {
        $gen = new CodeGenerator();
        // A namespace unique to this test run, so the eval'd classes never collide.
        $ns = 'GenCheck' . bin2hex(random_bytes(4));

        $fields = [['name' => 'Status', 'dataType' => 'ns=1;i=100']];
        $enumMap = ['ns=1;i=100' => 'StatusEnum'];

        $strip = static fn (string $php): string => preg_replace('/^<\?php\s*/', '', $php) ?? $php;

        eval($strip($gen->generateEnumClass('StatusEnum', [
            ['name' => 'IDLE', 'value' => 0],
            ['name' => 'RUNNING', 'value' => 1],
        ], $ns)));
        eval($strip($gen->generateDtoClass('Snapshot', $fields, $ns, $enumMap)));
        eval($strip($gen->generateCodecClass('SnapshotCodec', 'Snapshot', $fields, $ns, $enumMap)));

        $enumClass = $ns . '\\Enums\\StatusEnum';
        $dtoClass = $ns . '\\Types\\Snapshot';
        $codecClass = $ns . '\\Codecs\\SnapshotCodec';

        expect(enum_exists($enumClass))->toBeTrue();

        // The DTO must accept the enum the codec produces — this is what the
        // missing import broke: the declared type resolved to <ns>\Types\Enums\*,
        // which no value can ever satisfy.
        $dto = new $dtoClass($enumClass::RUNNING);
        expect($dto->Status)->toBe($enumClass::RUNNING);

        $encoder = new BinaryEncoder();
        (new $codecClass())->encode($encoder, $dto);

        $decoded = (new $codecClass())->decode(new BinaryDecoder($encoder->getBuffer()));

        expect($decoded)->toBeInstanceOf($dtoClass);
        expect($decoded->Status)->toBe($enumClass::RUNNING);
    });

});
