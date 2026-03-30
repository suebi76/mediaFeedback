<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Activities;

use MediaFeedbackV2\Support\HtmlSanitizer;
use MediaFeedbackV2\Support\UploadManager;

class TextActivity extends ActivityBase
{
    public function getType(): string { return 'text'; }
    public function getName(): string { return 'Text'; }
    public function getDescription(): string { return 'Sicherer HTML-Textblock für Hinweise und Kontext.'; }
    public function isQuestion(): bool { return false; }

    public function defaultData(): array
    {
        return ['content' => '<p>Neuer Textblock</p>', 'required' => false];
    }

    public function sanitizeEditorInput(array $input, array $files, UploadManager $uploads, ?array $currentData = null): array
    {
        return ['content' => HtmlSanitizer::clean($this->text($input, 'content')), 'required' => false];
    }

    public function validateEditorData(array $data): array
    {
        return trim(strip_tags((string) ($data['content'] ?? ''))) === '' ? ['Der Textblock darf nicht leer sein.'] : [];
    }

    public function renderEditorForm(array $data): string
    {
        return $this->textarea(
            'content',
            'Textinhalt',
            (string) ($data['content'] ?? ''),
            12,
            '',
            [
                    'data-richtext' => '1',
                    'data-richtext-profile' => 'content',
                ]
            );
    }

    public function renderEditorSummary(array $data): string
    {
        return '<div class="html-preview">' . ($data['content'] ?? '') . '</div>';
    }

    public function renderPublic(array $block, array $data, mixed $oldValue = null, array $errors = []): string
    {
        return '<div class="card activity-card"><div class="html-preview">' . ($data['content'] ?? '') . '</div></div>';
    }
}
