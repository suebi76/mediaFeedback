<?php
$blockTone = static function (array $block): string {
    return $block['activity']->isQuestion() ? 'question' : 'content';
};

$blockToneLabel = static function (array $block): string {
    return $block['activity']->isQuestion() ? 'Frage' : 'Inhalt';
};

$blockIconName = static function (array $block): string {
    return activity_icon_name((string) ($block['activity_type'] ?? ''));
};

$feedbackGeneralSummary = static function (array $feedback): string {
    $parts = [];
    if (($feedback['title'] ?? '') !== '') {
        $parts[] = (string) $feedback['title'];
    }
    $parts[] = layout_label((string) ($feedback['layout'] ?? 'one-per-page'));
    return implode(' · ', $parts);
};

$feedbackFlowSummary = static function (array $settings): string {
    $parts = [];
    if (!empty($settings['estimated_time_minutes'])) {
        $parts[] = (int) $settings['estimated_time_minutes'] . ' Min.';
    }
    if (!empty($settings['intro_enabled'])) {
        $parts[] = 'mit Willkommensseite';
    }
    if (!empty($settings['progress_bar'])) {
        $parts[] = 'mit Fortschritt';
    }
    return $parts !== [] ? implode(' · ', $parts) : 'Ohne Zusatzangaben';
};

$feedbackRulesSummary = static function (array $settings): string {
    return !empty($settings['limit_one_response_per_device'])
        ? 'Eine Antwort pro Gerät'
        : 'Keine zusätzliche Teilnahmebegrenzung';
};

$statusDisplay = [];
?>

<div class="editor-shell">
    <a href="<?= V2_BASE_URL ?>/dashboard" class="editor-back-link">&larr; Zurück zur Übersicht</a>
    <section class="card editor-header">
        <div class="editor-header-main">
            <div class="editor-header-copy">
                <h1><?= e($feedback['title']) ?></h1>
                <?php if (!empty($feedback['description'])): ?>
                    <p class="editor-header-lead"><?= e((string) $feedback['description']) ?></p>
                <?php endif; ?>
            </div>
            <div class="actions editor-header-actions">
                <button class="btn primary small" type="button" data-editor-modal-open="editor-feedback-modal"><?= ui_icon('settings') ?><span>Einstellungen</span></button>
                <button class="btn secondary small" type="button" data-editor-modal-open="editor-status-modal"><?= ui_icon('publish') ?><span>Live & Status</span></button>
                <a class="btn secondary small" href="<?= V2_BASE_URL ?>/f?slug=<?= e($feedback['slug']) ?>" target="_blank"><?= ui_icon('preview') ?><span>Vorschau</span></a>
                <button
                    class="btn secondary small"
                    type="button"
                    data-share-url="<?= e(public_feedback_url($feedback)) ?>"
                    data-share-title="<?= e($feedback['title']) ?>"
                    data-qr-url="<?= e(feedback_qr_url($feedback)) ?>"
                ><?= ui_icon('share') ?><span>Teilen</span></button>
            </div>
        </div>
        <div id="editor-unsaved-warning" class="flash warning editor-unsaved-warning" hidden>
            <div class="split">
                <div>
                    <strong>Ungespeicherte Änderungen</strong>
                    <div class="meta" id="editor-unsaved-warning-text">Speichern erforderlich</div>
                </div>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <span class="dirty-save-hint" id="editor-unsaved-warning-count">0 Bereiche offen</span>
                    <button type="button" class="btn primary small" id="editor-unsaved-save-btn">Alle speichern</button>
                </div>
            </div>
        </div>
    </section>

    <div class="editor-layout">


        <main class="editor-main stack">

