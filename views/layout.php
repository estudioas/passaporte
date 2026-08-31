<?php

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Security;

$h = [Security::class, 'h'];
$baseUrl = rtrim((string) Config::get('app.base_url', ''), '/');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Campanha Passaporte Ruffino Revestir 2027: inscreva seu projeto, conheça os finalistas e participe da votação auditável.">
    <meta name="theme-color" content="#8e281f">
    <meta name="csrf-token" content="<?= $h(Csrf::token()) ?>">
    <meta property="og:title" content="<?= $h($title ?? 'Passaporte Ruffino Revestir 2027') ?>">
    <meta property="og:description" content="Arquitetura brasileira em destaque. Inscrições, finalistas e votação auditável.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $h($baseUrl . ($_SERVER['REQUEST_URI'] ?? '/')) ?>">
    <meta property="og:image" content="<?= $h($baseUrl . '/assets/img/og-passaporte-ruffino.svg') ?>">
    <title><?= $h($title ?? 'Passaporte Ruffino Revestir 2027') ?> · Passaporte Ruffino</title>
    <link rel="icon" href="/assets/img/logo_pr_b.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/app.css?v=2.0.0">
</head>
<body class="campaign-v2">
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<header class="site-header">
    <a class="brand" href="/" aria-label="Passaporte Ruffino — início">
        <img src="/assets/img/logo_pr_w.svg" alt="Passaporte Ruffino Expo Revestir 2027">
    </a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-nav">Menu</button>
    <nav id="main-nav" class="main-nav" aria-label="Navegação principal">
        <a href="/#votacao">Votação</a>
        <a href="/#resultado">Resultado</a>
        <a href="/regulamento/profissionais">Regulamentos</a>
    </nav>
</header>
<main id="conteudo"><?= $content ?></main>
<footer class="site-footer">
    <div>
        <img src="/assets/img/logo-ruffino.svg" alt="Ruffino Acabamentos">
        <p>Passaporte Ruffino · Expo Revestir 2027</p>
    </div>
    <div class="footer-links">
        <a href="/privacidade">Privacidade e LGPD</a>
        <a href="/admin/login">Área administrativa</a>
    </div>
    <p class="footer-legal">Inscrições: 01/10 a 13/11/2026 · Votação: 25/11 a 11/12/2026 · Resultado até 14/12/2026.</p>
</footer>
<aside class="lgpd-notice">Ao continuar, você reconhece o tratamento de dados necessário à segurança da votação. <a href="/privacidade">Saiba mais</a>.</aside>
<script src="/assets/js/app.js?v=2.0.0" defer></script>
</body>
</html>
