<?php

declare(strict_types=1);

namespace App\Http\Response;

/**
 * Dual-mode HTTP Response Factory supporting both JSON APIs and legacy HTML rendering.
 */
final class ResponseFactory
{
    public static function isJsonRequested(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (str_starts_with($uri, '/api/')) {
            return true;
        }

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        if (isset($_GET['format']) && $_GET['format'] === 'json') {
            return true;
        }

        $xRequestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (strtolower($xRequestedWith) === 'xmlhttprequest') {
            return true;
        }

        return false;
    }

    /**
     * Sends structured JSON response.
     *
     * @param array<string, mixed>|object $data
     * @param array<string, string> $headers
     */
    public static function json(
        mixed $data,
        int $statusCode = 200,
        array $headers = []
    ): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }

        $payload = is_array($data) ? $data : (method_exists($data, 'toArray') ? $data->toArray() : (array)$data);

        // Ensure standard envelope if not already wrapped
        if (!isset($payload['success'])) {
            $payload = [
                'success' => $statusCode >= 200 && $statusCode < 400,
                'data' => $payload,
            ];
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Sends structured error response in JSON or redirects in HTML.
     *
     * @param array<string, mixed> $details
     */
    public static function error(
        string $message,
        int $statusCode = 400,
        ?string $errorCode = null,
        array $details = [],
        ?string $redirectUrl = null
    ): void {
        if (self::isJsonRequested()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');

            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => $errorCode ?? 'ERROR_' . $statusCode,
                    'message' => $message,
                    'details' => $details,
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        if (function_exists('flash')) {
            flash('danger', $message);
        }

        if ($redirectUrl !== null && function_exists('redirect')) {
            redirect($redirectUrl);
        }

        http_response_code($statusCode);
        echo "<!DOCTYPE html><html dir='rtl' lang='fa'><head><meta charset='utf-8'><title>خطا</title></head><body><h2>خطا ({$statusCode})</h2><p>" . htmlspecialchars($message) . "</p></body></html>";
        exit;
    }

    /**
     * Sends dual-mode response: JSON when requested, otherwise invokes HTML callback.
     */
    public static function dual(
        mixed $jsonData,
        callable $htmlRenderer,
        int $statusCode = 200
    ): void {
        if (self::isJsonRequested()) {
            self::json($jsonData, $statusCode);
        }

        http_response_code($statusCode);
        $htmlRenderer();
        exit;
    }
}
