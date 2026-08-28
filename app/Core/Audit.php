<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Audit
{
    public static function log(
        string $eventType,
        array $metadata = [],
        string $actorType = 'visitor',
        ?int $actorId = null,
        int $riskScore = 0,
        ?PDO $connection = null
    ): int {
        $write = static function (PDO $pdo) use ($eventType, $metadata, $actorType, $actorId, $riskScore): int {
            $lock = $pdo->query('SELECT last_hash FROM audit_chain_state WHERE id = 1 FOR UPDATE');
            $previousHash = (string) ($lock->fetchColumn() ?: str_repeat('0', 64));
            ksort($metadata);
            $createdAt = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s.u');
            $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
            $canonical = implode('|', [$previousHash, $createdAt, $eventType, $actorType, (string) $actorId, (string) $riskScore, $metadataJson]);
            $entryHash = hash_hmac('sha256', $canonical, (string) Config::get('app.secret'));
            $stmt = $pdo->prepare(
                'INSERT INTO audit_events '
                . '(event_type, actor_type, actor_id, visitor_hash, device_hash, ip_hash, user_agent_hash, country_code, request_method, request_path, risk_score, metadata_json, previous_hash, entry_hash, created_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $eventType,
                $actorType,
                $actorId,
                Security::visitorHash(),
                Security::deviceHash(),
                Security::ipHash(),
                Security::userAgentHash(),
                Geo::countryCode(),
                mb_substr((string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI'), 0, 10),
                mb_substr((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? 'cli'), PHP_URL_PATH) ?: 'cli'), 0, 500),
                max(0, min(100, $riskScore)),
                $metadataJson,
                $previousHash,
                $entryHash,
                $createdAt,
            ]);
            $id = (int) $pdo->lastInsertId();
            $update = $pdo->prepare('UPDATE audit_chain_state SET last_event_id = ?, last_hash = ?, updated_at = NOW() WHERE id = 1');
            $update->execute([$id, $entryHash]);
            return $id;
        };

        if ($connection instanceof PDO) {
            return $write($connection);
        }

        return Database::transaction($write);
    }

    public static function verifyChain(): array
    {
        $rows = Database::connection()->query('SELECT * FROM audit_events ORDER BY id ASC')->fetchAll();
        $previous = str_repeat('0', 64);
        foreach ($rows as $row) {
            if (!hash_equals($previous, (string) $row['previous_hash'])) {
                return ['valid' => false, 'event_id' => (int) $row['id'], 'reason' => 'previous_hash'];
            }
            $canonical = implode('|', [
                $row['previous_hash'], $row['created_at'], $row['event_type'], $row['actor_type'],
                (string) $row['actor_id'], (string) $row['risk_score'], $row['metadata_json'],
            ]);
            $expected = hash_hmac('sha256', $canonical, (string) Config::get('app.secret'));
            if (!hash_equals($expected, (string) $row['entry_hash'])) {
                return ['valid' => false, 'event_id' => (int) $row['id'], 'reason' => 'entry_hash'];
            }
            $previous = (string) $row['entry_hash'];
        }
        return ['valid' => true, 'events' => count($rows), 'last_hash' => $previous];
    }
}
