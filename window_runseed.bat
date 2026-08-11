@echo off
:: Timetable System - Windows: Add sample data (departments, students, courses, etc.)
:: Run after window_setup. Requires MySQL running.

set XAMPP=C:\xampp
if not defined PROJECT (
    for %%I in ("%~dp0.") do set "PROJECT=%%~nI"
)

echo ========================================
echo Timetable System - Run Seeds
echo ========================================
echo.

if not exist "%XAMPP%\php\php.exe" (
    echo [CHECK FAILED] PHP not found. Run window_setup.bat first.
    pause
    exit /b 1
)
echo [OK] PHP found. Running seed...
echo.

:: Always run seed from this project folder (the one you're editing)
"%XAMPP%\php\php.exe" "%~dp0database\seed.php"

echo.
echo Done. Run window_run_project.bat to open the app.
pause
