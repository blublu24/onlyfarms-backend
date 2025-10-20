# Gmail API Setup Guide for OnlyFarms

## Overview
This guide will help you set up Gmail API integration for seamless email verification. With Gmail API, your app can:
- Send verification emails directly through Gmail
- Automatically read verification emails from user's Gmail
- Auto-extract verification codes
- Provide a seamless verification experience

## 🚀 **Gmail API vs SMTP Comparison**

### **SMTP Approach (Current)**
- ✅ Simple setup
- ✅ Works with any email provider
- ❌ User must manually enter code
- ❌ No automatic verification

### **Gmail API Approach (New)**
- ✅ Automatic code extraction
- ✅ Seamless user experience
- ✅ Direct Gmail integration
- ❌ Requires Google Cloud setup
- ❌ More complex implementation

## 📋 **Prerequisites**

1. **Google Cloud Console Account**
2. **Gmail account** for sending emails
3. **Laravel application** (already set up)

## 🔧 **Step 1: Google Cloud Console Setup**

### 1.1 Create Google Cloud Project
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Click "Select a project" → "New Project"
3. Enter project name: `OnlyFarms Email Verification`
4. Click "Create"

### 1.2 Enable Gmail API
1. In your project, go to "APIs & Services" → "Library"
2. Search for "Gmail API"
3. Click on "Gmail API" → "Enable"

### 1.3 Create OAuth2 Credentials
1. Go to "APIs & Services" → "Credentials"
2. Click "Create Credentials" → "OAuth client ID"
3. Choose "Web application"
4. Add authorized redirect URIs:
   - `http://localhost:8000/api/gmail/callback` (development)
   - `https://yourdomain.com/api/gmail/callback` (production)
5. Click "Create"
6. Download the JSON file

### 1.4 Configure OAuth Consent Screen
1. Go to "APIs & Services" → "OAuth consent screen"
2. Choose "External" user type
3. Fill in required fields:
   - App name: `OnlyFarms`
   - User support email: Your email
   - Developer contact: Your email
4. Add scopes:
   - `https://www.googleapis.com/auth/gmail.readonly`
   - `https://www.googleapis.com/auth/gmail.send`
5. Add test users (your Gmail accounts)

## 🔑 **Step 2: Configure Laravel Application**

### 2.1 Add Environment Variables
Add these to your `.env` file:

```env
# Gmail API Configuration
GOOGLE_CLIENT_ID=your-client-id.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/gmail/callback
GOOGLE_CREDENTIALS_PATH=storage/app/google-credentials.json
```

### 2.2 Store Credentials File
1. Place the downloaded JSON file in `storage/app/`
2. Rename it to `google-credentials.json`
3. Update file permissions: `chmod 644 storage/app/google-credentials.json`

### 2.3 Install Dependencies
```bash
composer require google/apiclient
```

## 🎯 **Step 3: API Endpoints**

### **Gmail API Endpoints Available:**

#### 1. Get Gmail Authorization URL
```http
POST /api/gmail/auth-url
Content-Type: application/json

{
    "email": "user@example.com"
}

Response:
{
    "message": "Gmail authorization URL generated",
    "auth_url": "https://accounts.google.com/oauth/authorize?...",
    "email": "user@example.com"
}
```

#### 2. Handle Gmail OAuth Callback
```http
POST /api/gmail/callback
Content-Type: application/json

{
    "code": "authorization_code_from_google",
    "email": "user@example.com"
}
```

#### 3. Send Verification Email via Gmail API
```http
POST /api/gmail/send-verification
Content-Type: application/json

{
    "email": "user@example.com"
}
```

#### 4. Auto-Verify Email (Read from Gmail)
```http
POST /api/gmail/auto-verify
Content-Type: application/json

{
    "email": "user@example.com"
}
```

#### 5. Complete Verification with Auto-Detection
```http
POST /api/gmail/complete-verification
Content-Type: application/json

{
    "email": "user@example.com",
    "name": "John Doe",
    "password": "password123",
    "password_confirmation": "password123"
}
```

## 🔄 **User Flow with Gmail API**

