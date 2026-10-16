<?php declare(strict_types=1);

    namespace STDW\Database\Resource;

    use STDW\Contract\Database\Resource\DatabaseResourceInterface;
    use STDW\Contract\Database\Resource\ResourceMetadataAwareInterface;
    use STDW\Contract\Database\Resource\ResourceMetadataInterface;
    use STDW\Database\Exception\ResourceException;


    /**
     * Represents a named resource (table/collection) in the database.
     *
     * The contract is intentionally minimal: only the name. When the schema is
     * known at design time it can be attached as metadata, which consumers
     * (e.g. the SQLite search fallback) read via ResourceMetadataAwareInterface;
     * when absent, an empty metadata is returned.
     */
    final class Resource implements DatabaseResourceInterface, ResourceMetadataAwareInterface
    {
        private ResourceMetadataInterface $meta;

        /**
         * @param string $name
         * @param null|ResourceMetadataInterface $metadata
         * @throws ResourceException
         */
        public function __construct(
            private readonly string $name,
            ?ResourceMetadataInterface $metadata = null,
        ) {
            if (trim($name) === '') {
                throw ResourceException::emptyName();
            }

            $this->meta = $metadata ?? new ResourceMetadata();
        }


        /** @return string
         */
        public function name(): string
        {
            return $this->name;
        }

        /** @return ResourceMetadataInterface
         */
        public function metadata(): ResourceMetadataInterface
        {
            return $this->meta;
        }
    }
