# Instalação na hospedagem compartilhada Hostinger

## 1. Preparar o subdomínio

No hPanel, crie `passaporte.enquetedigital.com` e escolha como raiz de documentos a pasta `public/` deste projeto. Exemplo:

`/home/SEU_USUARIO/domains/enquetedigital.com/passaporte-app/public`

Não aponte o subdomínio para a raiz `passaporte-app`, pois `config/`, `storage/` e `database/` não podem ficar públicos.

## 2. Enviar os arquivos

Envie a pasta completa para `passaporte-app`, por Gerenciador de Arquivos, SFTP ou Git. Confirme:

- `public/index.php` é acessível pelo subdomínio;
- `storage/uploads` existe e recebe permissão `700` ou, se o ambiente exigir, `750`;
- `config/app.php` recebe permissão `600` ou `640`;
- a raiz do subdomínio é exatamente `passaporte-app/public`.

## 3. Criar e importar o banco

No hPanel, crie um banco MySQL e um usuário exclusivo. Pelo phpMyAdmin:

1. importe `database/schema.sql`;
2. importe `database/seed.sql`;
3. confira que as tabelas foram criadas com `utf8mb4` e InnoDB.

O `seed.sql` cria três finalistas demonstrativos para validar o layout. Eles devem ser substituídos no painel por URLs reais de publicações antes da campanha.

## 4. Configurar a aplicação

Copie `config/app.example.php` para `config/app.php`. Preencha:

- banco, usuário e senha;
- `base_url` com `https://passaporte.enquetedigital.com`;
- `app.secret` com pelo menos 64 caracteres aleatórios;
- datas, somente se houver alteração oficial aprovada.

Uma chave pode ser gerada no terminal do hPanel com:

`php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`

Nunca reutilize senha do banco como `app.secret` e nunca publique `config/app.php` em repositório.

## 5. Criar o administrador

No terminal/SSH da Hostinger, a partir da raiz do projeto:

`php bin/create-admin.php "Nome do responsável" email@empresa.com "UMA-SENHA-COM-12-OU-MAIS" administrator`

Depois, acesse `/admin/login`. Crie contas individuais; não compartilhe credenciais.

## 6. Ativar HTTPS e Cloudflare

Ative SSL no hPanel e force HTTPS. Para bloquear votos fora do Brasil com informação de país confiável:

1. coloque o DNS do subdomínio em proxy Cloudflare (nuvem laranja);
2. mantenha `security.country_header` como `HTTP_CF_IPCOUNTRY`;
3. mantenha `allow_unknown_country` como `false`;
4. bloqueie acesso direto ao IP de origem, quando o plano/painel permitir, aceitando somente a Cloudflare;
5. teste um acesso brasileiro e um acesso de fora do país antes de abrir a votação.

Sem proxy confiável, qualquer cabeçalho de país enviado pelo visitante pode ser falsificado. O sistema foi configurado para rejeitar país ausente em produção.

## 7. Ajustar PHP

Selecione PHP 8.1+ e confirme as extensões PDO MySQL, OpenSSL, Fileinfo e Mbstring. O arquivo `public/.user.ini` solicita limites adequados para cinco imagens; o valor efetivo do plano prevalece. No hPanel, confirme:

- `upload_max_filesize` ≥ 10 MB;
- `post_max_size` ≥ 55 MB;
- `max_file_uploads` ≥ 8;
- `display_errors` desligado.

## 8. Configurar a campanha

No painel:

1. substitua os três finalistas demonstrativos;
2. valide cada embed do Instagram;
3. mantenha exatamente três finalistas ativos;
4. decida se a prévia pública do ranking ficará ligada;
5. confirme que votação e inscrições não estão fechadas manualmente.

## 9. Backups e cron

Ative backup diário do banco e dos diretórios `storage/uploads` e `config`. Mantenha ao menos uma cópia fora da conta de hospedagem. O site não exige cron para funcionar. Opcionalmente, rode semanalmente:

`php bin/verify-audit.php`

O retorno deve ter `"valid": true`. Qualquer falha deve congelar a apuração e abrir investigação.

## 10. Publicação segura

Execute todo o checklist de `docs/TESTES-E-ACEITE.md` e `docs/SEGURANCA-E-AUDITORIA.md`. Atualize o canal LGPD na página de privacidade e confirme o foro pendente do regulamento de varejo.

Antes de liberar o DNS ao público, rode `php tests/health-check.php`. O comando verifica versão e extensões do PHP, conexão, tabelas, quantidade de finalistas, diretório de uploads, segredo e cadeia de auditoria sem alterar dados.
