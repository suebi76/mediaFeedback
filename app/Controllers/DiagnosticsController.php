<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Controllers;

use MediaFeedbackV2\Core\Controller;
use MediaFeedbackV2\Core\SystemCheck;

class DiagnosticsController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $systemCheck = new SystemCheck();
        $results = $systemCheck->results();
        $isHttps = $this->isHttpsRequest();
        $isLocalhost = $this->isLocalhostRequest();
        $scheme = $isHttps ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        $this->render('diagnostics', [
            'title' => 'Systemdiagnose',
            'results' => $results,
            'isHttps' => $isHttps,
            'isLocalhost' => $isLocalhost,
            'publicBaseUrl' => $scheme . '://' . $host . V2_BASE_URL,
            'mediaCaptureReady' => $isHttps || $isLocalhost,
            'prettyUrlsRecommended' => false,
        ]);
    }

    private function isHttpsRequest(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            return true;
        }

        $scheme = strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? ''));
        if ($scheme === 'https') {
            return true;
        }

        $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($forwardedProto === 'https') {
            return true;
        }

        return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }

    private function isLocalhostRequest(): bool
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $serverName = strtolower((string) ($_SERVER['SERVER_NAME'] ?? ''));
        $remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return str_contains($host, 'localhost')
            || str_contains($serverName, 'localhost')
            || in_array($remoteAddr, ['127.0.0.1', '::1'], true);
    }
}
