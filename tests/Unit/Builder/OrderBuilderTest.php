<?php declare(strict_types=1);

    use STDW\Contract\Database\QueryTree\Types\OrderDirection;
    use STDW\Database\Builder\OrderBuilder;
    use STDW\Database\Exception\QueryTreeException;
    use STDW\Database\QueryTree\OrderNode;


    test('builds order nodes from a direction map', function () {
        $nodes = OrderBuilder::make(['name' => 'asc', 'age' => 'DESC']);

        expect($nodes)->toHaveCount(2)
            ->and($nodes[0])->toBeInstanceOf(OrderNode::class)
            ->and($nodes[0]->column())->toBe('name')
            ->and($nodes[0]->direction())->toBe(OrderDirection::Asc)
            ->and($nodes[1]->direction())->toBe(OrderDirection::Desc);
    });

    test('accepts the OrderDirection enum', function () {
        $nodes = OrderBuilder::make(['name' => OrderDirection::Desc]);

        expect($nodes[0]->direction())->toBe(OrderDirection::Desc);
    });

    test('returns an empty list for an empty map', function () {
        expect(OrderBuilder::make([]))->toBe([]);
    });

    test('rejects list-shaped order', function () {
        OrderBuilder::make(['name']);
    })->throws(QueryTreeException::class);

    test('rejects invalid directions', function () {
        OrderBuilder::make(['name' => 'sideways']);
    })->throws(QueryTreeException::class);

    test('rejects empty columns', function () {
        OrderBuilder::make(['' => 'asc']);
    })->throws(QueryTreeException::class);
