<?php declare(strict_types=1);

    namespace STDW\Database\QueryTree;

    use STDW\Contract\Database\QueryTree\OrderNodeInterface;
    use STDW\Contract\Database\QueryTree\Types\OrderDirection;
    use STDW\Database\Exception\QueryTreeException;


    /**
     * A single ORDER BY entry: column + neutral direction.
     */
    final readonly class OrderNode implements OrderNodeInterface
    {
        /**
         * @param string $column
         * @param OrderDirection $direction
         * @throws QueryTreeException
         */
        public function __construct(
            private string $column,
            private OrderDirection $direction = OrderDirection::Asc,
        ) {
            if (trim($column) === '') {
                throw QueryTreeException::invalid('order columns cannot be empty');
            }
        }


        /** @return string
         */
        public function column(): string
        {
            return $this->column;
        }

        /** @return OrderDirection
         */
        public function direction(): OrderDirection
        {
            return $this->direction;
        }
    }