<?php foreach ($pages as $pageNumber => $blocks): ?>
    <?php $pageSummary = $pageSummaries[$pageNumber] ?? ['block_count' => count($blocks), 'question_count' => 0, 'content_count' => 0, 'preview' => '']; ?>
    <section id="page-<?= (int) $pageNumber ?>" class="card editor-page-card page-group" data-editor-page>
        <div class="editor-page-card-head">
            <div>
                <div class="badge">Seite <?= (int) $pageNumber + 1 ?></div>
            </div>
            <div class="actions">
                <?php if ($pageNumber > 0): ?>
                    <form action="<?= V2_BASE_URL ?>/activity/page-merge" method="post" data-discard-check>
                        <input type="hidden" name="_token" value="<?= e($editorToken) ?>">
                        <input type="hidden" name="feedback_id" value="<?= (int) $feedback['id'] ?>">
                        <input type="hidden" name="page_number" value="<?= (int) $pageNumber ?>">
                        <button class="btn secondary small" type="submit">Mit voriger Seite verbinden</button>
                    </form>
                <?php endif; ?>
                <form action="<?= V2_BASE_URL ?>/activity/reorder" method="post" data-reorder-form style="display: none;">
                    <input type="hidden" name="_token" value="<?= e($editorToken) ?>">
                    <input type="hidden" name="feedback_id" value="<?= (int) $feedback['id'] ?>">
                    <input type="hidden" name="order_json" value="">
                </form>
            </div>
        </div>
        <?php if (!$blocks): ?>
            <div class="response-box">Diese Seite ist noch leer. Du kannst links den ersten Inhalt oder die erste Frage dafür anlegen.</div>
        <?php endif; ?>

        <div class="stack" data-sortable-group>
            <button type="button" title="Baustein hier einfügen" class="editor-inserter-zone editor-inserter-zone-top" onclick="openAddBlockModal(<?= (int) $pageNumber ?>, 0)"><span class="editor-inserter-btn"><?= ui_icon('add') ?></span></button>
            <div data-sortable-page class="stack">
        <?php foreach ($blocks as $blockIndex => $block): ?>
            <div class="editor-sortable-wrapper" data-sortable-item data-block-id="<?= (int) $block['id'] ?>">
                <article class="card activity-card activity-card-<?= e($blockTone($block)) ?>">
                <div class="activity-card-head">
                        <div class="activity-card-title">
                            <strong class="editor-title-with-icon activity-card-heading"><?= ui_icon($blockIconName($block), 'ui-icon activity-type-icon') ?><span><?= e($block['activity']->getName()) ?></span></strong>
                            <div class="meta activity-card-meta">
                                <span class="activity-tone-pill"><?= e($blockToneLabel($block)) ?></span>
                                <span>Pos. <?= (int) $block['sort_order'] + 1 ?></span>
                                <?php if (!empty($block['data']['required'])): ?>
                                    <span>Erforderlich</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <button type="button" class="btn secondary small drag-handle" style="cursor: grab;" title="Gedrückt halten, um den Baustein zu verschieben">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ui-icon" style="margin-right: 0.25rem;"><polyline points="5 9 2 12 5 15"></polyline><polyline points="9 5 12 2 15 5"></polyline><polyline points="19 9 22 12 19 15"></polyline><polyline points="9 19 12 22 15 19"></polyline><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>
                            <span>Verschieben</span>
                        </button>
                        <details class="activity-action-menu">
                            <summary class="btn secondary small"><?= ui_icon('more') ?><span>Struktur</span></summary>
                        <div class="activity-action-menu-panel">
                            <form action="<?= V2_BASE_URL ?>/activity/duplicate" method="post" data-discard-check>
                                <input type="hidden" name="_token" value="<?= e($editorToken) ?>">
                                <input type="hidden" name="block_id" value="<?= (int) $block['id'] ?>">
                                <button class="btn secondary small" type="submit">Kopieren</button>
                            </form>
                            <?php if ($pageNumber > 0): ?>
                                <form action="<?= V2_BASE_URL ?>/activity/move-page" method="post" data-discard-check>
                                    <input type="hidden" name="_token" value="<?= e($editorToken) ?>">
                                    <input type="hidden" name="block_id" value="<?= (int) $block['id'] ?>">
                                    <input type="hidden" name="direction" value="previous">
                                    <button class="btn secondary small" type="submit">Auf vorige Seite</button>
                                </form>
                            <?php endif; ?>
                            <form action="<?= V2_BASE_URL ?>/activity/move-page" method="post" data-discard-check>
                                <input type="hidden" name="_token" value="<?= e($editorToken) ?>">
                                <input type="hidden" name="block_id" value="<?= (int) $block['id'] ?>">
                                <input type="hidden" name="direction" value="next">
                                <button class="btn secondary small" type="submit">Auf nächste Seite</button>
                            </form>
                            <?php if ($blockIndex > 0): ?>
                                <form action="<?= V2_BASE_URL ?>/activity/split-at-block" method="post" data-discard-check>
                                    <input type="hidden" name="_token" value="<?= e($editorToken) ?>">
                                    <input type="hidden" name="block_id" value="<?= (int) $block['id'] ?>">
                                    <button class="btn secondary small" type="submit">Ab hier neue Seite</button>
                                </form>
                            <?php endif; ?>
                            <form action="<?= V2_BASE_URL ?>/activity/move" method="post" data-discard-check>
                                <input type="hidden" name="_token" value="<?= e($editorToken) ?>">
                                <input type="hidden" name="block_id" value="<?= (int) $block['id'] ?>">
                                <input type="hidden" name="direction" value="up">
                                <button class="btn secondary small" type="submit">Eine Position nach oben</button>
                            </form>
                            <form action="<?= V2_BASE_URL ?>/activity/move" method="post" data-discard-check>
                                <input type="hidden" name="_token" value="<?= e($editorToken) ?>">
                                <input type="hidden" name="block_id" value="<?= (int) $block['id'] ?>">
                                <input type="hidden" name="direction" value="down">
                                <button class="btn secondary small" type="submit">Eine Position nach unten</button>
                            </form>
                            <form action="<?= V2_BASE_URL ?>/activity/delete" method="post" onsubmit="return confirm('Baustein wirklich löschen?');" data-discard-check>
                                <input type="hidden" name="_token" value="<?= e($editorToken) ?>">
                                <input type="hidden" name="block_id" value="<?= (int) $block['id'] ?>">
                                <button class="btn danger small" type="submit">Löschen</button>
                            </form>
                        </div>
                    </details>
                        <button type="button" class="btn danger small btn-edit-toggle" onclick="const d = this.closest('.activity-card').querySelector('.activity-editor-details'); d.open = !d.open;">
                            <?= ui_icon('edit') ?><span>Bearbeiten</span>
                        </button>
                    </div>
                </div>
                <details class="activity-editor-details">
                    <summary class="activity-editor-summary" style="cursor: pointer;" title="Klicken, um den Editor auf- oder zuzuklappen">
                        <div class="activity-card-summary editor-summary-card">
                            <?= $block['activity']->renderEditorSummary($block['data']) ?>
                        </div>
                        <div class="activity-editor-summary-meta">
                            <span class="dirty-indicator" data-dirty-indicator hidden>Ungespeicherte Änderungen</span>
                        </div>
                    </summary>
                    <div class="activity-editor-body">
                        <div class="split activity-card-note">
                            <div class="meta">Direkt im Kontext bearbeiten und anschließend für diesen Baustein speichern.</div>
                        </div>
                        <form class="stack" action="<?= V2_BASE_URL ?>/activity/update" method="post" enctype="multipart/form-data" data-track-dirty data-dirty-label="<?= e($block['activity']->getName()) ?> auf Seite <?= (int) $pageNumber + 1 ?>" data-save-form>
                            <input type="hidden" name="_token" value="<?= e($editorToken) ?>">
                            <input type="hidden" name="block_id" value="<?= (int) $block['id'] ?>">
                            <?= $block['activity']->renderEditorForm($block['data']) ?>
                            <div class="editor-save-row">
                                <div class="meta">Speichert nur diesen Baustein, nicht die ganze Seite.</div>
                                <button class="btn" type="submit">Baustein speichern</button>
                            </div>
                        </form>
                    </div>
                </details>
                </article>
                <button type="button" title="Baustein hier einfügen" class="editor-inserter-zone" onclick="openAddBlockModal(<?= (int) $pageNumber ?>, <?= (int) $block['sort_order'] + 1 ?>)"><span class="editor-inserter-btn"><?= ui_icon('add') ?></span></button>
            </div>
        <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endforeach; ?>

            <section class="editor-page-add-card" onclick="openAddBlockModal('new', null)">
                <div class="editor-page-add-content">
                    <?= ui_icon('add', 'ui-icon') ?>
                    <strong>Neue Seite hinzufügen</strong>
                    <span class="meta">Erstelle eine leere Seite am Ende des Feedbacks</span>
                </div>
            </section>
        </main>
    </div>
