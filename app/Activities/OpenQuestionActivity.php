<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Activities;

use MediaFeedbackV2\Support\UploadManager;

class OpenQuestionActivity extends ActivityBase
{
    public function getType(): string { return 'open_question'; }
    public function getName(): string { return 'Offene Frage'; }
    public function getDescription(): string { return 'Frage mit Text-, Audio-, Video- oder Dateiantwort.'; }
    public function isQuestion(): bool { return true; }

    public function defaultData(): array
    {
        return ['label' => 'Neue offene Frage', 'help_text' => '', 'allow_text' => true, 'allow_audio' => true, 'allow_video' => false, 'allow_file' => false, 'required' => false];
    }

    public function sanitizeEditorInput(array $input, array $files, UploadManager $uploads, ?array $currentData = null): array
    {
        return [
            'label' => $this->text($input, 'label'),
            'help_text' => $this->text($input, 'help_text'),
            'allow_text' => $this->boolValue($input, 'allow_text'),
            'allow_audio' => $this->boolValue($input, 'allow_audio'),
            'allow_video' => $this->boolValue($input, 'allow_video'),
            'allow_file' => $this->boolValue($input, 'allow_file'),
            'required' => $this->boolValue($input, 'required'),
        ];
    }

    public function validateEditorData(array $data): array
    {
        $errors = [];
        if (($data['label'] ?? '') === '') {
            $errors[] = 'Die Frage braucht einen Titel.';
        }
        if (!$data['allow_text'] && !$data['allow_audio'] && !$data['allow_video'] && !$data['allow_file']) {
            $errors[] = 'Mindestens ein Antwortformat muss aktiviert sein.';
        }
        return $errors;
    }

    public function renderEditorForm(array $data): string
    {
        $enabledModes = [];
        foreach ([
            'allow_text' => 'Text',
            'allow_audio' => 'Audio',
            'allow_video' => 'Video',
            'allow_file' => 'Datei',
        ] as $key => $label) {
            if (!empty($data[$key])) {
                $enabledModes[] = $label;
            }
        }

        return $this->editorSection(
            'Fragetext',
            $this->input('label', 'Frage', (string) ($data['label'] ?? ''), 'text', '')
            . $this->input('help_text', 'Hilfetext', (string) ($data['help_text'] ?? ''), 'text', ''),
            '',
            true,
            (string) ($data['label'] ?? 'Noch ohne Fragetext')
        )
        . $this->editorSection(
            'Antwortformate',
            '<div class="stack">'
            . $this->checkbox('allow_text', 'Textantwort erlauben', !empty($data['allow_text']))
            . $this->checkbox('allow_audio', 'Audioantwort erlauben', !empty($data['allow_audio']))
            . $this->checkbox('allow_video', 'Videoantwort erlauben', !empty($data['allow_video']))
            . $this->checkbox('allow_file', 'Dateianhang erlauben', !empty($data['allow_file']))
            . '</div>',
            '',
            false,
            $enabledModes !== [] ? implode(', ', $enabledModes) : 'Noch kein Antwortformat aktiviert'
        )
        . $this->editorSection(
            'Antwortregeln',
            $this->checkbox('required', 'Antwort erforderlich', !empty($data['required'])),
            '',
            false,
            !empty($data['required']) ? 'Pflichtfrage' : 'Antwort optional'
        );
    }

    public function renderEditorSummary(array $data): string
    {
        $modes = [];
        foreach (['allow_text' => 'Text', 'allow_audio' => 'Audio', 'allow_video' => 'Video', 'allow_file' => 'Datei'] as $key => $label) {
            if (!empty($data[$key])) {
                $modes[] = $label;
            }
        }
        return '<strong>' . \e($data['label'] ?? '') . '</strong><div class="meta">' . \e($data['help_text'] ?? '') . '</div><div class="pillset"><span class="pill">' . implode('</span><span class="pill">', array_map('e', $modes)) . '</span></div>';
    }

