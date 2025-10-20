# Google OAuth Setup Guide for OnlyFarms

## 📋 **Overview**
This guide will help you set up Google OAuth login for your OnlyFarms app, allowing users to sign up and login using their Gmail accounts.

## 🔧 **Step 1: Create Google Cloud Project**

1. **Go to Google Cloud Console**
   - Visit: https://console.cloud.google.com/

2. **Create a New Project**
   - Click "Select a project" → "New Project"
   - Project name: `OnlyFarms`
   - Click "Create"

3. **Enable Google+ API**
   - Go to "APIs & Services" → "Library"
   - Search for "Google+ API" and enable it
   - Also enable "Google OAuth2 API"

## 🔑 **Step 2: Create OAuth 2.0 Credentials**

1. **Go to Credentials**
   - Navigate to "APIs & Services" → "Credentials"
   - Click "Create Credentials" → "OAuth client ID"

2. **Configure OAuth Consent Screen**
   - If prompted, configure the OAuth consent screen:
     - User Type: "External"
     - App name: "OnlyFarms"
     - User support email: Your email
     - Developer contact: Your email
     - Add scopes: `email`, `profile`, `openid`

3. **Create OAuth Client ID**
   - Application type: "Web application"
   - Name: "OnlyFarms Web Client"
   - Authorized redirect URIs:
     ```
     http://192.168.0.147:8000/api/auth/google/callback
     ```
   - Click "Create"

4. **Get Your Credentials**
   - Copy the **Client ID** and **Client Secret**

## ⚙️ **Step 3: Update Your .env File**

Add these lines to your `.env` file:

```env
# Google OAuth Configuration
GOOGLE_CLIENT_ID=your_google_client_id_here
GOOGLE_CLIENT_SECRET=your_google_client_secret_here
GOOGLE_REDIRECT_URI=http://192.168.0.147:8000/api/auth/google/callback
```

**Replace:**
- `your_google_client_id_here` with your actual Client ID
- `your_google_client_secret_here` with your actual Client Secret

## 🔄 **Step 4: Clear Laravel Cache**

Run these commands in your backend directory:

```bash
php artisan config:clear
php artisan cache:clear
```

## ✅ **Step 5: Test the Integration**

1. **Start your backend server:**
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```

2. **Test the Google login flow:**
   - Open your mobile app
   - Go to the signup page
   - Tap "Continue with Google"
   - Complete the Google OAuth flow

## 🚨 **Important Notes**

### **Redirect URI Configuration**
- Make sure your redirect URI in Google Console matches your backend URL
- If your IP address changes, update both the Google Console and your `.env` file
- For production, use your actual domain instead of IP address

### **Security Considerations**
- Keep your Client Secret secure and never expose it in frontend code
- The OAuth flow happens entirely on the backend for security
- Google automatically verifies email addresses for OAuth users

### **User Experience**
- Users can now sign up using their Gmail accounts
- No need for email verification since Google already verified them
- Users can link their existing email accounts to Google accounts
- Profile pictures from Google are automatically imported

## 🔧 **Troubleshooting**

### **"Invalid redirect URI" Error**
- Check that the redirect URI in Google Console exactly matches your `.env` file
- Ensure there are no extra spaces or characters

### **"Client ID not found" Error**
- Verify your Client ID is correct in the `.env` file
- Make sure you've cleared the Laravel cache

### **"Access denied" Error**
- Check that the OAuth consent screen is properly configured
- Ensure the required scopes are added

## 📱 **Mobile App Integration**

The mobile app now includes:
- Google login button on the signup page
- WebView for secure OAuth flow
- Automatic account creation/linking
- Profile picture import from Google

## 🎉 **Success!**

Once configured, users can:
1. Tap "Continue with Google" on the signup page
2. Complete Google authentication in the WebView
3. Automatically get signed up/logged in to OnlyFarms
4. Have their Google profile picture imported

Your OnlyFarms app now supports Gmail-based authentication! 🚀
