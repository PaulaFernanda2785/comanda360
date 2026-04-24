# Plano de rebranding: Comanda360 para MesiMenu

Data do levantamento: 2026-04-24

## Objetivo

Renomear o sistema de Comanda360 para MesiMenu sem quebrar o que ja funciona. A mudanca envolve marca publica, arquivos, configuracoes, banco de dados, scripts locais, documentacao, repositorio GitHub e caminhos usados no WAMP.

## Principios de execucao

1. Separar marca visual/textual de infraestrutura.
2. Alterar primeiro pontos centralizados e de baixo risco.
3. Manter compatibilidade temporaria para dados e QR Codes legados.
4. Validar cada modulo antes de avancar para renomes fisicos.
5. Nao apagar assets antigos ate confirmar que nao existem referencias ativas.
6. Fazer backup do banco antes de qualquer renome de database, tabela temporaria ou seed.

## Estado atual encontrado

- Stack: PHP puro com arquitetura MVC, MySQL, Apache/WAMP, Node apenas para geracao de QR Code.
- Repositorio local original: `d:/wamp64/www/comanda360`.
- Repositorio local final: `d:/wamp64/www/mesimenu`.
- Branch: `main`, acompanhando `origin/main`.
- Git esta limpo, exceto o novo arquivo `public/img/logo-mesimenu.png`.
- Remote original: `https://github.com/PaulaFernanda2785/comanda360.git`.
- Nao existe `composer.json` nem `composer.lock`, mas o workflow `.github/workflows/php.yml` tenta rodar Composer. Isso ja e um risco independente do rebranding.
- Logo nova disponivel: `public/img/logo-mesimenu.png`.

## Inventario de impacto

### 1. Marca publica e SEO

Arquivos principais:

- `app/Services/Marketing/LandingPageService.php`
- `resources/views/auth/login.php`
- `resources/views/auth/access.php`
- `resources/views/layouts/public.php`
- `resources/views/marketing/signup_company.php`
- `resources/views/marketing/signup_payment.php`
- `resources/views/marketing/signup_confirmation.php`
- `app/Controllers/Auth/LoginController.php`
- `app/Controllers/Marketing/LeadController.php`
- `app/Controllers/Marketing/PublicInteractionController.php`
- `app/Services/Marketing/PublicOnboardingService.php`

Acao:

- Trocar textos institucionais, SEO title, meta description, structured data, alt text, chamadas comerciais e textos de formulario para MesiMenu.
- Trocar `logo-comanda360.png` por `logo-mesimenu.png`.
- Revisar keywords para nao manter `comanda360` como termo principal.

Risco:

- Medio. A maior parte e texto, mas SEO e structured data precisam ficar consistentes.

### 2. Layouts internos, favicon e identidade visual

Arquivos principais:

- `resources/views/layouts/app.php`
- `resources/views/layouts/saas.php`
- `resources/views/layouts/digital_menu.php`
- `resources/views/admin/dashboard/subscription_receipt.php`
- `app/Helpers/helpers.php`
- `app/Services/Shared/AppShellService.php`
- `app/Services/Guest/DigitalMenuService.php`
- `app/Services/Admin/DashboardService.php`

Acao:

- Atualizar nome default, footer default, badges, recibo SaaS e referencias de logo.
- Criar ou substituir favicon `public/img/mesimenu.ico` antes de remover `comanda360.ico`.
- Atualizar helper `public_logo_url()` para priorizar `logo-mesimenu.png`.

Risco:

- Medio. Helpers de imagem sao usados em varias telas, inclusive landing e autenticacao.

### 3. Configuracoes de aplicacao

Arquivos principais:

- `config/app.php`
- `config/database.php`
- `.env.example`
- `.env` local

Acao:

- `APP_NAME=MesiMenu`.
- `APP_URL=http://mesimenu.local` no exemplo e, depois, no ambiente local.
- `SESSION_NAME=mesimenu_session`.
- Definir se o banco continuara `comanda360` durante a transicao ou se sera migrado para `mesimenu`.

Risco:

- Alto se trocar banco e sessao no mesmo passo sem validacao.
- Recomendacao: primeiro trocar marca visual e manter `DB_DATABASE=comanda360`; depois migrar banco em etapa propria.

### 4. Banco de dados e seeds

