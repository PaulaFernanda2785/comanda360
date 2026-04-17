# Comanda360 no WAMP

## Estado atual

- O Apache principal atende `comanda360.local` em `443` e faz proxy para a instancia dedicada em `127.0.0.1:9080`.
- A configuracao dedicada fica em `D:\wamp64\bin\apache\apache2.4.65\conf\comanda360\httpd-comanda360.conf`.
- O `php.ini` isolado do `comanda360` fica em `D:\wamp64\bin\apache\apache2.4.65\conf\comanda360\php.ini`.

## Fechamento definitivo recomendado

1. Abra `cmd` ou `PowerShell` como Administrador.
2. Execute `D:\wamp64\www\comanda360\scripts\install_comanda360_dedicated_service.cmd`.
3. Verifique os servicos:
   `sc.exe query comanda360apache64`
   `sc.exe query wampapache64`

## Fallback sem servico

- Se o servico dedicado ainda nao estiver registrado, execute:
  `powershell -ExecutionPolicy Bypass -File D:\wamp64\www\comanda360\scripts\start_comanda360_dedicated_instance.ps1`

## Validacoes uteis

- Backend dedicado:
  `Invoke-WebRequest http://127.0.0.1:9080/ -UseBasicParsing`
- TLS do hostname:
  `D:\wamp64\bin\apache\apache2.4.65\bin\openssl.exe s_client -connect comanda360.local:443 -servername comanda360.local`
- Config principal:
  `D:\wamp64\bin\apache\apache2.4.65\bin\httpd.exe -t`
- Config dedicada:
  `D:\wamp64\bin\apache\apache2.4.65\bin\httpd.exe -t -f D:\wamp64\bin\apache\apache2.4.65\conf\comanda360\httpd-comanda360.conf`
