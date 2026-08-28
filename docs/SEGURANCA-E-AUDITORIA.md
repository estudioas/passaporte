# Segurança e auditoria

## Modelo de voto

O voto só é persistido depois de o visitante:

1. escolher um dos três projetos ativos;
2. abrir a confirmação;
3. resolver o CAPTCHA aritmético de uso único;
4. confirmar a escolha dentro do período oficial;
5. passar pela validação de país.

Antes da confirmação, a pessoa pode trocar livremente de projeto. Depois dela, a restrição única em `votes.device_hash` impede outro voto no mesmo identificador de dispositivo.

### Limite honesto de “um voto por pessoa”

Sem login, CPF ou validação por e-mail/telefone, nenhum site consegue provar matematicamente uma pessoa física única. Esta implementação escolhe menor coleta de dados: um voto por dispositivo pseudônimo, correlação por hash de rede e navegador e revisão de sinais. Limpar cookies, trocar dispositivo e rede pode produzir uma nova identidade; esses padrões devem ser detectados na auditoria. A opção `strict_ip_vote_limit` adiciona limite por rede, mas pode bloquear famílias, escritórios e redes móveis compartilhadas.

Se o risco reputacional exigir identidade individual forte, faça uma versão aprovada pelo jurídico com OTP por e-mail/WhatsApp antes da campanha. Isso amplia tratamento de dados pessoais e não deve ser ativado por improviso.

## Sinais antifraude

- país diferente de BR ou país ausente: rejeição;
- CAPTCHA incorreto: rejeição e evento de alto risco;
- honeypot preenchido: risco máximo;
- navegador ausente ou típico de automação: +35;
- idioma do navegador ausente: +10;
- confirmação em menos de oito segundos: +30;
- confirmação sem evento de abertura da home: +25;
- duplicidade do dispositivo: rejeição com preservação do recibo original;
- limite por hash de rede: opcional e desligado por padrão.

Votos com risco 60 ou superior entram como `review` e não compõem o ranking até revisão humana. O auditor pode validar, manter em revisão ou invalidar, sempre gerando novo evento.

## Cadeia de integridade

Cada linha em `audit_events` contém `previous_hash` e `entry_hash`. O hash cobre:

- hash do evento anterior;
- instante do evento;
- tipo e ator;
- pontuação de risco;
- metadados canônicos.

O estado da cadeia é bloqueado dentro de transação antes de cada gravação para manter a ordem mesmo com acessos simultâneos. Rode `php bin/verify-audit.php` para recalcular a cadeia inteira. A cadeia evidencia alteração; ela não substitui backup imutável externo. Exporte o CSV e gere um hash SHA-256 no encerramento da votação, guardando ambos fora da hospedagem.

## Controles implementados

- consultas preparadas por PDO;
- senha com `password_hash`/`password_verify`;
- limitação de tentativas de login por hash de rede;
- regeneração da sessão após login;
- cookies HttpOnly, Secure em HTTPS e SameSite=Lax;
- proteção CSRF em toda mutação;
- CSP, `nosniff`, `frame-ancestors`, referrer policy e permissions policy;
- validação real de MIME, tamanho e estrutura de imagens;
- arquivos enviados fora da pasta pública, com nome aleatório e hash SHA-256;
- dados pessoais de inscrição criptografados com AES-256-GCM;
- IP, navegador e dispositivo guardados apenas como HMAC-SHA-256;
- exclusão lógica de finalista ativo, preservando histórico referenciado por votos.

## Checklist antes da abertura

- [ ] HTTPS forçado e certificado válido.
- [ ] Cloudflare proxy ativo e origem restrita.
- [ ] `app.secret` aleatório, exclusivo e guardado em cofre.
- [ ] `config/app.php` fora do Git e sem acesso web.
- [ ] exatamente três finalistas reais ativos.
- [ ] embeds e links do Instagram conferidos.
- [ ] administrador individual com senha forte.
- [ ] backup restaurado em ambiente de teste.
- [ ] geobloqueio testado de dentro e fora do Brasil.
- [ ] fluxo de voto testado, incluindo duplicidade e recibo.
- [ ] fila de revisão, mudança de status e ranking conferidos.
- [ ] exportação CSV e `verify-audit.php` conferidos.
- [ ] upload de 3 e 5 fotos testado; 2 e 6 fotos rejeitados.
- [ ] canal LGPD publicado.
- [ ] foro do regulamento de varejo aprovado.
- [ ] monitoramento do espaço em disco e alertas de erro ativos.

## Resposta a incidente

1. Feche manualmente a votação no painel.
2. Não apague eventos, votos ou arquivos.
3. Exporte a auditoria e registre SHA-256 da exportação.
4. Rode o verificador da cadeia e salve a saída.
5. Preserve backup do banco e logs do provedor.
6. Documente período, origem, sinais e ações tomadas.
7. Submeta a decisão de invalidação à comissão autorizada.
8. Avalie obrigações de comunicação a titulares e ANPD.
