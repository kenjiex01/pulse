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
