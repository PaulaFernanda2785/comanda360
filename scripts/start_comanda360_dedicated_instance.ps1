$ErrorActionPreference = 'Stop'

$httpdExe = 'D:\wamp64\bin\apache\apache2.4.65\bin\httpd.exe'
$dedicatedConfig = 'D:\wamp64\bin\apache\apache2.4.65\conf\comanda360\httpd-comanda360.conf'
$dedicatedPid = 'D:\wamp64\bin\apache\apache2.4.65\logs\httpd-comanda360.pid'

foreach ($path in @($httpdExe, $dedicatedConfig)) {
    if (-not (Test-Path -LiteralPath $path)) {
        throw "Arquivo obrigatorio nao encontrado: $path"
    }
}

& $httpdExe -t -f $dedicatedConfig
if ($LASTEXITCODE -ne 0) {
    throw 'Falha na validacao da configuracao dedicada do comanda360.'
}

if (Test-Path -LiteralPath $dedicatedPid) {
    $rawPid = Get-Content -LiteralPath $dedicatedPid -ErrorAction SilentlyContinue | Select-Object -First 1
    $pidValue = 0
    [void] [int]::TryParse(($rawPid | Out-String).Trim(), [ref] $pidValue)
    if ($pidValue -gt 0) {
        $running = Get-Process -Id $pidValue -ErrorAction SilentlyContinue
        if ($running) {
            Write-Output "Instancia dedicada ja esta em execucao no PID $pidValue."
            exit 0
        }
    }
}

Start-Process -FilePath $httpdExe -ArgumentList @('-f', $dedicatedConfig) -WindowStyle Hidden
Start-Sleep -Seconds 3

if (Test-Path -LiteralPath $dedicatedPid) {
    $pidValue = (Get-Content -LiteralPath $dedicatedPid | Select-Object -First 1).Trim()
    Write-Output "Instancia dedicada iniciada. PID: $pidValue"
} else {
    Write-Warning 'A instancia foi iniciada, mas o PID file ainda nao apareceu. Valide os logs do Apache.'
}
