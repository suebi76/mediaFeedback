<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Activities;

use MediaFeedbackV2\Support\UploadManager;

class ImageActivity extends ActivityBase
{
    public function getType(): string { return 'image'; }
    public function getName(): string { return 'Bild'; }
    public function getDescription(): string { return 'Bildblock mit Alt-Text und Beschriftung.'; }
    public function isQuestion(): bool { return false; }

    public function defaultData(): array
    {
        return ['media_path' => '', 'alt_text' => 'Dekoratives Bild', 'caption' => '', 'required' => false];
    }

    public function sanitizeEditorInput(array $input, array $files, UploadManager $uploads, ?array $currentData = null): array
    {
        $data = $currentData ?? $this->defaultData();
        $data['alt_text'] = $this->text($input, 'alt_text');
        $data['caption'] = $this->text($input, 'caption');
        if (isset($files['media']) && ($files['media']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $data['media_path'] = $uploads->store($files['media'], 'image');
        }
        $data['required'] = false;
        return $data;
    }

    public function validateEditorData(array $data): array
    {
        $errors = [];
        if (($data['media_path'] ?? '') === '') {
            $errors[] = 'Bitte ein Bild hochladen.';
        }
        if (($data['alt_text'] ?? '') === '') {
            $errors[] = 'Bitte einen Alt-Text vergeben.';
        }
        return $errors;
    }

    public function renderEditorForm(array $data): string
    {
        return $this->upload('media', 'Bilddatei', 'Erlaubt: JPG, PNG, GIF, WEBP')
            . $this->input('alt_text', 'Alt-Text (Erforderlich)', (string) ($data['alt_text'] !== '' ? $data['alt_text'] : 'Dekoratives Bild'), 'text', 'Bildbeschreibung für Screenreader.', ['required' => true])
            . $this->input('caption', 'Bildunterschrift (optional)', (string) ($data['caption'] ?? ''), 'text', '');
    }

    public function renderEditorSummary(array $data): string
    {
        $html = '';
        if (!empty($data['media_path'])) {
            $html .= '<img class="media-preview" src="' . \e(V2_BASE_URL . '/media?file=' . $data['media_path']) . '" alt="' . \e($data['alt_text'] ?? '') . '">';
        }
        if (!empty($data['caption'])) {
            $html .= '<div class="meta">' . \e($data['caption']) . '</div>';
        }
        return $html ?: '<div class="meta">Noch kein Bild vorhanden.</div>';
    }

    public function renderPublic(array $block, array $data, mixed $oldValue = null, array $errors = []): string
    {
        ob_start(); ?>
        <div class="card activity-card">
            <?php if (!empty($data['media_path'])): ?>
                <img class="media-preview" src="<?= \e(V2_BASE_URL . '/media?file=' . $data['media_path']) ?>" alt="<?= \e($data['alt_text'] ?? '') ?>">
            <?php else: ?>
                <div class="meta">Für diesen Bildblock wurde noch keine Datei hinterlegt.</div>
            <?php endif; ?>
            <?php if (!empty($data['caption'])): ?><div class="meta"><?= \e($data['caption']) ?></div><?php endif; ?>
        </div>
        <?php return (string) ob_get_clean();
    }
}
