<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;
use RuntimeException;

/**
 * SQLite Connection Manager with WAL mode, busy timeout, and foreign key enforcement.
 */
final class SqliteConnection
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly ?string $dbPath = null
    ) {}

    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $path = $this->dbPath ?? (defined('DB_PATH') ? DB_PATH : dirname(__DIR__, 3) . '/storage/database.sqlite');

            if (!file_exists($path)) {
                $dir = dirname($path);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
            }

            $this->pdo = new PDO('sqlite:' . $path);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA journal_mode = WAL');
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
            $this->pdo->exec('PRAGMA synchronous = NORMAL');
        }

        return $this->pdo;
    }
}
