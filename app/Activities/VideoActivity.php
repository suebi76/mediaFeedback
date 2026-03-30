<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Activities;

use MediaFeedbackV2\Support\UploadManager;

class VideoActivity extends ActivityBase
{
    public function getType(): string { return 'video'; }
    public function getName(): string { return 'Video'; }
    public function getDescription(): string { return 'Video mit optionaler Beschriftung.'; }
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
            $data['media_path'] = $uploads->store($files['media'], 'video');
        }
        $data['required'] = false;
        return $data;
    }

    public function validateEditorData(array $data): array
    {
        return ($data['media_path'] ?? '') === '' ? ['Bitte ein Video hochladen.'] : [];
    }

    public function renderEditorForm(array $data): string
    {
        return '<div class="response-box stack" data-editor-recorder data-kind="video">'
            . '<div class="split"><strong>Video direkt aufnehmen</strong><span class="meta" data-role="status">Noch nicht verbunden</span></div>'
            . '<div class="grid two">'
            . '<div class="field"><label>Kamera</label><select data-role="video-source"></select></div>'
            . '<div class="field"><label>Mikrofon</label><select data-role="audio-source"></select></div>'
            . '</div>'
            . '<div class="actions">'
            . '<button class="btn small" style="background: var(--brand-500); color: white;" type="button" data-role="connect">Kamera & Mikrofon aktivieren</button>'
            . '<button class="btn danger small" type="button" data-role="start" disabled>Aufnahme starten</button>'
            . '<button class="btn secondary small" type="button" data-role="stop" disabled>Stoppen</button>'
            . '<button class="btn secondary small" type="button" data-role="reset" disabled>Verwerfen</button>'
            . '</div>'
            . '<video class="media-preview" autoplay muted playsinline data-role="preview" style="display:none;"></video>'
            . '<video class="media-preview" controls preload="metadata" data-role="playback" style="display:none;"></video>'
            . '</div>'
            . $this->upload('media', 'Videodatei auswählen', 'Erlaubt: MP4, WEBM', ['data-role' => 'file-input', 'accept' => 'video/*'], 'Datei auswählen')
            . $this->input('caption', 'Videobeschreibung (optional)', (string) ($data['caption'] ?? ''), 'text', '');
    }

    public function renderEditorSummary(array $data): string
    {
        $html = '';
        if (!empty($data['media_path'])) {
            $html .= '<video class="media-preview" controls preload="metadata"><source src="' . \e(V2_BASE_URL . '/media?file=' . $data['media_path']) . '"></video>';
        }
        if (!empty($data['caption'])) {
            $html .= '<div class="meta">' . \e($data['caption']) . '</div>';
        }
        return $html ?: '<div class="meta">Noch kein Video vorhanden.</div>';
    }

    public function renderPublic(array $block, array $data, mixed $oldValue = null, array $errors = []): string
    {
        ob_start(); ?>
        <div class="card activity-card">
            <?php if (!empty($data['media_path'])): ?>
                <video class="media-preview" controls preload="metadata"><source src="<?= \e(V2_BASE_URL . '/media?file=' . $data['media_path']) ?>"></video>
            <?php else: ?>
                <div class="meta">Für diesen Videoblock wurde noch keine Datei hinterlegt.</div>
            <?php endif; ?>
            <?php if (!empty($data['caption'])): ?><div class="meta"><?= \e($data['caption']) ?></div><?php endif; ?>
        </div>
        <?php return (string) ob_get_clean();
    }
}
