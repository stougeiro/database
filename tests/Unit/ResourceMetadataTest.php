<?php declare(strict_types=1);

    use STDW\Database\Resource\ResourceMetadata;


    test('exposes its schema', function () {
        $metadata = new ResourceMetadata(
            primaryKey: ['tenant_id', 'id'],
            fields: ['id' => 'int', 'name' => 'string'],
            foreignKeys: ['user_id' => 'users.id'],
        );

        expect($metadata->primaryKey())->toBe(['tenant_id', 'id'])
            ->and($metadata->fields())->toBe(['id' => 'int', 'name' => 'string'])
            ->and($metadata->foreignKeys())->toBe(['user_id' => 'users.id']);
    });

    test('defaults to an empty schema', function () {
        $metadata = new ResourceMetadata();

        expect($metadata->primaryKey())->toBe([])
            ->and($metadata->fields())->toBe([])
            ->and($metadata->foreignKeys())->toBe([]);
    });
