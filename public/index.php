<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\PublicController;
use App\Core\Audit;
use App\Core\Security;
use App\Core\View;

require dirname(__DIR__) . '/app/bootstrap.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$path = '/' . trim($path, '/');
$path = $path === '/' ? '/' : rtrim($path, '/');

$public = new PublicController();
$admin = new AdminController();

Audit::log(
    'http.request',
    [
        'query_keys' => array_values(array_map('strval', array_keys($_GET))),
        'content_type' => mb_substr((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 0, 100),
    ],
    !empty($_SESSION['admin_id']) ? 'admin' : 'visitor',
    !empty($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null,
    Security::isSuspiciousUserAgent() ? 35 : 0
);

$routes = [
    'GET' => [
        '/' => [$public, 'home'],
        '/inscricoes' => [$public, 'registration'],
        '/regulamento/profissionais' => static fn () => $public->regulations('profissionais'),
        '/regulamento/revendas' => static fn () => $public->regulations('varejo'),
        '/privacidade' => [$public, 'privacy'],
        '/auditoria' => [$public, 'auditPage'],
        '/api/captcha/vote' => static fn () => $public->captcha('vote'),
        '/api/captcha/inscricao' => static fn () => $public->captcha('registration'),
        '/admin/login' => [$admin, 'login'],
        '/admin' => [$admin, 'dashboard'],
        '/admin/finalistas' => [$admin, 'finalists'],
        '/admin/auditoria' => [$admin, 'audit'],
        '/admin/auditoria/exportar' => [$admin, 'exportAudit'],
        '/admin/configuracoes' => [$admin, 'settings'],
        '/admin/inscricoes' => [$admin, 'registrations'],
    ],
    'POST' => [
        '/api/vote' => [$public, 'vote'],
        '/inscricoes' => [$public, 'submitRegistration'],
        '/admin/login' => [$admin, 'authenticate'],
        '/admin/logout' => [$admin, 'logout'],
        '/admin/finalistas/salvar' => [$admin, 'saveFinalist'],
        '/admin/finalistas/desativar' => [$admin, 'disableFinalist'],
        '/admin/auditoria/voto' => [$admin, 'updateVote'],
        '/admin/configuracoes' => [$admin, 'saveSettings'],
    ],
];

if ($method === 'GET' && preg_match('#^/admin/inscricoes/(\d+)/arquivos$#', $path, $matches)) {
    $admin->registrationFiles((int) $matches[1]);
}
if ($method === 'GET' && preg_match('#^/admin/arquivos/(\d+)/download$#', $path, $matches)) {
    $admin->downloadRegistrationFile((int) $matches[1]);
}

$handler = $routes[$method][$path] ?? null;
if (is_callable($handler)) {
    $handler();
    exit;
}

http_response_code(404);
View::render('404', ['title' => 'Página não encontrada']);
