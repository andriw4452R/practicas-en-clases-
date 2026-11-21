$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$raiz = $PSScriptRoot
$destino = Join-Path $raiz 'laravel'
if (-not (Get-Command php -ErrorAction SilentlyContinue)) { throw 'No se encontró PHP en PATH.' }
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) { throw 'No se encontró Composer en PATH.' }
if (-not (Test-Path (Join-Path $destino 'artisan'))) {
    Write-Host 'Creando aplicación Laravel ...'
    composer create-project laravel/laravel $destino --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Falló la creación de Laravel.' }
}
Set-Location $destino
composer require 'php-amqplib/php-amqplib:^3.7' --no-interaction
if ($LASTEXITCODE -ne 0) { throw 'Falló la biblioteca AMQP. Compruebe sockets y mbstring con php -m.' }
Copy-Item -Path (Join-Path $raiz 'archivos\*') -Destination $destino -Recurse -Force
$bootstrap = Join-Path $destino 'bootstrap\app.php'
$texto = [System.IO.File]::ReadAllText($bootstrap)
if ($texto -notmatch "api:\s*__DIR__") {
    $patron = "web: __DIR__.'/../routes/web.php',"
    if (-not $texto.Contains($patron)) { throw 'No se encontró la configuración de rutas esperada en bootstrap/app.php.' }
    $texto = $texto.Replace($patron, "$patron`r`n        api: __DIR__.'/../routes/api.php',")
    [System.IO.File]::WriteAllText($bootstrap, $texto, [System.Text.UTF8Encoding]::new($false))
}
if (-not (Test-Path 'database\database.sqlite')) { New-Item -ItemType File -Path 'database\database.sqlite' | Out-Null }
php artisan config:clear
if ($LASTEXITCODE -ne 0) { throw 'Falló config:clear.' }
php artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw 'Fallaron las migraciones.' }
php artisan route:list --path=api
php artisan test
Write-Host 'Preparación terminada. Revise las pruebas antes de continuar.' -ForegroundColor Green
