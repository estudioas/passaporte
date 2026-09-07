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
