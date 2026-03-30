<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Core;

abstract class Controller
{
    protected array $config;
    protected Database $database;

    public function __construct()
    {
        $this->config = is_file(V2_CONFIG) ? require V2_CONFIG : [];
        $databasePath = $this->resolveDatabasePath($this->config['db_path'] ?? null);
        $this->database = new Database($databasePath);
        if (is_file(V2_CONFIG)) {
            (new MigrationRunner($this->database))->run();
        }
        Auth::start();
    }

    private function resolveDatabasePath(?string $configuredPath): string
    {
        $fallbackPaths = [
            V2_DATA . '/mediafeedback.sqlite',
            V2_DATA . '/mediafeedback-v2.sqlite',
        ];
        if ($configuredPath === null || trim($configuredPath) === '') {
            foreach ($fallbackPaths as $fallbackPath) {
                if (is_file($fallbackPath)) {
                    return $fallbackPath;
                }
            }
            return $fallbackPaths[0];
        }

        $normalized = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $configuredPath);
        $isAbsoluteWindows = preg_match('/^[A-Za-z]:[\\\\\\/]/', $configuredPath) === 1;
        $isAbsoluteUnix = str_starts_with($configuredPath, '/');
        $resolved = ($isAbsoluteWindows || $isAbsoluteUnix)
            ? $normalized
            : V2_ROOT . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR);

        if (is_file($resolved) || is_dir(dirname($resolved))) {
            return $resolved;
        }

        foreach ($fallbackPaths as $fallbackPath) {
            if (is_file($fallbackPath)) {
                return $fallbackPath;
            }
        }

        return $fallbackPaths[0];
    }

    protected function render(string $template, array $data = []): void
    {
        View::render($template, array_merge($data, [
            'flash' => $this->consumeFlash(),
            'currentUser' => Auth::check() ? Auth::user($this->database) : null,
        ]));
    }

    protected function redirect(string $path): never
    {
        header('Location: ' . V2_BASE_URL . $path);
        exit;
    }

    protected function requireAuth(): array
    {
        $user = Auth::user($this->database);
        if (!$user) {
            $this->redirect('/login');
        }
        return $user;
    }

    protected function requireAdmin(): array
    {
        $user = $this->requireAuth();
        if (($user['role'] ?? null) !== 'admin') {
            $this->flash('error', 'Nur Administratoren dürfen diese Seite aufrufen.');
            $this->redirect('/dashboard');
        }

        return $user;
    }

    protected function enforceCsrf(string $key = 'default'): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Csrf::validate((string) $token, $key)) {
            http_response_code(422);
            echo 'Ungueltiges Formular-Token.';
            exit;
        }
    }

    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    private function consumeFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }
}
