<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Analytics;
use App\Core\Auth;
use App\Core\Captcha;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Privacy;
use App\Core\Response;
use App\Core\Schema;
use App\Core\Security;
use App\Core\Settings;
use App\Core\View;
use RuntimeException;
use Throwable;

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
        if (!Auth::can('audit', $user)) {
            if (Auth::can('analytics', $user)) { Response::redirect('/admin/analytics'); }
            View::render('admin/welcome', ['title' => 'Painel', 'user' => $user], 'admin/layout');
            return;
        }
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

        ];
        $analyticsReady = Auth::can('analytics', $user) && Analytics::ready();
        $online = $analyticsReady ? Analytics::online() : null;
        $recent = $pdo->query('SELECT id, event_type, actor_type, country_code, risk_score, request_path, created_at FROM audit_events ORDER BY id DESC LIMIT 20')->fetchAll();
        View::render('admin/dashboard', compact('user', 'ranking', 'metrics', 'recent', 'online') + ['title' => 'Visão geral'], 'admin/layout');
    }

    public function finalists(): void
    {
        $user = Auth::requirePermission('finalists');
        Schema::ensureFinalistImages();
        $rows = Database::connection()->query('SELECT * FROM finalists ORDER BY active DESC, sort_order ASC, id ASC')->fetchAll();
        $images = Database::connection()->query('SELECT * FROM finalist_images ORDER BY finalist_id ASC, is_cover DESC, sort_order ASC, id ASC')->fetchAll();
        $byFinalist = [];
        foreach ($images as $image) {
            $byFinalist[(int) $image['finalist_id']][] = $image;
        }
        foreach ($rows as &$row) {
            $row['images'] = $byFinalist[(int) $row['id']] ?? [];
        }
        unset($row);
        View::render('admin/finalists', ['title' => 'Finalistas', 'user' => $user, 'finalists' => $rows, 'maxImages' => max(1, (int) Config::get('security.max_finalist_images', 10)), 'message' => $_SESSION['admin_message'] ?? null, 'error' => $_SESSION['admin_error'] ?? null], 'admin/layout');
        unset($_SESSION['admin_message'], $_SESSION['admin_error']);
    }

    public function saveFinalist(): never
    {
        $user = Auth::requirePermission('finalists');
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
                $_SESSION['admin_error'] = 'Desative um finalista antes de ativar outro. A página de votação aceita exatamente três.';
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
        $user = Auth::requirePermission('finalists');
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            Response::redirect('/admin/finalistas');
        }
        $id = (int) ($_POST['id'] ?? 0);
        Database::connection()->prepare('UPDATE finalists SET active = 0, updated_at = NOW() WHERE id = ?')->execute([$id]);
        Audit::log('admin.finalist_disabled', ['finalist_id' => $id], 'admin', (int) $user['id']);
        $_SESSION['admin_message'] = 'Finalista arquivado sem apagar fotos, votos ou histórico.';
        Response::redirect('/admin/finalistas');
    }

    public function uploadFinalistImages(): never
    {
        $user = Auth::requirePermission('finalists');
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
            $_SESSION['admin_error'] = 'O lote de fotos ultrapassou o limite do servidor. Envie menos imagens por vez ou reduza o tamanho dos arquivos.';
            Response::redirect('/admin/finalistas');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $_SESSION['admin_error'] = 'Sessão expirada.';
            Response::redirect('/admin/finalistas');
        }
        Schema::ensureFinalistImages();
        $finalistId = (int) ($_POST['finalist_id'] ?? 0);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, project_title FROM finalists WHERE id = ? LIMIT 1');
        $stmt->execute([$finalistId]);
        $finalist = $stmt->fetch();
        if (!$finalist) {
            $_SESSION['admin_error'] = 'Finalista não encontrado.';
            Response::redirect('/admin/finalistas');
        }

        $files = $this->normalizeImageUploads($_FILES['images'] ?? []);
        $maxImages = max(1, (int) Config::get('security.max_finalist_images', 10));
        $current = $pdo->prepare('SELECT COUNT(*) FROM finalist_images WHERE finalist_id = ?');
        $current->execute([$finalistId]);
        if (!$files || (int) $current->fetchColumn() + count($files) > $maxImages) {
            $_SESSION['admin_error'] = 'Selecione imagens válidas sem ultrapassar o limite de ' . $maxImages . ' fotos por finalista.';
            Response::redirect('/admin/finalistas#finalista-' . $finalistId);
        }

        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $maxBytes = max(1048576, (int) Config::get('security.max_finalist_image_bytes', 8388608));
        $validated = [];
        try {
            foreach ($files as $file) {
                $uploadError = (int) $file['error'];
                if ($uploadError !== UPLOAD_ERR_OK) {
                    $message = match ($uploadError) {
                        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uma das fotos ultrapassa o limite de 8 MB.',
                        UPLOAD_ERR_PARTIAL => 'Uma das fotos foi enviada apenas parcialmente. Tente novamente.',
                        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu armazenar as fotos temporariamente. Tente novamente em instantes.',
                        default => 'Uma das fotos não foi recebida corretamente.',
                    };
                    throw new RuntimeException($message);
                }
                if (!is_uploaded_file((string) $file['tmp_name'])) {
                    throw new RuntimeException('Uma das fotos não foi recebida corretamente.');
                }
                if ((int) $file['size'] < 1 || (int) $file['size'] > $maxBytes) {
                    throw new RuntimeException('Cada foto deve ter no máximo ' . number_format($maxBytes / 1048576, 0, ',', '.') . ' MB.');
                }
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
                $dimensions = @getimagesize((string) $file['tmp_name']);
                if (!isset($allowed[$mime]) || !is_array($dimensions) || ($dimensions['mime'] ?? '') !== $mime) {
                    throw new RuntimeException('Envie apenas imagens JPG, PNG ou WebP válidas.');
                }
                $width = (int) ($dimensions[0] ?? 0);
                $height = (int) ($dimensions[1] ?? 0);
                if ($width < 600 || $height < 600 || $width > 10000 || $height > 10000) {
                    throw new RuntimeException('Cada foto deve ter entre 600 e 10.000 pixels em cada dimensão.');
                }
                $validated[] = [
                    'tmp_name' => (string) $file['tmp_name'],
                    'original_name' => mb_substr(basename((string) $file['name']), 0, 255),
                    'mime' => $mime,
                    'extension' => $allowed[$mime],
                    'size' => (int) $file['size'],
                    'width' => $width,
                    'height' => $height,
                    'sha256' => hash_file('sha256', (string) $file['tmp_name']),
                ];
            }
        } catch (RuntimeException $e) {
            $_SESSION['admin_error'] = $e->getMessage();
            Response::redirect('/admin/finalistas#finalista-' . $finalistId);
        }

        $folder = dirname(__DIR__, 2) . '/storage/finalists/' . $finalistId;
        if (!is_dir($folder) && !mkdir($folder, 0700, true) && !is_dir($folder)) {
            $_SESSION['admin_error'] = 'Não foi possível preparar o armazenamento das fotos.';
            Response::redirect('/admin/finalistas#finalista-' . $finalistId);
        }
        $storedPaths = [];
        try {
            Database::transaction(function ($pdo) use ($validated, $folder, $finalist, $finalistId, $user, &$storedPaths): void {
                $next = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM finalist_images WHERE finalist_id = ?');
                $next->execute([$finalistId]);
                $sort = (int) $next->fetchColumn();
                $cover = $pdo->prepare('SELECT COUNT(*) FROM finalist_images WHERE finalist_id = ? AND is_cover = 1');
                $cover->execute([$finalistId]);
                $hasCover = (int) $cover->fetchColumn() > 0;
                $insert = $pdo->prepare(
                    'INSERT INTO finalist_images (finalist_id, original_name, stored_name, mime_type, size_bytes, width_px, height_px, sha256, alt_text, sort_order, is_cover, created_at) '
                    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $imageIds = [];
                foreach ($validated as $index => $file) {
                    $storedName = bin2hex(random_bytes(16)) . '.' . $file['extension'];
                    $target = $folder . '/' . $storedName;
                    if (!move_uploaded_file($file['tmp_name'], $target)) {
                        throw new RuntimeException('Não foi possível armazenar uma das fotos.');
                    }
                    chmod($target, 0600);
                    $storedPaths[] = $target;
                    $isCover = !$hasCover && $index === 0 ? 1 : 0;
                    $sort++;
                    $insert->execute([
                        $finalistId,
                        $file['original_name'],
                        $storedName,
                        $file['mime'],
                        $file['size'],
                        $file['width'],
                        $file['height'],
                        $file['sha256'],
                        mb_substr('Projeto ' . (string) $finalist['project_title'] . ' — foto ' . $sort, 0, 255),
                        $sort,
                        $isCover,
                    ]);
                    $imageIds[] = (int) $pdo->lastInsertId();
                }
                Audit::log('admin.finalist_images_uploaded', ['finalist_id' => $finalistId, 'image_ids' => $imageIds, 'count' => count($imageIds)], 'admin', (int) $user['id'], 0, $pdo);
            });
        } catch (Throwable $e) {
            foreach ($storedPaths as $storedPath) {
                if (is_file($storedPath)) {
                    unlink($storedPath);
                }
            }
            error_log((string) $e);
            $_SESSION['admin_error'] = $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível salvar as fotos.';
            Response::redirect('/admin/finalistas#finalista-' . $finalistId);
        }
        $_SESSION['admin_message'] = count($validated) === 1 ? 'Foto adicionada ao carrossel.' : count($validated) . ' fotos adicionadas ao carrossel.';
        Response::redirect('/admin/finalistas#finalista-' . $finalistId);
    }

    public function setFinalistImageCover(): never
    {
        $user = Auth::requirePermission('finalists');
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            Response::redirect('/admin/finalistas');
        }
        Schema::ensureFinalistImages();
        $imageId = (int) ($_POST['image_id'] ?? 0);
        $image = $this->finalistImageRow($imageId);
        if (!$image) {
            $_SESSION['admin_error'] = 'Foto não encontrada.';
            Response::redirect('/admin/finalistas');
        }
        Database::transaction(function ($pdo) use ($imageId, $image, $user): void {
            $current = $pdo->prepare('SELECT id, sort_order FROM finalist_images WHERE finalist_id = ? AND is_cover = 1 LIMIT 1 FOR UPDATE');
            $current->execute([(int) $image['finalist_id']]);
            $currentCover = $current->fetch();
            if ($currentCover && (int) $currentCover['id'] !== $imageId) {
                $pdo->prepare('UPDATE finalist_images SET sort_order = ? WHERE id = ?')->execute([(int) $currentCover['sort_order'], $imageId]);
                $pdo->prepare('UPDATE finalist_images SET sort_order = ? WHERE id = ?')->execute([(int) $image['sort_order'], (int) $currentCover['id']]);
            }
            $pdo->prepare('UPDATE finalist_images SET is_cover = 0 WHERE finalist_id = ?')->execute([(int) $image['finalist_id']]);
            $pdo->prepare('UPDATE finalist_images SET is_cover = 1 WHERE id = ? AND finalist_id = ?')->execute([$imageId, (int) $image['finalist_id']]);
            Audit::log('admin.finalist_image_cover_set', ['finalist_id' => (int) $image['finalist_id'], 'image_id' => $imageId], 'admin', (int) $user['id'], 0, $pdo);
        });
        $_SESSION['admin_message'] = 'Capa do carrossel atualizada.';
        Response::redirect('/admin/finalistas#finalista-' . (int) $image['finalist_id']);
    }

    public function moveFinalistImage(): never
    {
        $user = Auth::requirePermission('finalists');
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            Response::redirect('/admin/finalistas');
        }
        Schema::ensureFinalistImages();
        $imageId = (int) ($_POST['image_id'] ?? 0);
        $direction = ($_POST['direction'] ?? '') === 'up' ? -1 : 1;
        $image = $this->finalistImageRow($imageId);
        if (!$image) {
            $_SESSION['admin_error'] = 'Foto não encontrada.';
            Response::redirect('/admin/finalistas');
        }
        $stmt = Database::connection()->prepare('SELECT id, sort_order FROM finalist_images WHERE finalist_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([(int) $image['finalist_id']]);
        $ordered = $stmt->fetchAll();
        $position = array_search($imageId, array_map(static fn (array $row): int => (int) $row['id'], $ordered), true);
        $swapPosition = $position === false ? false : $position + $direction;
        if ($position !== false && isset($ordered[$swapPosition])) {
            $other = $ordered[$swapPosition];
            Database::transaction(function ($pdo) use ($image, $other, $user): void {
                $pdo->prepare('UPDATE finalist_images SET sort_order = ? WHERE id = ?')->execute([(int) $other['sort_order'], (int) $image['id']]);
                $pdo->prepare('UPDATE finalist_images SET sort_order = ? WHERE id = ?')->execute([(int) $image['sort_order'], (int) $other['id']]);
                Audit::log('admin.finalist_image_moved', ['finalist_id' => (int) $image['finalist_id'], 'image_id' => (int) $image['id']], 'admin', (int) $user['id'], 0, $pdo);
            });
        }
        Response::redirect('/admin/finalistas#finalista-' . (int) $image['finalist_id']);
    }

    public function reorderFinalistImages(): never
    {
        $user = Auth::requirePermission('finalists');
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            Response::json(['ok' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], 419);
        }
        Schema::ensureFinalistImages();
        $finalistId = (int) ($_POST['finalist_id'] ?? 0);
        $submitted = $_POST['image_ids'] ?? [];
        $imageIds = is_array($submitted)
            ? array_values(array_unique(array_filter(array_map('intval', $submitted), static fn (int $id): bool => $id > 0)))
            : [];

        if ($finalistId < 1 || !$imageIds) {
            Response::json(['ok' => false, 'message' => 'A ordem enviada é inválida.'], 422);
        }

        try {
            Database::transaction(function ($pdo) use ($finalistId, $imageIds, $user): void {
                $stmt = $pdo->prepare(
                    'SELECT id, is_cover FROM finalist_images WHERE finalist_id = ? ORDER BY is_cover DESC, sort_order ASC, id ASC FOR UPDATE'
                );
                $stmt->execute([$finalistId]);
                $images = $stmt->fetchAll();
                $storedIds = array_map(static fn (array $row): int => (int) $row['id'], $images);
                $submittedIds = $imageIds;
                sort($storedIds);
                sort($submittedIds);
                if ($storedIds !== $submittedIds) {
                    throw new RuntimeException('A galeria mudou. Atualize a página antes de reorganizar novamente.');
                }

                $coverId = null;
                foreach ($images as $image) {
                    if ((int) $image['is_cover'] === 1) {
                        $coverId = (int) $image['id'];
                        break;
                    }
                }
                if ($coverId !== null && $imageIds[0] !== $coverId) {
                    throw new RuntimeException('A foto de capa deve permanecer na primeira posição.');
                }

                $update = $pdo->prepare('UPDATE finalist_images SET sort_order = ? WHERE id = ? AND finalist_id = ?');
                foreach ($imageIds as $index => $imageId) {
                    $update->execute([$index + 1, $imageId, $finalistId]);
                }
                Audit::log(
                    'admin.finalist_images_reordered',
                    ['finalist_id' => $finalistId, 'image_ids' => $imageIds],
                    'admin',
                    (int) $user['id'],
                    0,
                    $pdo
                );
            });
        } catch (RuntimeException $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Ordem das fotos salva.']);
    }

    public function deleteFinalistImage(): never
    {
        $user = Auth::requirePermission('finalists');
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            Response::redirect('/admin/finalistas');
        }
        Schema::ensureFinalistImages();
        $imageId = (int) ($_POST['image_id'] ?? 0);
        $image = $this->finalistImageRow($imageId);
        if (!$image) {
            $_SESSION['admin_error'] = 'Foto não encontrada.';
            Response::redirect('/admin/finalistas');
        }
        Database::transaction(function ($pdo) use ($imageId, $image, $user): void {
            $pdo->prepare('DELETE FROM finalist_images WHERE id = ? AND finalist_id = ?')->execute([$imageId, (int) $image['finalist_id']]);
            if ((int) $image['is_cover'] === 1) {
                $next = $pdo->prepare('SELECT id FROM finalist_images WHERE finalist_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1');
                $next->execute([(int) $image['finalist_id']]);
                $nextId = $next->fetchColumn();
                if ($nextId !== false) {
                    $pdo->prepare('UPDATE finalist_images SET is_cover = 1 WHERE id = ?')->execute([(int) $nextId]);
                }
            }
            Audit::log('admin.finalist_image_deleted', ['finalist_id' => (int) $image['finalist_id'], 'image_id' => $imageId, 'sha256' => (string) $image['sha256']], 'admin', (int) $user['id'], 0, $pdo);
        });
        $path = dirname(__DIR__, 2) . '/storage/finalists/' . (int) $image['finalist_id'] . '/' . basename((string) $image['stored_name']);
        if (is_file($path)) {
            unlink($path);
        }
        $_SESSION['admin_message'] = 'Foto removida permanentemente do carrossel.';
        Response::redirect('/admin/finalistas#finalista-' . (int) $image['finalist_id']);
    }

    private function finalistImageRow(int $imageId): array|false
    {
        $stmt = Database::connection()->prepare('SELECT * FROM finalist_images WHERE id = ? LIMIT 1');
        $stmt->execute([$imageId]);
        return $stmt->fetch();
    }

    private function normalizeImageUploads(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            return (int) ($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$files];
        }
        $normalized = [];
        foreach ($files['name'] as $index => $name) {
            $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $normalized[] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $error,
                'size' => $files['size'][$index] ?? 0,
            ];
        }
        return $normalized;
    }

    public function analytics(): void
    {
        $user = Auth::requirePermission('analytics');
        $range = Analytics::range($_GET);
        $ready = Analytics::ready();
        $report = $ready ? Analytics::report($range) : [];
        View::render('admin/analytics', compact('user', 'range', 'ready', 'report') + ['title' => 'Analytics'], 'admin/layout');
    }

    public function exportAnalytics(): never
    {
        $user = Auth::requirePermission('analytics');
        if (!Analytics::ready()) { http_response_code(503); exit('Analytics indisponível.'); }
        $range = Analytics::range($_GET);
        $report = Analytics::report($range);
        Audit::log('admin.analytics_exported', $range, 'admin', (int) $user['id']);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="analytics-' . $range['start'] . '-' . $range['end'] . '.csv"');
        $out = fopen('php://output', 'wb'); fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['dimensao', 'descricao', 'visualizacoes', 'visitantes'], ';');
        foreach ($report['breakdowns'] as $dimension => $rows) {
            foreach ($rows as $row) {
                $label = (string) $row['label'];
                if (preg_match('/^[\s]*[=+@-]/', $label)) { $label = "'" . $label; }
                fputcsv($out, [$dimension, $label, $row['views'], $row['visitors']], ';');
            }
        }
        foreach ($report['daily'] as $row) { fputcsv($out, ['dia', $row['day'], $row['views'], $row['visitors']], ';'); }
        fclose($out); exit;
    }

    public function online(): never
    {
        Auth::requirePermission('analytics');
        header('Cache-Control: no-store');
        if (!Analytics::ready()) { Response::json(['ok' => false], 503); }
        Response::json(['ok' => true, 'online' => Analytics::online(), 'updated_at' => date('H:i:s')]);
    }

    private static function pagination(int $total, string $key, int $size = 50): array
    {
        $pages = max(1, (int) ceil($total / $size));
        $page = max(1, min($pages, (int) ($_GET[$key] ?? 1)));
        return ['total' => $total, 'pages' => $pages, 'page' => $page, 'size' => $size, 'offset' => ($page - 1) * $size, 'key' => $key];
    }

    public function audit(): void
    {
        $user = Auth::requirePermission('audit');
        $pdo = Database::connection();
        $status = in_array($_GET['status'] ?? '', ['valid', 'review', 'invalid'], true) ? (string) $_GET['status'] : '';
        $risk = max(0, min(100, (int) ($_GET['risk'] ?? 0)));
        $where = []; $params = [];
        if ($status !== '') { $where[] = 'v.status = ?'; $params[] = $status; }
        if ($risk > 0) { $where[] = 'v.risk_score >= ?'; $params[] = $risk; }
        $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM votes v' . $clause); $stmt->execute($params);
        $votePager = self::pagination((int) $stmt->fetchColumn(), 'votes_page');
        $stmt = $pdo->prepare('SELECT v.*, f.participant_name, f.project_title, a.metadata_json FROM votes v JOIN finalists f ON f.id = v.finalist_id LEFT JOIN audit_events a ON a.id = v.audit_event_id' . $clause . ' ORDER BY v.id DESC LIMIT 50 OFFSET ' . $votePager['offset']);
        $stmt->execute($params);
        $votes = array_map(static fn ($row) => Analytics::eventDetails($row, Auth::can('view_ips', $user)), $stmt->fetchAll());
        $eventType = is_string($_GET['event'] ?? null) ? mb_substr(trim($_GET['event']), 0, 100) : '';
        $ip = is_string($_GET['ip'] ?? null) ? trim($_GET['ip']) : '';
        $range = Analytics::range($_GET);
        $where = ['created_at >= ?', 'created_at < ?']; $params = [$range['start'], $range['until']];
        if ($eventType !== '') { $where[] = 'event_type = ?'; $params[] = $eventType; }
        if ($ip !== '') { $where[] = 'ip_hash = ?'; $params[] = Security::secretHash($ip); }
        $clause = ' WHERE ' . implode(' AND ', $where);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM audit_events' . $clause); $stmt->execute($params);
        $eventPager = self::pagination((int) $stmt->fetchColumn(), 'events_page');
        $stmt = $pdo->prepare('SELECT id, event_type, actor_type, actor_id, country_code, risk_score, request_method, request_path, metadata_json, entry_hash, created_at FROM audit_events' . $clause . ' ORDER BY id DESC LIMIT 50 OFFSET ' . $eventPager['offset']);
        $stmt->execute($params);
        $events = array_map(static fn ($row) => Analytics::eventDetails($row, Auth::can('view_ips', $user)), $stmt->fetchAll());
        $chain = ($_GET['verify'] ?? '') === '1' ? Audit::verifyChain() : null;
        View::render('admin/audit', compact('user', 'votes', 'events', 'chain', 'votePager', 'eventPager', 'range', 'eventType', 'ip') + ['title' => 'Auditoria', 'filters' => compact('status', 'risk')], 'admin/layout');
    }

    public function updateVote(): never
    {
        $user = Auth::requirePermission('review_votes');
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
        $user = Auth::requirePermission('export_audit');
        Audit::log('admin.audit_exported', [], 'admin', (int) $user['id']);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="auditoria-passaporte-ruffino-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['id', 'evento', 'ator', 'pais', 'risco', 'metodo', 'rota', 'hash', 'data', 'ip_completo', 'cidade', 'regiao'], ';');
        $stmt = Database::connection()->query('SELECT id, event_type, actor_type, country_code, risk_score, request_method, request_path, entry_hash, created_at, metadata_json FROM audit_events ORDER BY id ASC');
        while ($row = $stmt->fetch()) {
            $row = Analytics::eventDetails($row, Auth::can('view_ips', $user));
            unset($row['metadata_json']);
            $values = array_map(static fn ($v) => preg_match('/^[\s]*[=+@-]/', (string) $v) ? "'" . $v : $v, array_values($row));
            fputcsv($out, $values, ';');
        }
        fclose($out);
        exit;
    }

    public function settings(): void
    {
        $user = Auth::requirePermission('settings');
        View::render('admin/settings', [
            'title' => 'Configurações', 'user' => $user,
            'settings' => [
                'page_home_enabled' => Settings::bool('page_home_enabled', true),
                'page_voting_enabled' => Settings::bool('page_voting_enabled', false),
                'page_jurors_enabled' => Settings::bool('page_jurors_enabled', false),
                'public_ranking_enabled' => Settings::bool('public_ranking_enabled', false),
                'voting_manual_closed' => Settings::bool('voting_manual_closed', false),

            ],
            'message' => $_SESSION['admin_message'] ?? null,
        ], 'admin/layout');
        unset($_SESSION['admin_message']);
    }

    public function saveSettings(): never
    {
        $user = Auth::requirePermission('settings');
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            Response::redirect('/admin/configuracoes');
        }
        foreach (['public_ranking_enabled', 'voting_manual_closed', 'page_home_enabled', 'page_voting_enabled', 'page_jurors_enabled'] as $key) {
            Settings::set($key, !empty($_POST[$key]) ? '1' : '0');
        }
        Audit::log('admin.settings_updated', ['keys' => ['public_ranking_enabled', 'voting_manual_closed', 'page_home_enabled', 'page_voting_enabled', 'page_jurors_enabled']], 'admin', (int) $user['id']);
        $_SESSION['admin_message'] = 'Configurações atualizadas.';
        Response::redirect('/admin/configuracoes');
    }

    public function registrations(): void
    {
        $user = Auth::requirePermission('registrations');
        $rows = Database::connection()->query('SELECT id, reference_code, encrypted_payload, status, country_code, consented_at, created_at FROM registrations ORDER BY id DESC LIMIT 200')->fetchAll();
        foreach ($rows as &$row) {
            $row['data'] = Privacy::decrypt((string) $row['encrypted_payload']);
            unset($row['encrypted_payload']);
        }
        View::render('admin/registrations', ['title' => 'Inscrições', 'user' => $user, 'registrations' => $rows], 'admin/layout');
    }

    public function permissions(): void
    {
        $user = Auth::requireAdministrator();
        View::render('admin/permissions', ['title' => 'Permissões por nível', 'user' => $user, 'permissions' => Auth::permissions(), 'allowed' => Auth::rolePermissions(), 'message' => $_SESSION['permissions_message'] ?? null], 'admin/layout');
        unset($_SESSION['permissions_message']);
    }

    public function savePermissions(): never
    {
        $user = Auth::requireAdministrator();
        if (!Csrf::verify(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null)) { http_response_code(403); exit('Sessão expirada.'); }
        $submitted = is_array($_POST['permissions'] ?? null) ? $_POST['permissions'] : [];
        $allowed = array_values(array_intersect(array_keys(Auth::permissions()), array_filter($submitted, 'is_string')));
        if (array_intersect($allowed, ['review_votes', 'export_audit', 'view_ips'])) { $allowed[] = 'audit'; $allowed = array_values(array_unique($allowed)); }
        Database::transaction(static function ($pdo) use ($allowed, $user): void {
            Settings::set('role_permissions_auditor', (string) json_encode($allowed));
            Audit::log('admin.permissions_updated', ['role' => 'auditor', 'permissions' => $allowed], 'admin', (int) $user['id'], 0, $pdo);
        });
        $_SESSION['permissions_message'] = 'Permissões do nível Auditor atualizadas. Administradores mantêm acesso completo.';
        Response::redirect('/admin/permissoes');
    }

    public function users(): void
    {
        $user = Auth::requireAdministrator();
        $rows = Database::connection()->query('SELECT id, name, email, role, active, last_login_at, created_at FROM admin_users ORDER BY active DESC, name ASC')->fetchAll();
        View::render('admin/users', [
            'title' => 'Usuários',
            'user' => $user,
            'users' => $rows,
            'message' => $_SESSION['admin_message'] ?? null,
            'error' => $_SESSION['admin_error'] ?? null,
        ], 'admin/layout');
        unset($_SESSION['admin_message'], $_SESSION['admin_error']);
    }

    public function saveUser(): never
    {
        $user = Auth::requireAdministrator();
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $_SESSION['admin_error'] = 'Sessão expirada.';
            Response::redirect('/admin/usuarios');
        }
        $id = (int) ($_POST['id'] ?? 0);
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120);
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['administrator', 'auditor'], true) ? (string) $_POST['role'] : 'auditor';
        $active = !empty($_POST['active']) ? 1 : 0;
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || ($id < 1 && strlen($password) < 12) || ($password !== '' && strlen($password) < 12)) {
            $_SESSION['admin_error'] = 'Informe nome, e-mail válido e uma senha com pelo menos 12 caracteres.';
            Response::redirect('/admin/usuarios');
        }
        if ($id === (int) $user['id']) {
            $active = 1;
            $role = 'administrator';
        }
        $pdo = Database::connection();
        try {
            if ($id > 0) {
                if ($password !== '') {
                    $stmt = $pdo->prepare('UPDATE admin_users SET name = ?, email = ?, password_hash = ?, role = ?, active = ?, updated_at = NOW() WHERE id = ?');
                    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $active, $id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE admin_users SET name = ?, email = ?, role = ?, active = ?, updated_at = NOW() WHERE id = ?');
                    $stmt->execute([$name, $email, $role, $active, $id]);
                }
                Audit::log('admin.user_updated', ['user_id' => $id, 'role' => $role, 'active' => $active, 'password_changed' => $password !== ''], 'admin', (int) $user['id']);
                $_SESSION['admin_message'] = 'Usuário atualizado.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO admin_users (name, email, password_hash, role, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $active]);
                Audit::log('admin.user_created', ['user_id' => (int) $pdo->lastInsertId(), 'role' => $role], 'admin', (int) $user['id']);
                $_SESSION['admin_message'] = 'Novo usuário criado.';
            }
        } catch (\PDOException $e) {
            $_SESSION['admin_error'] = $e->getCode() === '23000' ? 'Já existe um usuário com esse e-mail.' : 'Não foi possível salvar o usuário.';
        }
        Response::redirect('/admin/usuarios');
    }

    public function registrationFiles(int $registrationId): never
    {
        Auth::requirePermission('registrations');
        $stmt = Database::connection()->prepare('SELECT id, file_kind, original_name, mime_type, size_bytes FROM registration_files WHERE registration_id = ? ORDER BY id ASC');
        $stmt->execute([$registrationId]);
        Response::json(['ok' => true, 'files' => $stmt->fetchAll()]);
    }

    public function downloadRegistrationFile(int $fileId): never
    {
        $user = Auth::requirePermission('registrations');
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
