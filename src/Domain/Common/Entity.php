<?php

declare(strict_types=1);

namespace App\Domain\Common;

/**
 * Base abstract class for all Domain Entities.
 */
abstract class Entity
{
    use RecordsEventsTrait;

    protected ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        if ($this->id !== null) {
            throw new \LogicException(sprintf('Entity %s already has an ID assigned (%d).', static::class, $this->id));
        }
        $this->id = $id;
    }

    public function equals(?Entity $other): bool
    {
        if ($other === null) {
            return false;
        }

        if (static::class !== get_class($other)) {
            return false;
        }

        if ($this->id === null || $other->getId() === null) {
            return false;
        }

        return $this->id === $other->getId();
    }
}
