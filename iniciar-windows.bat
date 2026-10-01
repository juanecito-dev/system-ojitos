@echo off
title Ojitos Caja
cd /d "%~dp0"
echo.
echo  ==============================
echo   Ojitos Caja - en esta PC
echo  ==============================
echo.

where php >nul 2>nul
if errorlevel 1 goto falta_php
where composer >nul 2>nul
if errorlevel 1 goto falta_php

if exist vendor\autoload.php goto paso_env
echo  Instalando lo necesario. Solo la primera vez, tarda unos minutos...
call composer install --no-interaction
if errorlevel 1 goto error

:paso_env
if exist .env goto paso_base
copy .env.example .env >nul
call php artisan key:generate --force
if errorlevel 1 goto error

:paso_base
if not exist database\database.sqlite type nul > database\database.sqlite
call php artisan migrate --force --seed
if errorlevel 1 goto error
call php artisan ojitos:modelos

echo.
echo  Listo. El sistema se abre en tu navegador: http://localhost:8000
echo.
echo  Desde un celular u otra PC de la misma red, entra a:
powershell -NoProfile -Command "Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.PrefixOrigin -ne 'WellKnown' -and $_.IPAddress -notlike '169.254.*' -and $_.InterfaceAlias -notmatch 'VMware|VirtualBox|vEthernet|Loopback' } | ForEach-Object { '    http://' + $_.IPAddress + ':8000' }"
echo.
echo  Para apagarlo, cierra esta ventana.
echo.
start "" cmd /c "timeout /t 2 >nul & start http://localhost:8000"
call php artisan serve --host=0.0.0.0 --port=8000
goto fin

:falta_php
echo  Falta instalar Laravel Herd, que trae PHP y Composer.
echo  Bajalo gratis de https://herd.laravel.com/windows
echo  Instalalo y vuelve a abrir este archivo.
echo.
pause
goto fin

:error
echo.
echo  Algo salio mal. Toma una foto de esta ventana y mandasela a Claude.
echo.
pause

:fin
