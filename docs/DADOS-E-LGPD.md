# Inventário de dados e retenção

## Votação e acesso

| Dado | Forma armazenada | Finalidade | Retenção proposta |
|---|---|---|---|
| IP | HMAC-SHA-256, sem IP bruto | correlação de abuso e limite opcional | 24 meses após o resultado |
| Dispositivo | cookie aleatório + HMAC no banco | impedir segunda confirmação | 24 meses após o resultado |
| Navegador | HMAC do user-agent | correlação antifraude | 24 meses após o resultado |
| País | código de duas letras | bloquear votos fora do Brasil | 24 meses após o resultado |
| Evento, rota, método, data | texto operacional | auditoria e segurança | 24 meses após o resultado |
| Risco e sinais | código e lista curta | revisão humana | 24 meses após o resultado |
| Voto e recibo | finalista, status e código aleatório | apuração e prova do voto | 24 meses após o resultado |

Não são registrados IP bruto, coordenadas, impressão digital invasiva, histórico de navegação externo, contatos do eleitor ou conteúdo de redes sociais.

## Inscrição

O banco guarda em campo criptografado: nome, registro profissional, Instagram, cidade/UF, e-mail, WhatsApp, empresa, revenda, cidade da revenda, vendedor, nome do ambiente e crédito fotográfico. O e-mail também gera um hash para detecção de duplicidade sem busca em texto claro. Fotos e nota fiscal ficam em `storage/uploads`, fora do diretório público, com hash de integridade.

Retenção proposta:

- inscrições não finalistas: 12 meses após o encerramento;
- finalistas: até 24 meses após o resultado;
- vencedor e parceiros premiados: prazos fiscais, contratuais e de defesa de direitos;
- consentimentos e evidências de aceite: enquanto necessários para demonstrar conformidade.

## Acessos administrativos

Nome, e-mail corporativo, hash de senha, função, último login, tentativas de login pseudonimizadas e ações realizadas. Retenha contas enquanto houver vínculo e mantenha ações na mesma janela da auditoria da campanha.

## Operação da retenção

A cadeia de auditoria não deve ter linhas apagadas isoladamente, pois isso quebra a prova de integridade. Ao fim do prazo:

1. exporte a cadeia e o banco para arquivo protegido;
2. gere e registre SHA-256 dos arquivos;
3. obtenha aprovação formal de descarte/arquivamento;
4. crie nova campanha ou novo ponto inicial de cadeia;
5. elimine dados e backups expirados de forma coordenada.

O script automático só remove tentativas antigas de login. O descarte da trilha é deliberadamente manual para impedir perda acidental de evidência.

## Pendências organizacionais

- informar o canal oficial do encarregado/LGPD na página pública;
- formalizar operadores (Hostinger, Cloudflare e backups);
- registrar base legal por finalidade;
- definir responsáveis por pedidos de titulares;
- definir procedimento e prazo de resposta a incidentes;
- aprovar o cronograma final de retenção com jurídico.
