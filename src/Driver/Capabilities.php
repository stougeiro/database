<?php declare(strict_types=1);

    namespace STDW\Database\Driver;

    use STDW\Contract\Database\Driver\DriverCapabilitiesInterface;


    /**
     * Announces which optional, engine-level capabilities a driver honors.
     * Every flag defaults to false: a driver opts in per capability.
     */
    final readonly class Capabilities implements DriverCapabilitiesInterface
    {
        /**
         * @param bool $aggregation
         * @param bool $batch
         * @param bool $search
         * @param bool $streaming
         * @param bool $transactions
         * @param bool $upsert
         */
        public function __construct(
            private bool $aggregation = false,
            private bool $batch = false,
            private bool $search = false,
            private bool $streaming = false,
            private bool $transactions = false,
            private bool $upsert = false,
        ) { }


        /** @return bool
         */
        public function supportsAggregation(): bool
        {
            return $this->aggregation;
        }

        /** @return bool
         */
        public function supportsBatch(): bool
        {
            return $this->batch;
        }

        /** @return bool
         */
        public function supportsSearch(): bool
        {
            return $this->search;
        }

        /** @return bool
         */
        public function supportsStreaming(): bool
        {
            return $this->streaming;
        }

        /** @return bool
         */
        public function supportsTransactions(): bool
        {
            return $this->transactions;
        }

        /** @return bool
         */
        public function supportsUpsert(): bool
        {
            return $this->upsert;
        }
    }
