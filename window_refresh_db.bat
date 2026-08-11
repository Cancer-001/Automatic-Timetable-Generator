@echo off
:: Timetable System - Windows: Reset database to fresh state (delete all data)
:: Use when you want to start over. Type YES to confirm.

set XAMPP=C:\xampp
if not defined PROJECT (
    for %%I in ("%~dp0.") do set "PROJECT=%%~nI"
)

echo ========================================
echo Timetable System - Refresh Database
echo ========================================
echo This will DELETE all data. Type YES to continue.
set /p confirm=Confirm: 
if /i not "%confirm%"=="YES" (
    echo Cancelled.
    pause
    exit /b 0
)
echo.

if not exist "%XAMPP%\php\php.exe" (
    echo XAMPP PHP not found. Run window_setup.bat first.
    pause
    exit /b 1
)

"%XAMPP%\php\php.exe" "%~dp0database\refresh_db.php"

echo.
echo Optional: run window_runseed.bat to add sample data again.
pause
