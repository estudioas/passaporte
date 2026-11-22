<?php use App\Core\Security; $h = [Security::class, 'h']; ?>
<section class="results-hero">
    <div class="results-hero-copy">
        <span class="passport-eyebrow">Resultado parcial</span>
        <h1>Como está a votação.</h1>
        <p>Acompanhe os percentuais dos votos válidos. Esta página não divulga quantidades absolutas e é atualizada automaticamente.</p>
    </div>
    <figure class="results-hero-art">
        <img src="/assets/img/resultados-materiais-apuracao.jpg" width="1536" height="1024" alt="Composição conceitual com amostras de madeira e pedra, planta arquitetônica e elementos gráficos de votação e apuração">
        <figcaption>Imagem conceitual criada com texturas de materiais Ruffino.</figcaption>
    </figure>
</section>
<section class="public-results" data-live-public-results>
    <header class="public-results-heading"><div><span class="passport-eyebrow">Visão nacional</span><h2>Percentual geral</h2></div><small>Atualizado automaticamente · <span data-public-results-updated>agora</span></small></header>
    <div class="public-ranking-list" data-public-ranking>
        <?php foreach ($ranking as $i => $row): ?><article class="public-ranking-row" data-ranking-id="<?= (int) $row['id'] ?>"><span><?= $i + 1 ?>º</span><div><strong><?= $h($row['project_title']) ?></strong><small><?= $h($row['participant_name']) ?></small><i><b style="width:<?= (float) $row['percentage'] ?>%"></b></i></div><em><?= number_format((float) $row['percentage'], 1, ',', '.') ?>%</em></article><?php endforeach; ?>
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
</section>
