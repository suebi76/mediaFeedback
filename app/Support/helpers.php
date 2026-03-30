<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function checked(bool $condition): string
{
    return $condition ? 'checked' : '';
}

function selected(string|int|null $value, string|int|null $expected): string
{
    return (string) $value === (string) $expected ? 'selected' : '';
}

function json_pretty(mixed $value): string
{
    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

function bytes_human(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $value = max($bytes, 0);
    $unit = 0;

    while ($value >= 1024 && $unit < count($units) - 1) {
        $value /= 1024;
        $unit++;
    }

    return number_format($value, $unit === 0 ? 0 : 1, ',', '.') . ' ' . $units[$unit];
}

function status_label(string $status): string
{
    return match ($status) {
        'draft' => 'Entwurf',
        'live' => 'Live',
        'closed' => 'Geschlossen',
        default => $status,
    };
}

function layout_label(string $layout): string
{
    return match ($layout) {
        'one-per-page' => 'Seite für Seite',
        'classic' => 'Klassisch',
        default => $layout,
    };
}

function role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Admin',
        'creator' => 'Ersteller',
        default => $role,
    };
}

function request_scheme(): string
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return 'https';
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower(trim((string) $_SERVER['HTTP_X_FORWARDED_PROTO']));
        if (in_array($proto, ['http', 'https'], true)) {
            return $proto;
        }
    }

    return 'http';
}

function request_origin(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }

    return request_scheme() . '://' . $host;
}

function public_feedback_url(array $feedback): string
{
    $slug = rawurlencode((string) ($feedback['slug'] ?? ''));
    $path = V2_BASE_URL . '/f?slug=' . $slug;
    $origin = request_origin();

    return $origin !== '' ? $origin . $path : $path;
}

function feedback_qr_url(array $feedback): string
{
    $id = (int) ($feedback['id'] ?? 0);
    $path = V2_BASE_URL . '/share/qr?id=' . $id;
    $origin = request_origin();

    return $origin !== '' ? $origin . $path : $path;
}

function ui_icon(string $name, string $class = 'ui-icon'): string
{
    $symbols = [
        'settings' => 'cog-6-tooth',
        'publish' => 'globe-alt',
        'results' => 'chart-bar-square',
        'preview' => 'eye',
        'share' => 'share',
        'delete' => 'trash',
        'back' => 'arrow-uturn-left',
        'pages' => 'rectangle-stack',
        'add' => 'plus-circle',
        'text' => 'document-text',
        'image' => 'photo',
        'audio' => 'speaker-wave',
        'video' => 'video-camera',
        'open_question' => 'question-mark-circle',
        'rating' => 'star',
        'single_choice' => 'list-bullet',
        'more' => 'ellipsis-horizontal-circle',
        'help' => 'information-circle',
        'status' => 'signal',
        'layout' => 'squares-2x2',
        'time' => 'clock',
        'draft' => 'pencil-square',
        'closed' => 'lock-closed',
    ];

    $symbol = $symbols[$name] ?? 'rectangle-stack';

    return '<svg class="' . e($class) . '" aria-hidden="true" focusable="false"><use href="' . e(V2_WEB_ROOT . '/assets/heroicons.svg#icon-' . $symbol) . '"></use></svg>';
}

function activity_icon_name(string $type): string
{
    return match ($type) {
        'text' => 'text',
        'image' => 'image',
        'audio' => 'audio',
        'video' => 'video',
        'open_question' => 'open_question',
        'rating' => 'rating',
        'single_choice' => 'single_choice',
        default => 'pages',
    };
}
