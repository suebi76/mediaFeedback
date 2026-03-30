<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Core;

class View
{
    public static function render(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require V2_TEMPLATES . '/' . $template . '.php';
        $content = ob_get_clean();

        require V2_TEMPLATES . '/layout.php';
    }
}