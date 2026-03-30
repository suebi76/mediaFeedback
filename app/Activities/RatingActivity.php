<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Activities;

use MediaFeedbackV2\Support\UploadManager;

class RatingActivity extends ActivityBase
{
    public function getType(): string { return 'rating'; }
    public function getName(): string { return 'Bewertung'; }
    public function getDescription(): string { return 'Skalierte Bewertung mit anpassbaren Text- oder Sternen-Labels.'; }
    public function isQuestion(): bool { return true; }

    public function defaultData(): array
    {
        return [
            'label' => 'Wie bewertest du das?', 
            'help_text' => '', 
            'display_type' => 'stars',
            'options' => ['Sehr schlecht', 'Schlecht', 'Mittel', 'Gut', 'Sehr gut'], 
            'required' => false
        ];
    }

    private function getMigratedOptions(array $data): array
    {
        $options = $data['options'] ?? [];
        if (empty($options)) {
            $scale = (int) ($data['scale'] ?? 5);
            for ($i = 1; $i <= $scale; $i++) {
                $options[] = (string) $i;
            }
        }
        return $options;
    }

    public function sanitizeEditorInput(array $input, array $files, UploadManager $uploads, ?array $currentData = null): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) ($input['options_text'] ?? '')) ?: [];
        $options = array_values(array_filter(array_map(static fn(string $line): string => trim($line), $lines)));
        
        $displayType = $input['display_type'] ?? 'stars';
        if (!in_array($displayType, ['stars', 'text'], true)) {
            $displayType = 'stars';
        }

        return ['label' => $this->text($input, 'label'), 'help_text' => $this->text($input, 'help_text'), 'display_type' => $displayType, 'options' => $options, 'required' => $this->boolValue($input, 'required')];
    }

    public function validateEditorData(array $data): array
    {
        $errors = [];
        if (($data['label'] ?? '') === '') {
            $errors[] = 'Die Bewertungsfrage braucht einen Titel.';
        }
        if (count($this->getMigratedOptions($data)) < 2) {
            $errors[] = 'Bitte mindestens zwei Skalenpunkte angeben.';
        }
        return $errors;
    }

    public function renderEditorForm(array $data): string
    {
        $options = $this->getMigratedOptions($data);
        $displayType = $data['display_type'] ?? 'stars';

        return '<div class="stack">'
            . $this->input('label', 'Fragetext', (string) ($data['label'] ?? ''), 'text', '')
            . $this->input('help_text', 'Hilfetext (optional)', (string) ($data['help_text'] ?? ''), 'text', '')
            . '<div class="field"><label>Darstellung der Skala</label><select name="display_type">'
            . '<option value="stars" ' . selected($displayType, 'stars') . '>Sterne (Stars)</option>'
            . '<option value="text" ' . selected($displayType, 'text') . '>Text-Labels</option>'
            . '</select></div>'
            . $this->textarea('options_text', 'Skalenpunkte (Ein Label pro Zeile)', implode("\n", $options), count($options) > 2 ? count($options) + 1 : 6, 'Die Anzahl der Zeilen bestimmt automatisch die Stufenanzahl der Skala.')
            . $this->checkbox('required', 'Antwort erforderlich', !empty($data['required']))
            . '</div>';
    }

    public function renderEditorSummary(array $data): string
    {
        $options = $this->getMigratedOptions($data);
        $typeLabel = ($data['display_type'] ?? 'stars') === 'stars' ? 'Sterne' : 'Text-Labels';
        return '<strong>' . \e($data['label'] ?? '') . '</strong><div class="meta">Skala: ' . count($options) . ' Stufen (' . $typeLabel . ')</div>';
    }

    public function renderPublic(array $block, array $data, mixed $oldValue = null, array $errors = []): string
    {
        $blockId = (int) $block['id'];
        $selected = (string) ($oldValue['value_text'] ?? '');
        $options = $this->getMigratedOptions($data);
        $displayType = $data['display_type'] ?? 'stars';
        
        ob_start(); ?>
        <div class="card activity-card stack">
            <?= $this->publicQuestionHeader(
                (string) ($data['label'] ?? ''),
                (string) ($data['help_text'] ?? ''),
                [count($options) . ' Stufen'],
                !empty($data['required'])
            ) ?>
            <?php if (!empty($errors[$blockId])): ?><div class="flash error"><?= \e($errors[$blockId]) ?></div><?php endif; ?>
            
            <?php if ($displayType === 'stars'): ?>
                <div class="rating-stars-container" data-choice-grid>
                    <?php for ($i = count($options) - 1; $i >= 0; $i--): ?>
                        <?php $val = (string) ($i + 1); $option = $options[$i]; ?>
                        <input type="radio" id="star-<?= $blockId ?>-<?= $val ?>" name="answers[<?= $blockId ?>]" value="<?= $val ?>" <?= $selected === $val ? 'checked' : '' ?> data-choice-input data-choice-label="<?= \e($option) ?>" class="sr-only">
                        <label for="star-<?= $blockId ?>-<?= $val ?>" title="<?= \e($option) ?>">★</label>
                    <?php endfor; ?>
                </div>
            <?php else: ?>
                <div class="public-choice-grid public-rating-grid" data-choice-grid>
                    <?php foreach ($options as $index => $option): ?>
                        <?php $val = (string) ($index + 1); ?>
                        <label class="public-choice-card public-rating-card<?= $selected === $val ? ' is-selected' : '' ?>" data-choice-card>
                            <input type="radio" name="answers[<?= $blockId ?>]" value="<?= $val ?>" <?= $selected === $val ? 'checked' : '' ?> data-choice-input data-choice-label="<?= \e($option) ?>">
                            <span class="public-rating-text" style="font-weight: 600;"><?= \e($option) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
            return 'Diese Bewertung ist erforderlich.';
        }
        return null;
    }
}
