#!/bin/bash

# ngrok Start Script for Laravel
# This script starts Laravel, Vite, and ngrok together

set -e

echo "🚀 Starting Laravel with ngrok..."
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if ngrok is installed
if ! command -v ngrok &> /dev/null; then
    echo "❌ ngrok is not installed!"
    echo "Please install ngrok: https://ngrok.com/download"
    exit 1
fi

# Check if .env exists
if [ ! -f ".env" ]; then
    echo "❌ .env file not found!"
    echo "Please copy .env.example to .env and configure it"
    exit 1
fi

# Get Laravel port (default 8000)
LARAVEL_PORT=${LARAVEL_PORT:-8000}

echo "📦 Installing dependencies if needed..."
if [ ! -d "vendor" ]; then
    composer install --no-interaction
fi

if [ ! -d "node_modules" ]; then
    npm install
fi

echo ""
echo "🔧 Starting services..."
echo ""

# Function to cleanup on exit
cleanup() {
    echo ""
    echo "🛑 Stopping services..."
    kill $(jobs -p) 2>/dev/null || true
    exit
}

trap cleanup SIGINT SIGTERM

# Start Laravel server
echo "${GREEN}Starting Laravel server on port $LARAVEL_PORT...${NC}"
php artisan serve --port=$LARAVEL_PORT > /dev/null 2>&1 &
LARAVEL_PID=$!

# Wait for Laravel to start
sleep 2

# Start Vite dev server
echo "${GREEN}Starting Vite dev server...${NC}"
npm run dev > /dev/null 2>&1 &
VITE_PID=$!

# Wait a bit for services to start
sleep 3

# Start ngrok
echo "${GREEN}Starting ngrok tunnel...${NC}"
ngrok http $LARAVEL_PORT > /tmp/ngrok.log 2>&1 &
NGROK_PID=$!

# Wait for ngrok to start
sleep 3

# Get ngrok URL
NGROK_URL=$(curl -s http://localhost:4040/api/tunnels | grep -o '"public_url":"https://[^"]*"' | head -1 | cut -d'"' -f4)

if [ -z "$NGROK_URL" ]; then
    echo "${YELLOW}⚠️  Could not get ngrok URL automatically${NC}"
    echo "Please check ngrok web interface: http://localhost:4040"
    echo "Or run: ngrok http $LARAVEL_PORT"
else
    echo ""
    echo "${GREEN}✅ Services started successfully!${NC}"
    echo ""
    echo "🌐 Public URL: $NGROK_URL"
    echo "🔗 Local URL: http://localhost:$LARAVEL_PORT"
    echo "📊 ngrok Web Interface: http://localhost:4040"
    echo ""
    echo "${YELLOW}⚠️  Don't forget to update APP_URL in .env:${NC}"
    echo "APP_URL=$NGROK_URL"
    echo ""
    echo "Press Ctrl+C to stop all services"
fi

# Wait for user interrupt
wait




