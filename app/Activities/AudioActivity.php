<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Activities;

use MediaFeedbackV2\Support\UploadManager;

class AudioActivity extends ActivityBase
{
    public function getType(): string { return 'audio'; }
    public function getName(): string { return 'Audio'; }
    public function getDescription(): string { return 'Audioblock mit optionaler Beschreibung.'; }
    public function isQuestion(): bool { return false; }

    public function defaultData(): array
    {
        return ['media_path' => '', 'caption' => '', 'required' => false];
    }

    public function sanitizeEditorInput(array $input, array $files, UploadManager $uploads, ?array $currentData = null): array
    {
        $data = $currentData ?? $this->defaultData();
        $data['caption'] = $this->text($input, 'caption');
        if (isset($files['media']) && ($files['media']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $data['media_path'] = $uploads->store($files['media'], 'audio');
        }
        $data['required'] = false;
        return $data;
    }

    public function validateEditorData(array $data): array
    {
        return ($data['media_path'] ?? '') === '' ? ['Bitte eine Audiodatei hochladen.'] : [];
    }

    public function renderEditorForm(array $data): string
    {
        return '<div class="response-box stack" data-editor-recorder data-kind="audio">'
            . '<div class="split"><strong>Audio direkt aufnehmen</strong><span class="meta" data-role="status">Noch nicht verbunden</span></div>'
            . '<div class="field"><label>Audioquelle</label><select data-role="audio-source"></select></div>'
            . '<div class="actions">'
            . '<button class="btn small" style="background: var(--brand-500); color: white;" type="button" data-role="connect">Mikrofon aktivieren</button>'
            . '<button class="btn danger small" type="button" data-role="start" disabled>Aufnahme starten</button>'
            . '<button class="btn secondary small" type="button" data-role="stop" disabled>Stoppen</button>'
            . '<button class="btn secondary small" type="button" data-role="reset" disabled>Verwerfen</button>'
            . '</div>'
            . '<audio class="media-preview" controls data-role="audio-playback" style="display:none;"></audio>'
            . '</div>'
            . $this->upload('media', 'Audiodatei auswählen', 'Erlaubt: MP3, WAV, M4A, WEBA', ['data-role' => 'file-input', 'accept' => 'audio/*'], 'Datei auswählen')
            . $this->input('caption', 'Audiobeschreibung (optional)', (string) ($data['caption'] ?? ''), 'text', '');
    }

    public function renderEditorSummary(array $data): string
    {
        $html = '';
        if (!empty($data['media_path'])) {
            $html .= '<audio class="media-preview" controls><source src="' . \e(V2_BASE_URL . '/media?file=' . $data['media_path']) . '"></audio>';
        }
        if (!empty($data['caption'])) {
            $html .= '<div class="meta">' . \e($data['caption']) . '</div>';
        }
        return $html ?: '<div class="meta">Noch kein Audio vorhanden.</div>';
    }

    public function renderPublic(array $block, array $data, mixed $oldValue = null, array $errors = []): string
    {
        ob_start(); ?>
        <div class="card activity-card">
            <?php if (!empty($data['media_path'])): ?>
                <audio class="media-preview" controls><source src="<?= \e(V2_BASE_URL . '/media?file=' . $data['media_path']) ?>"></audio>
            <?php else: ?>
                <div class="meta">Für diesen Audioblock wurde noch keine Datei hinterlegt.</div>
            <?php endif; ?>
            <?php if (!empty($data['caption'])): ?><div class="meta"><?= \e($data['caption']) ?></div><?php endif; ?>
        </div>
        <?php return (string) ob_get_clean();
    }
}
