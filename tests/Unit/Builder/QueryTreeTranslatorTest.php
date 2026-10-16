<?php declare(strict_types=1);

    use STDW\Contract\Database\QueryTree\Types\OrderDirection;
    use STDW\Database\Builder\FilterBuilder;
    use STDW\Database\Builder\OrderBuilder;
    use STDW\Database\Builder\PaginationBuilder;
    use STDW\Database\Builder\QueryTreeTranslator;
    use STDW\Database\Param\Param;


    test('round-trips a predicate filter', function () {
        $filter = ['age GTE' => Param::int(18), 'status' => Param::str('active')];

        expect(QueryTreeTranslator::filter(FilterBuilder::make($filter)))->toBe($filter);
    });

    test('translates a single null condition to its canonical form', function () {
        $filter = ['deleted_at IS_NULL' => null];

        expect(QueryTreeTranslator::filter(FilterBuilder::make($filter)))->toBe($filter);
    });

    test('round-trips a clause list with groups', function () {
        $filter = [
            ['name LIKE' => Param::str('%a%')],
            'OR',
            ['name LIKE' => Param::str('%b%')],
        ];

        expect(QueryTreeTranslator::filter(FilterBuilder::make($filter)))->toBe($filter);
    });

    test('omits EQ from the operator key', function () {
        $filter = ['status' => Param::str('active')];

        expect(QueryTreeTranslator::filter(FilterBuilder::make($filter)))
            ->toHaveKey('status')
            ->not->toHaveKey('status EQ');
    });

    test('translates order nodes back to the canonical map', function () {
        $order = QueryTreeTranslator::order(OrderBuilder::make(['name' => 'asc', 'age' => OrderDirection::Desc]));

        expect($order)->toBe(['name' => OrderDirection::Asc, 'age' => OrderDirection::Desc]);
    });

    test('translates pagination nodes back to the canonical array', function () {
        $pagination = QueryTreeTranslator::pagination(PaginationBuilder::make(['page' => 2, 'perPage' => 10]));

        expect($pagination)->toBe(['limit' => 10, 'offset' => 10]);
    });

    test('keeps the cursor in the canonical pagination', function () {
        $pagination = QueryTreeTranslator::pagination(PaginationBuilder::make(['cursor' => 'tok', 'limit' => 5]));

        expect($pagination)->toBe(['cursor' => 'tok', 'limit' => 5]);
    });

    test('translates an empty group to an empty filter', function () {
        expect(QueryTreeTranslator::filter(FilterBuilder::make([])))->toBe([]);
    });
