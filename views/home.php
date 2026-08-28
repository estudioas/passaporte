<?php

use App\Core\Security;

$h = [Security::class, 'h'];
?>
<section class="hero hero-vote">
    <div class="eyebrow">Concurso cultural · Revestir 2027</div>
    <h1>Seu voto abre portas para o melhor da arquitetura brasileira.</h1>
    <p>Conheça os três projetos finalistas. Você pode mudar sua escolha à vontade — o voto só se torna definitivo depois da confirmação.</p>
    <div class="hero-dates" aria-label="Datas da campanha">
        <span><strong>25 nov</strong> abre a votação</span>
        <span><strong>11 dez</strong> último dia</span>
        <span><strong>14 dez</strong> resultado</span>
    </div>
</section>

<section class="section finalists-section" aria-labelledby="finalistas-title">
    <div class="section-heading">
        <div>
            <span class="eyebrow dark">Finalistas</span>
            <h2 id="finalistas-title">Escolha um projeto</h2>
        </div>
        <p class="section-note">A ordem muda a cada visita para que todos tenham a mesma visibilidade.</p>
    </div>

    <?php if (count($finalists) !== 3): ?>
        <div class="notice warning">A curadoria dos três finalistas está sendo concluída. A votação ficará disponível assim que a seleção for publicada.</div>
    <?php endif; ?>

    <div class="finalist-grid" data-finalist-grid>
        <?php foreach ($finalists as $index => $finalist): ?>
            <article class="finalist-card" data-finalist-id="<?= (int) $finalist['id'] ?>">
                <div class="finalist-index" aria-hidden="true">0<?= $index + 1 ?></div>
                <div class="instagram-frame">
                    <?php if (preg_match('#instagram\.com/(p|reel)/#', (string) $finalist['instagram_url'])): ?>
                        <blockquote class="instagram-media" data-instgrm-permalink="<?= $h($finalist['instagram_url']) ?>" data-instgrm-version="14">
                            <a href="<?= $h($finalist['instagram_url']) ?>" target="_blank" rel="noopener">Ver publicação no Instagram</a>
                        </blockquote>
                    <?php elseif (!empty($finalist['fallback_image_url'])): ?>
                        <img src="<?= $h($finalist['fallback_image_url']) ?>" alt="Projeto <?= $h($finalist['project_title']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="project-placeholder" aria-label="Publicação aguardando configuração">
                            <span>Projeto finalista</span>
                            <strong><?= $h($finalist['project_title']) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="finalist-copy">
                    <span class="card-kicker">Projeto finalista</span>
                    <h3><?= $h($finalist['project_title']) ?></h3>
                    <p>por <?= $h($finalist['participant_name']) ?></p>
                    <a href="<?= $h($finalist['instagram_url']) ?>" target="_blank" rel="noopener">Ver publicação original ↗</a>
                </div>
                <button class="vote-choice" type="button" data-vote-choice="<?= (int) $finalist['id'] ?>" data-vote-label="<?= $h($finalist['project_title']) ?>" <?= $votingOpen ? '' : 'disabled' ?>>Escolher este projeto</button>
            </article>
        <?php endforeach; ?>
    </div>
    <p class="vote-period-status"><?= $votingOpen ? 'Votação aberta até 11/12/2026, às 23h59 (horário de Brasília).' : 'Votação disponível de 25/11/2026 a 11/12/2026.' ?></p>
</section>

<?php if ($rankingEnabled): ?>
<section class="section ranking-section" aria-labelledby="ranking-title">
    <div class="section-heading">
        <div>
            <span class="eyebrow dark">Prévia pública</span>
            <h2 id="ranking-title">Como está a disputa</h2>
        </div>
        <p class="section-note">Percentuais consideram apenas votos validados. Quantidades absolutas não são divulgadas.</p>
    </div>
    <div class="ranking-list">
        <?php foreach ($ranking as $position => $row): ?>
            <div class="ranking-row">
                <span class="rank-position"><?= $position + 1 ?>º</span>
                <div>
                    <strong><?= $h($row['project_title']) ?></strong>
                    <span><?= $h($row['participant_name']) ?></span>
                    <div class="rank-track"><i style="width: <?= (float) $row['percentage'] ?>%"></i></div>
                </div>
                <b><?= number_format((float) $row['percentage'], 1, ',', '.') ?>%</b>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="section trust-section">
    <div>
        <span class="eyebrow dark">Voto responsável</span>
        <h2>Uma escolha. Um comprovante. Uma trilha verificável.</h2>
    </div>
    <div class="trust-grid">
        <article><span>01</span><h3>Escolha livre</h3><p>Troque de projeto antes de confirmar. Nenhuma seleção temporária é contabilizada.</p></article>
        <article><span>02</span><h3>Confirmação protegida</h3><p>Um desafio simples e verificações de origem reduzem automações e votos fora do Brasil.</p></article>
        <article><span>03</span><h3>Recibo auditável</h3><p>Depois da confirmação, você recebe um código para consultar a existência e a integridade do voto.</p></article>
    </div>
</section>

<dialog class="vote-dialog" data-vote-dialog aria-labelledby="vote-dialog-title">
    <form method="dialog" class="dialog-close-row"><button value="cancel" aria-label="Fechar">×</button></form>
    <div class="dialog-step" data-vote-confirm-step>
        <span class="eyebrow dark">Confirme com atenção</span>
        <h2 id="vote-dialog-title">Seu voto vai para <span data-selected-project></span></h2>
        <p>Você ainda pode voltar e escolher outro projeto. Depois de confirmar, o voto não poderá ser alterado.</p>
        <div class="captcha-box">
            <label for="vote-captcha"><span data-captcha-question>Carregando verificação…</span></label>
            <input id="vote-captcha" type="number" inputmode="numeric" autocomplete="off" required>
        </div>
        <div class="honeypot" aria-hidden="true"><label>Website<input data-honeypot tabindex="-1" autocomplete="off"></label></div>
        <p class="form-feedback" role="status" data-vote-feedback></p>
        <div class="dialog-actions">
            <button type="button" class="button secondary" data-change-choice>Trocar escolha</button>
            <button type="button" class="button primary" data-confirm-vote>Confirmar voto</button>
        </div>
    </div>
    <div class="dialog-step" data-vote-success hidden>
        <span class="success-mark">✓</span>
        <h2>Voto recebido</h2>
        <p data-success-message></p>
        <div class="receipt"><span>Seu comprovante</span><strong data-receipt-code></strong></div>
        <a class="button primary" data-audit-link href="/auditoria">Consultar comprovante</a>
    </div>
</dialog>
<script async src="https://www.instagram.com/embed.js"></script>
