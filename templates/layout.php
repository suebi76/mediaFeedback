<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'mediaFeedback') ?></title>
    <link rel="icon" href="data:,">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
    <link rel="stylesheet" href="<?= V2_WEB_ROOT ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?= V2_WEB_ROOT ?>/assets/css/base.css">
    <link rel="stylesheet" href="<?= V2_WEB_ROOT ?>/assets/css/components.css">
    <link rel="stylesheet" href="<?= V2_WEB_ROOT ?>/assets/css/legacy.css">
    <link rel="stylesheet" href="<?= V2_WEB_ROOT ?>/assets/css/editor.css">
    <link rel="stylesheet" href="<?= V2_WEB_ROOT ?>/assets/css/public.css">
</head>
<body>
<?php if (($currentUser ?? null) !== null): ?>
    <header class="topbar">
        <div class="topbar-inner">
            <div class="brand">mediaFeedback<small><?= e($currentUser['name']) ?> · <?= e(role_label((string) $currentUser['role'])) ?></small></div>
            <div class="nav-links">
                <a href="<?= V2_BASE_URL ?>/dashboard">Dashboard</a>
                <form action="<?= V2_BASE_URL ?>/logout" method="post">
                    <input type="hidden" name="_token" value="<?= e(\MediaFeedbackV2\Core\Csrf::token('logout')) ?>">
                    <button class="btn secondary small" type="submit">Abmelden</button>
                </form>
            </div>
        </div>
    </header>
<?php endif; ?>
<main class="shell">
    <div class="container">
        <?php if (!empty($flash)): ?>
            <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
        <?= $content ?>
    </div>
</main>
<?php if (($currentUser ?? null) !== null): ?>
    <div id="share-modal" class="share-backdrop" hidden>
        <div class="card share-dialog stack" role="dialog" aria-modal="true" aria-labelledby="share-modal-title">
            <div class="split">
                <div>
                    <div class="badge">Teilen</div>
                    <h2 id="share-modal-title" style="margin:0.4rem 0 0;">Feedback teilen</h2>
                </div>
                <button class="btn secondary small" type="button" data-share-close>Schließen</button>
            </div>
            <div class="share-qr-wrap">
                <canvas id="share-qr-canvas" class="share-qr" width="220" height="220" aria-label="QR-Code zum Feedback-Link"></canvas>
            </div>
            <div class="share-url-row">
                <input id="share-url-input" class="share-url-input" type="text" readonly>
                <button id="share-copy-button" class="btn secondary" type="button">Link kopieren</button>
            </div>
            <div class="actions">
                <button id="share-native-button" class="btn secondary" type="button" hidden>System teilen</button>
                <a id="share-open-link" class="btn secondary" href="#" target="_blank" rel="noopener">Link öffnen</a>
                <a id="share-download-qr" class="btn secondary" href="#" download="feedback-qr.png">QR-Code laden</a>
            </div>
            <div id="share-status" class="share-status" aria-live="polite"></div>
        </div>
    </div>
    <script>
        (function () {
            const modal = document.getElementById('share-modal');
            const input = document.getElementById('share-url-input');
            const qrCanvas = document.getElementById('share-qr-canvas');
            const copyButton = document.getElementById('share-copy-button');
            const nativeButton = document.getElementById('share-native-button');
            const openLink = document.getElementById('share-open-link');
            const downloadQr = document.getElementById('share-download-qr');
            const status = document.getElementById('share-status');
            let currentTitle = 'Feedback';
            let qr = null;

            if (!modal || !input || !qrCanvas || !copyButton || !nativeButton || !openLink || !downloadQr || !status) {
                return;
            }

            const setStatus = (message, isError) => {
                status.textContent = message || '';
                status.style.color = isError ? 'var(--bad)' : 'var(--muted)';
            };

            const closeModal = () => {
                modal.hidden = true;
                modal.classList.remove('open');
                setStatus('');
            };

            const fallbackCopy = async (text) => {
                const helper = document.createElement('textarea');
                helper.value = text;
                helper.setAttribute('readonly', 'readonly');
                helper.style.position = 'fixed';
                helper.style.opacity = '0';
                helper.style.pointerEvents = 'none';
                document.body.appendChild(helper);
                helper.focus();
                helper.select();
                helper.setSelectionRange(0, helper.value.length);

                const copied = document.execCommand('copy');
                document.body.removeChild(helper);

                if (copied) {
                    return true;
                }

                throw new Error('copy-failed');
            };

            const copyLink = async () => {
                try {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        await navigator.clipboard.writeText(input.value);
                    } else {
                        await fallbackCopy(input.value);
                    }
                    setStatus('Link wurde in die Zwischenablage kopiert.');
                } catch (error) {
                    setStatus('Der Link konnte nicht automatisch kopiert werden. Du kannst ihn aber direkt aus dem Feld kopieren.', true);
                }
            };

            const renderQr = (url) => {
                if (typeof window.QRious !== 'function') {
                    setStatus('QR-Code konnte in diesem Browser nicht erzeugt werden. Der Link funktioniert trotzdem.', true);
                    return;
                }

                if (!qr) {
                    qr = new window.QRious({
                        element: qrCanvas,
                        value: url,
                        size: 220,
                        level: 'H',
                        padding: 0,
                    });
                } else {
                    qr.value = url;
                }

                downloadQr.href = qrCanvas.toDataURL('image/png');
            };

            const openModal = (button) => {
                const url = button.getAttribute('data-share-url') || '';
                const title = button.getAttribute('data-share-title') || 'Feedback';

                currentTitle = title;
                input.value = url;
                openLink.href = url;
                downloadQr.setAttribute('download', 'feedback-' + title.toLowerCase().replace(/[^a-z0-9]+/gi, '-').replace(/(^-|-$)/g, '') + '-qr.png');
                renderQr(url);

                if (navigator.share) {
                    nativeButton.hidden = false;
                } else {
                    nativeButton.hidden = true;
                }

                setStatus('Du kannst den Link kopieren, direkt teilen oder den QR-Code herunterladen.');
                modal.hidden = false;
                modal.classList.add('open');
            };

            document.querySelectorAll('[data-share-url]').forEach((button) => {
                button.addEventListener('click', () => openModal(button));
            });

            document.querySelectorAll('[data-share-close]').forEach((button) => {
                button.addEventListener('click', closeModal);
            });

            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal.classList.contains('open')) {
                    closeModal();
                }
            });

            copyButton.addEventListener('click', copyLink);
            nativeButton.addEventListener('click', async () => {
                try {
                    await navigator.share({
                        title: currentTitle,
                        text: currentTitle,
                        url: input.value,
                    });
                    setStatus('Feedback-Link wurde an das System zum Teilen übergeben.');
                } catch (error) {
                    if (error && error.name === 'AbortError') {
                        setStatus('Teilen wurde abgebrochen.');
                        return;
                    }
                    setStatus('Systemteilen ist auf diesem Gerät gerade nicht verfügbar. Du kannst stattdessen den Link kopieren.', true);
                }
            });
        }());
    </script>
<?php endif; ?>
</body>
</html>
