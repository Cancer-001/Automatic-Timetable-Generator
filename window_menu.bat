@echo off
:: Timetable System - One shortcut menu for all actions
:: Double-click this file to choose: Start project, Refresh DB, Seed data, etc.

setlocal
cd /d "%~dp0"

:menu
cls
echo.
echo ============================================================
echo   TIMETABLE SYSTEM - SHORTCUT MENU
echo ============================================================
echo.
echo   1. Start project             (open app in browser)
echo   2. Refresh database          (delete all data, type YES to confirm)
echo   3. Seed data                 (add sample departments, students, courses)
echo   4. Refresh DB + Seed data    (reset database, then add fresh sample data)
echo   5. Check setup               (see if XAMPP, PHP, MySQL, DB are OK)
echo   6. First-time setup          (install XAMPP + create database)
echo   7. Exit
echo.
choice /c 1234567 /n /m "Enter number (1-7): "
set "choice=%errorlevel%"

if "%choice%"=="1" goto start
if "%choice%"=="2" goto refresh
if "%choice%"=="3" goto seed
if "%choice%"=="4" goto refresh_and_seed
if "%choice%"=="5" goto checklist
if "%choice%"=="6" goto setup
if "%choice%"=="7" goto end
goto menu

:start
echo.
echo [Working] Starting project... please wait.
call "%~dp0window_run_project.bat"
goto back

:refresh
echo.
echo [Working] Refreshing database... this may take a moment.
call "%~dp0window_refresh_db.bat"
goto back

:seed
echo.
echo [Working] Seeding sample data... please wait.
call "%~dp0window_runseed.bat"
goto back

:refresh_and_seed
echo.
echo First: refreshing database...
echo [Working] Step 1/2 - refreshing database...
call "%~dp0window_refresh_db.bat"
echo.
echo Now: seeding sample data...
echo [Working] Step 2/2 - seeding sample data...
call "%~dp0window_runseed.bat"
goto back

:checklist
echo.
call "%~dp0window_checklist.bat"
goto back

:setup
echo.
echo Run window_setup.bat as Administrator for first-time install.
echo Opening it now - if prompted, choose "Run as administrator".
call "%~dp0window_setup.bat"
goto back

:back
echo.
echo ------------------------------------------------------------
set /p again=Back to menu? (Y/N, default Y): 
if /i "%again%"=="" set again=Y
if /i "%again%"=="Y" goto menu
:end
echo.
echo Exiting menu...
timeout /t 1 >nul
endlocal
exit
