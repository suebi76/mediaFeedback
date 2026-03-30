<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Controllers;

use MediaFeedbackV2\Activities\ActivityRegistry;
use MediaFeedbackV2\Core\Controller;
use MediaFeedbackV2\Models\ActivityBlock;
use MediaFeedbackV2\Models\Feedback;
use MediaFeedbackV2\Models\Media;
use MediaFeedbackV2\Support\UploadManager;

class ActivityController extends Controller
{
    public function add(): void
    {
        $user = $this->requireAuth();
        $this->enforceCsrf('editor');

        $feedbackId = (int) ($_POST['feedback_id'] ?? 0);
        $type = (string) ($_POST['type'] ?? '');
        $pageTarget = (string) ($_POST['page_target'] ?? 'existing');
        $pageNumber = max(0, (int) ($_POST['page_number'] ?? 0));
        $pageTargetChoice = (string) ($_POST['page_target_choice'] ?? '');
        if ($pageTargetChoice !== '') {
            if ($pageTargetChoice === 'new') {
                $pageTarget = 'new';
            } else {
                $pageTarget = 'existing';
                $pageNumber = max(0, (int) $pageTargetChoice);
            }
        }

        $feedback = (new Feedback($this->database))->findOwned($feedbackId, (int) $user['id']);
        $activity = (new ActivityRegistry())->get($type);
        if (!$feedback || !$activity) {
            $this->redirect('/dashboard');
        }

        $blockModel = new ActivityBlock($this->database);
        if ($pageTarget === 'new') {
            $pageNumber = $blockModel->maxPageNumber($feedbackId) + 1;
        }
        
        $insertAt = isset($_POST['insert_at']) && $_POST['insert_at'] !== '' ? (int) $_POST['insert_at'] : null;
        if ($insertAt !== null) {
            $stmt = $this->database->connect()->prepare('UPDATE activity_blocks SET sort_order = sort_order + 1 WHERE feedback_id = ? AND page_number = ? AND sort_order >= ?');
            $stmt->execute([$feedbackId, $pageNumber, $insertAt]);
            $sortOrder = $insertAt;
        } else {
            $sortOrder = $blockModel->maxSortOrder($feedbackId, $pageNumber) + 1;
        }
        
        $blockModel->create($feedbackId, $type, $pageNumber, $sortOrder, $activity->defaultData());
        $this->flash('success', $pageTarget === 'new' ? 'Neue Seite begonnen.' : 'Baustein hinzugefügt.');
        $this->redirect('/feedback/edit?id=' . $feedbackId);
    }

