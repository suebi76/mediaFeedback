<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Activities;

use MediaFeedbackV2\Support\UploadManager;

class SingleChoiceActivity extends ActivityBase
{
    public function getType(): string { return 'single_choice'; }
    public function getName(): string { return 'Single Choice'; }
    public function getDescription(): string { return 'Eine Auswahl aus mehreren Optionen.'; }
    public function isQuestion(): bool { return true; }

    public function defaultData(): array
    {
        return ['label' => 'Neue Auswahlfrage', 'help_text' => '', 'options' => ['Option A', 'Option B'], 'required' => false];
    }

    public function sanitizeEditorInput(array $input, array $files, UploadManager $uploads, ?array $currentData = null): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) ($input['options_text'] ?? '')) ?: [];
        $options = array_values(array_filter(array_map(static fn(string $line): string => trim($line), $lines)));
        return ['label' => $this->text($input, 'label'), 'help_text' => $this->text($input, 'help_text'), 'options' => $options, 'required' => $this->boolValue($input, 'required')];
    }

    public function validateEditorData(array $data): array
    {
        $errors = [];
        if (($data['label'] ?? '') === '') {
            $errors[] = 'Die Auswahlfrage braucht einen Titel.';
        }
        if (count($data['options'] ?? []) < 2) {
            $errors[] = 'Bitte mindestens zwei Auswahloptionen angeben.';
        }
        return $errors;
    }

    public function renderEditorForm(array $data): string
    {
        $options = $data['options'] ?? [];
        return $this->editorSection(
            'Fragetext',
            $this->input('label', 'Frage', (string) ($data['label'] ?? ''), 'text', '')
            . $this->input('help_text', 'Hilfetext', (string) ($data['help_text'] ?? ''), 'text', ''),
            '',
            true,
            (string) ($data['label'] ?? 'Noch ohne Fragetext')
        )
        . $this->editorSection(
            'Optionen',
            $this->pillList($options)
            . $this->textarea('options_text', 'Optionen', implode("\n", $options), 6, 'Eine Option pro Zeile.'),
            '',
            false,
            count($options) . ' Option(en)'
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
        return '<strong>' . \e($data['label'] ?? '') . '</strong><div class="pillset"><span class="pill">' . implode('</span><span class="pill">', array_map('e', $data['options'] ?? [])) . '</span></div>';
    }

    public function renderPublic(array $block, array $data, mixed $oldValue = null, array $errors = []): string
    {
        $blockId = (int) $block['id'];
        $selectedValue = (string) ($oldValue['value_text'] ?? '');
        ob_start(); ?>
        <div class="card activity-card stack">
            <?= $this->publicQuestionHeader(
                (string) ($data['label'] ?? ''),
                (string) ($data['help_text'] ?? ''),
                ['' . count($data['options'] ?? []) . ' Optionen'],
                !empty($data['required'])
            ) ?>
            <?php if (!empty($errors[$blockId])): ?><div class="flash error"><?= \e($errors[$blockId]) ?></div><?php endif; ?>
            <div class="public-choice-grid" data-choice-grid>
                <?php foreach ($data['options'] ?? [] as $option): ?>
                    <label class="public-choice-card<?= $selectedValue === (string) $option ? ' is-selected' : '' ?>" data-choice-card>
                        <input type="radio" name="answers[<?= $blockId ?>]" value="<?= \e($option) ?>" <?= $selectedValue === (string) $option ? 'checked' : '' ?> data-choice-input data-choice-label="<?= \e((string) $option) ?>">
                        <span><?= \e($option) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php return (string) ob_get_clean();
    }

    public function extractResponse(int $blockId, array $post, array $files, UploadManager $uploads): array
    {
        $value = trim((string) ($post['answers'][$blockId] ?? ''));
        return ['value_text' => $value !== '' ? $value : null, 'value_json' => null, 'media_path' => null];
    }

    public function validateResponse(array $data, array $response): ?string
    {
        if (!empty($data['required']) && empty($response['value_text'])) {
            return 'Bitte eine Option auswählen.';
        }
        return null;
    }
}
