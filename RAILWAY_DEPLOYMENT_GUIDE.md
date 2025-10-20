# 🚀 ONLYFARMS RAILWAY DEPLOYMENT GUIDE

## 🎯 **RAILWAY DEPLOYMENT STEPS**

### **Step 1: Create Railway Account**
1. Go to [railway.app](https://railway.app)
2. Sign up with GitHub (recommended)
3. Connect your GitHub account

### **Step 2: Prepare Your Repository**
1. **Create a new GitHub repository** called `onlyfarms-backend`
2. **Upload your backend code** to the repository
3. **Make sure these files are included:**
   - `railway.json` ✅
   - `Procfile` ✅
   - `composer.json` ✅
   - All Laravel files ✅

### **Step 3: Deploy to Railway**
1. **Go to Railway Dashboard**
2. **Click "New Project"**
3. **Select "Deploy from GitHub repo"**
4. **Choose your `onlyfarms-backend` repository**
5. **Railway will automatically detect it's a Laravel app**

### **Step 4: Configure Database**
1. **In your Railway project, click "New"**
2. **Select "Database" → "MySQL"**
3. **Railway will create a MySQL database automatically**
4. **Copy the database connection details**

### **Step 5: Set Environment Variables**
In Railway dashboard, go to your project → Variables tab:

```env
# App Configuration
APP_NAME=OnlyFarms
APP_ENV=production
APP_DEBUG=false
APP_URL=https://onlyfarms-production.railway.app

# Database (Railway will provide these automatically)
DB_CONNECTION=mysql
DB_HOST=${{MYSQL_HOST}}
DB_PORT=${{MYSQL_PORT}}
DB_DATABASE=${{MYSQL_DATABASE}}
DB_USERNAME=${{MYSQL_USER}}
DB_PASSWORD=${{MYSQL_PASSWORD}}

# OAuth Configuration
FACEBOOK_CLIENT_ID=1132226609113086
FACEBOOK_CLIENT_SECRET=a8a071c694d1b2f361e3aba5439880b9
FACEBOOK_REDIRECT_URI=https://onlyfarms-production.railway.app/api/auth/facebook/callback

GOOGLE_CLIENT_ID=47830452245-pl2sr09566uia5q9eampu7gqcq23jjak.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=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
GOOGLE_REDIRECT_URI=https://onlyfarms-production.railway.app/api/auth/google/callback

# Email Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@onlyfarms.com
MAIL_FROM_NAME=OnlyFarms

# SMS Configuration
FIREBASE_SERVER_KEY=your_firebase_server_key
```

### **Step 6: Deploy and Run Migrations**
1. **Railway will automatically deploy your app**
2. **Go to your project → Deployments**
3. **Click on the latest deployment**
4. **Go to "Logs" tab to see the deployment progress**
5. **Once deployed, run migrations:**
   ```bash
   # In Railway console or via CLI
   php artisan migrate --force
   ```

### **Step 7: Update OAuth Settings**

#### **Facebook App Settings:**
1. Go to [developers.facebook.com](https://developers.facebook.com)
2. Select your OnlyFarms app
3. **Update App Domains:** `onlyfarms-production.railway.app`
4. **Update Valid OAuth Redirect URIs:** `https://onlyfarms-production.railway.app/api/auth/facebook/callback`

#### **Google Cloud Console:**
1. Go to [console.cloud.google.com](https://console.cloud.google.com)
2. Select your project
3. Go to APIs & Services → Credentials
4. **Update Authorized redirect URIs:** `https://onlyfarms-production.railway.app/api/auth/google/callback`

### **Step 8: Test Your Deployment**
Test these endpoints:
```bash
# Basic API test
curl https://onlyfarms-production.railway.app/api/products

# Facebook OAuth test
curl https://onlyfarms-production.railway.app/api/auth/facebook/url

# Google OAuth test
curl https://onlyfarms-production.railway.app/api/auth/google/url
```

---

## 🔧 **RAILWAY-SPECIFIC CONFIGURATIONS**

### **Automatic HTTPS:**
- ✅ Railway provides free SSL certificates
- ✅ Your app will be available at `https://onlyfarms-production.railway.app`
- ✅ Perfect for OAuth (Facebook/Google require HTTPS)

### **Database:**
- ✅ Railway provides managed MySQL database
- ✅ Automatic backups included
- ✅ Connection details provided automatically

### **Scaling:**
- ✅ Railway auto-scales based on traffic
- ✅ Free tier: 500 hours/month
- ✅ Paid tier: $5/month for unlimited usage

### **Custom Domain (Optional):**
1. **In Railway dashboard, go to Settings**
2. **Click "Custom Domain"**
3. **Add your domain (e.g., api.onlyfarms.com)**
4. **Update DNS records as instructed**

---

## 📊 **COST BREAKDOWN**

### **Free Tier:**
- ✅ 500 hours/month (enough for development)
- ✅ 1GB RAM
- ✅ 1GB disk space
- ✅ MySQL database included

### **Pro Tier ($5/month):**
- ✅ Unlimited usage
- ✅ 8GB RAM
- ✅ 100GB disk space
- ✅ Custom domains
- ✅ Priority support

---

## 🎯 **DEPLOYMENT CHECKLIST**

### **Pre-Deployment:**
- [x] Railway account created
- [x] GitHub repository created
- [x] Code uploaded to GitHub
- [x] Railway configuration files added
- [x] Environment variables prepared

### **Deployment:**
- [ ] Railway project created
- [ ] Database added
- [ ] Environment variables set
- [ ] App deployed
- [ ] Migrations run
- [ ] OAuth settings updated

### **Post-Deployment:**
- [ ] API endpoints tested
- [ ] OAuth flows tested
- [ ] Database connection verified
- [ ] Frontend API URLs updated

---

## 🚀 **NEXT STEPS**

1. **Create GitHub repository** and upload your code
2. **Deploy to Railway** following the steps above
3. **Update your frontend** to use the new Railway URL
4. **Test everything** in production
5. **Go live!** 🎉

---

**Your OnlyFarms backend will be live at:**
**`https://onlyfarms-production.railway.app`**

This URL will work perfectly with your mobile app! 🚀
