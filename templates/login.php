<div class="card" style="max-width: 520px; margin: 4rem auto; padding: 2rem;">
    <div class="stack">
        <div>
            <div class="badge">Login</div>
            <h1>Anmelden</h1>
            <p class="muted">Melde dich mit deinem vorhandenen oder neu angelegten Benutzer an.</p>
        </div>
        <form class="stack" action="<?= V2_BASE_URL ?>/login" method="post">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <div class="field">
                <label for="email">E-Mail</label>
                <input id="email" type="email" name="email" autocomplete="email" required>
            </div>
            <div class="field">
                <label for="password">Passwort</label>
                <input id="password" type="password" name="password" autocomplete="current-password" required>
            </div>
            <button class="btn" type="submit">Einloggen</button>
        </form>
    </div>
</div>
