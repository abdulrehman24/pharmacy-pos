' Pakistan Pharmacy POS - Silent Launcher (No Black Terminal Window)
Set WshShell = CreateObject("WScript.Shell")
strPath = WshShell.CurrentDirectory & "\Start_Pharmacy_POS.bat"
' Run with WindowStyle 0 (hidden) so user sees zero command prompt windows
WshShell.Run Chr(34) & strPath & Chr(34), 0, False
Set WshShell = Nothing
