<?php
declare(strict_types=1);

/**
 * layout_head.php
 * Head metadata, stylesheets, offline store scripts, Quill editor, and Persian datepicker
 */

function render_layout_head(string $title): void
{
    $user = current_user();
    $userFavIds = [];
    if ($user) {
        global $pdo;
        try {
            $fStmt = $pdo->prepare("SELECT product_id FROM product_bookmarks WHERE user_id = ?");
            $fStmt->execute([$user['id']]);
            $userFavIds = array_map('intval', $fStmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $e) {}
    }
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | سامانه بفروش</title>
    <link href="/assets/Vazirmatn-font-face.css" rel="stylesheet">
    <link href="/assets/vendor/select2.min.css" rel="stylesheet">
    <link href="/assets/vendor/quill.snow.css" rel="stylesheet">
    <link href="/assets/vendor/persian-datepicker.min.css" rel="stylesheet">
    <script src="/assets/vendor/jquery.min.js"></script>
    <script src="/assets/vendor/select2.min.js"></script>
    <script src="/assets/vendor/quill.min.js"></script>
    <script src="/assets/vendor/persian-datepicker.min.js"></script>

    <style>
        :root {
            --font: 'Vazirmatn', Tahoma, sans-serif;
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --radius: 8px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--font); background: var(--bg); color: var(--text); line-height: 1.8; direction: rtl; text-align: right; min-height: 100vh; }
        a { color: inherit; text-decoration: none; }
        table { width: 100%; border-collapse: collapse; }
        button, input, select, textarea { font-family: inherit; font-size: 0.9rem; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); margin-bottom: 16px; }
        .card-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
        .card-header h2 { font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0; }
        .card-body { padding: 20px; }
        .card-footer { padding: 14px 20px; background: #f8fafc; border-top: 1px solid var(--border); border-radius: 0 0 var(--radius) var(--radius); display: flex; align-items: center; gap: 10px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; border: none; border-radius: var(--radius); padding: 8px 14px; font-size: 0.88rem; font-weight: bold; cursor: pointer; transition: 0.15s; line-height: 1.5; text-decoration: none; }
        .btn:hover { opacity: 0.92; transform: translateY(-1px); }
        .btn-sm { padding: 5px 10px; font-size: 0.8rem; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-success { background: #059669; color: #fff; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-outline { background: #fff; border: 1px solid var(--border); color: #334155; }
        .btn-ghost { background: transparent; color: #64748b; }
        .badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 99px; font-size: 0.75rem; font-weight: bold; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-purple { background: #f3e8ff; color: #6b21a8; }
        .badge-emerald { background: #d1fae5; color: #065f46; }
        .badge-rose { background: #ffe4e6; color: #9f1239; }
        .badge-muted { background: #f1f5f9; color: #475569; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 6px; }
        .form-control, .input, .select, textarea { width: 100%; border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px; background: #fff; color: #1e293b; outline: none; }
        .form-control:focus, .input:focus, .select:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(37,99,235,0.15); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
        .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; }
        .detail-item { background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 12px 16px; }
        .detail-label { font-size: 0.76rem; color: #64748b; font-weight: 600; margin-bottom: 4px; }
        .detail-value { font-size: 0.95rem; font-weight: 700; color: #0f172a; }
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
        .page-title-wrap { display: flex; align-items: center; gap: 12px; }
        .page-icon { width: 40px; height: 40px; border-radius: 10px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        .page-title-wrap h1 { font-size: 1.3rem; font-weight: 800; color: #0f172a; margin: 0; }
        .page-sub { font-size: 0.82rem; color: #64748b; margin-top: 2px; }
        .filter-bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px; }
        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px 20px; display: flex; align-items: center; gap: 16px; box-shadow: var(--shadow); }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; }
        .stat-icon.emerald { background: #ecfdf5; color: #059669; }
        .stat-icon.blue { background: #eff6ff; color: #2563eb; }
        .stat-icon.purple { background: #faf5ff; color: #7c3aed; }
        .stat-icon.amber { background: #fffbeb; color: #d97706; }
        .stat-icon.rose { background: #fff1f2; color: #e11d48; }
        .stat-value { font-size: 1.35rem; font-weight: 800; color: #0f172a; line-height: 1.2; }
        .stat-label { font-size: 0.8rem; color: #64748b; margin-top: 4px; }
        .table-responsive { overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch; }
        .table th { background: #f8fafc; padding: 12px 14px; font-size: 0.82rem; font-weight: 700; color: #475569; text-align: right; border-bottom: 1px solid var(--border); white-space: nowrap; }
        .table td { padding: 12px 14px; font-size: 0.88rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .flex { display: flex; align-items: center; }
        .gap-1 { gap: 6px; }
        .gap-2 { gap: 10px; }
        .gap-3 { gap: 16px; }
        .fav-heart-btn { cursor: pointer; transition: transform 0.15s; display: inline-flex; align-items: center; justify-content: center; background: none; border: none; }
        .fav-heart-btn:hover { transform: scale(1.15); }
        .fav-heart-btn.is-active svg { fill: #ef4444 !important; stroke: #ef4444 !important; }
        .toast-msg { position: fixed; bottom: 24px; left: 24px; z-index: 9999; background: #1e293b; color: #fff; padding: 12px 20px; border-radius: 8px; font-size: 0.9rem; font-weight: bold; box-shadow: 0 10px 25px rgba(0,0,0,0.2); transform: translateY(100px); opacity: 0; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
        .toast-msg.show { transform: translateY(0); opacity: 1; }
        /* Quill RTL styling */
        .ql-editor { direction: rtl; text-align: right; font-family: var(--font); min-height: 180px; font-size: 0.92rem; line-height: 1.8; }
        .ql-toolbar { direction: ltr; text-align: right; background: #f8fafc; border-top-left-radius: 8px; border-top-right-radius: 8px; }
        .ql-container { border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; }

        /* Print Media Styles: Hide actions and layout chrome */
        @media print {
            .sidebar, .topbar, .store-header, .store-footer, .sidebar-backdrop, .btn, .action-cluster, .actions, th.actions, td.actions, .no-print, [data-no-print], .card-footer {
                display: none !important;
            }
            body { background: #fff !important; color: #000 !important; }
            .card { border: none !important; box-shadow: none !important; margin: 0 !important; padding: 0 !important; }
            .main-content { margin: 0 !important; }
            .content-area { padding: 0 !important; max-width: 100% !important; }
            .table th, .table td { border-bottom: 1px solid #ddd !important; }
        }
    </style>

    <script>
    // Live price comma formatting for inputs
    document.addEventListener('input', function(e) {
        var el = e.target;
        if (el && el.matches('input[data-price-input], input[name="price"], input[name="cost_price"], input[name="price_irt"], input[name*="shipping"]')) {
            var raw = el.value.replace(/[^0-9]/g, '');
            if (raw) {
                el.value = Number(raw).toLocaleString('en-US');
            }
        }
    });
    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (form) {
            form.querySelectorAll('input[data-price-input], input[name="price"], input[name="cost_price"], input[name="price_irt"], input[name*="shipping"]').forEach(function(inp) {
                inp.value = inp.value.replace(/,/g, '');
            });
        }
    });
    window.BefrooshStore = {
        getCart: function() {
            try { return JSON.parse(localStorage.getItem('befroosh_cart') || '[]'); } catch(e) { return []; }
        },
        setCart: function(items) {
            localStorage.setItem('befroosh_cart', JSON.stringify(items));
            this.updateBadges();
            if (typeof window.renderMiniCart === 'function') { window.renderMiniCart(); }
        },
        addToCart: function(item) {
            var cart = this.getCart();
            var found = cart.find(function(i) { return Number(i.id) === Number(item.id); });
            if (found) {
                found.qty = (found.qty || 1) + (item.qty || 1);
            } else {
                cart.push(item);
            }
            this.setCart(cart);
            this.showToast('محصول «' + item.title + '» به سبد خرید اضافه شد.');
            if (typeof window.openMiniCart === 'function') { window.openMiniCart(); }
        },
        getFavs: function() {
            try { return JSON.parse(localStorage.getItem('befroosh_favs') || '[]'); } catch(e) { return []; }
        },
        setFavs: function(favs) {
            localStorage.setItem('befroosh_favs', JSON.stringify(favs));
            this.updateBadges();
        },
        toggleFav: function(productId, btnEl) {
            var favs = this.getFavs();
            var pId = parseInt(productId, 10);
            var idx = favs.indexOf(pId);
            var active = false;
            if (idx > -1) {
                favs.splice(idx, 1);
            } else {
                favs.push(pId);
                active = true;
            }
            this.setFavs(favs);
            if (btnEl) {
                if (active) {
                    btnEl.classList.add('is-active');
                    this.showToast('به لیست علاقه‌مندی‌ها اضافه شد.');
                } else {
                    btnEl.classList.remove('is-active');
                    this.showToast('از لیست علاقه‌مندی‌ها حذف شد.');
                }
            }
            <?php if ($user): ?>
            fetch('/api/toggle-favorite', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: pId })
            }).catch(function(){});
            <?php endif; ?>
            return active;
        },
        updateBadges: function() {
            var cartCount = this.getCart().reduce(function(acc, i){ return acc + (i.qty || 1); }, 0);
            var favCount = this.getFavs().length;
            var cEl = document.getElementById('store-cart-badge');
            if (cEl) { cEl.textContent = cartCount; cEl.style.display = cartCount > 0 ? 'inline-flex' : 'none'; }
            var fEl = document.getElementById('store-fav-badge');
            if (fEl) { fEl.textContent = favCount; fEl.style.display = favCount > 0 ? 'inline-flex' : 'none'; }
        },
        showToast: function(msg) {
            var el = document.getElementById('global-toast');
            if (!el) {
                el = document.createElement('div');
                el.id = 'global-toast';
                el.className = 'toast-msg';
                document.body.appendChild(el);
            }
            el.textContent = msg;
            el.classList.add('show');
            clearTimeout(window._toastTimeout);
            window._toastTimeout = setTimeout(function(){ el.classList.remove('show'); }, 3000);
        },
        syncToServer: function(csrf) {
            var cart = this.getCart();
            var favs = this.getFavs();
            if (cart.length === 0 && favs.length === 0) return;
            fetch('/api/sync-guest-data', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf: csrf, cart: cart, favorites: favs })
            }).then(function(res){ return res.json(); }).catch(function(){});
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($userFavIds)): ?>
        window.BefrooshStore.setFavs(<?= json_encode($userFavIds) ?>);
        <?php endif; ?>

        window.BefrooshStore.updateBadges();
        var favs = window.BefrooshStore.getFavs();
        document.querySelectorAll('[data-product-id]').forEach(function(el) {
            var pid = parseInt(el.getAttribute('data-product-id'), 10);
            if (favs.includes(pid)) { el.classList.add('is-active'); }
        });

        <?php if ($user): ?>
        window.BefrooshStore.syncToServer('<?= csrf_token() ?>');
        <?php endif; ?>

        // Initialize Quill Rich Text Editors
        if (typeof Quill !== 'undefined') {
            document.querySelectorAll('[data-rich-editor]').forEach(function(textarea) {
                if (textarea.dataset.quillReady) return;
                textarea.dataset.quillReady = 'true';
                textarea.style.display = 'none';

                var wrap = document.createElement('div');
                wrap.className = 'quill-wrapper';
                textarea.parentNode.insertBefore(wrap, textarea);

                var quill = new Quill(wrap, {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            [{ 'header': [1, 2, 3, false] }],
                            ['bold', 'italic', 'underline'],
                            [{ 'color': [] }, { 'background': [] }],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                            [{ 'direction': 'rtl' }, { 'align': [] }],
                            ['link', 'clean']
                        ]
                    }
                });
                quill.root.innerHTML = textarea.value;
                quill.on('text-change', function() { textarea.value = quill.root.innerHTML; });
                if (textarea.form) {
                    textarea.form.addEventListener('submit', function() { textarea.value = quill.root.innerHTML; });
                }
            });
        }

        // Initialize Persian / Shamsi Datepicker
        if (typeof kamaDatepicker === 'function') {
            document.querySelectorAll('input.jdate, input[data-jdate], input[name="from"], input[name="to"]').forEach(function(el) {
                if (!el.id) el.id = 'jdate_' + Math.random().toString(36).substring(2, 9);
                kamaDatepicker(el.id, {
                    placeholder: '۱۴۰۳/۰۱/۰۱',
                    twodigit: true,
                    closeAfterSelect: true,
                    nextButtonIcon: '‹',
                    previousButtonIcon: '›',
                    buttonsColor: 'blue',
                    markToday: true,
                    markHolidays: true,
                    highlightSelectedDay: true,
                    sync: true
                });
            });
        }
    });
    </script>
    <?php
}
