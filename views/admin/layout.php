<?php

use App\Core\Csrf;
use App\Core\Security;

$h = [Security::class, 'h'];
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="csrf-token" content="<?= $h(Csrf::token()) ?>"><title><?= $h($title) ?> · Painel Passaporte Ruffino</title><link rel="stylesheet" href="/assets/css/app.css?v=1.0.0"></head>
<body class="admin-body">
<aside class="admin-sidebar">
    <a href="/admin" class="admin-brand"><img src="/assets/img/logo_pr_w.svg" alt="Passaporte Ruffino"><span>Painel de controle</span></a>
    <nav><a href="/admin">Visão geral</a><?php if (($user['role'] ?? '') === 'administrator'): ?><a href="/admin/finalistas">Finalistas</a><?php endif; ?><a href="/admin/auditoria">Auditoria</a><?php if (($user['role'] ?? '') === 'administrator'): ?><a href="/admin/inscricoes">Inscrições</a><a href="/admin/configuracoes">Configurações</a><?php endif; ?><a href="/" target="_blank">Ver site ↗</a></nav>
    <form action="/admin/logout" method="post"><input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>"><button>Sair</button></form>
</aside>
<main class="admin-main"><header class="admin-top"><div><span>Passaporte Ruffino Revestir 2027</span><h1><?= $h($title) ?></h1></div><div class="admin-user"><strong><?= $h($user['name'] ?? '') ?></strong><span><?= $h($user['role'] ?? '') ?></span></div></header><?= $content ?></main>
<script src="/assets/js/app.js?v=1.0.0" defer></script>
</body></html>