</div>

<div id="editor-add-modal" class="editor-modal-backdrop" hidden>
    <div class="card editor-modal-dialog editor-modal-dialog-compact stack" role="dialog" aria-modal="true" aria-labelledby="editor-add-title">
        <div class="split">
            <div>
                <div class="badge">Neu</div>
                <h2 id="editor-add-title" class="editor-modal-title-row"><?= ui_icon('add', 'ui-icon editor-modal-heading-icon') ?><span>Baustein hinzufügen</span></h2>
            </div>
            <button class="btn secondary small" type="button" data-editor-modal-close>Abbrechen</button>
        </div>
        <form id="editor-add-form" class="stack" action="<?= V2_BASE_URL ?>/activity/add" method="post" data-discard-check>
            <input type="hidden" name="_token" value="<?= e($editorToken) ?>">
            <input type="hidden" name="feedback_id" value="<?= (int) $feedback['id'] ?>">
            <input type="hidden" name="page_number" id="add-modal-page" value="0">
            <input type="hidden" name="page_target_choice" id="add-modal-target" value="0">
            <input type="hidden" name="insert_at" id="add-modal-insert" value="">
            
            <div class="field">
                <label for="editor-add-type">Baustein wählen</label>
                <select id="editor-add-type" name="type" required>
                    <option value="" disabled selected>Bitte wählen...</option>
                    <optgroup label="Inhalte">
                        <?php foreach ($activities as $type => $activity): ?>
                            <?php if (!$activity->isQuestion()): ?>
                                <option value="<?= e($type) ?>"><?= e($activity->getName()) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Fragen">
                        <?php foreach ($activities as $type => $activity): ?>
                            <?php if ($activity->isQuestion()): ?>
                                <option value="<?= e($type) ?>"><?= e($activity->getName()) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>
            <div class="actions">
                <button class="btn" type="submit">Hinzufügen</button>
            </div>
        </form>
    </div>
