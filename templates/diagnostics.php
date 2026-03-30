<?php
$limits = $results['limits'];
$httpsBadgeClass = $mediaCaptureReady ? 'status-live' : 'status-closed';
?>
<div class="split" style="margin-bottom: 1.5rem;">
        <div>
            <div class="badge">Betriebscheck</div>
            <h1>Systemdiagnose</h1>
            <p class="muted">Diese Seite hilft uns vor dem FTP- oder Handy-Test dabei, Server-, Upload- und Medienvoraussetzungen schnell zu prüfen.</p>
        </div>
    <div class="actions">
        <a class="btn secondary" href="<?= V2_BASE_URL ?>/dashboard">Zum Dashboard</a>
    </div>
</div>

<div class="grid three" style="margin-bottom: 1.5rem;">
    <div class="card" style="padding: 1.2rem;">
        <div class="muted">PHP</div>
        <div style="font-size: 1.5rem; font-weight: 800;"><?= e($results['php_version']) ?></div>
            <div class="meta"><?= $results['php'] ? 'Anforderung erfüllt' : 'Mindestens PHP 8.2 erforderlich' ?></div>
    </div>
    <div class="card" style="padding: 1.2rem;">
        <div class="muted">Medienaufnahme im Browser</div>
        <div style="font-size: 1.25rem; font-weight: 800;">
            <span class="badge <?= $httpsBadgeClass ?>"><?= $mediaCaptureReady ? 'Bereit' : 'Blockiert' ?></span>
        </div>
            <div class="meta"><?= $mediaCaptureReady ? ($isHttps ? 'HTTPS ist aktiv.' : 'Lokale Ausnahme für localhost ist aktiv.') : 'Ohne HTTPS blockieren mobile Browser Kamera und Mikrofon.' ?></div>
    </div>
    <div class="card" style="padding: 1.2rem;">
        <div class="muted">Upload-Limits</div>
        <div style="font-size: 1.25rem; font-weight: 800;"><?= bytes_human((int) $limits['upload_max_bytes']) ?> / <?= bytes_human((int) $limits['post_max_bytes']) ?></div>
            <div class="meta"><?= $limits['video_upload_ready'] ? 'Video-Uploads bis 40 MB sind serverseitig abgedeckt.' : 'Für Videoantworten bis 40 MB sollten upload_max_filesize und post_max_size erhöht werden.' ?></div>
    </div>
</div>

<?php if (!$mediaCaptureReady): ?>
    <div class="flash error">
        Dieser Aufruf läuft aktuell nicht unter HTTPS. Für echte Handy-Tests mit Kamera und Mikrofon brauchst du auf dem Webserver eine HTTPS-URL.
    </div>
<?php endif; ?>

<?php if (!$limits['video_upload_ready']): ?>
    <div class="flash error">
        Die aktuellen Upload-Limits reichen nicht sicher für Videoantworten bis 40 MB. Passe auf dem Zielserver mindestens <code>upload_max_filesize</code> und <code>post_max_size</code> an.
    </div>
<?php endif; ?>

<div class="grid two">
    <div class="card" style="padding: 1.25rem;">
        <h2>Systemstatus</h2>
        <div class="stack">
            <div class="response-box">
                <strong>PHP 8.2+</strong><br>
                <span class="muted"><?= $results['php'] ? 'Bereit' : 'Nicht erfüllt' ?></span>
            </div>
            <?php foreach ($results['extensions'] as $extension => $ok): ?>
                <div class="response-box"><strong><?= e($extension) ?></strong><br><span class="muted"><?= $ok ? 'OK' : 'Fehlt' ?></span></div>
            <?php endforeach; ?>
            <?php foreach ($results['directories'] as $directory => $ok): ?>
                <div class="response-box"><strong><?= e($directory) ?></strong><br><span class="muted"><?= $ok ? 'Beschreibbar' : 'Nicht beschreibbar' ?></span></div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card" style="padding: 1.25rem;">
        <h2>Medien- und Deployment-Check</h2>
        <div class="stack">
            <div class="response-box">
                <strong>Aktuelle Basis-URL</strong><br>
                <span class="muted"><?= e($publicBaseUrl) ?></span>
            </div>
            <div class="response-box">
                <strong>HTTPS</strong><br>
                <span class="muted"><?= $isHttps ? 'Aktiv' : 'Nicht aktiv' ?></span>
            </div>
            <div class="response-box">
                <strong>upload_max_filesize</strong><br>
                <span class="muted"><?= e((string) $limits['upload_max_filesize']) ?> (<?= bytes_human((int) $limits['upload_max_bytes']) ?>)</span>
            </div>
            <div class="response-box">
                <strong>post_max_size</strong><br>
                <span class="muted"><?= e((string) $limits['post_max_size']) ?> (<?= bytes_human((int) $limits['post_max_bytes']) ?>)</span>
            </div>
            <div class="response-box">
                <strong>max_file_uploads</strong><br>
                <span class="muted"><?= (int) $limits['max_file_uploads'] ?></span>
            </div>
            <div class="response-box">
                <strong>Empfehlung für den externen Test</strong><br>
                <span class="muted">HTTPS aktivieren, diese Diagnose-Seite zuerst aufrufen und dann ein Live-Feedback auf Android und iPhone mit Text, Audio und Video testen.</span>
            </div>
            <div class="response-box">
                <strong>URL-Modus</strong><br>
                <span class="muted">Die App nutzt standardmäßig portable <code>index.php</code>-Links. Schöne URLs ohne <code>index.php</code> sind optional und brauchen korrektes Rewrite ohne MultiViews-Konflikte.</span>
            </div>
        </div>
    </div>
</div>

<div class="card" style="padding: 1.25rem; margin-top: 1.5rem;">
    <h2>Handy-Testablauf</h2>
    <div class="stack">
        <div class="response-box">
                <strong>1. Server prüfen</strong><br>
                <span class="muted">Nach dem FTP-Upload zuerst diese Diagnose-Seite öffnen und auf HTTPS, Schreibrechte und Upload-Limits achten.</span>
            </div>
            <div class="response-box">
                <strong>2. Android-Test</strong><br>
                <span class="muted">Live-Feedback öffnen, Kamera/Mikrofon erlauben, Gerät wechseln, kurz aufnehmen, absenden und Ergebnis prüfen.</span>
            </div>
            <div class="response-box">
                <strong>3. iPhone-Test</strong><br>
                <span class="muted">Falls Safari keine direkte Aufnahme anbietet, über den Dateidialog direkt Kamera oder Mikrofon nutzen und den Fallback mit testen.</span>
            </div>
            <div class="response-box">
                <strong>4. Ergebnischeck</strong><br>
                <span class="muted">Danach Ergebnisseite sowie CSV- und JSON-Export öffnen und die neuen Antworten gegenprüfen.</span>
            </div>
    </div>
</div>
