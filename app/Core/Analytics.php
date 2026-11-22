<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use Throwable;

final class Analytics
{
    public static function ready(): bool
    {
        try {
            if (Settings::get('analytics_schema_v1') !== '1') {
                Database::connection()->exec('CREATE TABLE IF NOT EXISTS analytics_views (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    session_hash CHAR(64) NOT NULL, device_hash CHAR(64) NOT NULL,
                    path VARCHAR(500) NOT NULL, referrer_host VARCHAR(190) NOT NULL,
                    source VARCHAR(190) NOT NULL, medium VARCHAR(100) NOT NULL, campaign VARCHAR(190) NOT NULL,
                    country_code CHAR(2) NOT NULL, city VARCHAR(150) NOT NULL, region VARCHAR(150) NOT NULL,
                    device_type VARCHAR(20) NOT NULL, browser VARCHAR(30) NOT NULL,
                    created_at DATETIME NOT NULL, last_seen_at DATETIME NULL,
                    INDEX idx_av_created (created_at), INDEX idx_av_seen (last_seen_at),
                    INDEX idx_av_session (session_hash, created_at), INDEX idx_av_device (device_hash, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
                Settings::set('analytics_schema_v1', '1');
                Settings::set('analytics_started_at', date('Y-m-d H:i:s'));
            }
            if (Settings::get('analytics_purged_on') !== date('Y-m-d')) {
                Database::connection()->exec('DELETE FROM analytics_views WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)');
                Settings::set('analytics_purged_on', date('Y-m-d'));
            }
            return true;
        } catch (Throwable $e) {
            error_log('Analytics initialization failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function trackPage(): void
    {
        unset($_SESSION['analytics_view']);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || !empty($_SESSION['admin_id']) || Security::isSuspiciousUserAgent() || http_response_code() >= 400) {
            return;
        }
        if (!self::ready()) { return; }
        try {
            $now = time();
            if (empty($_SESSION['analytics_session']) || $now - (int) ($_SESSION['analytics_activity'] ?? 0) > 1800) {
                $_SESSION['analytics_session'] = bin2hex(random_bytes(32));
                $_SESSION['analytics_origin'] = self::origin($_GET, (string) ($_SERVER['HTTP_REFERER'] ?? ''));
            }
            $_SESSION['analytics_activity'] = $now;
            $origin = $_SESSION['analytics_origin'];
            $geo = Geo::location();
            $agent = self::agent(Security::userAgent());
            $path = mb_substr((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'), 0, 500);
            $stmt = Database::connection()->prepare('INSERT INTO analytics_views (session_hash, device_hash, path, referrer_host, source, medium, campaign, country_code, city, region, device_type, browser, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([Security::secretHash($_SESSION['analytics_session']), Security::deviceHash(), $path, $origin['referrer'], $origin['source'], $origin['medium'], $origin['campaign'], $geo['country'], $geo['city'], $geo['region'], $agent['device'], $agent['browser']]);
            $_SESSION['analytics_view'] = (int) Database::connection()->lastInsertId();
            // At most 20 concurrent tabs may send presence updates in this session.
            $_SESSION['analytics_views'][$_SESSION['analytics_view']] = true;
            $_SESSION['analytics_views'] = array_slice($_SESSION['analytics_views'], -20, null, true);
        } catch (Throwable $e) {
            unset($_SESSION['analytics_view']);
            error_log('Analytics page failed: ' . $e->getMessage());
        }
    }

    public static function heartbeat(): never
    {
        header('Cache-Control: no-store');
        if (!Csrf::verify(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null)) {
            Response::json(['ok' => false], 403);
        }
        $id = (int) ($_POST['view'] ?? 0);
        if (!empty($_SESSION['admin_id']) || Security::isSuspiciousUserAgent() || !isset($_SESSION['analytics_views'][$id])) {
            Response::json(['ok' => false], 403);
        }
        if (time() - (int) ($_SESSION['analytics_ping'] ?? 0) >= 20) {
            try {
                Database::connection()->prepare('UPDATE analytics_views SET last_seen_at = NOW() WHERE id = ? AND session_hash = ?')->execute([$id, Security::secretHash((string) $_SESSION['analytics_session'])]);
                $_SESSION['analytics_ping'] = time();
                $_SESSION['analytics_activity'] = time();
            } catch (Throwable $e) {
                error_log('Analytics presence failed: ' . $e->getMessage());
                Response::json(['ok' => false], 503);
            }
        }
        Response::json(['ok' => true]);
    }

    public static function origin(array $query, string $referrer): array
    {
        $text = static fn (mixed $value, int $length): string => is_string($value) ? mb_substr(trim($value), 0, $length) : '';
        $host = strtolower((string) (parse_url($referrer, PHP_URL_HOST) ?: ''));
        $own = strtolower((string) parse_url((string) Config::get('app.base_url'), PHP_URL_HOST));
        $host = $host === $own ? '' : mb_substr($host, 0, 190);
        return ['referrer' => $host, 'source' => $text($query['utm_source'] ?? '', 190) ?: ($host ?: 'Direto'), 'medium' => $text($query['utm_medium'] ?? '', 100), 'campaign' => $text($query['utm_campaign'] ?? '', 190)];
    }

    public static function agent(string $ua): array
    {
        $device = preg_match('/ipad|tablet/i', $ua) ? 'Tablet' : (preg_match('/mobile|iphone|android/i', $ua) ? 'Celular' : 'Computador');
        $browser = 'Outro';
        foreach (['/edg/i' => 'Edge', '/opr|opera/i' => 'Opera', '/firefox|fxios/i' => 'Firefox', '/chrome|crios/i' => 'Chrome', '/safari/i' => 'Safari'] as $pattern => $name) {
            if (preg_match($pattern, $ua)) { $browser = $name; break; }
        }
        return compact('device', 'browser');
    }

    public static function range(array $query): array
    {
        $today = new DateTimeImmutable('today');
        $parse = static function (mixed $value, DateTimeImmutable $fallback): DateTimeImmutable {
            if (!is_string($value)) { return $fallback; }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            return $date && $date->format('Y-m-d') === $value ? $date : $fallback;
        };
        $end = min($today, $parse($query['end'] ?? null, $today));
        $start = $parse($query['start'] ?? null, $end->modify('-29 days'));
        $start = max($end->modify('-365 days'), min($start, $end));
        return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d'), 'until' => $end->modify('+1 day')->format('Y-m-d')];
    }

    public static function online(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(DISTINCT device_hash) FROM analytics_views WHERE last_seen_at >= DATE_SUB(NOW(), INTERVAL 90 SECOND)')->fetchColumn();
    }

    public static function report(array $range): array
    {
        $pdo = Database::connection();
        $args = [$range['start'], $range['until']];
        $query = static function (string $sql) use ($pdo, $args): array {
            $stmt = $pdo->prepare($sql); $stmt->execute($args); return $stmt->fetchAll();
        };
        $where = ' FROM analytics_views WHERE created_at >= ? AND created_at < ? ';
        $totals = $query('SELECT COUNT(*) AS views, COUNT(DISTINCT device_hash) AS visitors, COUNT(DISTINCT session_hash) AS sessions' . $where)[0];
        $sessions = $query('SELECT AVG(pages) AS pages_per_session, AVG(duration) AS duration, AVG(pages = 1) * 100 AS single_page FROM (SELECT COUNT(*) AS pages, TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(COALESCE(last_seen_at, created_at))) AS duration' . $where . 'GROUP BY session_hash) s')[0];
        $daily = $query('SELECT DATE(created_at) AS day, COUNT(*) AS views, COUNT(DISTINCT device_hash) AS visitors' . $where . 'GROUP BY DATE(created_at) ORDER BY day');
        $breakdowns = [];
        foreach (['pages' => 'path', 'countries' => 'country_code', 'cities' => "CONCAT(COALESCE(NULLIF(city, ''), 'Não informada'), ' · ', COALESCE(NULLIF(region, ''), '—'), ' · ', country_code)", 'sources' => 'source', 'campaigns' => "CONCAT(COALESCE(NULLIF(campaign, ''), 'Sem campanha UTM'), ' · ', source, ' · ', COALESCE(NULLIF(medium, ''), '—'))", 'devices' => 'device_type', 'browsers' => 'browser'] as $key => $column) {
            $breakdowns[$key] = $query('SELECT ' . $column . ' AS label, COUNT(*) AS views, COUNT(DISTINCT device_hash) AS visitors' . $where . 'GROUP BY label ORDER BY views DESC, label ASC LIMIT 20');
        }
        $conversions = $query('SELECT event_type AS label, COUNT(*) AS total FROM audit_events WHERE created_at >= ? AND created_at < ? AND event_type = "vote.confirmed" GROUP BY event_type');
        return compact('totals', 'sessions', 'daily', 'breakdowns', 'conversions') + [
            'online' => self::online(),
            'started' => Settings::get('analytics_started_at', ''),
            'unknown_city' => (int) $query('SELECT COUNT(*) AS n' . $where . "AND city = ''")[0]['n'],
            'regional_votes' => self::regionalVotes($range),
        ];
    }

    /**
     * Regional audit summary built only from valid, Brazilian votes.
     * IP addresses and hashes never leave the audit tables through this report.
     */
    public static function regionalVotes(array $range): array
    {
        $pdo = Database::connection();
        $finalists = $pdo->query('SELECT id, participant_name, project_title FROM finalists WHERE active = 1 ORDER BY sort_order, id')->fetchAll();
        $stmt = $pdo->prepare('SELECT v.finalist_id, f.participant_name, f.project_title, a.metadata_json
            FROM votes v
            JOIN finalists f ON f.id = v.finalist_id
            LEFT JOIN audit_events a ON a.id = v.audit_event_id
            WHERE v.status = "valid" AND v.country_code = "BR" AND f.active = 1 AND v.confirmed_at >= ? AND v.confirmed_at < ?');
        $stmt->execute([$range['start'], $range['until']]);

        $states = [];
        $macroregions = [];
        $unlocated = 0;
        $total = 0;
        foreach ($stmt->fetchAll() as $row) {
            $metadata = json_decode((string) ($row['metadata_json'] ?? '{}'), true) ?: [];
            $uf = self::normalizeState((string) ($metadata['geo_region'] ?? ''));
            $city = self::normalizeCity((string) ($metadata['geo_city'] ?? ''));
            $finalistId = (int) $row['finalist_id'];
            $total++;
            if ($uf === '') {
                $unlocated++;
                continue;
            }
            $stateInfo = self::stateInfo()[$uf];
            if (!isset($states[$uf])) {
                $states[$uf] = ['uf' => $uf, 'name' => $stateInfo['name'], 'macroregion' => $stateInfo['region'], 'total' => 0, 'votes' => [], 'cities' => []];
            }
            $states[$uf]['total']++;
            $states[$uf]['votes'][$finalistId] = ($states[$uf]['votes'][$finalistId] ?? 0) + 1;
            if ($city !== '') {
                if (!isset($states[$uf]['cities'][$city])) {
                    $states[$uf]['cities'][$city] = ['name' => $city, 'total' => 0, 'votes' => []];
                }
                $states[$uf]['cities'][$city]['total']++;
                $states[$uf]['cities'][$city]['votes'][$finalistId] = ($states[$uf]['cities'][$city]['votes'][$finalistId] ?? 0) + 1;
            }
            $macro = $stateInfo['region'];
            if (!isset($macroregions[$macro])) {
                $macroregions[$macro] = ['name' => $macro, 'total' => 0, 'votes' => []];
            }
            $macroregions[$macro]['total']++;
            $macroregions[$macro]['votes'][$finalistId] = ($macroregions[$macro]['votes'][$finalistId] ?? 0) + 1;
        }

        $catalog = [];
        foreach ($finalists as $finalist) {
            $catalog[(int) $finalist['id']] = [
                'id' => (int) $finalist['id'],
                'project' => (string) $finalist['project_title'],
                'participant' => (string) $finalist['participant_name'],
            ];
        }
        foreach (self::stateInfo() as $uf => $info) {
            $states[$uf] ??= ['uf' => $uf, 'name' => $info['name'], 'macroregion' => $info['region'], 'total' => 0, 'votes' => [], 'cities' => []];
            $macroregions[$info['region']] ??= ['name' => $info['region'], 'total' => 0, 'votes' => []];
        }
        foreach ($states as &$state) {
            $state = self::finishRegion($state, $catalog);
            $cities = array_values($state['cities']);
            foreach ($cities as &$city) { $city = self::finishRegion($city, $catalog); }
            unset($city);
            usort($cities, static fn (array $a, array $b): int => $b['total'] <=> $a['total'] ?: strcmp($a['name'], $b['name']));
            $state['cities'] = $cities;
        }
        unset($state);
        foreach ($macroregions as &$macro) { $macro = self::finishRegion($macro, $catalog); }
        unset($macro);
        uasort($states, static fn (array $a, array $b): int => $b['total'] <=> $a['total'] ?: strcmp($a['name'], $b['name']));
        uasort($macroregions, static fn (array $a, array $b): int => $b['total'] <=> $a['total'] ?: strcmp($a['name'], $b['name']));

        $topState = ($total - $unlocated) > 0 && $states ? reset($states) : null;
        $topCity = null;
        foreach ($states as $state) {
            foreach ($state['cities'] as $city) {
                if ($topCity === null || $city['total'] > $topCity['total']) {
                    $topCity = $city + ['uf' => $state['uf'], 'state' => $state['name']];
                }
            }
        }
        return [
            'total' => $total,
            'located' => $total - $unlocated,
            'unlocated' => $unlocated,
            'states' => $states,
            'macroregions' => $macroregions,
            'top_state' => $topState,
            'top_city' => $topCity,
            'finalists' => array_values($catalog),
        ];
    }

    private static function finishRegion(array $region, array $catalog): array
    {
        $candidates = [];
        foreach ($catalog as $id => $finalist) {
            $votes = (int) ($region['votes'][$id] ?? 0);
            $candidates[] = $finalist + [
                'votes' => $votes,
                'percentage' => $region['total'] > 0 ? round($votes * 100 / $region['total'], 1) : 0.0,
            ];
        }
        usort($candidates, static fn (array $a, array $b): int => $b['votes'] <=> $a['votes'] ?: strcmp($a['project'], $b['project']));
        $region['candidates'] = $candidates;
        $region['leader'] = $candidates && $candidates[0]['votes'] > 0 ? $candidates[0] : null;
        $region['tie'] = count($candidates) > 1 && $candidates[0]['votes'] > 0 && $candidates[0]['votes'] === $candidates[1]['votes'];
        unset($region['votes']);
        return $region;
    }

    private static function normalizeCity(string $city): string
    {
        $city = preg_replace('/\s+/u', ' ', trim($city)) ?? '';
        return mb_substr($city, 0, 150);
    }

    private static function normalizeState(string $region): string
    {
        $region = trim($region);
        $upper = strtoupper($region);
        if (isset(self::stateInfo()[$upper])) { return $upper; }
        $plain = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($region, 'UTF-8'));
        $plain = preg_replace('/[^a-z]+/', ' ', is_string($plain) ? $plain : '') ?? '';
        $plain = trim($plain);
        foreach (self::stateInfo() as $uf => $info) {
            $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($info['name'], 'UTF-8'));
            if ($plain === strtolower((string) $name)) { return $uf; }
        }
        return ['federal district' => 'DF', 'brasilia' => 'DF'][$plain] ?? '';
    }

    private static function stateInfo(): array
    {
        return [
            'AC' => ['name' => 'Acre', 'region' => 'Norte'], 'AL' => ['name' => 'Alagoas', 'region' => 'Nordeste'],
            'AP' => ['name' => 'Amapá', 'region' => 'Norte'], 'AM' => ['name' => 'Amazonas', 'region' => 'Norte'],
            'BA' => ['name' => 'Bahia', 'region' => 'Nordeste'], 'CE' => ['name' => 'Ceará', 'region' => 'Nordeste'],
            'DF' => ['name' => 'Distrito Federal', 'region' => 'Centro-Oeste'], 'ES' => ['name' => 'Espírito Santo', 'region' => 'Sudeste'],
            'GO' => ['name' => 'Goiás', 'region' => 'Centro-Oeste'], 'MA' => ['name' => 'Maranhão', 'region' => 'Nordeste'],
            'MT' => ['name' => 'Mato Grosso', 'region' => 'Centro-Oeste'], 'MS' => ['name' => 'Mato Grosso do Sul', 'region' => 'Centro-Oeste'],
            'MG' => ['name' => 'Minas Gerais', 'region' => 'Sudeste'], 'PA' => ['name' => 'Pará', 'region' => 'Norte'],
            'PB' => ['name' => 'Paraíba', 'region' => 'Nordeste'], 'PR' => ['name' => 'Paraná', 'region' => 'Sul'],
            'PE' => ['name' => 'Pernambuco', 'region' => 'Nordeste'], 'PI' => ['name' => 'Piauí', 'region' => 'Nordeste'],
            'RJ' => ['name' => 'Rio de Janeiro', 'region' => 'Sudeste'], 'RN' => ['name' => 'Rio Grande do Norte', 'region' => 'Nordeste'],
            'RS' => ['name' => 'Rio Grande do Sul', 'region' => 'Sul'], 'RO' => ['name' => 'Rondônia', 'region' => 'Norte'],
            'RR' => ['name' => 'Roraima', 'region' => 'Norte'], 'SC' => ['name' => 'Santa Catarina', 'region' => 'Sul'],
            'SP' => ['name' => 'São Paulo', 'region' => 'Sudeste'], 'SE' => ['name' => 'Sergipe', 'region' => 'Nordeste'],
            'TO' => ['name' => 'Tocantins', 'region' => 'Norte'],
        ];
    }

    public static function eventDetails(array $row, bool $showIp = true): array
    {
        $metadata = json_decode((string) ($row['metadata_json'] ?? '{}'), true) ?: [];
        $private = $showIp ? Privacy::decrypt((string) ($metadata['network_encrypted'] ?? '')) : [];
        $row['ip_address'] = $showIp ? ($private['ip'] ?? 'Não registrado (histórico)') : 'Acesso restrito';
        $row['city'] = $metadata['geo_city'] ?? '';
        $row['region'] = $metadata['geo_region'] ?? '';
        $row['terms_accepted'] = ($metadata['terms_accepted'] ?? false) === true;
        $row['terms_version'] = (string) ($metadata['terms_version'] ?? '');
        unset($metadata['network_encrypted']);
        $row['metadata_json'] = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        return $row;
    }
}
