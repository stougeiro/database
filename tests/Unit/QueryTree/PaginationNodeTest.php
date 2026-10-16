<?php declare(strict_types=1);

    use STDW\Database\Exception\QueryTreeException;
    use STDW\Database\QueryTree\PaginationNode;


    test('keeps explicit limit and offset', function () {
        $node = new PaginationNode(limit: 10, offset: 20);

        expect($node->limit())->toBe(10)
            ->and($node->offset())->toBe(20)
            ->and($node->page())->toBeNull()
            ->and($node->perPage())->toBeNull()
            ->and($node->cursor())->toBeNull();
    });

    test('derives limit and offset from page and perPage', function () {
        $node = new PaginationNode(page: 3, perPage: 15);

        expect($node->limit())->toBe(15)
            ->and($node->offset())->toBe(30);
    });

    test('keeps the raw page and perPage', function () {
        $node = new PaginationNode(page: 2, perPage: 5);

        expect($node->page())->toBe(2)
            ->and($node->perPage())->toBe(5);
    });

    test('holds a cursor', function () {
        $node = new PaginationNode(cursor: 'opaque-token');

        expect($node->cursor())->toBe('opaque-token');
    });

    test('rejects a negative limit', function () {
        new PaginationNode(limit: -1);
    })->throws(QueryTreeException::class);

    test('rejects a zero page', function () {
        new PaginationNode(page: 0);
    })->throws(QueryTreeException::class);
