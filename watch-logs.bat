@echo off
echo === Watching PHP Error Logs (Press Ctrl+C to stop) ===
echo.
powershell -Command "Get-Content 'debug.log' -Wait -Tail 20"