</div>

<div id="editor-feedback-modal" class="editor-modal-backdrop" hidden>
    <div class="card editor-modal-dialog stack" role="dialog" aria-modal="true" aria-labelledby="editor-feedback-title">
        <div class="split">
            <div>
                <div class="badge">Einstellungen</div>
                <h2 id="editor-feedback-title" class="editor-modal-title-row"><?= ui_icon('settings', 'ui-icon editor-modal-heading-icon') ?><span>Grundeinstellungen</span></h2>
            </div>
            <button class="btn secondary small" type="button" data-editor-modal-close>Schließen</button>
        </div>
        <form class="stack" action="<?= V2_BASE_URL ?>/feedback/update" method="post" data-track-dirty data-dirty-label="Grundeinstellungen" data-save-form>
            <input type="hidden" name="_token" value="<?= e($editorToken) ?>">
            <input type="hidden" name="feedback_id" value="<?= (int) $feedback['id'] ?>">
            <details class="editor-section editor-config-group" open>
                <summary class="editor-section-summary">
                    <div>
                        <h3>Allgemein</h3>
                        <div class="meta"><?= e($feedbackGeneralSummary($feedback)) ?></div>
                    </div>
                    <span class="editor-section-toggle" data-open-label="Weniger" data-closed-label="Mehr">Weniger</span>
                </summary>
                <div class="editor-section-body">
                    <div class="stack">
                        <div class="field">
                            <label for="feedback-title">Titel</label>
                            <input id="feedback-title" type="text" name="title" value="<?= e($feedback['title']) ?>">
                        </div>
                        <div class="field">
                            <label for="feedback-layout">Ablaufdarstellung</label>
                            <select id="feedback-layout" name="layout">
                                <option value="one-per-page" <?= selected($feedback['layout'], 'one-per-page') ?>>Seite für Seite</option>
                                <option value="classic" <?= selected($feedback['layout'], 'classic') ?>>Klassisch</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="feedback-description">Beschreibung</label>
                            <textarea id="feedback-description" name="description" rows="4"><?= e($feedback['description']) ?></textarea>
                        </div>
                    </div>
                </div>
            </details>
            <details class="editor-section editor-config-group">
                <summary class="editor-section-summary">
                    <div>
                        <h3>Ablauf</h3>
                        <div class="meta"><?= e($feedbackFlowSummary($settings)) ?></div>
                    </div>
                    <span class="editor-section-toggle" data-open-label="Weniger" data-closed-label="Mehr">Mehr</span>
                </summary>
                <div class="editor-section-body">
                    <div class="stack">
                        <div class="field">
                            <label for="estimated-time">Geschätzte Dauer in Minuten</label>
                            <input id="estimated-time" type="number" min="1" name="estimated_time_minutes" value="<?= e(($settings['estimated_time_minutes'] ?? null) !== null ? (string) $settings['estimated_time_minutes'] : '') ?>">
                        </div>
                        <label class="editor-check">
                            <input type="hidden" name="intro_enabled" value="0">
                            <input type="checkbox" name="intro_enabled" value="1" <?= checked(!empty($settings['intro_enabled'])) ?>>
                            <span>Willkommensseite anzeigen</span>
                        </label>
                        <label class="editor-check">
                            <input type="hidden" name="progress_bar" value="0">
                            <input type="checkbox" name="progress_bar" value="1" <?= checked(!empty($settings['progress_bar'])) ?>>
                            <span>Fortschrittsanzeige im Seitenmodus anzeigen</span>
                        </label>
                        <div class="field">
                            <label for="intro-text">Einleitungstext</label>
                            <textarea id="intro-text" name="intro_text" rows="5"><?= e((string) ($settings['intro_text'] ?? '')) ?></textarea>
                        </div>
                    </div>
                </div>
            </details>
            <details class="editor-section editor-config-group">
                <summary class="editor-section-summary">
                    <div>
                        <h3>Teilnahme-Regeln</h3>
                        <div class="meta"><?= e($feedbackRulesSummary($settings)) ?></div>
                    </div>
                    <span class="editor-section-toggle" data-open-label="Weniger" data-closed-label="Mehr">Mehr</span>
                </summary>
                <div class="editor-section-body">
                    <div class="stack">
                        <label class="editor-check">
                            <input type="hidden" name="limit_one_response_per_device" value="0">
                            <input type="checkbox" name="limit_one_response_per_device" value="1" <?= checked(!empty($settings['limit_one_response_per_device'])) ?>>
                            <span>Nur eine Antwort pro Gerät zulassen</span>
                        </label>
                    </div>
                </div>
            </details>
            <div class="editor-modal-actions">
                <span class="dirty-indicator" data-dirty-indicator hidden>Ungespeicherte Änderungen</span>
                <button class="btn" type="submit">Grundeinstellungen speichern</button>
            </div>
        </form>
        <hr class="editor-divider" style="margin: 2rem 0; border: none; border-top: 1px solid var(--border-light);">
        <div class="split">
            <div>
                <strong>Feedback löschen</strong>
                <div class="meta" style="margin-top: 0.25rem;">Entfernt dieses Projekt und alle zugehörigen Antworten unwiderruflich.</div>
            </div>
            <form action="<?= V2_BASE_URL ?>/feedback/delete" method="post" onsubmit="return confirm('Feedback wirklich löschen?');" data-discard-check>
                <input type="hidden" name="_token" value="<?= e($editorToken) ?>">
                <input type="hidden" name="feedback_id" value="<?= (int) $feedback['id'] ?>">
                <button class="btn danger small" type="submit"><?= ui_icon('delete') ?><span>Löschen</span></button>
            </form>
        </div>
    </div>
