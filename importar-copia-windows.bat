@echo off
title Ojitos Caja - importar copia
cd /d "%~dp0"
echo.
if "%~1"=="" goto sin_archivo
if not exist vendor\autoload.php goto sin_instalar

php artisan ojitos:importar "%~1" --negocio=ojitos
echo.
pause
goto fin

:sin_archivo
echo  Arrastra el archivo de tu copia, ojitos-copia-....json,
echo  y sueltalo encima de este archivo importar-copia-windows.bat
echo.
pause
goto fin

:sin_instalar
echo  Primero abre iniciar-windows.bat una vez para instalar el sistema.
echo.
pause

:fin
