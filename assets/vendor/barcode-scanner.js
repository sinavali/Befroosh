/**
 * barcode-scanner.js
 * Universal offline barcode scanning support for Befroosh Platform
 * - Hardware / Keyboard-Wedge Scanner detection (PC, Android, iOS USB/Bluetooth)
 * - HTML5 Camera Barcode Detection for Mobile & Tablet devices
 */
(function() {
    'use strict';

    // 1. HARDWARE KEYBOARD WEDGE SCANNER LISTENER
    let buffer = '';
    let lastKeyTime = Date.now();
    const SCAN_TIMEOUT = 50; // max ms between keystrokes for hardware scanner

    window.addEventListener('keydown', function(e) {
        // If typing slowly in a text input or textarea, let normal typing proceed
        const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
        const isEditable = activeTag === 'textarea' || (activeTag === 'input' && !document.activeElement.hasAttribute('data-barcode-input'));

        const now = Date.now();
        const diff = now - lastKeyTime;
        lastKeyTime = now;

        if (e.key === 'Enter') {
            if (buffer.length >= 3 && (diff < 100 || !isEditable)) {
                e.preventDefault();
                const code = buffer.trim();
                buffer = '';
                triggerBarcode(code);
            } else {
                buffer = '';
            }
            return;
        }

        if (diff > SCAN_TIMEOUT && isEditable) {
            // Typing manually
            buffer = '';
            return;
        }

        if (e.key.length === 1) {
            buffer += e.key;
        }
    }, true);

    function triggerBarcode(code) {
        console.log('[Barcode Scanned]', code);
        
        // Dispatch global event
        const event = new CustomEvent('barcode-scanned', { detail: { barcode: code } });
        window.dispatchEvent(event);

        // Auto-fill active barcode input if available
        const barcodeInputs = document.querySelectorAll('[data-barcode-input]');
        if (barcodeInputs.length > 0) {
            barcodeInputs[0].value = code;
            barcodeInputs[0].dispatchEvent(new Event('input', { bubbles: true }));
            barcodeInputs[0].dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Show brief visual toast
        showScanToast(code);
    }

    function showScanToast(code) {
        let toast = document.getElementById('barcode-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'barcode-toast';
            toast.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#10b981;color:#fff;padding:10px 18px;border-radius:10px;font-size:0.9rem;font-weight:bold;z-index:99999;box-shadow:0 10px 25px rgba(0,0,0,0.2);display:flex;align-items:center;gap:8px;direction:rtl;';
            document.body.appendChild(toast);
        }
        toast.innerHTML = '<span>بارکد شناسایی شد: </span><code style="background:rgba(255,255,255,0.2);padding:2px 6px;border-radius:4px;">' + code + '</code>';
        toast.style.display = 'flex';
        clearTimeout(toast._timeout);
        toast._timeout = setTimeout(() => { toast.style.display = 'none'; }, 3000);
    }

    // 2. MOBILE / TABLET CAMERA SCANNER
    window.BefrooshScanner = {
        isCameraSupported: function() {
            return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && ('BarcodeDetector' in window));
        },
        openCamera: function(onSuccess) {
            if (!this.isCameraSupported()) {
                alert('قابلیت اسکن با دوربین در این مرورگر پشتیبانی نمی‌شود. از بارکدخوان فیزیکی استفاده نمایید یا بارکد را به صورت دستی وارد کنید.');
                return;
            }

            let modal = document.getElementById('camera-scanner-modal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'camera-scanner-modal';
                modal.className = 'modal-backdrop';
                modal.style.display = 'flex';
                modal.innerHTML = `
                    <div class="modal" style="max-width:440px; text-align:center;">
                        <div class="modal-header">
                            <h3>اسکن بارکد با دوربین</h3>
                            <button type="button" class="btn btn-ghost btn-sm" id="close-camera-scanner">✕</button>
                        </div>
                        <div class="modal-body" style="padding:10px;">
                            <video id="scanner-video" style="width:100%; height:260px; object-fit:cover; border-radius:8px; background:#000;" playsinline></video>
                            <div style="font-size:0.8rem; color:#6b7280; margin-top:8px;">بارکد یا QR کد کالا را جلوی دوربین قرار دهید</div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            } else {
                modal.style.display = 'flex';
            }

            const video = document.getElementById('scanner-video');
            const closeBtn = document.getElementById('close-camera-scanner');

            let stream = null;
            let scanning = true;

            const barcodeDetector = new BarcodeDetector({
                formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'qr_code', 'upc_a', 'upc_e']
            });

            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(function(s) {
                    stream = s;
                    video.srcObject = stream;
                    video.play();

                    const detectInterval = setInterval(function() {
                        if (!scanning) {
                            clearInterval(detectInterval);
                            return;
                        }
                        barcodeDetector.detect(video).then(function(barcodes) {
                            if (barcodes.length > 0) {
                                scanning = false;
                                const raw = barcodes[0].rawValue;
                                stopStream();
                                modal.style.display = 'none';
                                triggerBarcode(raw);
                                if (typeof onSuccess === 'function') onSuccess(raw);
                            }
                        }).catch(function(err) {});
                    }, 250);
                })
                .catch(function(err) {
                    alert('خطا در دسترسی به دوربین: ' + err.message);
                    modal.style.display = 'none';
                });

            function stopStream() {
                scanning = false;
                if (stream) {
                    stream.getTracks().forEach(t => t.stop());
                    stream = null;
                }
            }

            closeBtn.onclick = function() {
                stopStream();
                modal.style.display = 'none';
            };
        }
    };
})();
