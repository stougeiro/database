<?php declare(strict_types=1);

    namespace STDW\Database\Resource;

    use STDW\Contract\Database\Resource\ResourceMetadataInterface;


    /**
     * Describes a resource schema (primary key, fields and foreign keys).
     */
    final readonly class ResourceMetadata implements ResourceMetadataInterface
    {
        /**
         * @param array<int, string> $primaryKey
         * @param array<string, string> $fields Column => storage type.
         * @param array<string, string> $foreignKeys Column => referenced column.
         */
        public function __construct(
            private array $primaryKey = [],
            private array $fields = [],
            private array $foreignKeys = [],
        ) { }


        /** @return array<int, string>
         */
        public function primaryKey(): array
        {
            return $this->primaryKey;
        }

        /** @return array<string, string>
         */
        public function foreignKeys(): array
        {
            return $this->foreignKeys;
        }

        /** @return array<string, string>
         */
        public function fields(): array
        {
            return $this->fields;
        }
    }
