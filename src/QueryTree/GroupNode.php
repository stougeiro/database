<?php declare(strict_types=1);

    namespace STDW\Database\QueryTree;

    use STDW\Contract\Database\QueryTree\ConditionNodeInterface;
    use STDW\Contract\Database\QueryTree\GroupNodeInterface;
    use STDW\Contract\Database\QueryTree\LogicNodeInterface;
    use STDW\Database\Exception\QueryTreeException;


    /**
     * A set of nodes grouped together (parentheses). The group is the root of
     * the condition expression: a single ConditionNode is a leaf; a GroupNode
     * composes leaves, logic tokens and nested groups. There is no separate
     * root node.
     */
    final readonly class GroupNode implements GroupNodeInterface
    {
        /** @var array<int, ConditionNodeInterface|LogicNodeInterface|GroupNodeInterface>
         */
        private array $children;

        /**
         * @param array<array-key, mixed> $nodes
         * @throws QueryTreeException
         */
        public function __construct(array $nodes = [])
        {
            $children = [];

            foreach ($nodes as $node) {
                if (
                       $node instanceof ConditionNodeInterface
                    || $node instanceof LogicNodeInterface
                    || $node instanceof GroupNodeInterface
                ) {
                    $children[] = $node;

                    continue;
                }

                throw QueryTreeException::invalid('group children must be condition, logic or group nodes');
            }

            $this->children = $children;
        }


        /** @return array<int, ConditionNodeInterface|LogicNodeInterface|GroupNodeInterface>
         */
        public function nodes(): array
        {
            return $this->children;
        }
    }
