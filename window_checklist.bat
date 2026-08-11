@echo off
setlocal EnableDelayedExpansion
cd /d "%~dp0"
title Timetable - Setup Checklist
:: Timetable System - Windows: CHECKLIST - Verify every requirement from scratch
:: Run this first on a new PC to see what is installed and what is missing.

set XAMPP=C:\xampp
if not defined PROJECT (
    for %%I in ("%~dp0.") do set "PROJECT=%%~nI"
)
if not defined PROJECT set "PROJECT=assigment"
set ALL_OK=1
set XAMPP_OK=1
set DB_OK=0

echo.
echo ============================================================
echo   TIMETABLE SYSTEM - SETUP CHECKLIST (Windows)
echo   Run this on a new PC to see what you need to install.
echo ============================================================
echo.

:: Environment check (can this laptop run the scripts?)
echo [Env] Windows version:
ver

echo [Env] Checking basic command-line tools...
where curl >nul 2>&1
if %errorlevel% equ 0 (
    echo   [OK] curl found - used by window_setup.bat to download XAMPP.
) else (
    echo   [WARN] curl not found. If window_setup.bat cannot download XAMPP,
    echo          download XAMPP manually from https://www.apachefriends.org/
    echo.
)

where powershell >nul 2>&1
if %errorlevel% equ 0 (
    echo   [OK] PowerShell available.
) else (
    echo   [INFO] PowerShell not found - rare on modern Windows.
)

:: Check if running as administrator (optional but recommended for setup)
net session >nul 2>&1
if %errorlevel% equ 0 (
    echo   [Env] You are running as Administrator.
) else (
    echo   [Env] Not running as Administrator. That is OK for this checklist,
    echo        but run window_setup.bat as Administrator for installation.
)
echo.

:: Step 1 - XAMPP folder
echo [Step 1/8] XAMPP folder exists (C:\xampp)
if exist "%XAMPP%\*" (
    echo   [OK] XAMPP folder found.
) else (
    echo   [MISSING] XAMPP is not installed. You need XAMPP to run this project.
    echo   Action: Run window_setup.bat as Administrator - it will download and install XAMPP.
    set ALL_OK=0
)
echo.

:: Step 2 - PHP
echo [Step 2/8] PHP (required to run the project)
if exist "%XAMPP%\php\php.exe" (
    echo   [OK] PHP found at %XAMPP%\php\php.exe
    "%XAMPP%\php\php.exe" -v 2>nul
) else (
    echo   [MISSING] PHP not found. XAMPP includes PHP - install XAMPP first - Step 1.
    set ALL_OK=0
    set XAMPP_OK=0
)
echo.

:: Step 3 - Apache (web server)
echo [Step 3/8] Apache web server
if exist "%XAMPP%\apache\bin\httpd.exe" (
    echo   [OK] Apache found.
) else (
    echo   [MISSING] Apache not found. XAMPP includes Apache - install XAMPP first.
    set ALL_OK=0
    set XAMPP_OK=0
)
echo.

:: Step 4 - MySQL
echo [Step 4/8] MySQL (database)
if exist "%XAMPP%\mysql\bin\mysqld.exe" (
    echo   [OK] MySQL found.
) else (
    echo   [MISSING] MySQL not found. XAMPP includes MySQL - install XAMPP first.
    set ALL_OK=0
    set XAMPP_OK=0
)
echo.

:: Step 5 - Apache running
echo [Step 5/8] Apache is running
tasklist /nh 2>nul | findstr /i "httpd.exe" >nul 2>&1
if %errorlevel% equ 0 (
    echo   [OK] Apache - httpd is running.
) else (
    echo   [NOT RUNNING] Start Apache from XAMPP Control Panel, or run window_setup.bat / window_run_project.bat.
    set ALL_OK=0
)
echo.

:: Step 6 - MySQL running
echo [Step 6/8] MySQL is running
tasklist /nh 2>nul | findstr /i "mysqld.exe" >nul 2>&1
if %errorlevel% equ 0 (
    echo   [OK] MySQL - mysqld is running.
) else (
    echo   [NOT RUNNING] Start MySQL from XAMPP Control Panel, or run window_setup.bat / window_run_project.bat.
    set ALL_OK=0
)
echo.

:: Step 7 - Project in htdocs
echo [Step 7/8] Project copied to XAMPP htdocs
if exist "%XAMPP%\htdocs\%PROJECT%\index.php" (
    echo   [OK] Project found at %XAMPP%\htdocs\%PROJECT%\
) else (
    echo   [MISSING] Project not in htdocs. Run window_setup.bat to copy project.
    set ALL_OK=0
)
echo.

:: Step 8 - Database created (install.php was run)
echo [Step 8/8] Database created (assignmentupdated)
if exist "%XAMPP%\php\php.exe" (
    "%XAMPP%\php\php.exe" "%~dp0database\check_db.php" >nul 2>&1
    if %errorlevel% equ 0 (
        echo   [OK] Database exists and is accessible.
        set DB_OK=1
    ) else (
        echo   [MISSING] Database not created. Run window_setup.bat to run install.php and create the database.
        set ALL_OK=0
        set DB_OK=0
    )
) else (
    echo   [SKIP] PHP not found - cannot check database.
    set ALL_OK=0
    set DB_OK=0
)
echo.

echo ============================================================
echo   SUMMARY (first-time status)
if "%XAMPP_OK%"=="1" (
    echo   - XAMPP installed ^(Apache + MySQL + PHP^): YES
    echo       -^> XAMPP folder and binaries detected under %XAMPP%.
) else (
    echo   - XAMPP installed ^(Apache + MySQL + PHP^): NO
    echo       -^> Run window_setup.bat to install XAMPP.
)
if "%DB_OK%"=="1" (
    echo   - Database installed ^(assignmentupdated^): YES
    echo       -^> Database exists and is reachable.
) else (
    echo   - Database installed ^(assignmentupdated^): NO
    echo       -^> Run window_setup.bat so it can run install.php and create the DB.
)
if "%ALL_OK%"=="1" (
    echo   - Ready to start project ^(window_run_project.bat^): YES
) else (
    echo   - Ready to start project ^(window_run_project.bat^): NO
)
echo.
if "%ALL_OK%"=="1" (
    echo   RESULT: All checks passed. You can run window_run_project.bat
    echo   Login: admin@isp.edu.pk / admin123
) else (
    echo   RESULT: Some steps are missing or not running.
    echo   Action: Run window_setup.bat ^(right-click - Run as administrator^)
    echo   Then run this checklist again to verify.
)
echo ============================================================
echo.
echo Press any key to close checklist and return to menu...
pause >nul
exit /b 0
