<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Controllers;

use MediaFeedbackV2\Core\Controller;
use MediaFeedbackV2\Models\Feedback;

final class ShareController extends Controller
{
    public function qr(): void
    {
        $user = $this->requireAuth();
        $feedbackId = (int) ($_GET['id'] ?? 0);
        $feedback = (new Feedback($this->database))->findOwned($feedbackId, (int) $user['id']);

        if (!$feedback) {
            $this->renderErrorSvg('Feedback nicht gefunden.', 404);
        }

        if (!class_exists(\chillerlan\QRCode\QRCode::class) || !class_exists(\chillerlan\QRCode\QROptions::class)) {
            $this->renderErrorSvg('QR-Bibliothek fehlt auf diesem Server.', 503);
        }

        try {
            $options = new \chillerlan\QRCode\QROptions([
                'version' => 5,
                'eccLevel' => 1,
                'addQuietzone' => true,
                'outputBase64' => false,
                'svgViewBox' => true,
            ]);

            $svg = (new \chillerlan\QRCode\QRCode($options))->render(public_feedback_url($feedback));

            while (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Type: image/svg+xml; charset=UTF-8');
            header('Cache-Control: private, max-age=300');
            echo trim($svg);
            exit;
        } catch (\Throwable $throwable) {
            $this->renderErrorSvg('QR-Code konnte nicht erzeugt werden.', 500);
        }
    }

    private function renderErrorSvg(string $message, int $statusCode): never
    {
        http_response_code($statusCode);
        $safeMessage = e($message);
        header('Content-Type: image/svg+xml; charset=UTF-8');
        echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="320" height="320" viewBox="0 0 320 320" role="img" aria-label="{$safeMessage}">
  <rect width="320" height="320" rx="24" fill="#ffffff" stroke="#d8e1ec" />
  <rect x="32" y="32" width="256" height="256" rx="20" fill="#fef1f0" stroke="#f9c5c1" />
  <text x="160" y="142" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="22" font-weight="700" fill="#b42318">QR-Code</text>
  <text x="160" y="176" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="16" fill="#b42318">{$safeMessage}</text>
</svg>
SVG;
        exit;
    }
}
