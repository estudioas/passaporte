<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Passaporte Ruffino Revestir 2027',
        'env' => 'production',
        'debug' => false,
        'base_url' => 'https://passaporte.enquetedigital.com',
        'timezone' => 'America/Sao_Paulo',
        'secret' => 'TROQUE-POR-UMA-CHAVE-ALEATORIA-COM-64-CARACTERES',
        'session_name' => 'pr_session',
    ],
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'SEU_BANCO',
        'user' => 'SEU_USUARIO',
        'password' => 'SUA_SENHA',
        'charset' => 'utf8mb4',
    ],
    'campaign' => [
        'registration_starts_at' => '2026-10-01 00:00:00',
        'registration_ends_at' => '2026-11-13 23:59:59',
        'voting_starts_at' => '2026-11-25 00:00:00',
        'voting_ends_at' => '2026-12-11 23:59:59',
        'result_deadline' => '2026-12-14',
    ],
    'security' => [
        // Ative somente quando o DNS estiver em proxy Cloudflare e o acesso direto à origem estiver bloqueado.
        'trust_proxy_headers' => true,
        'country_header' => 'HTTP_CF_IPCOUNTRY',
        'allow_unknown_country' => false,
        'strict_ip_vote_limit' => false,
        'ip_vote_limit' => 3,
        'retention_access_days' => 180,
        'retention_vote_days' => 730,
        'max_upload_bytes' => 8388608,
    ],
];
