$ErrorActionPreference = 'Stop'
$labRoot = Split-Path $PSScriptRoot -Parent
$path = Join-Path $labRoot '.local/environment.json'
$settings = @{}
if (Test-Path -LiteralPath $path) {
    $existing = Get-Content -LiteralPath $path -Raw | ConvertFrom-Json
    foreach ($property in $existing.PSObject.Properties) { $settings[$property.Name] = $property.Value }
}
foreach ($name in @('AWS_PROFILE','AWS_REGION','SQS_QUEUE_URL','MAILTRAP_HOST','MAILTRAP_PORT','MAIL_FROM_ADDRESS','MAILTRAP_DELAY_SECONDS')) {
    $current = $settings[$name]
    $value = Read-Host "$name [$current] (Enter keeps current)"
    if ($value) { $settings[$name] = $value }
}
foreach ($name in @('MAILTRAP_USERNAME','MAILTRAP_PASSWORD')) {
    $secure = Read-Host "$name (hidden; Enter keeps current)" -AsSecureString
    if ($secure.Length -gt 0) { $settings[$name] = [System.Net.NetworkCredential]::new('', $secure).Password }
}
New-Item -ItemType Directory -Force -Path (Split-Path $path -Parent) | Out-Null
[System.IO.File]::WriteAllText($path, ($settings | ConvertTo-Json), [System.Text.UTF8Encoding]::new($false))
Write-Host 'Saved private local settings. Credential values were not printed.'
