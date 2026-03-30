<?php
declare(strict_types=1);

if (!function_exists('mediafeedback_v2_results_media_url')) {
    function mediafeedback_v2_results_media_url(?string $file): string
    {
        if ($file === null || $file === '') {
            return '';
        }

        return V2_BASE_URL . '/media?file=' . rawurlencode($file);
    }
}

if (!function_exists('mediafeedback_v2_results_format_date')) {
    function mediafeedback_v2_results_format_date(?string $value): string
    {
        if ($value === null || $value === '') {
            return '–';
        }

        $timestamp = strtotime($value);
        return $timestamp !== false ? date('d.m.Y H:i', $timestamp) : $value;
    }
}

if (!function_exists('mediafeedback_v2_results_device_label')) {
    function mediafeedback_v2_results_device_label(?string $deviceHash): string
    {
        if ($deviceHash === null || $deviceHash === '') {
            return 'Ohne Gerätekennung';
        }

        return substr($deviceHash, 0, 12) . '…';
    }
}

if (!function_exists('mediafeedback_v2_results_render_answer')) {
    function mediafeedback_v2_results_render_answer(array $answer): string
    {
        if (empty($answer['has_answer'])) {
            return '<p class="results-answer-empty">Keine Antwort gegeben.</p>';
        }

        ob_start();
        $displayType = (string) ($answer['display_type'] ?? 'text');
        if ($displayType === 'rating') {
            $value = (int) ($answer['rating_value'] ?? 0);
            ?>
            <div class="results-rating">
                <?php for ($i = 1; $i <= max($value, 5); $i++): ?>
                    <span class="results-rating-star<?= $i <= $value ? ' is-active' : '' ?>">★</span>
                <?php endfor; ?>
                <strong><?= $value ?></strong>
            </div>
            <?php
        } elseif ($displayType === 'choice') {
            ?>
            <div class="results-choice-pill"><?= e((string) ($answer['choice_value'] ?? $answer['text'] ?? '')) ?></div>
            <?php
        } elseif ($displayType === 'multiple_choice') {
            ?>
            <div class="results-inline-pills">
                <?php foreach ((array) ($answer['multiple_choices'] ?? []) as $choice): ?>
                    <span class="results-choice-pill" style="display: inline-block; margin-right: 0.5rem; margin-bottom: 0.5rem;"><?= e((string) $choice) ?></span>
                <?php endforeach; ?>
            </div>
            <?php
        } elseif ($displayType === 'media') {
            $mediaKind = (string) ($answer['media_kind'] ?? 'file');
            $mediaUrl = mediafeedback_v2_results_media_url((string) ($answer['media_path'] ?? ''));
            $mediaName = (string) ($answer['media_name'] ?? basename((string) ($answer['media_path'] ?? 'Datei')));

            if ($mediaKind === 'video') {
                ?>
                <div class="results-video-frame">
                    <video controls preload="metadata" class="results-video-player">
                        <source src="<?= e($mediaUrl) ?>">
                    </video>
                </div>
                <?php
            } elseif ($mediaKind === 'audio') {
                ?>
                <div class="results-audio-card">
                    <audio controls class="results-audio-player">
                        <source src="<?= e($mediaUrl) ?>">
                    </audio>
                </div>
                <?php
            } else {
                ?>
                <a class="results-file-link" href="<?= e($mediaUrl) ?>" target="_blank" rel="noopener">Datei öffnen: <?= e($mediaName) ?></a>
                <?php
            }

            if (!empty($answer['supplemental_text'])) {
                ?>
                <p class="results-answer-text" style="margin-top:0.9rem;"><?= nl2br(e((string) $answer['supplemental_text'])) ?></p>
                <?php
            }
        } else {
            ?>
            <p class="results-answer-text"><?= nl2br(e((string) ($answer['text'] ?? ''))) ?></p>
            <?php
        }

        return (string) ob_get_clean();
    }
}

