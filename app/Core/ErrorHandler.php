<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Core;

use Throwable;

class ErrorHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handleException']);
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleException(Throwable $throwable): void
    {
        self::log($throwable);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        $reference = date('Ymd-His') . '-' . substr(sha1($throwable::class . '|' . $throwable->getMessage()), 0, 8);
        $hint = self::hintFor($throwable);

        echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Systemfehler</title><style>body{margin:0;font-family:Segoe UI,Arial,sans-serif;background:#f5f7fb;color:#14213d}main{max-width:780px;margin:3rem auto;padding:0 1rem}.card{background:#fff;border:1px solid #d8e1ec;border-radius:20px;padding:2rem;box-shadow:0 18px 45px rgba(15,35,60,.08)}.badge{display:inline-block;padding:.35rem .7rem;border-radius:999px;background:#eef4ff;color:#0f62fe;font-size:.84rem;font-weight:700}.muted{color:#667085}code{background:#f6f8fb;padding:.1rem .35rem;border-radius:6px}</style></head><body><main><div class="card"><div class="badge">Systemfehler</div><h1>Die Anwendung konnte die Anfrage nicht sauber verarbeiten.</h1><p class="muted">' . e($hint) . '</p><p class="muted">Referenz: <code>' . e($reference) . '</code></p><p class="muted">Bitte prüfe die Systemdiagnose, die Server-Schreibrechte und die Konfiguration in <code>data/config.php</code>. Wenn der Fehler bestehen bleibt, hilft die Logdatei in <code>data/logs</code>.</p></div></main></body></html>';
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($error['type'] ?? 0, $fatalTypes, true)) {
            return;
        }

        self::handleException(new \Error($error['message'] ?? 'Fatal error', 0, $error['type'] ?? E_ERROR, $error['file'] ?? __FILE__, $error['line'] ?? 0));
    }

    private static function hintFor(Throwable $throwable): string
    {
        $message = $throwable->getMessage();

        if (str_contains($message, 'Database connection failed') || str_contains($message, 'unable to open database file')) {
            return 'Die Datenbank konnte nicht geöffnet werden. Bitte prüfe den db_path in data/config.php sowie Schreibrechte im data-Ordner.';
        }

        if (str_contains($message, 'Dateityp nicht erlaubt') || str_contains($message, 'Dateiformat nicht erlaubt')) {
            return 'Der Upload wurde vom Server abgelehnt. Bitte Dateityp und Upload-Grenzen prüfen.';
        }

        if (str_contains($message, 'Ungueltiges Formular-Token')) {
            return 'Das Formular-Token war ungültig oder abgelaufen. Bitte die Seite neu laden und den Vorgang erneut versuchen.';
        }

        return 'Bitte prüfe Serverkonfiguration, Dateirechte und die aktuelle Anfrage in der Logdatei.';
    }

    private static function log(Throwable $throwable): void
    {
        $logDirectory = V2_DATA . '/logs';
        if (!is_dir($logDirectory)) {
            @mkdir($logDirectory, 0775, true);
        }

        $target = is_dir($logDirectory)
            ? $logDirectory . '/app-' . date('Y-m-d') . '.log'
            : sys_get_temp_dir() . '/mediafeedback-' . date('Y-m-d') . '.log';

        $payload = '[' . date('c') . '] '
            . ($throwable::class)
            . ' | URI=' . ($_SERVER['REQUEST_URI'] ?? '-')
            . ' | Message=' . $throwable->getMessage()
            . ' | File=' . $throwable->getFile() . ':' . $throwable->getLine()
            . PHP_EOL
            . $throwable->getTraceAsString()
            . PHP_EOL . PHP_EOL;

        @file_put_contents($target, $payload, FILE_APPEND);
    }
}
