<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\PublicController;
use App\Core\Audit;
use App\Core\Analytics;
use App\Core\Security;
use App\Core\View;

defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$path = '/' . trim($path, '/');
$path = $path === '/' ? '/' : rtrim($path, '/');

$public = new PublicController();
$admin = new AdminController();

if (!in_array($path, ['/api/analytics/presence', '/api/resultados', '/admin/analytics/online', '/admin/api/ranking'], true) && !str_starts_with($path, '/media/finalistas/')) {
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

}
if (str_starts_with($path, '/admin') || $path === '/api/analytics/presence') { header('Cache-Control: no-store, private'); }

$routes = [
    'GET' => [
        '/' => [$public, 'home'],
        '/votacao' => [$public, 'voting'],
        '/resultados' => [$public, 'results'],
        '/api/resultados' => [$public, 'liveResults'],
        '/jurados' => [$public, 'jurors'],
        '/inscricoes' => static fn () => \App\Core\Response::redirect('https://www.ruffinoacabamentos.com/passaporte-revestir'),
        '/regulamento/profissionais' => static fn () => $public->regulations('profissionais'),
        '/privacidade' => [$public, 'privacy'],
        '/auditoria' => [$public, 'auditPage'],
        '/api/captcha/vote' => static fn () => $public->captcha('vote'),
        '/admin/login' => [$admin, 'login'],
        '/admin/analytics' => [$admin, 'analytics'],
        '/admin/analytics/evolucao-votos' => [$admin, 'voteEvolution'],
        '/admin/analytics/exportar' => [$admin, 'exportAnalytics'],
        '/admin/analytics/online' => [$admin, 'online'],
        '/admin' => [$admin, 'dashboard'],
        '/admin/api/ranking' => [$admin, 'liveRanking'],
        '/admin/finalistas' => [$admin, 'finalists'],
        '/admin/auditoria' => [$admin, 'audit'],
        '/admin/auditoria/exportar' => [$admin, 'exportAudit'],
        '/admin/configuracoes' => [$admin, 'settings'],
        '/admin/permissoes' => [$admin, 'permissions'],
        '/admin/usuarios' => [$admin, 'users'],
    ],
    'POST' => [
        '/api/analytics/presence' => [Analytics::class, 'heartbeat'],
        '/acesso' => [$public, 'authenticateSite'],
        '/api/vote' => [$public, 'vote'],
        '/admin/login' => [$admin, 'authenticate'],
        '/admin/logout' => [$admin, 'logout'],
        '/admin/finalistas/salvar' => [$admin, 'saveFinalist'],
        '/admin/finalistas/desativar' => [$admin, 'disableFinalist'],
        '/admin/finalistas/imagens/enviar' => [$admin, 'uploadFinalistImages'],
        '/admin/finalistas/imagens/capa' => [$admin, 'setFinalistImageCover'],
        '/admin/finalistas/imagens/mover' => [$admin, 'moveFinalistImage'],
        '/admin/finalistas/imagens/ordenar' => [$admin, 'reorderFinalistImages'],
        '/admin/finalistas/imagens/excluir' => [$admin, 'deleteFinalistImage'],
        '/admin/auditoria/voto' => [$admin, 'updateVote'],
        '/admin/configuracoes' => [$admin, 'saveSettings'],
        '/admin/configuracoes/turnstile' => [$admin, 'saveTurnstile'],
        '/admin/permissoes' => [$admin, 'savePermissions'],
        '/admin/usuarios/salvar' => [$admin, 'saveUser'],
    ],
];

if ($method === 'GET' && preg_match('#^/media/finalistas/([1-9][0-9]*)$#', $path, $matches)) {
    $public->finalistImage((int) $matches[1]);
}

$handler = $routes[$method][$path] ?? null;
if (is_callable($handler)) {
    $handler();
    exit;
}

http_response_code(404);
View::render('404', ['title' => 'Página não encontrada']);
