# Analytics e permissões

- `/admin/analytics`: período de até 366 dias por consulta, visitantes por navegador, páginas, sessões (30 min de inatividade), duração observada, evolução diária, origens, UTM, cidades/países, dispositivos e navegadores. Exporta resumos das principais dimensões e série diária.
- `/admin/auditoria`: votos e eventos com paginação independente de 50 registros; filtro de eventos por data, tipo e IP. Exportação CSV integral protegida contra fórmulas.
- `/admin/permissoes`: somente administradores. Nível Administrador possui acesso completo; nível Auditor tem matriz configurável. Gestão de usuários e permissões não pode ser delegada. IP completo tem permissão própria. Concessões de revisão/exportação/IP habilitam também a consulta à auditoria.
- Online: navegadores com sinal de página visível nos últimos 90 segundos; atualização a cada 30 segundos. Robôs identificáveis pelo user-agent e sessões administrativas são excluídos. Não é contagem de pessoas identificadas. Não mede formulários externos da Ruffino.
- Novos IPs são criptografados em metadados da cadeia de auditoria. Os hashes antigos são irreversíveis e não permitem recuperar IPs completos. Não se reescreve a cadeia antiga.
- A primeira navegação cria `analytics_views` com `CREATE TABLE IF NOT EXISTS`, sem alterar tabelas existentes. Erros da coleta não bloqueiam o site e são enviados ao log do servidor. O painel mostra indisponibilidade caso falte permissão de criação.
- Coleta detalhada começa na implantação; não há retropreenchimento fictício. País XX e cidade vazia são apresentados como desconhecidos. Limpeza diária de dados de audiência anteriores a 180 dias; cadeia de auditoria mantém sua política separada.
- Datas de aplicação e conexão MySQL são alinhadas ao fuso configurado.

## Cloudflare — preparação, sem migração de DNS

DNS consultado em 07/09/2026: `enquetedigital.com` usa `helios.dns-parking.com` e `aster.dns-parking.com`; o subdomínio resolve para `147.79.105.41` e `89.116.213.34`.

Antes de mudar nameservers, é necessário acessar a conta Cloudflare e exportar a zona completa no provedor atual (inclusive MX, SPF, DKIM, DMARC, verificação e demais subdomínios). A consulta DNS pública não substitui o inventário completo da zona. Importar e comparar todos os registros, confirmar DNSSEC e os nameservers atribuídos à zona, e só então trocar a delegação. O site permanece hospedado na Hostinger.

Para cidades: proxy ativo e Managed Transform **Add visitor location headers**, que envia `CF-IPCity`, `CF-Region` e `CF-IPCountry`. O código valida o IP do proxy contra as faixas oficiais Cloudflare antes de aceitar esses cabeçalhos. Quando a hospedagem reescreve REMOTE_ADDR, validar a cadeia de proxies com o provedor; não confiar indiscriminadamente em cabeçalhos públicos. Sem cidade fornecida pela hospedagem/proxy, exibir desconhecida.

Manter rotas HTML autenticadas, `/admin/*` e `/api/*` sem cache, HTTPS Full (strict) após verificar o certificado da origem e testar acesso ao painel, IP real e localização após propagação. Não usar uma regra de cache de HTML que ignore cookies de sessão.

Referências: https://developers.cloudflare.com/rules/transform/managed-transforms/reference/ ; https://www.cloudflare.com/ips-v4/ ; https://www.cloudflare.com/ips-v6/ .

## Validação

PHP 8.3 via WebAssembly: 31 verificações de regressão em `tests/analytics-regression.php`, quatro telas renderizadas e oito bloqueios de autorização de controladores. Agregações usam SQLite no teste com adaptação explícita de funções MySQL; não substitui teste da instância MySQL de produção. Sintaxe dos 49 arquivos PHP e JavaScript verificada. Testes não usam configuração ou credenciais de produção.
