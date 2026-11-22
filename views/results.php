<?php use App\Core\Security; $h = [Security::class, 'h']; ?>
<?php if ($mode === 'awaiting'): ?>
<section class="results-waiting" data-results-mode="awaiting">
    <div>
        <span class="passport-eyebrow">Apuração final</span>
        <h1>A votação entrou em período de sigilo.</h1>
        <p>Os percentuais parciais foram retirados do ar para preservar os momentos finais da campanha.</p>
        <strong>O resultado final será divulgado ao meio-dia de 14/12/2026.</strong>
    </div>
</section>
<?php else: ?>
<section class="results-hero">
    <div class="results-hero-copy">
        <span class="passport-eyebrow"><?php if ($mode === 'final'): ?>Resultado final<?php else: ?>Resultado <span class="tag-keyword tag-keyword-light">parcial</span><?php endif; ?></span>
        <h1><?= $mode === 'final' ? 'O projeto escolhido pelo público.' : 'Como está a votação' ?></h1>
        <p><?php if ($mode === 'final'): ?>Confira o resultado final da votação popular, com os percentuais e os números absolutos de votos válidos.<?php else: ?>Acompanhe em tempo real os percentuais dos votos válidos. Esta página divulgará o resultado em número absoluto somente após o fim da votação.<?php endif; ?></p>
        <a class="button light" href="#apuracao-nacional">Acompanhe a apuração</a>
    </div>
    <figure class="results-hero-art">
        <img src="/assets/img/resultados-materiais-apuracao.jpg" width="1536" height="1024" alt="Composição conceitual com amostras de madeira e pedra, planta arquitetônica e elementos gráficos de votação e apuração">
    </figure>
</section>
<section class="public-results" id="apuracao-nacional" data-live-public-results data-results-mode="<?= $h($mode) ?>">
    <header class="public-results-heading"><div><span class="passport-eyebrow dark">Parcial <span class="tag-keyword">nacional</span></span><h2><?= $mode === 'final' ? 'Resultado final' : 'Percentual geral' ?></h2></div><small><?= $mode === 'final' ? 'Apuração encerrada · ' : 'Atualizado automaticamente · ' ?><span data-public-results-updated><?= $mode === 'final' ? '14/12/2026, às 12h' : 'agora' ?></span></small></header>
    <div class="public-ranking-list" data-public-ranking>
        <?php foreach ($ranking as $i => $row): ?><article class="public-ranking-row" data-ranking-id="<?= (int) $row['id'] ?>"><span><?= $i + 1 ?>º</span><div><strong><?= $h($row['project_title']) ?></strong><small><?= $h($row['participant_name']) ?></small><i><b style="width:<?= (float) $row['percentage'] ?>%"></b></i><?php if ($mode === 'final'): ?><small class="public-absolute" data-public-absolute><?= number_format((int) $row['votes'], 0, ',', '.') ?> votos válidos</small><?php endif; ?></div><em><?= number_format((float) $row['percentage'], 1, ',', '.') ?>%</em></article><?php endforeach; ?>
    </div>
    <section class="public-region-section" data-public-regional-map>
        <header><div><span class="passport-eyebrow">Brasil</span><h2>Votação por região</h2></div><p>Selecione uma região no mapa para comparar os percentuais dos projetos. O cálculo regional considera somente votos válidos com UF identificada.</p></header>
        <div class="public-region-layout">
            <div class="public-region-map"><div data-public-brazil-map><?php require dirname(__DIR__) . '/public/assets/img/brasil-estados.svg'; ?></div><p>As cores identificam as cinco regiões brasileiras; não representam volume de votos.</p></div>
            <aside class="public-region-detail" aria-live="polite"><span data-public-region-kicker>Percentuais regionais</span><h3 data-public-region-name>Brasil por região</h3><p data-public-region-summary>Selecione uma região no mapa.</p><div data-public-region-candidates></div></aside>
        </div>
        <div class="public-region-tabs" data-public-region-tabs><?php foreach ($regional['macroregions'] as $region): ?><button type="button" data-public-region="<?= $h($region['name']) ?>"><?= $h($region['name']) ?></button><?php endforeach; ?></div>
    </section>
    <script type="application/json" data-public-regional-data><?= json_encode($regional, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <?php if ($mode === 'live'): ?><section class="public-vote-cta"><span class="passport-eyebrow">Sua <span class="tag-keyword tag-keyword-light">escolha</span></span><h2>Qual projeto merece ir mais longe?</h2><p>Conheça os três finalistas e vote no seu favorito. O voto é individual e, depois de confirmado, não poderá ser alterado.</p><a class="button light" href="/votacao">Votar no meu favorito</a></section><?php endif; ?>
</section>
<?php endif; ?>
