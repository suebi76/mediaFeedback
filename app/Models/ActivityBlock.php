<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Models;

class ActivityBlock extends BaseModel
{
    public function forFeedback(int $feedbackId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM activity_blocks WHERE feedback_id = ? ORDER BY page_number ASC, sort_order ASC, id ASC');
        $stmt->execute([$feedbackId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM activity_blocks WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(int $feedbackId, string $type, int $pageNumber, int $sortOrder, array $activityData): int
    {
        $stmt = $this->pdo()->prepare('INSERT INTO activity_blocks (feedback_id, activity_type, activity_data, sort_order, page_number, is_required, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$feedbackId, $type, json_encode($activityData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $sortOrder, $pageNumber, !empty($activityData['required']) ? 1 : 0, date('c'), date('c')]);
        return (int) $this->pdo()->lastInsertId();
    }

    public function update(int $id, array $activityData): void
    {
        $stmt = $this->pdo()->prepare('UPDATE activity_blocks SET activity_data = ?, is_required = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([json_encode($activityData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), !empty($activityData['required']) ? 1 : 0, date('c'), $id]);
    }

    public function duplicate(int $id): ?int
    {
        $block = $this->find($id);
        if (!$block) {
            return null;
        }

        $this->pdo()->prepare(
            'UPDATE activity_blocks
             SET sort_order = sort_order + 1, updated_at = ?
             WHERE feedback_id = ? AND page_number = ? AND sort_order > ?'
        )->execute([
            date('c'),
            (int) $block['feedback_id'],
            (int) $block['page_number'],
            (int) $block['sort_order'],
        ]);

        $stmt = $this->pdo()->prepare(
            'INSERT INTO activity_blocks (feedback_id, activity_type, activity_data, sort_order, page_number, is_required, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $block['feedback_id'],
            (string) $block['activity_type'],
            (string) $block['activity_data'],
            (int) $block['sort_order'] + 1,
            (int) $block['page_number'],
            (int) $block['is_required'],
            date('c'),
            date('c'),
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo()->prepare('DELETE FROM activity_blocks WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function maxPageNumber(int $feedbackId): int
    {
        $stmt = $this->pdo()->prepare('SELECT COALESCE(MAX(page_number), 0) FROM activity_blocks WHERE feedback_id = ?');
        $stmt->execute([$feedbackId]);
        return (int) $stmt->fetchColumn();
    }

    public function maxSortOrder(int $feedbackId, int $pageNumber): int
    {
        $stmt = $this->pdo()->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM activity_blocks WHERE feedback_id = ? AND page_number = ?');
        $stmt->execute([$feedbackId, $pageNumber]);
        return (int) $stmt->fetchColumn();
    }

    public function shiftPagesAfter(int $feedbackId, int $pageNumber): void
    {
        $stmt = $this->pdo()->prepare('UPDATE activity_blocks SET page_number = page_number + 1 WHERE feedback_id = ? AND page_number > ?');
        $stmt->execute([$feedbackId, $pageNumber]);
    }

    public function mergePageIntoPrevious(int $feedbackId, int $pageNumber): void
    {
        $stmt = $this->pdo()->prepare('UPDATE activity_blocks SET page_number = page_number - 1 WHERE feedback_id = ? AND page_number >= ?');
        $stmt->execute([$feedbackId, $pageNumber]);
    }

    public function resequence(int $feedbackId): void
    {
        $blocks = $this->forFeedback($feedbackId);
        $positions = [];
        $pageMap = [];
        $nextPageNumber = 0;
        foreach ($blocks as $block) {
            $originalPage = (int) $block['page_number'];
            if (!array_key_exists($originalPage, $pageMap)) {
                $pageMap[$originalPage] = $nextPageNumber;
                $nextPageNumber++;
            }

            $page = $pageMap[$originalPage];
            $positions[$page] = ($positions[$page] ?? 0);
            $stmt = $this->pdo()->prepare('UPDATE activity_blocks SET page_number = ?, sort_order = ?, updated_at = ? WHERE id = ?');
            $stmt->execute([$page, $positions[$page], date('c'), (int) $block['id']]);
            $positions[$page]++;
        }
    }

    public function moveWithinPage(int $blockId, string $direction): void
    {
        $block = $this->find($blockId);
        if (!$block) {
            return;
        }

        $comparison = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'DESC' : 'ASC';
        $stmt = $this->pdo()->prepare("SELECT * FROM activity_blocks WHERE feedback_id = ? AND page_number = ? AND sort_order $comparison ? ORDER BY sort_order $order LIMIT 1");
        $stmt->execute([(int) $block['feedback_id'], (int) $block['page_number'], (int) $block['sort_order']]);
        $swap = $stmt->fetch();
        if (!$swap) {
            return;
        }

        $first = $this->pdo()->prepare('UPDATE activity_blocks SET sort_order = ?, updated_at = ? WHERE id = ?');
        $first->execute([(int) $swap['sort_order'], date('c'), (int) $block['id']]);
        $second = $this->pdo()->prepare('UPDATE activity_blocks SET sort_order = ?, updated_at = ? WHERE id = ?');
        $second->execute([(int) $block['sort_order'], date('c'), (int) $swap['id']]);
    }

    public function reorderPage(int $feedbackId, int $pageNumber, array $orderedIds): void
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id FROM activity_blocks WHERE feedback_id = ? AND page_number = ? ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([$feedbackId, $pageNumber]);
        $existingIds = array_map('intval', array_column($stmt->fetchAll(), 'id'));
        $incomingIds = array_map('intval', $orderedIds);

        sort($existingIds);
        $sortedIncoming = $incomingIds;
        sort($sortedIncoming);

        if ($existingIds !== $sortedIncoming) {
            throw new \RuntimeException('Die uebermittelte Sortierung passt nicht zur Seite.');
        }

        $update = $this->pdo()->prepare('UPDATE activity_blocks SET sort_order = ?, updated_at = ? WHERE id = ?');
        foreach ($incomingIds as $index => $id) {
            $update->execute([$index, date('c'), $id]);
        }
    }

    public function reorderFeedback(int $feedbackId, array $pagePayload): void
    {
        $existingIds = array_map('intval', array_column($this->forFeedback($feedbackId), 'id'));
        $incomingIds = [];
        $normalizedPages = [];

        foreach ($pagePayload as $page) {
            if (!is_array($page)) {
                continue;
            }

            $orderedIds = $page['ordered_ids'] ?? [];
            if (!is_array($orderedIds)) {
                continue;
            }

            $orderedIds = array_values(array_map('intval', $orderedIds));
            if ($orderedIds === []) {
                continue;
            }

            $normalizedPages[] = $orderedIds;
            foreach ($orderedIds as $id) {
                $incomingIds[] = $id;
            }
        }

        if ($normalizedPages === []) {
            throw new \RuntimeException('Die übermittelte Sortierung war leer.');
        }

        $sortedExisting = $existingIds;
        $sortedIncoming = $incomingIds;
        sort($sortedExisting);
        sort($sortedIncoming);

        if ($sortedExisting !== $sortedIncoming || count($incomingIds) !== count(array_unique($incomingIds))) {
            throw new \RuntimeException('Die übermittelte Sortierung passt nicht zum Feedback.');
        }

        $update = $this->pdo()->prepare('UPDATE activity_blocks SET page_number = ?, sort_order = ?, updated_at = ? WHERE id = ?');
        $timestamp = date('c');

        $this->pdo()->beginTransaction();
        try {
            foreach ($normalizedPages as $pageNumber => $orderedIds) {
                foreach ($orderedIds as $sortOrder => $id) {
                    $update->execute([$pageNumber, $sortOrder, $timestamp, $id]);
                }
            }
            $this->pdo()->commit();
        } catch (\Throwable $throwable) {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }
            throw $throwable;
        }
    }

    public function moveToAdjacentPage(int $blockId, string $direction): void
    {
        $block = $this->find($blockId);
        if (!$block) {
            return;
        }

        $feedbackId = (int) $block['feedback_id'];
        $currentPage = (int) $block['page_number'];
        $targetPage = $direction === 'previous' ? $currentPage - 1 : $currentPage + 1;
        if ($targetPage < 0) {
            return;
        }

        $insertAtStart = $direction !== 'previous';
        if ($insertAtStart) {
            $this->pdo()->prepare(
                'UPDATE activity_blocks
                 SET sort_order = sort_order + 1, updated_at = ?
                 WHERE feedback_id = ? AND page_number = ?'
            )->execute([date('c'), $feedbackId, $targetPage]);
            $newSortOrder = 0;
        } else {
            $newSortOrder = $this->maxSortOrder($feedbackId, $targetPage) + 1;
        }

        $stmt = $this->pdo()->prepare('UPDATE activity_blocks SET page_number = ?, sort_order = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$targetPage, $newSortOrder, date('c'), $blockId]);
    }

    public function splitFromBlock(int $blockId): void
    {
        $block = $this->find($blockId);
        if (!$block) {
            return;
        }

        $feedbackId = (int) $block['feedback_id'];
        $currentPage = (int) $block['page_number'];
        $currentOrder = (int) $block['sort_order'];

        $stmt = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM activity_blocks WHERE feedback_id = ? AND page_number = ? AND sort_order < ?'
        );
        $stmt->execute([$feedbackId, $currentPage, $currentOrder]);
        $blocksBefore = (int) $stmt->fetchColumn();
        if ($blocksBefore === 0) {
            return;
        }

        $this->shiftPagesAfter($feedbackId, $currentPage);

        $move = $this->pdo()->prepare(
            'UPDATE activity_blocks
             SET page_number = ?, sort_order = sort_order - ?, updated_at = ?
             WHERE feedback_id = ? AND page_number = ? AND sort_order >= ?'
        );
        $move->execute([
            $currentPage + 1,
            $currentOrder,
            date('c'),
            $feedbackId,
            $currentPage,
            $currentOrder,
        ]);
    }
}
