<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Activities;

use MediaFeedbackV2\Support\UploadManager;

abstract class ActivityBase
{
    private static int $uploadFieldCounter = 0;

    abstract public function getType(): string;
    abstract public function getName(): string;
    abstract public function getDescription(): string;
    abstract public function isQuestion(): bool;
    abstract public function renderEditorForm(array $data): string;
    abstract public function renderEditorSummary(array $data): string;
    abstract public function renderPublic(array $block, array $data, mixed $oldValue = null, array $errors = []): string;
    abstract public function sanitizeEditorInput(array $input, array $files, UploadManager $uploads, ?array $currentData = null): array;
    abstract public function validateEditorData(array $data): array;

    public function defaultData(): array
    {
        return ['required' => false];
    }

    public function validateResponse(array $data, array $response): ?string
    {
        return null;
    }

    public function extractResponse(int $blockId, array $post, array $files, UploadManager $uploads): array
    {
        return ['value_text' => null, 'value_json' => null, 'media_path' => null];
    }

    protected function boolValue(array $input, string $key): bool
    {
        return isset($input[$key]) && (string) $input[$key] === '1';
    }

    protected function text(array $input, string $key, string $default = ''): string
    {
        return trim((string) ($input[$key] ?? $default));
    }

    protected function textarea(string $name, string $label, string $value, int $rows = 5, string $hint = '', array $attributes = []): string
    {
        $attributeHtml = '';
        foreach ($attributes as $attribute => $attributeValue) {
            if ($attributeValue === null || $attributeValue === false) {
                continue;
            }
            if ($attributeValue === true) {
                $attributeHtml .= ' ' . \e((string) $attribute);
                continue;
            }
            $attributeHtml .= ' ' . \e((string) $attribute) . '="' . \e((string) $attributeValue) . '"';
        }
        ob_start(); ?>
        <div class="field">
            <label><?= \e($label) ?></label>
            <textarea name="<?= \e($name) ?>" rows="<?= $rows ?>" data-autosize<?= $attributeHtml ?>><?= \e($value) ?></textarea>
            <?php if ($hint !== ''): ?><div class="meta"><?= \e($hint) ?></div><?php endif; ?>
        </div>
        <?php return (string) ob_get_clean();
    }

    protected function input(string $name, string $label, string $value, string $type = 'text', string $hint = '', array $attributes = []): string
    {
        $attributeHtml = '';
        foreach ($attributes as $attribute => $attributeValue) {
            if ($attributeValue === null || $attributeValue === false) {
                continue;
            }
            if ($attributeValue === true) {
                $attributeHtml .= ' ' . \e((string) $attribute);
                continue;
            }
            $attributeHtml .= ' ' . \e((string) $attribute) . '="' . \e((string) $attributeValue) . '"';
        }
        ob_start(); ?>
        <div class="field">
            <label><?= \e($label) ?></label>
            <input type="<?= \e($type) ?>" name="<?= \e($name) ?>" value="<?= \e($value) ?>"<?= $attributeHtml ?>>
            <?php if ($hint !== ''): ?><div class="meta"><?= \e($hint) ?></div><?php endif; ?>
        </div>
        <?php return (string) ob_get_clean();
    }

    protected function checkbox(string $name, string $label, bool $checked): string
    {
        return '<label class="editor-check"><input type="checkbox" name="' . \e($name) . '" value="1" ' . ($checked ? 'checked' : '') . '><span>' . \e($label) . '</span></label>';
    }

    protected function upload(string $name, string $label, string $hint = '', array $attributes = [], string $buttonLabel = 'Datei auswählen', string $emptyText = 'Noch keine Datei gewählt.'): string
    {
        self::$uploadFieldCounter++;
        $inputId = 'upload-' . self::$uploadFieldCounter;
        $attributeHtml = '';
        foreach ($attributes as $attribute => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if ($value === true) {
                $attributeHtml .= ' ' . \e((string) $attribute);
                continue;
            }
            $attributeHtml .= ' ' . \e((string) $attribute) . '="' . \e((string) $value) . '"';
        }
        ob_start(); ?>
        <div class="field upload-field">
            <label for="<?= \e($inputId) ?>"><?= \e($label) ?></label>
            <div class="upload-control">
                <input id="<?= \e($inputId) ?>" class="sr-only-file-input" type="file" name="<?= \e($name) ?>" data-file-input<?= $attributeHtml ?>>
                <button class="btn secondary small" type="button" data-file-trigger="<?= \e($inputId) ?>"><?= \e($buttonLabel) ?></button>
                <span class="meta" data-file-name for="<?= \e($inputId) ?>"><?= \e($emptyText) ?></span>
            </div>
            <?php if ($hint !== ''): ?><div class="meta"><?= \e($hint) ?></div><?php endif; ?>
        </div>
        <?php return (string) ob_get_clean();
    }

