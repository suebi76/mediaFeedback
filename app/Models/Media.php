<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Models;

class Media extends BaseModel
{
    public function record(?int $feedbackId, ?int $blockId, ?int $responseId, string $kind, string $fileName, string $originalName, string $mimeType, int $size): void
    {
        $stmt = $this->pdo()->prepare('INSERT INTO media (feedback_id, block_id, response_id, kind, file_name, original_name, mime_type, size_bytes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$feedbackId, $blockId, $responseId, $kind, $fileName, $originalName, $mimeType, $size, date('c')]);
    }
}