### **Option 1: Automatic Verification**
1. User enters email in signup
2. App requests Gmail authorization
3. User grants permission to read Gmail
4. App sends verification email via Gmail API
5. App automatically reads Gmail to find verification code
6. App auto-verifies and creates account
7. User is logged in seamlessly

### **Option 2: Manual Verification (Fallback)**
1. User enters email in signup
2. App sends verification email via Gmail API
3. User manually enters code from Gmail
4. App verifies and creates account

## 🛠️ **Frontend Integration**

### **React Native Implementation:**
```javascript
// Get Gmail authorization URL
const getGmailAuthUrl = async (email) => {
  const response = await api.post('/gmail/auth-url', { email });
  return response.data.auth_url;
};

// Open Gmail authorization in browser
const authorizeGmail = async (email) => {
  const authUrl = await getGmailAuthUrl(email);
  await Linking.openURL(authUrl);
};

// Auto-verify email
const autoVerifyEmail = async (email) => {
  const response = await api.post('/gmail/auto-verify', { email });
  return response.data;
};

// Complete verification with auto-detection
const completeVerification = async (userData) => {
  const response = await api.post('/gmail/complete-verification', userData);
  return response.data;
};
```

## 🔒 **Security Features**

### **OAuth2 Security:**
- Secure token exchange
- Limited scope access (read only + send)
- Token expiration handling
- User consent required

### **Rate Limiting:**
- 5 requests/minute for auth URL
- 10 requests/minute for verification
- 5 requests/minute for completion

### **Data Protection:**
- Tokens stored securely
- No permanent Gmail access
- User can revoke access anytime

## 🧪 **Testing**

### **Test the Gmail API:**
```bash
# Test Gmail API service
php artisan tinker

# In tinker:
$gmailApi = app(\App\Services\GmailApiService::class);
$result = $gmailApi->sendVerificationEmail('test@example.com', '123456');
dd($result);
```

### **Test OAuth Flow:**
1. Start Laravel server: `php artisan serve`
2. Test auth URL endpoint
3. Complete OAuth flow in browser
4. Test auto-verification

## 🚨 **Troubleshooting**

### **Common Issues:**

#### 1. "Invalid client" error
- Check GOOGLE_CLIENT_ID in .env
- Verify OAuth consent screen is configured
- Ensure redirect URI matches exactly

#### 2. "Access denied" error
- Check OAuth consent screen status
- Add test users if in testing mode
- Verify scopes are correctly configured

#### 3. "Quota exceeded" error
- Gmail API has daily quotas
- Check Google Cloud Console quotas
- Consider implementing caching

#### 4. "Token expired" error
- Implement token refresh logic
- Store refresh tokens securely
- Handle token expiration gracefully

## 📊 **Production Considerations**

### **Scaling:**
- Implement token caching
- Use Redis for token storage
- Monitor API quotas
- Implement fallback to SMTP

### **Monitoring:**
- Log Gmail API calls
- Monitor success rates
- Track user experience metrics
- Set up alerts for failures

### **Backup Plan:**
- Keep SMTP as fallback
- Implement manual code entry
- Graceful degradation
- User-friendly error messages

## 🎉 **Benefits of Gmail API**

1. **Seamless Experience**: Users don't need to manually enter codes
2. **Higher Success Rate**: Automatic verification reduces user friction
3. **Professional Feel**: Direct Gmail integration feels native
4. **Reduced Support**: Fewer "didn't receive email" issues
5. **Better Analytics**: Track email delivery and verification rates

## 🔄 **Migration from SMTP**

Your existing SMTP system will continue to work as a fallback. The Gmail API system provides an enhanced experience while maintaining compatibility.

## 📞 **Support**

If you encounter issues:
1. Check Google Cloud Console logs
2. Review Laravel logs: `storage/logs/laravel.log`
3. Test OAuth flow step by step
4. Verify credentials and permissions

## 🚀 **Next Steps**

1. Set up Google Cloud project
2. Configure OAuth credentials
3. Update environment variables
4. Test the Gmail API endpoints
5. Integrate with your frontend
6. Deploy and monitor

The Gmail API integration provides a premium user experience while maintaining the security and reliability of your email verification system!
