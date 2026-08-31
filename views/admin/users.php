<?php use App\Core\Csrf; use App\Core\Security; $h=[Security::class,'h']; ?>
<?php if($message): ?><div class="notice success"><?= $h($message) ?></div><?php endif; ?>
<?php if($error): ?><div class="notice error"><?= $h($error) ?></div><?php endif; ?>
<div class="admin-grid two users-layout">
    <section class="admin-panel">
        <div class="panel-heading"><div><span>Novo acesso</span><h2>Criar usuário</h2></div></div>
        <form action="/admin/usuarios/salvar" method="post" class="admin-form">
            <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>">
            <div class="field-grid">
                <label>Nome<input name="name" maxlength="120" required autocomplete="name"></label>
                <label>E-mail de acesso<input type="email" name="email" maxlength="190" required autocomplete="email"></label>
                <label>Senha inicial <span>mínimo de 12 caracteres</span><input type="password" name="password" minlength="12" required autocomplete="new-password"></label>
                <label>Permissão<select name="role"><option value="auditor">Auditor — consulta dados e auditoria</option><option value="administrator">Administrador — acesso completo</option></select></label>
            </div>
            <label class="check-row"><input type="checkbox" name="active" value="1" checked><span>Permitir acesso imediatamente</span></label>
            <button class="button primary">Criar usuário</button>
        </form>
    </section>
    <section class="admin-panel">
        <div class="panel-heading"><div><span>Acessos cadastrados</span><h2>Usuários</h2></div><small><?= count($users) ?> conta(s)</small></div>
        <div class="user-admin-list">
            <?php foreach($users as $row): ?>
                <details class="user-admin-item">
                    <summary><div><span class="status-dot <?= (int)$row['active']===1?'active':'' ?>"></span><strong><?= $h($row['name']) ?></strong><small><?= $h($row['email']) ?></small></div><span><?= $row['role']==='administrator'?'Administrador':'Auditor' ?></span></summary>
                    <form action="/admin/usuarios/salvar" method="post" class="admin-form">
                        <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <div class="field-grid"><label>Nome<input name="name" value="<?= $h($row['name']) ?>" required></label><label>E-mail<input type="email" name="email" value="<?= $h($row['email']) ?>" required></label><label>Nova senha <span>deixe vazio para manter a atual</span><input type="password" name="password" minlength="12" autocomplete="new-password"></label><label>Permissão<select name="role"><option value="auditor" <?= $row['role']==='auditor'?'selected':'' ?>>Auditor</option><option value="administrator" <?= $row['role']==='administrator'?'selected':'' ?>>Administrador</option></select></label></div>
                        <label class="check-row"><input type="checkbox" name="active" value="1" <?= (int)$row['active']===1?'checked':'' ?>><span>Usuário ativo</span></label>
                        <button class="button primary">Salvar alterações</button>
                    </form>
                </details>
            <?php endforeach; ?>
        </div>
    </section>
</div>
