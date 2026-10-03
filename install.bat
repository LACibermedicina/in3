@echo off
REM =====================================================================
REM  IN³ · INcubator — autoinstalador para Windows (cmd / PowerShell)
REM  Uso: install.bat            (interativo, sobe o servidor)
REM       install.bat 0          (prepara sem subir o servidor)
REM =====================================================================
setlocal enabledelayedexpansion
cd /d "%~dp0"

echo.
echo === IN3 - INcubator - instalacao (Windows) ===
echo.

where php >nul 2>&1
if errorlevel 1 (
  echo   [X] PHP nao encontrado no PATH.
  echo       Instale o PHP 8+ ^(php-cli com pdo_sqlite, sqlite3 e mbstring^) e adicione ao PATH.
  exit /b 1
)
for /f "delims=" %%v in ('php -r "echo PHP_VERSION;"') do set PHPV=%%v
echo   [OK] PHP %PHPV%

php -m | findstr /I /X "pdo_sqlite" >nul || (echo   [X] extensao ausente: pdo_sqlite & exit /b 1)
php -m | findstr /I /X "sqlite3"    >nul || (echo   [X] extensao ausente: sqlite3 & exit /b 1)
php -m | findstr /I /X "mbstring"   >nul || (echo   [X] extensao ausente: mbstring & exit /b 1)
echo   [OK] extensoes php: pdo_sqlite / sqlite3 / mbstring

if exist .env (
  echo   [!] .env ja existe - mantido como esta
) else (
  copy /y .env.example .env >nul
  echo   [OK] .env criado - EDITE o valor de IN3_ROTA_PAINEL ^(rota secreta do painel^)
)

if not exist data\semear mkdir data\semear
echo   [OK] pastas de dados prontas

echo.
php tools\instalar.php
if errorlevel 1 (
  echo   [X] a instalacao falhou
  exit /b 1
)

echo.
php tools\verificar.php
if errorlevel 1 echo   [!] verificacao com pendencias - leia a saida acima

set SUBIR=%1
if "%SUBIR%"=="0" (
  echo.
  echo   Preparacao concluida. Suba depois com:
  echo     php -S 0.0.0.0:8787 -t public public\index.php
  exit /b 0
)

set PORTA=8787
for /f "tokens=2 delims==" %%p in ('findstr /B /C:"PORT=" .env') do set PORTA=%%p
echo.
echo   Site publico: http://localhost:%PORTA%
echo   Painel:       http://localhost:%PORTA%/console   ^(ou a rota definida em .env^)
echo   ^(Ctrl+C para parar^)
echo.
php -S 0.0.0.0:%PORTA% -t public public\index.php
