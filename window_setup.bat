@echo off
setlocal EnableDelayedExpansion
:: Timetable System - Windows: FULL SETUP FROM SCRATCH
:: Each step is checked. If missing, it is installed or done. Run as Administrator first time.

set XAMPP=C:\xampp
set INSTALLER=%TEMP%\xampp-setup.exe
if not defined PROJECT (
    for %%I in ("%~dp0.") do set "PROJECT=%%~nI"
)

echo.
echo ============================================================
echo   TIMETABLE SYSTEM - SETUP FROM SCRATCH (Windows)
echo   Every step will be checked. Missing steps will be installed.
echo ============================================================
echo.

:: ---------------------------------------------------------------------------
:: Environment pre-checks (is this laptop ready to run setup?)
:: ---------------------------------------------------------------------------
echo [Env] Windows version:
ver

:: Check for curl (used to download XAMPP automatically)
where curl >nul 2>&1
if %errorlevel% neq 0 (
    echo [WARN] curl command not found.
    echo        Automatic XAMPP download may fail on this laptop.
    echo        If download fails, manually download XAMPP from:
    echo        https://www.apachefriends.org/download.html
)

:: Check if running as Administrator (recommended)
net session >nul 2>&1
if %errorlevel% equ 0 (
    echo [Env] Running as Administrator.
) else (
    echo [Env] Not running as Administrator. For installation, right-click
    echo        window_setup.bat and choose \"Run as administrator\".
)
echo.

:: ---------------------------------------------------------------------------
:: Step 1: XAMPP (includes PHP, Apache, MySQL)
:: ---------------------------------------------------------------------------
echo [Step 1/7] Checking XAMPP (PHP + Apache + MySQL)...
if exist "%XAMPP%\apache\bin\httpd.exe" (
    echo   [OK] XAMPP already installed. Moving to next step.
    goto :step2
)

echo   [MISSING] XAMPP is not installed.
if not exist "%INSTALLER%" (
    echo   Downloading XAMPP installer (~150 MB). Please wait...
    curl -L -o "%INSTALLER%" "https://sourceforge.net/projects/xampp/files/XAMPP%%20Windows/8.2.12/xampp-windows-x64-8.2.12-0-VS16-installer.exe/download"
    if not exist "%INSTALLER%" (
        echo   [FAILED] Download failed.
        echo   Get XAMPP manually: https://www.apachefriends.org/download.html
        echo   Install XAMPP, then run this file again.
        pause
        exit /b 1
    )
)

echo   Installing XAMPP. If prompted, choose "Run as administrator".
echo   When XAMPP installer finishes, run this batch file AGAIN to complete setup.
"%INSTALLER%" --mode unattended --launchapps 0
echo.
echo   After XAMPP installation completes, run window_setup.bat again.
pause
exit /b 0

:step2
:: ---------------------------------------------------------------------------
:: Step 2: PHP
:: ---------------------------------------------------------------------------
echo.
echo [Step 2/7] Checking PHP...
if exist "%XAMPP%\php\php.exe" (
    echo   [OK] PHP found.
) else (
    echo   [MISSING] PHP not found. Reinstall XAMPP or run this as Administrator.
    pause
    exit /b 1
)

:: ---------------------------------------------------------------------------
:: Step 3: Apache
:: ---------------------------------------------------------------------------
echo.
echo [Step 3/7] Checking Apache (web server)...
if exist "%XAMPP%\apache\bin\httpd.exe" (
    echo   [OK] Apache found.
) else (
    echo   [MISSING] Apache not found. Reinstall XAMPP.
    pause
    exit /b 1
)

:: ---------------------------------------------------------------------------
:: Step 4: MySQL
:: ---------------------------------------------------------------------------
echo.
echo [Step 4/7] Checking MySQL (database)...
if exist "%XAMPP%\mysql\bin\mysqld.exe" (
    echo   [OK] MySQL found.
) else (
    echo   [MISSING] MySQL not found. Reinstall XAMPP.
    pause
    exit /b 1
)

:: ---------------------------------------------------------------------------
:: Step 5: Start Apache and MySQL if not running
:: ---------------------------------------------------------------------------
echo.
echo [Step 5/7] Starting Apache and MySQL if not running...
tasklist /nh 2>nul | findstr /i "httpd.exe" >nul 2>&1
if %errorlevel% neq 0 (
    echo   Starting Apache...
    start /B "" "%XAMPP%\apache_start.bat" >nul 2>&1
    timeout /t 3 /nobreak >nul
) else (
    echo   [OK] Apache already running.
)

tasklist /nh 2>nul | findstr /i "mysqld.exe" >nul 2>&1
if %errorlevel% neq 0 (
    echo   Starting MySQL...
    start /B "" "%XAMPP%\mysql_start.bat" >nul 2>&1
    timeout /t 5 /nobreak >nul
) else (
    echo   [OK] MySQL already running.
)

:: ---------------------------------------------------------------------------
:: Step 6: Copy project to htdocs and create database
:: ---------------------------------------------------------------------------
echo.
echo [Step 6/7] Copying project to XAMPP htdocs...
if not exist "%XAMPP%\htdocs" mkdir "%XAMPP%\htdocs"
xcopy /E /I /Y "%~dp0*" "%XAMPP%\htdocs\%PROJECT%\" >nul 2>&1
echo   [OK] Project copied to %XAMPP%\htdocs\%PROJECT%\

echo.
echo [Step 6b/7] Checking database...
"%XAMPP%\php\php.exe" "%XAMPP%\htdocs\%PROJECT%\database\check_db.php" >nul 2>&1
if %errorlevel% equ 0 (
    echo   [OK] Database already exists. Skipping install.php
) else (
    echo   [MISSING] Creating database (running install.php)...
    "%XAMPP%\php\php.exe" "%XAMPP%\htdocs\%PROJECT%\install.php"
    if %errorlevel% neq 0 (
        echo   [FAILED] install.php failed. Make sure MySQL is running in XAMPP Control Panel.
        pause
        exit /b 1
    )
    echo   [OK] Database created.
)

:: ---------------------------------------------------------------------------
:: Step 7: Optional seed (sample data)
:: ---------------------------------------------------------------------------
echo.
echo [Step 7/7] Sample data (departments, students, courses, etc.)
set /p seed=Add sample data? (Y/N, default Y): 
if /i "%seed%"=="" set seed=Y
if /i "%seed%"=="Y" (
    "%XAMPP%\php\php.exe" "%XAMPP%\htdocs\%PROJECT%\database\seed.php"
    echo   [OK] Sample data added.
) else (
    echo   Skipped. You can run window_runseed.bat later.
)

:: ---------------------------------------------------------------------------
:: Open XAMPP Control Panel so user can see Apache/MySQL
:: ---------------------------------------------------------------------------
if exist "%XAMPP%\xampp-control.exe" (
    start "" "%XAMPP%\xampp-control.exe"
)

echo.
echo ============================================================
echo   SETUP COMPLETE
echo   Next: run window_run_project.bat to open the app in browser.
echo   Login: admin@isp.edu.pk / admin123
echo ============================================================
echo.
echo To verify everything: run window_checklist.bat
echo.
pause
