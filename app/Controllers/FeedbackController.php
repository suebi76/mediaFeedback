<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Controllers;

use MediaFeedbackV2\Activities\ActivityRegistry;
use MediaFeedbackV2\Core\Controller;
use MediaFeedbackV2\Core\Csrf;
use MediaFeedbackV2\Models\ActivityBlock;
use MediaFeedbackV2\Models\Feedback;
use MediaFeedbackV2\Models\Response;

class FeedbackController extends Controller
{
    public function create(): void
    {
        $this->requireAuth();
        $this->render('feedback_create', [
            'title' => 'Neues Feedback',
            'csrfToken' => Csrf::token('feedback_create'),
        ]);
    }

    public function store(): void
    {
        $user = $this->requireAuth();
        $this->enforceCsrf('feedback_create');

        $title = trim((string) ($_POST['title'] ?? ''));
        $layout = (string) ($_POST['layout'] ?? 'one-per-page');
        if ($title === '') {
            $this->flash('error', 'Bitte einen Titel vergeben.');
            $this->redirect('/feedback/create');
        }
        if (!in_array($layout, ['one-per-page', 'classic'], true)) {
            $layout = 'one-per-page';
        }

        $id = (new Feedback($this->database))->create((int) $user['id'], $title, $layout);
        $this->flash('success', 'Feedback angelegt. Jetzt kannst du Blöcke hinzufügen.');
        $this->redirect('/feedback/edit?id=' . $id);
    }

    public function edit(): void
    {
        $user = $this->requireAuth();
        $feedbackId = (int) ($_GET['id'] ?? 0);
        $feedback = (new Feedback($this->database))->findOwned($feedbackId, (int) $user['id']);
        if (!$feedback) {
            $this->redirect('/dashboard');
        }

        $registry = new ActivityRegistry();
        $blocks = (new ActivityBlock($this->database))->forFeedback((int) $feedback['id']);
        $pages = [];
        foreach ($blocks as $block) {
            $block['data'] = json_decode((string) $block['activity_data'], true) ?: [];
            $block['activity'] = $registry->get($block['activity_type']);
            $pages[(int) $block['page_number']][] = $block;
        }
        if (!$pages) {
            $pages[0] = [];
        }
        ksort($pages);
        $pageSummaries = [];
        $questionCount = 0;
        $contentCount = 0;
        foreach ($pages as $pageNumber => $pageBlocks) {
            $pageQuestionCount = 0;
            $pageContentCount = 0;
            $labels = [];
            $focusLabel = '';
            foreach ($pageBlocks as $pageBlock) {
                if ($pageBlock['activity']?->isQuestion()) {
                    $pageQuestionCount++;
                    $questionCount++;
                    if ($focusLabel === '') {
                        $focusLabel = trim((string) ($pageBlock['data']['label'] ?? ''));
                    }
                } else {
                    $pageContentCount++;
                    $contentCount++;
                    if ($focusLabel === '') {
                        $focusLabel = trim((string) ($pageBlock['data']['caption'] ?? strip_tags((string) ($pageBlock['data']['content'] ?? ''))));
                    }
                }
                $labels[] = $pageBlock['activity']->getName();
            }
            $pageSummaries[$pageNumber] = [
                'block_count' => count($pageBlocks),
                'question_count' => $pageQuestionCount,
                'content_count' => $pageContentCount,
                'preview' => implode(' · ', array_slice($labels, 0, 3)),
                'focus_label' => $focusLabel,
            ];
        }
        $settings = Feedback::decodeSettings((string) ($feedback['settings_json'] ?? '{}'));
        $responseCount = (new Response($this->database))->countForFeedback((int) $feedback['id']);

        $this->render('feedback_edit', [
            'title' => 'Editor – ' . $feedback['title'],
            'feedback' => $feedback,
            'settings' => $settings,
            'pages' => $pages,
            'pageSummaries' => $pageSummaries,
            'questionCount' => $questionCount,
            'contentCount' => $contentCount,
            'responseCount' => $responseCount,
            'activities' => $registry->all(),
            'editorToken' => Csrf::token('editor'),
        ]);
    }

    public function update(): void
    {
        $user = $this->requireAuth();
        $this->enforceCsrf('editor');
        $feedbackId = (int) ($_POST['feedback_id'] ?? 0);
        $feedbackModel = new Feedback($this->database);
        $feedback = $feedbackModel->findOwned($feedbackId, (int) $user['id']);
        if (!$feedback) {
            $this->redirect('/dashboard');
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $layout = (string) ($_POST['layout'] ?? 'one-per-page');
        $description = trim((string) ($_POST['description'] ?? ''));
        $settings = [
            'intro_enabled' => isset($_POST['intro_enabled']) && (string) $_POST['intro_enabled'] === '1',
            'intro_text' => trim((string) ($_POST['intro_text'] ?? '')),
            'progress_bar' => !isset($_POST['progress_bar']) || (string) $_POST['progress_bar'] === '1',
            'estimated_time_minutes' => (int) ($_POST['estimated_time_minutes'] ?? 0),
            'limit_one_response_per_device' => isset($_POST['limit_one_response_per_device']) && (string) $_POST['limit_one_response_per_device'] === '1',
        ];

        if ($title === '') {
            $this->flash('error', 'Der Titel darf nicht leer sein.');
            $this->redirect('/feedback/edit?id=' . $feedbackId);
        }
        if (!in_array($layout, ['one-per-page', 'classic'], true)) {
            $layout = 'one-per-page';
        }

        $feedbackModel->update($feedbackId, (int) $user['id'], [
            'title' => $title,
            'layout' => $layout,
            'description' => $description,
            'settings' => $settings,
        ]);

        $this->flash('success', 'Feedback-Einstellungen gespeichert.');
        $this->redirect('/feedback/edit?id=' . $feedbackId);
    }

    public function changeStatus(): void
    {
        $user = $this->requireAuth();
        $this->enforceCsrf('editor');
        $feedbackId = (int) ($_POST['feedback_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'draft');
        if (!in_array($status, ['draft', 'live', 'closed'], true)) {
            $status = 'draft';
        }
        (new Feedback($this->database))->setStatus($feedbackId, (int) $user['id'], $status);
        $this->flash('success', 'Status aktualisiert.');
        $this->redirect('/feedback/edit?id=' . $feedbackId);
    }

    public function delete(): void
    {
        $user = $this->requireAuth();
        $this->enforceCsrf('editor');
        $feedbackId = (int) ($_POST['feedback_id'] ?? 0);
        (new Feedback($this->database))->delete($feedbackId, (int) $user['id']);
        $this->flash('success', 'Feedback gelöscht.');
        $this->redirect('/dashboard');
    }
}
