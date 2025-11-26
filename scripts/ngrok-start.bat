@echo off
REM ngrok Start Script for Laravel (Windows)
REM This script starts Laravel, Vite, and ngrok together

echo 🚀 Starting Laravel with ngrok...
echo.

REM Check if ngrok is installed
where ngrok >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ❌ ngrok is not installed!
    echo Please install ngrok: https://ngrok.com/download
    exit /b 1
)

REM Check if .env exists
if not exist ".env" (
    echo ❌ .env file not found!
    echo Please copy .env.example to .env and configure it
    exit /b 1
)

set LARAVEL_PORT=8000

echo 📦 Installing dependencies if needed...
if not exist "vendor" (
    composer install --no-interaction
)

if not exist "node_modules" (
    call npm install
)

echo.
echo 🔧 Starting services...
echo.

REM Start Laravel server
echo Starting Laravel server on port %LARAVEL_PORT%...
start "Laravel Server" cmd /c "php artisan serve --port=%LARAVEL_PORT%"

REM Wait a bit
timeout /t 2 /nobreak >nul

REM Start Vite dev server
echo Starting Vite dev server...
start "Vite Dev Server" cmd /c "npm run dev"

REM Wait a bit
timeout /t 3 /nobreak >nul

REM Start ngrok
echo Starting ngrok tunnel...
start "ngrok" cmd /c "ngrok http %LARAVEL_PORT%"

REM Wait for ngrok to start
timeout /t 3 /nobreak >nul

echo.
echo ✅ Services started!
echo.
echo 🌐 Check ngrok web interface for public URL: http://localhost:4040
echo 🔗 Local URL: http://localhost:%LARAVEL_PORT%
echo.
echo ⚠️  Don't forget to update APP_URL in .env with your ngrok URL
echo.
echo Press any key to exit (services will continue running)...
pause >nul




