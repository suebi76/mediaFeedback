<div class="split" style="margin-bottom: 1.5rem;">
    <div>
        <div class="badge">Administration</div>
        <h1>Benutzerverwaltung</h1>
        <p class="muted">Administratoren können neue Administratoren und Ersteller anlegen sowie vorhandene Konten verwalten.</p>
    </div>
    <div class="actions">
        <a class="btn secondary" href="<?= V2_BASE_URL ?>/dashboard">Zum Dashboard</a>
    </div>
</div>

<div class="grid two" style="align-items:start;">
    <div class="card" style="padding: 1.25rem;">
        <h2>Neuen Benutzer anlegen</h2>
        <form class="stack" action="<?= V2_BASE_URL ?>/users/create" method="post">
            <input type="hidden" name="_token" value="<?= e($userToken) ?>">
            <div class="field">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" autocomplete="name" required>
            </div>
            <div class="field">
                <label for="email">E-Mail</label>
                <input id="email" type="email" name="email" autocomplete="email" required>
            </div>
            <div class="field">
                <label for="password">Passwort</label>
                <input id="password" type="password" name="password" autocomplete="new-password" minlength="8" required>
            </div>
            <div class="field">
                <label for="password_confirmation">Passwort wiederholen</label>
                <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
            </div>
            <div class="field">
                <label for="role">Rolle</label>
                <select id="role" name="role">
                    <option value="creator">Ersteller</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button class="btn" type="submit">Benutzer anlegen</button>
        </form>
    </div>

    <div class="card" style="padding: 1.25rem;">
        <div class="split" style="margin-bottom: 1rem;">
            <h2>Vorhandene Benutzer</h2>
            <span class="muted"><?= count($users) ?> Konto/Konten</span>
        </div>

        <?php if (!$users): ?>
            <div class="response-box">Noch keine Benutzer vorhanden.</div>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>Benutzer</th>
                    <th>Rolle</th>
                    <th>Erstellt</th>
                    <th class="right">Aktion</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <strong><?= e($user['name']) ?></strong><br>
                            <span class="meta"><?= e($user['email']) ?></span>
                        </td>
                        <td><span class="badge"><?= e(role_label((string) $user['role'])) ?></span></td>
                        <td><?= e(date('d.m.Y', strtotime((string) $user['created_at']))) ?></td>
                        <td class="right">
                            <?php if ((int) $user['id'] === (int) $currentAdmin['id']): ?>
                                <span class="meta">Aktuelles Konto</span>
                            <?php else: ?>
                                <form action="<?= V2_BASE_URL ?>/users/delete" method="post" onsubmit="return confirm('Benutzer wirklich löschen?');" style="display:inline;">
                                    <input type="hidden" name="_token" value="<?= e($userToken) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                    <button class="btn danger small" type="submit">Löschen</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
