<?php

use App\Core\Security;

$h = [Security::class, 'h'];
$defaultImages = [
    '/assets/img/finalista-ambiente-01-v2.jpg',
    '/assets/img/finalista-ambiente-02-v2.jpg',
    '/assets/img/finalista-ambiente-03-v2.jpg',
];
?>
<section id="top" class="passport-hero">
    <div class="blueprint" aria-hidden="true"></div>
    <div class="passport-hero-copy">
        <span class="passport-eyebrow">#PassaporteRuffino</span>
        <h1>Seu voto pode levar um projeto <em>mais longe.</em></h1>
        <p>Conheça os três projetos finalistas e escolha aquele que merece embarcar para a Expo Revestir 2027.</p>
        <a class="passport-primary-cta" href="#votacao">Conheça os finalistas <span>↓</span></a>
    </div>
    <div class="passport-hero-photo">
        <img src="/assets/img/hero-viagem-v2.jpg" alt="Mala de viagem com materiais de arquitetura e amostras de acabamento">
        <div class="passport-stamp">VOTAÇÃO<br><strong>POPULAR</strong><small>2027</small></div>
    </div>
</section>

<section id="votacao" class="passport-vote-section" aria-labelledby="finalistas-title">
    <div class="passport-section-heading">
        <div><span class="passport-eyebrow dark">Votação popular</span><h2 id="finalistas-title">Qual projeto merece o seu voto?</h2></div>
        <p>A ordem dos finalistas muda a cada visita à página para garantir uma escolha justa. Analise os projetos e vote no seu preferido.</p>
    </div>

    <?php if (count($finalists) !== 3): ?>
        <div class="notice warning">A curadoria dos três finalistas está sendo concluída. A votação ficará disponível assim que a seleção for publicada.</div>
    <?php endif; ?>

    <div class="candidate-grid" data-finalist-grid>
        <?php foreach ($finalists as $index => $finalist): ?>
            <?php $image = !empty($finalist['fallback_image_url']) ? (string) $finalist['fallback_image_url'] : $defaultImages[$index % 3]; ?>
            <article class="candidate-card" data-finalist-id="<?= (int) $finalist['id'] ?>">
                <button class="candidate-select" type="button" data-vote-choice="<?= (int) $finalist['id'] ?>" data-vote-label="<?= $h($finalist['project_title']) ?>" data-vote-social="<?= $h($finalist['instagram_url']) ?>" aria-pressed="false" <?= $votingOpen ? '' : 'disabled' ?>>
                    <div class="candidate-image"><img src="<?= $h($image) ?>" alt="Projeto <?= $h($finalist['project_title']) ?>" loading="lazy"><span>0<?= $index + 1 ?></span></div>
                    <div class="candidate-meta"><div><small>PROJETO</small><h3><?= $h($finalist['project_title']) ?></h3><p><?= $h($finalist['participant_name']) ?></p></div><span class="candidate-radio" aria-hidden="true">✓</span></div>
                </button>
            </article>
        <?php endforeach; ?>
    </div>
    <div class="passport-vote-action">
        <p><span aria-hidden="true">◇</span> Um voto por pessoa. Seus dados são usados apenas para validar a participação.</p>
        <button class="passport-vote-button" type="button" data-submit-vote disabled>Confirmar meu voto</button>
    </div>
    <p class="vote-period-status"><?= $votingOpen ? 'Votação aberta até 11/12/2026, às 23h59 (horário de Brasília).' : 'Votação disponível de 25/11/2026 a 11/12/2026.' ?></p>
</section>

<?php if ($rankingEnabled): ?>
<section id="resultado" class="passport-results-section" aria-labelledby="ranking-title">
    <div><span class="passport-eyebrow">Acompanhe</span><h2 id="ranking-title">Como está a votação</h2><p>O resultado parcial é atualizado em tempo real. Para preservar a disputa, divulgamos somente os percentuais.</p></div>
    <div class="passport-result-list">
        <?php foreach ($ranking as $row): ?>
            <div class="passport-result-row"><div><strong>Projeto <?= $h($row['project_title']) ?></strong><span><?= number_format((float) $row['percentage'], 1, ',', '.') ?>%</span></div><div class="passport-track"><i style="width:<?= (float) $row['percentage'] ?>%"></i></div></div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<dialog class="vote-dialog passport-vote-dialog" data-vote-dialog aria-labelledby="vote-dialog-title">
    <form method="dialog" class="dialog-close-row"><button value="cancel" aria-label="Fechar">×</button></form>
    <div class="dialog-step" data-vote-confirm-step>
        <span class="passport-eyebrow dark">Confirme com atenção</span>
        <h2 id="vote-dialog-title">Seu voto vai para <span data-selected-project></span></h2>
        <p>Depois da confirmação, o voto não poderá ser alterado.</p>
        <div class="captcha-box"><label for="vote-captcha"><span data-captcha-question>Carregando verificação…</span></label><input id="vote-captcha" type="number" inputmode="numeric" autocomplete="off" required></div>
        <div class="honeypot" aria-hidden="true"><label>Website<input data-honeypot tabindex="-1" autocomplete="off"></label></div>
        <p class="form-feedback" role="status" data-vote-feedback></p>
        <div class="dialog-actions"><button type="button" class="button secondary" data-change-choice>Trocar escolha</button><button type="button" class="button primary" data-confirm-vote>Confirmar voto</button></div>
    </div>
    <div class="dialog-step passport-thanks" data-vote-success hidden>
        <img src="/assets/img/logo-ruffino.svg" alt="">
        <span class="passport-eyebrow dark">Voto confirmado</span>
        <h2>Obrigado por fazer parte desta viagem.</h2>
        <p data-success-message></p>
        <div class="receipt"><span>Seu comprovante</span><strong data-receipt-code></strong></div>
        <a class="passport-primary-cta" data-social-link href="#" target="_blank" rel="noopener">Curtir a publicação ↗</a>
    </div>
</dialog>
