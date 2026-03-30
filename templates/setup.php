<div class="card" style="max-width: 880px; margin: 2rem auto; padding: 2rem;">
    <div class="stack">
        <div>
            <div class="badge">Phase 1 · Einrichtung</div>
            <h1>mediaFeedback einrichten</h1>
            <p class="muted">Die Anwendung bekommt eine eigene SQLite-Datenbank und ein eigenes Setup für einen sauberen Neustart.</p>
        </div>

        <div class="grid two">
            <div class="card" style="padding: 1.25rem; box-shadow: none;">
                <h2>Systemprüfung</h2>
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
                    <div class="response-box">
                        <strong>Hinweis für spätere Handy-Tests</strong><br>
                        <span class="muted">Kamera- und Mikrofonaufnahmen funktionieren auf echten Mobilgeräten später nur über HTTPS oder lokal auf localhost.</span>
                    </div>
                </div>
            </div>

            <div class="card" style="padding: 1.25rem; box-shadow: none;">
                <h2>Installation</h2>
                <form class="stack" action="<?= V2_BASE_URL ?>/setup" method="post">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <div class="field">
                        <label for="app_name">Anwendungsname</label>
                        <input id="app_name" type="text" name="app_name" value="mediaFeedback" autocomplete="organization" required>
                    </div>
                    <div class="field">
                        <label for="admin_name">Admin-Name</label>
                        <input id="admin_name" type="text" name="admin_name" autocomplete="name" required>
                    </div>
                    <div class="field">
                        <label for="admin_email">Admin-E-Mail</label>
                        <input id="admin_email" type="email" name="admin_email" autocomplete="email" required>
                    </div>
                    <div class="field">
                        <label for="admin_password">Admin-Passwort</label>
                        <input id="admin_password" type="password" name="admin_password" autocomplete="new-password" minlength="8" required>
                    </div>
                    <button class="btn" type="submit">Setup ausführen</button>
                </form>
            </div>
        </div>
    </div>
</div>
