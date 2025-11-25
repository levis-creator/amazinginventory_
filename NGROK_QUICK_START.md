# ngrok Quick Start Guide

## 🚀 Fastest Way to Get Started

### Step 1: Install ngrok

**Windows:**
```bash
# Using Chocolatey
choco install ngrok

# Or download from https://ngrok.com/download
```

**macOS:**
```bash
# Using Homebrew
brew install ngrok

# Or download from https://ngrok.com/download
```

**Linux:**
```bash
# Download and install
curl -s https://ngrok-agent.s3.amazonaws.com/ngrok.asc | sudo tee /etc/apt/trusted.gpg.d/ngrok.asc >/dev/null
echo "deb https://ngrok-agent.s3.amazonaws.com buster main" | sudo tee /etc/apt/sources.list.d/ngrok.list
sudo apt update && sudo apt install ngrok
```

### Step 2: Sign Up & Get Auth Token

1. Go to [ngrok.com](https://ngrok.com) and sign up (free)
2. Get your authtoken from the dashboard
3. Authenticate:
   ```bash
   ngrok config add-authtoken YOUR_AUTH_TOKEN
   ```

### Step 3: Start Everything

**Option A: Manual (3 terminals)**

Terminal 1 - Laravel:
```bash
php artisan serve
```

Terminal 2 - Vite:
```bash
npm run dev
```

Terminal 3 - ngrok:
```bash
ngrok http 8000
```

**Option B: Using Scripts (Windows)**

```bash
# Run the Windows batch script
scripts\ngrok-start.bat
```

**Option C: Using Scripts (Linux/macOS)**

```bash
# Make script executable
chmod +x scripts/ngrok-start.sh

# Run the script
./scripts/ngrok-start.sh
```

### Step 4: Update APP_URL

1. Copy the ngrok URL (e.g., `https://abc123.ngrok.io`)
2. Update `.env`:
   ```env
   APP_URL=https://abc123.ngrok.io
   ```
3. Clear config cache:
   ```bash
   php artisan config:clear
   ```

### Step 5: Access Your App

- **Public URL**: Use the ngrok URL (e.g., `https://abc123.ngrok.io`)
- **Local URL**: `http://localhost:8000`
- **ngrok Dashboard**: `http://localhost:4040` (view all requests)

## ✅ That's It!

Your app is now accessible from anywhere on the internet!

## 🔍 View Requests

Open `http://localhost:4040` to see:
- All incoming requests
- Request/response details
- Replay requests
- Debug issues

## ⚠️ Important Notes

1. **ngrok URL changes** each time you restart (free plan)
2. **Update APP_URL** in `.env` each time you restart ngrok
3. **HTTPS is automatic** - ngrok provides SSL
4. **For testing only** - not for production

## 🐛 Troubleshooting

### "ngrok: command not found"
- Make sure ngrok is installed and in your PATH
- Or use full path to ngrok executable

### Assets not loading
- Make sure Vite is running: `npm run dev`
- Check browser console for errors

### APP_URL not updating
```bash
php artisan config:clear
php artisan cache:clear
```

### Port 8000 already in use
```bash
# Use different port
php artisan serve --port=8080
ngrok http 8080
```

## 📚 More Help

See [NGROK_SETUP.md](./NGROK_SETUP.md) for detailed documentation.


