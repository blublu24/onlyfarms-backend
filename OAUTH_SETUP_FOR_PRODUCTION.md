# 🔐 OAuth Setup for Production (Railway)

Your backend is now deployed at:
**`https://onlyfarms-backend-production.up.railway.app`**

This guide helps you configure Facebook Login, Google Login, and Gmail for your production environment.

---

## 📋 **PART 1: Update Railway Environment Variables**

### **Step 1: Go to Railway Dashboard**
1. Visit: https://railway.app
2. Open your OnlyFarms project
3. Click on your **backend service** (not the database)
4. Go to **"Variables"** tab

### **Step 2: Update/Add These Variables**

#### **✅ App URL (CRITICAL)**
```
APP_URL=https://onlyfarms-backend-production.up.railway.app
```
⚠️ **Remove any old `APP_URL` with `web-production-65250` first!**

#### **✅ Facebook OAuth**
```
FACEBOOK_CLIENT_ID=1132226609113086
FACEBOOK_CLIENT_SECRET=a8a071c694d1b2f361e3aba5439880b9
FACEBOOK_REDIRECT_URI=https://onlyfarms-backend-production.up.railway.app/api/auth/facebook/callback
```

#### **✅ Google OAuth**
```
GOOGLE_CLIENT_ID=47830452245-pl2sr09566uia5q9eampu7gqcq23jjak.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=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
GOOGLE_REDIRECT_URI=https://onlyfarms-backend-production.up.railway.app/api/auth/google/callback
```

#### **✅ Gmail (Email Sending)**
```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=onlyfarms@gmail.com
MAIL_PASSWORD=your-gmail-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=onlyfarms@gmail.com
MAIL_FROM_NAME=OnlyFarms
```

**After adding all variables, Railway will auto-redeploy (2-3 minutes).**

---

## 📘 **PART 2: Update Facebook App Settings**

### **Step 1: Open Facebook Developers Console**
1. Visit: https://developers.facebook.com/apps
2. Select your app: **"OnlyFarms"** (App ID: `1132226609113086`)

### **Step 2: Update Basic Settings**
1. Go to: **Settings → Basic**
2. Find **"App Domains"**
3. Add:
   ```
   onlyfarms-backend-production.up.railway.app
   ```
4. Click **"Save Changes"**

### **Step 3: Update Facebook Login Settings**
1. Go to: **Facebook Login → Settings** (in left sidebar)
2. Find **"Valid OAuth Redirect URIs"**
3. Add:
   ```
   https://onlyfarms-backend-production.up.railway.app/api/auth/facebook/callback
   ```
4. Click **"Save Changes"**

### **Step 4: Make App Public (If Needed)**
1. Go to: **Settings → Basic**
2. At the top, if you see **"App Mode: Development"**
3. Click **"Switch Mode"** to make it **"Live"**
4. Follow the prompts to complete the review checklist

---

## 🔴 **PART 3: Update Google OAuth Settings**

### **Step 1: Open Google Cloud Console**
1. Visit: https://console.cloud.google.com/apis/credentials
2. Select your project (or create one if needed)

### **Step 2: Update OAuth 2.0 Client**
1. Click on your **OAuth 2.0 Client ID**:
   - Client ID: `47830452245-pl2sr09566uia5q9eampu7gqcq23jjak`
2. Under **"Authorized redirect URIs"**, click **"+ ADD URI"**
3. Add:
   ```
   https://onlyfarms-backend-production.up.railway.app/api/auth/google/callback
   ```
4. Click **"Save"**

### **Step 3: Configure OAuth Consent Screen**
1. Go to: **OAuth consent screen** (in left sidebar)
2. Make sure the following are set:
   - **User type:** External
   - **App name:** OnlyFarms
   - **User support email:** Your email
   - **Developer contact:** Your email
3. Add **Scopes:**
   - `.../auth/userinfo.email`
   - `.../auth/userinfo.profile`
4. Click **"Save and Continue"**

---

## 📧 **PART 4: Configure Gmail for Sending Emails**

### **Step 1: Enable 2-Step Verification (If Not Already)**
1. Visit: https://myaccount.google.com/security
2. Find **"2-Step Verification"**
3. Click **"Get Started"** and follow the prompts

### **Step 2: Generate App Password**
1. Visit: https://myaccount.google.com/apppasswords
2. Select **"Mail"** as the app
3. Select **"Other (Custom name)"** as the device
4. Enter: **"OnlyFarms Railway"**
5. Click **"Generate"**
6. **Copy the 16-character password** (example: `abcd efgh ijkl mnop`)

### **Step 3: Update Railway Variable**
1. Go to Railway Dashboard → Your backend service → Variables
2. Find `MAIL_PASSWORD` variable
3. Update it with the **App Password** you just generated (remove spaces)
   ```
   MAIL_PASSWORD=abcdefghijklmnop
   ```
4. Save (Railway will redeploy)

---

## ✅ **PART 5: Test Everything**

### **After Railway Redeploys (~3 minutes), Test:**

#### **1. Test Facebook Login**
```
POST https://onlyfarms-backend-production.up.railway.app/api/auth/facebook/callback
```
Or use your mobile app's Facebook login button

#### **2. Test Google Login**
```
POST https://onlyfarms-backend-production.up.railway.app/api/auth/google/callback
```
Or use your mobile app's Google login button

#### **3. Test Email Sending**
Try signing up a new user or requesting a password reset - you should receive an email!

---

## 🔍 **Troubleshooting**

### **Facebook Login Issues:**
- ❌ **Error: "URL Blocked"**
  - Solution: Make sure you added the redirect URI in Facebook Developer Console

- ❌ **Error: "App Not Setup"**
  - Solution: Make sure your app is in "Live" mode, not "Development"

### **Google Login Issues:**
- ❌ **Error: "redirect_uri_mismatch"**
  - Solution: Double-check the redirect URI in Google Cloud Console matches exactly

- ❌ **Error: "Access Denied"**
  - Solution: Make sure OAuth consent screen is configured and published

### **Gmail Issues:**
- ❌ **Error: "Invalid credentials"**
  - Solution: Generate a new App Password and update `MAIL_PASSWORD` in Railway

- ❌ **Emails not sending**
  - Solution: Check Railway logs for email errors: `php artisan queue:work` might be needed

---

## 📝 **Summary Checklist**

- [ ] Updated `APP_URL` in Railway to `https://onlyfarms-backend-production.up.railway.app`
- [ ] Added all Facebook OAuth variables to Railway
- [ ] Added all Google OAuth variables to Railway
- [ ] Added all Gmail variables to Railway
- [ ] Updated Facebook App redirect URI
- [ ] Updated Google OAuth redirect URI
- [ ] Generated Gmail App Password
- [ ] Tested Facebook login
- [ ] Tested Google login
- [ ] Tested email sending

---

## 🎉 **You're Done!**

Your OnlyFarms backend is now fully configured for production with:
- ✅ Facebook Login working
- ✅ Google Login working
- ✅ Gmail email sending working

If you encounter any issues, check the Railway deployment logs for error messages.

