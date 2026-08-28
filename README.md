# Passaporte Ruffino Revestir 2027

Subsite completo para `passaporte.enquetedigital.com`, construído para hospedagem compartilhada Hostinger com PHP 8.1+ e MySQL/MariaDB. Não usa Node, filas, WebSockets, VPS ou processos persistentes em produção.

## O que está incluído

- home responsiva com três finalistas em ordem aleatória a cada carregamento;
- seleção livre e confirmação definitiva do voto;
- um voto confirmado por identificador pseudônimo de dispositivo;
- georrestrição ao Brasil por cabeçalho de país da Cloudflare, com falha fechada;
- CAPTCHA aritmético de uso único, CSRF, honeypot e sinais antifraude;
- comprovante público e trilha de auditoria encadeada por hash;
- ranking percentual opcional, sem total absoluto;
- painel para finalistas, configurações, votos, eventos, inscrições e arquivos;
- inscrição em quatro etapas, com nota fiscal e 3 a 5 fotos;
- dados pessoais criptografados e arquivos fora da pasta pública;
- regulamentos para profissionais e para revendas/vendedores;
- scripts SQL, criação de administrador e verificação de auditoria.

## Comece aqui

1. Leia [docs/INSTALACAO-HOSTINGER.md](docs/INSTALACAO-HOSTINGER.md).
2. Importe `database/schema.sql` e `database/seed.sql`.
3. Copie `config/app.example.php` para `config/app.php` e troque todos os segredos.
4. Aponte a raiz do subdomínio para a pasta `public/`.
5. Crie o administrador com `php bin/create-admin.php`.
6. Troque os três finalistas demonstrativos no painel antes da abertura.
7. Rode `php tests/health-check.php` e corrija qualquer item marcado como falha.

## Requisitos

- PHP 8.1 ou superior com PDO MySQL, OpenSSL, Fileinfo e Mbstring;
- MySQL 8+ ou MariaDB 10.5+;
- Apache com `mod_rewrite` e suporte a `.htaccess`;
- HTTPS obrigatório;
- Cloudflare em modo proxy para georrestrição confiável ao Brasil.

## Estrutura

- `public/`: única pasta exposta na web;
- `app/`: regras de negócio, segurança e controladores;
- `views/`: telas públicas e administrativas;
- `database/`: schema e dados iniciais;
- `storage/uploads/`: documentos e fotos privados;
- `bin/`: tarefas de terminal;
- `docs/`: implantação, segurança, LGPD e aceite.

## Decisões de produto

O regulamento oficial anexado chama a campanha de **Revestir 2027**, embora inscrições e votação ocorram em 2026. O site segue essa nomenclatura e o calendário: inscrições de 01/10/2026 a 13/11/2026, votação de 25/11/2026 a 11/12/2026 e resultado até 14/12/2026.

O regulamento de varejo contém o campo jurídico pendente `[cidade-sede da Ruffino]`. O site sinaliza essa pendência; confirme o foro antes da publicação.
