<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Activities;

use MediaFeedbackV2\Support\UploadManager;

class MultipleChoiceActivity extends ActivityBase
{
    public function getType(): string { return 'multiple_choice'; }
    public function getName(): string { return 'Multiple Choice'; }
    public function getDescription(): string { return 'Mehrere Optionen gleichzeitig wählbar (Checkboxen).'; }
    public function isQuestion(): bool { return true; }

    public function defaultData(): array
    {
        return ['label' => 'Neue Mehrfachauswahl', 'help_text' => '', 'options' => ['Option A', 'Option B', 'Option C'], 'required' => false];
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
        return '<div class="stack">'
            . $this->input('label', 'Fragetext', (string) ($data['label'] ?? ''), 'text', '')
            . $this->input('help_text', 'Hilfetext', (string) ($data['help_text'] ?? ''), 'text', '')
            . $this->textarea('options_text', 'Optionen', implode("\n", $options), 6, 'Eine Option pro Zeile.')
            . $this->checkbox('required', 'Mindestens eine Antwort erforderlich', !empty($data['required']))
            . '</div>';
    }

    public function renderEditorSummary(array $data): string
    {
        return '<strong>' . \e($data['label'] ?? '') . '</strong><div class="pillset"><span class="pill">' . implode('</span><span class="pill">', array_map('e', $data['options'] ?? [])) . '</span></div>';
    }

    public function renderPublic(array $block, array $data, mixed $oldValue = null, array $errors = []): string
    {
        $blockId = (int) $block['id'];
        $selectedValues = (array) json_decode((string) ($oldValue['value_json'] ?? '[]'), true);
        ob_start(); ?>
        <div class="card activity-card stack">
            <?= $this->publicQuestionHeader(
                (string) ($data['label'] ?? ''),
                (string) ($data['help_text'] ?? ''),
                ['' . count($data['options'] ?? []) . ' Optionen', 'Mehrfachauswahl'],
                !empty($data['required'])
            ) ?>
            <?php if (!empty($errors[$blockId])): ?><div class="flash error"><?= \e($errors[$blockId]) ?></div><?php endif; ?>
            <div class="public-choice-grid" data-choice-grid>
                <?php foreach ($data['options'] ?? [] as $option): ?>
                    <?php $isChecked = in_array((string) $option, $selectedValues, true); ?>
                    <label class="public-choice-card<?= $isChecked ? ' is-selected' : '' ?>" data-choice-card>
                        <input type="checkbox" name="answers[<?= $blockId ?>][]" value="<?= \e($option) ?>" <?= $isChecked ? 'checked' : '' ?> data-choice-input data-choice-label="<?= \e((string) $option) ?>">
                        <span><?= \e($option) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php return (string) ob_get_clean();
    }

    public function extractResponse(int $blockId, array $post, array $files, UploadManager $uploads): array
    {
        $choices = (array) ($post['answers'][$blockId] ?? []);
        $cleanChoices = array_values(array_filter(array_map('trim', $choices)));
        return [
            'value_text' => null, 
            'value_json' => empty($cleanChoices) ? null : json_encode($cleanChoices, JSON_UNESCAPED_UNICODE), 
            'media_path' => null
        ];
    }

    public function validateResponse(array $data, array $response): ?string
    {
        if (!empty($data['required'])) {
            $parsed = json_decode((string) ($response['value_json'] ?? '[]'), true) ?: [];
            if (empty($parsed)) {
                return 'Bitte wähle mindestens eine Option aus.';
            }
        }
        return null;
    }
}
