<?php
$baseQuery = array_intersect_key($_GET, array_flip(['start', 'end', 'status', 'risk', 'event', 'ip', 'votes_page', 'events_page']));
$link = static fn (int $page): string => '/admin/auditoria?' . http_build_query(array_merge($baseQuery, [$pager['key'] => $page])) . '#' . $anchor;
?>
<nav class="log-pagination" aria-label="<?= $h($paginationLabel) ?>"><span><?= number_format($pager['total'], 0, ',', '.') ?> registros · Página <?= $pager['page'] ?> de <?= $pager['pages'] ?></span><div>
<?php if ($pager['page'] > 1): ?><a href="<?= $h($link(1)) ?>">Primeira</a><a href="<?= $h($link($pager['page'] - 1)) ?>">← Anterior</a><?php endif; ?>
<?php if ($pager['page'] < $pager['pages']): ?><a href="<?= $h($link($pager['page'] + 1)) ?>">Próxima →</a><a href="<?= $h($link($pager['pages'])) ?>">Última</a><?php endif; ?>
</div></nav>
