<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Models;

class Feedback extends BaseModel
{
    public static function defaultSettings(): array
    {
        return [
            'intro_enabled' => false,
            'intro_text' => '',
            'progress_bar' => true,
            'estimated_time_minutes' => null,
            'limit_one_response_per_device' => false,
        ];
    }

    public static function normalizeSettings(array $settings): array
    {
        $merged = array_merge(self::defaultSettings(), $settings);
        $estimatedTime = $merged['estimated_time_minutes'];

        return [
            'intro_enabled' => !empty($merged['intro_enabled']),
            'intro_text' => trim((string) ($merged['intro_text'] ?? '')),
            'progress_bar' => !array_key_exists('progress_bar', $merged) || !empty($merged['progress_bar']),
            'estimated_time_minutes' => is_numeric($estimatedTime) && (int) $estimatedTime > 0 ? (int) $estimatedTime : null,
            'limit_one_response_per_device' => !empty($merged['limit_one_response_per_device']),
        ];
    }

    public static function decodeSettings(string $json): array
    {
        $decoded = json_decode($json, true);
        return self::normalizeSettings(is_array($decoded) ? $decoded : []);
    }

    public function allForUser(int $userId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT f.*, COUNT(r.id) AS response_count
             FROM feedbacks f
             LEFT JOIN responses r ON r.feedback_id = f.id
             WHERE f.user_id = ?
             GROUP BY f.id
             ORDER BY f.updated_at DESC, f.created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function create(int $userId, string $title, string $layout): int
    {
        $slug = $this->makeUniqueSlug($title);
        $settings = self::normalizeSettings([]);
        $stmt = $this->pdo()->prepare(
            'INSERT INTO feedbacks (user_id, title, slug, status, layout, description, settings_json, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $title,
            $slug,
            'draft',
            $layout,
            '',
            json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            date('c'),
            date('c'),
        ]);
        return (int) $this->pdo()->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): void
    {
        $settings = self::normalizeSettings($data['settings'] ?? []);
        $stmt = $this->pdo()->prepare('UPDATE feedbacks SET title = ?, layout = ?, description = ?, settings_json = ?, updated_at = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([
            $data['title'],
            $data['layout'],
            $data['description'],
            json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            date('c'),
            $id,
            $userId,
        ]);
    }

    public function findOwned(int $id, int $userId): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM feedbacks WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM feedbacks WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public function delete(int $id, int $userId): void
    {
        $this->deleteWithAssets($id, $userId);
    }

    public function deleteWithAssets(int $id, int $userId): void
    {
        $feedback = $this->findOwned($id, $userId);
        if (!$feedback) {
            return;
        }

        $candidateFiles = $this->collectFilesForFeedback($id);

        $this->database->transaction(function () use ($id, $userId): void {
            $stmt = $this->pdo()->prepare('DELETE FROM feedbacks WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $userId]);
        });

        $this->deleteUnreferencedUploads($candidateFiles);
    }

    public function setStatus(int $id, int $userId, string $status): void
    {
        $stmt = $this->pdo()->prepare('UPDATE feedbacks SET status = ?, updated_at = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$status, date('c'), $id, $userId]);
    }

    private function makeUniqueSlug(string $title): string
    {
        $slug = trim(mb_strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $title) ?: 'feedback'), '-');
        if ($slug === '') {
            $slug = 'feedback';
        }

        $base = $slug;
        $counter = 1;
        while (true) {
            $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM feedbacks WHERE slug = ?');
            $stmt->execute([$slug]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $slug;
            }
            $counter++;
            $slug = $base . '-' . $counter;
        }
    }

    private function collectFilesForFeedback(int $feedbackId): array
    {
        $files = [];

        $blockStmt = $this->pdo()->prepare('SELECT activity_data FROM activity_blocks WHERE feedback_id = ?');
        $blockStmt->execute([$feedbackId]);
        foreach ($blockStmt->fetchAll() as $row) {
            $data = json_decode((string) ($row['activity_data'] ?? ''), true);
            if (is_array($data) && !empty($data['media_path']) && is_string($data['media_path'])) {
                $files[] = basename($data['media_path']);
            }
        }

        $answerStmt = $this->pdo()->prepare(
            'SELECT a.media_path
             FROM answers a
             INNER JOIN responses r ON r.id = a.response_id
             WHERE r.feedback_id = ? AND a.media_path IS NOT NULL AND a.media_path <> ""'
        );
        $answerStmt->execute([$feedbackId]);
        foreach ($answerStmt->fetchAll() as $row) {
            $files[] = basename((string) $row['media_path']);
        }

        $mediaStmt = $this->pdo()->prepare('SELECT file_name FROM media WHERE feedback_id = ?');
        $mediaStmt->execute([$feedbackId]);
        foreach ($mediaStmt->fetchAll() as $row) {
            $files[] = basename((string) $row['file_name']);
        }

        $files = array_values(array_unique(array_filter($files, static fn(string $file): bool => $file !== '')));
        sort($files);
        return $files;
    }

    private function collectReferencedFiles(): array
    {
        $files = [];

        foreach ($this->pdo()->query('SELECT activity_data FROM activity_blocks')->fetchAll() as $row) {
            $data = json_decode((string) ($row['activity_data'] ?? ''), true);
            if (is_array($data) && !empty($data['media_path']) && is_string($data['media_path'])) {
                $files[] = basename($data['media_path']);
            }
        }

        foreach ($this->pdo()->query('SELECT media_path FROM answers WHERE media_path IS NOT NULL AND media_path <> ""')->fetchAll() as $row) {
            $files[] = basename((string) $row['media_path']);
        }

        foreach ($this->pdo()->query('SELECT file_name FROM media')->fetchAll() as $row) {
            $files[] = basename((string) $row['file_name']);
        }

        return array_values(array_unique(array_filter($files, static fn(string $file): bool => $file !== '')));
    }

    private function deleteUnreferencedUploads(array $candidateFiles): void
    {
        if ($candidateFiles === []) {
            return;
        }

        $stillReferenced = array_flip($this->collectReferencedFiles());
        foreach ($candidateFiles as $file) {
            if (isset($stillReferenced[$file])) {
                continue;
            }

            $path = V2_DATA . '/uploads/' . basename($file);
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
