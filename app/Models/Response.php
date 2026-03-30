<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Models;

class Response extends BaseModel
{
    public function countForFeedback(int $feedbackId): int
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM responses WHERE feedback_id = ?');
        $stmt->execute([$feedbackId]);
        return (int) $stmt->fetchColumn();
    }

    public function create(int $feedbackId, string $sessionToken, ?string $deviceHash = null): int
    {
        $stmt = $this->pdo()->prepare('INSERT INTO responses (feedback_id, session_token, device_hash, submitted_at, created_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$feedbackId, $sessionToken, $deviceHash, date('c'), date('c')]);
        return (int) $this->pdo()->lastInsertId();
    }

    public function forFeedback(int $feedbackId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM responses WHERE feedback_id = ? ORDER BY submitted_at DESC, id DESC');
        $stmt->execute([$feedbackId]);
        return $stmt->fetchAll();
    }

    public function existsForFeedbackDevice(int $feedbackId, string $deviceHash): bool
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM responses WHERE feedback_id = ? AND device_hash = ?');
        $stmt->execute([$feedbackId, $deviceHash]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
