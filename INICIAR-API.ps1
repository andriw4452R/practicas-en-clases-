$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot 'laravel')
php artisan serve --host=127.0.0.1 --port=8001
