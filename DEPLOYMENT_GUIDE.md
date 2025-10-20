# 🚀 ONLYFARMS BACKEND DEPLOYMENT GUIDE

## 📋 **PRE-DEPLOYMENT CHECKLIST**

### ✅ **Code Preparation (COMPLETED)**
- [x] All routes implemented and tested
- [x] Database migrations ready
- [x] OAuth configurations set
- [x] Laravel cache optimized
- [x] Environment variables configured
- [x] Security measures implemented

### ✅ **Production Requirements**
- [x] PHP 8.1+ (Laravel 10 compatible)
- [x] MySQL 8.0+ database
- [x] Composer for dependencies
- [x] SSL certificate (for HTTPS)
- [x] Domain name configured

---

## 🎯 **DEPLOYMENT STEPS**

### **Step 1: Choose Hosting Provider**

**Option A: Shared Hosting (Recommended for beginners)**
- **Hostinger:** $2.99/month - Laravel support
- **A2 Hosting:** $4.99/month - Laravel optimized
- **SiteGround:** $6.99/month - Excellent support

**Option B: VPS/Cloud (For advanced users)**
- **DigitalOcean:** $12/month - Full control
- **AWS EC2:** $10-50/month - Scalable
- **Linode:** $10/month - Simple VPS

### **Step 2: Upload Files to Server**

**Method 1: File Manager (cPanel)**
1. Login to your hosting control panel
2. Go to File Manager
3. Navigate to `public_html` folder
4. Upload all backend files (except `vendor` folder)
5. Extract the files

**Method 2: FTP/SFTP**
```bash
# Upload all files to server
# Make sure to exclude: vendor/, node_modules/, .git/
```

**Method 3: Git Deployment**
```bash
# If your hosting supports Git:
git clone your-repository
cd onlyfarmsbackend
composer install --optimize-autoloader --no-dev
```

### **Step 3: Configure Production Environment**

**Create `.env` file on server:**
```env
APP_NAME="OnlyFarms"
APP_ENV=production
APP_KEY=base64:YOUR_APP_KEY_HERE
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

FACEBOOK_CLIENT_ID=1132226609113086
FACEBOOK_CLIENT_SECRET=a8a071c694d1b2f361e3aba5439880b9
FACEBOOK_REDIRECT_URI=https://yourdomain.com/api/auth/facebook/callback

GOOGLE_CLIENT_ID=47830452245-pl2sr09566uia5q9eampu7gqcq23jjak.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=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
GOOGLE_REDIRECT_URI=https://yourdomain.com/api/auth/google/callback

MAIL_MAILER=smtp
MAIL_HOST=your_smtp_host
MAIL_PORT=587
MAIL_USERNAME=your_email
MAIL_PASSWORD=your_email_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="OnlyFarms"

FIREBASE_SERVER_KEY=your_firebase_server_key
```

### **Step 4: Install Dependencies**

**On your server, run:**
```bash
# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Generate application key
php artisan key:generate

# Clear and cache configurations
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### **Step 5: Setup Database**

**Create database and run migrations:**
```bash
# Run database migrations
php artisan migrate --force

# Seed initial data (if needed)
php artisan db:seed --force
```

### **Step 6: Configure Web Server**

**For Apache (most shared hosting):**
- Ensure `public` folder is your document root
- Enable mod_rewrite
- Set proper file permissions

**For Nginx:**
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/your/app/public;
    
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    
    index index.php;
    
    charset utf-8;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    
    error_page 404 /index.php;
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### **Step 7: Update OAuth Settings**

**Facebook App Settings:**
1. Go to https://developers.facebook.com/
2. Select your OnlyFarms app
3. Update **App Domains:** `yourdomain.com`
4. Update **Valid OAuth Redirect URIs:** `https://yourdomain.com/api/auth/facebook/callback`

**Google Cloud Console:**
1. Go to https://console.cloud.google.com/
2. Select your project
3. Go to APIs & Services > Credentials
4. Update **Authorized redirect URIs:** `https://yourdomain.com/api/auth/google/callback`

### **Step 8: Test Deployment**

**Test these endpoints:**
```bash
# Test basic API
curl https://yourdomain.com/api/login

# Test Facebook OAuth
curl https://yourdomain.com/api/auth/facebook/url

# Test Google OAuth
curl https://yourdomain.com/api/auth/google/url

# Test database connection
curl https://yourdomain.com/api/products
```

---

## 🔧 **TROUBLESHOOTING**

### **Common Issues:**

**1. 500 Internal Server Error**
- Check file permissions: `chmod -R 755 storage bootstrap/cache`
- Check Laravel logs: `storage/logs/laravel.log`
- Ensure `.env` file exists and is configured

**2. Database Connection Error**
- Verify database credentials in `.env`
- Ensure database exists and user has permissions
- Check if MySQL service is running

**3. OAuth Not Working**
- Verify HTTPS is enabled
- Check OAuth redirect URIs match exactly
- Ensure Facebook/Google app settings are updated

**4. File Upload Issues**
- Check `storage` folder permissions: `chmod -R 775 storage`
- Ensure `public/storage` symlink exists: `php artisan storage:link`

---

## 📊 **POST-DEPLOYMENT CHECKLIST**

### **✅ Verify These Features:**
- [ ] User registration works
- [ ] User login works
- [ ] Facebook login works
- [ ] Google login works
- [ ] Seller registration works
- [ ] Product creation works
- [ ] Order placement works
- [ ] Admin functions work
- [ ] Email notifications work
- [ ] SMS notifications work

### **✅ Performance Checks:**
- [ ] API response times < 2 seconds
- [ ] Database queries optimized
- [ ] Images load properly
- [ ] OAuth flows complete successfully

---

## 🎯 **NEXT STEPS AFTER DEPLOYMENT**

1. **Update Frontend API URLs** to point to production
2. **Test all features** in production environment
3. **Configure monitoring** and error tracking
4. **Set up backups** for database and files
5. **Go live** with your app! 🚀

---

**Your OnlyFarms backend is ready for deployment!** 

Choose your hosting provider and follow the steps above. Let me know which option you'd like to use, and I'll provide specific guidance for your chosen platform.
