<div class="split split-center mb-8">
    <div>
        <h1>Dashboard</h1>
    </div>
    <div class="actions">
        <?php if (($currentUser['role'] ?? null) === 'admin'): ?>
            <a class="btn btn-secondary" href="<?= V2_BASE_URL ?>/users">Benutzerverwaltung</a>
        <?php endif; ?>
        <a class="btn btn-secondary" href="<?= V2_BASE_URL ?>/diagnostics">Systemdiagnose</a>
    </div>
</div>

<div class="card p-8">
    <div class="split split-center mb-6">
        <div>
            <h2 class="mb-1">Deine Feedbacks</h2>
            <p class="text-muted">Verwalte hier deine erstellten Fragenbögen und starte neue Umfragen.</p>
        </div>
        <div class="actions">
            <a class="btn btn-primary" href="<?= V2_BASE_URL ?>/feedback/create">Neues Feedback erstellen</a>
        </div>
    </div>

    <?php if (!$feedbacks): ?>
        <div class="card p-6 text-center text-muted" style="border-style: dashed; border-width: 2px;">
            Es wurden noch keine Feedbacks erstellt.
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
            <tr>
                <th>Titel</th>
                <th>Status</th>
                <th>Layout</th>
                <th>Antworten</th>
                <th class="text-right">Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($feedbacks as $feedback): ?>
                <tr class="hover-bg-slate-50 transition-colors">
                    <td>
                        <strong class="text-lg"><?= e($feedback['title']) ?></strong><br>
                        <a href="<?= V2_BASE_URL ?>/f?slug=<?= e($feedback['slug']) ?>" class="text-sm text-brand" target="_blank">/f?slug=<?= e($feedback['slug']) ?></a>
                    </td>
                    <td><span class="badge status-<?= e($feedback['status']) ?>"><?= e(status_label((string) $feedback['status'])) ?></span></td>
                    <td><span class="text-sm font-medium"><?= e(layout_label((string) $feedback['layout'])) ?></span></td>
                    <td><div class="badge badge-brand"><?= (int) $feedback['response_count'] ?></div></td>
                    <td class="text-right">
                        <div class="actions" style="justify-content:flex-end;">
                            <a class="btn btn-secondary btn-small" href="<?= V2_BASE_URL ?>/feedback/edit?id=<?= (int) $feedback['id'] ?>">Editor</a>
                            <a class="btn btn-secondary btn-small" href="<?= V2_BASE_URL ?>/results?id=<?= (int) $feedback['id'] ?>">Ergebnisse</a>
                            <a class="btn btn-secondary btn-small" href="<?= V2_BASE_URL ?>/f?slug=<?= e($feedback['slug']) ?>" target="_blank">Vorschau</a>
                            <button
                                class="btn btn-secondary btn-small"
                                type="button"
                                data-share-url="<?= e(public_feedback_url($feedback)) ?>"
                                data-share-title="<?= e($feedback['title']) ?>"
                                data-qr-url="<?= e(feedback_qr_url($feedback)) ?>"
                            >Teilen</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>
