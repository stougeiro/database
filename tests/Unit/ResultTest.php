<?php declare(strict_types=1);

    use STDW\Database\Result\Result;


    test('make() builds a valid result', function () {
        $result = Result::make([['id' => 1]], 1);

        expect($result->isValid())->toBeTrue()
            ->and($result->count())->toBe(1)
            ->and($result->data())->toBe([['id' => 1]]);
    });

    test('invalid() builds an invalid result with zero count', function () {
        $result = Result::invalid();

        expect($result->isValid())->toBeFalse()
            ->and($result->count())->toBe(0)
            ->and($result->data())->toBeNull();
    });

    test('data() accepts objects and null', function () {
        $object = new stdClass();

        expect(Result::make($object, 1)->data())->toBe($object)
            ->and(Result::make(null, 0)->data())->toBeNull();
    });
