<?php
declare(strict_types=1);

/**
 * test_e2e_archetypes.php
 * Comprehensive verification of all 7 Frontend Archetypes specified in DESIGN.md
 * Dispatches simulated requests through index.php using isolated processes.
 */

function runRequest(string $method, string $uri, array $session = [], array $post = []): array {
    $parts = parse_url($uri);
    $path = $parts['path'] ?? '/';
    $query = $parts['query'] ?? '';

    $script = "<?php\n";
    $script .= "ini_set('display_errors', '1');\n";
    $script .= "error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);\n";
    $script .= "\$_SERVER['REQUEST_METHOD'] = " . var_export($method, true) . ";\n";
    $script .= "\$_SERVER['REQUEST_URI'] = " . var_export($uri, true) . ";\n";
    $script .= "\$_SERVER['SCRIPT_NAME'] = '/index.php';\n";
    $script .= "\$_SERVER['HTTP_HOST'] = 'befroosh.test';\n";
    $script .= "\$_SERVER['HTTPS'] = 'on';\n";
    if ($query !== '') {
        $script .= "\$_SERVER['QUERY_STRING'] = " . var_export($query, true) . ";\n";
        $script .= "parse_str(" . var_export($query, true) . ", \$_GET);\n";
    } else {
        $script .= "\$_GET = [];\n";
    }
    $script .= "\$_POST = " . var_export($post, true) . ";\n";
    $script .= "require " . var_export(__DIR__ . '/bootstrap.php', true) . ";\n";
    $script .= "foreach (" . var_export($session, true) . " as \$k => \$v) { \$_SESSION[\$k] = \$v; }\n";
    if (isset($session['user_id'])) {
        $script .= "\$_SESSION['last_activity'] = time();\n";
    }
    $script .= "require " . var_export(__DIR__ . '/index.php', true) . ";\n";

    $tmp = tempnam(sys_get_temp_dir(), 'req_');
    file_put_contents($tmp, $script);

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];
    $process = proc_open(PHP_BINARY . ' ' . escapeshellarg($tmp), $descriptors, $pipes, __DIR__);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    @unlink($tmp);

    return ['exit_code' => $exitCode, 'stdout' => $stdout, 'stderr' => $stderr];
}

$passed = 0;
$failed = 0;

function assertCondition(string $desc, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  ✓ [PASS] $desc\n";
        $passed++;
    } else {
        echo "  ✗ [FAIL] $desc\n";
        if ($details !== '') {
            echo "     --> " . substr(trim($details), 0, 300) . "\n";
        }
        $failed++;
    }
}

echo "======================================================================\n";
echo "E2E VERIFICATION BATTERY: 7 FRONTEND ARCHETYPES (DESIGN.md)\n";
echo "======================================================================\n\n";

// Real database user sessions
$adminSession = [
    'user_id' => 1,
    'last_activity' => time(),
    'active_business_id' => 1
];
$merchantSession = [
    'user_id' => 3,
    'last_activity' => time(),
    'active_business_id' => 1
];
$customerSession = [
    'user_id' => 9,
    'last_activity' => time()
];

// -------------------------------------------------------------
// Archetype 1: Customer Storefront & Catalog
// -------------------------------------------------------------
echo "=== Archetype 1: Customer Storefront & Catalog ===\n";
$res1 = runRequest('GET', '/b/central');
assertCondition("Storefront catalog renders successfully", $res1['exit_code'] === 0 && !empty($res1['stdout']));
assertCondition("Storefront contains branch selector / switcher", str_contains($res1['stdout'], 'شعب فعال') || str_contains($res1['stdout'], 'شعبه'));
assertCondition("Storefront contains live search autocomplete input", str_contains($res1['stdout'], 'bizLiveSearchInput') || str_contains($res1['stdout'], 'bizAutocompleteDropdown'));
assertCondition("Storefront contains category filter pills", str_contains($res1['stdout'], 'همه کالاها') && str_contains($res1['stdout'], 'badge'));
assertCondition("Storefront contains quick bag drawer", str_contains($res1['stdout'], 'mini-cart-drawer') && str_contains($res1['stdout'], 'miniCartDrawer'));

$res1branch = runRequest('GET', '/b/central/azadi');
assertCondition("Branch storefront (/b/central/azadi) renders successfully", $res1branch['exit_code'] === 0);
assertCondition("Branch storefront includes branch switcher pills", str_contains($res1branch['stdout'], 'انتخاب شعبه'));
assertCondition("Branch storefront includes live search autocomplete input", str_contains($res1branch['stdout'], 'storeLiveSearchInput'));

