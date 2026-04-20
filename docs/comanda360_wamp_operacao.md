# Comanda360 no WAMP

## Estado atual

- O Apache principal atende `http://comanda360.local` diretamente pelo `DocumentRoot` `D:/wamp64/www/comanda360/public`.
- O host `https://comanda360.local` deve apenas redirecionar para `http://comanda360.local` no ambiente local.
- A antiga instancia dedicada em `127.0.0.1:9080` deixou de ser requisito para uso local porque era o ponto que gerava o `503 Service Unavailable` quando nao subia junto.
- A configuracao dedicada antiga continua em `D:\wamp64\bin\apache\apache2.4.65\conf\comanda360\httpd-comanda360.conf` apenas como referencia/legado.

## Reinicio necessario

1. Reinicie o Apache do Wamp para carregar a configuracao nova.
2. Se o Wamp nao reciclar o Apache corretamente, feche o Wamp e abra novamente como Administrador.
3. Se ainda ficar com processo antigo preso, reinicie a maquina e teste antes de religar qualquer instancia dedicada do `comanda360`.

## Validacoes uteis

- HTTP local:
  `Invoke-WebRequest http://comanda360.local/ -UseBasicParsing`
- Redirecionamento HTTPS local:
  `D:\wamp64\bin\apache\apache2.4.65\bin\openssl.exe s_client -connect comanda360.local:443 -servername comanda360.local`
- Config principal:
  `D:\wamp64\bin\apache\apache2.4.65\bin\httpd.exe -t`
