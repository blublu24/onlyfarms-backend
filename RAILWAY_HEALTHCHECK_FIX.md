# 🚨 RAILWAY HEALTHCHECK FAILURE - QUICK FIX

## Problem
Your deployment logs show: **"Healthcheck failed! 1/1 replicas never became healthy!"**

The healthcheck is trying to reach `/api/health` but the application isn't responding because **APP_KEY is missing**.

---

## ✅ IMMEDIATE FIX (5 minutes)

### Step 1: Generate APP_KEY
In your local backend directory, run:
```bash
cd c:\xampp\htdocs\onlyfarmsbackend
php artisan key:generate --show
```

**Copy the output** (it will look like: `base64:abc123xyz...`)

### Step 2: Add APP_KEY to Railway
1. Go to your Railway dashboard: https://railway.app
2. Open your **OnlyFarms backend project**
3. Click on the **service** (not the database)
4. Click **"Variables"** tab
5. Click **"+ New Variable"**
6. **Name:** `APP_KEY`
7. **Value:** Paste the key from Step 1 (e.g., `base64:abc123xyz...`)
8. Click **"Add"**

### Step 3: Redeploy
Railway will automatically redeploy with the new variable. Monitor the logs:
1. Go to **"Deployments"** tab
2. Click on the latest deployment
3. Watch the **"Build Logs"** - should complete successfully
4. Watch the **"Deploy Logs"** - should show healthcheck passing

---

## ✅ VERIFY THE FIX

### Test the health endpoint:
```bash
# Replace with your actual Railway URL
curl https://your-app.railway.app/api/health
```

**Expected response:**
```json
{
  "status": "ok",
  "message": "OnlyFarms API is running",
  "timestamp": "2025-10-20T22:00:00.000000Z"
}
```

---

## 🔍 OTHER COMMON ISSUES (if healthcheck still fails)

### Issue 1: Database Connection
If APP_KEY is set but healthcheck still fails, check database variables:
- ✅ `DB_CONNECTION=mysql`
- ✅ `DB_HOST=${{MYSQL_HOST}}`
- ✅ `DB_PORT=${{MYSQL_PORT}}`
- ✅ `DB_DATABASE=${{MYSQL_DATABASE}}`
- ✅ `DB_USERNAME=${{MYSQL_USER}}`
- ✅ `DB_PASSWORD=${{MYSQL_PASSWORD}}`

**Fix:** Railway should auto-populate these when you add a MySQL database to your project.

### Issue 2: Node Version Warning
Your logs show:
```
You are using Node.js 18.20.5. Vite requires Node.js version 20.19+ or 22.12+.
```

This is just a **warning** - the build still succeeded. To fix permanently:
1. In Railway, add environment variable:
   - **Name:** `NODE_VERSION`
   - **Value:** `20`

### Issue 3: PORT Not Set
Railway provides `$PORT` automatically, but verify:
1. Check if `PORT` variable exists in Railway
2. If not, Railway should inject it automatically

---

## 📊 EXPECTED DEPLOYMENT LOGS (Success)

After adding APP_KEY, you should see:
```
===================
Starting Healthcheck
====================
Path: /api/health
Retry window: 5m0s

✓ Healthcheck passed!
```

---

## 🆘 STILL HAVING ISSUES?

### Check Railway Logs:
1. Go to **Deployments** → Latest deployment
2. Click **"View Logs"**
3. Look for error messages like:
   - `No application encryption key has been specified`
   - `SQLSTATE[HY000] [2002]` (database connection error)
   - `Class not found` (autoload issue)

### Common Solutions:
- **Missing APP_KEY:** Add it as shown above
- **Database not connected:** Add MySQL database in Railway
- **Migration errors:** Run `php artisan migrate:fresh --force` in Railway console
- **Cache issues:** Railway automatically runs cache commands, but you can clear via console

---

## 📝 DEPLOYMENT CHECKLIST

- [ ] APP_KEY generated and added to Railway
- [ ] MySQL database added to project
- [ ] Database variables auto-populated
- [ ] Deployment successful (check logs)
- [ ] Healthcheck passing
- [ ] `/api/health` endpoint responding
- [ ] Test API endpoints working

---

## 🚀 NEXT STEPS AFTER FIX

1. **Test your API:**
   ```bash
   curl https://your-app.railway.app/api/products
   ```

2. **Update frontend API URL:**
   - Edit `lib/api.ts` in your React Native app
   - Change `API_URL` to your Railway URL

3. **Test OAuth (optional):**
   - Update Facebook/Google redirect URIs to Railway URL
   - Test login flows

---

**Your deployment should be working within 2-3 minutes after adding APP_KEY!** 🎉

