param(
    [string] $Password = 'Pf04102008@',
    [string] $MysqlExe = '',
    [string] $DbHost = '',
    [int] $DbPort = 0,
    [string] $DbName = '',
    [string] $DbUser = '',
    [string] $DbPass = ''
)

$ErrorActionPreference = 'Stop'

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$EnvFile = Join-Path $ProjectRoot '.env'

function Read-EnvFile {
    param([string] $Path)

    $map = @{}
    if (!(Test-Path -LiteralPath $Path)) {
        return $map
    }

    foreach ($line in Get-Content -LiteralPath $Path) {
        $trimmed = $line.Trim()
        if ($trimmed -eq '' -or $trimmed.StartsWith('#')) {
            continue
        }

        $separator = $trimmed.IndexOf('=')
        if ($separator -lt 1) {
            continue
        }

        $key = $trimmed.Substring(0, $separator).Trim()
        $value = $trimmed.Substring($separator + 1).Trim()

        if (
            ($value.StartsWith('"') -and $value.EndsWith('"')) -or
            ($value.StartsWith("'") -and $value.EndsWith("'"))
        ) {
            $value = $value.Substring(1, $value.Length - 2)
        }

        $map[$key] = $value
    }

    return $map
}

function Resolve-Setting {
    param(
        [string] $Current,
        [hashtable] $EnvMap,
        [string] $EnvKey,
        [string] $Default = ''
    )

    if ($Current -ne '') {
        return $Current
    }

    if ($EnvMap.ContainsKey($EnvKey) -and $EnvMap[$EnvKey] -ne '') {
        return [string] $EnvMap[$EnvKey]
    }

    return $Default
}

function Resolve-MysqlExe {
    param([string] $Provided)

    if ($Provided -ne '') {
        if (!(Test-Path -LiteralPath $Provided)) {
            throw "mysql.exe nao encontrado em: $Provided"
        }
        return $Provided
    }

    $wampMysqlBase = 'D:\wamp64\bin\mysql'
    if (Test-Path -LiteralPath $wampMysqlBase) {
        $candidate = Get-ChildItem -LiteralPath $wampMysqlBase -Directory |
            Sort-Object Name -Descending |
            ForEach-Object { Join-Path $_.FullName 'bin\mysql.exe' } |
            Where-Object { Test-Path -LiteralPath $_ } |
            Select-Object -First 1

        if ($null -ne $candidate -and $candidate -ne '') {
            return $candidate
        }
    }

    $fromPath = Get-Command mysql -ErrorAction SilentlyContinue
    if ($null -ne $fromPath) {
        return $fromPath.Source
    }

    throw 'Nao foi possivel localizar mysql.exe. Informe -MysqlExe manualmente.'
}

$envMap = Read-EnvFile -Path $EnvFile

$DbHost = Resolve-Setting -Current $DbHost -EnvMap $envMap -EnvKey 'DB_HOST' -Default '127.0.0.1'
$DbName = Resolve-Setting -Current $DbName -EnvMap $envMap -EnvKey 'DB_DATABASE' -Default 'comanda360'
$DbUser = Resolve-Setting -Current $DbUser -EnvMap $envMap -EnvKey 'DB_USERNAME'
$DbPass = Resolve-Setting -Current $DbPass -EnvMap $envMap -EnvKey 'DB_PASSWORD'

if ($DbPort -le 0) {
    $DbPortRaw = Resolve-Setting -Current '' -EnvMap $envMap -EnvKey 'DB_PORT' -Default '3306'
    $DbPort = [int] $DbPortRaw
}

if ($DbUser -eq '') {
    throw 'DB_USERNAME nao definido. Configure no .env ou passe -DbUser.'
}

if ($DbPass -eq '') {
    throw 'DB_PASSWORD nao definido. Configure no .env ou passe -DbPass.'
}

$MysqlExe = Resolve-MysqlExe -Provided $MysqlExe

$php = Get-Command php -ErrorAction SilentlyContinue
if ($null -eq $php) {
    throw 'PHP nao encontrado no PATH. Necessario para gerar o hash bcrypt.'
}

$passwordForPhp = $Password.Replace('\', '\\').Replace("'", "\'")
$hash = (& php -r "echo password_hash('$passwordForPhp', PASSWORD_BCRYPT);").Trim()
if ($LASTEXITCODE -ne 0 -or $hash -eq '') {
    throw 'Falha ao gerar hash bcrypt da senha.'
}

$demoEmails = @(
    'admin@saboremesa.local',
    'gerente@saboremesa.local',
    'caixa@saboremesa.local',
    'garcom@saboremesa.local',
    'cozinha@saboremesa.local',
    'motoboy@saboremesa.local',
    'saas.admin@menu.local',
    'saas.suporte@menu.local',
    'saas.financeiro@menu.local'
)

$inList = ($demoEmails | ForEach-Object { "'" + $_.Replace("'", "''") + "'" }) -join ','
$hashSql = $hash.Replace("'", "''")

$updateQuery = @"
UPDATE users
SET password_hash = '$hashSql',
    updated_at = NOW()
WHERE email IN ($inList);
SELECT ROW_COUNT() AS updated_rows;
"@

$baseArgs = @(
    '-h', $DbHost,
    '-P', [string] $DbPort,
    '-u', $DbUser,
    $DbName
)

$oldMysqlPwd = $env:MYSQL_PWD
$env:MYSQL_PWD = $DbPass

try {
    $updateOutput = & $MysqlExe @baseArgs -e $updateQuery 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw ("Falha ao atualizar contas demo.`n" + ($updateOutput | Out-String))
    }

    $verifyQuery = "SELECT id, email, status FROM users WHERE email IN ($inList) ORDER BY id;"
    $verifyOutput = & $MysqlExe @baseArgs -e $verifyQuery 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw ("Falha ao validar contas demo.`n" + ($verifyOutput | Out-String))
    }

    Write-Output 'Senha demo resetada com sucesso.'
    Write-Output ('Senha aplicada: ' + $Password)
    Write-Output 'Usuarios demo atualizados:'
    Write-Output $verifyOutput
}
finally {
    if ($null -eq $oldMysqlPwd) {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    } else {
        $env:MYSQL_PWD = $oldMysqlPwd
    }
}
