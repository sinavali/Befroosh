<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Common\ValueObject;

/**
 * Immutable snapshot of customer identity and contact details frozen at order placement.
 */
final readonly class CustomerSnapshot implements ValueObject
{
    public function __construct(
        public int $userId,
        public string $username,
        public string $nickname,
        public ?string $phone = null,
        public ?string $email = null
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int)($data['user_id'] ?? $data['id'] ?? 0),
            username: (string)($data['username'] ?? ''),
            nickname: (string)($data['nickname'] ?? $data['name'] ?? ''),
            phone: isset($data['phone']) ? (string)$data['phone'] : null,
            email: isset($data['email']) ? (string)$data['email'] : null
        );
    }

    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return new self(0, '', '');
        }

        return self::fromArray($decoded);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'username' => $this->username,
            'nickname' => $this->nickname,
            'phone' => $this->phone,
            'email' => $this->email,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $this->userId === $other->userId
            && $this->username === $other->username
            && $this->nickname === $other->nickname
            && $this->phone === $other->phone
            && $this->email === $other->email;
    }
}
