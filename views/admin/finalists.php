<?php use App\Core\Csrf; use App\Core\Security; $h=[Security::class,'h']; ?>
<?php if($message): ?><div class="notice success"><?= $h($message) ?></div><?php endif; ?>
<?php if($error): ?><div class="notice error"><?= $h($error) ?></div><?php endif; ?>

<section class="admin-panel">
    <div class="panel-heading"><div><span>Curadoria</span><h2>Projetos finalistas</h2></div><small>Mantenha exatamente 3 ativos</small></div>
    <p class="analytics-note">Cada projeto possui uma identidade interna permanente. A ordem pública pode mudar a cada visita, mas fotos, seleção e votos continuam vinculados ao finalista correto.</p>
    <div class="finalist-admin-list">
    <?php foreach($finalists as $row): ?>
        <details class="finalist-admin-item" id="finalista-<?= (int)$row['id'] ?>" <?= (int)$row['active']===1?'open':'' ?>>
            <summary><div><span class="status-dot <?= (int)$row['active']===1?'active':'' ?>"></span><strong><?= $h($row['project_title']) ?></strong><small><?= $h($row['participant_name']) ?> · identidade #<?= (int)$row['id'] ?></small></div><span><?= (int)$row['active']===1?'Ativo':'Arquivado' ?></span></summary>
            <div class="finalist-editor-grid">
                <div>
                    <form action="/admin/finalistas/salvar" method="post" class="admin-form">
                        <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <div class="field-grid two">
                            <label>Profissional<input name="participant_name" value="<?= $h($row['participant_name']) ?>" required></label>
                            <label>Título do projeto<input name="project_title" value="<?= $h($row['project_title']) ?>" required></label>
                            <label>URL da publicação no Instagram<input type="url" name="instagram_url" value="<?= $h($row['instagram_url']) ?>" required></label>
                            <label>URL de embed <span>opcional</span><input type="url" name="instagram_embed_url" value="<?= $h($row['instagram_embed_url']) ?>"></label>
                            <label>Imagem externa de reserva <span>opcional</span><input type="url" name="fallback_image_url" value="<?= $h($row['fallback_image_url']) ?>"></label>
                            <label>Ordem administrativa<input type="number" name="sort_order" value="<?= (int)$row['sort_order'] ?>"></label>
                        </div>
                        <label class="check-row"><input type="checkbox" name="active" value="1" <?= (int)$row['active']===1?'checked':'' ?>><span>Exibir este projeto na página de votação</span></label>
                        <div class="admin-actions"><button class="button primary">Salvar informações</button></div>
                    </form>
                    <?php if((int)$row['active']===1): ?>
                    <form action="/admin/finalistas/desativar" method="post" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <button class="text-button danger">Arquivar sem apagar votos ou fotos</button>
                    </form>
                    <?php endif; ?>
                </div>

                <section class="finalist-gallery-admin" aria-labelledby="galeria-<?= (int)$row['id'] ?>">
                    <div class="gallery-admin-heading"><div><span>Carrossel</span><h3 id="galeria-<?= (int)$row['id'] ?>">Fotos do projeto</h3></div><small><?= count($row['images']) ?>/<?= (int)$maxImages ?> imagens</small></div>
                    <form action="/admin/finalistas/imagens/enviar" method="post" enctype="multipart/form-data" class="gallery-upload-form">
                        <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>">
                        <input type="hidden" name="finalist_id" value="<?= (int)$row['id'] ?>">
                        <label>Adicionar fotos JPG, PNG ou WebP
                            <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required>
                            <span>Até <?= (int)$maxImages ?> fotos por projeto, 8 MB por arquivo e mínimo de 600 × 600 px. Para arquivos grandes, envie em lotes menores.</span>
                        </label>
                        <button class="button secondary small" <?= count($row['images']) >= (int)$maxImages ? 'disabled' : '' ?>>Enviar para o carrossel</button>
                    </form>

                    <?php if(!$row['images']): ?>
                        <div class="gallery-empty">Nenhuma foto enviada. Enquanto isso, a imagem externa de reserva será usada.</div>
                    <?php else: ?>
                        <p class="gallery-sort-help">Arraste as fotos para mudar a ordem do carrossel. A capa permanece fixa na primeira posição.</p>
                        <div class="gallery-admin-grid" data-gallery-sortable data-reorder-url="/admin/finalistas/imagens/ordenar" data-finalist-id="<?= (int)$row['id'] ?>" data-csrf="<?= $h(Csrf::token()) ?>">
                        <?php foreach($row['images'] as $imageIndex => $image): ?>
                            <article class="gallery-admin-item <?= (int)$image['is_cover']===1?'is-cover':'' ?>" data-gallery-image data-image-id="<?= (int)$image['id'] ?>" draggable="<?= (int)$image['is_cover']===1?'false':'true' ?>">
                                <div class="gallery-admin-preview"><img src="/media/finalistas/<?= (int)$image['id'] ?>" alt="<?= $h($image['alt_text']) ?>" loading="lazy"><?php if((int)$image['is_cover']===1): ?><span>Capa</span><?php endif; ?></div>
                                <div class="gallery-admin-meta"><strong data-photo-number>Foto <?= $imageIndex + 1 ?></strong><small><?= (int)$image['width_px'] ?> × <?= (int)$image['height_px'] ?> px · <?= number_format((int)$image['size_bytes']/1048576, 1, ',', '.') ?> MB</small><span class="gallery-drag-handle"><?= (int)$image['is_cover']===1?'Capa fixa':'⋮⋮ Arraste para ordenar' ?></span></div>
                                <div class="gallery-admin-actions">
                                    <?php if((int)$image['is_cover']!==1): ?>
                                    <form action="/admin/finalistas/imagens/capa" method="post"><input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>"><input type="hidden" name="image_id" value="<?= (int)$image['id'] ?>"><button>Usar como capa</button></form>
                                    <?php endif; ?>
                                    <form action="/admin/finalistas/imagens/mover" method="post"><input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>"><input type="hidden" name="image_id" value="<?= (int)$image['id'] ?>"><input type="hidden" name="direction" value="up"><button <?= $imageIndex===0?'disabled':'' ?>>Antes</button></form>
                                    <form action="/admin/finalistas/imagens/mover" method="post"><input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>"><input type="hidden" name="image_id" value="<?= (int)$image['id'] ?>"><input type="hidden" name="direction" value="down"><button <?= $imageIndex===count($row['images'])-1?'disabled':'' ?>>Depois</button></form>
                                    <form action="/admin/finalistas/imagens/excluir" method="post" onsubmit="return confirm('Excluir esta foto permanentemente?')"><input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>"><input type="hidden" name="image_id" value="<?= (int)$image['id'] ?>"><button class="danger">Excluir</button></form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        </div>
                        <p class="gallery-order-status" data-gallery-order-status aria-live="polite"></p>
                    <?php endif; ?>
                </section>
            </div>
        </details>
    <?php endforeach; ?>
    </div>
</section>

<section class="admin-panel">
    <div class="panel-heading"><div><span>Novo</span><h2>Adicionar finalista</h2></div></div>
    <form action="/admin/finalistas/salvar" method="post" class="admin-form">
        <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>">
        <div class="field-grid two"><label>Profissional<input name="participant_name" required></label><label>Título do projeto<input name="project_title" required></label><label>URL da publicação no Instagram<input type="url" name="instagram_url" placeholder="https://www.instagram.com/p/..." required></label><label>Imagem externa de reserva <span>opcional</span><input type="url" name="fallback_image_url"></label></div>
        <label class="check-row"><input type="checkbox" name="active" value="1"><span>Ativar imediatamente</span></label>
        <button class="button primary">Adicionar finalista</button>
    </form>
</section>
