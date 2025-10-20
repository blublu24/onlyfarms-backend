# 🔧 Railway Environment Variables Setup Guide

## ❓ Common Question: "Do I need to rename railway.env.template?"

**Answer: NO!** Keep the filename as `railway.env.template`.

---

## 📁 File Purpose

### **`railway.env.template`**
- ✅ This is a **reference/documentation** file
- ✅ Shows what variables you need to set in Railway
- ✅ Helps you track your configuration
- ❌ Railway does **NOT** read this file
- ❌ Do **NOT** rename it to `.env`

---

## 🎯 How Railway Environment Variables Work

Railway doesn't use `.env` files like local development. Instead:

```
┌─────────────────────────────────┐
│  railway.env.template (local)   │
│  └─ Reference only              │
│  └─ Not used by Railway         │
└─────────────────────────────────┘
            │
            │ Copy values manually
            ↓
┌─────────────────────────────────┐
│  Railway Dashboard → Variables  │
│  └─ ACTUAL environment vars     │
│  └─ Used by your app            │
└─────────────────────────────────┘
```

---

## 📋 Step-by-Step: Setting Environment Variables

### **Step 1: Keep the Template File**
✅ Your file: `railway.env.template`
✅ Don't rename it
✅ Use it as a checklist

### **Step 2: Go to Railway Dashboard**
1. Visit https://railway.app
2. Select your **OnlyFarms** project
3. Click on your **backend service** (the one with your code)
4. Click **"Variables"** tab

### **Step 3: Add Each Variable Manually**
Copy each variable from `railway.env.template` and add it to Railway:

#### Example: Adding APP_KEY
```
Template file shows:
APP_KEY=base64:AjAziaOy5j+iMbJys7xdHfio0jTcTK6iCdYodfV1muI=

In Railway Dashboard:
1. Click "+ New Variable"
2. Name: APP_KEY
3. Value: base64:AjAziaOy5j+iMbJys7xdHfio0jTcTK6iCdYodfV1muI=
4. Click "Add"
```

---

## 🎯 Required Variables for Railway

### **1. App Configuration**
```env
APP_NAME=OnlyFarms
APP_ENV=production
APP_KEY=base64:AjAziaOy5j+iMbJys7xdHfio0jTcTK6iCdYodfV1muI=
APP_DEBUG=false
APP_URL=https://your-app.railway.app
```

### **2. Database (Auto-populated by Railway)**
When you add a MySQL database, Railway automatically creates:
```env
DB_CONNECTION=mysql
DB_HOST=${{MYSQL_HOST}}
DB_PORT=${{MYSQL_PORT}}
DB_DATABASE=${{MYSQL_DATABASE}}
DB_USERNAME=${{MYSQL_USER}}
DB_PASSWORD=${{MYSQL_PASSWORD}}
```

### **3. Email (Optional - for email verification)**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@onlyfarms.com
MAIL_FROM_NAME=OnlyFarms
```

### **4. SMS (Optional - for phone verification)**
```env
FIREBASE_SERVER_KEY=your_firebase_server_key
```

### **5. OAuth (Optional - for social login)**
```env
FACEBOOK_CLIENT_ID=your_facebook_app_id
FACEBOOK_CLIENT_SECRET=your_facebook_secret
FACEBOOK_REDIRECT_URI=https://your-app.railway.app/api/auth/facebook/callback

GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_secret
GOOGLE_REDIRECT_URI=https://your-app.railway.app/api/auth/google/callback
```

---

## ✅ Verification Checklist

After adding variables to Railway:

- [ ] APP_KEY is set in Railway Dashboard
- [ ] APP_URL matches your Railway domain
- [ ] Database variables are auto-populated (if using Railway MySQL)
- [ ] Service redeployed automatically
- [ ] Check deployment logs for success
- [ ] Test `/api/health` endpoint

---

## 🔍 How to Check Your Variables

### In Railway Dashboard:
1. Go to your backend service
2. Click "Variables" tab
3. You should see all variables listed

### Common Variables You Should See:
```
✅ APP_KEY=base64:...
✅ APP_NAME=OnlyFarms
✅ APP_ENV=production
✅ APP_DEBUG=false
✅ APP_URL=https://...
✅ DB_CONNECTION=mysql
✅ MYSQL_HOST=...
✅ MYSQL_PORT=...
✅ MYSQL_DATABASE=...
✅ MYSQL_USER=...
✅ MYSQL_PASSWORD=...
```

---

## 🚨 Common Mistakes

### ❌ **Mistake 1: Renaming the template file**
```
DON'T DO THIS:
railway.env.template → .env
```
Railway doesn't read `.env` files!

### ❌ **Mistake 2: Only updating template file**
```
WRONG: Only editing railway.env.template
RIGHT: Add variables to Railway Dashboard
```

### ❌ **Mistake 3: Including quotes in Railway**
```
WRONG in Railway:
APP_NAME="OnlyFarms"

RIGHT in Railway:
APP_NAME=OnlyFarms
```

### ❌ **Mistake 4: Duplicate "base64:" prefix**
```
WRONG:
APP_KEY=base64:base64:abc123...

RIGHT:
APP_KEY=base64:abc123...
```

---

## 🎯 Summary

| Question | Answer |
|----------|--------|
| **Rename `railway.env.template`?** | ❌ No, keep the name |
| **Does Railway read this file?** | ❌ No, it's for reference only |
| **Where to set actual variables?** | ✅ Railway Dashboard → Variables tab |
| **Need .env file in project?** | ❌ No, Railway uses dashboard variables |
| **How to update variables?** | ✅ Edit in Railway Dashboard, not the file |

---

## 🚀 Quick Start

1. **Open Railway:** https://railway.app
2. **Select project** → Click backend service
3. **Go to Variables tab**
4. **Add this FIRST:**
   ```
   APP_KEY=base64:AjAziaOy5j+iMbJys7xdHfio0jTcTK6iCdYodfV1muI=
   ```
5. **Wait for automatic redeploy** (2-3 minutes)
6. **Test:** `curl https://your-app.railway.app/api/health`

---

**Your app should now start successfully!** 🎉

