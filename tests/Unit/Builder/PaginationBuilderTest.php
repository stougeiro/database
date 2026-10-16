<?php declare(strict_types=1);

    use STDW\Database\Builder\PaginationBuilder;
    use STDW\Database\Exception\QueryTreeException;
    use STDW\Database\QueryTree\PaginationNode;


    test('builds a node from the canonical keys', function () {
        $node = PaginationBuilder::make(['limit' => 10, 'offset' => 5, 'cursor' => 'abc']);

        expect($node)->toBeInstanceOf(PaginationNode::class)
            ->and($node->limit())->toBe(10)
            ->and($node->offset())->toBe(5)
            ->and($node->cursor())->toBe('abc');
    });

    test('builds a node from page and perPage', function () {
        $node = PaginationBuilder::make(['page' => 2, 'perPage' => 10]);

        expect($node->limit())->toBe(10)
            ->and($node->offset())->toBe(10);
    });

    test('builds an empty node', function () {
        $node = PaginationBuilder::make([]);

        expect($node->limit())->toBeNull()
            ->and($node->offset())->toBeNull()
            ->and($node->cursor())->toBeNull();
    });

    test('rejects unknown keys', function () {
        PaginationBuilder::make(['size' => 10]);
    })->throws(QueryTreeException::class);

    test('rejects non-integer values', function () {
        PaginationBuilder::make(['limit' => '10']);
    })->throws(QueryTreeException::class);

    test('rejects out-of-range values', function () {
        PaginationBuilder::make(['page' => 0]);
    })->throws(QueryTreeException::class);
