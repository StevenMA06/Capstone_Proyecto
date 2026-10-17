# Script de verificación rápida del entorno local
# Uso: .\scripts\test-local.ps1

Write-Host "`n=== Verificación de Entorno XAMPP ===" -ForegroundColor Cyan

# 1. Verificar PHP
Write-Host "`n[1/7] Verificando PHP..." -ForegroundColor Yellow
php -v
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ PHP no encontrado o error" -ForegroundColor Red
    exit 1
}
Write-Host "✅ PHP OK" -ForegroundColor Green

# 2. Verificar Composer
Write-Host "`n[2/7] Verificando Composer..." -ForegroundColor Yellow
composer -v | Select-Object -First 1
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Composer no encontrado" -ForegroundColor Red
    exit 1
}
Write-Host "✅ Composer OK" -ForegroundColor Green

# 3. Verificar Node
Write-Host "`n[3/7] Verificando Node.js..." -ForegroundColor Yellow
node -v
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Node.js no encontrado" -ForegroundColor Red
    exit 1
}
Write-Host "✅ Node.js OK" -ForegroundColor Green

# 4. Verificar archivo .env
Write-Host "`n[4/7] Verificando archivo .env..." -ForegroundColor Yellow
if (-Not (Test-Path ".env")) {
    Write-Host "❌ Archivo .env no encontrado" -ForegroundColor Red
    Write-Host "   Copiando desde .env.example..." -ForegroundColor Yellow
    Copy-Item ".env.example" ".env"
    Write-Host "   Ejecuta: php artisan key:generate" -ForegroundColor Yellow
} else {
    Write-Host "✅ .env existe" -ForegroundColor Green
}

# 5. Verificar APP_KEY
Write-Host "`n[5/7] Verificando APP_KEY..." -ForegroundColor Yellow
$envContent = Get-Content ".env" -Raw
if ($envContent -match "APP_KEY=base64:[A-Za-z0-9+/=]+") {
    Write-Host "✅ APP_KEY configurado" -ForegroundColor Green
} else {
    Write-Host "⚠️  APP_KEY no configurado" -ForegroundColor Yellow
    Write-Host "   Ejecuta: php artisan key:generate" -ForegroundColor Yellow
}

# 6. Verificar dependencias
Write-Host "`n[6/7] Verificando dependencias..." -ForegroundColor Yellow
if (-Not (Test-Path "vendor")) {
    Write-Host "⚠️  Directorio vendor no existe" -ForegroundColor Yellow
    Write-Host "   Ejecuta: composer install" -ForegroundColor Yellow
} else {
    Write-Host "✅ Dependencias PHP instaladas" -ForegroundColor Green
}

if (-Not (Test-Path "node_modules")) {
    Write-Host "⚠️  Directorio node_modules no existe" -ForegroundColor Yellow
    Write-Host "   Ejecuta: npm install" -ForegroundColor Yellow
} else {
    Write-Host "✅ Dependencias Node instaladas" -ForegroundColor Green
}

# 7. Test de conectividad a BD
Write-Host "`n[7/7] Probando conexión a base de datos..." -ForegroundColor Yellow
$dbTest = php artisan migrate:status 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Conexión a BD exitosa" -ForegroundColor Green
} else {
    Write-Host "❌ Error de conexión a BD" -ForegroundColor Red
    Write-Host "   Verifica que MySQL esté corriendo en XAMPP" -ForegroundColor Yellow
    Write-Host "   Y que la BD 'db_LunnysPeru' exista" -ForegroundColor Yellow
}

Write-Host "`n=== Resumen ===" -ForegroundColor Cyan
Write-Host "
Para iniciar el servidor de desarrollo:
  1. php artisan serve          (backend en http://127.0.0.1:8000)
  2. npm run dev                 (frontend con Vite)

Para ejecutar tests:
  - composer test                (tests PHP/PHPUnit)
  - npm run e2e                  (tests E2E con Playwright)

Para limpiar cache:
  - php artisan config:clear
  - php artisan cache:clear
  - php artisan route:clear
  - php artisan view:clear
" -ForegroundColor White
