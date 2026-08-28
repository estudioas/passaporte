<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Captcha;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Privacy;
use App\Core\Response;
use App\Core\Security;
use App\Core\Settings;
use App\Core\View;
use RuntimeException;

final class AdminController
{
    public function login(): void
    {
        if (Auth::user()) {
            Response::redirect('/admin');
        }
        View::render('admin/login', ['title' => 'Acesso administrativo', 'error' => $_SESSION['login_error'] ?? null], 'admin/layout-guest');
        unset($_SESSION['login_error']);
    }

    public function authenticate(): never
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $_SESSION['login_error'] = 'Sessão expirada. Tente novamente.';
            Response::redirect('/admin/login');
        }
        if (Auth::attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            Response::redirect('/admin');
        }
        $_SESSION['login_error'] = 'Credenciais inválidas ou acesso temporariamente limitado.';
        Response::redirect('/admin/login');
    }

    public function logout(): never
    {
        Auth::requireUser();
        if (Csrf::verify($_POST['_csrf'] ?? null)) {
            Auth::logout();
        }
        Response::redirect('/admin/login');
    }

    public function dashboard(): void
    {
        $user = Auth::requireUser();
        $pdo = Database::connection();
        $ranking = $pdo->query(
            'SELECT f.id, f.participant_name, f.project_title, COUNT(v.id) AS vote_count '
            . 'FROM finalists f LEFT JOIN votes v ON v.finalist_id = f.id AND v.status = "valid" '
            . 'WHERE f.active = 1 GROUP BY f.id ORDER BY vote_count DESC, f.id ASC'
        )->fetchAll();
        $total = array_sum(array_map(static fn (array $row): int => (int) $row['vote_count'], $ranking));
        foreach ($ranking as &$row) {
            $row['percentage'] = $total > 0 ? round(((int) $row['vote_count'] / $total) * 100, 1) : 0;
            unset($row['vote_count']);
        }
        $metrics = [
            'accesses_24h' => (int) $pdo->query('SELECT COUNT(*) FROM audit_events WHERE event_type = "http.request" AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)')->fetchColumn(),
            'review_queue' => (int) $pdo->query('SELECT COUNT(*) FROM votes WHERE status = "review"')->fetchColumn(),
            'high_risk_24h' => (int) $pdo->query('SELECT COUNT(*) FROM audit_events WHERE risk_score >= 60 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)')->fetchColumn(),
            'registrations' => (int) $pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn(),
        ];
        $recent = $pdo->query('SELECT id, event_type, actor_type, country_code, risk_score, request_path, created_at FROM audit_events ORDER BY id DESC LIMIT 20')->fetchAll();
        View::render('admin/dashboard', compact('user', 'ranking', 'metrics', 'recent') + ['title' => 'Visão geral'], 'admin/layout');
    }

    public function finalists(): void
    {
        $user = Auth::requireAdministrator();
        $rows = Database::connection()->query('SELECT * FROM finalists ORDER BY active DESC, sort_order ASC, id ASC')->fetchAll();
        View::render('admin/finalists', ['title' => 'Finalistas', 'user' => $user, 'finalists' => $rows, 'message' => $_SESSION['admin_message'] ?? null, 'error' => $_SESSION['admin_error'] ?? null], 'admin/layout');
        unset($_SESSION['admin_message'], $_SESSION['admin_error']);
    }

    public function saveFinalist(): never
    {
        $user = Auth::requireAdministrator();
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $_SESSION['admin_error'] = 'Sessão expirada.';
            Response::redirect('/admin/finalistas');
        }
        $pdo = Database::connection();
        $id = (int) ($_POST['id'] ?? 0);
        $active = !empty($_POST['active']) ? 1 : 0;
        $participant = trim((string) ($_POST['participant_name'] ?? ''));
        $title = trim((string) ($_POST['project_title'] ?? ''));
        $instagram = trim((string) ($_POST['instagram_url'] ?? ''));
        if ($participant === '' || $title === '' || !filter_var($instagram, FILTER_VALIDATE_URL)) {
            $_SESSION['admin_error'] = 'Preencha nome, projeto e uma URL válida do Instagram.';
            Response::redirect('/admin/finalistas');
        }
        if ($active) {
            $count = $pdo->prepare('SELECT COUNT(*) FROM finalists WHERE active = 1 AND id <> ?');
            $count->execute([$id]);
            if ((int) $count->fetchColumn() >= 3) {
                $_SESSION['admin_error'] = 'Desative um finalista antes de ativar outro. A home aceita exatamente três.';
                Response::redirect('/admin/finalistas');
            }
        }
        $embed = trim((string) ($_POST['instagram_embed_url'] ?? ''));
        $fallback = trim((string) ($_POST['fallback_image_url'] ?? ''));
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE finalists SET participant_name = ?, project_title = ?, instagram_url = ?, instagram_embed_url = ?, fallback_image_url = ?, active = ?, sort_order = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$participant, $title, $instagram, $embed ?: null, $fallback ?: null, $active, (int) ($_POST['sort_order'] ?? 0), $id]);
            Audit::log('admin.finalist_updated', ['finalist_id' => $id, 'active' => $active], 'admin', (int) $user['id']);
        } else {
            $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', mb_strtolower($participant . '-' . $title)))) ?: 'finalista-' . time();
            $stmt = $pdo->prepare('INSERT INTO finalists (slug, participant_name, project_title, instagram_url, instagram_embed_url, fallback_image_url, active, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([$slug, $participant, $title, $instagram, $embed ?: null, $fallback ?: null, $active, (int) ($_POST['sort_order'] ?? 0)]);
            Audit::log('admin.finalist_created', ['finalist_id' => (int) $pdo->lastInsertId(), 'active' => $active], 'admin', (int) $user['id']);
        }
        $_SESSION['admin_message'] = 'Finalista salvo.';
        Response::redirect('/admin/finalistas');
    }

    public function disableFinalist(): never
    {
        $user = Auth::requireAdministrator();
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            Response::redirect('/admin/finalistas');
        }
        $id = (int) ($_POST['id'] ?? 0);
        Database::connection()->prepare('UPDATE finalists SET active = 0, updated_at = NOW() WHERE id = ?')->execute([$id]);
        Audit::log('admin.finalist_disabled', ['finalist_id' => $id], 'admin', (int) $user['id']);
        $_SESSION['admin_message'] = 'Finalista removido da home sem apagar o histórico.';
        Response::redirect('/admin/finalistas');
    }

    public function audit(): void
    {
        $user = Auth::requireUser();
        $pdo = Database::connection();
        $status = in_array($_GET['status'] ?? '', ['valid', 'review', 'invalid'], true) ? (string) $_GET['status'] : '';
        $risk = max(0, min(100, (int) ($_GET['risk'] ?? 0)));
        $where = [];
        $params = [];
        if ($status !== '') {
            $where[] = 'v.status = ?';
            $params[] = $status;
        }
        if ($risk > 0) {
            $where[] = 'v.risk_score >= ?';
            $params[] = $risk;
        }
        $sql = 'SELECT v.id, v.receipt_code, v.status, v.risk_score, v.risk_signals_json, v.country_code, v.device_hash, v.ip_hash, v.confirmed_at, f.participant_name, f.project_title '
            . 'FROM votes v JOIN finalists f ON f.id = v.finalist_id '
            . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY v.id DESC LIMIT 250';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $votes = $stmt->fetchAll();
        $events = $pdo->query('SELECT id, event_type, actor_type, actor_id, country_code, risk_score, request_method, request_path, metadata_json, entry_hash, created_at FROM audit_events ORDER BY id DESC LIMIT 250')->fetchAll();
        View::render('admin/audit', ['title' => 'Auditoria', 'user' => $user, 'votes' => $votes, 'events' => $events, 'chain' => Audit::verifyChain(), 'filters' => compact('status', 'risk')], 'admin/layout');
    }

    public function updateVote(): never
    {
        $user = Auth::requireUser();
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            Response::redirect('/admin/auditoria');
        }
        $id = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'review');
        if (!in_array($status, ['valid', 'review', 'invalid'], true)) {
            $status = 'review';
        }
        $notes = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 2000);
        $stmt = Database::connection()->prepare('UPDATE votes SET status = ?, review_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?');
        $stmt->execute([$status, $notes, (int) $user['id'], $id]);
        Audit::log('admin.vote_reviewed', ['vote_id' => $id, 'status' => $status, 'notes_hash' => Security::secretHash($notes)], 'admin', (int) $user['id']);
        Response::redirect('/admin/auditoria');
    }

    public function exportAudit(): never
    {
        $user = Auth::requireUser();
        Audit::log('admin.audit_exported', [], 'admin', (int) $user['id']);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="auditoria-passaporte-ruffino-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['id', 'evento', 'ator', 'pais', 'risco', 'metodo', 'rota', 'hash', 'data'], ';');
        $stmt = Database::connection()->query('SELECT id, event_type, actor_type, country_code, risk_score, request_method, request_path, entry_hash, created_at FROM audit_events ORDER BY id ASC');
        while ($row = $stmt->fetch()) {
            fputcsv($out, array_values($row), ';');
        }
        fclose($out);
        exit;
    }

    public function settings(): void
    {
        $user = Auth::requireAdministrator();
        View::render('admin/settings', [
            'title' => 'Configurações', 'user' => $user,
            'settings' => [
                'public_ranking_enabled' => Settings::bool('public_ranking_enabled', false),
                'voting_manual_closed' => Settings::bool('voting_manual_closed', false),
                'registration_manual_closed' => Settings::bool('registration_manual_closed', false),
            ],
            'message' => $_SESSION['admin_message'] ?? null,
        ], 'admin/layout');
        unset($_SESSION['admin_message']);
    }

    public function saveSettings(): never
    {
        $user = Auth::requireAdministrator();
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            Response::redirect('/admin/configuracoes');
        }
        foreach (['public_ranking_enabled', 'voting_manual_closed', 'registration_manual_closed'] as $key) {
            Settings::set($key, !empty($_POST[$key]) ? '1' : '0');
        }
        Audit::log('admin.settings_updated', ['keys' => ['public_ranking_enabled', 'voting_manual_closed', 'registration_manual_closed']], 'admin', (int) $user['id']);
        $_SESSION['admin_message'] = 'Configurações atualizadas.';
        Response::redirect('/admin/configuracoes');
    }

    public function registrations(): void
    {
        $user = Auth::requireAdministrator();
        $rows = Database::connection()->query('SELECT id, reference_code, encrypted_payload, status, country_code, consented_at, created_at FROM registrations ORDER BY id DESC LIMIT 200')->fetchAll();
        foreach ($rows as &$row) {
            $row['data'] = Privacy::decrypt((string) $row['encrypted_payload']);
            unset($row['encrypted_payload']);
        }
        View::render('admin/registrations', ['title' => 'Inscrições', 'user' => $user, 'registrations' => $rows], 'admin/layout');
    }

    public function registrationFiles(int $registrationId): never
    {
        Auth::requireAdministrator();
        $stmt = Database::connection()->prepare('SELECT id, file_kind, original_name, mime_type, size_bytes FROM registration_files WHERE registration_id = ? ORDER BY id ASC');
        $stmt->execute([$registrationId]);
        Response::json(['ok' => true, 'files' => $stmt->fetchAll()]);
    }

    public function downloadRegistrationFile(int $fileId): never
    {
        $user = Auth::requireAdministrator();
        $stmt = Database::connection()->prepare('SELECT rf.*, r.reference_code FROM registration_files rf JOIN registrations r ON r.id = rf.registration_id WHERE rf.id = ? LIMIT 1');
        $stmt->execute([$fileId]);
        $file = $stmt->fetch();
        if (!$file) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }
        $path = dirname(__DIR__, 2) . '/storage/uploads/' . strtolower((string) $file['reference_code']) . '/' . basename((string) $file['stored_name']);
        if (!is_file($path) || !hash_equals((string) $file['sha256'], hash_file('sha256', $path))) {
            throw new RuntimeException('Arquivo ausente ou com integridade inválida.');
        }
        Audit::log('admin.registration_file_downloaded', ['file_id' => $fileId, 'registration_id' => (int) $file['registration_id']], 'admin', (int) $user['id']);
        header('Content-Type: ' . $file['mime_type']);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: attachment; filename="' . rawurlencode((string) $file['original_name']) . '"');
        readfile($path);
        exit;
    }
}
