<?php

declare(strict_types=1);

use App\Core\Audit;
use App\Core\Config;
use App\Core\Database;

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

$checks = [];
$check = static function (string $label, bool $ok, string $detail = '') use (&$checks): void {
    $checks[] = compact('label', 'ok', 'detail');
};

foreach (['pdo_mysql', 'openssl', 'fileinfo', 'mbstring'] as $extension) {
    $check('Extensão ' . $extension, extension_loaded($extension));
}
$check('PHP 8.1+', version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION);
$check('HTTPS na URL base', str_starts_with((string) Config::get('app.base_url'), 'https://'));
$secret = (string) Config::get('app.secret');
$check('Segredo configurado', strlen($secret) >= 64 && !str_contains($secret, 'TROQUE-POR'));
$check('Uploads graváveis', is_dir(dirname(__DIR__) . '/storage/uploads') && is_writable(dirname(__DIR__) . '/storage/uploads'));

try {
    $pdo = Database::connection();
    $check('Conexão com banco', (int) $pdo->query('SELECT 1')->fetchColumn() === 1);
    $tables = ['admin_users','settings','finalists','audit_chain_state','audit_events','votes','auth_attempts','registrations','registration_files'];
    foreach ($tables as $table) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$table]);
        $check('Tabela ' . $table, (int) $stmt->fetchColumn() === 1);
    }
    $active = (int) $pdo->query('SELECT COUNT(*) FROM finalists WHERE active = 1')->fetchColumn();
    $check('Exatamente três finalistas ativos', $active === 3, (string) $active);
    $chain = Audit::verifyChain();
    $check('Cadeia de auditoria', (bool) $chain['valid'], json_encode($chain, JSON_UNESCAPED_SLASHES));
} catch (Throwable $e) {
    $check('Banco e schema', false, $e->getMessage());
}

foreach ($checks as $item) {
    echo ($item['ok'] ? '[OK]   ' : '[FALHA]') . ' ' . $item['label'] . ($item['detail'] !== '' ? ' — ' . $item['detail'] : '') . PHP_EOL;
}
$failed = array_filter($checks, static fn (array $item): bool => !$item['ok']);
exit($failed ? 1 : 0);
