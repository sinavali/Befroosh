<?php

declare(strict_types=1);

use App\Http\Controllers\ApiController;
use App\Http\Controllers\CatalogApiController;
use App\Http\Controllers\OrderApiController;

/**
 * RESTful API route declarations providing clean JSON endpoints for the frontend rework.
 */

// 1. System Health & Info
route('GET', '/api/health', [], function () {
    (new ApiController())->health();
});

// 2. Authentication & Current User
route('GET', '/api/auth/me', [], function () {
    (new ApiController())->me();
});

route('POST', '/api/auth/login', [], function () {
    (new ApiController())->login();
});

route('POST', '/api/auth/logout', [], function () {
    (new ApiController())->logout();
});

// 3. Orders & Checkout API
route('POST', '/api/orders', [], function () {
    (new OrderApiController())->submit();
});

route('GET', '/api/orders/(\d+)', [], function (string $id) {
    (new OrderApiController())->show((int)$id);
});

route('POST', '/api/orders/(\d+)/cancel', [], function (string $id) {
    (new OrderApiController())->cancel((int)$id);
});

route('POST', '/api/orders/(\d+)/verify-payment', [], function (string $id) {
    (new OrderApiController())->verifyPayment((int)$id);
});

route('POST', '/api/orders/(\d+)/ship', [], function (string $id) {
    (new OrderApiController())->ship((int)$id);
});

route('POST', '/api/orders/(\d+)/complete', [], function (string $id) {
    (new OrderApiController())->complete((int)$id);
});

// 4. Catalog & Public Storefront API
route('GET', '/api/shops/([^/]+)', [], function (string $slug) {
    (new CatalogApiController())->shop($slug);
});

route('GET', '/(?:api/shops|api/v1/store)/([^/]+)/products', [], function (string $slug) {
    (new CatalogApiController())->products($slug);
});

route('GET', '/api/shops/([^/]+)/categories', [], function (string $slug) {
    (new CatalogApiController())->categories($slug);
});

route('GET', '/api/products/(\d+)', [], function (string $id) {
    (new CatalogApiController())->product((int)$id);
});

route('POST', '/api/products/(\d+)/check-purchase', [], function (string $id) {
    (new CatalogApiController())->checkPurchase((int)$id);
});
