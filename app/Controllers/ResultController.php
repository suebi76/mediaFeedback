<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Controllers;

use MediaFeedbackV2\Activities\ActivityRegistry;
use MediaFeedbackV2\Core\Controller;
use MediaFeedbackV2\Models\ActivityBlock;
use MediaFeedbackV2\Models\Answer;
use MediaFeedbackV2\Models\Feedback;

class ResultController extends Controller
{
    public function index(): void
    {
        $user = $this->requireAuth();
        $feedbackId = (int) ($_GET['id'] ?? 0);
        $feedback = (new Feedback($this->database))->findOwned($feedbackId, (int) $user['id']);
        if (!$feedback) {
            $this->redirect('/dashboard');
        }

        [$questionBlocks, $responsesView, $questionsView, $exportRows] = $this->buildResultData($feedbackId);
        $resultOverview = $this->buildResultOverview($responsesView, $questionBlocks);
        $this->render('results', [
            'title' => 'Ergebnisse – ' . $feedback['title'],
            'feedback' => $feedback,
            'questionBlocks' => $questionBlocks,
            'responsesView' => $responsesView,
            'questionsView' => $questionsView,
            'responseCount' => count($responsesView),
            'questionCount' => count($questionBlocks),
            'exportRows' => $exportRows,
            'resultOverview' => $resultOverview,
        ]);
    }

