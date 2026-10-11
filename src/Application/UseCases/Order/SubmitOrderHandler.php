<?php

declare(strict_types=1);

namespace App\Application\UseCases\Order;

use App\Application\DTO\SubmitOrderRequest;
use App\Application\DTO\SubmitOrderResponse;
use App\Domain\Common\EventDispatcherInterface;
use App\Domain\Common\TransactionManagerInterface;
use App\Domain\Exceptions\EntityNotFoundException;
use App\Domain\Order\AddressSnapshot;
use App\Domain\Order\CustomerSnapshot;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\OrderStatus;
use App\Domain\Order\PriceSnapshot;
use App\Domain\Repositories\AddressRepositoryInterface;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\ShopRepositoryInterface;
use App\Domain\Repositories\UserRepositoryInterface;

/**
 * Use-Case: Submits a new customer or manager order, calculates pricing & shipping,
 * reserves product stock, checks purchase limits, and enforces free-order auto-pay.
 */
final readonly class SubmitOrderHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private ProductRepositoryInterface $productRepository,
        private ShopRepositoryInterface $shopRepository,
        private UserRepositoryInterface $userRepository,
        private AddressRepositoryInterface $addressRepository,
        private TransactionManagerInterface $transactionManager,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function handle(SubmitOrderRequest $request): SubmitOrderResponse
    {
        return $this->transactionManager->transactional(function () use ($request) {
            $shop = $this->shopRepository->findById($request->shopId);
            if ($shop === null) {
                throw EntityNotFoundException::forEntity('Shop', $request->shopId);
            }

            $customer = $this->userRepository->findById($request->customerId);
            if ($customer === null) {
                throw EntityNotFoundException::forEntity('Customer', $request->customerId);
            }

            $address = $this->addressRepository->findById($request->addressId);
            if ($address === null || $address->getUserId() !== $request->customerId) {
                throw EntityNotFoundException::forEntity('Address', $request->addressId);
            }

            $addressSnapshot = new AddressSnapshot(
                recipientName: $address->getRecipientName(),
                recipientPhone: $address->getRecipientPhone(),
                state: $address->getState(),
                city: $address->getCity(),
                address: $address->getAddress(),
                postalCode: $address->getPostalCode(),
                description: $address->getDescription()
            );
            $customerSnapshot = new CustomerSnapshot(
                userId: $customer->getId() ?? $request->customerId,
                username: $customer->getUsername(),
                nickname: $customer->getNickname(),
                phone: $customer->getPhone(),
                email: $customer->getEmail()
            );

            if (empty($request->items)) {
                throw new \InvalidArgumentException('سفارش باید دارای حداقل یک قلم کالا باشد.');
            }

            $orderItems = [];
            $subtotal = 0.0;
            $taxAmount = 0.0;

            foreach ($request->items as $itemData) {
                $productId = (int)($itemData['product_id'] ?? 0);
                $qty = (float)($itemData['quantity'] ?? 0);

                if ($productId <= 0 || $qty <= 0) {
                    continue;
                }

                $product = $this->productRepository->findById($productId);
                if ($product === null || $product->getShopId() !== $request->shopId || !$product->isActive()) {
                    throw EntityNotFoundException::forEntity('Product', $productId);
                }

                // Check stock and limits
                $past30DaysQty = $this->orderRepository->calculate30DayPurchasedQuantity($request->customerId, $productId);
                $product->assertCanPurchase($qty, $past30DaysQty);

                // Reserve stock on product
                $product->reserveStock($qty);
                $this->productRepository->updateStock(
                    $product->getId() ?? $productId,
                    $product->getStockQuantity()->value,
                    $product->getReservedQuantity()->value
                );

                $unitPrice = $product->getPrice();
                $lineTotal = $unitPrice * $qty;
                $lineTax = $lineTotal * $product->getTaxRate();

                $subtotal += $lineTotal;
                $taxAmount += $lineTax;

                $orderItems[] = new OrderItem(
                    orderId: null,
                    productId: $productId,
                    productTitle: $product->getTitle(),
                    unitPrice: $unitPrice,
                    quantity: $qty,
                    unitCostPrice: $product->getCostPrice(),
                    productSku: $product->getSku()?->value,
                    taxAmount: $lineTax,
                    unit: $product->getUnit()
                );
            }

            if (empty($orderItems)) {
                throw new \InvalidArgumentException('حداقل یک کالای معتبر باید در سفارش موجود باشد.');
            }

            // Calculate shipping cost
            $shippingCost = $shop->calculateShippingCost($subtotal);

            $priceSnapshot = new PriceSnapshot(
                subtotal: $subtotal,
                shippingCost: $shippingCost,
                taxAmount: $taxAmount,
                discountAmount: 0.0
            );

            $uuid = self::generateUuid();

            $order = Order::create(
                shopId: $request->shopId,
                customerId: $request->customerId,
                uuid: $uuid,
                price: $priceSnapshot,
                addressSnapshot: $addressSnapshot,
                customerSnapshot: $customerSnapshot,
                items: $orderItems,
                paymentMethod: $request->paymentMethod,
                reservationDays: $shop->getReservationDays(),
                branchId: $request->branchId,
                shippingGroupId: $request->shippingGroupId,
                createdByType: $request->createdByType,
                createdById: $request->createdById
            );

            $savedOrder = $this->orderRepository->save($order);

            // Dispatch recorded domain events
            $events = $savedOrder->releaseEvents();
            $this->eventDispatcher->dispatchAll($events);

            return new SubmitOrderResponse(
                orderId: $savedOrder->getId() ?? 0,
                uuid: $savedOrder->getUuid(),
                status: $savedOrder->getStatus()->value,
                grandTotal: $savedOrder->getPrice()->finalTotal,
                subtotal: $savedOrder->getPrice()->subtotal,
                shippingCost: $savedOrder->getPrice()->shippingCost,
                taxAmount: $savedOrder->getPrice()->taxAmount,
                isPaid: $savedOrder->getStatus() === OrderStatus::Paid,
                reservationExpiresAt: $savedOrder->getReservationExpiresAt(),
                itemsCount: count($savedOrder->getItems())
            );
        });
    }

    private static function generateUuid(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        return substr(str_shuffle($chars), 0, 8);
    }
}
