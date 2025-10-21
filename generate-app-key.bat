@echo off
echo ==========================================
echo   ONLYFARMS - Generate APP_KEY for Railway
echo ==========================================
echo.
echo Generating Laravel APP_KEY...
echo.

php artisan key:generate --show

echo.
echo ==========================================
echo   COPY THE KEY ABOVE AND ADD IT TO RAILWAY
echo ==========================================
echo.
echo Instructions:
echo 1. Copy the key above (starts with "base64:")
echo 2. Go to Railway dashboard
echo 3. Open your project ^> Variables tab
echo 4. Add new variable: APP_KEY = [paste the key]
echo 5. Railway will auto-redeploy
echo.
pause

