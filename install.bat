@echo off
REM =====================================================================
REM  IN³ · INcubator — autoinstalador para Windows (cmd/PowerShell)
REM  Requer PHP 8.1+ no PATH, com pdo_sqlite, sqlite3 e mbstring.
REM
REM    install.bat                 instala e sobe o servidor
REM    install.bat --so-preparar   só prepara
REM    install.bat --porta=9000    muda a porta
REM =====================================================================
setlocal enabledelayedexpansion
cd /d "%~dp0"
set PORTA=%PORT%
if "%PORTA%"=="" set PORTA=8787
set SUBIR=1

:args
if "%~1"=="" goto depois
if /I "%~1"=="--so-preparar" set SUBIR=0
echo %~1 | findstr /B /C:"--porta=" >nul && set PORTA=%~1
echo !PORTA! | findstr /B /C:"--porta=" >nul && set PORTA=!PORTA:--porta==!
shift
goto args
:depois

echo.
echo ==============================================================
echo   IN3 - INcubator - portfolio vivo da incubadora m3d.pro
echo   O que entra na m3d, sai ao cubo.
echo ==============================================================
echo.

where php >nul 2>nul
if errorlevel 1 (
  echo   X PHP nao encontrado no PATH.
  echo     Baixe em https://windows.php.net/download/ e marque "Add to PATH".
  echo     Habilite em php.ini: extension=pdo_sqlite  extension=sqlite3  extension=mbstring
  exit /b 1
)
for /f "delims=" %%v in ('php -r "echo PHP_VERSION;"') do set PHPV=%%v
echo   1/7 Ambiente: PHP !PHPV!
php -r "if(!version_compare(PHP_VERSION,'8.1.0','>=')){fwrite(STDERR,'versao antiga');exit(1);}" || (echo   X PHP antigo, minimo 8.1 & exit /b 1)

echo   2/7 Arquivos
if not exist data mkdir data
if not exist data\semear mkdir data\semear
if not exist .env (copy /y .env.example .env >nul & echo       .env criado) else (echo       .env mantido)

echo   3/7 Banco de dados
php tools\instalar.php || php public\index.php --instalar || (echo   X falha no esquema & exit /b 1)

echo   4/7 Inventario GitHub
php tools\sincronizar.php || echo       ! sem rede: usando cache existente

echo   5/7 Semear acervo
php tools\semear.php || (echo   X falha ao semear & exit /b 1)

echo   6/7 Verificacao de privacidade
php tools\verificar.php || (echo   X verificacao falhou & exit /b 1)

echo   7/7 Servidor
echo.
echo   Falta criar o primeiro administrador:
echo       php tools\senha.php --usuario=SEU_USUARIO --criar --papel=admin --clareza=4
echo.

if "%SUBIR%"=="0" (echo   Preparacao concluida. & exit /b 0)
echo   Servidor em http://localhost:%PORTA%  (Ctrl+C para parar)
php -S 0.0.0.0:%PORTA% -t public public\index.php
