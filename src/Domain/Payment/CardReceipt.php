<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Domain\Common\Entity;
use App\Domain\Exceptions\PaymentAlreadyVerifiedException;
use DateTimeImmutable;

/**
 * CardReceipt entity representing an uploaded proof of card-to-card bank transfer.
 */
final class CardReceipt extends Entity
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public function __construct(
        private int $orderId,
        private ?string $filePath = null,
        private ?string $referenceNumber = null,
        private ?string $description = null,
        private string $status = self::STATUS_PENDING,
        private ?string $rejectionReason = null,
        private ?int $verifiedById = null,
        private ?DateTimeImmutable $verifiedAt = null,
        private ?DateTimeImmutable $uploadedAt = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->uploadedAt = $uploadedAt ?? new DateTimeImmutable();
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function getReferenceNumber(): ?string
    {
        return $this->referenceNumber;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function getVerifiedById(): ?int
    {
        return $this->verifiedById;
    }

    public function getVerifiedAt(): ?DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function getUploadedAt(): DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function approve(int $verifiedById, ?DateTimeImmutable $at = null): void
    {
        if (!$this->isPending()) {
            throw new PaymentAlreadyVerifiedException('رسید پرداخت قبلاً تعیین تکلیف شده است.');
        }

        $this->status = self::STATUS_APPROVED;
        $this->verifiedById = $verifiedById;
        $this->verifiedAt = $at ?? new DateTimeImmutable();
        $this->rejectionReason = null;
    }

    public function reject(string $reason, int $verifiedById, ?DateTimeImmutable $at = null): void
    {
        if (!$this->isPending()) {
            throw new PaymentAlreadyVerifiedException('رسید پرداخت قبلاً تعیین تکلیف شده است.');
        }

        $this->status = self::STATUS_REJECTED;
        $this->verifiedById = $verifiedById;
        $this->verifiedAt = $at ?? new DateTimeImmutable();
        $this->rejectionReason = $reason;
    }
}
