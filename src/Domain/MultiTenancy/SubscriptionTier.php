<?php

declare(strict_types=1);

namespace App\Domain\MultiTenancy;

enum SubscriptionTier: string
{
    case FREE = 'free';
    case PLUS = 'plus';
    case PRO = 'pro';
    case ULTIMATE = 'ultimate';
}
