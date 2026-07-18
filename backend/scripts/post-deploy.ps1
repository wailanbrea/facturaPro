param(
    [string] $PhpPath = "C:\xampp\php\php.exe",
    [string] $ProjectRoot = "C:\xampp\htdocs\facturaPro\backend"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw "Ejecuta este script en PowerShell como Administrador. Sin elevacion no puede purgar bootstrap\cache ni reiniciar servicios de XAMPP."
}

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

# Purga las caches compiladas ANTES de invocar artisan.
#
# `composer install --no-dev` desinstala los paquetes de desarrollo (p. ej.
# laravel/pail), pero deja intacto el `bootstrap/cache/packages.php` generado
# cuando si estaban presentes. Al arrancar, Laravel intenta registrar el
# provider de un paquete que ya no existe y lanza
# "Class Laravel\Pail\PailServiceProvider not found": la aplicacion entera
# responde 500 y ni siquiera `artisan down` funciona. Estos ficheros se
# regeneran solos, asi que borrarlos siempre es seguro.
$staleCaches = @(
    (Join-Path $ProjectRoot "bootstrap\cache\packages.php"),
    (Join-Path $ProjectRoot "bootstrap\cache\services.php"),
    (Join-Path $ProjectRoot "bootstrap\cache\config.php"),
    (Join-Path $ProjectRoot "bootstrap\cache\events.php")
)

foreach ($cacheFile in $staleCaches) {
    if (Test-Path -LiteralPath $cacheFile) {
        Write-Output ("Purgando cache obsoleta: " + $cacheFile)
        Remove-Item -LiteralPath $cacheFile -Force
    }
}

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
