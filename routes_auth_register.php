<?php
declare(strict_types=1);

/**
 * routes_auth_register.php
 * Public Registration for Customers and Business Owners
 */

$registerHandler = function () use ($pdo) {
    if (current_user()) {
        redirect('/dashboard');
    }

    $accountType = $_GET['type'] ?? $_POST['account_type'] ?? 'customer';
    if (!in_array($accountType, ['customer', 'business_owner'], true)) {
        $accountType = 'customer';
    }

    $error = '';
    $next = $_GET['next'] ?? $_POST['next'] ?? ($accountType === 'business_owner' ? '/dashboard' : '/profile');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            $error = 'توکن امنیتی نامعتبر یا منقضی شده است.';
        } else {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $nickname = trim($_POST['nickname'] ?? '');
            if (!$nickname) {
                $nickname = trim($firstName . ' ' . $lastName) ?: 'کاربر جدید';
            }
            $username = trim($_POST['username'] ?? '');
            $phoneInput = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';
            $shopName = trim($_POST['shop_name'] ?? '');
            $shopSlug = trim($_POST['shop_slug'] ?? '');

            $normPhone = normalize_phone($phoneInput);

            if (empty($username) || strlen($username) < 3) {
                $error = 'نام کاربری باید حداقل ۳ کاراکتر و به حروف یا اعداد انگلیسی باشد.';
            } elseif (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $username)) {
                $error = 'نام کاربری فقط می‌تواند شامل حروف، اعداد، خط فاصله و زیرخط انگلیسی باشد.';
            } elseif (!$normPhone || strlen($normPhone) !== 10) {
                $error = 'شماره موبایل وارد شده نامعتبر است (مثال: ۰۹۱۲۳۴۵۶۷۸۹).';
            } elseif (strlen($password) < 6) {
                $error = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
            } elseif ($password !== $passwordConfirm) {
                $error = 'تکرار رمز عبور با رمز عبور اصلی مطابقت ندارد.';
            } elseif ($accountType === 'business_owner' && (empty($shopName) || empty($shopSlug))) {
                $error = 'نام کسب‌وکار و شناسه انگلیسی (نامک) برای ایجاد فروشگاه الزامی است.';
            } else {
                // Check username or phone uniqueness
                $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR phone = ?) AND deleted_at IS NULL LIMIT 1");
                $stmt->execute([$username, $normPhone]);
                if ($stmt->fetch()) {
                    $error = 'این نام کاربری یا شماره موبایل قبلاً در سامانه ثبت شده است.';
                }

                // Check shop slug uniqueness if business
                if (!$error && $accountType === 'business_owner') {
                    $shopSlug = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '', $shopSlug));
                    if (strlen($shopSlug) < 3) {
                        $error = 'شناسه انگلیسی فروشگاه (اسلاگ) باید حداقل ۳ کاراکتر انگلیسی باشد.';
                    } else {
                        $chkShop = $pdo->prepare("SELECT id FROM shops WHERE slug = ? LIMIT 1");
                        $chkShop->execute([$shopSlug]);
                        if ($chkShop->fetch()) {
                            $error = 'این شناسه فروشگاه قبلاً رزرو شده است. لطفاً شناسه دیگری انتخاب کنید.';
                        }
                    }
                }

                if (!$error) {
                    try {
                        $pdo->beginTransaction();
                        $now = date('Y-m-d H:i:s');
                        $pwdHash = password_hash($password, PASSWORD_DEFAULT);

                        // 1. Create User
                        $role = ($accountType === 'business_owner') ? 'business_owner' : 'customer';
                        $insUser = $pdo->prepare("
                            INSERT INTO users (username, password, nickname, first_name, last_name, role, phone, active, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
                        ");
                        $insUser->execute([$username, $pwdHash, $nickname, $firstName, $lastName, $role, $normPhone, $now, $now]);
                        $userId = (int)$pdo->lastInsertId();

                        // 2. If business owner, create shop and default branch
                        if ($accountType === 'business_owner') {
                            $expiresAt = date('Y-m-d H:i:s', time() + 365 * 86400);
                            $insShop = $pdo->prepare("
                                INSERT INTO shops (name, slug, owner_id, phone, card_to_card_enabled, reservation_days, subscription_plan_id, subscription_expires_at, active, created_at, updated_at)
                                VALUES (?, ?, ?, ?, 1, 4, 1, ?, 1, ?, ?)
                            ");
                            $insShop->execute([$shopName, $shopSlug, $userId, '0' . $normPhone, $expiresAt, $now, $now]);
                            $shopId = (int)$pdo->lastInsertId();

                            // Update user's shop_id
                            $pdo->prepare("UPDATE users SET shop_id = ? WHERE id = ?")->execute([$shopId, $userId]);

                            // Create Main Branch
                            $insBranch = $pdo->prepare("
                                INSERT INTO branches (business_id, name, slug, catalog_mode, is_main, active, created_at)
                                VALUES (?, 'شعبه اصلی', 'main', 'both', 1, 1, ?)
                            ");
                            $insBranch->execute([$shopId, $now]);
                            $branchId = (int)$pdo->lastInsertId();

                            // Assign user to branch
                            $insAssign = $pdo->prepare("
                                INSERT INTO branch_user_assignments (business_id, branch_id, user_id, role, created_at)
                                VALUES (?, ?, ?, 'branch_manager', ?)
                            ");
                            $insAssign->execute([$shopId, $branchId, $userId, $now]);
                        }

                        $pdo->commit();

                        // Log user in directly
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $userId;
                        $_SESSION['last_activity'] = time();

                        if ($accountType === 'business_owner') {
                            flash('success', 'فروشگاه شما با موفقیت ایجاد گردید! به جمع فروشندگان بَفروش خوش آمدید.');
                            redirect('/dashboard');
                        } else {
                            flash('success', 'حساب کاربری شما با موفقیت ایجاد شد! اکنون می‌توانید خرید خود را انجام دهید.');
                            redirect($next);
                        }
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        $error = 'خطا در ثبت‌نام: ' . $e->getMessage();
                    }
                }
            }
        }
    }

    auth_layout_start('ثبت‌نام در سامانه');
    ?>
    <div class="logo"><?= icon('user', 22) ?></div>
    <h1>ثبت‌نام در بَفروش</h1>
    <div class="sub">حساب کاربری خود را در چند ثانیه بسازید</div>

    <div style="display:flex; background:#f1f5f9; padding:4px; border-radius:10px; margin-bottom:20px; gap:4px;">
        <a href="/register?type=customer" style="flex:1; text-align:center; padding:8px 12px; font-size:0.85rem; font-weight:bold; border-radius:8px; text-decoration:none; <?= $accountType === 'customer' ? 'background:#fff; color:#2563eb; box-shadow:0 1px 3px rgba(0,0,0,0.1);' : 'color:#64748b;' ?>">
            <?= icon('user', 14) ?> خریدار
        </a>
        <a href="/register?type=business_owner" style="flex:1; text-align:center; padding:8px 12px; font-size:0.85rem; font-weight:bold; border-radius:8px; text-decoration:none; <?= $accountType === 'business_owner' ? 'background:#fff; color:#2563eb; box-shadow:0 1px 3px rgba(0,0,0,0.1);' : 'color:#64748b;' ?>">
            <?= icon('store', 14) ?> ایجاد فروشگاه (صاحب کسب‌وکار)
        </a>
    </div>

    <?php if ($error): ?>
        <div class="error" style="background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:10px 14px; border-radius:8px; font-size:0.88rem; margin-bottom:16px;">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/register">
        <?= csrf_field() ?>
        <input type="hidden" name="account_type" value="<?= e($accountType) ?>">
        <input type="hidden" name="next" value="<?= e($next) ?>">

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div class="form-group">
                <label>نام</label>
                <input class="input" type="text" name="first_name" value="<?= e($_POST['first_name'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label>نام خانوادگی</label>
                <input class="input" type="text" name="last_name" value="<?= e($_POST['last_name'] ?? '') ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>شماره موبایل</label>
            <input class="input" type="text" name="phone" placeholder="۰۹۱۲۳۴۵۶۷۸۹" value="<?= e($_POST['phone'] ?? '') ?>" dir="ltr" required>
        </div>

        <div class="form-group">
            <label>نام کاربری انگلیسی</label>
            <input class="input" type="text" name="username" placeholder="ali_rezaei" value="<?= e($_POST['username'] ?? '') ?>" dir="ltr" required>
        </div>

        <?php if ($accountType === 'business_owner'): ?>
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px; margin-bottom:16px;">
                <div style="font-weight:bold; color:#0f172a; font-size:0.9rem; margin-bottom:10px; display:flex; align-items:center; gap:6px;">
                    <?= icon('store', 15) ?> اطلاعات فروشگاه شما
                </div>
                <div class="form-group" style="margin-bottom:10px;">
                    <label>نام فروشگاه / برند</label>
                    <input class="input" type="text" name="shop_name" placeholder="مثال: پوشاک آریانا" value="<?= e($_POST['shop_name'] ?? '') ?>" required>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>شناسه انگلیسی فروشگاه (آدرس اختصاصی)</label>
                    <div style="display:flex; align-items:center; direction:ltr; background:#fff; border:1px solid #cbd5e1; border-radius:8px; padding-left:10px;">
                        <span style="color:#94a3b8; font-size:0.85rem;">/b/</span>
                        <input class="input" type="text" name="shop_slug" placeholder="ariana" value="<?= e($_POST['shop_slug'] ?? '') ?>" style="border:none; box-shadow:none;" required>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div class="form-group">
                <label>رمز عبور</label>
                <input class="input" type="password" name="password" minlength="6" required>
            </div>
            <div class="form-group">
                <label>تکرار رمز عبور</label>
                <input class="input" type="password" name="password_confirm" minlength="6" required>
            </div>
        </div>

        <button class="btn" type="submit" style="width:100%; margin-top:8px;">
            <?= $accountType === 'business_owner' ? 'ثبت‌نام و راه‌اندازی فروشگاه' : 'تکمیل ثبت‌نام و ورود' ?>
        </button>
    </form>

    <div style="margin-top:20px; text-align:center; font-size:0.88rem; color:#64748b;">
        قبلاً ثبت‌نام کرده‌اید؟ <a href="/login" style="color:#2563eb; font-weight:bold; text-decoration:none;">ورود به حساب</a>
    </div>
    <?php
    auth_layout_end();
};

route('GET|POST', '/register(?:\.php)?', [], $registerHandler);
route('GET|POST', '/signup(?:\.php)?', [], $registerHandler);