</div>

<div id="editor-status-modal" class="editor-modal-backdrop" hidden>
    <div class="card editor-modal-dialog editor-modal-dialog-compact stack" role="dialog" aria-modal="true" aria-labelledby="editor-status-title">
        <div class="split">
            <div>
                <div class="badge">Status</div>
                <h2 id="editor-status-title" class="editor-modal-title-row"><?= ui_icon('publish', 'ui-icon editor-modal-heading-icon') ?><span>Veröffentlichung</span></h2>
            </div>
        </div>
        <form class="stack" action="<?= V2_BASE_URL ?>/feedback/status" method="post" data-track-dirty data-dirty-label="Veröffentlichung" data-save-form>
            <input type="hidden" name="_token" value="<?= e($editorToken) ?>">
            <input type="hidden" name="feedback_id" value="<?= (int) $feedback['id'] ?>">
            <div class="field">
                <label for="feedback-status">Sichtbarkeit</label>
                <select id="feedback-status" name="status">
                    <option value="draft" <?= selected($feedback['status'], 'draft') ?>>Entwurf</option>
                    <option value="live" <?= selected($feedback['status'], 'live') ?>>Live</option>
                    <option value="closed" <?= selected($feedback['status'], 'closed') ?>>Geschlossen</option>
                </select>
            </div>
            <div class="editor-modal-actions">
                <span class="dirty-indicator" data-dirty-indicator hidden>Ungespeicherte Änderungen</span>
                <button class="btn secondary" type="submit">Sichtbarkeit speichern</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= e(V2_WEB_ROOT . '/assets/vendor/tinymce/tinymce.min.js') ?>"></script>
