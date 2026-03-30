<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Models;

class Answer extends BaseModel
{
    public function create(int $responseId, int $blockId, ?string $valueText, ?string $valueJson, ?string $mediaPath): void
    {
        $stmt = $this->pdo()->prepare('INSERT INTO answers (response_id, block_id, value_text, value_json, media_path, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$responseId, $blockId, $valueText, $valueJson, $mediaPath, date('c')]);
    }

    public function groupedForFeedback(int $feedbackId): array
    {
        $stmt = $this->pdo()->prepare('SELECT r.id AS response_id, r.session_token, r.device_hash, r.submitted_at, a.block_id, a.value_text, a.value_json, a.media_path FROM responses r LEFT JOIN answers a ON a.response_id = r.id WHERE r.feedback_id = ? ORDER BY r.submitted_at DESC, r.id DESC');
        $stmt->execute([$feedbackId]);
        return $stmt->fetchAll();
    }
}
