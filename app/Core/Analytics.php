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
        return compact('totals', 'sessions', 'daily', 'breakdowns', 'conversions') + ['online' => self::online(), 'started' => Settings::get('analytics_started_at', ''), 'unknown_city' => (int) $query('SELECT COUNT(*) AS n' . $where . "AND city = ''")[0]['n']];
    }

    public static function eventDetails(array $row, bool $showIp = true): array
    {
        $metadata = json_decode((string) ($row['metadata_json'] ?? '{}'), true) ?: [];
        $private = $showIp ? Privacy::decrypt((string) ($metadata['network_encrypted'] ?? '')) : [];
        $row['ip_address'] = $showIp ? ($private['ip'] ?? 'Não registrado (histórico)') : 'Acesso restrito';
        $row['city'] = $metadata['geo_city'] ?? '';
        $row['region'] = $metadata['geo_region'] ?? '';
        unset($metadata['network_encrypted']);
        $row['metadata_json'] = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        return $row;
    }
}
