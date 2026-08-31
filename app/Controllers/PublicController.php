<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Campaign;
use App\Core\Captcha;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Geo;
use App\Core\Privacy;
use App\Core\Response;
use App\Core\Security;
use App\Core\Settings;
use App\Core\View;
use PDO;
use RuntimeException;
use Throwable;

final class PublicController
{
    public function home(): void
    {
        if (!Auth::user()) {
            View::render('access-login', [
                'title' => 'Acesso ao Passaporte Ruffino',
                'error' => $_SESSION['site_login_error'] ?? null,
            ], 'admin/layout-guest');
            unset($_SESSION['site_login_error']);
            return;
        }
        $this->logPageView('home');
        $pdo = Database::connection();
        $finalists = $pdo->query('SELECT id, slug, participant_name, project_title, instagram_url, instagram_embed_url, fallback_image_url FROM finalists WHERE active = 1 ORDER BY RAND() LIMIT 3')->fetchAll();
        $_SESSION['vote_page_at'] = time();
        $ranking = $this->ranking();
        View::render('home', [
            'title' => 'Vote no projeto finalista',
            'finalists' => $finalists,
            'ranking' => $ranking,
            'votingOpen' => Campaign::isVotingOpen() && count($finalists) === 3,
            'rankingEnabled' => true,
        ]);
    }

