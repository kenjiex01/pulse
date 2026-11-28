; Stock electron-builder treats a broken "tasklist | find" result as "People360 is still open",
; so setup shows "cannot be closed" and Retry loops even after a reboot with no people360.exe.
; Close the app (and any helper still locked inside the install folder) and continue.
!macro customCheckAppRunning
  nsExec::ExecToLog `taskkill /F /T /IM "${APP_EXECUTABLE_FILENAME}"`
  Pop $0
  nsExec::ExecToLog `powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "Get-CimInstance Win32_Process | Where-Object { $$_.ExecutablePath -and $$_.ExecutablePath -like '*\People360\*' } | ForEach-Object { Stop-Process -Id $$_.ProcessId -Force -ErrorAction SilentlyContinue }"`
  Pop $0
  Sleep 500
!macroend

!macro customInstall
  nsExec::ExecToLog 'netsh advfirewall firewall delete rule name="People360 LAN"'
  nsExec::ExecToLog 'netsh advfirewall firewall delete rule name="People360 LAN HTTP"'
  nsExec::ExecToLog 'netsh advfirewall firewall add rule name="People360 LAN" dir=in action=allow protocol=UDP localport=47836 profile=any enable=yes'
  nsExec::ExecToLog 'netsh advfirewall firewall add rule name="People360 LAN HTTP" dir=in action=allow protocol=TCP localport=47837 profile=any enable=yes'
!macroend

!macro customUnInstall
  nsExec::ExecToLog 'netsh advfirewall firewall delete rule name="People360 LAN"'
  nsExec::ExecToLog 'netsh advfirewall firewall delete rule name="People360 LAN HTTP"'
!macroend
