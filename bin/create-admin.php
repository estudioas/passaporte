<?php

declare(strict_types=1);

use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

[$script, $name, $email, $password, $role] = array_pad($argv, 5, null);
if (!$name || !$email || !$password) {
    fwrite(STDERR, "Uso: php bin/create-admin.php \"Nome\" email@dominio.com \"Senha-forte\" [administrator|auditor]\n");
    exit(1);
}
$role = in_array($role, ['administrator', 'auditor'], true) ? $role : 'administrator';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Informe e-mail válido e senha com pelo menos 12 caracteres.\n");
    exit(1);
}
$stmt = Database::connection()->prepare('INSERT INTO admin_users (name, email, password_hash, role, active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())');
$stmt->execute([$name, mb_strtolower($email), password_hash($password, PASSWORD_DEFAULT), $role]);
fwrite(STDOUT, "Administrador criado com sucesso.\n");
