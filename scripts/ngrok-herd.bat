@echo off
REM ngrok Start Script for Laravel Herd (Windows)
REM This script starts ngrok tunnel for Herd applications

echo 🚀 Starting ngrok for Laravel Herd...
echo.

REM Check if ngrok is installed
where ngrok >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ❌ ngrok is not installed!
    echo Please install ngrok: https://ngrok.com/download
    echo Or use: choco install ngrok
    exit /b 1
)

echo.
echo 📝 Note: Make sure your Laravel Herd app is running at amazinginventory.test
echo.

REM Start ngrok on port 80 (Herd's default HTTP port)
echo 🌐 Starting ngrok tunnel on port 80...
echo.
echo Your ngrok URL will be available at: http://localhost:4040
echo.
start "ngrok" cmd /c "ngrok http 80"

timeout /t 3 /nobreak >nul

echo.
echo ✅ ngrok is starting!
echo.
echo 📋 Next steps:
echo    1. Wait a few seconds for ngrok to start
echo    2. Open http://localhost:4040 in your browser to see your public URL
echo    3. Copy the HTTPS URL (e.g., https://xxxx-xx-xx-xx-xx.ngrok.io)
echo    4. Update APP_URL in your .env file with that URL
echo    5. Run: php artisan config:clear
echo.
echo Press any key to exit (ngrok will continue running)...
pause >nul


