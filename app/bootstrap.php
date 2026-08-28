<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $path = __DIR__ . DIRECTORY_SEPARATOR . $relative . '.php';
    if (is_file($path)) {
        require $path;
    }
});

$configFile = dirname(__DIR__) . '/config/app.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('Aplicação ainda não configurada. Copie config/app.example.php para config/app.php.');
}

$GLOBALS['app_config'] = require $configFile;
date_default_timezone_set((string) ($GLOBALS['app_config']['app']['timezone'] ?? 'America/Sao_Paulo'));

$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' && ($GLOBALS['app_config']['security']['trust_proxy_headers'] ?? false));

session_name((string) ($GLOBALS['app_config']['app']['session_name'] ?? 'pr_session'));
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' https: data:; style-src 'self' 'unsafe-inline'; script-src 'self' https://www.instagram.com; frame-src https://www.instagram.com; connect-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'self'");
}

set_exception_handler(static function (Throwable $e): void {
    $debug = (bool) ($GLOBALS['app_config']['app']['debug'] ?? false);
    error_log((string) $e);
    http_response_code(500);
    if ($debug) {
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo 'Não foi possível concluir esta operação. Tente novamente em instantes.';
    }
});
