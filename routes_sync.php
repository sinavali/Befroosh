<?php
declare(strict_types=1);

/**
 * routes_sync.php
 * API endpoint to synchronize guest localStorage interactions (cart & bookmarks) upon login
 */

route('POST', '/api/sync-guest-data', [], function () use ($pdo) {
    header('Content-Type: application/json; charset=utf-8');
    $user = current_user();
    if (!$user) {
        echo json_encode(['ok' => false, 'message' => 'Unauthenticated']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];

    $cart = $data['cart'] ?? [];
    $favs = $data['favorites'] ?? [];

    if (is_array($cart) || is_array($favs)) {
        sync_guest_storage_to_db((int)$user['id'], (array)$cart, (array)$favs);
    }

    echo json_encode(['ok' => true]);
    exit;
});