<script src="<?= e(V2_WEB_ROOT . '/assets/sortable.min.js') ?>"></script>
<script>
    (function () {
        const trackedForms = Array.from(document.querySelectorAll('[data-track-dirty]'));
        const warningBox = document.getElementById('editor-unsaved-warning');
        const warningText = document.getElementById('editor-unsaved-warning-text');
        const warningCount = document.getElementById('editor-unsaved-warning-count');
        let allowNavigation = false;

        const serializeForm = (form) => {
            const values = [];
            Array.from(form.elements).forEach((element, index) => {
                if (!element.name || element.disabled) {
                    return;
                }
                const type = (element.type || '').toLowerCase();
                if (type === 'submit' || type === 'button' || type === 'reset' || type === 'image') {
                    return;
                }
                if (type === 'file') {
                    const files = Array.from(element.files || []).map((file) => [file.name, file.size, file.lastModified].join(':')).join('|');
                    values.push([index, element.name, type, files].join('='));
                    return;
                }
                if (type === 'checkbox' || type === 'radio') {
                    values.push([index, element.name, type, element.checked ? '1' : '0', element.value].join('='));
                    return;
                }
                values.push([index, element.name, type || element.tagName.toLowerCase(), element.value].join('='));
            });
            return values.join('&');
        };

        const trackedState = trackedForms.map((form) => ({
            form,
            initial: serializeForm(form),
            dirty: false,
            label: form.dataset.dirtyLabel || 'Editorbereich'
        }));

        const dirtyLabels = () => trackedState.filter((entry) => entry.dirty).map((entry) => entry.label);

        const syncWarningBox = () => {
            const labels = dirtyLabels();
            if (labels.length === 0) {
                warningBox.hidden = true;
                warningBox.setAttribute('hidden', '');
                warningText.textContent = 'Bitte speichere deine Änderungen, bevor du den Editor verlässt oder andere Aktionen ausführst.';
                warningCount.textContent = '0 Bereiche offen';
                return;
            }

            warningBox.hidden = false;
            warningBox.removeAttribute('hidden');
            warningCount.textContent = labels.length === 1 ? '1 Bereich offen' : labels.length + ' Bereiche offen';
            warningText.textContent = labels.length === 1
                ? 'Noch nicht gespeichert: ' + labels[0] + '.'
                : 'Noch nicht gespeichert: ' + labels.slice(0, 3).join(', ') + (labels.length > 3 ? ' und weitere Bereiche.' : '.');
        };

        const updateEntry = (entry) => {
            entry.dirty = serializeForm(entry.form) !== entry.initial;
            const card = entry.form.closest('.card, .activity-card');
            if (card) {
                card.classList.toggle('dirty-form', entry.dirty);
            }
            const indicator = card ? card.querySelector('[data-dirty-indicator]') : null;
            if (indicator) {
                indicator.hidden = !entry.dirty;
            }
            syncWarningBox();
        };

        const hasDirtyForms = () => trackedState.some((entry) => entry.dirty);
        const dirtyMessage = () => {
            const labels = dirtyLabels();
            if (labels.length === 0) {
                return '';
            }
            return 'Es gibt ungespeicherte Änderungen in ' + labels.slice(0, 3).join(', ') + (labels.length > 3 ? ' und weiteren Bereichen. Diese Aktion würde die Änderungen verwerfen. Trotzdem fortfahren?': '. Diese Aktion würde die Änderungen verwerfen. Trotzdem fortfahren?');
        };

        const saveBtn = document.getElementById('editor-unsaved-save-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', async () => {
                const dirtyForms = trackedState.filter(entry => entry.dirty).map(e => e.form);
                if (dirtyForms.length === 0) return;
                
                const originalText = saveBtn.textContent;
                saveBtn.textContent = 'Speichert...';
                saveBtn.disabled = true;
                
                try {
                    for (const form of dirtyForms) {
                        const formData = new FormData(form);
                        await fetch(form.action, {
                            method: form.method || 'POST',
                            body: formData,
                            redirect: 'follow'
                        });
                    }
                    window.location.reload();
                } catch (error) {
                    console.error('Bulk save failed', error);
                    saveBtn.textContent = 'Fehler';
                    setTimeout(() => {
                        saveBtn.textContent = originalText;
                        saveBtn.disabled = false;
                    }, 2000);
                }
            });
        }

        trackedState.forEach((entry) => {
            const refresh = () => updateEntry(entry);
            entry.form.addEventListener('input', refresh);
            entry.form.addEventListener('change', refresh);
            entry.form.addEventListener('submit', () => {
                allowNavigation = true;
            });
            updateEntry(entry);
        });

        const editorModals = Array.from(document.querySelectorAll('.editor-modal-backdrop'));
        const closeEditorModal = (modal) => {
            modal.hidden = true;
            modal.classList.remove('open');
        };
        const openEditorModal = (modal) => {
            modal.hidden = false;
            modal.classList.add('open');
        };

        document.querySelectorAll('[data-editor-modal-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const modalId = button.getAttribute('data-editor-modal-open') || '';
                const modal = document.getElementById(modalId);
                if (!modal) {
                    return;
                }
                openEditorModal(modal);
            });
        });

        document.querySelectorAll('[data-editor-modal-close]').forEach((button) => {
            button.addEventListener('click', () => {
                const modal = button.closest('.editor-modal-backdrop');
                if (modal) {
                    closeEditorModal(modal);
                }
            });
        });

        editorModals.forEach((modal) => {
            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeEditorModal(modal);
                }
            });
        });

        const syncSectionToggles = (scope) => {
            scope.querySelectorAll('.editor-section').forEach((section) => {
                const toggle = section.querySelector('.editor-section-toggle');
                if (!toggle) {
                    return;
                }
                toggle.textContent = section.open
                    ? (toggle.dataset.openLabel || 'Weniger')
                    : (toggle.dataset.closedLabel || 'Mehr');
            });
        };

        syncSectionToggles(document);
        document.querySelectorAll('.editor-section').forEach((section) => {
            section.addEventListener('toggle', () => syncSectionToggles(document));
        });

        window.addEventListener('beforeunload', (event) => {
            if (!allowNavigation && hasDirtyForms()) {
                event.preventDefault();
                event.returnValue = '';
            }
        });

        document.querySelectorAll('a[href]').forEach((link) => {
            const href = link.getAttribute('href') || '';
            if (link.target === '_blank' || link.hasAttribute('download') || href.startsWith('#')) {
                return;
            }
            link.addEventListener('click', (event) => {
                if (!hasDirtyForms()) {
                    return;
                }
                if (!window.confirm(dirtyMessage())) {
                    event.preventDefault();
                    return;
                }
                allowNavigation = true;
            });
        });

        document.querySelectorAll('[data-reorder-form]').forEach((form) => {
            form.addEventListener('submit', () => {
                allowNavigation = true;
            });
        });

        document.querySelectorAll('form:not([data-save-form]):not([data-reorder-form])').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!hasDirtyForms()) {
                    allowNavigation = true;
                    return;
                }
                if (!window.confirm(dirtyMessage())) {
                    event.preventDefault();
                    return;
                }
                allowNavigation = true;
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }
            const openModal = editorModals.find((modal) => modal.classList.contains('open'));
            if (openModal) {
                closeEditorModal(openModal);
            }
        });

        document.querySelectorAll('textarea[data-autosize]').forEach((textarea) => {
            const resize = () => {
                if (textarea.hasAttribute('data-richtext')) {
                    return;
                }
                textarea.style.height = 'auto';
                textarea.style.height = Math.max(textarea.scrollHeight, 140) + 'px';
            };
            textarea.addEventListener('input', resize);
            resize();
        });

        if (window.tinymce) {
            document.querySelectorAll('textarea[data-richtext]').forEach((textarea, index) => {
                if (!textarea.id) {
                    textarea.id = 'richtext-' + (index + 1);
                }

                window.tinymce.init({
                    selector: '#' + textarea.id,
                    base_url: '<?= e(V2_WEB_ROOT . '/assets/vendor/tinymce') ?>',
                    suffix: '.min',
                    license_key: 'gpl',
                    menubar: false,
                    statusbar: false,
                    min_height: 340,
                    resize: true,
                    promotion: false,
                    plugins: 'autolink advlist lists link table autoresize',
                    toolbar: 'undo redo | blocks | bold italic underline | forecolor backcolor | alignleft aligncenter alignright | bullist numlist | link table | removeformat',
                    content_css: 'default',
                    skin: 'oxide',
                    browser_spellcheck: true,
                    contextmenu: 'link table',
                    convert_urls: false,
                    setup: (editor) => {
                        const sync = () => {
                            editor.save();
                            textarea.dispatchEvent(new Event('input', { bubbles: true }));
                            textarea.dispatchEvent(new Event('change', { bubbles: true }));
                        };

                        editor.on('input change undo redo SetContent', sync);
                    }
                });
            });
        }

        document.querySelectorAll('[data-save-form]').forEach((form) => {
            form.addEventListener('submit', () => {
                if (window.tinymce) {
                    window.tinymce.triggerSave();
                }
            });
        });

        document.querySelectorAll('[data-file-trigger]').forEach((button) => {
            const inputId = button.getAttribute('data-file-trigger');
            const input = document.getElementById(inputId);
            const fileName = document.querySelector('[data-file-name][for="' + inputId + '"]');
            if (!input) {
                return;
            }

            const syncFileName = () => {
                if (!fileName) {
                    return;
                }
                fileName.textContent = input.files && input.files.length > 0
                    ? input.files[0].name
                    : 'Noch keine Datei gewählt.';
            };

            button.addEventListener('click', () => {
                input.click();
            });
            input.addEventListener('change', syncFileName);
            syncFileName();
        });

        (function () {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !navigator.mediaDevices.enumerateDevices) {
                document.querySelectorAll('[data-editor-recorder]').forEach(function (root) {
                    const status = root.querySelector('[data-role="status"]');
                    const connectButton = root.querySelector('[data-role="connect"]');
                    if (status) {
                        status.textContent = 'Auf diesem Gerät werden Kamera oder Mikrofon vom Browser nicht unterstützt.';
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

            const roots = document.querySelectorAll('[data-editor-recorder]');
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
                const fileInput = root.parentElement.querySelector('[data-role="file-input"]');
                const videoSelect = root.querySelector('[data-role="video-source"]');
                const audioSelect = root.querySelector('[data-role="audio-source"]');
                const fileName = fileInput ? root.parentElement.querySelector('[data-file-name][for="' + fileInput.id + '"]') : null;

                let stream = null;
                let recorder = null;
                let chunks = [];
                let lastObjectUrl = null;

                const setStatus = (message) => {
                    if (status) {
                        status.textContent = message;
                    }
                };

                const setButtons = (connected, recording) => {
                    if (startButton) {
                        startButton.disabled = !connected || recording || !canRecordInBrowser;
                        startButton.textContent = recording ? 'Aufnahme gestartet' : 'Aufnahme starten';
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

                const syncFileName = () => {
                    if (!fileInput || !fileName) {
                        return;
                    }
                    fileName.textContent = fileInput.files && fileInput.files.length > 0
                        ? fileInput.files[0].name
                        : 'Noch keine Datei gewählt.';
                };

                const connect = async () => {
                    try {
                        if (connectButton) {
                            connectButton.disabled = true;
                        }
                        setStatus('Geräte werden verbunden ...');
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
                        setStatus('Geräte bereit. Du kannst aufnehmen.');
                    } catch (error) {
                        stopStream();
                        resetPreview();
                        setButtons(false, false);
                        setStatus('Geräte konnten nicht initialisiert werden. Bitte Browserfreigabe und Geräte prüfen.');
                        console.error(error);
                    } finally {
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
                    syncFileName();
                    setButtons(false, false);
                    setStatus('Aufnahme verworfen.');
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
                        setStatus('Aufnahme gespeichert. Bitte Baustein jetzt speichern.');
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
                    setStatus('Direkte Browseraufnahme ist hier nicht verfügbar. Bitte Datei direkt auswählen.');
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
                        stopStream();
                        resetPreview();
                        syncFileName();
                        setButtons(false, false);
                        if (fileInput.files.length > 0) {
                            setStatus('Datei ausgewählt. Bitte Baustein jetzt speichern.');
                        }
                    });
                    syncFileName();
                }
            });
        }());

        const pages = document.querySelectorAll('[data-sortable-page]');
        const reorderForms = document.querySelectorAll('[data-reorder-form]');
        const buildPageOrderPayload = () => {
            return Array.from(document.querySelectorAll('[data-editor-page]')).map((pageSection) => {
                const sortablePage = pageSection.querySelector('[data-sortable-page]');
                const orderedIds = sortablePage
                    ? Array.from(sortablePage.querySelectorAll('[data-sortable-item]')).map((node) => Number(node.dataset.blockId))
                    : [];

                return {
                    page_number: Number((pageSection.id || '').replace('page-', '')) || 0,
                    ordered_ids: orderedIds,
                };
            }).filter((page) => page.ordered_ids.length > 0);
        };

        const syncAllOrders = () => {
            const payload = JSON.stringify(buildPageOrderPayload());
            reorderForms.forEach((form) => {
                const input = form.querySelector('[name="order_json"]');
                if (input) {
                    input.value = payload;
                }
            });
        };

        syncAllOrders();
        reorderForms.forEach((form) => {
            form.addEventListener('submit', syncAllOrders);
        });

        pages.forEach((page) => {
            const form = page.closest('[data-editor-page]')?.querySelector('[data-reorder-form]') || reorderForms[0] || null;
            if (window.Sortable) {
                Sortable.create(page, {
                    animation: 150,
                    draggable: '[data-sortable-item]',
                    handle: '.drag-handle',
                    ghostClass: 'sortable-ghost',
                    group: 'feedback-pages',
                    onEnd: function () {
                        syncAllOrders();
                        if (form) {
                            fetch(form.action, {
                                method: 'POST',
                                body: new FormData(form),
                                headers: { 'X-Requested-With': 'XMLHttpRequest' }
                            }).catch(console.error);
                        }
                    }
                });
            }
        });

        if (!document.getElementById('sortable-style')) {
            const style = document.createElement('style');
            style.id = 'sortable-style';
            style.textContent = '.sortable-ghost { opacity: 0.45; }';
            document.head.appendChild(style);
        }
    }());

    function openAddBlockModal(pageNumber, insertAt) {
        document.getElementById('add-modal-page').value = pageNumber;
        document.getElementById('add-modal-target').value = pageNumber;
        
        const insertField = document.getElementById('add-modal-insert');
        if (insertAt !== null && insertAt !== undefined) {
            insertField.value = insertAt;
        } else {
            insertField.value = '';
        }
        
        const modal = document.getElementById('editor-add-modal');
        modal.hidden = false;
        modal.classList.add('open');
    }
</script>
