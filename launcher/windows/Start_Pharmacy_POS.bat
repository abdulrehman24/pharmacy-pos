@echo off
title Pakistan Pharmacy POS Launcher
setlocal

:: Get root directory of the application
cd /d "%~dp0..\.."

set PORT=8080

:: 1. Ensure MySQL service is running if installed as a Windows service
net start mysql >nul 2>&1
net start MySQL80 >nul 2>&1
net start MySQL84 >nul 2>&1

:: 2. Check if port is already active
netstat -ano | findstr ":%PORT%" | findstr "LISTENING" >nul
if %ERRORLEVEL% equ 0 (
    echo Server is already running on port %PORT%.
    goto OPEN_BROWSER
)

echo Starting Pharmacy POS Server on port %PORT%...
:: Start PHP artisan serve in background
start /B php artisan serve --host=0.0.0.0 --port=%PORT% > storage\logs\server_launcher.log 2>&1

:: Wait 2 seconds for server to boot
ping 127.0.0.1 -n 3 >nul

:OPEN_BROWSER
echo Opening Pharmacy POS...

:: Check for Google Chrome for App Mode (Clean, full desktop feel with no address bar)
if exist "%ProgramFiles%\Google\Chrome\Application\chrome.exe" (
    start "" "%ProgramFiles%\Google\Chrome\Application\chrome.exe" --app="http://localhost:%PORT%/pos"
    exit /b
)
if exist "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" (
    start "" "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" --app="http://localhost:%PORT%/pos"
    exit /b
)
if exist "%LocalAppData%\Google\Chrome\Application\chrome.exe" (
    start "" "%LocalAppData%\Google\Chrome\Application\chrome.exe" --app="http://localhost:%PORT%/pos"
    exit /b
)

:: Check for Microsoft Edge
if exist "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" (
    start "" "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" --app="http://localhost:%PORT%/pos"
    exit /b
)

:: Fallback: Open in default browser
start "" "http://localhost:%PORT%/pos"
exit /b
