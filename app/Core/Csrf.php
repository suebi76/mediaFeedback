<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Core;

class Csrf
{
    public static function token(string $key = 'default'): string
    {
        Auth::start();
        if (!isset($_SESSION['csrf'][$key])) {
            $_SESSION['csrf'][$key] = bin2hex(random_bytes(24));
        }
        return $_SESSION['csrf'][$key];
    }

    public static function validate(string $token, string $key = 'default'): bool
    {
        Auth::start();
        $expected = $_SESSION['csrf'][$key] ?? null;
        return is_string($expected) && hash_equals($expected, $token);
    }
}