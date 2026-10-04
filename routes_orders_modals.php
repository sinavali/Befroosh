<?php
declare(strict_types=1);

/**
 * routes_orders_modals.php
 * Action modals for order management: Reject Payment, Ship with Tracking, Cancel Order
 */

function render_order_modals(int $orderId): void {
    ?>
    <!-- REJECT PAYMENT MODAL -->
    <div class="modal-backdrop" id="rejectPaymentModal">
        <div class="modal" style="max-width:440px;">
            <form method="post" action="/orders/<?= $orderId ?>/payment/verify">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject">
                <div class="modal-header">
                    <h3>رد فیش پرداخت</h3>
                    <button type="button" class="btn btn-ghost btn-sm" data-modal-close>✕</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>دلیل رد فیش * (به مشتری نمایش داده می‌شود)</label>
                        <textarea class="textarea" name="reason" required placeholder="مثال: فیش ناخوانا است یا مبلغ واریزی مغایرت دارد..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-danger" type="submit">ثبت رد فیش</button>
                    <button type="button" class="btn btn-outline" data-modal-close>انصراف</button>
                </div>
            </form>
        </div>
    </div>

    <!-- SHIP ORDER MODAL -->
    <div class="modal-backdrop" id="shipModal">
        <div class="modal" style="max-width:440px;">
            <form method="post" action="/orders/<?= $orderId ?>/ship">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h3>ارسال مرسوله و درج کد رهگیری پست</h3>
                    <button type="button" class="btn btn-ghost btn-sm" data-modal-close>✕</button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-2">
                        <label>روش ارسال</label>
                        <input class="input" name="shipping_method" value="پست پیشتاز" required>
                    </div>
                    <div class="form-group">
                        <label>کد رهگیری پستی (بارکد ۲۴ رقمی پست) *</label>
                        <input class="input" name="tracking_code" dir="ltr" required placeholder="مثال: 123456789012345678901234">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" type="submit">ثبت و تغییر وضعیت به ارسال شده</button>
                    <button type="button" class="btn btn-outline" data-modal-close>انصراف</button>
                </div>
            </form>
        </div>
    </div>

    <!-- CANCEL ORDER MODAL -->
    <div class="modal-backdrop" id="cancelModal">
        <div class="modal" style="max-width:440px;">
            <form method="post" action="/orders/<?= $orderId ?>/cancel">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h3>لغو سفارش</h3>
                    <button type="button" class="btn btn-ghost btn-sm" data-modal-close>✕</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>دلیل لغو سفارش * (موجودی انبار به طور خودکار بازگردانده خواهد شد)</label>
                        <textarea class="textarea" name="reason" required placeholder="علت لغو سفارش را وارد نمایید..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-danger" type="submit">تایید لغو سفارش</button>
                    <button type="button" class="btn btn-outline" data-modal-close>انصراف</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}
