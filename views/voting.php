<?php

use App\Core\Security;

$h = [Security::class, 'h'];
$defaultImages = [
    '/assets/img/finalista-ambiente-01-v2.jpg',
    '/assets/img/finalista-ambiente-02-v2.jpg',
    '/assets/img/finalista-ambiente-03-v2.jpg',
];
?>
<?php if (!empty($turnstileSiteKey)): ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script><?php endif; ?>
<section id="top" class="passport-hero voting-hero">
    <div class="blueprint" aria-hidden="true"></div>
    <div class="passport-hero-copy">
        <span class="passport-eyebrow">#<span class="tag-keyword">Passaporte</span>Ruffino</span>
        <h1>Seu voto pode levar um projeto <em>mais longe.</em></h1>
        <p>Conheça os projetos finalistas e escolha aquele que merece embarcar para a Expo Revestir 2027.</p>
        <a class="passport-primary-cta" href="#votacao">Conheça os finalistas <span>↓</span></a>
    </div>
    <div class="passport-hero-photo">
        <img src="/assets/img/hero-passaporte-oficial-2027.jpg" alt="Mala de viagem com materiais de arquitetura, amostras de acabamento e passaporte da campanha">
        <div class="passport-stamp">VOTAÇÃO<br><strong>POPULAR</strong><small>2027</small></div>
    </div>
</section>

<section id="votacao" class="passport-vote-section" aria-labelledby="finalistas-title">
    <div class="passport-section-heading">
        <span class="passport-eyebrow dark"><span class="tag-keyword">Votação</span> Popular</span>
        <h2 id="finalistas-title">Qual projeto merece o seu voto?</h2>
        <p>A ordem de exibição dos finalistas muda a cada visita à página para garantir uma escolha justa. Analise os projetos e vote no seu preferido.</p>
    </div>

    <?php if (count($finalists) < 3): ?>
        <div class="notice warning">A curadoria dos finalistas está sendo concluída. A votação ficará disponível assim que a seleção for publicada.</div>
    <?php endif; ?>

    <div class="candidate-grid" data-finalist-grid>
        <?php foreach ($finalists as $index => $finalist): ?>
            <?php
                $identityNumber = (int) sprintf('%u', crc32((string) $finalist['slug']));
                $fallback = !empty($finalist['fallback_image_url'])
                    ? (string) $finalist['fallback_image_url']
                    : $defaultImages[$identityNumber % count($defaultImages)];
                $projectImages = $finalist['images'] ?? [];
            ?>
            <article class="candidate-card" data-finalist-id="<?= (int) $finalist['id'] ?>" data-finalist-key="<?= $h($finalist['slug']) ?>">
                <div class="candidate-carousel" data-carousel aria-label="Fotos do projeto <?= $h($finalist['project_title']) ?>">
                    <div class="candidate-slides">
                    <?php if ($projectImages): ?>
                        <?php foreach ($projectImages as $imageIndex => $image): ?>
                            <figure class="candidate-image" data-carousel-slide data-image-zoom data-zoom-label="Ver foto em detalhes" tabindex="0" role="button" aria-label="Ampliar esta foto" <?= $imageIndex === 0 ? '' : 'hidden' ?>><img src="/media/finalistas/<?= (int) $image['id'] ?>" alt="<?= $h($image['alt_text']) ?>" width="<?= (int) $image['width_px'] ?>" height="<?= (int) $image['height_px'] ?>" <?= $imageIndex === 0 ? '' : 'loading="lazy"' ?>></figure>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <figure class="candidate-image" data-carousel-slide data-image-zoom data-zoom-label="Ver foto em detalhes" tabindex="0" role="button" aria-label="Ampliar esta foto"><img src="<?= $h($fallback) ?>" alt="Projeto <?= $h($finalist['project_title']) ?>" loading="lazy"></figure>
                    <?php endif; ?>
                    </div>
                    <span class="candidate-identity">Projeto finalista</span>
                    <?php if (count($projectImages) > 1): ?>
                    <div class="candidate-carousel-controls"><button type="button" data-carousel-prev aria-label="Foto anterior">‹</button><span><b data-carousel-current>1</b>/<?= count($projectImages) ?></span><button type="button" data-carousel-next aria-label="Próxima foto">›</button></div>
                    <?php endif; ?>
                </div>
                <button class="candidate-select" type="button" data-vote-choice="<?= (int) $finalist['id'] ?>" data-vote-label="<?= $h($finalist['project_title']) ?>" data-vote-social="<?= $h($finalist['instagram_url']) ?>" aria-pressed="false" <?= $votingOpen ? '' : 'disabled' ?>>
                    <div class="candidate-meta"><div><small>PROJETO</small><h3><?= $h($finalist['project_title']) ?></h3><p><?= $h($finalist['participant_name']) ?></p><strong class="candidate-select-label">Selecionar este projeto</strong></div><span class="candidate-radio" aria-hidden="true">✓</span></div>
                </button>
            </article>
        <?php endforeach; ?>
    </div>
    <div class="passport-vote-action">
        <p><span aria-hidden="true">◇</span> Voto individual e único. Sinais técnicos são usados somente para validar a participação e prevenir fraude.</p>
        <button class="passport-vote-button" type="button" data-submit-vote disabled>Confirmar meu voto</button>
    </div>
    <p class="vote-integrity-note">É permitido um único voto por dispositivo. O limite é aplicado por meio de um identificador pseudônimo armazenado no navegador. Sinais de repetição também são registrados para auditoria; o compartilhamento de uma mesma rede, isoladamente, não caracteriza irregularidade.</p>
    <p class="vote-period-status">
        <?= $votingOpen ? 'Votação aberta até 11/12/2026, às 23h59 (horário de Brasília).' : 'Votação disponível de 25/11/2026 a 11/12/2026.' ?><br>
        <a href="/auditoria?consulta=meu#consulta-voto">Consultar meu voto</a>
    </p>
