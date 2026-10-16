<?php declare(strict_types=1);

namespace Tests\Stubs;

use Stringable;

final class StringableStub implements Stringable
{
    public function __construct(private readonly string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
