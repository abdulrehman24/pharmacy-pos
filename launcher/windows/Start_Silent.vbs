' Pakistan Pharmacy POS - Silent Launcher (No Black Terminal Window)
Set fso = CreateObject("Scripting.FileSystemObject")
strDir = fso.GetParentFolderName(WScript.ScriptFullName)
strPath = fso.BuildPath(strDir, "Start_Pharmacy_POS.bat")

Set WshShell = CreateObject("WScript.Shell")
' Run with WindowStyle 0 (hidden) so user sees zero command prompt windows
WshShell.Run Chr(34) & strPath & Chr(34), 0, False
Set WshShell = Nothing
Set fso = Nothing
