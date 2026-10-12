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
    echo Server port %PORT% is listening. Verifying application health...
    goto CHECK_HEALTH
)

echo Starting Pharmacy POS Server on port %PORT%...
:: Start PHP artisan serve in background
start /B php artisan serve --host=0.0.0.0 --port=%PORT% > storage\logs\server_launcher.log 2>&1

:CHECK_HEALTH
echo Verifying Pharmacy POS server readiness on http://127.0.0.1:%PORT%/up ...
set RETRIES=15

:HEALTH_LOOP
powershell -Command "try { $r = Invoke-WebRequest -Uri 'http://127.0.0.1:%PORT%/up' -UseBasicParsing -TimeoutSec 2; if ($r.StatusCode -eq 200) { exit 0 } else { exit 1 } } catch { exit 1 }" >nul 2>&1
if %ERRORLEVEL% equ 0 (
    echo [SUCCESS] Server is healthy and operational!
    goto OPEN_BROWSER
)

set /a RETRIES=%RETRIES%-1
if %RETRIES% leq 0 (
    echo [ERROR] Pharmacy POS server failed health check on port %PORT%.
    echo Please check error log file: storage\logs\server_launcher.log
    pause
    exit /b 1
)
ping 127.0.0.1 -n 2 >nul
goto HEALTH_LOOP

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
if exist "%ProgramFiles%\Microsoft\Edge\Application\msedge.exe" (
    start "" "%ProgramFiles%\Microsoft\Edge\Application\msedge.exe" --app="http://localhost:%PORT%/pos"
    exit /b
)

:: Fallback: Open in default browser
start "" "http://localhost:%PORT%/pos"
exit /b
