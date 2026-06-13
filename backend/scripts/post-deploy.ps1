param(
    [string] $PhpPath = "C:\xampp\php\php.exe",
    [string] $ProjectRoot = "C:\xampp\php\www\FacturaPro\backend"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

if (-not (Test-Path -LiteralPath $PhpPath)) {
    throw "No se encontro php.exe en: $PhpPath"
}

if (-not (Test-Path -LiteralPath $ProjectRoot)) {
    throw "No se encontro el proyecto en: $ProjectRoot"
}

$commands = @(
    @("artisan", "down", "--render=errors::503"),
    @("artisan", "migrate", "--force"),
    @("artisan", "storage:link"),
    @("artisan", "config:cache"),
    @("artisan", "route:cache"),
    @("artisan", "view:cache"),
    @("artisan", "up")
)

Push-Location $ProjectRoot
try {
    foreach ($command in $commands) {
        Write-Output ("Ejecutando: php " + ($command -join " "))
        & $PhpPath @command

        if ($LASTEXITCODE -ne 0) {
            throw ("Fallo el comando: php " + ($command -join " "))
        }
    }
}
finally {
    Pop-Location
}
