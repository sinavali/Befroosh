<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\DTO\CancelOrderRequest;
use App\Application\DTO\SubmitOrderRequest;
use App\Application\DTO\VerifyPaymentReceiptRequest;
use App\Application\UseCases\Order\CancelOrderHandler;
use App\Application\UseCases\Order\CompleteOrderHandler;
use App\Application\UseCases\Order\ShipOrderHandler;
use App\Application\UseCases\Order\SubmitOrderHandler;
use App\Application\UseCases\Payment\VerifyPaymentReceiptHandler;
use App\Domain\Common\DomainException;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Http\Response\ResponseFactory;
use App\Infrastructure\Container\Container;
use App\Infrastructure\Security\SessionAuthAdapter;

/**
 * Controller exposing RESTful Order operations.
 */
final class OrderApiController
{
    public function submit(): void
    {
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $auth = Container::getInstance()->get(SessionAuthAdapter::class);
        $user = $auth->currentUser();

        $customerId = $user?->getId() ?? (int)($input['customer_id'] ?? 0);
        if ($customerId <= 0) {
            ResponseFactory::error('شناسه مشتری الزامی است یا باید وارد سیستم شوید.', 401, 'UNAUTHORIZED');
        }

        $shopId = (int)($input['shop_id'] ?? 0);
        $addressId = (int)($input['address_id'] ?? 0);
        $items = $input['items'] ?? [];

        if ($shopId <= 0 || $addressId <= 0 || empty($items) || !is_array($items)) {
            ResponseFactory::error('اطلاعات فروشگاه، آدرس یا اقلام سفارش ناقص است.', 422, 'VALIDATION_FAILED');
        }

        $request = new SubmitOrderRequest(
            shopId: $shopId,
            customerId: $customerId,
            addressId: $addressId,
            items: $items,
            paymentMethod: (string)($input['payment_method'] ?? 'card_to_card'),
            paymentCardId: isset($input['payment_card_id']) ? (int)$input['payment_card_id'] : null,
            description: isset($input['description']) ? (string)$input['description'] : null,
            createdByType: $user?->getRole()->value ?? 'customer',
            createdById: $user?->getId(),
            branchId: isset($input['branch_id']) ? (int)$input['branch_id'] : null,
            shippingGroupId: isset($input['shipping_group_id']) ? (int)$input['shipping_group_id'] : null
        );

        try {
            $handler = Container::getInstance()->get(SubmitOrderHandler::class);
            $response = $handler->handle($request);

            ResponseFactory::json($response->toArray(), 201);
        } catch (DomainException $e) {
            ResponseFactory::error($e->getMessage(), $e->getHttpStatusCode(), $e->getErrorCode());
        } catch (\Throwable $e) {
            ResponseFactory::error($e->getMessage(), 400, 'ORDER_CREATION_FAILED');
        }
    }

    public function show(int $id): void
    {
        $orderRepo = Container::getInstance()->get(OrderRepositoryInterface::class);
        $order = $orderRepo->findById($id);

        if ($order === null) {
            ResponseFactory::error('سفارش مورد نظر یافت نشد.', 404, 'ORDER_NOT_FOUND');
        }

        $items = [];
        foreach ($order->getItems() as $item) {
            $items[] = [
                'product_id' => $item->getProductId(),
                'title' => $item->getProductTitle(),
                'sku' => $item->getProductSku(),
                'quantity' => $item->getQuantity(),
                'unit_price' => $item->getUnitPrice(),
                'line_total' => $item->getLineTotal(),
                'unit' => $item->getUnit(),
            ];
        }

        ResponseFactory::json([
            'id' => $order->getId(),
            'uuid' => $order->getUuid(),
            'shop_id' => $order->getShopId(),
            'status' => $order->getStatus()->value,
            'status_label' => $order->getStatus()->label(),
            'payment_status' => $order->getPaymentStatus()->value,
            'pricing' => [
                'subtotal' => $order->getPrice()->subtotal,
                'shipping_cost' => $order->getPrice()->shippingCost,
                'tax_amount' => $order->getPrice()->taxAmount,
                'discount_amount' => $order->getPrice()->discountAmount,
                'grand_total' => $order->getPrice()->finalTotal,
            ],
            'tracking' => [
                'code' => $order->getPostalTracking()->code,
                'shipping_method' => $order->getPostalTracking()->shippingMethod,
                'tracking_url' => $order->getPostalTracking()->getTrackingUrl(),
            ],
            'reservation_expires_at' => $order->getReservationExpiresAt()?->format('Y-m-d H:i:s'),
            'items' => $items,
        ]);
    }

