# Testes e aceite de publicação

## Público

- [ ] A home mostra exatamente três finalistas.
- [ ] A ordem muda entre visitas sem mudar os dados de cada projeto.
- [ ] É possível trocar a escolha antes de confirmar.
- [ ] Fechar a janela não registra voto.
- [ ] CAPTCHA incorreto não registra voto.
- [ ] Voto fora do Brasil é rejeitado.
- [ ] Primeiro voto válido gera recibo.
- [ ] Segunda tentativa no mesmo dispositivo é rejeitada e mostra o recibo original.
- [ ] O recibo é localizado em `/auditoria`.
- [ ] A prévia pública desaparece quando desligada.
- [ ] Quando ligada, a prévia mostra só percentuais e nenhum total.
- [ ] Layout funciona em 360 px, tablet e desktop.
- [ ] Navegação por teclado e foco do diálogo funcionam.

## Inscrição

- [ ] Campos do formulário atual foram preservados: contato, registro, Instagram, local, e-mail, WhatsApp, empresa, revenda, nota fiscal, ambiente, fotos e crédito fotográfico.
- [ ] Revenda e vendedor estão disponíveis para a política de premiação.
- [ ] Três e cinco imagens são aceitas; duas e seis são rejeitadas.
- [ ] Arquivo acima do limite ou com MIME falso é rejeitado.
- [ ] O protocolo aparece após envio.
- [ ] Dados pessoais não aparecem em texto no banco.
- [ ] Arquivos não abrem por URL direta.
- [ ] Download administrativo confere SHA-256 antes de entregar.

## Painel

- [ ] Oito logins incorretos em 15 minutos bloqueiam novas tentativas daquela rede.
- [ ] É impossível ativar um quarto finalista.
- [ ] Desativar finalista não apaga histórico.
- [ ] Voto de risco alto entra em revisão e fica fora do ranking.
- [ ] Alterar status gera evento de auditoria.
- [ ] Filtros de risco e status funcionam.
- [ ] Exportação CSV abre corretamente em UTF-8.
- [ ] `php bin/verify-audit.php` retorna cadeia válida.
- [ ] Contas de auditor não compartilham senha com administrador.

## Campanha e conteúdo

- [ ] Identidade visual usa as logos oficiais fornecidas.
- [ ] Campanha identificada como Revestir 2027.
- [ ] Inscrições: 01/10/2026 a 13/11/2026, 23h59.
- [ ] Votação: 25/11/2026 a 11/12/2026.
- [ ] Resultado: até 14/12/2026.
- [ ] Prêmio do profissional confere com o regulamento.
- [ ] Revenda: R$ 5.000 em produtos, com requisitos de atividade e adimplência.
- [ ] Vendedor: R$ 1.000 em cartão premiação.
- [ ] Foro pendente do varejo foi substituído após aprovação jurídica.
- [ ] Canal LGPD foi publicado.

## Evidência de aceite

Registre data, versão do código, responsável, resultado dos itens acima, capturas dos fluxos principais, hash da exportação inicial vazia e confirmação de backup. Preserve esse pacote com a documentação da campanha.
