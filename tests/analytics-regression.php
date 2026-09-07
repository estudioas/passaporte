<?php
// Standalone tests: PHP 8.1+ with PDO SQLite. Never loads production configuration.
declare(strict_types=1);
namespace App\Core {
    final class TestPDO extends \PDO {
        public function __construct() {
            parent::__construct('sqlite::memory:');
            $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            $this->sqliteCreateFunction('NOW', static fn () => date('Y-m-d H:i:s'));
            $this->sqliteCreateFunction('CONCAT', static fn (...$args) => implode('', $args));
            $this->sqliteCreateFunction('TIMESTAMPDIFF', static fn ($unit, $a, $b) => strtotime($b) - strtotime($a));
        }
        private function adapt(string $sql): string {
            $sql = str_replace('TIMESTAMPDIFF(SECOND,', "TIMESTAMPDIFF('SECOND',", $sql);
            $sql = str_replace('DATE_SUB(NOW(), INTERVAL 90 SECOND)', "datetime(NOW(), '-90 seconds')", $sql);
            $sql = str_replace('DATE_SUB(NOW(), INTERVAL 180 DAY)', "datetime(NOW(), '-180 days')", $sql);
            $sql = str_replace(' LIMIT 5000', '', $sql);
            $sql = str_replace('ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()', 'ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = NOW()', $sql);
            if (str_starts_with($sql, 'CREATE TABLE IF NOT EXISTS analytics_views')) {
                $sql = preg_replace('/,\s*INDEX .*$/s', ')', $sql);
                $sql = str_replace('BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
            }
            return $sql;
        }
        public function prepare(string $query, array $options = []): \PDOStatement|false { return parent::prepare($this->adapt($query), $options); }
        public function query(string $query, ?int $fetchMode = null, mixed ...$args): \PDOStatement|false { return parent::query($this->adapt($query)); }
        public function exec(string $statement): int|false { return parent::exec($this->adapt($statement)); }
    }
    final class Database {
        private static ?TestPDO $pdo = null;
        public static function connection(): TestPDO { return self::$pdo ??= new TestPDO(); }
    }
}
namespace {
    if (PHP_SAPI !== 'cli' && !defined('TEST_RUNTIME')) { http_response_code(404); exit; }
    spl_autoload_register(static function ($class) { $path = dirname(__DIR__) . '/' . str_replace('App/', 'app/', str_replace('\\', '/', $class)) . '.php'; if (is_file($path)) { require $path; } });
    use App\Core\{Analytics, Auth, Config, Database, Geo, Privacy, Security, Settings};
    $GLOBALS['app_config'] = ['app' => ['secret' => str_repeat('x', 64), 'base_url' => 'https://passaporte.enquetedigital.com'], 'security' => ['country_header' => 'GEOIP_COUNTRY_CODE']];
    date_default_timezone_set('America/Sao_Paulo');
    $count = 0;
    function check(bool $condition, string $label): void { global $count; if (!$condition) { throw new \RuntimeException('FAIL: ' . $label); } $count++; }
    $_SERVER = ['REMOTE_ADDR' => '8.8.8.8', 'HTTP_CF_CONNECTING_IP' => '1.2.3.4', 'HTTP_CF_IPCOUNTRY' => 'BR', 'HTTP_CF_IPCITY' => 'Spoofed'];
    check(Security::clientIp() === '8.8.8.8', 'Ignore spoofed forwarded IP');
    check(Geo::countryCode() === 'XX' && Geo::location()['city'] === '', 'Ignore spoofed geography');
    $_SERVER['REMOTE_ADDR'] = '104.16.0.1';
    check(Security::clientIp() === '1.2.3.4' && Geo::countryCode() === 'BR', 'Trust verified Cloudflare peer');
    $_SERVER['REMOTE_ADDR'] = '2606:4700::1'; check(Security::isCloudflareProxy(), 'IPv6 proxy');
    $_SERVER['REMOTE_ADDR'] = '2606:4800::1'; check(!Security::isCloudflareProxy(), 'IPv6 outside range');
    $_SERVER['GEOIP_COUNTRY_CODE'] = 'BR'; $_SERVER['GEOIP_CITY'] = 'Curitiba'; check(Geo::location()['city'] === 'Curitiba', 'Host geolocation');
    $_COOKIE = []; $_SESSION = [];
    check(Security::deviceHash() === Security::deviceHash(), 'Stable first-request visitor cookie');
    $secret = Privacy::encrypt(['ip' => '2001:db8::1234']);
    check(!str_contains($secret, '2001:db8') && Privacy::decrypt($secret)['ip'] === '2001:db8::1234', 'Encrypted full IPv6 roundtrip');
    $event = ['metadata_json' => json_encode(['network_encrypted' => $secret, 'geo_city' => 'Curitiba'])];
    check(Analytics::eventDetails($event)['ip_address'] === '2001:db8::1234', 'Authorized IP visible');
    check(Analytics::eventDetails($event, false)['ip_address'] === 'Acesso restrito', 'IP permission enforced');
    check(!str_contains(Analytics::eventDetails($event, false)['metadata_json'], $secret), 'Ciphertext not leaked through details');
    check(str_contains(Analytics::eventDetails([])['ip_address'], 'histórico'), 'Honest historical absence');
    $range = Analytics::range(['start' => '2020-01-01', 'end' => date('Y-m-d')]);
    check((strtotime($range['end']) - strtotime($range['start'])) / 86400 <= 365, 'Bounded period');
    check(Analytics::range(['start' => 'bad', 'end' => '2026-02-30'])['end'] === date('Y-m-d'), 'Invalid date handling');
    $origin = Analytics::origin(['utm_source' => 'instagram'], 'https://google.com/private?q=secret');
    check($origin['source'] === 'instagram' && $origin['referrer'] === 'google.com', 'UTM and referrer minimization');
    check(Analytics::agent('Mozilla iPhone Safari')['device'] === 'Celular', 'Mobile classification');
    $pdo = Database::connection();
    $pdo->exec('CREATE TABLE settings (setting_key TEXT PRIMARY KEY, setting_value TEXT, updated_at TEXT)');
    $pdo->exec('CREATE TABLE audit_events (event_type TEXT, created_at TEXT)');
    $auditor = ['role' => 'auditor']; $admin = ['role' => 'administrator'];
    check(Auth::can('analytics', $auditor) && !Auth::can('settings', $auditor), 'Default role permissions');
    Settings::set('role_permissions_auditor', '[]');
    check(!Auth::can('analytics', $auditor) && Auth::can('analytics', $admin), 'Revoke without blocking administrator');
    Settings::set('role_permissions_auditor', '["analytics", "users", "view_ips"]');
    check(Auth::can('view_ips', $auditor) && !Auth::can('users', $auditor), 'No administrative escalation');
    check(Analytics::ready(), 'Create analytics storage');
    $_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_USER_AGENT'] = 'Mozilla Chrome'; $_GET = [];
    Analytics::trackPage(); $first = $_SESSION['analytics_view'];
    Analytics::trackPage();
    check((int) $pdo->query('SELECT COUNT(DISTINCT device_hash) FROM analytics_views')->fetchColumn() === 1, 'Repeated pages one unique visitor');
    check(Analytics::online() === 0, 'No presence until browser signal');
    $pdo->exec('UPDATE analytics_views SET last_seen_at = NOW()');
    check(Analytics::online() === 1, 'Multiple tabs count once online');
    $report = Analytics::report(Analytics::range([]));
    check((int) $report['totals']['views'] === 2 && (int) $report['totals']['sessions'] === 1, 'Views and sessions totals');
    check((float) $report['sessions']['pages_per_session'] === 2.0, 'Pages per session');
    check($report['breakdowns']['cities'][0]['label'] === 'Curitiba · — · BR', 'Cities aggregation');
    $_SERVER['HTTP_USER_AGENT'] = 'Googlebot'; Analytics::trackPage();
    check(!isset($_SESSION['analytics_view']), 'Bots excluded from presence token');
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla Chrome'; $_SESSION['admin_id'] = 1; Analytics::trackPage();
    check((int) $pdo->query('SELECT COUNT(*) FROM analytics_views')->fetchColumn() === 2, 'Admin excluded');
    unset($_SESSION['admin_id']); $_SESSION['analytics_activity'] = time() - 1900; Analytics::trackPage();
    check((int) $pdo->query('SELECT COUNT(DISTINCT session_hash) FROM analytics_views')->fetchColumn() === 2, 'Session timeout');
    $method = new ReflectionMethod(\App\Controllers\AdminController::class, 'pagination');
    $_GET = ['events_page' => 9999]; $pager = $method->invoke(null, 101, 'events_page');
    check($pager['page'] === 3 && $pager['offset'] === 100, 'Pagination bounded');
    $_GET = ['events_page' => -10]; check($method->invoke(null, 0, 'events_page')['page'] === 1, 'Empty pagination');
    if (!defined('TEST_FIXTURE_ONLY')) { echo "$count regression checks passed\n"; }
}