    public function cancel(int $id): void
    {
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $auth = Container::getInstance()->get(SessionAuthAdapter::class);
        $user = $auth->currentUser();

        if ($user === null) {
            ResponseFactory::error('برای لغو سفارش باید وارد سیستم شوید.', 401, 'UNAUTHORIZED');
        }

        $reason = (string)($input['reason'] ?? 'لغو سفارش توسط کاربر');

        $request = new CancelOrderRequest(
            orderId: $id,
            userId: $user->getId() ?? 0,
            userRole: $user->getRole()->value,
            reason: $reason
        );

        try {
            $handler = Container::getInstance()->get(CancelOrderHandler::class);
            $response = $handler->handle($request);

            ResponseFactory::json($response->toArray());
        } catch (DomainException $e) {
            ResponseFactory::error($e->getMessage(), $e->getHttpStatusCode(), $e->getErrorCode());
        } catch (\Throwable $e) {
            ResponseFactory::error($e->getMessage(), 400, 'CANCELLATION_FAILED');
        }
    }

    public function verifyPayment(int $id): void
    {
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $auth = Container::getInstance()->get(SessionAuthAdapter::class);
        $user = $auth->currentUser();

        if ($user === null) {
            ResponseFactory::error('تنها مدیران فروشگاه امکان تایید یا رد فیش را دارند.', 401, 'UNAUTHORIZED');
        }

        $action = (string)($input['action'] ?? 'approve');
        $reason = isset($input['reason']) ? (string)$input['reason'] : null;

        $request = new VerifyPaymentReceiptRequest(
            orderId: $id,
            verifierUserId: $user->getId() ?? 0,
            action: $action,
            rejectionReason: $reason
        );

        try {
            $handler = Container::getInstance()->get(VerifyPaymentReceiptHandler::class);
            $response = $handler->handle($request);

            ResponseFactory::json($response->toArray());
        } catch (DomainException $e) {
            ResponseFactory::error($e->getMessage(), $e->getHttpStatusCode(), $e->getErrorCode());
        } catch (\Throwable $e) {
            ResponseFactory::error($e->getMessage(), 400, 'VERIFICATION_FAILED');
        }
    }

    public function ship(int $id): void
    {
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $auth = Container::getInstance()->get(SessionAuthAdapter::class);
        $user = $auth->currentUser();

        if ($user === null) {
            ResponseFactory::error('تنها کارکنان فروشگاه مجاز به ثبت ارسال کالا هستند.', 401, 'UNAUTHORIZED');
        }

        $trackingCode = (string)($input['tracking_code'] ?? '');
        $shippingMethod = (string)($input['shipping_method'] ?? 'پست پیشتاز');

        try {
            $handler = Container::getInstance()->get(ShipOrderHandler::class);
            $order = $handler->handle($id, $trackingCode, $shippingMethod, $user->getId());

            ResponseFactory::json([
                'order_id' => $order->getId(),
                'uuid' => $order->getUuid(),
                'status' => $order->getStatus()->value,
                'tracking_code' => $order->getPostalTracking()->code,
                'shipping_method' => $order->getPostalTracking()->shippingMethod,
                'shipped_at' => $order->getShippedAt()?->format('Y-m-d H:i:s'),
            ]);
        } catch (DomainException $e) {
            ResponseFactory::error($e->getMessage(), $e->getHttpStatusCode(), $e->getErrorCode());
        } catch (\Throwable $e) {
            ResponseFactory::error($e->getMessage(), 400, 'SHIPPING_FAILED');
        }
    }

    public function complete(int $id): void
    {
        try {
            $handler = Container::getInstance()->get(CompleteOrderHandler::class);
            $order = $handler->handle($id);

            ResponseFactory::json([
                'order_id' => $order->getId(),
                'uuid' => $order->getUuid(),
                'status' => $order->getStatus()->value,
                'completed_at' => $order->getCompletedAt()?->format('Y-m-d H:i:s'),
            ]);
        } catch (DomainException $e) {
            ResponseFactory::error($e->getMessage(), $e->getHttpStatusCode(), $e->getErrorCode());
        } catch (\Throwable $e) {
            ResponseFactory::error($e->getMessage(), 400, 'COMPLETION_FAILED');
        }
    }
}
