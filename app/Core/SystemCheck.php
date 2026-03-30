<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Core;

class SystemCheck
{
    public function results(): array
    {
        $extensions = ['pdo_sqlite', 'mbstring', 'fileinfo', 'json', 'session'];
        $directories = ['data', 'data/logs', 'data/sessions', 'data/uploads'];

        $extensionChecks = [];
        foreach ($extensions as $extension) {
            $extensionChecks[$extension] = extension_loaded($extension);
        }

        $directoryChecks = [];
        foreach ($directories as $directory) {
            $directoryChecks[$directory] = is_dir(V2_ROOT . '/' . $directory) && is_writable(V2_ROOT . '/' . $directory);
        }

        $uploadMaxBytes = $this->iniToBytes((string) ini_get('upload_max_filesize'));
        $postMaxBytes = $this->iniToBytes((string) ini_get('post_max_size'));
        $requiredVideoBytes = 40_000_000;

        return [
            'php' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'php_version' => PHP_VERSION,
            'extensions' => $extensionChecks,
            'directories' => $directoryChecks,
            'limits' => [
                'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
                'upload_max_bytes' => $uploadMaxBytes,
                'post_max_size' => (string) ini_get('post_max_size'),
                'post_max_bytes' => $postMaxBytes,
                'max_file_uploads' => max(1, (int) ini_get('max_file_uploads')),
                'video_upload_required_bytes' => $requiredVideoBytes,
                'video_upload_ready' => $uploadMaxBytes >= $requiredVideoBytes && $postMaxBytes >= $requiredVideoBytes,
            ],
        ];
    }

    public function allGood(): bool
    {
        $results = $this->results();
        if (!$results['php']) {
            return false;
        }

        foreach ($results['extensions'] as $ok) {
            if (!$ok) {
                return false;
            }
        }

        foreach ($results['directories'] as $ok) {
            if (!$ok) {
                return false;
            }
        }

        return true;
    }

    private function iniToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $number = (float) $value;
        $suffix = strtolower(substr($value, -1));

        return match ($suffix) {
            'g' => (int) round($number * 1024 * 1024 * 1024),
            'm' => (int) round($number * 1024 * 1024),
            'k' => (int) round($number * 1024),
            default => (int) round($number),
        };
    }
}
