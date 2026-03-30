<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Support;

use RuntimeException;

class UploadManager
{
    private const RULES = [
        'image' => [
            'max' => 10_000_000,
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        ],
        'video' => [
            'max' => 40_000_000,
            'extensions' => ['mp4', 'webm', 'mov'],
            'mimes' => ['video/mp4', 'video/webm', 'video/quicktime', 'application/octet-stream'],
        ],
        'audio' => [
            'max' => 20_000_000,
            'extensions' => ['mp3', 'wav', 'm4a', 'weba', 'ogg', 'webm', 'mp4'],
            'mimes' => ['audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/mp4', 'audio/webm', 'audio/ogg', 'video/webm', 'video/mp4', 'application/octet-stream'],
        ],
        'attachment' => [
            'max' => 20_000_000,
            'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'],
            'mimes' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'text/plain'],
        ],
    ];

    public function store(array $file, string $kind): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload fehlgeschlagen.');
        }

        $rule = self::RULES[$kind] ?? null;
        if ($rule === null) {
            throw new RuntimeException('Unbekannter Upload-Typ.');
        }

        $originalName = (string) ($file['name'] ?? 'upload.bin');
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mime = (string) (mime_content_type($tmpName) ?: 'application/octet-stream');

        if (!in_array($extension, $rule['extensions'], true)) {
            throw new RuntimeException('Dateityp nicht erlaubt.');
        }

        if (!in_array($mime, $rule['mimes'], true)) {
            throw new RuntimeException('Dateiformat nicht erlaubt.');
        }

        if ($size <= 0 || $size > $rule['max']) {
            throw new RuntimeException('Dateigroesse ungueltig.');
        }

        $targetName = bin2hex(random_bytes(12)) . '.' . $extension;
        $targetPath = V2_DATA . '/uploads/' . $targetName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new RuntimeException('Datei konnte nicht gespeichert werden.');
        }

        return $targetName;
    }

    public function nested(array $files, string $field, int $blockId): ?array
    {
        if (!isset($files[$field]['error'][$blockId]) || $files[$field]['error'][$blockId] !== UPLOAD_ERR_OK) {
            return null;
        }

        return [
            'name' => $files[$field]['name'][$blockId],
            'type' => $files[$field]['type'][$blockId],
            'tmp_name' => $files[$field]['tmp_name'][$blockId],
            'error' => $files[$field]['error'][$blockId],
            'size' => $files[$field]['size'][$blockId],
        ];
    }
}
