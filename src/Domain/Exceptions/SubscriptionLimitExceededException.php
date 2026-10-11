<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Common\DomainException;
use Throwable;

class SubscriptionLimitExceededException extends DomainException
{
    public function __construct(
        string $message = 'سقف مجاز طرح اشتراک تکمیل شده است.',
        string $errorCode = 'SUBSCRIPTION_LIMIT_EXCEEDED',
        array $context = [],
        int $httpStatusCode = 403,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $context, $httpStatusCode, $previous);
    }

    public static function forBranches(int $shopId, int $maxBranches, int $currentBranches): self
    {
        return new self(
            message: sprintf('سقف مجاز تعداد شعب در طرح اشتراک شما (%d شعبه) تکمیل شده است. جهت افزودن شعبه ارتقای طرح الزامی است.', $maxBranches),
            errorCode: 'BRANCH_LIMIT_EXCEEDED',
            context: [
                'shop_id' => $shopId,
                'quota_type' => 'branches',
                'max_allowed' => $maxBranches,
                'current_count' => $currentBranches,
            ],
            httpStatusCode: 403
        );
    }

    public static function forProducts(int $shopId, int $maxProducts, int $currentProducts): self
    {
        return new self(
            message: sprintf('سقف مجاز تعریف کالا در طرح اشتراک شما (%d کالا) تکمیل شده است. لطفا طرح اشتراک خود را ارتقا دهید.', $maxProducts),
            errorCode: 'PRODUCT_LIMIT_EXCEEDED',
            context: [
                'shop_id' => $shopId,
                'quota_type' => 'products',
                'max_allowed' => $maxProducts,
                'current_count' => $currentProducts,
            ],
            httpStatusCode: 403
        );
    }

    public static function forManagers(int $shopId, int $maxManagers, int $currentManagers): self
    {
        return new self(
            message: sprintf('سقف تعداد مدیران فعال در طرح اشتراک شما (%d مدیر) تکمیل شده است. جهت ارتقا یا انتصاب مدیر جدید طرح خود را تغییر دهید.', $maxManagers),
            errorCode: 'MANAGER_LIMIT_EXCEEDED',
            context: [
                'shop_id' => $shopId,
                'quota_type' => 'managers',
                'max_allowed' => $maxManagers,
                'current_count' => $currentManagers,
            ],
            httpStatusCode: 403
        );
    }

    public static function forTicketEscalation(int $shopId, string $currentPlanSlug): self
    {
        return new self(
            message: 'ارجاع مستقیم و اولویت‌دار تیکت به مدیر ارشد سیستم منحصراً در طرح نامحدود سازمانی (Ultimate) فعال است.',
            errorCode: 'TICKET_ESCALATION_NOT_PERMITTED',
            context: [
                'shop_id' => $shopId,
                'current_plan' => $currentPlanSlug,
                'required_plan' => 'ultimate',
            ],
            httpStatusCode: 403
        );
    }
}
