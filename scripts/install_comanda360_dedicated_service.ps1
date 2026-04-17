$ErrorActionPreference = 'Stop'

function Test-IsAdministrator {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

if (-not (Test-IsAdministrator)) {
    Write-Error 'Execute este script em um PowerShell aberto como Administrador.'
    exit 1
}

$httpdExe = 'D:\wamp64\bin\apache\apache2.4.65\bin\httpd.exe'
$mainService = 'wampapache64'
$dedicatedService = 'comanda360apache64'
$dedicatedConfig = 'D:\wamp64\bin\apache\apache2.4.65\conf\comanda360\httpd-comanda360.conf'
$certPath = 'D:\wamp64\bin\apache\apache2.4.65\conf\certs\wamp-local-server.crt'
$dedicatedPid = 'D:\wamp64\bin\apache\apache2.4.65\logs\httpd-comanda360.pid'

foreach ($path in @($httpdExe, $dedicatedConfig, $certPath)) {
    if (-not (Test-Path -LiteralPath $path)) {
        throw "Arquivo obrigatorio nao encontrado: $path"
    }
}

Write-Output 'Validando configuracao principal do Apache...'
& $httpdExe -t
if ($LASTEXITCODE -ne 0) {
    throw 'Falha na validacao da configuracao principal do Apache.'
}

Write-Output 'Validando configuracao dedicada do comanda360...'
& $httpdExe -t -f $dedicatedConfig
if ($LASTEXITCODE -ne 0) {
    throw 'Falha na validacao da configuracao dedicada do comanda360.'
}

Write-Output 'Importando certificado local no repositório Trusted Root da maquina...'
Import-Certificate -FilePath $certPath -CertStoreLocation 'Cert:\LocalMachine\Root' | Out-Null

$serviceQuery = sc.exe query $dedicatedService 2>$null
if ($LASTEXITCODE -ne 0) {
    Write-Output 'Instalando servico dedicado do comanda360...'
    & $httpdExe -k install -n $dedicatedService -f $dedicatedConfig
    if ($LASTEXITCODE -ne 0) {
        throw 'Falha ao instalar o servico dedicado do comanda360.'
    }
} else {
    Write-Output 'Servico dedicado ja existente.'
}

if (Test-Path -LiteralPath $dedicatedPid) {
    Write-Output 'Encerrando instancia dedicada avulsa para evitar conflito...'
    & $httpdExe -k shutdown -f $dedicatedConfig 2>$null | Out-Null
    Start-Sleep -Seconds 2
}

Write-Output 'Subindo servico dedicado do comanda360...'
sc.exe stop $dedicatedService 2>$null | Out-Null
Start-Sleep -Seconds 2
sc.exe start $dedicatedService | Out-Null
Start-Sleep -Seconds 3

Write-Output 'Reiniciando Apache principal...'
sc.exe stop $mainService | Out-Null
Start-Sleep -Seconds 3
sc.exe start $mainService | Out-Null
Start-Sleep -Seconds 3

Write-Output 'Estado final dos servicos:'
sc.exe query $dedicatedService
sc.exe query $mainService
