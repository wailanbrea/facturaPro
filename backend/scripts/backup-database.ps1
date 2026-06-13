param(
    [Parameter(Mandatory = $true)]
    [string] $Database,

    [Parameter(Mandatory = $true)]
    [string] $Username,

    [Parameter(Mandatory = $true)]
    [string] $Password,

    [string] $Host = "127.0.0.1",
    [int] $Port = 3306,
    [string] $MySqlDumpPath = "C:\xampp\mysql\bin\mysqldump.exe",
    [string] $BackupDirectory = "C:\backups\facturapro"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

if (-not (Test-Path -LiteralPath $MySqlDumpPath)) {
    throw "No se encontro mysqldump en: $MySqlDumpPath"
}

if (-not (Test-Path -LiteralPath $BackupDirectory)) {
    New-Item -ItemType Directory -Path $BackupDirectory | Out-Null
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$backupFile = Join-Path $BackupDirectory "facturapro-$timestamp.sql"

$arguments = @(
    "--host=$Host"
    "--port=$Port"
    "--user=$Username"
    "--password=$Password"
    "--single-transaction"
    "--quick"
    "--routines"
    "--events"
    $Database
)

$process = Start-Process -FilePath $MySqlDumpPath -ArgumentList $arguments -RedirectStandardOutput $backupFile -RedirectStandardError "$backupFile.err" -PassThru -Wait -WindowStyle Hidden

if ($process.ExitCode -ne 0) {
    $errorLog = Get-Content -Path "$backupFile.err" -Raw
    throw "mysqldump fallo con codigo $($process.ExitCode): $errorLog"
}

Remove-Item -LiteralPath "$backupFile.err" -ErrorAction SilentlyContinue
Write-Output "Backup generado: $backupFile"
