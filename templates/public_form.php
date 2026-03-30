<?php
$layoutMode = $feedback['layout'];
$totalPages = count($pages);
$settings = $settings ?? [
    'intro_enabled' => false,
    'intro_text' => '',
    'progress_bar' => true,
    'estimated_time_minutes' => null,
    'limit_one_response_per_device' => false,
];
$showIntroOnly = !empty($showIntroOnly);
$estimatedTime = !empty($settings['estimated_time_minutes']) ? (int) $settings['estimated_time_minutes'] : null;
$layoutLabel = layout_label((string) $layoutMode);
$introText = trim((string) ($settings['intro_text'] ?? ''));

$publicPageTitle = static function (array $blocks, int $pageIndex): string {
    foreach ($blocks as $block) {
        if ($block['activity']->isQuestion()) {
            $label = trim((string) ($block['data']['label'] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }
    }

    foreach ($blocks as $block) {
        $content = trim(strip_tags((string) ($block['data']['content'] ?? $block['data']['caption'] ?? '')));
        if ($content !== '') {
            if (function_exists('mb_substr') && function_exists('mb_strlen')) {
                return mb_substr($content, 0, 72) . (mb_strlen($content) > 72 ? '…' : '');
            }

            return substr($content, 0, 72) . (strlen($content) > 72 ? '…' : '');
        }
    }

    return 'Schritt ' . ($pageIndex + 1);
};


?>
<div class="public-feedback-shell">
    <header class="public-feedback-header">
        <div class="public-feedback-header-kicker">mediaFeedback</div>
        <h1 class="public-feedback-title"><?= e($feedback['title']) ?></h1>
        <p class="public-feedback-lead">
            <?= !empty($feedback['description']) ? e((string) $feedback['description']) : 'Danke, dass du dir ein paar Minuten Zeit nimmst. Dein Feedback hilft dabei, Inhalte und Formate gezielt weiterzuentwickeln.' ?>
        </p>



        <?php if (!empty($preview)): ?>
            <div class="public-feedback-preview-note" style="margin-top: 1.5rem;">
                <strong>Vorschau</strong>
                <span>Du siehst dieses Feedback im Entwurfsmodus. Antworten werden trotzdem gespeichert, solange du als Eigentümer eingeloggt bist.</span>
            </div>
        <?php endif; ?>
    </header>

<?php if ($showIntroOnly): ?>
    <section class="public-intro-section">
        <div class="public-stage-card stack text-center">
            <?php if ($introText !== ''): ?>
                <p class="public-intro-text"><?= nl2br(e($introText)) ?></p>
            <?php endif; ?>
            


            <div class="actions" style="justify-content: center; margin-top: 1rem;">
                <a class="btn public-primary-button" href="<?= V2_BASE_URL ?>/f?slug=<?= e($feedback['slug']) ?>&start=1" style="font-size: 1.125rem; padding: 0.75rem 2rem;">Feedback starten</a>
            </div>
        </div>
    </section>
<?php else: ?>
    <form id="public-form" class="public-feedback-form stack" action="<?= V2_BASE_URL ?>/f/submit" method="post" enctype="multipart/form-data">
        <input type="hidden" name="feedback_id" value="<?= (int) $feedback['id'] ?>">
        <input type="hidden" name="feedback_slug" value="<?= e($feedback['slug']) ?>">
        <input type="hidden" name="submit_token" value="<?= e($submitToken) ?>">
        <input type="hidden" name="device_hash" value="">

        <?php if ($layoutMode === 'classic'): ?>
            <div class="public-classic-layout">
                <?php foreach ($pages as $pageNumber => $blocks): ?>
                    <section class="public-stage-card public-classic-section stack">
                        <div class="public-stage-head">
                            <div>
                                <div class="badge public-badge-soft">Seite <?= (int) $pageNumber + 1 ?></div>
                                <h2><?= e($publicPageTitle($blocks, (int) $pageNumber)) ?></h2>
                            </div>
                            <div class="public-stage-mini-card">
                                <span>Fokus</span>
                                <strong><?= count($blocks) ?> Baustein<?= count($blocks) === 1 ? '' : 'e' ?></strong>
                            </div>
                        </div>
                        <?php foreach ($blocks as $block): ?>
                            <?= $block['activity']->renderPublic($block, $block['data'], $oldAnswers[(int) $block['id']] ?? null, $errorsByBlock) ?>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>
                <div class="public-stage-card public-submit-card">
                    <div>
                        <h2>Bereit zum Absenden?</h2>
                        <p class="meta">Prüfe deine Antworten kurz und sende dein Feedback dann mit einem Klick ab.</p>
                    </div>
                    <button class="btn public-primary-button" type="submit">Feedback absenden</button>
                </div>
            </div>
        <?php else: ?>
            <div class="public-slider-viewport">
                <div class="public-step-slider" id="public-step-slider">
            <?php $pageIndex = 0; foreach ($pages as $pageNumber => $blocks): ?>
                <?php
                $progressPercent = (int) round((($pageIndex + 1) / max($totalPages, 1)) * 100);
                $isFirstStep = $pageIndex === 0;
                $pageTitle = $publicPageTitle($blocks, $pageIndex);
                ?>
                <section class="public-step public-stage-card<?= $isFirstStep ? ' is-active' : '' ?>" data-step="<?= $pageIndex ?>">
                    <div class="public-step-top">
                        <div class="public-step-copy">
                            <div class="badge public-badge-soft">Schritt <?= $pageIndex + 1 ?> von <?= $totalPages ?></div>
                            <h2><?= e($pageTitle) ?></h2>
                        </div>
                        <div class="public-step-progress-card">
                            <?php if (!empty($settings['progress_bar'])): ?>
                                <div class="public-step-progress-meta">
                                    <span>Fortschritt</span>
                                    <strong><?= $progressPercent ?> %</strong>
                                </div>
                                <div class="public-progress-track" role="progressbar" aria-valuemin="1" aria-valuemax="<?= $totalPages ?>" aria-valuenow="<?= $pageIndex + 1 ?>" aria-label="Fortschritt im Feedback">
                                    <div class="public-progress-value" style="width:<?= (($pageIndex + 1) / max($totalPages, 1)) * 100 ?>%;"></div>
                                </div>
                            <?php else: ?>
                                <div class="public-step-progress-meta">
                                    <span>Seitenmodus</span>
                                    <strong>Fokussiert</strong>
                                </div>
                                <p class="meta">Auf jeder Seite siehst du nur die gerade relevanten Inhalte.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="public-step-body stack">
                        <?php foreach ($blocks as $block): ?>
                            <?= $block['activity']->renderPublic($block, $block['data'], $oldAnswers[(int) $block['id']] ?? null, $errorsByBlock) ?>
                        <?php endforeach; ?>
                    </div>
                    <div class="actions public-step-actions">
                        <?php if ($pageIndex > 0): ?><button class="btn secondary" type="button" onclick="showStep(<?= $pageIndex - 1 ?>)">Zurück</button><?php endif; ?>
                        <?php if ($pageIndex < $totalPages - 1): ?><button class="btn public-primary-button" type="button" onclick="showStep(<?= $pageIndex + 1 ?>)">Weiter zu Schritt <?= $pageIndex + 2 ?></button><?php else: ?><button class="btn public-primary-button" type="submit">Feedback absenden</button><?php endif; ?>
                    </div>
                </section>
            <?php $pageIndex++; endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </form>
<?php endif; ?>
</div>

<script>
    function focusFirstField(stepNode) {
        if (!stepNode) {
            return;
        }

        const target = stepNode.querySelector('textarea, input:not([type="hidden"]):not([type="file"]), select');
        if (target) {
            target.focus({ preventScroll: true });
        }
    }

    function showStep(step) {
        const slider = document.getElementById('public-step-slider');
        if (slider) {
            slider.style.transform = `translateX(-${step * 100}%)`;
        }

        document.querySelectorAll('.public-step').forEach(function (node) {
            const isActive = Number(node.getAttribute('data-step')) === step;
            if (isActive) {
                node.classList.add('is-active');
                setTimeout(() => focusFirstField(node), 300);
            } else {
                node.classList.remove('is-active');
            }
        });

        const shell = document.querySelector('.public-feedback-shell');
        if (shell) {
            shell.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const initialStep = document.querySelector('.public-step.is-active') || document.querySelector('.public-step:not([hidden])') || document.querySelector('.public-step');
        focusFirstField(initialStep);

        document.querySelectorAll('[data-answer-mode-group]').forEach((group) => {
            const options = Array.from(group.querySelectorAll('[data-answer-mode-option]'));
            const panels = Array.from(group.querySelectorAll('[data-answer-mode-panel]'));
            const setMode = (mode) => {
                options.forEach((input) => {
                    const card = input.closest('.public-choice-card');
                    const isActive = input.value === mode;
                    input.checked = isActive;
                    if (card) {
                        card.classList.toggle('is-selected', isActive);
                    }
                });
                panels.forEach((panel) => {
                    const isActive = panel.getAttribute('data-answer-mode-panel') === mode;
                    panel.hidden = !isActive;
                    if (isActive) {
                        const recorderRoots = panel.matches('[data-recorder-root]')
                            ? [panel]
                            : Array.from(panel.querySelectorAll('[data-recorder-root]'));
                        recorderRoots.forEach((root) => {
                            if (typeof root.__activateRecorder === 'function') {
                                root.__activateRecorder();
                            }
                        });
                    }
                });
            };

            const defaultMode = group.getAttribute('data-default-mode') || (options[0] ? options[0].value : '');
            options.forEach((input) => {
                input.addEventListener('change', () => setMode(input.value));
            });
            if (defaultMode !== '') {
                setMode(defaultMode);
            }
        });

        const form = document.getElementById('public-form');
        const deviceInput = form ? form.querySelector('[name="device_hash"]') : null;
        if (deviceInput) {
            const storageKey = 'mediafeedback-device-id';
            let deviceId = '';
            try {
                deviceId = localStorage.getItem(storageKey) || '';
                if (!deviceId) {
                    deviceId = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : String(Date.now()) + '-' + Math.random().toString(36).slice(2);
                    localStorage.setItem(storageKey, deviceId);
                }
            } catch (error) {
                deviceId = navigator.userAgent + '|fallback-device';
            }

            const rawFingerprint = [navigator.userAgent, navigator.language, window.screen.width + 'x' + window.screen.height, deviceId].join('|');
            let hash = 0;
            for (let index = 0; index < rawFingerprint.length; index += 1) {
                hash = ((hash << 5) - hash) + rawFingerprint.charCodeAt(index);
                hash |= 0;
            }
            deviceInput.value = 'mf-' + Math.abs(hash).toString(16);
        }
    });

    (function () {
        const userAgent = navigator.userAgent || '';
        const isAndroid = /Android/i.test(userAgent);
        const isAppleMobile = /iP(ad|hone|od)/i.test(userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

        document.querySelectorAll('[data-file-input]').forEach((input) => {
            const inputId = input.id;
            const fileName = document.querySelector('[data-file-name][for="' + inputId + '"]');
            if (!input) {
                return;
            }

            const syncFileName = () => {
                if (!fileName) {
                    return;
                }
                const emptyText = input.getAttribute('data-empty-text') || 'Noch keine Datei gewählt.';
                fileName.textContent = input.files && input.files.length > 0
                    ? input.files[0].name
                    : emptyText;
            };

            input.addEventListener('change', syncFileName);
            syncFileName();
        });

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !navigator.mediaDevices.enumerateDevices) {
            document.querySelectorAll('[data-recorder-root]').forEach(function (root) {
                const status = root.querySelector('[data-role="status"]');
                const connectButton = root.querySelector('[data-role="connect"]');
                if (status) {
                    status.textContent = isAndroid || isAppleMobile
                        ? 'Direkte Browseraufnahme ist hier nicht verfügbar. Nutze bitte "Datei auswählen". Kamera oder Mikrofon des Geräts öffnen sich dann direkt.'
                        : 'Auf diesem Gerät werden Kamera oder Mikrofon vom Browser nicht unterstützt.';
                }
                if (connectButton) {
                    connectButton.disabled = true;
                }
            });
            return;
        }

        const canRecordInBrowser = typeof window.MediaRecorder === 'function' && typeof window.DataTransfer === 'function';

        const supportedMime = (kind) => {
            const options = kind === 'video'
                ? ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm', 'video/mp4']
                : ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg'];

            return options.find((option) => window.MediaRecorder && MediaRecorder.isTypeSupported(option)) || '';
        };

        const extensionFor = (kind, mimeType) => {
            if (mimeType.includes('mp4')) {
                return kind === 'video' ? 'mp4' : 'm4a';
            }
            if (mimeType.includes('ogg')) {
                return 'ogg';
            }
            return kind === 'video' ? 'webm' : 'weba';
        };

        const stopTracks = (stream) => {
            if (!stream) {
                return;
            }
            stream.getTracks().forEach((track) => track.stop());
        };

        const roots = document.querySelectorAll('[data-recorder-root]');
        roots.forEach((root) => {
            const kind = root.dataset.kind;
            const connectButton = root.querySelector('[data-role="connect"]');
            const startButton = root.querySelector('[data-role="start"]');
            const stopButton = root.querySelector('[data-role="stop"]');
            const resetButton = root.querySelector('[data-role="reset"]');
            const status = root.querySelector('[data-role="status"]');
            const preview = root.querySelector('[data-role="preview"]');
            const playback = root.querySelector('[data-role="playback"]');
            const audioPlayback = root.querySelector('[data-role="audio-playback"]');
            const fileInput = root.querySelector('[data-role="file-input"]');
            const videoSelect = root.querySelector('[data-role="video-source"]');
            const audioSelect = root.querySelector('[data-role="audio-source"]');
            const fileName = fileInput ? root.querySelector('[data-file-name][for="' + fileInput.id + '"]') : null;

            let stream = null;
            let recorder = null;
            let chunks = [];
            let lastObjectUrl = null;
            let hasTriedToConnect = false;
            let isConnecting = false;

            const waitingStatus = kind === 'video'
                ? 'Videoaufnahme vorbereiten'
                : 'Audioaufnahme vorbereiten';
            const connectingStatus = kind === 'video'
                ? 'Kamera und Mikrofon werden vorbereitet ...'
                : 'Mikrofon wird vorbereitet ...';
            const readyStatus = kind === 'video'
                ? 'Kamera und Mikrofon sind bereit. Du kannst jetzt aufnehmen.'
                : 'Mikrofon ist bereit. Du kannst jetzt aufnehmen.';
            const errorStatus = kind === 'video'
                ? 'Kamera oder Mikrofon konnten nicht vorbereitet werden. Bitte Freigabe und Geräte prüfen.'
                : 'Das Mikrofon konnte nicht vorbereitet werden. Bitte Freigabe und Gerät prüfen.';
            const fallbackStatus = isAndroid || isAppleMobile
                ? 'Direkte Browseraufnahme ist hier nicht verfügbar. Nutze bitte "Datei auswählen". Kamera oder Mikrofon des Geräts öffnen sich dann direkt.'
                : 'Direkte Browseraufnahme ist hier nicht verfügbar. Bitte Datei direkt über Kamera oder Mikrofon auswählen.';

            const setStatus = (message) => {
                if (status) {
                    status.textContent = message;
                }
            };

            const setButtons = (connected, recording) => {
                if (connectButton) {
                    connectButton.disabled = isConnecting;
                }
                if (startButton) {
                    startButton.disabled = !connected || recording || !canRecordInBrowser;
                }
                if (stopButton) {
                    stopButton.disabled = !recording || !canRecordInBrowser;
                }
                if (resetButton) {
                    resetButton.disabled = !connected && !(fileInput && fileInput.files.length);
                }
            };

            const resetPreview = () => {
                if (preview) {
                    preview.pause();
                    preview.srcObject = null;
                    preview.style.display = 'none';
                }
                if (playback) {
                    playback.pause();
                    playback.removeAttribute('src');
                    playback.load();
                    playback.style.display = 'none';
                }
                if (audioPlayback) {
                    audioPlayback.pause();
                    audioPlayback.removeAttribute('src');
                    audioPlayback.load();
                    audioPlayback.style.display = 'none';
                }
                if (lastObjectUrl) {
                    URL.revokeObjectURL(lastObjectUrl);
                    lastObjectUrl = null;
                }
            };

            const stopStream = () => {
                stopTracks(stream);
                stream = null;
            };

            const syncFileName = () => {
                if (!fileInput || !fileName) {
                    return;
                }
                const emptyText = fileInput.getAttribute('data-empty-text') || 'Noch keine Datei gewählt.';
                fileName.textContent = fileInput.files && fileInput.files.length > 0
                    ? fileInput.files[0].name
                    : emptyText;
            };

            const fillDeviceSelect = (select, devices, labelPrefix, previousValue) => {
                if (!select) {
                    return;
                }

                select.innerHTML = '';
                if (devices.length === 0) {
                    const emptyOption = document.createElement('option');
                    emptyOption.value = '';
                    emptyOption.textContent = 'Kein Gerät gefunden';
                    select.appendChild(emptyOption);
                    select.disabled = true;
                    return;
                }

                select.disabled = false;
                devices.forEach((device, index) => {
                    const option = document.createElement('option');
                    option.value = device.deviceId;
                    option.textContent = device.label || `${labelPrefix} ${index + 1}`;
                    select.appendChild(option);
                });

                const canRestore = previousValue && devices.some((device) => device.deviceId === previousValue);
                select.value = canRestore ? previousValue : devices[0].deviceId;
            };

            const populateDevices = async () => {
                const devices = await navigator.mediaDevices.enumerateDevices();
                const videoDevices = devices.filter((device) => device.kind === 'videoinput');
                const audioDevices = devices.filter((device) => device.kind === 'audioinput');

                fillDeviceSelect(videoSelect, videoDevices, 'Kamera', videoSelect ? videoSelect.value : '');
                fillDeviceSelect(audioSelect, audioDevices, 'Mikrofon', audioSelect ? audioSelect.value : '');
            };

            const requestPermission = async () => {
                const permissionStream = await navigator.mediaDevices.getUserMedia({
                    audio: true,
                    video: kind === 'video',
                });
                stopTracks(permissionStream);
            };

            const buildConstraints = (strictSelection) => {
                const audioConstraint = audioSelect && audioSelect.value
                    ? (strictSelection ? { deviceId: { exact: audioSelect.value } } : { deviceId: audioSelect.value })
                    : true;

                const videoConstraint = kind === 'video'
                    ? (videoSelect && videoSelect.value
                        ? Object.assign(
                            { width: { ideal: 1280 }, height: { ideal: 720 } },
                            strictSelection ? { deviceId: { exact: videoSelect.value } } : { deviceId: videoSelect.value }
                        )
                        : { width: { ideal: 1280 }, height: { ideal: 720 } })
                    : false;

                return {
                    audio: audioConstraint,
                    video: videoConstraint,
                };
            };

            const attachPreview = () => {
                if (preview && kind === 'video') {
                    preview.srcObject = stream;
                    preview.style.display = 'block';
                    preview.play().catch(() => {});
                }
            };

            const connect = async () => {
                if (isConnecting) {
                    return;
                }

                try {
                    isConnecting = true;
                    hasTriedToConnect = true;
                    if (connectButton) {
                        connectButton.disabled = true;
                    }
                    setStatus(connectingStatus);
                    stopStream();
                    resetPreview();

                    await requestPermission();
                    await populateDevices();

                    try {
                        stream = await navigator.mediaDevices.getUserMedia(buildConstraints(true));
                    } catch (strictError) {
                        console.warn('Selected device could not be opened strictly, falling back.', strictError);
                        stream = await navigator.mediaDevices.getUserMedia(buildConstraints(false));
                    }

                    attachPreview();
                    setButtons(true, false);
                    setStatus(readyStatus);
                } catch (error) {
                    stopStream();
                    resetPreview();
                    setButtons(false, false);
                    setStatus(errorStatus);
                    console.error(error);
                } finally {
                    isConnecting = false;
                    if (connectButton) {
                        connectButton.disabled = false;
                    }
                }
            };

            const reset = () => {
                stopStream();
                chunks = [];
                resetPreview();
                if (fileInput) {
                    fileInput.value = '';
                }
                setButtons(false, false);
                setStatus(waitingStatus);
            };

            const startRecording = () => {
                if (!stream || !window.MediaRecorder) {
                    return;
                }
                const mimeType = supportedMime(kind);
                recorder = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream);
                chunks = [];

                recorder.ondataavailable = (event) => {
                    if (event.data.size > 0) {
                        chunks.push(event.data);
                    }
                };

                recorder.onstop = () => {
                    const chosenMime = recorder.mimeType || mimeType || (kind === 'video' ? 'video/webm' : 'audio/webm');
                    const blob = new Blob(chunks, { type: chosenMime });
                    const file = new File([blob], `recording.${extensionFor(kind, chosenMime)}`, { type: chosenMime });
                    if (window.DataTransfer && fileInput) {
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        fileInput.files = dt.files;
                        fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    resetPreview();
                    lastObjectUrl = URL.createObjectURL(blob);
                    if (kind === 'video' && playback) {
                        playback.src = lastObjectUrl;
                        playback.style.display = 'block';
                    }
                    if (kind === 'audio' && audioPlayback) {
                        audioPlayback.src = lastObjectUrl;
                        audioPlayback.style.display = 'block';
                    }

                    syncFileName();
                    setButtons(true, false);
                    setStatus('Aufnahme gespeichert. Du kannst sie absenden oder erneut aufnehmen.');
                };

                recorder.start();
                setButtons(true, true);
                setStatus('Aufnahme läuft ...');
            };

            const stopRecording = () => {
                if (recorder && recorder.state !== 'inactive') {
                    recorder.stop();
                }
            };

            if (!canRecordInBrowser) {
                if (connectButton) {
                    connectButton.disabled = true;
                }
                if (startButton) {
                    startButton.disabled = true;
                }
                if (stopButton) {
                    stopButton.disabled = true;
                }
                setStatus(fallbackStatus);
            }

            if (connectButton) {
                connectButton.addEventListener('click', connect);
            }
            if (startButton) {
                startButton.addEventListener('click', startRecording);
            }
            if (stopButton) {
                stopButton.addEventListener('click', stopRecording);
            }
            if (resetButton) {
                resetButton.addEventListener('click', reset);
            }

            if (videoSelect) {
                videoSelect.addEventListener('change', connect);
            }
            if (audioSelect) {
                audioSelect.addEventListener('change', connect);
            }
            if (fileInput) {
                fileInput.addEventListener('change', () => {
                    if (fileInput.files.length > 0) {
                        stopStream();
                        resetPreview();
                        syncFileName();
                        setButtons(false, false);
                        setStatus('Datei ausgewählt. Die Aufnahme ist für das Absenden bereit.');
                    }
                });
                syncFileName();
            }

            root.__activateRecorder = () => {
                if (!canRecordInBrowser || hasTriedToConnect) {
                    return;
                }
                connect();
            };

            setStatus(waitingStatus);

            const panel = root.closest('[data-answer-mode-panel]');
            if (!panel || !panel.hidden) {
                root.__activateRecorder();
            }
        });

        document.querySelectorAll('[data-choice-grid]').forEach((grid) => {
            const syncSelection = () => {
                const feedback = grid.parentElement ? grid.parentElement.querySelector('[data-choice-feedback]') : null;
                const checkedInput = grid.querySelector('[data-choice-input]:checked');

                grid.querySelectorAll('[data-choice-card]').forEach((card) => {
                    const input = card.querySelector('[data-choice-input]');
                    card.classList.toggle('is-selected', !!input && input.checked);
                });

                if (!feedback) {
                    return;
                }

                if (!checkedInput) {
                    feedback.textContent = feedback.getAttribute('data-empty-text') || 'Noch nichts gewählt.';
                    feedback.classList.remove('is-active');
                    return;
                }

                const selectedLabel = checkedInput.getAttribute('data-choice-label') || checkedInput.value;
                feedback.textContent = `Gewählt: ${selectedLabel}`;
                feedback.classList.add('is-active');
            };

            grid.addEventListener('change', syncSelection);
            syncSelection();
        });
    }());
</script>
