<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Controllers;

use MediaFeedbackV2\Activities\ActivityRegistry;
use MediaFeedbackV2\Core\Auth;
use MediaFeedbackV2\Core\Controller;
use MediaFeedbackV2\Models\ActivityBlock;
use MediaFeedbackV2\Models\Answer;
use MediaFeedbackV2\Models\Feedback;
use MediaFeedbackV2\Models\Media;
use MediaFeedbackV2\Models\Response;
use MediaFeedbackV2\Support\UploadManager;

class PublicFeedbackController extends Controller
{
    public function show(): void
    {
        $slug = trim((string) ($_GET['slug'] ?? ''));
        $feedback = (new Feedback($this->database))->findBySlug($slug);
        if (!$feedback) {
            http_response_code(404);
            $this->render('message', ['title' => 'Nicht gefunden', 'message' => 'Das angeforderte Feedback wurde nicht gefunden.']);
            return;
        }

        $currentUser = Auth::check() ? Auth::user($this->database) : null;
        $isOwnerPreview = $currentUser && (int) $currentUser['id'] === (int) $feedback['user_id'];
        if ($feedback['status'] !== 'live' && !$isOwnerPreview) {
            $this->render('message', ['title' => 'Nicht veröffentlicht', 'message' => 'Dieses Feedback ist aktuell nicht live geschaltet.']);
            return;
        }

        $settings = Feedback::decodeSettings((string) ($feedback['settings_json'] ?? '{}'));
        $prepared = $this->preparedBlocks((int) $feedback['id']);
        $pages = [];
        foreach ($prepared as $block) {
            $pages[(int) $block['page_number']][] = $block;
        }
        if (!$pages) {
            $pages[0] = [];
        }
        ksort($pages);

        $token = bin2hex(random_bytes(18));
        $_SESSION['public_form_token_' . $feedback['id']] = $token;
        $showIntroOnly = !empty($settings['intro_enabled']) && (string) ($_GET['start'] ?? '') !== '1';

        $this->render('public_form', [
            'title' => $feedback['title'],
            'feedback' => $feedback,
            'settings' => $settings,
            'pages' => $pages,
            'submitToken' => $token,
            'errorsByBlock' => [],
            'oldAnswers' => [],
            'preview' => $isOwnerPreview && $feedback['status'] !== 'live',
            'showIntroOnly' => $showIntroOnly,
        ]);
    }

    public function submit(): void
    {
        $feedbackId = (int) ($_POST['feedback_id'] ?? 0);
        $feedback = (new Feedback($this->database))->findBySlug((string) ($_POST['feedback_slug'] ?? ''));
        if (!$feedback || (int) $feedback['id'] !== $feedbackId) {
            http_response_code(400);
            echo 'Ungültiges Feedback.';
            return;
        }

        $currentUser = Auth::check() ? Auth::user($this->database) : null;
        $isOwnerPreview = $currentUser && (int) $currentUser['id'] === (int) $feedback['user_id'];
        if ($feedback['status'] !== 'live' && !$isOwnerPreview) {
            http_response_code(403);
            echo 'Feedback nicht freigegeben.';
            return;
        }

        $submittedToken = (string) ($_POST['submit_token'] ?? '');
        $expectedToken = $_SESSION['public_form_token_' . $feedbackId] ?? null;
        if (!is_string($expectedToken) || !hash_equals($expectedToken, $submittedToken)) {
            http_response_code(422);
            echo 'Das Formular wurde bereits verwendet oder ist ungültig.';
            return;
        }

        $settings = Feedback::decodeSettings((string) ($feedback['settings_json'] ?? '{}'));
        $deviceHash = trim((string) ($_POST['device_hash'] ?? ''));
        $responseModel = new Response($this->database);

        if (!empty($settings['limit_one_response_per_device'])) {
            if ($deviceHash === '') {
                $this->render('message', [
                    'title' => 'Gerät nicht erkannt',
                    'message' => 'Für dieses Feedback ist nur eine Antwort pro Gerät erlaubt. Bitte aktiviere JavaScript und versuche es erneut.',
                ]);
                return;
            }

            if ($responseModel->existsForFeedbackDevice($feedbackId, $deviceHash)) {
                $this->render('message', [
                    'title' => 'Bereits teilgenommen',
                    'message' => 'Für dieses Feedback wurde auf diesem Gerät bereits eine Antwort abgegeben.',
                ]);
                return;
            }
        }

        $prepared = $this->preparedBlocks($feedbackId);
        $uploads = new UploadManager();
        $errors = [];
        $capturedAnswers = [];

        foreach ($prepared as $block) {
            $activity = $block['activity'];
            if (!$activity || !$activity->isQuestion()) {
                continue;
            }

            try {
                $answer = $activity->extractResponse((int) $block['id'], $_POST, $_FILES, $uploads);
                $error = $activity->validateResponse($block['data'], $answer);
                if ($error !== null) {
                    $errors[(int) $block['id']] = $error;
                }
                $capturedAnswers[(int) $block['id']] = $answer;
            } catch (\Throwable $throwable) {
                $errors[(int) $block['id']] = $throwable->getMessage();
            }
        }

        if ($errors) {
            $pages = [];
            foreach ($prepared as $block) {
                $pages[(int) $block['page_number']][] = $block;
            }
            ksort($pages);
            $this->render('public_form', [
                'title' => $feedback['title'],
                'feedback' => $feedback,
                'settings' => $settings,
                'pages' => $pages,
                'submitToken' => $expectedToken,
                'errorsByBlock' => $errors,
                'oldAnswers' => $capturedAnswers,
                'preview' => $isOwnerPreview && $feedback['status'] !== 'live',
                'showIntroOnly' => false,
            ]);
            return;
        }

        unset($_SESSION['public_form_token_' . $feedbackId]);
        $answerModel = new Answer($this->database);
        $mediaModel = new Media($this->database);

        $this->database->transaction(function () use ($feedbackId, $capturedAnswers, $responseModel, $answerModel, $mediaModel, $deviceHash): void {
            $responseId = $responseModel->create($feedbackId, bin2hex(random_bytes(16)), $deviceHash !== '' ? $deviceHash : null);
            foreach ($capturedAnswers as $blockId => $answer) {
                if (($answer['value_text'] ?? null) === null && ($answer['value_json'] ?? null) === null && ($answer['media_path'] ?? null) === null) {
                    continue;
                }
                $answerModel->create($responseId, $blockId, $answer['value_text'] ?? null, $answer['value_json'] ?? null, $answer['media_path'] ?? null);
                if (!empty($answer['media_path'])) {
                    $mediaModel->record($feedbackId, $blockId, $responseId, 'response', (string) $answer['media_path'], (string) $answer['media_path'], '', 0);
                }
            }
        });

        $this->render('public_submitted', [
            'title' => 'Vielen Dank',
            'feedback' => $feedback,
        ]);
    }

    private function preparedBlocks(int $feedbackId): array
    {
        $registry = new ActivityRegistry();
        $blocks = (new ActivityBlock($this->database))->forFeedback($feedbackId);
        foreach ($blocks as &$block) {
            $block['data'] = json_decode((string) $block['activity_data'], true) ?: [];
            $block['activity'] = $registry->get((string) $block['activity_type']);
        }
        unset($block);
        return $blocks;
    }
}
