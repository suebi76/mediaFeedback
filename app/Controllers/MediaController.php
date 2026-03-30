<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Controllers;

use MediaFeedbackV2\Core\Controller;

class MediaController extends Controller
{
    public function serve(): void
    {
        $file = basename((string) ($_GET['file'] ?? ''));
        $path = V2_DATA . '/uploads/' . $file;
        if ($file === '' || !is_file($path)) {
            http_response_code(404);
            echo 'Datei nicht gefunden.';
            return;
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        readfile($path);
    }
}