if (!function_exists('mediafeedback_v2_results_insight_badges')) {
    function mediafeedback_v2_results_insight_badges(array $badges): string
    {
        $badges = array_values(array_filter(array_map(static fn ($badge): string => trim((string) $badge), $badges), static fn (string $badge): bool => $badge !== ''));
        if ($badges === []) {
            return '';
        }

        return '<div class="results-inline-pills"><span class="results-inline-pill">' . implode('</span><span class="results-inline-pill">', array_map('e', $badges)) . '</span></div>';
    }
}
?>

<div class="results-shell">
    <section class="card results-header-card">
        <div class="results-header-main">
            <div class="results-header-copy">
                <div class="badge">Ergebnisse</div>
                <h1><?= e($feedback['title']) ?></h1>
                <p class="muted">Von hier aus wechselst du zwischen Einzelantworten und Fragenperspektive, exportierst Daten und erkennst schneller, wo Muster oder Luecken liegen.</p>
            </div>
            <div class="actions results-header-actions">
                <a class="btn secondary" href="<?= V2_BASE_URL ?>/feedback/edit?id=<?= (int) $feedback['id'] ?>">Zum Editor</a>
                <a class="btn secondary" href="<?= V2_BASE_URL ?>/export?id=<?= (int) $feedback['id'] ?>&format=csv">CSV exportieren</a>
                <a class="btn secondary" href="<?= V2_BASE_URL ?>/export?id=<?= (int) $feedback['id'] ?>&format=json">JSON exportieren</a>
            </div>
        </div>
        <div class="results-summary-grid">
            <div class="results-summary-card">
                <span class="results-summary-label">Teilnahmen</span>
                <strong><?= (int) $responseCount ?></strong>
            </div>
            <div class="results-summary-card">
                <span class="results-summary-label">Fragebausteine</span>
                <strong><?= (int) $questionCount ?></strong>
            </div>
            <div class="results-summary-card">
                <span class="results-summary-label">Ø Vollständigkeit</span>
                <strong><?= (int) ($resultOverview['average_completion_rate'] ?? 0) ?> %</strong>
            </div>
            <div class="results-summary-card">
                <span class="results-summary-label">Ø Antworten / Teilnahme</span>
                <strong><?= e((string) ($resultOverview['average_answers_per_response'] ?? 0)) ?></strong>
            </div>
        </div>
        <div class="results-overview-strip">
            <div class="results-overview-item">
                <span>Textantworten</span>
                <strong><?= (int) ($resultOverview['text_answer_total'] ?? 0) ?></strong>
            </div>
            <div class="results-overview-item">
                <span>Medienantworten</span>
                <strong><?= (int) ($resultOverview['media_answer_total'] ?? 0) ?></strong>
            </div>
            <div class="results-overview-item">
                <span>Offene Fragen</span>
                <strong><?= (int) (($resultOverview['type_breakdown']['open_question'] ?? 0)) ?></strong>
            </div>
            <div class="results-overview-item">
                <span>Bewertungen</span>
                <strong><?= (int) (($resultOverview['type_breakdown']['rating'] ?? 0)) ?></strong>
            </div>
            <div class="results-overview-item">
                <span>Auswahlfragen</span>
                <strong><?= (int) (($resultOverview['type_breakdown']['single_choice'] ?? 0)) ?></strong>
            </div>
        </div>
    </section>

    <?php if (!$responsesView): ?>
        <div class="card results-empty-card">
            <h2>Noch keine Antworten vorhanden.</h2>
            <p class="muted">Sobald die ersten Teilnehmenden ihr Feedback absenden, erscheint hier die Auswertung nach Teilnehmenden und nach Fragen.</p>
        </div>
    <?php else: ?>
        <section class="card results-workspace">
            <aside class="results-sidebar">
                <div class="results-sidebar-head">
                    <div class="results-view-toggle" role="tablist" aria-label="Ergebnisansicht">
                        <button id="results-tab-responses" class="results-view-button is-active" type="button" onclick="switchResultsMode('responses')">Nach Teilnehmenden</button>
                        <button id="results-tab-questions" class="results-view-button" type="button" onclick="switchResultsMode('questions')">Nach Fragen</button>
                    </div>
                </div>

                <div id="results-list-responses" class="results-nav-list">
                    <?php foreach ($responsesView as $index => $response): ?>
                        <button
                            type="button"
                            id="results-response-button-<?= (int) $response['response_id'] ?>"
                            class="results-nav-item<?= $index === 0 ? ' is-active' : '' ?>"
                            onclick="showResultsResponse(<?= (int) $response['response_id'] ?>)"
                        >
                            <div class="results-nav-badge"><?= (int) $response['response_id'] ?></div>
                            <div class="results-nav-copy">
                                <strong>Teilnehmer <?= (int) $response['response_id'] ?></strong>
                                <span><?= (int) ($response['answered_count'] ?? 0) ?> / <?= (int) $questionCount ?> beantwortet · <?= (int) ($response['completion_rate'] ?? 0) ?> %</span>
                                <span><?= e(mediafeedback_v2_results_format_date((string) $response['submitted_at'])) ?> Uhr</span>
                                <span><?= e(mediafeedback_v2_results_device_label($response['device_hash'] ?? null)) ?></span>
                            </div>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div id="results-list-questions" class="results-nav-list hidden">
                    <?php foreach ($questionsView as $index => $question): ?>
                        <button
                            type="button"
                            id="results-question-button-<?= (int) $question['id'] ?>"
                            class="results-nav-item<?= $index === 0 ? ' is-active' : '' ?>"
                            onclick="showResultsQuestion(<?= (int) $question['id'] ?>)"
                        >
                            <div class="results-nav-badge question"><?= $index + 1 ?></div>
                            <div class="results-nav-copy">
                                <strong><?= e((string) $question['label']) ?></strong>
                                <span><?= e((string) $question['type_label']) ?> · <?= (int) ($question['answer_rate'] ?? 0) ?> % beantwortet</span>
                                <?php if (!empty($question['is_archived'])): ?>
                                    <span>Frühere Version</span>
                                <?php endif; ?>
                                <span><?= count($question['responses'] ?? []) ?> Antwort(en)</span>
                            </div>
                        </button>
                    <?php endforeach; ?>
                </div>
            </aside>

            <div class="results-main">
                <div id="results-detail-responses">
                    <?php foreach ($responsesView as $index => $response): ?>
                        <section id="results-response-detail-<?= (int) $response['response_id'] ?>" class="results-detail-pane<?= $index === 0 ? '' : ' hidden' ?>">
                            <div class="results-detail-header">
                                <div>
                                    <div class="badge">Teilnehmeransicht</div>
                                    <h2>Teilnehmer <?= (int) $response['response_id'] ?></h2>
                                </div>
                                <div class="results-detail-meta">
                                    <span><?= (int) ($response['answered_count'] ?? 0) ?> von <?= (int) $questionCount ?> beantwortet</span>
                                    <span><?= (int) ($response['completion_rate'] ?? 0) ?> % vollständig</span>
                                    <span><?= e(mediafeedback_v2_results_format_date((string) $response['submitted_at'])) ?> Uhr</span>
                                    <span><?= e(mediafeedback_v2_results_device_label($response['device_hash'] ?? null)) ?></span>
                                </div>
                            </div>

                            <div class="results-section-stack">
                                <?php foreach ($questionBlocks as $questionIndex => $question): ?>
                                    <article class="results-answer-card">
                                        <div class="results-answer-head">
                                            <div class="results-answer-number"><?= $questionIndex + 1 ?></div>
                                            <div class="results-answer-copy">
                                                <strong><?= e((string) $question['label']) ?></strong>
                                                <div class="results-answer-tags">
                                                    <span class="results-answer-tag"><?= e((string) $question['type_label']) ?></span>
                                                    <span class="results-answer-tag">Seite <?= (int) $question['page_number'] + 1 ?></span>
                                                    <?php if (!empty($question['is_archived'])): ?>
                                                        <span class="results-answer-tag">Frühere Version</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="results-answer-body">
                                            <?= mediafeedback_v2_results_render_answer($response['answers'][(int) $question['id']] ?? []) ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>

                <div id="results-detail-questions" class="hidden">
                    <?php foreach ($questionsView as $index => $question): ?>
                        <section id="results-question-detail-<?= (int) $question['id'] ?>" class="results-detail-pane<?= $index === 0 ? '' : ' hidden' ?>">
                            <div class="results-detail-header">
                                <div>
                                    <div class="badge">Fragenansicht</div>
                                    <h2><?= e((string) $question['label']) ?></h2>
                                </div>
                                <div class="results-detail-meta">
                                    <span><?= e((string) $question['type_label']) ?></span>
                                    <span><?= (int) ($question['answer_rate'] ?? 0) ?> % beantwortet</span>
                                    <?php if (!empty($question['is_archived'])): ?>
                                        <span>Frühere Version</span>
                                    <?php endif; ?>
                                    <span><?= count($question['responses'] ?? []) ?> Antwort(en)</span>
                                </div>
                            </div>

                            <?php if (!empty($question['help_text'])): ?>
                                <div class="results-question-help"><?= e((string) $question['help_text']) ?></div>
                            <?php endif; ?>

                            <div class="results-insight-card">
                                <strong><?= e((string) (($question['insight']['headline'] ?? '')) ?: 'Noch keine Antworten vorhanden.') ?></strong>
                                <?= mediafeedback_v2_results_insight_badges((array) ($question['insight']['badges'] ?? [])) ?>
                            </div>

                            <?php if (empty($question['responses'])): ?>
                                <div class="results-empty-card" style="margin-top:0;">
                                    <h3>Noch keine Antworten zu dieser Frage.</h3>
                                    <p class="muted">Sobald Teilnehmende auf diesen Baustein antworten, erscheinen die Ergebnisse hier gesammelt.</p>
                                </div>
                            <?php else: ?>
                                <div class="results-question-grid">
                                    <?php foreach ($question['responses'] as $entry): ?>
                                        <article class="results-question-card">
                                            <div class="results-question-card-top">
                                                <strong>Teilnehmer <?= (int) $entry['response_id'] ?></strong>
                                                <span><?= e(mediafeedback_v2_results_format_date((string) $entry['submitted_at'])) ?> Uhr</span>
                                            </div>
                                            <div class="results-question-card-body">
                                                <?= mediafeedback_v2_results_render_answer($entry['answer']) ?>
                                            </div>
                                            <div class="results-question-card-foot">
                                                <span><?= e(mediafeedback_v2_results_device_label($entry['device_hash'] ?? null)) ?></span>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php if ($responsesView): ?>
