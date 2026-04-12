$ErrorActionPreference = 'Stop'
$hosts = 'C:\Windows\System32\drivers\etc\hosts'
$entries = @('127.0.0.1 comanda360.local','127.0.0.1 www.comanda360.local')
$current = Get-Content -LiteralPath $hosts -Raw
$toAdd = @()
foreach ($e in $entries) {
    $escaped = [regex]::Escape($e).Replace('\\ ', '\\s+')
    if ($current -notmatch "(?im)^\s*$escaped\s*$") {
        $toAdd += $e
    }
}
if ($toAdd.Count -gt 0) {
    Add-Content -LiteralPath $hosts -Value ("`r`n" + ($toAdd -join "`r`n") + "`r`n")
    Write-Output 'hosts atualizado.'
} else {
    Write-Output 'hosts j? estava ok.'
}

sc.exe stop wampapache64 | Out-Null
Start-Sleep -Seconds 2
sc.exe start wampapache64 | Out-Null
Write-Output 'Apache reiniciado.'
