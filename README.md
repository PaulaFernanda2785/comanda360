# Comanda360 - Estrutura MVC Inicial

Projeto base do Comanda360 em PHP puro com arquitetura MVC modular.

## Requisitos
- PHP 8.3+
- MySQL 8.4+
- Apache com mod_rewrite
- Extensões: pdo_mysql, mbstring, json

## Estrutura inicial
- `public/` entrada pública do sistema
- `app/Core/` núcleo do framework interno
- `app/Controllers/` controllers por contexto
- `app/Services/` regras de negócio
- `app/Repositories/` acesso a dados
- `resources/views/` templates
- `routes/` definição de rotas
- `config/` configurações
- `storage/` logs e arquivos temporários

## Como iniciar
1. Ajuste `config/database.php`
2. Ajuste `config/app.php`
3. Configure o VirtualHost para apontar para `public/`
4. Importe o schema SQL no banco
5. Acesse `/login`

## Usuário demo esperado
Use os registros que foram preparados no `seed_demo_comanda360.sql`.
