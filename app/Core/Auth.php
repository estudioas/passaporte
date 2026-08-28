<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public static function user(): ?array
    {
        $id = (int) ($_SESSION['admin_id'] ?? 0);
        if ($id < 1) {
            return null;
        }
        $stmt = Database::connection()->prepare('SELECT id, name, email, role, active FROM admin_users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user && (int) $user['active'] === 1 ? $user : null;
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if (!$user) {
            Response::redirect('/admin/login');
        }
        return $user;
    }

    public static function requireAdministrator(): array
    {
        $user = self::requireUser();
        if (($user['role'] ?? '') !== 'administrator') {
            http_response_code(403);
            exit('Ação restrita a administradores.');
        }
        return $user;
    }

    public static function attempt(string $email, string $password): bool
    {
        $pdo = Database::connection();
        $ipHash = Security::ipHash();
        $rate = $pdo->prepare('SELECT COUNT(*) FROM auth_attempts WHERE ip_hash = ? AND succeeded = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
        $rate->execute([$ipHash]);
        if ((int) $rate->fetchColumn() >= 8) {
            Audit::log('admin.login_blocked', ['email_hash' => Security::secretHash(strtolower($email))], 'admin', null, 80);
            return false;
        }

        $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE email = ? AND active = 1 LIMIT 1');
        $stmt->execute([mb_strtolower(trim($email))]);
        $user = $stmt->fetch();
        $success = $user && password_verify($password, (string) $user['password_hash']);
        $save = $pdo->prepare('INSERT INTO auth_attempts (email_hash, ip_hash, succeeded, created_at) VALUES (?, ?, ?, NOW())');
        $save->execute([Security::secretHash(mb_strtolower(trim($email))), $ipHash, $success ? 1 : 0]);
        if (!$success) {
            Audit::log('admin.login_failed', ['email_hash' => Security::secretHash(mb_strtolower(trim($email)))], 'admin', null, 55);
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $user['id'];
        $pdo->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?')->execute([(int) $user['id']]);
        Audit::log('admin.login_succeeded', [], 'admin', (int) $user['id']);
        return true;
    }

    public static function logout(): void
    {
        $id = (int) ($_SESSION['admin_id'] ?? 0);
        if ($id > 0) {
            Audit::log('admin.logout', [], 'admin', $id);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
