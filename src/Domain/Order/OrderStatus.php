<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Exceptions\InvalidOrderStateTransitionException;

/**
 * Order state machine backed enum.
 */
enum OrderStatus: string
{
    case Submitted = 'submitted';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Canceled = 'canceled';
    case Placed = 'placed'; // legacy alias
    case Finalised = 'finalised'; // legacy alias

    public function label(): string
    {
        return match ($this) {
            self::Submitted, self::Placed => 'ثبت‌شده (در انتظار پرداخت)',
            self::Paid => 'پرداخت‌شده (در حال پردازش)',
            self::Shipped => 'ارسال‌شده (تحویل به پست)',
            self::Completed, self::Finalised => 'تکمیل‌شده (تحویل داده‌شده)',
            self::Canceled => 'لغوشده',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Canceled, self::Finalised], true);
    }

    public function canTransitionTo(OrderStatus $target): bool
    {
        if ($this->isTerminal()) {
            return false;
        }

        if ($this === $target) {
            return false;
        }

        return match ($this) {
            self::Submitted => in_array($target, [self::Paid, self::Canceled], true),
            self::Paid => in_array($target, [self::Shipped, self::Canceled], true),
            self::Shipped => in_array($target, [self::Completed], true),
            self::Placed => in_array($target, [self::Submitted, self::Paid, self::Canceled], true),
            default => false,
        };
    }

    public function assertCanTransitionTo(OrderStatus $target, int|string $orderId = 0, string $orderUuid = ''): void
    {
        if (!$this->canTransitionTo($target)) {
            throw InvalidOrderStateTransitionException::fromTransition(
                $orderId,
                $orderUuid,
                $this->value,
                $target->value
            );
        }
    }
}
