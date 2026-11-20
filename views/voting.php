<?php

use App\Core\Security;

$h = [Security::class, 'h'];
$defaultImages = [
    '/assets/img/finalista-ambiente-01-v2.jpg',
    '/assets/img/finalista-ambiente-02-v2.jpg',
    '/assets/img/finalista-ambiente-03-v2.jpg',
];
$identityColors = ['#8e281f', '#315f5a', '#66507a'];
?>
<section id="top" class="passport-hero">
    <div class="blueprint" aria-hidden="true"></div>
    <div class="passport-hero-copy">
        <span class="passport-eyebrow">#<span class="tag-keyword">Passaporte</span>Ruffino</span>
        <h1>Seu voto pode levar um projeto <em>mais longe.</em></h1>
        <p>Conheça os projetos finalistas e escolha aquele que merece embarcar para a Expo Revestir 2027.</p>
        <a class="passport-primary-cta" href="#votacao">Conheça os finalistas <span>↓</span></a>
    </div>
    <div class="passport-hero-photo">
        <img src="/assets/img/hero-viagem-v2.jpg" alt="Mala de viagem com materiais de arquitetura e amostras de acabamento">
        <div class="passport-stamp">VOTAÇÃO<br><strong>POPULAR</strong><small>2027</small></div>
    </div>
</section>

<section id="votacao" class="passport-vote-section" aria-labelledby="finalistas-title">
    <div class="passport-section-heading">
        <div><span class="passport-eyebrow dark"><span class="tag-keyword">Votação</span> Popular</span><h2 id="finalistas-title">Qual projeto merece o seu voto?</h2></div>
        <p>A ordem dos finalistas muda a cada visita à página para garantir uma escolha justa. Analise os projetos e vote no seu preferido.</p>
    </div>

    <?php if (count($finalists) < 3): ?>
        <div class="notice warning">A curadoria dos finalistas está sendo concluída. A votação ficará disponível assim que a seleção for publicada.</div>
    <?php endif; ?>

    <div class="candidate-grid" data-finalist-grid>
        <?php foreach ($finalists as $index => $finalist): ?>
            <?php
                $identityNumber = (int) sprintf('%u', crc32((string) $finalist['slug']));
                $accent = $identityColors[$identityNumber % count($identityColors)];
                $fallback = !empty($finalist['fallback_image_url'])
                    ? (string) $finalist['fallback_image_url']
                    : $defaultImages[$identityNumber % count($defaultImages)];
                $projectImages = $finalist['images'] ?? [];
            ?>
            <article class="candidate-card" data-finalist-id="<?= (int) $finalist['id'] ?>" data-finalist-key="<?= $h($finalist['slug']) ?>" style="--candidate-accent:<?= $h($accent) ?>">
                <div class="candidate-carousel" data-carousel aria-label="Fotos do projeto <?= $h($finalist['project_title']) ?>">
                    <div class="candidate-slides">
                    <?php if ($projectImages): ?>
                        <?php foreach ($projectImages as $imageIndex => $image): ?>
                            <figure class="candidate-image" data-carousel-slide <?= $imageIndex === 0 ? '' : 'hidden' ?>><img src="/media/finalistas/<?= (int) $image['id'] ?>" alt="<?= $h($image['alt_text']) ?>" width="<?= (int) $image['width_px'] ?>" height="<?= (int) $image['height_px'] ?>" <?= $imageIndex === 0 ? '' : 'loading="lazy"' ?>><button class="candidate-zoom" type="button" data-image-zoom aria-label="Ampliar esta foto" title="Ver foto em detalhes"><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"></circle><path d="m15.5 15.5 5 5M10.5 7.5v6M7.5 10.5h6"></path></svg></button></figure>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <figure class="candidate-image" data-carousel-slide><img src="<?= $h($fallback) ?>" alt="Projeto <?= $h($finalist['project_title']) ?>" loading="lazy"><button class="candidate-zoom" type="button" data-image-zoom aria-label="Ampliar esta foto" title="Ver foto em detalhes"><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"></circle><path d="m15.5 15.5 5 5M10.5 7.5v6M7.5 10.5h6"></path></svg></button></figure>
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
    <p class="vote-period-status"><?= $votingOpen ? 'Votação aberta até 11/12/2026, às 23h59 (horário de Brasília).' : 'Votação disponível de 25/11/2026 a 11/12/2026.' ?></p>
</section>

<dialog class="image-lightbox" data-image-lightbox aria-label="Visualização ampliada da foto">
    <form method="dialog" class="image-lightbox-close"><button value="cancel" aria-label="Fechar imagem ampliada">×</button></form>
    <figure><img data-lightbox-image src="" alt=""><figcaption data-lightbox-caption></figcaption></figure>
</dialog>

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
        <label class="vote-terms"><input type="checkbox" data-vote-terms required><span>Li e aceito o <a href="/regulamento/profissionais" target="_blank" rel="noopener">regulamento</a> e o <a href="/privacidade" target="_blank" rel="noopener">aviso de privacidade</a>. Confirmo que este voto é individual e não utiliza automação.</span></label>
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
