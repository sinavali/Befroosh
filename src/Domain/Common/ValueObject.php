<?php

declare(strict_types=1);

namespace App\Domain\Common;

/**
 * Base interface for immutable Value Objects.
 */
interface ValueObject
{
    /**
     * Compare structural equality with another value object.
     */
    public function equals(ValueObject $other): bool;

    /**
     * Primitive or array representation for serialization.
     *
     * @return array<string, mixed>|string|float|int
     */
    public function toArray(): array|string|float|int;
}
