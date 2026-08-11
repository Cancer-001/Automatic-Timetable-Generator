@echo off
:: Timetable System - Windows: Start project and open in browser
:: Checks requirements first. If something is missing, tells you to run window_setup.bat

set XAMPP=C:\xampp
if not defined PROJECT (
    for %%I in ("%~dp0.") do set "PROJECT=%%~nI"
)
set URL=http://localhost/%PROJECT%/

echo.
echo ========================================
echo   Timetable System - Start Project
echo ========================================
echo.

:: Check XAMPP exists
if not exist "%XAMPP%\apache\bin\httpd.exe" (
    echo [CHECK FAILED] XAMPP not found.
    echo Run window_setup.bat first ^(right-click - Run as administrator^).
    echo.
    pause
    exit /b 1
)
echo [OK] XAMPP found.

:: Sync project (in case you updated files in the folder)
if not exist "%XAMPP%\htdocs" mkdir "%XAMPP%\htdocs"
xcopy /E /I /Y "%~dp0*" "%XAMPP%\htdocs\%PROJECT%\" >nul 2>&1
echo [OK] Project synced to htdocs.

:: Start Apache if not running
tasklist /nh 2>nul | findstr /i "httpd.exe" >nul 2>&1
if %errorlevel% neq 0 (
    echo Starting Apache...
    start /B "" "%XAMPP%\apache_start.bat" >nul 2>&1
    timeout /t 2 /nobreak >nul
) else (
    echo [OK] Apache running.
)

:: Start MySQL if not running
tasklist /nh 2>nul | findstr /i "mysqld.exe" >nul 2>&1
if %errorlevel% neq 0 (
    echo Starting MySQL...
    start /B "" "%XAMPP%\mysql_start.bat" >nul 2>&1
    timeout /t 4 /nobreak >nul
) else (
    echo [OK] MySQL running.
)

echo.
echo Opening browser at %URL%
start "" "%URL%"

echo.
echo App: %URL%
echo Login: admin@isp.edu.pk / admin123
echo.
timeout /t 3
