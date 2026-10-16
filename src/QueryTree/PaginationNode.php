<?php declare(strict_types=1);

    namespace STDW\Database\QueryTree;

    use STDW\Contract\Database\QueryTree\PaginationNodeInterface;
    use STDW\Database\Exception\QueryTreeException;


    /**
     * Pagination parameters.
     *
     * limit()/offset() expose the canonical values: when only page/perPage
     * are given they are derived (limit = perPage, offset = (page-1)*perPage).
     * On the driver edge the cursor takes precedence over both forms
     * (keyset pagination) — the driver checks cursor() first.
     */
    final readonly class PaginationNode implements PaginationNodeInterface
    {
        /**
         * @param null|int $limit
         * @param null|int $offset
         * @param null|int $page
         * @param null|int $perPage
         * @param mixed $cursor
         * @throws QueryTreeException
         */
        public function __construct(
            private ?int $limit = null,
            private ?int $offset = null,
            private ?int $page = null,
            private ?int $perPage = null,
            private mixed $cursor = null,
        ) {
            if ($limit !== null && $limit < 0) {
                throw QueryTreeException::invalid('limit cannot be negative');
            }

            if ($offset !== null && $offset < 0) {
                throw QueryTreeException::invalid('offset cannot be negative');
            }

            if ($page !== null && $page < 1) {
                throw QueryTreeException::invalid('page must be greater than or equal to 1');
            }

            if ($perPage !== null && $perPage < 1) {
                throw QueryTreeException::invalid('perPage must be greater than or equal to 1');
            }
        }


        /** @return null|int
         */
        public function limit(): ?int
        {
            if ($this->limit !== null) {
                return $this->limit;
            }

            if ($this->page !== null && $this->perPage !== null) {
                return $this->perPage;
            }

            return null;
        }

        /** @return null|int
         */
        public function offset(): ?int
        {
            if ($this->offset !== null) {
                return $this->offset;
            }

            if ($this->page !== null && $this->perPage !== null) {
                return ($this->page - 1) * $this->perPage;
            }

            return null;
        }

        /** @return null|int
         */
        public function page(): ?int
        {
            return $this->page;
        }

        /** @return null|int
         */
        public function perPage(): ?int
        {
            return $this->perPage;
        }

        /** @return mixed
         */
        public function cursor(): mixed
        {
            return $this->cursor;
        }
    }
