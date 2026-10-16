<?php declare(strict_types=1);

    namespace STDW\Database\QueryTree;

    use STDW\Contract\Database\QueryTree\LogicNodeInterface;
    use STDW\Contract\Database\QueryTree\Types\LogicOperator;


    /**
     * Carries a single logical operator (and/or/not). A LogicNode applies to
     * the operand that follows it; the default join of a group is AND.
     */
    final readonly class LogicNode implements LogicNodeInterface
    {
        /** @param LogicOperator $operator
         */
        public function __construct(
            private LogicOperator $operator,
        ) { }


        /** @return LogicOperator
         */
        public function operator(): LogicOperator
        {
            return $this->operator;
        }
    }