</section>

<dialog class="image-lightbox" data-image-lightbox aria-label="Visualização ampliada da foto">
    <form method="dialog" class="image-lightbox-close"><button value="cancel" aria-label="Fechar imagem ampliada">×</button></form>
    <button class="image-lightbox-nav image-lightbox-prev" type="button" data-lightbox-prev aria-label="Foto anterior">‹</button>
    <button class="image-lightbox-nav image-lightbox-next" type="button" data-lightbox-next aria-label="Próxima foto">›</button>
    <figure><img data-lightbox-image src="" alt=""><figcaption data-lightbox-caption></figcaption></figure>
</dialog>

<?php if ($rankingEnabled): ?>
<section id="resultado" class="passport-results-section" aria-labelledby="ranking-title">
    <div><span class="passport-eyebrow">Acompanhe</span><h2 id="ranking-title">Como está a votação</h2><p>O resultado parcial é atualizado em tempo real. Para preservar a disputa, divulgamos somente os percentuais.</p></div>
    <div class="passport-result-list">
        <?php foreach ($ranking as $row): ?>
            <div class="passport-result-row"><div><strong>Projeto <?= $h($row['project_title']) ?></strong><span><?= number_format((float) $row['percentage'], 1, ',', '.') ?>%</span></div><div class="passport-track"><i style="width:<?= (float) $row['percentage'] ?>%"></i></div></div>
        <?php endforeach; ?>
        <a class="button light" href="/resultados">Ver percentuais por região</a>
    </div>
</section>
<?php endif; ?>

<dialog class="vote-dialog passport-vote-dialog" data-vote-dialog aria-labelledby="vote-dialog-title">
    <form method="dialog" class="dialog-close-row"><button value="cancel" aria-label="Fechar">×</button></form>
    <div class="dialog-step" data-vote-confirm-step>
        <span class="passport-eyebrow dark">Confirme com atenção</span>
        <h2 id="vote-dialog-title">Seu voto vai para <span data-selected-project></span></h2>
        <p>Depois da confirmação, o voto não poderá ser alterado.</p>
        <?php if (!empty($turnstileSiteKey)): ?>
        <div class="turnstile-verification"><div data-turnstile data-sitekey="<?= $h($turnstileSiteKey) ?>"></div><span>Verificação automática de segurança</span></div>
        <?php else: ?>
        <div class="captcha-box" data-math-captcha><label for="vote-captcha"><span data-captcha-question>Carregando verificação…</span></label><input id="vote-captcha" type="number" inputmode="numeric" autocomplete="off" required></div>
        <?php endif; ?>
        <label class="vote-terms"><input type="checkbox" data-vote-terms required><span>Li e aceito o <a href="/regulamento/profissionais" target="_blank" rel="noopener">regulamento</a> e o <a href="/privacidade" target="_blank" rel="noopener">aviso de privacidade</a>. Confirmo que este dispositivo será usado para um único voto e que não utilizo automação.</span></label>
        <div class="honeypot" aria-hidden="true"><label>Website<input data-honeypot tabindex="-1" autocomplete="off"></label></div>
        <p class="form-feedback" role="status" data-vote-feedback></p>
        <div class="dialog-actions"><button type="button" class="button secondary" data-change-choice>Trocar escolha</button><button type="button" class="button primary" data-confirm-vote>Confirmar voto</button></div>
    </div>
    <div class="dialog-step passport-thanks" data-vote-success hidden>
        <img src="/assets/img/logo-ruffino.svg" alt="">
        <span class="passport-eyebrow dark" data-vote-outcome>Voto confirmado</span>
        <h2 data-vote-outcome-title>Obrigado por fazer parte desta viagem.</h2>
        <p data-success-message></p>
        <div class="receipt vote-receipt">
            <div><span>Seu comprovante</span> <strong data-receipt-code></strong></div>
            <div data-vote-receipt-action hidden><a class="button primary" data-audit-link href="/auditoria">Consultar meu voto</a></div>
        </div>
        <a class="passport-primary-cta" data-social-link href="#" target="_blank" rel="noopener">Curtir a publicação ↗</a>
    </div>
</dialog>
