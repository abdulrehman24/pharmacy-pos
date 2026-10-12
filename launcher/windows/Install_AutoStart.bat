@echo off
title Install Pharmacy POS Desktop & AutoStart Shortcut
setlocal

set SCRIPT_DIR=%~dp0
set VBS_TARGET=%SCRIPT_DIR%Start_Silent.vbs
set ROOT_DIR=%SCRIPT_DIR%..\..
cd /d "%ROOT_DIR%"
set APP_DIR=%CD%

echo ===================================================
echo   Pakistan Pharmacy POS - Auto-Launcher Installer
echo ===================================================
echo.
echo Installing 1-Click Desktop Shortcut...

:: PowerShell command to create desktop shortcut
powershell -Command "$ws = New-Object -ComObject WScript.Shell; $s = $ws.CreateShortcut([Environment]::GetFolderPath('Desktop') + '\Pharmacy POS.lnk'); $s.TargetPath = '%VBS_TARGET%'; $s.WorkingDirectory = '%SCRIPT_DIR%'; $s.Description = 'Launch Pharmacy Point of Sale'; $s.Save()"

echo [OK] Created "Pharmacy POS" on Desktop!
echo.

set /p AUTOBOOT="Do you want the server to start automatically when Windows turns on? (Y/N): "
if /i "%AUTOBOOT%"=="Y" (
    powershell -Command "$ws = New-Object -ComObject WScript.Shell; $s = $ws.CreateShortcut([Environment]::GetFolderPath('Startup') + '\Pharmacy_POS_Server.lnk'); $s.TargetPath = '%VBS_TARGET%'; $s.WorkingDirectory = '%SCRIPT_DIR%'; $s.Description = 'Pharmacy POS Background Server'; $s.Save()"
    echo [OK] Added to Windows Startup! Server will boot on system startup.
)

echo.
echo ===================================================
echo   Installation Complete!
echo   Your client can now simply double-click
echo   "Pharmacy POS" on the Desktop.
echo ===================================================
pause
