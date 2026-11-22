<?php use App\Core\Security; $h = [Security::class, 'h']; $n = static fn ($v) => number_format((float) $v, 0, ',', '.'); ?>
<?php if (!$ready): ?><section class="admin-panel"><h2>Não foi possível iniciar o analytics</h2><p>O banco precisa permitir a criação da tabela de analytics. Os demais recursos continuam disponíveis.</p></section><?php else: ?>
<section class="admin-panel"><div class="panel-heading"><div><span>Audiência do site</span><h2>Visitas, conteúdo e origens</h2></div><?php if (\App\Core\Auth::can('audit', $user)): ?><a href="/admin/auditoria">Consultar logs e IPs →</a><?php endif; ?></div>
<form class="filter-bar" method="get"><label>De<input type="date" name="start" value="<?= $h($range['start']) ?>" required></label><label>Até<input type="date" name="end" value="<?= $h($range['end']) ?>" required></label><button class="button secondary small">Aplicar período</button><a href="/admin/analytics?start=<?= date('Y-m-d') ?>&amp;end=<?= date('Y-m-d') ?>">Hoje</a><a href="/admin/analytics?start=<?= date('Y-m-d', strtotime('-6 days')) ?>&amp;end=<?= date('Y-m-d') ?>">7 dias</a><a href="/admin/analytics">30 dias</a><a href="/admin/analytics/exportar?<?= $h(http_build_query(['start' => $range['start'], 'end' => $range['end']])) ?>">Exportar resumo CSV</a></form>
<p class="analytics-note">Coleta iniciada em <?= $h($report['started']) ?>. Horário de Brasília. Navegação administrativa e robôs identificados são excluídos. Visitantes são estimados por navegador; não representam pessoas identificadas.</p></section>
<section class="metric-grid">
<article><span>Pessoas online · estimativa</span><strong data-online-count><?= (int) $report['online'] ?></strong><small>navegadores ativos há até 90 segundos</small><small data-online-status>atualização a cada 30 segundos</small></article>
<article><span>Visitantes únicos</span><strong><?= $n($report['totals']['visitors']) ?></strong><small>navegadores distintos no período</small></article>
<article><span>Visualizações</span><strong><?= $n($report['totals']['views']) ?></strong><small>páginas públicas carregadas</small></article>
<article><span>Sessões</span><strong><?= $n($report['totals']['sessions']) ?></strong><small>nova sessão após 30 min sem atividade</small></article>
<article><span>Páginas por sessão</span><strong><?= number_format((float) $report['sessions']['pages_per_session'], 1, ',', '.') ?></strong></article>
<article><span>Duração observada média</span><strong><?= $n($report['sessions']['duration']) ?> s</strong><small>entre a primeira página e o último sinal</small></article>
<article><span>Sessões de uma página</span><strong><?= number_format((float) $report['sessions']['single_page'], 1, ',', '.') ?>%</strong><small>não equivale à taxa de rejeição</small></article>
<article><span>Cidade não informada</span><strong><?= $n($report['unknown_city']) ?></strong><small>visualizações sem localização municipal</small></article>
</section>
<?php $regional = $report['regional_votes']; $topState = $regional['top_state']; $topCity = $regional['top_city']; $evolution = $report['vote_evolution']; ?>
<section class="admin-panel vote-evolution-panel" data-vote-evolution data-evolution-url="/admin/analytics/evolucao-votos">
<div class="panel-heading"><div><span>Evolução da apuração</span><h2>Curva diária dos resultados</h2></div><small><?= date('d/m/Y', strtotime($evolution['starts_at'])) ?> a <?= date('d/m/Y', strtotime($evolution['ends_at'])) ?></small></div>
<p class="analytics-note">As linhas mostram o percentual acumulado de cada finalista entre os votos válidos confirmados até cada dia. Votos posteriormente invalidados deixam de compor toda a curva.</p>
<div class="vote-evolution-legend" data-vote-evolution-legend></div>
<div class="vote-evolution-chart" role="img" aria-label="Evolução diária dos percentuais acumulados por finalista. Os valores completos estão disponíveis na tabela abaixo.">
<svg viewBox="0 0 1000 380" preserveAspectRatio="xMidYMid meet" data-vote-evolution-svg aria-hidden="true"></svg>
<p class="vote-evolution-empty" data-vote-evolution-empty hidden>A curva aparecerá assim que os primeiros votos válidos forem confirmados.</p>
</div>
<p class="analytics-note vote-evolution-status" data-vote-evolution-status>Atualização automática a cada 30 segundos · <?= $h($evolution['updated_at']) ?></p>
<script type="application/json" data-vote-evolution-data><?= json_encode($evolution, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<details class="vote-evolution-table"><summary>Ver evolução diária em números</summary><div class="table-wrap"><table><thead><tr><th>Dia</th><th>Votos acumulados</th><?php foreach ($evolution['finalists'] as $finalist): ?><th><?= $h($finalist['project']) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($evolution['days'] as $point): ?><tr><td><?= $h($point['label']) ?><?= $point['future'] ? ' · previsto' : '' ?></td><td><?= $point['future'] ? '—' : $n($point['total']) ?></td><?php foreach ($evolution['finalists'] as $finalist): $candidate = $point['candidates'][(string) $finalist['id']] ?? ['votes' => 0, 'percentage' => 0]; ?><td><?= $point['future'] ? '—' : number_format((float) $candidate['percentage'], 1, ',', '.') . '% · ' . $n($candidate['votes']) . ' votos' ?></td><?php endforeach; ?></tr><?php endforeach; ?>
</tbody></table></div></details>
</section>
<section class="admin-panel regional-votes-panel" id="mapa-votos" data-regional-map>
<div class="panel-heading"><div><span>Auditoria geográfica dos votos</span><h2>Origem e liderança por região</h2></div><small>Somente votos válidos do Brasil · <?= $h($range['start']) ?> a <?= $h($range['end']) ?></small></div>
<p class="analytics-note">A localização corresponde à UF e à cidade informadas pela infraestrutura de rede no momento da confirmação. O mapa não expõe endereços IP e não altera a apuração.</p>
<div class="analytics-vote-overview">
<div class="panel-heading"><div><span>Apuração geral no período</span><h3><?= $n($regional['national']['total']) ?> votos válidos</h3></div><small>Valores absolutos e percentuais</small></div>
<div class="ranking-list compact"><?php foreach ($regional['national']['candidates'] as $i => $candidate): ?><div class="ranking-row"><span class="rank-position"><?= $i + 1 ?>º</span><div><strong><?= $h($candidate['project']) ?></strong><span><?= $h($candidate['participant']) ?></span><div class="rank-track"><i style="width:<?= (float) $candidate['percentage'] ?>%"></i></div></div><b><span><?= number_format((float) $candidate['percentage'], 1, ',', '.') ?>%</span><small><?= $n($candidate['votes']) ?> votos</small></b></div><?php endforeach; ?></div>
</div>
<div class="regional-summary">
<article><span>Votos válidos no período</span><strong><?= $n($regional['total']) ?></strong><small><?= $n($regional['located']) ?> com UF · <?= $n($regional['unlocated']) ?> sem UF</small></article>
<article><span>Estado com mais votos</span><strong><?= $topState ? $h($topState['name']) : '—' ?></strong><small><?= $topState ? $n($topState['total']) . ' votos válidos' : 'Ainda sem dados' ?></small></article>
<article><span>Cidade com mais votos</span><strong><?= $topCity ? $h($topCity['name']) : '—' ?></strong><small><?= $topCity ? $h($topCity['uf']) . ' · ' . $n($topCity['total']) . ' votos válidos' : 'Ainda sem dados' ?></small></article>
</div>
<div class="regional-map-layout">
<div class="regional-map-visual">
<div role="group" aria-label="Mapa interativo do Brasil dividido por estados" data-brazil-map-inline><?php require dirname(__DIR__, 2) . '/public/assets/img/brasil-estados.svg'; ?></div>
<p><span class="map-scale map-scale-0"></span>Sem votos <span class="map-scale map-scale-1"></span>Menor volume <span class="map-scale map-scale-4"></span>Maior volume</p>
</div>
<aside class="regional-map-detail" data-region-detail aria-live="polite">
<span data-region-kicker>Selecione um estado</span>
<h3 data-region-name>Mapa do Brasil</h3>
<p data-region-summary>Passe o cursor, use Tab ou clique em um estado para comparar os finalistas.</p>
<div data-region-candidates></div>
<div data-region-cities></div>
</aside>
</div>
<script type="application/json" data-regional-vote-data><?= json_encode($regional, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<details class="regional-cross-table"><summary>Cruzar por estado, cidade e projeto</summary>
<div class="table-wrap"><table><thead><tr><th>Estado</th><th>Região</th><th>Origem principal</th><th>Líder no estado</th><th>Percentuais dos projetos</th><th>Votos válidos</th></tr></thead><tbody>
<?php foreach ($regional['states'] as $state): ?>
<tr><td><button type="button" class="regional-table-select" data-select-uf="<?= $h($state['uf']) ?>"><?= $h($state['name']) ?> · <?= $h($state['uf']) ?></button></td><td><?= $h($state['macroregion']) ?></td><td><?= $state['cities'] ? $h($state['cities'][0]['name']) : 'Cidade não informada' ?></td><td><?= $state['tie'] ? 'Empate · ' . number_format((float) $state['leader']['percentage'], 1, ',', '.') . '%' : ($state['leader'] ? $h($state['leader']['project']) . ' · ' . number_format((float) $state['leader']['percentage'], 1, ',', '.') . '%' : 'Sem dados') ?></td><td><?php foreach ($state['candidates'] as $candidate): ?><span class="regional-table-percentage"><?= $h($candidate['project']) ?>: <?= number_format((float) $candidate['percentage'], 1, ',', '.') ?>%</span><?php endforeach; ?></td><td><?= $n($state['total']) ?></td></tr>
<?php endforeach; ?>
<?php if (!$regional['states']): ?><tr><td colspan="6">Ainda não há votos válidos com UF identificada neste período.</td></tr><?php endif; ?>
</tbody></table></div></details>
<details class="regional-cross-table"><summary>Comparar as cinco macrorregiões</summary>
<div class="table-wrap"><table><thead><tr><th>Região</th><th>Participação nacional</th><th>Líder regional</th><th>Percentuais dos projetos</th><th>Votos válidos</th></tr></thead><tbody>
<?php foreach ($regional['macroregions'] as $macro): $share = $regional['located'] ? $macro['total'] * 100 / $regional['located'] : 0; ?>
<tr><td><?= $h($macro['name']) ?></td><td><?= number_format($share, 1, ',', '.') ?>%</td><td><?= $macro['tie'] ? 'Empate · ' . number_format((float) $macro['leader']['percentage'], 1, ',', '.') . '%' : ($macro['leader'] ? $h($macro['leader']['project']) . ' · ' . number_format((float) $macro['leader']['percentage'], 1, ',', '.') . '%' : 'Sem dados') ?></td><td><?php foreach ($macro['candidates'] as $candidate): ?><span class="regional-table-percentage"><?= $h($candidate['project']) ?>: <?= number_format((float) $candidate['percentage'], 1, ',', '.') ?>%</span><?php endforeach; ?></td><td><?= $n($macro['total']) ?></td></tr>
<?php endforeach; ?>
<?php if (!$regional['macroregions']): ?><tr><td colspan="5">Ainda não há dados regionais neste período.</td></tr><?php endif; ?>
</tbody></table></div></details>
</section>
<section class="admin-panel"><div class="panel-heading"><h2>Evolução diária</h2><small>Visualizações e visitantes únicos</small></div>
<?php $max = max(1, ...array_column($report['daily'], 'views')); $days = array_column($report['daily'], null, 'day'); ?>
<div class="analytics-chart" role="img" aria-label="Visualizações diárias. Valores disponíveis na tabela abaixo.">
<?php for ($day = new DateTimeImmutable($range['start']); $day->format('Y-m-d') <= $range['end']; $day = $day->modify('+1 day')): $key = $day->format('Y-m-d'); $views = (int) ($days[$key]['views'] ?? 0); ?>
<div class="analytics-column" title="<?= $h($day->format('d/m') . ': ' . $views . ' visualizações') ?>"><i style="height:<?= $views ? max(2, $views / $max * 100) : 0 ?>%"></i><small><?= $day->format('d/m') ?></small></div>
<?php endfor; ?></div>
<details><summary>Ver valores por dia</summary><div class="table-wrap"><table><thead><tr><th>Dia</th><th>Visualizações</th><th>Visitantes</th></tr></thead><tbody><?php foreach ($report['daily'] as $row): ?><tr><td><?= $h($row['day']) ?></td><td><?= $n($row['views']) ?></td><td><?= $n($row['visitors']) ?></td></tr><?php endforeach; ?></tbody></table></div></details></section>
<p class="analytics-note">Cidades e países são aproximações por IP, conforme dados fornecidos pela hospedagem ou Cloudflare. “XX” significa país não identificado. A ausência de cidade não indica ausência de visitantes.</p>
<section class="admin-grid two">
<?php foreach (['pages' => 'Páginas mais acessadas', 'sources' => 'Origem do tráfego', 'countries' => 'Países', 'cities' => 'Cidades e regiões', 'devices' => 'Dispositivos', 'browsers' => 'Navegadores', 'campaigns' => 'Campanhas UTM · origem · mídia'] as $key => $label): ?>
<article class="admin-panel"><div class="panel-heading"><h2><?= $h($label) ?></h2><small>Até 20 principais</small></div><div class="table-wrap"><table><thead><tr><th><?= $key === 'countries' ? 'País (ISO)' : 'Origem / conteúdo' ?></th><th>Visualizações</th><th>Visitantes</th></tr></thead><tbody>
<?php foreach ($report['breakdowns'][$key] as $row): ?><tr><td><?= $h($row['label']) ?></td><td><?= $n($row['views']) ?></td><td><?= $n($row['visitors']) ?></td></tr><?php endforeach; ?>
<?php if (!$report['breakdowns'][$key]): ?><tr><td colspan="3">Sem dados neste período.</td></tr><?php endif; ?>
</tbody></table></div></article><?php endforeach; ?>
<article class="admin-panel"><h2>Ações registradas</h2><p>Totais de ações no período, sem atribuição à origem do tráfego.</p><ul><?php foreach ($report['conversions'] as $row): ?><li>Votos confirmados (todos os status): <strong><?= $n($row['total']) ?></strong></li><?php endforeach; ?></ul><p>O cadastro externo no site da Ruffino não está incluído nestas métricas.</p></article>
</section>
<?php endif; ?>