Arquivos principais:

- `basedados/schema_producao_implantacao_comanda360.sql`
- `basedados/schema_views_relatorios_comanda360.sql`
- `basedados/schema_menu_interativo_refinado_comanda360.sql`
- `basedados/seed_demo_comanda360.sql`
- `storage/tmp/schema_producao_implantacao_comanda360.mysql84.sql`

Acao:

- Criar novos arquivos `*_mesimenu.sql` com `CREATE DATABASE IF NOT EXISTS mesimenu` e `USE mesimenu`.
- Atualizar `.env.example` para apontar para os novos nomes.
- Decidir migracao real do banco local:
  - caminho conservador: manter banco `comanda360` ate o app estar rebrandado;
  - caminho final: criar `mesimenu`, importar schema/seed ou clonar dados, validar, depois trocar `DB_DATABASE`.

Risco:

- Alto. Pode quebrar login, dados demo, relatorios e views se importado parcialmente.

### 5. QR Code e compatibilidade legada

Arquivo principal:

- `app/Controllers/MediaController.php`

Estado atual:

- O endpoint aceita payload legado iniciado com `comanda360:`.
- Tambem aceita URL HTTP/HTTPS.

Acao:

- Aceitar tambem `mesimenu:`.
- Manter `comanda360:` por compatibilidade com QR Codes ja impressos.
- Revisar textos impressos em:
  - `resources/views/admin/tables/print_qr.php`
  - `resources/views/admin/orders/print_ticket.php`

Risco:

- Alto se remover payload legado. Nao remover nesta fase.

### 6. Scripts WAMP e host local

Estado atual:

- `mesimenu.local` e `www.mesimenu.local` sao os hosts locais ativos.
- `D:/wamp64/www/mesimenu` e uma pasta fisica independente, nao uma junction para `comanda360`.
- O VirtualHost legado de `comanda360.local` foi removido do Apache.
- O bloco SSL legado de `comanda360.local` foi removido do Apache.
- As entradas `comanda360.local` e `www.comanda360.local` foram removidas do `hosts`.
- O servico dedicado `comanda360apache64` e a pasta `conf/comanda360` foram removidos.
- Os scripts e a documentacao operacional antigos de `comanda360.local` foram removidos para evitar recriacao acidental do host legado.

Arquivos principais:

- `scripts/fix_wamp_hosts_mesimenu.ps1`
- `scripts/fix_wamp_hosts_mesimenu.cmd`
- `docs/mesimenu_wamp_operacao.md`

Acao:

- Manter somente scripts equivalentes para `mesimenu.local`.
- Validar Apache/WAMP sempre com `http://mesimenu.local`.
- Atualizar documentacao operacional quando houver mudanca no WAMP.

Risco:

- Alto. Envolve hosts do Windows, Apache e possiveis servicos dedicados.

### 7. Package e repositorio GitHub

Arquivos principais:

- `package.json`
- `package-lock.json`
- remote Git local
- repositorio GitHub

Acao:

- Atualizar `name`, `description`, `repository.url`, `bugs.url`, `homepage`.
- Renomear repositorio no GitHub de `comanda360` para `mesimenu`.
- Depois do rename no GitHub, atualizar remote:
  - `git remote set-url origin https://github.com/PaulaFernanda2785/mesimenu.git`

Risco:

- Medio. O GitHub normalmente redireciona o repositorio antigo, mas e melhor atualizar o remote local logo apos o rename.

### 8. Pastas e caminho raiz

Estado atual:

- Caminho local final: `d:/wamp64/www/mesimenu`.
- A pasta `d:/wamp64/www/comanda360` foi removida apos a copia independente para `mesimenu`.

Acao:

- Manter VirtualHost/DocumentRoot em `D:/wamp64/www/mesimenu/public`.
- Validar `APP_URL`, paths absolutos em scripts e documentos quando houver nova alteracao operacional.

Risco:

- Alto. Renome de pasta pode quebrar Apache, scripts e atalhos locais.

### 9. Artefatos gerados e historico

Arquivos/pastas:

- `storage/logs/app.log`
- `storage/marketing_leads/*.jsonl`
- `deploy/`
- assets antigos `public/img/logo-comanda360.png`, `public/img/comanda360.png`, `public/img/comanda360.ico`

