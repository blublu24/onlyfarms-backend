# ⚡ QUICK FIX: Update OAuth for Production

## 🎯 **YOUR MISSION: 3 Simple Tasks**

Your backend is live at: **`https://onlyfarms-backend-production.up.railway.app`**

But OAuth is pointing to the old URL. Let's fix it!

---

## ✅ **TASK 1: Update Railway Variables (5 minutes)**

### **Go Here:** https://railway.app
1. Open your **OnlyFarms project**
2. Click **backend service** (not MySQL)
3. Click **"Variables"** tab

### **Update These 4 Variables:**

#### **1. APP_URL** ⚠️ **MOST IMPORTANT**
```
APP_URL=https://onlyfarms-backend-production.up.railway.app
```

#### **2. FACEBOOK_REDIRECT_URI**
```
FACEBOOK_REDIRECT_URI=https://onlyfarms-backend-production.up.railway.app/api/auth/facebook/callback
```

#### **3. GOOGLE_REDIRECT_URI**
```
GOOGLE_REDIRECT_URI=https://onlyfarms-backend-production.up.railway.app/api/auth/google/callback
```

#### **4. MAIL_FROM_ADDRESS** (if not set)
```
MAIL_FROM_ADDRESS=onlyfarms@gmail.com
MAIL_USERNAME=onlyfarms@gmail.com
```

**After saving, Railway will auto-redeploy (wait 2-3 minutes)**

---

## 📘 **TASK 2: Update Facebook (2 minutes)**

### **Go Here:** https://developers.facebook.com/apps
1. Select your **OnlyFarms app** (ID: 1132226609113086)
2. Go to **Facebook Login → Settings**
3. Find **"Valid OAuth Redirect URIs"**
4. Add this URL:
   ```
   https://onlyfarms-backend-production.up.railway.app/api/auth/facebook/callback
   ```
5. **Save Changes**

---

## 🔴 **TASK 3: Update Google (2 minutes)**

### **Go Here:** https://console.cloud.google.com/apis/credentials
1. Click your **OAuth 2.0 Client ID** (ID: 47830452245...)
2. Under **"Authorized redirect URIs"**, click **"+ ADD URI"**
3. Add this URL:
   ```
   https://onlyfarms-backend-production.up.railway.app/api/auth/google/callback
   ```
4. **Save**

---

## 🎉 **YOU'RE DONE!**

### **Test Your OAuth:**

After Railway redeploys (~3 minutes), test:

1. **Facebook Login:** Use your app's Facebook login button
2. **Google Login:** Use your app's Google login button

### **If Something Doesn't Work:**

Check Railway logs:
1. Go to Railway Dashboard
2. Click your backend service
3. Click **"Deployments"**
4. Click latest deployment
5. Check **"Deploy Logs"** for errors

---

## 📧 **BONUS: Setup Gmail (Optional, 5 minutes)**

If you want email notifications to work:

### **Step 1: Generate Gmail App Password**
1. Visit: https://myaccount.google.com/apppasswords
2. Select **"Mail"** and **"Other"**
3. Name it: **"OnlyFarms"**
4. Click **"Generate"**
5. **Copy the 16-character password**

### **Step 2: Add to Railway**
1. Go to Railway → Variables
2. Add:
   ```
   MAIL_PASSWORD=your-16-char-password-here
   ```
3. Save (Railway will redeploy)

---

## 🔍 **Quick Troubleshooting**

### **Facebook: "URL Blocked" Error**
- Make sure you added the redirect URI in Facebook Developer Console
- Make sure there's no typo in the URL

### **Google: "redirect_uri_mismatch" Error**
- Double-check the URL in Google Cloud Console matches exactly
- Make sure you clicked "Save" after adding the URI

### **Emails Not Sending**
- Generate a new Gmail App Password
- Update `MAIL_PASSWORD` in Railway variables
- Make sure 2-Step Verification is enabled on your Gmail account

---

## 📋 **Checklist**

- [ ] Task 1: Updated Railway variables (APP_URL, FACEBOOK_REDIRECT_URI, GOOGLE_REDIRECT_URI)
- [ ] Task 2: Updated Facebook redirect URI
- [ ] Task 3: Updated Google redirect URI
- [ ] Tested Facebook login
- [ ] Tested Google login
- [ ] (Optional) Setup Gmail for emails

**Total Time: ~10 minutes**

---

**Need help?** Check the full guide: `OAUTH_SETUP_FOR_PRODUCTION.md`

