<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Order\AddressSnapshot;
use App\Domain\Order\CustomerSnapshot;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\OrderStatus;
use App\Domain\Order\PaymentStatus;
use App\Domain\Order\PostalTracking;
use App\Domain\Order\PriceSnapshot;
use App\Domain\Repositories\OrderRepositoryInterface;
use DateTimeImmutable;
use PDO;

/**
 * SQLite implementation of OrderRepositoryInterface.
 */
final class SqliteOrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function findById(int $id): ?Order
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByUuid(string $uuid): ?Order
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE uuid = ? LIMIT 1');
        $stmt->execute([$uuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByTrackingCode(string $trackingCode): ?Order
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE tracking_code = ? LIMIT 1');
        $stmt->execute([$trackingCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return array<Order>
     */
    public function findByCustomerId(int $customerId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $customerId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * @return array<Order>
     */
    public function findByShopId(
        int $shopId,
        ?string $status = null,
        ?string $paymentStatus = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        $sql = 'SELECT * FROM orders WHERE shop_id = ?';
        $params = [$shopId];

        if ($status !== null) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }

        if ($paymentStatus !== null) {
            $sql .= ' AND payment_status = ?';
            $params[] = $paymentStatus;
        }

        $sql .= ' ORDER BY id DESC LIMIT ? OFFSET ?';

        $stmt = $this->pdo->prepare($sql);
        $idx = 1;
        foreach ($params as $p) {
            $stmt->bindValue($idx++, $p);
        }
        $stmt->bindValue($idx++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($idx++, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * @return array<Order>
     */
    public function findExpiredSubmittedOrders(DateTimeImmutable $thresholdDateTime): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM orders 
            WHERE status = 'submitted' 
              AND reservation_expires_at IS NOT NULL 
              AND reservation_expires_at <= ?
            ORDER BY id ASC
        ");
        $stmt->execute([$thresholdDateTime->format('Y-m-d H:i:s')]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function calculate30DayPurchasedQuantity(int $customerId, int $productId): float
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(oi.quantity), 0)
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            WHERE o.customer_id = ?
              AND oi.product_id = ?
              AND o.status != 'canceled'
              AND o.created_at >= datetime('now', '-30 days')
        ");
        $stmt->execute([$customerId, $productId]);

        return (float)$stmt->fetchColumn();
    }

    public function countByShopAndStatus(int $shopId, string $status): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM orders WHERE shop_id = ? AND status = ?');
        $stmt->execute([$shopId, $status]);

        return (int)$stmt->fetchColumn();
    }

    public function existsByUuid(string $uuid): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM orders WHERE uuid = ?');
        $stmt->execute([$uuid]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function save(Order $order): Order
    {
        $now = date('Y-m-d H:i:s');
        $paidAt = $order->getPaidAt()?->format('Y-m-d H:i:s');
        $reservationExpiresAt = $order->getReservationExpiresAt()?->format('Y-m-d H:i:s');
        $shippedAt = $order->getShippedAt()?->format('Y-m-d H:i:s');
        $deliveredAt = $order->getDeliveredAt()?->format('Y-m-d H:i:s');
        $completedAt = $order->getCompletedAt()?->format('Y-m-d H:i:s');
        $canceledAt = $order->getCanceledAt()?->format('Y-m-d H:i:s');
        $finalisedAt = $order->getFinalisedAt()?->format('Y-m-d H:i:s');
        $verifiedAt = $order->getReceiptVerifiedAt()?->format('Y-m-d H:i:s');

        $price = $order->getPrice();
        $tracking = $order->getPostalTracking();

        if ($order->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO orders (
                    uuid, shop_id, customer_id, branch_id, shipping_group_id,
                    created_by_type, created_by_id, status, subtotal, shipping_cost,
                    tax_amount, discount_amount, estimated_total, final_total,
                    payment_method, payment_status, payment_card_id, payment_receipt_path,
                    payment_reference, payment_reject_reason, paid_at, reservation_expires_at,
                    shipping_method, tracking_code, shipped_at, delivered_at,
                    completed_at, canceled_at, cancellation_reason, finalised_at,
                    address_id, address_snapshot, customer_snapshot, description,
                    receipt_status, receipt_verified_by, receipt_verified_at, receipt_rejection_reason,
                    seen_by_customer, seen_by_admin, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $order->getUuid(),
                $order->getShopId(),
                $order->getCustomerId(),
                $order->getBranchId(),
                $order->getShippingGroupId(),
                $order->getCreatedByType(),
                $order->getCreatedById(),
                $order->getStatus()->value,
                $price->subtotal,
                $price->shippingCost,
                $price->taxAmount,
                $price->discountAmount,
                $price->finalTotal,
                $price->finalTotal,
                $order->getPaymentMethod(),
                $order->getPaymentStatus()->value,
                $order->getPaymentCardId(),
                $order->getPaymentReceiptPath(),
                $order->getPaymentReference(),
                $order->getPaymentRejectReason(),
                $paidAt,
                $reservationExpiresAt,
                $tracking->shippingMethod,
                $tracking->code,
                $shippedAt,
                $deliveredAt,
                $completedAt,
                $canceledAt,
                $order->getCancellationReason(),
                $finalisedAt,
                $order->getAddressId(),
                json_encode($order->getAddressSnapshot()->toArray(), JSON_UNESCAPED_UNICODE),
                json_encode($order->getCustomerSnapshot()->toArray(), JSON_UNESCAPED_UNICODE),
                $order->getDescription(),
                $order->getReceiptStatus(),
                $order->getReceiptVerifiedBy(),
                $verifiedAt,
                $order->getReceiptRejectionReason(),
                $order->isSeenByCustomer() ? 1 : 0,
                $order->isSeenByAdmin() ? 1 : 0,
                $now,
                $now
            ]);

            $orderId = (int)$this->pdo->lastInsertId();
            $order->setId($orderId);

            // Insert line items
            $insItem = $this->pdo->prepare("
                INSERT INTO order_items (
                    order_id, product_id, product_title, product_sku, unit_price,
                    unit_cost_price, quantity, tax_amount, line_total, unit, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?
                )
            ");

            foreach ($order->getItems() as $item) {
                $item->setOrderId($orderId);
                $insItem->execute([
                    $orderId,
                    $item->getProductId(),
                    $item->getProductTitle(),
                    $item->getProductSku(),
                    $item->getUnitPrice(),
                    $item->getUnitCostPrice(),
                    $item->getQuantity(),
                    $item->getTaxAmount(),
                    $item->getLineTotal(),
                    $item->getUnit(),
                    $now,
                    $now
                ]);
            }
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE orders SET
                    status = ?, subtotal = ?, shipping_cost = ?, tax_amount = ?, discount_amount = ?,
                    estimated_total = ?, final_total = ?, payment_method = ?, payment_status = ?,
                    payment_card_id = ?, payment_receipt_path = ?, payment_reference = ?,
                    payment_reject_reason = ?, paid_at = ?, reservation_expires_at = ?,
                    shipping_method = ?, tracking_code = ?, shipped_at = ?, delivered_at = ?,
                    completed_at = ?, canceled_at = ?, cancellation_reason = ?, finalised_at = ?,
                    receipt_status = ?, receipt_verified_by = ?, receipt_verified_at = ?,
                    receipt_rejection_reason = ?, seen_by_customer = ?, seen_by_admin = ?, updated_at = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $order->getStatus()->value,
                $price->subtotal,
                $price->shippingCost,
                $price->taxAmount,
                $price->discountAmount,
                $price->finalTotal,
                $price->finalTotal,
                $order->getPaymentMethod(),
                $order->getPaymentStatus()->value,
                $order->getPaymentCardId(),
                $order->getPaymentReceiptPath(),
                $order->getPaymentReference(),
                $order->getPaymentRejectReason(),
                $paidAt,
                $reservationExpiresAt,
                $tracking->shippingMethod,
                $tracking->code,
                $shippedAt,
                $deliveredAt,
                $completedAt,
                $canceledAt,
                $order->getCancellationReason(),
                $finalisedAt,
                $order->getReceiptStatus(),
                $order->getReceiptVerifiedBy(),
                $verifiedAt,
                $order->getReceiptRejectionReason(),
                $order->isSeenByCustomer() ? 1 : 0,
                $order->isSeenByAdmin() ? 1 : 0,
                $now,
                $order->getId()
            ]);
        }

        return $order;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Order
    {
        $orderId = (int)$row['id'];

        // Load items
        $iStmt = $this->pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
        $iStmt->execute([$orderId]);
        $iRows = $iStmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        foreach ($iRows as $ir) {
            $items[] = new OrderItem(
                orderId: $orderId,
                productId: isset($ir['product_id']) ? (int)$ir['product_id'] : null,
                productTitle: (string)$ir['product_title'],
                unitPrice: (int)$ir['unit_price'],
                quantity: (float)$ir['quantity'],
                unitCostPrice: (float)($ir['unit_cost_price'] ?? 0.0),
                productSku: isset($ir['product_sku']) ? (string)$ir['product_sku'] : null,
                taxAmount: (float)($ir['tax_amount'] ?? 0.0),
                lineTotal: (float)$ir['line_total'],
                unit: (string)($ir['unit'] ?? 'عدد'),
                createdAt: !empty($ir['created_at']) ? new DateTimeImmutable((string)$ir['created_at']) : null,
                updatedAt: !empty($ir['updated_at']) ? new DateTimeImmutable((string)$ir['updated_at']) : null,
                id: (int)$ir['id']
            );
        }

        $addrArr = !empty($row['address_snapshot']) ? json_decode((string)$row['address_snapshot'], true) : [];
        $custArr = !empty($row['customer_snapshot']) ? json_decode((string)$row['customer_snapshot'], true) : [];

        $addressSnapshot = is_array($addrArr) && !empty($addrArr)
            ? AddressSnapshot::fromArray($addrArr)
            : AddressSnapshot::empty();

        $customerSnapshot = is_array($custArr) && !empty($custArr)
            ? CustomerSnapshot::fromArray($custArr)
            : CustomerSnapshot::empty();

        $price = new PriceSnapshot(
            subtotal: (float)($row['subtotal'] ?? 0.0),
            shippingCost: (float)($row['shipping_cost'] ?? 0.0),
            taxAmount: (float)($row['tax_amount'] ?? 0.0),
            discountAmount: (float)($row['discount_amount'] ?? 0.0)
        );

        $postal = PostalTracking::from(
            isset($row['tracking_code']) ? (string)$row['tracking_code'] : null,
            (string)($row['shipping_method'] ?? 'پست پیشتاز')
        );

        $status = OrderStatus::from((string)$row['status']);
        $pmtStatus = PaymentStatus::from((string)($row['payment_status'] ?? 'unpaid'));

        return new Order(
            uuid: (string)$row['uuid'],
            shopId: (int)$row['shop_id'],
            customerId: (int)$row['customer_id'],
            status: $status,
            price: $price,
            addressSnapshot: $addressSnapshot,
            customerSnapshot: $customerSnapshot,
            paymentMethod: (string)($row['payment_method'] ?? 'card_to_card'),
            paymentStatus: $pmtStatus,
            postalTracking: $postal,
            branchId: isset($row['branch_id']) ? (int)$row['branch_id'] : null,
            shippingGroupId: isset($row['shipping_group_id']) ? (int)$row['shipping_group_id'] : null,
            createdByType: (string)($row['created_by_type'] ?? 'customer'),
            createdById: isset($row['created_by_id']) ? (int)$row['created_by_id'] : null,
            paymentCardId: isset($row['payment_card_id']) ? (int)$row['payment_card_id'] : null,
            paymentReceiptPath: isset($row['payment_receipt_path']) ? (string)$row['payment_receipt_path'] : null,
            paymentReference: isset($row['payment_reference']) ? (string)$row['payment_reference'] : null,
            paymentRejectReason: isset($row['payment_reject_reason']) ? (string)$row['payment_reject_reason'] : null,
            paidAt: !empty($row['paid_at']) ? new DateTimeImmutable((string)$row['paid_at']) : null,
            reservationExpiresAt: !empty($row['reservation_expires_at']) ? new DateTimeImmutable((string)$row['reservation_expires_at']) : null,
            shippedAt: !empty($row['shipped_at']) ? new DateTimeImmutable((string)$row['shipped_at']) : null,
            deliveredAt: !empty($row['delivered_at']) ? new DateTimeImmutable((string)$row['delivered_at']) : null,
            completedAt: !empty($row['completed_at']) ? new DateTimeImmutable((string)$row['completed_at']) : null,
            canceledAt: !empty($row['canceled_at']) ? new DateTimeImmutable((string)$row['canceled_at']) : null,
            cancellationReason: isset($row['cancellation_reason']) ? (string)$row['cancellation_reason'] : null,
            finalisedAt: !empty($row['finalised_at']) ? new DateTimeImmutable((string)$row['finalised_at']) : null,
            addressId: isset($row['address_id']) ? (int)$row['address_id'] : null,
            description: isset($row['description']) ? (string)$row['description'] : null,
            receiptStatus: (string)($row['receipt_status'] ?? 'pending'),
            receiptVerifiedBy: isset($row['receipt_verified_by']) ? (int)$row['receipt_verified_by'] : null,
            receiptVerifiedAt: !empty($row['receipt_verified_at']) ? new DateTimeImmutable((string)$row['receipt_verified_at']) : null,
            receiptRejectionReason: isset($row['receipt_rejection_reason']) ? (string)$row['receipt_rejection_reason'] : null,
            seenByCustomer: (bool)($row['seen_by_customer'] ?? 1),
            seenByAdmin: (bool)($row['seen_by_admin'] ?? 0),
            items: $items,
            createdAt: !empty($row['created_at']) ? new DateTimeImmutable((string)$row['created_at']) : null,
            updatedAt: !empty($row['updated_at']) ? new DateTimeImmutable((string)$row['updated_at']) : null,
            id: $orderId
        );
    }
}