    public function export(): void
    {
        $user = $this->requireAuth();
        $feedbackId = (int) ($_GET['id'] ?? 0);
        $format = (string) ($_GET['format'] ?? 'csv');
        $feedback = (new Feedback($this->database))->findOwned($feedbackId, (int) $user['id']);
        if (!$feedback) {
            $this->redirect('/dashboard');
        }

        [$questionBlocks, $responsesView, $questionsView, $rows] = $this->buildResultData($feedbackId);
        $fileBase = 'mediafeedback-' . preg_replace('/[^A-Za-z0-9-]+/', '-', $feedback['slug']);

        if ($format === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $fileBase . '.json');
            echo json_encode([
                'feedback' => $feedback,
                'questions' => $questionBlocks,
                'responses' => $responsesView,
                'questions_view' => $questionsView,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $fileBase . '.csv');
        $handle = fopen('php://output', 'wb');
        fputcsv($handle, array_merge(['response_id', 'device_hash', 'submitted_at'], array_map(static fn(array $block): string => (string) $block['label'], $questionBlocks)), ';');
        foreach ($rows as $row) {
            $line = [$row['response_id'], $row['device_hash'], $row['submitted_at']];
            foreach ($questionBlocks as $block) {
                $line[] = $row['answers'][(int) $block['id']] ?? '';
            }
            fputcsv($handle, $line, ';');
        }
        fclose($handle);
    }

    private function buildResultData(int $feedbackId): array
    {
        $registry = new ActivityRegistry();
        $blockModel = new ActivityBlock($this->database);
        $blocks = $blockModel->forFeedback($feedbackId);
        $questionBlocks = [];
        $questionIndex = [];
        foreach ($blocks as $block) {
            $activity = $registry->get((string) $block['activity_type']);
            $data = json_decode((string) $block['activity_data'], true) ?: [];
            if (!$activity || !$activity->isQuestion()) {
                continue;
            }
            $questionBlock = [
                'id' => (int) $block['id'],
                'label' => (string) ($data['label'] ?? $activity->getName()),
                'type' => $block['activity_type'],
                'type_label' => $this->questionTypeLabel((string) $block['activity_type']),
                'help_text' => (string) ($data['help_text'] ?? ''),
                'page_number' => (int) ($block['page_number'] ?? 0),
                'sort_order' => (int) ($block['sort_order'] ?? 0),
                'data' => $data,
                'responses' => [],
            ];
            $questionBlocks[] = $questionBlock;
            $questionIndex[(int) $questionBlock['id']] = count($questionBlocks) - 1;
        }

        $responses = (new Answer($this->database))->groupedForFeedback($feedbackId);
        $answerMap = [];
        $responseMeta = [];
        $orphanAnswers = [];
        foreach ($responses as $answerRow) {
            $responseId = (int) $answerRow['response_id'];
            $responseMeta[$responseId] = [
                'response_id' => $responseId,
                'device_hash' => $answerRow['device_hash'] ?? null,
                'submitted_at' => (string) $answerRow['submitted_at'],
            ];
            if ($answerRow['block_id'] === null) {
                continue;
            }

            $blockId = (int) $answerRow['block_id'];
            $answerMap[$responseId][$blockId] = $answerRow;
            if (!array_key_exists($blockId, $questionIndex)) {
                $orphanAnswers[$blockId] = $answerRow;
            }
        }

        foreach ($orphanAnswers as $blockId => $answerRow) {
            $archivedBlock = $this->buildArchivedQuestionBlock($blockModel->find($blockId), $answerRow, count($questionBlocks));
            $questionBlocks[] = $archivedBlock;
            $questionIndex[$blockId] = count($questionBlocks) - 1;
        }

        $responsesView = [];
        $exportRows = [];
        $questionTotal = count($questionBlocks);

        foreach ($responseMeta as $responseId => $meta) {
            $responseEntry = $meta + [
                'answers' => [],
                'answered_count' => 0,
                'media_answer_count' => 0,
                'completion_rate' => 0,
            ];
            $exportEntry = [
                'response_id' => $responseId,
                'device_hash' => $meta['device_hash'],
                'submitted_at' => $meta['submitted_at'],
                'answers' => [],
            ];

            foreach ($questionBlocks as $questionBlock) {
                $blockId = (int) $questionBlock['id'];
                $normalized = $this->normalizeAnswer($questionBlock, $answerMap[$responseId][$blockId] ?? null);
                $responseEntry['answers'][$blockId] = $normalized;
                $exportEntry['answers'][$blockId] = $this->answerExportValue($normalized);

                if ($normalized['has_answer']) {
                    $responseEntry['answered_count']++;
                    if ($normalized['display_type'] === 'media') {
                        $responseEntry['media_answer_count']++;
                    }
                    $questionBlocks[$questionIndex[$blockId]]['responses'][] = [
                        'response_id' => $responseId,
                        'device_hash' => $meta['device_hash'],
                        'submitted_at' => $meta['submitted_at'],
                        'answer' => $normalized,
                    ];
                }
            }

            $responseEntry['completion_rate'] = $questionTotal > 0
                ? (int) round(($responseEntry['answered_count'] / $questionTotal) * 100)
                : 0;
            $responsesView[] = $responseEntry;
            $exportRows[] = $exportEntry;
        }

        foreach ($questionBlocks as &$questionBlock) {
            $answeredCount = count($questionBlock['responses'] ?? []);
            $questionBlock['answered_count'] = $answeredCount;
            $questionBlock['answer_rate'] = count($responsesView) > 0
                ? (int) round(($answeredCount / count($responsesView)) * 100)
                : 0;
            $questionBlock['insight'] = $this->buildQuestionInsight($questionBlock);
        }
        unset($questionBlock);

        return [$questionBlocks, $responsesView, $questionBlocks, $exportRows];
    }

    private function buildResultOverview(array $responsesView, array $questionBlocks): array
    {
        $responseCount = count($responsesView);
        $questionCount = count($questionBlocks);
        $answeredTotal = 0;
        $mediaAnswerTotal = 0;
        $completionTotal = 0;

        foreach ($responsesView as $response) {
            $answeredTotal += (int) ($response['answered_count'] ?? 0);
            $mediaAnswerTotal += (int) ($response['media_answer_count'] ?? 0);
            $completionTotal += (int) ($response['completion_rate'] ?? 0);
        }

        $typeBreakdown = [
            'open_question' => 0,
            'rating' => 0,
            'single_choice' => 0,
            'multiple_choice' => 0,
        ];
        foreach ($questionBlocks as $questionBlock) {
            $type = (string) ($questionBlock['type'] ?? '');
            if (array_key_exists($type, $typeBreakdown)) {
                $typeBreakdown[$type]++;
            }
        }

        return [
            'average_completion_rate' => $responseCount > 0 ? (int) round($completionTotal / $responseCount) : 0,
            'answered_total' => $answeredTotal,
            'media_answer_total' => $mediaAnswerTotal,
            'text_answer_total' => max(0, $answeredTotal - $mediaAnswerTotal),
            'average_answers_per_response' => $responseCount > 0 ? round($answeredTotal / $responseCount, 1) : 0,
            'type_breakdown' => $typeBreakdown,
            'response_count' => $responseCount,
            'question_count' => $questionCount,
        ];
    }

    private function buildArchivedQuestionBlock(?array $block, array $answerRow, int $fallbackIndex): array
    {
        $data = [];
        $type = $this->inferAnswerType($answerRow);
        $label = 'Frühere Antwort';
        $pageNumber = 0;
        $sortOrder = 999 + $fallbackIndex;

        if ($block) {
            $data = json_decode((string) $block['activity_data'], true) ?: [];
            $label = (string) ($data['label'] ?? $data['title'] ?? $label);
            $pageNumber = (int) ($block['page_number'] ?? 0);
            $sortOrder = (int) ($block['sort_order'] ?? $sortOrder);
        }

        return [
            'id' => (int) ($answerRow['block_id'] ?? 0),
            'label' => $label,
            'type' => $type,
            'type_label' => $this->questionTypeLabel($type),
            'help_text' => 'Dieser Fragebaustein wurde nach eingegangenen Antworten verändert oder entfernt. Die vorhandenen Antworten bleiben hier weiterhin sichtbar.',
            'page_number' => $pageNumber,
            'sort_order' => $sortOrder,
            'data' => $data,
            'responses' => [],
            'is_archived' => true,
        ];
    }

    private function inferAnswerType(array $answerRow): string
    {
        if (!empty($answerRow['media_path'])) {
            return 'open_question';
        }

        if (is_string($answerRow['value_text'] ?? null) && preg_match('/^\d+$/', trim((string) $answerRow['value_text'])) === 1) {
            return 'rating';
        }

        return 'open_question';
    }

    private function questionTypeLabel(string $type): string
    {
        return match ($type) {
            'open_question' => 'Offene Antwort',
            'rating' => 'Bewertung',
            'single_choice' => 'Single Choice',
            'multiple_choice' => 'Multiple Choice',
            default => $type,
        };
    }

    private function buildQuestionInsight(array $questionBlock): array
    {
        $responses = $questionBlock['responses'] ?? [];
        $answeredCount = count($responses);
        if ($answeredCount === 0) {
            return [
                'headline' => 'Noch keine Antworten vorhanden.',
                'badges' => ['0 Antworten'],
            ];
        }

        $type = (string) ($questionBlock['type'] ?? '');
        if ($type === 'rating') {
            $values = [];
            foreach ($responses as $entry) {
                $value = (int) ($entry['answer']['rating_value'] ?? 0);
                if ($value > 0) {
                    $values[] = $value;
                }
            }
            if ($values !== []) {
                $average = round(array_sum($values) / count($values), 1);
                $distribution = array_count_values($values);
                arsort($distribution);
                $topValue = (int) array_key_first($distribution);
                return [
                    'headline' => 'Durchschnitt ' . $average . ' bei ' . count($values) . ' Bewertung(en).',
                    'badges' => ['Top-Wert ' . $topValue, 'Skala ' . ((int) ($questionBlock['data']['scale'] ?? 5))],
                ];
            }
        }

        if ($type === 'single_choice' || $type === 'multiple_choice') {
            $counts = [];
            foreach ($responses as $entry) {
                if ($type === 'multiple_choice') {
                    $choices = (array) ($entry['answer']['multiple_choices'] ?? []);
                    foreach ($choices as $c) {
                        $choice = trim((string) $c);
                        if ($choice !== '') {
                            $counts[$choice] = ($counts[$choice] ?? 0) + 1;
                        }
                    }
                } else {
                    $choice = trim((string) ($entry['answer']['choice_value'] ?? ''));
                    if ($choice !== '') {
                        $counts[$choice] = ($counts[$choice] ?? 0) + 1;
                    }
                }
            }
            if ($counts !== []) {
                arsort($counts);
                $topChoice = (string) array_key_first($counts);
                $topCount = (int) current($counts);
                return [
                    'headline' => '"' . $topChoice . '" wurde am häufigsten gewählt.',
                    'badges' => [$topCount . ' Nennungen', count($counts) . ' aktive Optionen'],
                ];
            }
        }

        $textCount = 0;
        $mediaCount = 0;
        foreach ($responses as $entry) {
            if (($entry['answer']['display_type'] ?? '') === 'media') {
                $mediaCount++;
            } else {
                $textCount++;
            }
        }

        return [
            'headline' => $mediaCount > 0
                ? $textCount . ' Textantwort(en) und ' . $mediaCount . ' Medienantwort(en).'
                : $textCount . ' Textantwort(en) eingegangen.',
            'badges' => [$answeredCount . ' Antworten', $mediaCount > 0 ? $mediaCount . ' Medien' : 'Ohne Medien'],
        ];
    }

    private function normalizeAnswer(array $questionBlock, ?array $answerRow): array
    {
        $empty = [
            'has_answer' => false,
            'display_type' => 'empty',
            'text' => '',
            'supplemental_text' => '',
            'media_path' => null,
            'media_kind' => null,
            'media_name' => null,
            'rating_value' => null,
            'choice_value' => null,
            'json_value' => null,
        ];

        if ($answerRow === null) {
            return $empty;
        }

        $valueText = trim((string) ($answerRow['value_text'] ?? ''));
        $valueJson = $answerRow['value_json'] ?? null;
        $mediaPath = $answerRow['media_path'] ?? null;
        $type = (string) ($questionBlock['type'] ?? '');

        if ($mediaPath !== null && $mediaPath !== '') {
            return [
                'has_answer' => true,
                'display_type' => 'media',
                'text' => $valueText,
                'supplemental_text' => $valueText,
                'media_path' => (string) $mediaPath,
                'media_kind' => $this->detectMediaKind((string) $mediaPath),
                'media_name' => basename((string) $mediaPath),
                'rating_value' => null,
                'choice_value' => null,
                'json_value' => $valueJson,
            ];
        }

        if ($type === 'rating' && $valueText !== '') {
            return [
                'has_answer' => true,
                'display_type' => 'rating',
                'text' => $valueText,
                'supplemental_text' => '',
                'media_path' => null,
                'media_kind' => null,
                'media_name' => null,
                'rating_value' => (int) $valueText,
                'choice_value' => null,
                'json_value' => $valueJson,
            ];
        }

        if ($type === 'single_choice' && $valueText !== '') {
            return [
                'has_answer' => true,
                'display_type' => 'choice',
                'text' => $valueText,
                'supplemental_text' => '',
                'media_path' => null,
                'media_kind' => null,
                'media_name' => null,
                'rating_value' => null,
                'choice_value' => $valueText,
                'json_value' => $valueJson,
            ];
        }

        if ($type === 'multiple_choice' && $valueJson !== null) {
            $parsed = json_decode((string) $valueJson, true);
            if (is_array($parsed) && $parsed !== []) {
                return [
                    'has_answer' => true,
                    'display_type' => 'multiple_choice',
                    'text' => implode(', ', $parsed),
                    'supplemental_text' => '',
                    'media_path' => null,
                    'media_kind' => null,
                    'media_name' => null,
                    'rating_value' => null,
                    'choice_value' => null,
                    'json_value' => $valueJson,
                    'multiple_choices' => $parsed,
                ];
            }
        }

        if ($valueText !== '') {
            return [
                'has_answer' => true,
                'display_type' => 'text',
                'text' => $valueText,
                'supplemental_text' => '',
                'media_path' => null,
                'media_kind' => null,
                'media_name' => null,
                'rating_value' => null,
                'choice_value' => null,
                'json_value' => $valueJson,
            ];
        }

        if ($valueJson !== null && $valueJson !== '') {
            return [
                'has_answer' => true,
                'display_type' => 'json',
                'text' => (string) $valueJson,
                'supplemental_text' => '',
                'media_path' => null,
                'media_kind' => null,
                'media_name' => null,
                'rating_value' => null,
                'choice_value' => null,
                'json_value' => $valueJson,
            ];
        }

        return $empty;
    }

    private function detectMediaKind(string $mediaPath): string
    {
        $extension = strtolower((string) pathinfo($mediaPath, PATHINFO_EXTENSION));
        if (in_array($extension, ['webm', 'mp4', 'mov', 'm4v'], true)) {
            return 'video';
        }
        if (in_array($extension, ['mp3', 'wav', 'm4a', 'weba', 'ogg'], true)) {
            return 'audio';
        }
        return 'file';
    }

    private function answerExportValue(array $answer): string
    {
        if (!$answer['has_answer']) {
            return '';
        }

        return match ($answer['display_type']) {
            'rating' => 'Bewertung: ' . $answer['rating_value'],
            'choice', 'text' => (string) $answer['text'],
            'json' => (string) $answer['text'],
            'media' => trim(($answer['supplemental_text'] !== '' ? $answer['supplemental_text'] . ' ' : '') . '[Datei: ' . ($answer['media_name'] ?? $answer['media_path']) . ']'),
            default => (string) ($answer['text'] ?? ''),
        };
    }
}
