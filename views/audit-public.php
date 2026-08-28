<?php use App\Core\Security; $h = [Security::class, 'h']; ?>
<section class="hero hero-compact">
    <span class="eyebrow">Transparência</span>
    <h1>Cada voto confirmado deixa uma marca verificável.</h1>
    <p>Consulte seu comprovante sem expor dados pessoais ou o volume total da votação.</p>
</section>
<section class="section audit-explainer">
    <form class="receipt-search" method="get" action="/auditoria">
        <label for="codigo">Código do comprovante</label>
        <div><input id="codigo" name="codigo" value="<?= $h($receipt) ?>" placeholder="PR-…" required><button class="button primary">Verificar</button></div>
    </form>
    <?php if ($result === false): ?>
        <div class="notice error">Comprovante não encontrado. Confira o código exatamente como foi exibido.</div>
    <?php elseif (is_array($result)): ?>
        <div class="receipt-result">
            <span class="success-mark">✓</span>
            <div><span>Comprovante válido</span><strong><?= $h($result['receipt_code']) ?></strong></div>
            <dl>
                <div><dt>Projeto escolhido</dt><dd><?= $h($result['project_title']) ?> — <?= $h($result['participant_name']) ?></dd></div>
                <div><dt>Confirmado em</dt><dd><?= $h(date('d/m/Y H:i', strtotime((string) $result['confirmed_at']))) ?></dd></div>
                <div><dt>Situação</dt><dd><?= $result['status'] === 'valid' ? 'Validado' : ($result['status'] === 'review' ? 'Em revisão de integridade' : 'Invalidado após auditoria') ?></dd></div>
                <div><dt>Prova de integridade</dt><dd><code><?= $h($result['entry_hash']) ?></code></dd></div>
            </dl>
        </div>
    <?php endif; ?>
    <div class="trust-grid audit-grid">
        <article><span>01</span><h2>Identidade pseudônima</h2><p>Endereço de rede e dispositivo são transformados em hashes irreversíveis; o painel não precisa guardar IP bruto.</p></article>
        <article><span>02</span><h2>Cadeia de eventos</h2><p>Cada registro inclui o hash do evento anterior. Alterações retroativas quebram a verificação de integridade.</p></article>
        <article><span>03</span><h2>Revisão humana</h2><p>Sinais de automação, velocidade anormal e repetição são separados para análise antes de compor o ranking.</p></article>
    </div>
</section>
