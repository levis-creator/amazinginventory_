# Running with ngrok - Quick Reference

## 🎯 Quick Start

1. **Install ngrok**: https://ngrok.com/download
2. **Authenticate**: `ngrok config add-authtoken YOUR_TOKEN`
3. **Start services**:

```bash
# Terminal 1: Laravel
php artisan serve

# Terminal 2: Vite (for assets)
npm run dev

# Terminal 3: ngrok
ngrok http 8000
```

4. **Update `.env`**:
   ```env
   APP_URL=https://your-ngrok-url.ngrok.io
   ```

5. **Clear cache**:
   ```bash
   php artisan config:clear
   ```

## 📖 Full Documentation

- **Quick Start**: [NGROK_QUICK_START.md](./NGROK_QUICK_START.md)
- **Detailed Guide**: [NGROK_SETUP.md](./NGROK_SETUP.md)

## 🔧 Helper Scripts

**Windows:**
```bash
scripts\ngrok-start.bat
```

**Linux/macOS:**
```bash
chmod +x scripts/ngrok-start.sh
./scripts/ngrok-start.sh
```

## ✅ Verify It's Working

1. Open ngrok URL in browser
2. Check ngrok dashboard: http://localhost:4040
3. Verify assets load (CSS/JS)
4. Test API endpoints

## ⚠️ Remember

- ngrok URL changes on restart (free plan)
- Update APP_URL each time
- HTTPS is automatic
- For testing only!




