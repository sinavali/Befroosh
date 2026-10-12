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
            --font: 'Vazirmatn', -apple-system, BlinkMacSystemFont, "SF Pro Text", "SF Pro Display", "Segoe UI", Roboto, sans-serif;
            --canvas: #ffffff;
            --app-bg: #f5f5f7;
            --surface-muted: #fbfbfd;
            --surface-well: #f2f2f7;
            --border: #e5e5ea;
            --border-subtle: #d1d1d6;
            --ink: #1d1d1f;
            --slate: #48484a;
            --muted: #6e6e73;
            --placeholder: #8e8e93;
            --primary: #0071e3;
            --primary-dark: #0077ed;
            --primary-light: #edf5ff;
            --emerald: #34c759;
            --emerald-bg: #e8f8ed;
            --amber: #ff9500;
            --amber-bg: #fff4e5;
            --crimson: #ff3b30;
            --crimson-bg: #ffebea;
            --indigo: #5856d6;
            --indigo-bg: #f0effc;
            --radius-sm: 8px;
            --radius: 10px;
            --radius-lg: 14px;
            --radius-full: 9999px;
            --shadow-whisper: 0 1px 3px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02);
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.03);
            --shadow-lift: 0 12px 32px rgba(0, 0, 0, 0.08), 0 4px 12px rgba(0, 0, 0, 0.04);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-font-smoothing: antialiased; }
        body { font-family: var(--font); background: var(--app-bg); color: var(--ink); line-height: 1.75; direction: rtl; text-align: right; min-height: 100vh; font-feature-settings: "ss01" 1; }
        a { color: inherit; text-decoration: none; transition: color 0.15s ease; }
        table { width: 100%; border-collapse: collapse; }
        button, input, select, textarea { font-family: inherit; font-size: 0.88rem; }
        .tabular-nums, .font-mono, [data-tabular] { font-variant-numeric: tabular-nums; font-feature-settings: "tnum" 1; letter-spacing: -0.01em; }

        .card { background: var(--canvas); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-whisper); margin-bottom: 16px; transition: box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease; }
        .card-header { padding: 14px 18px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; background: #fff; border-top-left-radius: var(--radius); border-top-right-radius: var(--radius); }
        .card-header h2 { font-size: 1rem; font-weight: 600; color: var(--ink); margin: 0; letter-spacing: -0.01em; }
        .card-body { padding: 18px 20px; }
        .card-footer { padding: 12px 18px; background: var(--surface-muted); border-top: 1px solid var(--border); border-radius: 0 0 var(--radius) var(--radius); display: flex; align-items: center; gap: 10px; }

        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; border: 1px solid transparent; border-radius: var(--radius-sm); padding: 7px 14px; font-size: 0.86rem; font-weight: 500; cursor: pointer; transition: all 0.16s cubic-bezier(0.16, 1, 0.3, 1); line-height: 1.5; text-decoration: none; user-select: none; }
        .btn:hover { opacity: 0.95; transform: translateY(-1px); }
        .btn:active { transform: scale(0.98); }
        .btn-sm { padding: 5px 11px; font-size: 0.8rem; border-radius: var(--radius-sm); }
        .btn-lg { padding: 10px 20px; font-size: 0.95rem; border-radius: var(--radius); }
        .btn-primary { background: var(--primary); color: #fff; border-color: var(--primary); }
        .btn-primary:hover { background: var(--primary-dark); border-color: var(--primary-dark); }
        .btn-cobalt { background: var(--primary); color: #fff; border-color: var(--primary); }
        .btn-cobalt:hover { background: var(--primary-dark); }
        .btn-success { background: var(--emerald); color: #fff; border-color: var(--emerald); }
        .btn-danger { background: var(--crimson); color: #fff; border-color: var(--crimson); }
        .btn-outline { background: #fff; border: 1px solid var(--border-subtle); color: var(--ink); }
        .btn-outline:hover { background: var(--surface-well); border-color: var(--border-subtle); color: var(--ink); }
        .btn-ghost { background: transparent; color: var(--muted); border: 1px solid transparent; }
        .btn-ghost:hover { background: var(--surface-well); color: var(--ink); }

        .badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: var(--radius-full); font-size: 0.74rem; font-weight: 600; letter-spacing: -0.01em; }
        .badge-amber { background: #fff4e5; color: #a05e00; border: 1px solid rgba(255, 149, 0, 0.25); }
        .badge-blue { background: #edf5ff; color: #0062c4; border: 1px solid rgba(0, 113, 227, 0.25); }
        .badge-purple { background: #f0effc; color: #4341b4; border: 1px solid rgba(88, 86, 214, 0.25); }
        .badge-emerald { background: #e8f8ed; color: #1d833e; border: 1px solid rgba(52, 199, 89, 0.25); }
        .badge-rose { background: #ffebea; color: #d70015; border: 1px solid rgba(255, 59, 48, 0.25); }
        .badge-muted { background: var(--surface-well); color: var(--slate); border: 1px solid var(--border); }
        .badge-gray { background: #f2f2f7; color: #636366; border: 1px solid #e5e5ea; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.82rem; font-weight: 500; color: var(--slate); margin-bottom: 5px; }
        .form-control, .input, .select, textarea { width: 100%; border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 8px 12px; background: #fff; color: var(--ink); outline: none; transition: border-color 0.15s, box-shadow 0.15s; font-size: 0.88rem; }
        .form-control:focus, .input:focus, .select:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0, 113, 227, 0.15); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
        .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; }
        .detail-item { background: var(--surface-muted); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 12px 16px; }
        .detail-label { font-size: 0.76rem; color: var(--muted); font-weight: 500; margin-bottom: 4px; }
        .detail-value { font-size: 0.95rem; font-weight: 600; color: var(--ink); }

        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 14px; }
        .page-title-wrap { display: flex; align-items: center; gap: 12px; }
        .page-icon { width: 40px; height: 40px; border-radius: 10px; background: #edf5ff; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; border: 1px solid rgba(0, 113, 227, 0.2); }
        .page-title-wrap h1 { font-size: 1.3rem; font-weight: 700; color: var(--ink); margin: 0; letter-spacing: -0.01em; }
        .page-sub { font-size: 0.82rem; color: var(--muted); margin-top: 2px; }
        .filter-bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px; margin-bottom: 18px; }
        .stat-card, .metric-card {
            background: var(--canvas);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 8px;
            box-shadow: var(--shadow-whisper);
            transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
        }
        .stat-card:hover, .metric-card:hover {
            border-color: var(--border-subtle);
            box-shadow: var(--shadow);
            transform: translateY(-1px);
        }
        .metric-header, .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .metric-label, .stat-label {
            font-size: 0.8rem;
            color: var(--muted);
            font-weight: 500;
            letter-spacing: -0.01em;
        }
        .metric-value, .stat-value {
            font-size: 1.55rem;
            font-weight: 700;
            color: var(--ink);
            line-height: 1.15;
            letter-spacing: -0.02em;
            font-variant-numeric: tabular-nums;
            font-feature-settings: "tnum" 1;
        }
        .metric-sub, .stat-sub {
            font-size: 0.74rem;
            color: var(--slate);
            font-weight: 400;
        }
        .stat-icon { display: none; }
        .table-responsive { overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch; }
        .table th { background: var(--surface-muted); padding: 11px 16px; font-size: 0.78rem; font-weight: 600; color: var(--muted); text-align: right; border-bottom: 1px solid var(--border); white-space: nowrap; }
        .table td { padding: 13px 16px; font-size: 0.88rem; border-bottom: 1px solid #f2f2f7; vertical-align: middle; color: var(--ink); }
        .table tbody tr:hover { background: #fbfbfd; }
        .flex { display: flex; align-items: center; }

        /* Bank Card Mockup Component (Apple Light HIG) */
        .bank-card-mockup {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 16px;
            color: var(--ink);
            padding: 24px 28px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            max-width: 440px;
            width: 100%;
        }
        .bank-card-mockup::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(0,113,227,0.03) 0%, transparent 70%);
            pointer-events: none;
        }
        .bank-card-chip {
            width: 44px;
            height: 32px;
            background: linear-gradient(135deg, #fcd34d, #f59e0b);
            border-radius: 6px;
            border: 1px solid #d97706;
            margin-bottom: 20px;
            position: relative;
        }
        .bank-card-number {
            font-family: 'JetBrains Mono', Consolas, monospace;
            font-size: 1.35rem;
            letter-spacing: 0.18em;
            direction: ltr;
            text-align: center;
            margin: 16px 0;
            font-weight: 700;
            color: var(--ink);
            text-shadow: none;
        }
        .bank-card-details {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 0.85rem;
            color: var(--muted);
            margin-top: 14px;
        }
        .bank-card-holder {
            font-weight: 700;
            color: var(--ink);
            font-size: 0.95rem;
        }

        /* 4-Day Reservation Banner Component */
        .reservation-banner {
            background: var(--amber-bg);
            border: 1px solid rgba(255, 149, 0, 0.28);
            border-radius: var(--radius);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 20px;
        }
        .reservation-timer-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ffffff;
            border: 1px solid rgba(255, 149, 0, 0.35);
            padding: 6px 14px;
            border-radius: var(--radius-full);
            font-weight: 800;
            color: #a05e00;
            font-size: 0.92rem;
            font-variant-numeric: tabular-nums;
        }
        .reservation-progress {
            width: 100%;
            height: 5px;
            background: rgba(255, 149, 0, 0.15);
            border-radius: 99px;
            overflow: hidden;
            margin-top: 8px;
        }
        .reservation-progress-bar {
            height: 100%;
            background: var(--amber);
            border-radius: 99px;
            transition: width 1s linear;
        }

        /* 5-Stage Iran Post Stepper Component */
        .stepper-5 {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            position: relative;
            margin: 24px 0 16px;
        }
        .stepper-item {
            text-align: center;
            position: relative;
            z-index: 2;
        }
        .stepper-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            margin: 0 auto 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.88rem;
            background: var(--surface-well);
            color: var(--muted);
            border: 2px solid var(--border-subtle);
            transition: all 0.2s ease;
        }
        .stepper-item.done .stepper-circle {
            background: var(--emerald);
            color: #fff;
            border-color: var(--emerald);
        }
        .stepper-item.active .stepper-circle {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37,99,235,0.18);
        }
        .stepper-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--muted);
            line-height: 1.4;
        }
        .stepper-item.done .stepper-label { color: var(--ink); }
        .stepper-item.active .stepper-label { color: var(--primary); font-weight: 800; }
        .stepper-track {
            position: absolute;
            top: 18px;
            right: 10%;
            left: 10%;
            height: 2px;
            background: var(--border);
            z-index: 1;
        }
        .stepper-track-fill {
            height: 100%;
            background: var(--emerald);
            transition: width 0.3s ease;
        }

        /* Post 24-digit Tracking Barcode Card */
        .barcode-tracking-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: var(--radius);
            padding: 18px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 20px;
        }
        .barcode-number {
            font-family: 'JetBrains Mono', Consolas, monospace;
            font-size: 1.25rem;
            font-weight: 800;
            color: #1e40af;
            letter-spacing: 0.12em;
            direction: ltr;
        }

        /* Slip Drag-and-Drop Dropzone */
        .receipt-dropzone {
            border: 2px dashed #cbd5e1;
            border-radius: var(--radius);
            padding: 28px 20px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .receipt-dropzone:hover, .receipt-dropzone.dragover {
            border-color: var(--primary);
            background: #eff6ff;
        }
        .dropzone-preview {
            max-width: 240px;
            max-height: 180px;
            border-radius: 8px;
            border: 1px solid var(--border);
            margin: 12px auto 0;
            object-fit: cover;
            display: none;
        }

        /* Apple Light Mode Modals & Sheets */
        .modal-overlay, .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.28);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal-overlay.open, .modal-backdrop.open, .modal-backdrop.active { display: flex; }
        .modal, .modal-sheet {
            background: #ffffff;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 520px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lift);
            border: 1px solid var(--border-subtle);
            display: flex;
            flex-direction: column;
            animation: appleModalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .modal-sheet { max-width: 880px; }
        @keyframes appleModalPop {
            from { opacity: 0; transform: scale(0.96); }
            to { opacity: 1; transform: scale(1); }
        }
        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            border-top-left-radius: var(--radius-lg);
            border-top-right-radius: var(--radius-lg);
        }
        .modal-header h3 {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--ink);
            margin: 0;
        }
        .modal-body { padding: 20px; }
        .modal-footer {
            padding: 14px 20px;
            border-top: 1px solid var(--border);
            background: var(--surface-muted);
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
        }
        .split-inspect-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            padding: 24px;
        }
        @media (max-width: 768px) {
            .split-inspect-grid { grid-template-columns: 1fr; }
        /* Live Ledger Preview in Inspection Modal */
        .ledger-preview-card {
            background: var(--surface-muted);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            margin: 12px 0;
        }
        .ledger-preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--slate);
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 1px solid var(--border);
        }
        .ledger-preview-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.82rem;
            padding: 3px 0;
            color: var(--ink);
        }
        .ledger-preview-delta {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.74rem;
            font-weight: 700;
            color: #1d833e;
            background: #e8f8ed;
            padding: 2px 8px;
            border-radius: var(--radius-full);
            border: 1px solid rgba(52, 199, 89, 0.3);
        }

        /* Double-Entry Ledger & Balance Verification */
        .balance-check-banner {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-ambient);
        }
        .balance-metric {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .balance-metric-label {
            font-size: 0.78rem;
            color: var(--muted);
            font-weight: 500;
        }
        .balance-metric-value {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--ink);
            font-variant-numeric: tabular-nums;
            font-feature-settings: "tnum" 1;
        }
        .account-hierarchy-tag {
            font-family: 'JetBrains Mono', Consolas, monospace;
            font-size: 0.76rem;
            background: var(--surface-well);
            padding: 2px 7px;
            border-radius: 4px;
            border: 1px solid var(--border-subtle);
            color: var(--slate);
        }

        /* Quota Usage Meters */
        .quota-meter-wrap {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 140px;
        }
        .quota-meter-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.76rem;
            color: var(--slate);
            font-weight: 600;
        }
        .quota-meter-bar {
            height: 6px;
            background: var(--surface-well);
            border-radius: 99px;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.04);
        }
        .quota-meter-fill {
            height: 100%;
            border-radius: 99px;
            transition: width 0.4s ease;
        }
        /* Apple Messages Style Chat Thread */
        .chat, .chat-thread {
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: 8px 4px;
        }
        .chat .message, .chat-bubble-wrap {
            display: flex;
            flex-direction: column;
            max-width: 80%;
            border-radius: var(--radius-lg);
            padding: 14px 18px;
            box-shadow: var(--shadow-whisper);
            border: 1px solid var(--border);
            transition: all 0.15s ease;
        }
        .chat .message.mine, .chat-bubble-wrap.mine {
            align-self: flex-start;
            background: #ffffff;
            border-right: 3px solid var(--primary);
        }
        .chat .message.other, .chat-bubble-wrap.other {
            align-self: flex-end;
            background: var(--surface-muted);
            border-left: 3px solid var(--border-subtle);
        }
        .chat .message-meta, .chat-meta {
            font-size: 0.78rem;
            color: var(--muted);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .chat .message-body, .chat-body {
            font-size: 0.92rem;
            line-height: 1.7;
            color: var(--ink);
            white-space: pre-wrap;
            word-break: break-word;
        }
        .chat .attachments, .chat-attachments {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px dashed var(--border);
        }
        .chat .attachments a img, .chat-attachments a img {
            width: 76px;
            height: 76px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .chat .attachments a img:hover, .chat-attachments a img:hover {
            transform: scale(1.04);
            box-shadow: var(--shadow);
        }

        .gap-1 { gap: 6px; }
        .gap-2 { gap: 10px; }
        .gap-3 { gap: 16px; }
        .fav-heart-btn { cursor: pointer; transition: transform 0.15s; display: inline-flex; align-items: center; justify-content: center; background: none; border: none; }
        .fav-heart-btn:hover { transform: scale(1.15); }
        .fav-heart-btn.is-active svg { fill: #ef4444 !important; stroke: #ef4444 !important; }
        .toast-msg { position: fixed; bottom: 24px; left: 24px; z-index: 9999; background: #ffffff; color: #1d1d1f; border: 1px solid rgba(0,0,0,0.08); padding: 12px 20px; border-radius: 12px; font-size: 0.9rem; font-weight: bold; box-shadow: 0 12px 32px rgba(0,0,0,0.12); transform: translateY(100px); opacity: 0; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
        .toast-msg.show { transform: translateY(0); opacity: 1; }
        /* Quill RTL styling */
        .ql-editor { direction: rtl; text-align: right; font-family: var(--font); min-height: 180px; font-size: 0.92rem; line-height: 1.8; }
        .ql-toolbar { direction: ltr; text-align: right; background: #f8fafc; border-top-left-radius: 8px; border-top-right-radius: 8px; }
        /* Apple Light KamaDatepicker Popover Customization */
        .bd-main {
            background: #ffffff !important;
            border: 1px solid var(--border-subtle) !important;
            border-radius: var(--radius-lg) !important;
            box-shadow: var(--shadow-lift) !important;
            padding: 12px !important;
            font-family: var(--font) !important;
            direction: rtl !important;
            z-index: 99999 !important;
        }
        .bd-table { font-family: var(--font) !important; width: 100% !important; }
        .bd-table th { font-size: 0.76rem !important; color: var(--muted) !important; font-weight: 600 !important; padding: 6px 4px !important; text-align: center !important; }
        .bd-table td { padding: 3px !important; text-align: center !important; }
        .bd-day { border-radius: var(--radius-sm) !important; font-size: 0.84rem !important; color: var(--ink) !important; font-family: var(--font) !important; transition: all 0.15s ease !important; cursor: pointer !important; padding: 6px !important; }
        .bd-day:hover { background: var(--surface-well) !important; color: var(--ink) !important; }
        .bd-selected-day { background: var(--primary) !important; color: #ffffff !important; border-radius: var(--radius-sm) !important; font-weight: 700 !important; }
        .bd-today { border: 1px solid var(--primary) !important; border-radius: var(--radius-sm) !important; color: var(--primary) !important; }
        .bd-prev, .bd-next { color: var(--primary) !important; font-weight: bold !important; cursor: pointer !important; padding: 4px 8px !important; border-radius: var(--radius-sm) !important; }
        .bd-prev:hover, .bd-next:hover { background: var(--surface-well) !important; }

        /* Print Media Styles: Hide actions and layout chrome */
        @media print {
            @page { size: A4; margin: 15mm; }
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
