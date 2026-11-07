{{-- Camera barcode / QR scanner shared by every "Camera" button (x-camera-scan-button). The library loads only when first used. --}}
<div id="cam-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/70 p-4">
    <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-line px-4 py-3">
            <span class="font-extrabold">Scan with camera</span>
            <button type="button" id="cam-close" class="rounded-lg p-1.5 text-muted hover:bg-canvas" aria-label="Close camera"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <div class="p-4">
            <div id="cam-reader" class="overflow-hidden rounded-xl bg-slate-100"></div>
            <p id="cam-status" class="mt-3 text-sm text-muted">Point the camera at a barcode or QR code.</p>
            <p id="cam-last" class="mt-1 hidden font-mono text-sm font-bold text-brand"></p>
        </div>
    </div>
</div>
<script>
    (() => {
        const LIB = 'https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js';
        const modal = document.getElementById('cam-modal');
        const statusEl = document.getElementById('cam-status');
        const lastEl = document.getElementById('cam-last');
        let scanner = null, active = null, lastCode = '', lastAt = 0, libPromise = null;

        const loadLib = () => libPromise ??= new Promise((resolve, reject) => {
            if (window.Html5Qrcode) return resolve();
            const s = document.createElement('script');
            s.src = LIB; s.onload = resolve; s.onerror = () => reject(new Error('Could not load the scanner library. Check your internet connection.'));
            document.head.appendChild(s);
        });

        const resolveTarget = (btn) => {
            const sel = btn.dataset.cameraScan || '';
            if (sel.startsWith('closest:')) {
                const [scope, inner] = sel.slice(8).split('|');
                return btn.closest(scope)?.querySelector(inner);
            }
            return document.querySelector(sel);
        };

        const stop = async () => {
            modal.classList.add('hidden'); modal.classList.remove('flex');
            try { if (scanner?.isScanning) await scanner.stop(); scanner?.clear(); } catch (e) {}
            scanner = null; active = null;
        };

        const deliver = (code) => {
            const { target, mode, submit } = active;
            if (mode === 'append-line') {
                const lines = target.value.split(/\r?\n/).map((x) => x.trim()).filter(Boolean);
                if (lines.includes(code)) { statusEl.textContent = code + ' is already in the list.'; return false; }
                target.value = lines.concat(code).join('\n') + '\n';
                target.dispatchEvent(new Event('input', { bubbles: true }));
            } else if (mode === 'enter') {
                // optional: skip codes already in a list, without a blocking alert()
                const list = active.list ? document.querySelector(active.list) : null;
                if (list && list.value.split(/\r?\n/).map((x) => x.trim()).includes(code)) { statusEl.textContent = code + ' is already in the list.'; return false; }
                target.value = code;
                target.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
            } else {
                target.value = code;
                target.dispatchEvent(new Event('input', { bubbles: true }));
            }
            return true;
        };

        const onCode = (code) => {
            const now = Date.now();
            if (!active || (code === lastCode && now - lastAt < 2500)) return;   // ignore the same code read repeatedly
            lastCode = code; lastAt = now;
            if (!deliver(code)) return;
            lastEl.textContent = code; lastEl.classList.remove('hidden');
            statusEl.textContent = 'Scanned. Point at the next one, or close when done.';
            if (navigator.vibrate) navigator.vibrate(80);
            // single scan: close (and submit if asked); continuous modes stay open
            if (active.mode === 'set') {
                const form = active.submit ? active.target.form : null;
                stop().then(() => form?.requestSubmit());
            }
        };

        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-camera-scan]');
            if (!btn) return;
            e.preventDefault();
            const target = resolveTarget(btn);
            if (!target) return;
            lastEl.classList.add('hidden'); lastCode = '';
            modal.classList.remove('hidden'); modal.classList.add('flex');
            if (window.lucide) lucide.createIcons();
            active = { target, mode: btn.dataset.cameraMode || 'set', submit: !!btn.dataset.cameraSubmit, list: btn.dataset.cameraList || null };

            if (!window.isSecureContext) {
                statusEl.textContent = 'The camera only works on a secure (https) page.'; return;
            }
            statusEl.textContent = 'Starting camera...';
            try {
                await loadLib();
                scanner = new Html5Qrcode('cam-reader');
                await scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 260, height: 160 } }, onCode, () => {});
                statusEl.textContent = 'Point the camera at a barcode or QR code.';
            } catch (err) {
                statusEl.textContent = (err && err.message) ? err.message : 'Cannot open the camera. Allow camera access for this site and try again.';
            }
        });

        document.getElementById('cam-close').addEventListener('click', stop);
        modal.addEventListener('click', (e) => { if (e.target === modal) stop(); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && active) stop(); });
    })();
</script>
