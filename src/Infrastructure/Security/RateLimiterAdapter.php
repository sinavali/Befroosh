<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use PDO;

/**
 * SQLite-backed Rate Limiter Adapter with sliding decay windows.
 */
final class RateLimiterAdapter
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $now = time();
        $stmt = $this->pdo->prepare('SELECT hits, reset_at FROM rate_limits WHERE key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && (int)$row['reset_at'] > $now) {
            return (int)$row['hits'] >= $maxAttempts;
        }

        return false;
    }

    public function hit(string $key, int $decaySeconds = 60): int
    {
        $now = time();
        $resetAt = $now + $decaySeconds;

        $stmt = $this->pdo->prepare("
            INSERT INTO rate_limits (key, hits, reset_at)
            VALUES (?, 1, ?)
            ON CONFLICT(key) DO UPDATE SET
                hits = CASE WHEN reset_at > ? THEN hits + 1 ELSE 1 END,
                reset_at = CASE WHEN reset_at > ? THEN reset_at ELSE ? END
        ");
        $stmt->execute([$key, $resetAt, $now, $now, $resetAt]);

        $checkStmt = $this->pdo->prepare('SELECT hits FROM rate_limits WHERE key = ?');
        $checkStmt->execute([$key]);
        return (int)$checkStmt->fetchColumn();
    }

    public function clear(string $key): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rate_limits WHERE key = ?');
        $stmt->execute([$key]);
    }
}
