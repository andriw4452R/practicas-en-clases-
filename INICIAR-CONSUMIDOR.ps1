$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot 'laravel')
php artisan p31:consumir-reportes --max=3
