<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $template, array $data = [], string $layout = 'layout'): void
    {
        $root = dirname(__DIR__, 2) . '/views/';
        extract($data, EXTR_SKIP);
        ob_start();
        require $root . $template . '.php';
        $content = (string) ob_get_clean();
        require $root . $layout . '.php';
    }
}
