<?php

declare(strict_types=1);

namespace App\Infrastructure\Container;

/**
 * High-performance PSR-4 Autoloader mapping App\ namespace to src/.
 */
final class Autoloader
{
    private static bool $registered = false;

    public static function register(?string $baseDir = null): void
    {
        if (self::$registered) {
            return;
        }

        $srcDir = $baseDir ?? dirname(__DIR__, 2);

        spl_autoload_register(function (string $class) use ($srcDir) {
            $prefix = 'App\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relativeClass = substr($class, strlen($prefix));
            $file = rtrim($srcDir, '/\\') . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });

        self::$registered = true;
    }
}
