param(
    [Parameter(Mandatory = $true)]
    [string] $ApiBaseUrl,

    [Parameter(Mandatory = $true)]
    [string] $Email,

    [Parameter(Mandatory = $true)]
    [string] $Password
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Assert-Ok {
    param(
        [Parameter(Mandatory = $true)]
        [bool] $Condition,

        [Parameter(Mandatory = $true)]
        [string] $Message
    )

    if (-not $Condition) {
        throw $Message
    }
}

$normalizedBaseUrl = $ApiBaseUrl.TrimEnd('/')

Write-Output "1. Validando health endpoint..."
$health = Invoke-RestMethod -Method Get -Uri "$normalizedBaseUrl/health"
Assert-Ok ($health.status -eq "ok") "Health endpoint no devolvio status=ok."

Write-Output "2. Iniciando sesion..."
$loginBody = @{
    email = $Email
    password = $Password
    device_name = "FacturaPro Production Smoke Test"
} | ConvertTo-Json

$login = Invoke-RestMethod -Method Post -Uri "$normalizedBaseUrl/login" -ContentType "application/json" -Body $loginBody
Assert-Ok (-not [string]::IsNullOrWhiteSpace($login.access_token)) "Login no devolvio access_token."

$headers = @{
    Authorization = "$($login.token_type) $($login.access_token)"
    Accept = "application/json"
}

Write-Output "3. Validando perfil autenticado..."
$me = Invoke-RestMethod -Method Get -Uri "$normalizedBaseUrl/me" -Headers $headers
$authenticatedEmail = if ($null -ne $me.user) { $me.user.email } else { $me.email }
Assert-Ok ($authenticatedEmail -eq $Email) "El usuario autenticado no coincide con el esperado."

Write-Output "4. Validando bootstrap..."
$bootstrap = Invoke-RestMethod -Method Get -Uri "$normalizedBaseUrl/settings/bootstrap" -Headers $headers
Assert-Ok ($bootstrap.data.currencies.Count -gt 0) "Bootstrap no devolvio monedas."
Assert-Ok ($bootstrap.data.taxes.Count -gt 0) "Bootstrap no devolvio impuestos."
Assert-Ok ($bootstrap.data.payment_terms.Count -gt 0) "Bootstrap no devolvio terminos de pago."

Write-Output "5. Cerrando sesion..."
Invoke-RestMethod -Method Post -Uri "$normalizedBaseUrl/logout" -Headers $headers | Out-Null

Write-Output "Smoke test completado correctamente."