Acao:

- Nao editar logs como parte da migracao de codigo.
- Regenerar pacotes em `deploy/` apenas no final.
- Manter assets antigos por uma versao para fallback e cache.

Risco:

- Baixo para runtime, alto para rastreabilidade se apagar historico sem necessidade.

## Ordem recomendada de execucao

### Fase 0 - Preparacao

- Confirmar que a logo `logo-mesimenu.png` esta correta.
- Criar backup do banco atual.
- Criar uma branch dedicada, por exemplo `rebranding/mesimenu`.
- Registrar inventario antes das mudancas.

### Fase 1 - Marca central e assets

- Atualizar defaults em `config/app.php`.
- Atualizar `public_logo_url()` em `app/Helpers/helpers.php`.
- Atualizar layouts principais para MesiMenu.
- Manter assets antigos.
- Validar login, landing, painel admin, SaaS e menu digital.

### Fase 2 - Landing, SEO e textos publicos

- Atualizar `LandingPageService`.
- Atualizar `LoginController`.
- Atualizar views publicas e onboarding.
- Validar HTML gerado, title, meta tags e imagens.

### Fase 3 - App interno e recibos

- Atualizar footers, badges, assinatura SaaS, tickets e recibos.
- Atualizar mensagens de lead/feedback.
- Validar fluxo autenticado e impressao.

### Fase 4 - QR e compatibilidade

- Adicionar suporte a `mesimenu:` em `MediaController`.
- Manter `comanda360:` como legado.
- Atualizar textos impressos de QR/ticket.
- Gerar QR novo e validar leitura.

### Fase 5 - Configuracao local e scripts

- Criar scripts WAMP para `mesimenu.local`. Concluido: `scripts/fix_wamp_hosts_mesimenu.ps1` e `scripts/fix_wamp_hosts_mesimenu.cmd`.
- Remover scripts operacionais antigos de `comanda360.local`. Concluido.
- Atualizar `.env.example`. Concluido para `APP_URL`, `SESSION_NAME`, `DB_DATABASE` e arquivos SQL MesiMenu.
- Ajustar `.env` local somente quando o host estiver configurado.
- Validar Apache/WAMP com `http://mesimenu.local`.

### Fase 6 - Banco

- Criar arquivos SQL novos com nome MesiMenu. Concluido em `basedados/*_mesimenu.sql`.
- Validar importacao em banco `mesimenu`.
- Trocar `DB_DATABASE` somente depois de importar e validar.
- Manter plano de rollback para `comanda360`.

### Fase 7 - Repositorio e pasta

- Renomear repositorio GitHub.
- Atualizar remote local.
- Renomear pasta raiz local.
- Atualizar DocumentRoot e documentacao.
- Regenerar pacote de deploy.

## Validacoes minimas por fase

- `git status --short`
- Sintaxe PHP dos arquivos alterados.
- Acesso a `/` e `/login`.
- Acesso a `/admin/dashboard`.
- Acesso a `/saas/dashboard`.
- Acesso a `/menu-digital`.
- Geracao de QR de mesa.
- Renderizacao da logo nova.
- Conferencia de ocorrencias restantes de `Comanda360` e `comanda360`.

## Decisoes pendentes

1. O dominio final sera `mesimenu.com.br`, `mesimenu.app` ou outro?
2. O banco final deve se chamar `mesimenu` ou manter `comanda360` temporariamente em producao/local?
3. O repositorio GitHub sera `mesimenu` ou `MesiMenu`?
4. A marca deve ser escrita sempre como `MesiMenu` ou pode aparecer `Mesimenu` em nomes tecnicos?
5. O favicon novo deve ser derivado da logo ou ja existe um arquivo separado?

## Primeira implementacao recomendada

Comecar pela Fase 1 com mudancas pequenas:

1. Atualizar defaults de marca em `config/app.php`.
2. Atualizar helper de logo para usar `logo-mesimenu.png`.
3. Usar `logo-mesimenu.png` como favicon temporario em PNG ate gerar `mesimenu.ico`.
4. Atualizar layouts principais para exibir MesiMenu.
5. Rodar validacao de sintaxe PHP.

Essa primeira etapa troca a percepcao visual do produto sem mexer ainda em banco, pasta raiz, remote GitHub ou host WAMP.