    public function update(): void
    {
        $user = $this->requireAuth();
        $this->enforceCsrf('editor');

        $blockId = (int) ($_POST['block_id'] ?? 0);
        $blockModel = new ActivityBlock($this->database);
        $block = $blockModel->find($blockId);
        if (!$block) {
            $this->redirect('/dashboard');
        }

        $feedback = (new Feedback($this->database))->findOwned((int) $block['feedback_id'], (int) $user['id']);
        if (!$feedback) {
            $this->redirect('/dashboard');
        }

        $activity = (new ActivityRegistry())->get((string) $block['activity_type']);
        if (!$activity) {
            $this->flash('error', 'Aktivitätstyp nicht gefunden.');
            $this->redirect('/feedback/edit?id=' . $block['feedback_id']);
        }

        $currentData = json_decode((string) $block['activity_data'], true) ?: [];
        $uploads = new UploadManager();
        $originalFile = $_FILES['media'] ?? null;

        try {
            $data = $activity->sanitizeEditorInput($_POST, $_FILES, $uploads, $currentData);
            $errors = $activity->validateEditorData($data);
            if ($errors) {
                $this->flash('error', implode(' ', $errors));
                $this->redirect('/feedback/edit?id=' . $block['feedback_id']);
            }

            $blockModel->update($blockId, $data);
            if ($originalFile && ($originalFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && !empty($data['media_path'])) {
                (new Media($this->database))->record((int) $block['feedback_id'], $blockId, null, (string) $block['activity_type'], (string) $data['media_path'], (string) $originalFile['name'], (string) ($originalFile['type'] ?? ''), (int) ($originalFile['size'] ?? 0));
            }
        $this->flash('success', 'Baustein gespeichert.');
        } catch (\Throwable $throwable) {
            $this->flash('error', $throwable->getMessage());
        }

        $this->redirect('/feedback/edit?id=' . $block['feedback_id']);
    }

    public function delete(): void
    {
        $user = $this->requireAuth();
        $this->enforceCsrf('editor');
        $blockId = (int) ($_POST['block_id'] ?? 0);
        $blockModel = new ActivityBlock($this->database);
        $block = $blockModel->find($blockId);
        if (!$block) {
            $this->redirect('/dashboard');
        }
        $feedback = (new Feedback($this->database))->findOwned((int) $block['feedback_id'], (int) $user['id']);
        if (!$feedback) {
            $this->redirect('/dashboard');
        }
        $blockModel->delete($blockId);
        $blockModel->resequence((int) $block['feedback_id']);
        $this->flash('success', 'Baustein gelöscht.');
        $this->redirect('/feedback/edit?id=' . $block['feedback_id']);
    }

    public function duplicate(): void
    {
        $user = $this->requireAuth();
        $this->enforceCsrf('editor');

        $blockId = (int) ($_POST['block_id'] ?? 0);
        $blockModel = new ActivityBlock($this->database);
        $block = $blockModel->find($blockId);
        if (!$block) {
            $this->redirect('/dashboard');
        }

        $feedback = (new Feedback($this->database))->findOwned((int) $block['feedback_id'], (int) $user['id']);
        if (!$feedback) {
            $this->redirect('/dashboard');
        }

        $duplicatedId = $blockModel->duplicate($blockId);
        if ($duplicatedId === null) {
            $this->flash('error', 'Der Baustein konnte nicht kopiert werden.');
        } else {
            $this->flash('success', 'Baustein kopiert.');
        }

        $this->redirect('/feedback/edit?id=' . $block['feedback_id']);
    }

    public function move(): void
    {
        $user = $this->requireAuth();
        $this->enforceCsrf('editor');
        $blockId = (int) ($_POST['block_id'] ?? 0);
        $direction = (string) ($_POST['direction'] ?? 'up');
        $blockModel = new ActivityBlock($this->database);
        $block = $blockModel->find($blockId);
        if (!$block) {
            $this->redirect('/dashboard');
        }
        $feedback = (new Feedback($this->database))->findOwned((int) $block['feedback_id'], (int) $user['id']);
        if (!$feedback) {
            $this->redirect('/dashboard');
        }
        $blockModel->moveWithinPage($blockId, $direction === 'down' ? 'down' : 'up');
        $this->redirect('/feedback/edit?id=' . $block['feedback_id']);
    }

    public function movePage(): void
    {
        $user = $this->requireAuth();
        $this->enforceCsrf('editor');

        $blockId = (int) ($_POST['block_id'] ?? 0);
        $direction = (string) ($_POST['direction'] ?? 'next');
        $blockModel = new ActivityBlock($this->database);
        $block = $blockModel->find($blockId);
        if (!$block) {
            $this->redirect('/dashboard');
        }

        $feedback = (new Feedback($this->database))->findOwned((int) $block['feedback_id'], (int) $user['id']);
        if (!$feedback) {
            $this->redirect('/dashboard');
        }

        $blockModel->moveToAdjacentPage($blockId, $direction === 'previous' ? 'previous' : 'next');
        $blockModel->resequence((int) $block['feedback_id']);
        $this->flash('success', $direction === 'previous' ? 'Baustein auf die vorige Seite verschoben.' : 'Baustein auf die nächste Seite verschoben.');
        $this->redirect('/feedback/edit?id=' . $block['feedback_id']);
    }

    public function splitAtBlock(): void
    {
        $user = $this->requireAuth();
        $this->enforceCsrf('editor');

        $blockId = (int) ($_POST['block_id'] ?? 0);
        $blockModel = new ActivityBlock($this->database);
        $block = $blockModel->find($blockId);
        if (!$block) {
            $this->redirect('/dashboard');
        }

        $feedback = (new Feedback($this->database))->findOwned((int) $block['feedback_id'], (int) $user['id']);
        if (!$feedback) {
            $this->redirect('/dashboard');
        }

        $blockModel->splitFromBlock($blockId);
        $blockModel->resequence((int) $block['feedback_id']);
        $this->flash('success', 'Neue Seite ab diesem Baustein begonnen.');
        $this->redirect('/feedback/edit?id=' . $block['feedback_id']);
    }

    public function reorder(): void
    {
        $user = $this->requireAuth();
        $this->enforceCsrf('editor');

        $feedbackId = (int) ($_POST['feedback_id'] ?? 0);
        $orderJson = (string) ($_POST['order_json'] ?? '[]');
        $order = json_decode($orderJson, true);

        $feedback = (new Feedback($this->database))->findOwned($feedbackId, (int) $user['id']);
        if (!$feedback) {
            $this->redirect('/dashboard');
        }

        if (!is_array($order) || $order === []) {
            $this->flash('error', 'Die neue Sortierung war ungültig.');
            $this->redirect('/feedback/edit?id=' . $feedbackId);
        }

        try {
            $blockModel = new ActivityBlock($this->database);
            if (isset($order[0]) && is_array($order[0]) && array_key_exists('ordered_ids', $order[0])) {
                $blockModel->reorderFeedback($feedbackId, $order);
            } else {
                $pageNumber = (int) ($_POST['page_number'] ?? 0);
                $blockModel->reorderPage($feedbackId, $pageNumber, $order);
            }
            $this->flash('success', 'Sortierung aktualisiert.');
        } catch (\Throwable $throwable) {
            $this->flash('error', $throwable->getMessage());
        }

        $this->redirect('/feedback/edit?id=' . $feedbackId);
    }

    public function insertPageBreak(): void
    {
        $user = $this->requireAuth();
        $this->enforceCsrf('editor');
        $feedbackId = (int) ($_POST['feedback_id'] ?? 0);
        $pageNumber = max(0, (int) ($_POST['page_number'] ?? 0));
        $feedback = (new Feedback($this->database))->findOwned($feedbackId, (int) $user['id']);
        if (!$feedback) {
            $this->redirect('/dashboard');
        }
        $blockModel = new ActivityBlock($this->database);
        $blockModel->shiftPagesAfter($feedbackId, $pageNumber);
        $blockModel->resequence($feedbackId);
        $this->flash('success', 'Seitenstruktur vorbereitet.');
        $this->redirect('/feedback/edit?id=' . $feedbackId);
    }

    public function mergePage(): void
    {
        $user = $this->requireAuth();
        $this->enforceCsrf('editor');
        $feedbackId = (int) ($_POST['feedback_id'] ?? 0);
        $pageNumber = max(1, (int) ($_POST['page_number'] ?? 1));
        $feedback = (new Feedback($this->database))->findOwned($feedbackId, (int) $user['id']);
        if (!$feedback) {
            $this->redirect('/dashboard');
        }
        $blockModel = new ActivityBlock($this->database);
        $blockModel->mergePageIntoPrevious($feedbackId, $pageNumber);
        $blockModel->resequence($feedbackId);
        $this->flash('success', 'Seiten zusammengeführt.');
        $this->redirect('/feedback/edit?id=' . $feedbackId);
    }
}
