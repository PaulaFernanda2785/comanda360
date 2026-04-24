# MesiMenu no WAMP

## Estado alvo desta etapa

- Host local novo: `http://mesimenu.local`.
- Alias local: `http://www.mesimenu.local`.
- DocumentRoot temporario: `D:/wamp64/www/comanda360/public`.
- Banco alvo preparado: `mesimenu`.
- Banco antigo preservado para rollback: `comanda360`.

O DocumentRoot ainda aponta para `comanda360/public` de proposito. O renome fisico da pasta para `D:/wamp64/www/mesimenu` fica para a fase final, depois de validar host, banco e aplicacao.

## Scripts criados

- `scripts/fix_wamp_hosts_mesimenu.cmd`
- `scripts/fix_wamp_hosts_mesimenu.ps1`
- `scripts/migrate_database_to_mesimenu.ps1`

## Configurar host local

Execute como Administrador:

```powershell
scripts\fix_wamp_hosts_mesimenu.cmd
```

O script:

- adiciona `mesimenu.local` e `www.mesimenu.local` no `hosts`;
- adiciona ou atualiza um bloco gerenciado em `httpd-vhosts.conf`;
- cria backup do `httpd-vhosts.conf` antes de alterar;
- valida Apache com `httpd.exe -t`;
- reinicia o servico `wampapache64`.

Enquanto a pasta fisica ainda for `D:/wamp64/www/comanda360`, o caminho padrao do script esta correto. Depois do renome final da pasta, execute o `.ps1` diretamente com:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\fix_wamp_hosts_mesimenu.ps1 -ProjectPublicPath "D:/wamp64/www/mesimenu/public"
```

## Validar host local

```powershell
Invoke-WebRequest http://mesimenu.local/ -UseBasicParsing
Invoke-WebRequest http://mesimenu.local/login -UseBasicParsing
```

## Preparar banco MesiMenu

Para clonar os dados reais do banco `comanda360` para o banco `mesimenu`, preservando backup antes da importacao:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\migrate_database_to_mesimenu.ps1 -User root
```

Se a senha do MySQL nao estiver vazia:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\migrate_database_to_mesimenu.ps1 -User root -Password "SUA_SENHA"
```

Para recriar o banco alvo do zero e importar uma instalacao limpa com seed demo:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\migrate_database_to_mesimenu.ps1 -User root -ForceRecreateTarget -FreshInstall -ImportSeed
```

## Trocar aplicacao para o banco novo

Depois da importacao e validacao:

```env
DB_DATABASE=mesimenu
APP_URL=http://mesimenu.local
SESSION_NAME=mesimenu_session
```

Mantenha o banco `comanda360` ate validar login, dashboards, cardapio digital, pagamento, QR e relatorios.

## Validacoes minimas

- `http://mesimenu.local/`
- `http://mesimenu.local/login`
- login de usuario demo
- `/admin/dashboard`
- `/saas/dashboard`
- `/menu-digital`
- geracao de QR
- painel de relatorios

## Pendencias para fase final

- Renomear a pasta local para `D:/wamp64/www/mesimenu`.
- Atualizar o DocumentRoot do vhost para `D:/wamp64/www/mesimenu/public`.
- Renomear o repositorio GitHub.
- Atualizar `origin` para `https://github.com/PaulaFernanda2785/mesimenu.git`.
- Regenerar pacote de deploy.
