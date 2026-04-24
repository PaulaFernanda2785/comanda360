param(
    [string] $SourceDatabase = 'comanda360',
    [string] $TargetDatabase = 'mesimenu',
    [string] $HostName = '127.0.0.1',
    [int] $Port = 3306,
    [string] $User = 'root',
    [string] $Password = '',
    [switch] $FreshInstall,
    [switch] $ImportSeed,
    [switch] $ForceRecreateTarget
)

$ErrorActionPreference = 'Stop'

function Find-MySqlTool {
    param([string] $ToolName)

    $command = Get-Command $ToolName -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }

    $wampMysqlBase = 'D:\wamp64\bin\mysql'
    if (Test-Path -LiteralPath $wampMysqlBase) {
        $candidate = Get-ChildItem -LiteralPath $wampMysqlBase -Directory |
            Sort-Object Name -Descending |
            ForEach-Object { Join-Path $_.FullName "bin\$ToolName.exe" } |
            Where-Object { Test-Path -LiteralPath $_ } |
            Select-Object -First 1

        if ($candidate) {
            return $candidate
        }
    }

    throw "Ferramenta MySQL nao encontrada: $ToolName"
}

function Build-MySqlArgs {
    param([string] $Database = '')

    $args = @(
        '--host', $HostName,
        '--port', [string] $Port,
        '--user', $User
    )

    if ($Password -ne '') {
        $args += "--password=$Password"
    }

    if ($Database -ne '') {
        $args += $Database
    }

    return $args
}

$basePath = Split-Path -Parent (Split-Path -Parent $PSCommandPath)
$backupDir = Join-Path $basePath 'storage\backups\database'
$schemaFile = Join-Path $basePath 'basedados\schema_producao_implantacao_mesimenu.sql'
$seedFile = Join-Path $basePath 'basedados\seed_demo_mesimenu.sql'
$viewsFile = Join-Path $basePath 'basedados\schema_views_relatorios_mesimenu.sql'
$schemaFileForMysql = $schemaFile.Replace('\', '/')
$seedFileForMysql = $seedFile.Replace('\', '/')
$viewsFileForMysql = $viewsFile.Replace('\', '/')

foreach ($path in @($schemaFile, $viewsFile)) {
    if (-not (Test-Path -LiteralPath $path)) {
        throw "Arquivo SQL obrigatorio nao encontrado: $path"
    }
}

if ($ImportSeed -and -not (Test-Path -LiteralPath $seedFile)) {
    throw "Arquivo seed nao encontrado: $seedFile"
}

if (-not (Test-Path -LiteralPath $backupDir)) {
    New-Item -ItemType Directory -Path $backupDir | Out-Null
}

$mysql = Find-MySqlTool 'mysql'
$mysqldump = Find-MySqlTool 'mysqldump'
$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$backupPath = Join-Path $backupDir "$SourceDatabase`_$timestamp.sql"

Write-Output "Gerando backup do banco '$SourceDatabase' em: $backupPath"
& $mysqldump @((Build-MySqlArgs $SourceDatabase) + @('--routines', '--triggers', '--events', '--single-transaction', "--result-file=$backupPath"))
if ($LASTEXITCODE -ne 0) {
    throw 'Falha ao gerar backup. Migracao abortada.'
}

if ($ForceRecreateTarget) {
    Write-Output "Recriando banco alvo '$TargetDatabase'."
    & $mysql @((Build-MySqlArgs) + @('--execute', "DROP DATABASE IF EXISTS ``$TargetDatabase``; CREATE DATABASE ``$TargetDatabase`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"))
    if ($LASTEXITCODE -ne 0) {
        throw 'Falha ao recriar banco alvo.'
    }
} else {
    Write-Output "Criando banco alvo '$TargetDatabase' se ainda nao existir."
    & $mysql @((Build-MySqlArgs) + @('--execute', "CREATE DATABASE IF NOT EXISTS ``$TargetDatabase`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"))
    if ($LASTEXITCODE -ne 0) {
        throw 'Falha ao criar banco alvo.'
    }
}

if ($FreshInstall) {
    Write-Output "Importando estrutura limpa MesiMenu em '$TargetDatabase'."
    & $mysql @((Build-MySqlArgs $TargetDatabase) + @('--execute', "SOURCE $schemaFileForMysql"))
    if ($LASTEXITCODE -ne 0) {
        throw 'Falha ao importar schema.'
    }

    if ($ImportSeed) {
        Write-Output "Importando seed demo MesiMenu em '$TargetDatabase'."
        & $mysql @((Build-MySqlArgs $TargetDatabase) + @('--execute', "SOURCE $seedFileForMysql"))
        if ($LASTEXITCODE -ne 0) {
            throw 'Falha ao importar seed demo.'
        }
    }
} else {
    Write-Output "Clonando dados reais de '$SourceDatabase' para '$TargetDatabase' a partir do backup."
    & $mysql @((Build-MySqlArgs $TargetDatabase) + @('--execute', "SOURCE $($backupPath.Replace('\', '/'))"))
    if ($LASTEXITCODE -ne 0) {
        throw 'Falha ao importar backup no banco alvo. Se o alvo ja tinha tabelas, revise e execute novamente com -ForceRecreateTarget.'
    }
}

Write-Output "Importando views/relatorios MesiMenu em '$TargetDatabase'."
& $mysql @((Build-MySqlArgs $TargetDatabase) + @('--execute', "SOURCE $viewsFileForMysql"))
if ($LASTEXITCODE -ne 0) {
    throw 'Falha ao importar views/relatorios.'
}

Write-Output 'Migracao preparada com sucesso.'
Write-Output "Backup gerado: $backupPath"
Write-Output "Proximo passo manual: alterar DB_DATABASE=$TargetDatabase no .env e validar login, dashboards e menu digital."
