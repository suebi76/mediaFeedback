<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Core;

use MediaFeedbackV2\Models\User;

class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_save_path(V2_DATA . '/sessions');
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'use_strict_mode' => true,
        ]);
    }

    public static function attempt(Database $database, string $email, string $password): bool
    {
        self::start();
        $user = (new User($database))->findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['v2_user_id'] = (int) $user['id'];
        $_SESSION['v2_user_role'] = $user['role'];
        return true;
    }

    public static function check(): bool
    {
        self::start();
        return isset($_SESSION['v2_user_id']);
    }

    public static function id(): ?int
    {
        self::start();
        return isset($_SESSION['v2_user_id']) ? (int) $_SESSION['v2_user_id'] : null;
    }

    public static function user(Database $database): ?array
    {
        $id = self::id();
        if ($id === null) {
            return null;
        }

        return (new User($database))->find($id);
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }
}