$res1p = runRequest('GET', '/b/central/p/1');
assertCondition("Storefront product detail (/b/central/p/1) renders successfully", $res1p['exit_code'] === 0);
assertCondition("Product detail includes live stock indicator / badge", str_contains($res1p['stdout'], 'موجودی') || str_contains($res1p['stdout'], 'انبار'));

$res1api = runRequest('GET', '/api/v1/store/central/products?q=تست');
assertCondition("Catalog API products search returns output", !empty($res1api['stdout']));
$json1 = json_decode($res1api['stdout'], true);
$dataObj = $json1['data'] ?? $json1;
assertCondition("Catalog API returns products array", isset($dataObj['products']) || isset($json1['products']));

// -------------------------------------------------------------
// Archetype 2: Multi-Step Checkout & Card-to-Card Payment
// -------------------------------------------------------------
echo "\n=== Archetype 2: Multi-Step Checkout & Card-to-Card Payment ===\n";
$res2 = runRequest('GET', '/checkout?shop_id=1', $customerSession);
assertCondition("Checkout page renders successfully", $res2['exit_code'] === 0 && !empty($res2['stdout']));
assertCondition("Checkout contains 4-day reservation urgency banner", str_contains($res2['stdout'], 'reservation-urgency-banner') || str_contains($res2['stdout'], 'رزرو'));
assertCondition("Checkout contains countdown clock elements", str_contains($res2['stdout'], 'countdownDisplay'));
assertCondition("Checkout contains bank card copy button with Persian-to-English digit fix", str_contains($res2['stdout'], 'copyCardNumber') && str_contains($res2['stdout'], 'faDigits'));
assertCondition("Checkout contains dynamic Sheba copy", str_contains($res2['stdout'], 'copyShabaNumber'));
assertCondition("Checkout contains receipt upload dropzone with preview", str_contains($res2['stdout'], 'receiptDropzone') && str_contains($res2['stdout'], 'receiptPreviewImg'));
assertCondition("Checkout has fallback to localStorage cart when server items empty", str_contains($res2['stdout'], 'BefrooshStore.getCart'));

// -------------------------------------------------------------
// Archetype 3: Customer Portal & Order Tracking
// -------------------------------------------------------------
echo "\n=== Archetype 3: Customer Portal & Order Tracking ===\n";
$res3 = runRequest('GET', '/orders/1', $merchantSession);
assertCondition("Order tracking portal renders successfully", $res3['exit_code'] === 0 && !empty($res3['stdout']));
assertCondition("Order portal contains 5-stage milestone stepper", str_contains($res3['stdout'], 'stepper-5') && str_contains($res3['stdout'], 'stepper-item'));
assertCondition("Order portal contains receipt verification status badge", str_contains($res3['stdout'], 'وضعیت پرداخت') || str_contains($res3['stdout'], 'status-badge') || str_contains($res3['stdout'], 'تأیید فیش'));
assertCondition("Order portal contains printable invoice link", str_contains($res3['stdout'], '/orders/1/print'));
assertCondition("Storefront link in order items uses canonical /b/ route", str_contains($res3['stdout'], '/b/') && !str_contains($res3['stdout'], '/s/'));

// -------------------------------------------------------------
// Archetype 4: Merchant Operations & Verification Queue
// -------------------------------------------------------------
echo "\n=== Archetype 4: Merchant Operations & Verification Queue ===\n";
$res4 = runRequest('GET', '/merchant/orders', $merchantSession);
assertCondition("Merchant orders queue renders successfully", $res4['exit_code'] === 0 && !empty($res4['stdout']));
assertCondition("Merchant orders contains KPI cards (Expiring reservations & Pending slips)", str_contains($res4['stdout'], 'رزروهای در آستانه انقضا') && str_contains($res4['stdout'], 'فیش‌های در انتظار بررسی'));
assertCondition("Merchant orders contains split-screen slip inspection modal", str_contains($res4['stdout'], 'slipInspectorModal') && str_contains($res4['stdout'], 'split-inspect-grid'));
assertCondition("Inspection modal contains optical check badges", str_contains($res4['stdout'], 'تطبیق مبلغ فاکتور') && str_contains($res4['stdout'], 'حساب مقصد فروشگاه'));
assertCondition("Inspection modal contains image zoom toggle button", str_contains($res4['stdout'], 'toggleSlipZoom') && str_contains($res4['stdout'], 'zoomToggleBtn'));
assertCondition("Inspection modal contains store bank account selector", str_contains($res4['stdout'], 'inspectBankCardSelect') && str_contains($res4['stdout'], 'bank_card_id'));

