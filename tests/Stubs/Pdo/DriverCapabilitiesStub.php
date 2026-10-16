<?php declare(strict_types=1);

namespace Tests\Stubs\Pdo;

use STDW\Contract\Database\Driver\DriverCapabilitiesInterface;

final class DriverCapabilitiesStub implements DriverCapabilitiesInterface
{
    public function supportsAggregation(): bool
    {
        return false;
    }

    public function supportsBatch(): bool
    {
        return false;
    }

    public function supportsSearch(): bool
    {
        return false;
    }

    public function supportsStreaming(): bool
    {
        return false;
    }

    public function supportsTransactions(): bool
    {
        return true;
    }

    public function supportsUpsert(): bool
    {
        return false;
    }
}
