<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = Database::connection();
$accessDays = max(30, (int) Config::get('security.retention_access_days', 180));
$pdo->exec('DELETE FROM auth_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL ' . $accessDays . ' DAY)');
// Eventos comuns antigos só podem ser removidos em lote com reencadeamento supervisionado.
// Por segurança, este script não altera audit_events nem votes automaticamente.
echo "Tentativas de autenticação antigas removidas. Revise a política antes de arquivar a cadeia de auditoria.\n";