// -------------------------------------------------------------
// Archetype 5: Merchant Inventory Audit
// -------------------------------------------------------------
echo "\n=== Archetype 5: Merchant Inventory Audit ===\n";
$res5 = runRequest('GET', '/merchant/inventory', $merchantSession);
assertCondition("Merchant inventory renders successfully", $res5['exit_code'] === 0 && !empty($res5['stdout']));
assertCondition("Inventory includes low stock alert indicators", str_contains($res5['stdout'], 'low_stock') || str_contains($res5['stdout'], 'کسری موجودی') || str_contains($res5['stdout'], 'stat-card'));
assertCondition("Inventory links to inward stock intake", str_contains($res5['stdout'], '/inventory/inward'));
assertCondition("Inventory links to stock adjustment", str_contains($res5['stdout'], '/inventory/adjustment'));

$res5inward = runRequest('GET', '/merchant/inventory/inward', $merchantSession);
assertCondition("Inward stock entry route resolves successfully", $res5inward['exit_code'] === 0 && !empty($res5inward['stdout']));

$res5adj = runRequest('GET', '/merchant/inventory/adjustment', $merchantSession);
assertCondition("Stock adjustment route resolves successfully", $res5adj['exit_code'] === 0 && !empty($res5adj['stdout']));

// -------------------------------------------------------------
// Archetype 6: Store Accounting Ledger & Trial Balance
// -------------------------------------------------------------
echo "\n=== Archetype 6: Store Accounting Ledger & Trial Balance ===\n";
$res6 = runRequest('GET', '/merchant/accounting', $merchantSession);
assertCondition("Merchant accounting overview renders successfully", $res6['exit_code'] === 0 && !empty($res6['stdout']));
assertCondition("Accounting includes double-entry balance check indicator", str_contains($res6['stdout'], 'balance-check-banner') || str_contains($res6['stdout'], 'تراز دفاتر کاملاً متعادل است'));
assertCondition("Accounting includes trial balance chart of accounts", str_contains($res6['stdout'], 'جدول تراز آزمایشی') || str_contains($res6['stdout'], 'Chart of Accounts'));
assertCondition("Accounting includes journal vouchers table", str_contains($res6['stdout'], 'Journal Vouchers') || str_contains($res6['stdout'], 'اسناد ثبت‌شده در دفتر روزنامه'));

$res6led = runRequest('GET', '/merchant/accounting/ledger', $merchantSession);
assertCondition("Merchant general ledger renders successfully", $res6led['exit_code'] === 0 && !empty($res6led['stdout']));
assertCondition("Ledger contains sales_revenue and shipping_revenue filter options", str_contains($res6led['stdout'], 'value="sales_revenue"') && str_contains($res6led['stdout'], 'value="shipping_revenue"'));

// -------------------------------------------------------------
// Archetype 7: SuperAdmin Platform Panel
// -------------------------------------------------------------
echo "\n=== Archetype 7: SuperAdmin Platform Panel ===\n";
$res7 = runRequest('GET', '/admin/plans', $adminSession);
assertCondition("Superadmin plans matrix renders successfully", $res7['exit_code'] === 0 && !empty($res7['stdout']));
assertCondition("Plans matrix includes 4 tiers (free, plus, pro, ultimate)", str_contains($res7['stdout'], 'free') && str_contains($res7['stdout'], 'plus') && str_contains($res7['stdout'], 'pro') && str_contains($res7['stdout'], 'ultimate'));
assertCondition("Plans matrix contains high-priority ticket escalation badge", str_contains($res7['stdout'], 'ارجاع تیکت به سوپرادمین') && str_contains($res7['stdout'], 'VIP آنی'));

$res7my = runRequest('GET', '/admin/plans/my-plan', $merchantSession);
assertCondition("Merchant my-plan subscription meters render successfully", $res7my['exit_code'] === 0 && !empty($res7my['stdout']));
assertCondition("My-plan contains usage progress meters", str_contains($res7my['stdout'], 'quota-meter-fill') || str_contains($res7my['stdout'], 'درصد مصرف'));

echo "\n======================================================================\n";
echo "SUMMARY: Total: " . ($passed + $failed) . " | Passed: $passed | Failed: $failed\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
