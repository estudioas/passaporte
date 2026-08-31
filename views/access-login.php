<?php

use App\Core\Csrf;
use App\Core\Security;

$h = [Security::class, 'h'];
?>
<main class="login-card access-login-card">
    <img src="/assets/img/logo_pr_b.svg" alt="Passaporte Ruffino">
    <span class="eyebrow dark">Acesso restrito</span>
    <h1>Bem-vindo ao Passaporte Ruffino.</h1>
    <p>Entre com seu login e senha para acessar a votação.</p>
    <?php if ($error): ?><div class="notice error"><?= $h($error) ?></div><?php endif; ?>
    <form action="/acesso" method="post">
        <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>">
        <label>Login<input type="email" name="email" required autocomplete="username" placeholder="seu@email.com"></label>
        <label>Senha<input type="password" name="password" required autocomplete="current-password" placeholder="••••••••"></label>
        <button class="button primary">Entrar</button>
    </form>
    <small>Área protegida para acompanhamento da campanha.</small>
</main>
