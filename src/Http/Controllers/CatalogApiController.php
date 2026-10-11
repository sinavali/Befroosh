<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\UseCases\Inventory\CheckPurchaseLimitsHandler;
use App\Domain\Repositories\CategoryRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\ShopRepositoryInterface;
use App\Http\Response\ResponseFactory;
use App\Infrastructure\Container\Container;
use App\Infrastructure\Security\SessionAuthAdapter;

/**
 * Controller exposing public storefront and catalog endpoints.
 */
final class CatalogApiController
{
    public function shop(string $slug): void
    {
        $shopRepo = Container::getInstance()->get(ShopRepositoryInterface::class);
        $shop = $shopRepo->findBySlug($slug);

        if ($shop === null) {
            ResponseFactory::error('فروشگاه مورد نظر یافت نشد.', 404, 'SHOP_NOT_FOUND');
        }

        ResponseFactory::json([
            'id' => $shop->getId(),
            'name' => $shop->getName(),
            'slug' => $shop->getSlug(),
            'phone' => $shop->getPhone(),
            'email' => $shop->getEmail(),
            'address' => $shop->getAddress(),
            'shipping' => [
                'default_cost' => $shop->getDefaultShippingCost(),
                'free_threshold' => $shop->getFreeShippingThreshold(),
            ],
            'card_to_card_enabled' => $shop->isCardToCardEnabled(),
            'card_details' => $shop->isCardToCardEnabled() ? [
                'card_number' => $shop->getCardNumber(),
                'card_holder' => $shop->getCardHolder(),
                'bank_name' => $shop->getBankName(),
            ] : null,
        ]);
    }

    public function products(string $slug): void
    {
        $shopRepo = Container::getInstance()->get(ShopRepositoryInterface::class);
        $shop = $shopRepo->findBySlug($slug);

        if ($shop === null) {
            ResponseFactory::error('فروشگاه مورد نظر یافت نشد.', 404, 'SHOP_NOT_FOUND');
        }

        $branchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;
        $catalogMode = isset($_GET['catalog_mode']) ? (string)$_GET['catalog_mode'] : 'both';
        $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;
        $offset = isset($_GET['offset']) ? max((int)$_GET['offset'], 0) : 0;

        $q = trim((string)($_GET['q'] ?? ''));
        $productRepo = Container::getInstance()->get(ProductRepositoryInterface::class);
        $products = $productRepo->findForStorefront($shop->getId() ?? 0, $branchId, $catalogMode, $limit, $offset);

        $items = [];
        foreach ($products as $p) {
            if ($q !== '') {
                $matchTitle = mb_stripos($p->getTitle(), $q) !== false;
                $matchSku = $p->getSku() !== null && mb_stripos($p->getSku()->value, $q) !== false;
                $matchBarcode = $p->getBarcode() !== null && mb_stripos($p->getBarcode(), $q) !== false;
                if (!$matchTitle && !$matchSku && !$matchBarcode) {
                    continue;
                }
            }

            $items[] = [
                'id' => $p->getId(),
                'title' => $p->getTitle(),
                'slug' => $p->getSlug(),
                'price' => $p->getPrice(),
                'available_stock' => $p->getAvailableStock(),
                'in_stock' => $p->isInStock(),
                'sku' => $p->getSku()?->value,
                'barcode' => $p->getBarcode(),
                'image_path' => $p->getImagePath(),
                'unit' => $p->getUnit(),
                'branch_id' => $p->getBranchId(),
                'max_per_order' => $p->getMaxPerOrder(),
            ];
        }

        ResponseFactory::json([
            'shop_id' => $shop->getId(),
            'shop_slug' => $shop->getSlug(),
            'total_returned' => count($items),
            'products' => $items,
            'data' => $items,
        ]);
    }

    public function categories(string $slug): void
    {
        $shopRepo = Container::getInstance()->get(ShopRepositoryInterface::class);
        $shop = $shopRepo->findBySlug($slug);

        if ($shop === null) {
            ResponseFactory::error('فروشگاه مورد نظر یافت نشد.', 404, 'SHOP_NOT_FOUND');
        }

        $catRepo = Container::getInstance()->get(CategoryRepositoryInterface::class);
        $categories = $catRepo->findByShopId($shop->getId() ?? 0, true);

        $items = [];
        foreach ($categories as $c) {
            $items[] = [
                'id' => $c->getId(),
                'name' => $c->getName(),
                'slug' => $c->getSlug(),
                'parent_id' => $c->getParentId(),
                'sort_order' => $c->getSortOrder(),
            ];
        }

        ResponseFactory::json([
            'shop_id' => $shop->getId(),
            'categories' => $items,
        ]);
    }

    public function product(int $id): void
    {
        $productRepo = Container::getInstance()->get(ProductRepositoryInterface::class);
        $product = $productRepo->findById($id);

        if ($product === null) {
            ResponseFactory::error('کالای مورد نظر یافت نشد.', 404, 'PRODUCT_NOT_FOUND');
        }

        ResponseFactory::json([
            'id' => $product->getId(),
            'shop_id' => $product->getShopId(),
            'title' => $product->getTitle(),
            'slug' => $product->getSlug(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'available_stock' => $product->getAvailableStock(),
            'in_stock' => $product->isInStock(),
            'sku' => $product->getSku()?->value,
            'barcode' => $product->getBarcode(),
            'unit' => $product->getUnit(),
            'weight_grams' => $product->getWeightGrams(),
            'branch_id' => $product->getBranchId(),
            'max_per_order' => $product->getMaxPerOrder(),
            'max_per_month' => $product->getMaxPerMonth(),
            'min_order_qty' => $product->getMinOrderQty(),
            'image_path' => $product->getImagePath(),
        ]);
    }

    public function checkPurchase(int $id): void
    {
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $qty = (float)($input['quantity'] ?? 1.0);
        $auth = Container::getInstance()->get(SessionAuthAdapter::class);
        $user = $auth->currentUser();
        $customerId = $user?->getId() ?? (int)($input['customer_id'] ?? 0);

        $handler = Container::getInstance()->get(CheckPurchaseLimitsHandler::class);
        $result = $handler->handle($customerId, $id, $qty);

        ResponseFactory::json($result, $result['ok'] ? 200 : 422);
    }
}