    protected function editorSection(string $title, string $body, string $hint = '', bool $open = false, string $summary = ''): string
    {
        ob_start(); ?>
        <details class="editor-section" <?= $open ? 'open' : '' ?>>
            <summary class="editor-section-summary">
                <div>
                    <h3><?= \e($title) ?></h3>
                    <?php if ($summary !== ''): ?><div class="meta"><?= \e($summary) ?></div><?php endif; ?>
                </div>
                <span class="editor-section-toggle" data-open-label="Weniger" data-closed-label="Mehr"><?= $open ? 'Weniger' : 'Mehr' ?></span>
            </summary>
            <div class="editor-section-body">
                <?php if ($hint !== ''): ?><div class="meta"><?= \e($hint) ?></div><?php endif; ?>
                <div class="stack">
                    <?= $body ?>
                </div>
            </div>
        </details>
        <?php return (string) ob_get_clean();
    }

    protected function pillList(array $items): string
    {
        $items = array_values(array_filter(array_map(static fn ($item): string => trim((string) $item), $items), static fn (string $item): bool => $item !== ''));
        if ($items === []) {
            return '<div class="meta">Noch keine Einträge vorhanden.</div>';
        }

        return '<div class="pillset"><span class="pill">' . implode('</span><span class="pill">', array_map('e', $items)) . '</span></div>';
    }

    protected function editorPreviewNote(string $text): string
    {
        return '<div class="response-box editor-preview-note">' . \e($text) . '</div>';
    }

    protected function publicQuestionHeader(string $title, string $helpText = '', array $metaPills = [], bool $required = false): string
    {
        $pills = [];
        foreach ($metaPills as $pill) {
            $pill = trim((string) $pill);
            if ($pill !== '') {
                $pills[] = $pill;
            }
        }

        ob_start(); ?>
        <div class="public-question-head">
            <div class="public-question-copy">
                <strong><?= \e($title) ?></strong>
                <?php if ($helpText !== ''): ?><div class="meta"><?= \e($helpText) ?></div><?php endif; ?>
            </div>
            <div class="public-question-meta">
                <?php foreach ($pills as $pill): ?>
                    <span class="public-question-pill"><?= \e($pill) ?></span>
                <?php endforeach; ?>
                <?php if ($required): ?>
                    <span class="public-question-pill is-required">Pflicht</span>
                <?php endif; ?>
            </div>
        </div>
        <?php return (string) ob_get_clean();
    }

    protected function publicUploadField(string $name, string $title, string $hint = '', array $attributes = [], string $buttonLabel = 'Datei auswählen', string $emptyText = 'Noch keine Datei gewählt.'): string
    {
        self::$uploadFieldCounter++;
        $inputId = 'public-upload-' . self::$uploadFieldCounter;
        $attributeHtml = '';
        foreach ($attributes as $attribute => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if ($value === true) {
                $attributeHtml .= ' ' . \e((string) $attribute);
                continue;
            }
            $attributeHtml .= ' ' . \e((string) $attribute) . '="' . \e((string) $value) . '"';
        }

        ob_start(); ?>
        <div class="public-upload-card">
            <div class="public-upload-copy">
                <strong><?= \e($title) ?></strong>
                <?php if ($hint !== ''): ?><div class="meta"><?= \e($hint) ?></div><?php endif; ?>
            </div>
            <div class="public-upload-actions">
                <label class="btn secondary small public-upload-trigger public-upload-trigger-label">
                    <span><?= \e($buttonLabel) ?></span>
                    <input
                        id="<?= \e($inputId) ?>"
                        class="public-upload-native-input"
                        type="file"
                        name="<?= \e($name) ?>"
                        data-file-input
                        data-empty-text="<?= \e($emptyText) ?>"
                        aria-label="<?= \e($buttonLabel) ?>"<?= $attributeHtml ?>>
                </label>
                <span class="public-upload-name" data-file-name for="<?= \e($inputId) ?>"><?= \e($emptyText) ?></span>
            </div>
        </div>
        <?php return (string) ob_get_clean();
    }
}
