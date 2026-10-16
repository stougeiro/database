<?php declare(strict_types=1);

namespace Tests\Stubs\Models;

use STDW\Contract\Database\Manager\DatabaseManagerInterface;
use STDW\Contract\Database\Resource\ResourceMetadataInterface;
use STDW\Database\Model\ModelAbstracted;
use STDW\Database\Resource\ResourceMetadata;

final class User extends ModelAbstracted
{
    protected string $resource = 'users';

    protected string $connection = 'default';

    protected ?ResourceMetadataInterface $metadata = null;

    public function __construct(DatabaseManagerInterface $manager)
    {
        parent::__construct($manager);

        $this->metadata = new ResourceMetadata(
            primaryKey: ['name'],
            fields: ['name' => 'string', 'email' => 'string', 'age' => 'int'],
        );
    }
}
