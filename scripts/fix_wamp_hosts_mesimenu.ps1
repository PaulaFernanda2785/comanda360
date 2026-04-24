param(
    [string] $ProjectPublicPath = 'D:/wamp64/www/mesimenu/public'
)

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

$hostsPath = 'C:\Windows\System32\drivers\etc\hosts'
$vhostsPath = 'D:\wamp64\bin\apache\apache2.4.65\conf\extra\httpd-vhosts.conf'
$httpdExe = 'D:\wamp64\bin\apache\apache2.4.65\bin\httpd.exe'
$apacheService = 'wampapache64'
$errorLogPath = 'D:/wamp64/logs/mesimenu-error.log'
$accessLogPath = 'D:/wamp64/logs/mesimenu-access.log'

foreach ($path in @($hostsPath, $vhostsPath, $httpdExe, $ProjectPublicPath)) {
    if (-not (Test-Path -LiteralPath $path)) {
        throw "Caminho obrigatorio nao encontrado: $path"
    }
}

$entries = @(
    '127.0.0.1 mesimenu.local',
    '127.0.0.1 www.mesimenu.local'
)

$hostsContent = Get-Content -LiteralPath $hostsPath -Raw
$toAdd = @()
foreach ($entry in $entries) {
    $escaped = [regex]::Escape($entry).Replace('\\ ', '\\s+')
    if ($hostsContent -notmatch "(?im)^\s*$escaped\s*$") {
        $toAdd += $entry
    }
}

if ($toAdd.Count -gt 0) {
    Add-Content -LiteralPath $hostsPath -Value ("`r`n" + ($toAdd -join "`r`n") + "`r`n")
    Write-Output 'hosts atualizado para mesimenu.local.'
} else {
    Write-Output 'hosts ja estava configurado para mesimenu.local.'
}

$startMarker = '# BEGIN MESIMENU LOCAL VHOST'
$endMarker = '# END MESIMENU LOCAL VHOST'
$vhostBlock = @"
$startMarker
<VirtualHost *:80>
    ServerName mesimenu.local
    ServerAlias www.mesimenu.local
    DocumentRoot "$ProjectPublicPath"
    DirectoryIndex index.php
    ErrorLog "$errorLogPath"
    CustomLog "$accessLogPath" combined
    <Directory "$ProjectPublicPath/">
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
$endMarker
"@

$vhostsContent = Get-Content -LiteralPath $vhostsPath -Raw
$managedBlockPattern = "(?s)$([regex]::Escape($startMarker)).*?$([regex]::Escape($endMarker))"
$vhostsWillChange = $false

if ($vhostsContent -match $managedBlockPattern) {
    $vhostsContent = [regex]::Replace($vhostsContent, $managedBlockPattern, $vhostBlock)
    $vhostsWillChange = $true
    $backupPath = "$vhostsPath.mesimenu-backup-$(Get-Date -Format 'yyyyMMdd_HHmmss')"
    Copy-Item -LiteralPath $vhostsPath -Destination $backupPath -Force
    [System.IO.File]::WriteAllText($vhostsPath, $vhostsContent, [System.Text.UTF8Encoding]::new($false))
    Write-Output 'VirtualHost mesimenu.local atualizado.'
} elseif ($vhostsContent -match '(?im)^\s*ServerName\s+mesimenu\.local\s*$') {
    Write-Output 'VirtualHost mesimenu.local ja existe fora do bloco gerenciado. Nenhuma alteracao aplicada ao vhosts.'
} else {
    $vhostsWillChange = $true
    $backupPath = "$vhostsPath.mesimenu-backup-$(Get-Date -Format 'yyyyMMdd_HHmmss')"
    Copy-Item -LiteralPath $vhostsPath -Destination $backupPath -Force
    Add-Content -LiteralPath $vhostsPath -Value ("`r`n" + $vhostBlock + "`r`n")
    Write-Output 'VirtualHost mesimenu.local adicionado.'
}

if ($vhostsWillChange) {
    Write-Output "Backup do httpd-vhosts.conf gerado: $backupPath"
}

Write-Output 'Validando configuracao do Apache...'
& $httpdExe -t
if ($LASTEXITCODE -ne 0) {
    throw 'Falha na validacao da configuracao do Apache.'
}

Write-Output 'Reiniciando Apache do WAMP...'
sc.exe stop $apacheService | Out-Null
Start-Sleep -Seconds 2
sc.exe start $apacheService | Out-Null
Start-Sleep -Seconds 3

Write-Output 'Estado final do Apache:'
sc.exe query $apacheService

Write-Output 'Validacao sugerida: Invoke-WebRequest http://mesimenu.local/ -UseBasicParsing'
