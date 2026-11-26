<?php
$now = new DateTimeImmutable('now');
$votingStarted = $now >= new DateTimeImmutable((string) \App\Core\Config::get('campaign.voting_starts_at'));
$votingEnded = $now > new DateTimeImmutable((string) \App\Core\Config::get('campaign.voting_ends_at'));
$votingStatus = match (true) {
    $votingOpen => 'Em andamento',
    $votingEnded => 'Concluída',
    $votingStarted => 'Pausada',
    default => 'Próxima etapa',
};
?>
<section class="passport-hero campaign-home campaign-next-phase">
 <div class="passport-hero-copy">
  <span class="passport-eyebrow dark">#<span class="tag-keyword">Passaporte</span>Ruffino</span>
  <p class="campaign-phase-label"><?= $votingOpen ? 'Votação aberta' : ($votingEnded ? 'Votação encerrada' : 'Inscrições encerradas') ?></p>
  <h1>Os projetos estão prontos para ganhar <em>o seu voto.</em></h1>
  <p><?= $votingOpen ? 'A votação popular do Passaporte Ruffino já começou. Conheça os três projetos finalistas e escolha o seu preferido.' : 'Conheça os três projetos selecionados pela comissão técnica do Passaporte Ruffino.' ?></p>
  <?php if ($votingOpen): ?>
   <a class="passport-primary-cta" href="/votacao">Vote no seu projeto preferido <span aria-hidden="true">→</span></a>
  <?php elseif ($votingPublished): ?>
   <a class="passport-primary-cta" href="/votacao">Conheça os projetos finalistas <span aria-hidden="true">→</span></a>
  <?php else: ?>
   <span class="campaign-coming-soon">Votação a partir de 25/11/2026</span>
  <?php endif; ?>
  <p class="campaign-date">Votação de 25/11 a 11/12/2026, para participantes no Brasil.</p>
 </div>
 <figure class="passport-hero-photo home-photo"><img src="/assets/img/home-sala-2027-v2.jpg" alt="Sala com sofá vinho, painel ripado e piso amadeirado" width="1280" height="1600" fetchpriority="high"><figcaption>Imagem ilustrativa. Não representa projeto inscrito.</figcaption></figure>
</section>

<section id="proximos-passos" class="passport-vote-section campaign-next-steps">
 <div class="passport-section-heading"><span class="passport-eyebrow dark">Próximos <span class="tag-keyword">passos</span></span><h2><?= $votingOpen ? 'A votação popular está aberta.' : ($votingEnded ? 'A votação popular foi encerrada.' : 'A campanha entra na fase final.') ?></h2><p>A comissão técnica selecionou os três projetos finalistas. Na votação popular, escolha um projeto e confira sua seleção antes de confirmar.</p></div>
 <div class="campaign-steps campaign-phase-steps">
  <article class="is-complete"><span class="step-number">01</span><span class="phase-status">Concluída</span><h3>Inscrições</h3><p>Profissionais de arquitetura, design de interiores e decoração enviaram seus projetos dentro do período oficial.</p><small>01/10 a 13/11/2026</small></article>
  <article class="is-complete"><span class="step-number">02</span><span class="phase-status">Concluída</span><h3>Seleção dos finalistas</h3><p>A comissão técnica avaliou os projetos e selecionou os três finalistas da votação popular.</p><?php if (\App\Core\Settings::bool('page_jurors_enabled', false)): ?><a class="campaign-jurors-link" href="/jurados">Conheça os jurados <span aria-hidden="true">↗</span></a><?php endif; ?></article>
  <article<?= $votingOpen ? ' class="is-current" aria-current="step"' : ($votingEnded ? ' class="is-complete"' : '') ?>><span class="step-number">03</span><span class="phase-status"><?= $votingStatus ?></span><h3>Votação popular</h3><p>Conheça os três projetos, escolha apenas um e confirme o voto.</p><small>25/11 a 11/12/2026<br>Resultado até 14/12/2026</small><?php if ($votingOpen): ?><a class="campaign-jurors-link" href="/votacao">Escolha seu projeto favorito</a><?php endif; ?></article>
 </div>
</section>

<section class="campaign-eligibility campaign-how-to-vote">
 <span class="passport-eyebrow dark">Como <span class="tag-keyword">votar</span></span>
 <h2>Uma escolha simples e individual.</h2>
 <ol>
  <li><strong>Conheça os finalistas.</strong> A ordem dos três projetos muda a cada visita.</li>
  <li><strong>Escolha um projeto.</strong> Você pode trocar a seleção antes de confirmar.</li>
  <li><strong>Leia as condições.</strong> Aceite o regulamento e o aviso de privacidade antes de confirmar.</li>
  <li><strong>Confirme uma única vez.</strong> Depois da confirmação, o voto não poderá ser alterado.</li>
 </ol>
</section>

<section class="passport-results-section campaign-trust">
 <span class="passport-eyebrow">Participação <span class="tag-keyword tag-keyword-light">responsável</span></span>
 <div class="campaign-trust-copy"><h2>Votação protegida e auditável.</h2><p>A participação é permitida somente a pessoas no Brasil. Automação, compra de votos e outras formas de manipulação são proibidas e podem levar à invalidação após auditoria.</p></div>
 <div class="campaign-trust-links"><h3>Transparência e privacidade</h3><p>Registramos apenas os sinais necessários para validar a participação, investigar fraude e preservar a integridade da campanha, conforme o aviso de privacidade.</p><a href="/regulamento/profissionais">Regulamento da campanha</a><a href="/privacidade">Privacidade e tratamento de dados</a><a href="/auditoria">Como a votação é auditada</a></div>
</section>
