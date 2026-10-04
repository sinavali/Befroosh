<?php
declare(strict_types=1);

/**
 * layout_head.php
 * Head metadata, stylesheets, and client-side offline store scripts
 */

function render_layout_head(string $title): void
{
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | سامانه بفروش</title>
    <link href="/assets/Vazirmatn-font-face.css" rel="stylesheet">
    <link href="/assets/vendor/select2.min.css" rel="stylesheet">
    <script src="/assets/vendor/jquery.min.js"></script>
    <script src="/assets/vendor/select2.min.js"></script>

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
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; border: none; border-radius: var(--radius); padding: 8px 14px; font-size: 0.88rem; font-weight: bold; cursor: pointer; transition: 0.15s; line-height: 1.5; text-decoration: none; }
        .btn:hover { opacity: 0.92; transform: translateY(-1px); }
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
        .form-control, .input, .select, textarea { width: 100%; border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px; background: #fff; color: #1e293b; outline: none; }
        .form-control:focus, .input:focus, .select:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(37,99,235,0.15); }
        .fav-heart-btn { cursor: pointer; transition: transform 0.15s; display: inline-flex; align-items: center; justify-content: center; }
        .fav-heart-btn:hover { transform: scale(1.15); }
        .fav-heart-btn.is-active svg { fill: #ef4444 !important; stroke: #ef4444 !important; }
        .toast-msg { position: fixed; bottom: 24px; left: 24px; z-index: 9999; background: #1e293b; color: #fff; padding: 12px 20px; border-radius: 8px; font-size: 0.9rem; font-weight: bold; box-shadow: 0 10px 25px rgba(0,0,0,0.2); transform: translateY(100px); opacity: 0; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
        .toast-msg.show { transform: translateY(0); opacity: 1; }
    </style>

    <script>
    window.BefrooshStore = {
        getCart: function() {
            try { return JSON.parse(localStorage.getItem('befroosh_cart') || '[]'); } catch(e) { return []; }
        },
        setCart: function(items) {
            localStorage.setItem('befroosh_cart', JSON.stringify(items));
            this.updateBadges();
        },
        addToCart: function(item) {
            var cart = this.getCart();
            var found = cart.find(function(i) { return i.id === item.id; });
            if (found) {
                found.qty = (found.qty || 1) + (item.qty || 1);
            } else {
                cart.push(item);
            }
            this.setCart(cart);
            this.showToast('محصول «' + item.title + '» به سبد خرید اضافه شد.');
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
            var idx = favs.indexOf(productId);
            var active = false;
            if (idx > -1) {
                favs.splice(idx, 1);
            } else {
                favs.push(productId);
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
            }).then(function(res){ return res.json(); }).then(function(data){
                if (data.ok) {
                    localStorage.removeItem('befroosh_cart');
                    localStorage.removeItem('befroosh_favs');
                }
            }).catch(function(){});
        }
    };
    document.addEventListener('DOMContentLoaded', function() {
        window.BefrooshStore.updateBadges();
        var favs = window.BefrooshStore.getFavs();
        document.querySelectorAll('[data-product-id]').forEach(function(el) {
            var pid = parseInt(el.getAttribute('data-product-id'), 10);
            if (favs.includes(pid)) { el.classList.add('is-active'); }
        });
        <?php if (is_authenticated()): ?>
        window.BefrooshStore.syncToServer('<?= csrf_token() ?>');
        <?php endif; ?>
    });
    </script>
    <?php
}
