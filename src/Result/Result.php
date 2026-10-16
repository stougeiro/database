<?php declare(strict_types=1);

    namespace STDW\Database\Result;

    use STDW\Contract\Database\Result\ResultInterface;


    /**
     * Universal return of every driver operation (except stream()).
     *
     * Semantics by operation:
     * - create/read: count() = 1 on success, 0 otherwise.
     * - list: count() = number of returned records.
     * - update/delete: count() = number of affected records.
     * - total: count() = the matching records (data() = null).
     * - capabilities (Batch insertMany/updateMany): count() = inserted /
     *   affected records across all entries.
     * isValid() reports whether the operation itself succeeded.
     */
    final readonly class Result implements ResultInterface
    {
        /**
         * @param null|array<int|string, mixed>|object $data
         * @param int $count
         * @param bool $valid
         */
        private function __construct(
            private null|array|object $data,
            private int $count,
            private bool $valid,
        ) { }


        /**
         * @param null|array<int|string, mixed>|object $data
         * @param int $count
         * @return self
         */
        public static function make(null|array|object $data, int $count): self
        {
            return new self($data, $count, true);
        }

        /**
         * @param null|array<int|string, mixed>|object $data
         * @param int $count
         * @return self
         */
        public static function invalid(null|array|object $data = null, int $count = 0): self
        {
            return new self($data, $count, false);
        }


        /** @return null|array<int|string, mixed>|object
         */
        public function data(): null|array|object
        {
            return $this->data;
        }

        /** @return int
         */
        public function count(): int
        {
            return $this->count;
        }

        /** @return bool
         */
        public function isValid(): bool
        {
            return $this->valid;
        }
    }