    public function renderPublic(array $block, array $data, mixed $oldValue = null, array $errors = []): string
    {
        $blockId = (int) $block['id'];
        $modeLabels = [
            'allow_text' => 'Text',
            'allow_video' => 'Video',
            'allow_audio' => 'Audio',
            'allow_file' => 'Datei',
        ];
        $modeMap = [
            'text' => !empty($data['allow_text']),
            'video' => !empty($data['allow_video']),
            'audio' => !empty($data['allow_audio']),
            'file' => !empty($data['allow_file']),
        ];
        $availableModes = array_values(array_filter(
            ['text', 'video', 'audio', 'file'],
            static fn (string $mode): bool => !empty($modeMap[$mode])
        ));
        $hasModeChooser = count($availableModes) > 1;
        $selectedMode = $availableModes[0] ?? null;
        $valueText = trim((string) ($oldValue['value_text'] ?? ''));
        $mediaPath = (string) ($oldValue['media_path'] ?? '');
        if ($valueText !== '' && !empty($modeMap['text'])) {
            $selectedMode = 'text';
        } elseif ($mediaPath !== '') {
            $extension = strtolower((string) pathinfo($mediaPath, PATHINFO_EXTENSION));
            if (in_array($extension, ['webm', 'mp4', 'mov', 'm4v'], true) && !empty($modeMap['video'])) {
                $selectedMode = 'video';
            } elseif (in_array($extension, ['mp3', 'wav', 'm4a', 'weba', 'ogg'], true) && !empty($modeMap['audio'])) {
                $selectedMode = 'audio';
            } elseif (!empty($modeMap['file'])) {
                $selectedMode = 'file';
            } elseif (!empty($modeMap['audio'])) {
                $selectedMode = 'audio';
            } elseif (!empty($modeMap['video'])) {
                $selectedMode = 'video';
            }
        }
        ob_start(); ?>
        <div class="card activity-card stack">
            <?= $this->publicQuestionHeader(
                (string) ($data['label'] ?? ''),
                (string) ($data['help_text'] ?? ''),
                [],
                !empty($data['required'])
            ) ?>
            <?php if (!empty($errors[$blockId])): ?><div class="flash error"><?= \e($errors[$blockId]) ?></div><?php endif; ?>
            <?php if ($hasModeChooser): ?>
                <div class="public-mode-picker stack" data-answer-mode-group data-default-mode="<?= \e((string) $selectedMode) ?>">
                    <div class="public-mode-picker-copy">
                        <h3>Alternative Antworten</h3>
                        <div class="meta">Wähle den Weg, der für dich gerade am einfachsten ist.</div>
                    </div>
                    <div class="public-choice-grid public-mode-choice-grid">
                        <?php foreach ($availableModes as $mode): ?>
                            <label class="public-choice-card public-mode-card<?= $selectedMode === $mode ? ' is-selected' : '' ?>">
                                <input
                                    type="radio"
                                    name="answer_mode_display_<?= $blockId ?>"
                                    value="<?= \e($mode) ?>"
                                    data-answer-mode-option="<?= \e($mode) ?>"
                                    <?= $selectedMode === $mode ? 'checked' : '' ?>
                                >
                                <span><?= \e($modeLabels['allow_' . $mode] ?? ucfirst($mode)) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
            <?php endif; ?>
            <?php if (!empty($data['allow_text'])): ?>
                <div class="response-box public-response-card stack"<?= $hasModeChooser ? ' data-answer-mode-panel="text"' . ($selectedMode === 'text' ? '' : ' hidden') : '' ?>>
                    <div class="public-response-card-head">
                        <strong>Textantwort</strong>
                        <span class="meta">Schreibe deine Gedanken direkt hier hinein.</span>
                    </div>
                    <div class="field">
                        <label for="answer-<?= $blockId ?>">Antwort</label>
                        <textarea id="answer-<?= $blockId ?>" name="answers[<?= $blockId ?>]" rows="5" placeholder="Schreibe hier deine Antwort ..."><?= \e((string) ($oldValue['value_text'] ?? '')) ?></textarea>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($data['allow_audio'])): ?>
                <div class="response-box public-response-card public-recorder-card stack" data-recorder-root data-kind="audio" data-block-id="<?= $blockId ?>"<?= $hasModeChooser ? ' data-answer-mode-panel="audio"' . ($selectedMode === 'audio' ? '' : ' hidden') : '' ?>>
                    <div class="public-response-card-head">
                        <div>
                            <strong>Antwort als Audio</strong>
                            <div class="meta">Sprich direkt ein oder lade eine bestehende Datei hoch.</div>
                        </div>
                        <span class="public-recorder-status" data-role="status">Audioaufnahme vorbereiten</span>
                    </div>
                    <div class="field">
                        <label for="audio-device-<?= $blockId ?>">Audioquelle</label>
                        <select id="audio-device-<?= $blockId ?>" data-role="audio-source"></select>
                    </div>
                    <div class="actions public-recorder-actions">
                        <button class="btn secondary small" type="button" data-role="connect">Geraet pruefen</button>
                        <button class="btn secondary small" type="button" data-role="start" disabled>Aufnahme starten</button>
                        <button class="btn secondary small" type="button" data-role="stop" disabled>Stoppen</button>
                        <button class="btn secondary small" type="button" data-role="reset" disabled>Neu beginnen</button>
                    </div>
                    <audio class="media-preview" controls data-role="audio-playback" style="display:none;"></audio>
                    <?= $this->publicUploadField(
                        'response_audio[' . $blockId . ']',
                        'Alternativ Datei hochladen',
                        'Falls du schon eine Aufnahme hast, kannst du sie direkt auswaehlen.',
                        ['accept' => 'audio/*', 'capture' => 'user', 'data-role' => 'file-input'],
                        'Audio hochladen'
                    ) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($data['allow_video'])): ?>
                <div class="response-box public-response-card public-recorder-card stack" data-recorder-root data-kind="video" data-block-id="<?= $blockId ?>"<?= $hasModeChooser ? ' data-answer-mode-panel="video"' . ($selectedMode === 'video' ? '' : ' hidden') : '' ?>>
                    <div class="public-response-card-head">
                        <div>
                            <strong>Antwort als Video</strong>
                            <div class="meta">Nimm direkt auf oder lade ein vorhandenes Video hoch.</div>
                        </div>
                        <span class="public-recorder-status" data-role="status">Videoaufnahme vorbereiten</span>
                    </div>
                    <div class="grid two public-recorder-device-grid">
                        <div class="field">
                            <label for="video-device-<?= $blockId ?>">Kamera</label>
                            <select id="video-device-<?= $blockId ?>" data-role="video-source"></select>
                        </div>
                        <div class="field">
                            <label for="video-audio-device-<?= $blockId ?>">Mikrofon</label>
                            <select id="video-audio-device-<?= $blockId ?>" data-role="audio-source"></select>
                        </div>
                    </div>
                    <div class="actions public-recorder-actions">
                        <button class="btn secondary small" type="button" data-role="connect">Geraete pruefen</button>
                        <button class="btn secondary small" type="button" data-role="start" disabled>Aufnahme starten</button>
                        <button class="btn secondary small" type="button" data-role="stop" disabled>Stoppen</button>
                        <button class="btn secondary small" type="button" data-role="reset" disabled>Neu beginnen</button>
                    </div>
                    <video class="media-preview" autoplay muted playsinline data-role="preview" style="display:none;"></video>
                    <video class="media-preview" controls data-role="playback" style="display:none;"></video>
                    <?= $this->publicUploadField(
                        'response_video[' . $blockId . ']',
                        'Alternativ Datei hochladen',
                        'Falls du schon ein Video vorbereitet hast, kannst du es direkt auswaehlen.',
                        ['accept' => 'video/*', 'capture' => 'user', 'data-role' => 'file-input'],
                        'Video hochladen'
                    ) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($data['allow_file'])): ?>
                <div class="response-box public-response-card stack"<?= $hasModeChooser ? ' data-answer-mode-panel="file"' . ($selectedMode === 'file' ? '' : ' hidden') : '' ?>>
                    <?= $this->publicUploadField(
                        'response_file[' . $blockId . ']',
                        'Datei anhängen',
                        'Zum Beispiel PDF, Office-Dokumente oder Textdateien.',
                        ['accept' => '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt', 'data-role' => 'file-input'],
                        'Datei auswählen'
                    ) ?>
                </div>
            <?php endif; ?>
            <?php if ($hasModeChooser): ?>
                </div>
            <?php endif; ?>
        </div>
        <?php return (string) ob_get_clean();
    }

    public function extractResponse(int $blockId, array $post, array $files, UploadManager $uploads): array
    {
        $text = trim((string) ($post['answers'][$blockId] ?? ''));
        $mediaPath = null;

        foreach ([['field' => 'response_audio', 'kind' => 'audio'], ['field' => 'response_video', 'kind' => 'video'], ['field' => 'response_file', 'kind' => 'attachment']] as $spec) {
            $nested = $uploads->nested($files, $spec['field'], $blockId);
            if ($nested) {
                $mediaPath = $uploads->store($nested, $spec['kind']);
                break;
            }
        }

        return ['value_text' => $text !== '' ? $text : null, 'value_json' => null, 'media_path' => $mediaPath];
    }

    public function validateResponse(array $data, array $response): ?string
    {
        $hasText = !empty($response['value_text']);
        $hasMedia = !empty($response['media_path']);
        if (!empty($data['required']) && !$hasText && !$hasMedia) {
            return 'Diese offene Frage ist erforderlich.';
        }
        return null;
    }
}
