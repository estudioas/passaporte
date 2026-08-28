<?php

use App\Core\Csrf;
use App\Core\Security;

$h = [Security::class, 'h'];
$value = static fn (string $key): string => $h((string) ($old[$key] ?? ''));
$error = static fn (string $key): string => isset($errors[$key]) ? '<span class="field-error">' . $h((string) $errors[$key]) . '</span>' : '';
?>
<section class="hero hero-compact">
    <span class="eyebrow">Inscrições · 01/10 a 13/11/2026</span>
    <h1>Seu projeto pode levar você à Expo Revestir 2027.</h1>
    <p>Inscreva um ambiente real, concluído em 2026, com piso vinílico Ruffino em evidência.</p>
</section>

<section class="section form-section">
    <?php if ($success): ?>
        <div class="success-panel">
            <span class="success-mark">✓</span>
            <h2>Inscrição recebida</h2>
            <p>Guarde seu protocolo: <strong><?= $h($success) ?></strong></p>
            <p>A equipe Ruffino fará a análise técnica após o encerramento das inscrições.</p>
        </div>
    <?php elseif (!$registrationOpen): ?>
        <div class="notice warning"><strong>Inscrições fechadas.</strong> O formulário fica disponível entre 01/10/2026 e 13/11/2026, às 23h59.</div>
    <?php else: ?>
        <?php if (!empty($errors['_form'])): ?><div class="notice error"><?= $h($errors['_form']) ?></div><?php endif; ?>
        <div class="form-shell" data-multistep-form>
            <ol class="stepper" aria-label="Etapas da inscrição">
                <li class="active" data-step-indicator="1"><span>1</span>Contato</li>
                <li data-step-indicator="2"><span>2</span>Compra</li>
                <li data-step-indicator="3"><span>3</span>Obra</li>
                <li data-step-indicator="4"><span>4</span>Aceite</li>
            </ol>
            <form action="/inscricoes" method="post" enctype="multipart/form-data" novalidate data-registration-form>
                <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>">
                <input type="hidden" name="captcha_id" data-registration-captcha-id>
                <div class="honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>

                <fieldset data-form-step="1">
                    <legend><span>01</span>Dados de contato</legend>
                    <p class="fieldset-intro">Conte quem assina o projeto e como podemos falar com você.</p>
                    <div class="field-grid two">
                        <label>Nome completo do candidato*<input name="full_name" value="<?= $value('full_name') ?>" required autocomplete="name"><?= $error('full_name') ?></label>
                        <label>Registro profissional (CAU/ABD/CRE)*<input name="professional_registration" value="<?= $value('professional_registration') ?>" required><?= $error('professional_registration') ?></label>
                        <label>Instagram do candidato*<input name="instagram" value="<?= $value('instagram') ?>" placeholder="@minhapagina" required><?= $error('instagram') ?></label>
                        <label>Nome do escritório/empresa <span>opcional</span><input name="company" value="<?= $value('company') ?>" autocomplete="organization"></label>
                        <label>Cidade*<input name="city" value="<?= $value('city') ?>" required autocomplete="address-level2"><?= $error('city') ?></label>
                        <label>UF*<input name="state" value="<?= $value('state') ?>" maxlength="2" placeholder="PR" required autocomplete="address-level1"><?= $error('state') ?></label>
                        <label>E-mail*<input type="email" name="email" value="<?= $value('email') ?>" required autocomplete="email"><?= $error('email') ?></label>
                        <label>WhatsApp*<input type="tel" name="phone" value="<?= $value('phone') ?>" placeholder="(41) 99999-9999" required autocomplete="tel"><?= $error('phone') ?></label>
                    </div>
                </fieldset>

                <fieldset data-form-step="2" hidden>
                    <legend><span>02</span>Dados da compra</legend>
                    <p class="fieldset-intro">A indicação correta define a elegibilidade da revenda e do vendedor à premiação.</p>
                    <div class="field-grid two">
                        <label>Loja / revenda onde adquiriu o piso Ruffino*<input name="retailer_name" value="<?= $value('retailer_name') ?>" required><?= $error('retailer_name') ?></label>
                        <label>Cidade da revenda<input name="retailer_city" value="<?= $value('retailer_city') ?>"></label>
                        <label>Vendedor da revenda <span>opcional</span><input name="seller_name" value="<?= $value('seller_name') ?>"></label>
                        <label>Nota fiscal <span>PDF, JPG ou PNG; até 10 MB</span><input type="file" name="invoice" accept="application/pdf,image/jpeg,image/png,image/webp"></label>
                    </div>
                </fieldset>

                <fieldset data-form-step="3" hidden>
                    <legend><span>03</span>Dados da obra</legend>
                    <p class="fieldset-intro">O ambiente precisa ser real, finalizado e mostrar claramente o piso vinílico Ruffino.</p>
                    <div class="field-grid">
                        <label>Nome do ambiente*<input name="environment_name" value="<?= $value('environment_name') ?>" placeholder="Ex.: Living Aurora" required><?= $error('environment_name') ?></label>
                        <label>Fotos do ambiente — envie de 3 a 5 arquivos* <span>JPG, PNG ou WEBP; até 8 MB cada</span><input type="file" name="project_photos[]" accept="image/jpeg,image/png,image/webp" multiple required><?= $error('project_photos') ?></label>
                        <label>Fotógrafo / proprietário das imagens <span>opcional</span><input name="photographer" value="<?= $value('photographer') ?>"></label>
                    </div>
                    <div class="notice subtle">Não são aceitos renders, maquetes eletrônicas, fotos geradas por IA ou imagens ilustrativas.</div>
                </fieldset>

                <fieldset data-form-step="4" hidden>
                    <legend><span>04</span>Regulamento e envio</legend>
                    <div class="consent-list">
                        <label class="check-row"><input type="checkbox" name="regulation_consent" value="1" required><span>Declaro que li e aceito integralmente o <a href="/regulamento/profissionais" target="_blank">regulamento da campanha</a>.</span></label>
                        <label class="check-row"><input type="checkbox" name="privacy_consent" value="1" required><span>Li o <a href="/privacidade" target="_blank">aviso de privacidade</a> e autorizo o tratamento dos dados para gerir minha inscrição.</span></label>
                        <label class="check-row"><input type="checkbox" name="image_rights_consent" value="1" required><span>Confirmo que tenho autorização para uso das imagens e assumo responsabilidade sobre direitos autorais e de imagem.</span></label>
                    </div>
                    <?= $error('consent') ?>
                    <div class="captcha-box registration-captcha">
                        <label for="registration-captcha"><span data-registration-captcha-question>Carregando verificação…</span></label>
                        <input id="registration-captcha" name="captcha_answer" type="number" inputmode="numeric" required autocomplete="off">
                        <?= $error('captcha') ?>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <button type="button" class="button secondary" data-step-back hidden>Anterior</button>
                    <button type="button" class="button primary" data-step-next>Próximo</button>
                    <button type="submit" class="button primary" data-form-submit hidden>Enviar inscrição</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</section>