    public function authenticateSite(): never
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $_SESSION['site_login_error'] = 'Sessão expirada. Tente novamente.';
            Response::redirect('/');
        }
        if (Auth::attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            Response::redirect('/');
        }
        $_SESSION['site_login_error'] = 'Login ou senha inválidos.';
        Response::redirect('/');
    }

    public function captcha(string $scope): never
    {
        if (!in_array($scope, ['vote', 'registration'], true)) {
            Response::json(['ok' => false, 'message' => 'Escopo inválido.'], 400);
        }
        Response::json(['ok' => true, 'challenge' => Captcha::issue($scope), 'csrf' => Csrf::token()]);
    }

    public function vote(): never
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            Audit::log('vote.rejected', ['reason' => 'csrf'], 'visitor', null, 70);
            Response::json(['ok' => false, 'message' => 'Sessão expirada. Atualize a página.'], 419);
        }

        $risk = 0;
        $signals = [];
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            $risk = 100;
            $signals[] = 'honeypot';
        }
        if (!Captcha::verify('vote', $_POST['captcha_id'] ?? null, $_POST['captcha_answer'] ?? null)) {
            $risk = max($risk, 75);
            $signals[] = 'captcha_failed';
            Audit::log('vote.rejected', ['reason' => 'captcha', 'signals' => $signals], 'visitor', null, $risk);
            Response::json(['ok' => false, 'message' => 'Resposta de segurança incorreta. Tente novamente.'], 422);
        }
        if (!Geo::isBrazil()) {
            Audit::log('vote.rejected', ['reason' => 'country', 'country' => Geo::countryCode()], 'visitor', null, 100);
            Response::json(['ok' => false, 'message' => 'A votação está disponível somente no Brasil.'], 403);
        }
        if (!Campaign::isVotingOpen()) {
            Audit::log('vote.rejected', ['reason' => 'period'], 'visitor');
            Response::json(['ok' => false, 'message' => 'A votação não está aberta neste momento.'], 403);
        }
        if (Security::isSuspiciousUserAgent()) {
            $risk += 35;
            $signals[] = 'suspicious_user_agent';
        }
        if (trim((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) === '') {
            $risk += 10;
            $signals[] = 'missing_accept_language';
        }
        $pdo = Database::connection();
        $velocity = $pdo->prepare('SELECT COUNT(*) FROM audit_events WHERE ip_hash = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)');
        $velocity->execute([Security::ipHash()]);
        if ((int) $velocity->fetchColumn() > 20) {
            $risk += 25;
            $signals[] = 'high_request_velocity';
        }
        $networkVotes = $pdo->prepare('SELECT COUNT(*) FROM votes WHERE ip_hash = ?');
        $networkVotes->execute([Security::ipHash()]);
        $networkVoteCount = (int) $networkVotes->fetchColumn();
        if ($networkVoteCount >= 3) {
            $risk += 30;
            $signals[] = 'shared_network_many_votes';
        } elseif ($networkVoteCount >= 1) {
            $risk += 10;
            $signals[] = 'shared_network_repeat';
        }
        $elapsed = time() - (int) ($_SESSION['vote_page_at'] ?? time());
        if (!isset($_SESSION['vote_page_at'])) {
            $risk += 25;
            $signals[] = 'missing_page_view';
        } elseif ($elapsed < 8) {
            $risk += 30;
            $signals[] = 'too_fast';
        }
        $risk = min(100, $risk);
        $finalistId = filter_var($_POST['finalist_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$finalistId) {
            Response::json(['ok' => false, 'message' => 'Escolha um projeto finalista.'], 422);
        }

        try {
            $result = Database::transaction(function (PDO $pdo) use ($finalistId, $risk, $signals, $elapsed): array {
                $candidate = $pdo->prepare('SELECT id, participant_name, project_title FROM finalists WHERE id = ? AND active = 1 FOR UPDATE');
                $candidate->execute([$finalistId]);
                $finalist = $candidate->fetch();
                if (!$finalist) {
                    throw new RuntimeException('Finalista indisponível.');
                }

                $deviceHash = Security::deviceHash();
                $existing = $pdo->prepare('SELECT receipt_code FROM votes WHERE device_hash = ? LIMIT 1');
                $existing->execute([$deviceHash]);
                $receipt = $existing->fetchColumn();
                if ($receipt !== false) {
                    return ['duplicate' => true, 'receipt' => (string) $receipt];
                }

                if ((bool) Config::get('security.strict_ip_vote_limit', false)) {
                    $limit = max(1, (int) Config::get('security.ip_vote_limit', 3));
                    $ipCount = $pdo->prepare('SELECT COUNT(*) FROM votes WHERE ip_hash = ?');
                    $ipCount->execute([Security::ipHash()]);
                    if ((int) $ipCount->fetchColumn() >= $limit) {
                        Audit::log('vote.rejected', ['reason' => 'ip_limit'], 'visitor', null, 90, $pdo);
                        throw new RuntimeException('Limite de votos desta rede atingido.');
                    }
                }

                $receiptCode = 'PR-' . substr(Security::randomCode(12), 0, 24);
                $status = $risk >= 60 ? 'review' : 'valid';
                $insert = $pdo->prepare(
                    'INSERT INTO votes (finalist_id, receipt_code, device_hash, visitor_hash, ip_hash, user_agent_hash, country_code, risk_score, risk_signals_json, status, confirmed_at) '
                    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $insert->execute([
                    $finalistId, $receiptCode, $deviceHash, Security::visitorHash(), Security::ipHash(), Security::userAgentHash(),
                    Geo::countryCode(), $risk, json_encode($signals, JSON_UNESCAPED_UNICODE), $status,
                ]);
                $voteId = (int) $pdo->lastInsertId();
                $eventId = Audit::log('vote.confirmed', [
                    'vote_id' => $voteId,
                    'finalist_id' => (int) $finalistId,
                    'elapsed_seconds' => max(0, $elapsed),
                    'signals' => $signals,
                    'status' => $status,
                ], 'visitor', null, $risk, $pdo);
                $pdo->prepare('UPDATE votes SET audit_event_id = ? WHERE id = ?')->execute([$eventId, $voteId]);
                return ['duplicate' => false, 'receipt' => $receiptCode, 'status' => $status];
            });
        } catch (RuntimeException $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            error_log((string) $e);
            Response::json(['ok' => false, 'message' => 'Não foi possível registrar o voto. Tente novamente.'], 500);
        }

        if ($result['duplicate']) {
            Audit::log('vote.duplicate', ['receipt' => $result['receipt']], 'visitor', null, 45);
            Response::json(['ok' => false, 'duplicate' => true, 'receipt' => $result['receipt'], 'message' => 'Este dispositivo já confirmou um voto.'], 409);
        }
        unset($_SESSION['vote_page_at']);
        Response::json([
            'ok' => true,
            'receipt' => $result['receipt'],
            'message' => $result['status'] === 'review'
                ? 'Voto recebido e encaminhado para verificação de integridade.'
                : 'Voto confirmado. Guarde o comprovante para auditoria.',
        ], 201);
    }

    public function registration(): void
    {
        $this->logPageView('registration');
        View::render('registration', [
            'title' => 'Inscreva seu projeto',
            'registrationOpen' => Campaign::isRegistrationOpen(),
            'errors' => $_SESSION['form_errors'] ?? [],
            'old' => $_SESSION['form_old'] ?? [],
            'success' => $_SESSION['form_success'] ?? null,
        ]);
        unset($_SESSION['form_errors'], $_SESSION['form_old'], $_SESSION['form_success']);
    }

    public function submitRegistration(): never
    {
        $errors = [];
        $required = ['full_name', 'professional_registration', 'instagram', 'city', 'state', 'email', 'phone', 'retailer_name', 'environment_name'];
        foreach ($required as $field) {
            if (trim((string) ($_POST[$field] ?? '')) === '') {
                $errors[$field] = 'Campo obrigatório.';
            }
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $errors['_form'] = 'Sessão expirada. Atualize a página.';
        }
        if (!Campaign::isRegistrationOpen()) {
            $errors['_form'] = 'As inscrições não estão abertas neste momento.';
        }
        if (!filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Informe um e-mail válido.';
        }
        $validStates = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
        if (!in_array(strtoupper(trim((string) ($_POST['state'] ?? ''))), $validStates, true)) {
            $errors['state'] = 'Informe uma UF válida.';
        }
        if (empty($_POST['regulation_consent']) || empty($_POST['privacy_consent']) || empty($_POST['image_rights_consent'])) {
            $errors['consent'] = 'É necessário aceitar o regulamento e o aviso de privacidade.';
        }
        if (!Geo::isBrazil()) {
            $errors['_form'] = 'A campanha é exclusiva a profissionais no território nacional.';
        }
        if (!Captcha::verify('registration', $_POST['captcha_id'] ?? null, $_POST['captcha_answer'] ?? null)) {
            $errors['captcha'] = 'Resposta de segurança incorreta.';
        }
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            $errors['_form'] = 'Envio inválido.';
        }

        $photos = $this->normalizeFiles($_FILES['project_photos'] ?? []);
        if (count($photos) < 3 || count($photos) > 5) {
            $errors['project_photos'] = 'Envie de 3 a 5 fotos do ambiente.';
        }
        if ($errors) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_old'] = array_map(static fn ($v) => is_string($v) ? mb_substr($v, 0, 1000) : '', $_POST);
            Audit::log('registration.rejected', ['fields' => array_keys($errors)], 'visitor', null, 40);
            Response::redirect('/inscricoes');
        }

        $allowedImages = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $invoice = $this->normalizeFiles($_FILES['invoice'] ?? []);
        try {
            foreach ($photos as $photo) {
                $this->validateUpload($photo, $allowedImages, (int) Config::get('security.max_upload_bytes', 8388608));
            }
            if ($invoice) {
                $this->validateUpload($invoice[0], $allowedImages + ['application/pdf' => 'pdf'], 10485760);
            }
        } catch (RuntimeException $e) {
            $_SESSION['form_errors'] = ['_form' => $e->getMessage()];
            $_SESSION['form_old'] = array_map(static fn ($v) => is_string($v) ? mb_substr($v, 0, 1000) : '', $_POST);
            Audit::log('registration.rejected', ['reason' => 'upload_validation'], 'visitor', null, 45);
            Response::redirect('/inscricoes');
        }

        $reference = 'INS-' . substr(Security::randomCode(10), 0, 20);
        $folder = dirname(__DIR__, 2) . '/storage/uploads/' . strtolower($reference);
        if (!is_dir($folder) && !mkdir($folder, 0700, true) && !is_dir($folder)) {
            throw new RuntimeException('Não foi possível preparar o envio.');
        }

        $personal = [
            'full_name' => trim((string) $_POST['full_name']),
            'professional_registration' => trim((string) $_POST['professional_registration']),
            'instagram' => trim((string) $_POST['instagram']),
            'city' => trim((string) $_POST['city']),
            'state' => strtoupper(trim((string) $_POST['state'])),
            'email' => mb_strtolower(trim((string) $_POST['email'])),
            'phone' => trim((string) $_POST['phone']),
            'company' => trim((string) ($_POST['company'] ?? '')),
            'retailer_name' => trim((string) $_POST['retailer_name']),
            'retailer_city' => trim((string) ($_POST['retailer_city'] ?? '')),
            'seller_name' => trim((string) ($_POST['seller_name'] ?? '')),
            'environment_name' => trim((string) $_POST['environment_name']),
            'photographer' => trim((string) ($_POST['photographer'] ?? '')),
        ];

        $pdo = Database::connection();
        try {
            Database::transaction(function (PDO $pdo) use ($reference, $personal, $photos, $invoice, $folder, $allowedImages): void {
                $stmt = $pdo->prepare(
                    'INSERT INTO registrations (reference_code, email_hash, encrypted_payload, ip_hash, country_code, status, consented_at, created_at) '
                    . 'VALUES (?, ?, ?, ?, ?, "received", NOW(), NOW())'
                );
                $stmt->execute([$reference, Security::secretHash($personal['email']), Privacy::encrypt($personal), Security::ipHash(), Geo::countryCode()]);
                $registrationId = (int) $pdo->lastInsertId();
                $allFiles = [];
                foreach ($photos as $index => $photo) {
                    $allFiles[] = ['file' => $photo, 'kind' => 'project_photo', 'allowed' => $allowedImages, 'display' => 'foto-projeto-' . ($index + 1)];
                }
                if ($invoice) {
                    $allFiles[] = ['file' => $invoice[0], 'kind' => 'invoice', 'allowed' => $allowedImages + ['application/pdf' => 'pdf'], 'display' => 'nota-fiscal'];
                }
                foreach ($allFiles as $item) {
                    $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($item['file']['tmp_name']);
                    $extension = $item['allowed'][$mime];
                    $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
                    $target = $folder . '/' . $storedName;
                    if (!move_uploaded_file($item['file']['tmp_name'], $target)) {
                        throw new RuntimeException('Falha ao armazenar arquivo enviado.');
                    }
                    chmod($target, 0600);
                    $fileStmt = $pdo->prepare('INSERT INTO registration_files (registration_id, file_kind, original_name, stored_name, mime_type, size_bytes, sha256, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
                    $fileStmt->execute([$registrationId, $item['kind'], $item['display'] . '.' . $extension, $storedName, $mime, (int) $item['file']['size'], hash_file('sha256', $target)]);
                }
                Audit::log('registration.received', ['registration_id' => $registrationId, 'reference' => $reference, 'photo_count' => count($photos)], 'visitor', null, 0, $pdo);
            });
        } catch (Throwable $e) {
            error_log((string) $e);
            $_SESSION['form_errors'] = ['_form' => 'Não foi possível concluir a inscrição. Tente novamente.'];
            Response::redirect('/inscricoes');
        }
        $_SESSION['form_success'] = $reference;
        Response::redirect('/inscricoes');
    }

    public function regulations(string $type): void
    {
        $this->logPageView('regulations.' . $type);
        $template = $type === 'varejo' ? 'regulations-retail' : 'regulations-professionals';
        View::render($template, ['title' => $type === 'varejo' ? 'Regulamento para revendas e vendedores' : 'Regulamento para profissionais']);
    }

    public function privacy(): void
    {
        $this->logPageView('privacy');
        View::render('privacy', ['title' => 'Privacidade e proteção de dados']);
    }

    public function auditPage(): void
    {
        $this->logPageView('public_audit');
        $receipt = trim((string) ($_GET['codigo'] ?? ''));
        $result = null;
        if ($receipt !== '') {
            $stmt = Database::connection()->prepare(
                'SELECT v.receipt_code, v.status, v.confirmed_at, v.risk_score, f.participant_name, f.project_title, a.entry_hash '
                . 'FROM votes v JOIN finalists f ON f.id = v.finalist_id LEFT JOIN audit_events a ON a.id = v.audit_event_id WHERE v.receipt_code = ? LIMIT 1'
            );
            $stmt->execute([$receipt]);
            $result = $stmt->fetch() ?: false;
        }
        View::render('audit-public', ['title' => 'Auditoria da votação', 'receipt' => $receipt, 'result' => $result]);
    }

    private function ranking(): array
    {
        $rows = Database::connection()->query(
            'SELECT f.id, f.participant_name, f.project_title, COUNT(v.id) AS votes '
            . 'FROM finalists f LEFT JOIN votes v ON v.finalist_id = f.id AND v.status = "valid" '
            . 'WHERE f.active = 1 GROUP BY f.id ORDER BY votes DESC, f.id ASC'
        )->fetchAll();
        $total = array_sum(array_map(static fn (array $row): int => (int) $row['votes'], $rows));
        foreach ($rows as &$row) {
            $row['percentage'] = $total > 0 ? round(((int) $row['votes'] / $total) * 100, 1) : 0.0;
            unset($row['votes']);
        }
        return $rows;
    }

    private function logPageView(string $page): void
    {
        $risk = Security::isSuspiciousUserAgent() ? 35 : 0;
        $referrerHost = parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST) ?: null;
        Audit::log('page.view', [
            'page' => $page,
            'accept_language' => mb_substr((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 100),
            'referrer_host' => $referrerHost,
        ], 'visitor', null, $risk);
    }

    private function normalizeFiles(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            return ($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$files];
        }
        $normalized = [];
        foreach ($files['name'] as $index => $name) {
            if (($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $normalized[] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];
        }
        return $normalized;
    }

    private function validateUpload(array $file, array $allowedMimeTypes, int $maxBytes): void
    {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Um arquivo não pôde ser enviado.');
        }
        if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > $maxBytes) {
            throw new RuntimeException('Arquivo acima do limite permitido.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) {
            throw new RuntimeException('Origem de arquivo inválida.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!is_string($mime) || !isset($allowedMimeTypes[$mime])) {
            throw new RuntimeException('Formato de arquivo não permitido.');
        }
        if (str_starts_with($mime, 'image/') && @getimagesize($tmp) === false) {
            throw new RuntimeException('Imagem inválida.');
        }
    }
}