<script>
    function switchResultsMode(mode) {
        const isResponses = mode === 'responses';
        document.getElementById('results-list-responses').classList.toggle('hidden', !isResponses);
        document.getElementById('results-list-questions').classList.toggle('hidden', isResponses);
        document.getElementById('results-detail-responses').classList.toggle('hidden', !isResponses);
        document.getElementById('results-detail-questions').classList.toggle('hidden', isResponses);
        document.getElementById('results-tab-responses').classList.toggle('is-active', isResponses);
        document.getElementById('results-tab-questions').classList.toggle('is-active', !isResponses);

        if (isResponses) {
            const first = document.querySelector('#results-list-responses .results-nav-item');
            if (first) {
                first.click();
            }
        } else {
            const first = document.querySelector('#results-list-questions .results-nav-item');
            if (first) {
                first.click();
            }
        }
    }

    function showResultsResponse(id) {
        document.querySelectorAll('#results-detail-responses .results-detail-pane').forEach((node) => node.classList.add('hidden'));
        document.querySelectorAll('#results-list-responses .results-nav-item').forEach((node) => node.classList.remove('is-active'));
        document.getElementById('results-response-detail-' + id).classList.remove('hidden');
        document.getElementById('results-response-button-' + id).classList.add('is-active');
    }

    function showResultsQuestion(id) {
        document.querySelectorAll('#results-detail-questions .results-detail-pane').forEach((node) => node.classList.add('hidden'));
        document.querySelectorAll('#results-list-questions .results-nav-item').forEach((node) => node.classList.remove('is-active'));
        document.getElementById('results-question-detail-' + id).classList.remove('hidden');
        document.getElementById('results-question-button-' + id).classList.add('is-active');
    }

    document.addEventListener('DOMContentLoaded', function () {
        switchResultsMode('responses');
    });
</script>
<?php endif; ?>
