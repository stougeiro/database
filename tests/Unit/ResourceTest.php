<?php declare(strict_types=1);

    use STDW\Database\Exception\ResourceException;
    use STDW\Database\Resource\Resource;
    use STDW\Database\Resource\ResourceMetadata;


    test('exposes its name', function () {
        $resource = new Resource('users');

        expect($resource->name())->toBe('users');
    });

    test('returns empty metadata when none is attached', function () {
        $resource = new Resource('users');
        $metadata = $resource->metadata();

        expect($metadata)->toBeInstanceOf(ResourceMetadata::class)
            ->and($metadata->primaryKey())->toBe([])
            ->and($metadata->fields())->toBe([])
            ->and($metadata->foreignKeys())->toBe([]);
    });

    test('exposes the attached metadata', function () {
        $metadata = new ResourceMetadata(
            primaryKey: ['id'],
            fields: ['id' => 'int', 'name' => 'string'],
            foreignKeys: ['user_id' => 'users.id'],
        );

        $resource = new Resource('posts', $metadata);

        expect($resource->metadata())->toBe($metadata);
    });

    test('rejects an empty name', function () {
        new Resource('   ');
    })->throws(ResourceException::class);
