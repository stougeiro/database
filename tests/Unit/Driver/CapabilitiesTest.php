<?php declare(strict_types=1);

    use STDW\Database\Driver\Capabilities;


    test('defaults every capability to false', function () {
        $capabilities = new Capabilities();

        expect($capabilities->supportsAggregation())->toBeFalse()
            ->and($capabilities->supportsBatch())->toBeFalse()
            ->and($capabilities->supportsSearch())->toBeFalse()
            ->and($capabilities->supportsStreaming())->toBeFalse()
            ->and($capabilities->supportsTransactions())->toBeFalse()
            ->and($capabilities->supportsUpsert())->toBeFalse();
    });

    test('honors the flags it was built with', function () {
        $capabilities = new Capabilities(
            aggregation: true,
            batch: true,
            search: false,
            streaming: true,
            transactions: true,
            upsert: true,
        );

        expect($capabilities->supportsAggregation())->toBeTrue()
            ->and($capabilities->supportsBatch())->toBeTrue()
            ->and($capabilities->supportsSearch())->toBeFalse()
            ->and($capabilities->supportsStreaming())->toBeTrue()
            ->and($capabilities->supportsTransactions())->toBeTrue()
            ->and($capabilities->supportsUpsert())->toBeTrue();
    });
