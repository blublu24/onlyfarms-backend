# Social Login Setup Guide for OnlyFarms

## Overview
This guide shows how to set up Google and Facebook login for your OnlyFarms app, just like Shopee, Instagram, and other popular apps.

## 🎯 **What You'll Get:**

### **Three Signup Options:**
1. **Manual Gmail Signup** - User enters Gmail + verification code
2. **Google Login** - One-click Google account login
3. **Facebook Login** - One-click Facebook account login

## 🔧 **Backend Setup (Already Done):**

✅ **Laravel Socialite** - Installed
✅ **SocialLoginController** - Created
✅ **API Routes** - Added
✅ **User Model** - Updated for social fields

## 🚀 **Frontend Setup (React Native):**

### **Step 1: Install React Native Social Login Packages**

```bash
# For Google Login
npm install @react-native-google-signin/google-signin

# For Facebook Login  
npm install react-native-fbsdk-next
```

### **Step 2: Add Social Login Buttons to Signup Screen**

Update your `signup.tsx` to include social login options:

```typescript
// Add these imports
import { GoogleSignin } from '@react-native-google-signin/google-signin';
import { LoginManager, AccessToken } from 'react-native-fbsdk-next';

// Add social login functions
const handleGoogleLogin = async () => {
  try {
    await GoogleSignin.hasPlayServices();
    const userInfo = await GoogleSignin.signIn();
    
    // Send to your backend
    const response = await api.post('/auth/google/mobile', {
      id_token: userInfo.idToken,
      email: userInfo.user.email,
      name: userInfo.user.name,
      profile_image: userInfo.user.photo,
    });
    
    // Handle successful login
    const { token, user } = response.data;
    await setAuthToken(token);
    setUser(user);
    router.replace('/homepage');
    
  } catch (error) {
    console.log('Google login error:', error);
  }
};

const handleFacebookLogin = async () => {
  try {
    const result = await LoginManager.logInWithPermissions(['public_profile', 'email']);
    
    if (result.isCancelled) {
      return;
    }
    
    const data = await AccessToken.getCurrentAccessToken();
    
    if (data) {
      // Send to your backend
      const response = await api.post('/auth/facebook/mobile', {
        facebook_id: data.userID,
        email: 'user@example.com', // You'll need to get this from Facebook Graph API
        name: 'User Name', // You'll need to get this from Facebook Graph API
        profile_image: 'profile_url', // You'll need to get this from Facebook Graph API
      });
      
      // Handle successful login
      const { token, user } = response.data;
      await setAuthToken(token);
      setUser(user);
      router.replace('/homepage');
    }
    
  } catch (error) {
    console.log('Facebook login error:', error);
  }
};
```

### **Step 3: Update Signup UI**

Add social login buttons to your signup screen:

```typescript
// Add after your regular signup form
<View style={styles.socialLoginContainer}>
  <Text style={styles.socialLoginText}>Or sign up with</Text>
  
  <View style={styles.socialButtons}>
    <TouchableOpacity
      style={styles.googleButton}
      onPress={handleGoogleLogin}
    >
      <Ionicons name="logo-google" size={20} color="white" />
      <Text style={styles.socialButtonText}>Google</Text>
    </TouchableOpacity>
    
    <TouchableOpacity
      style={styles.facebookButton}
      onPress={handleFacebookLogin}
    >
      <Ionicons name="logo-facebook" size={20} color="white" />
      <Text style={styles.socialButtonText}>Facebook</Text>
    </TouchableOpacity>
  </View>
</View>
```

## 🔑 **API Configuration Required:**

### **Google OAuth Setup:**
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create/select project
3. Enable Google+ API
4. Create OAuth 2.0 credentials
5. Add your app's package name and SHA-1 fingerprint

### **Facebook App Setup:**
1. Go to [Facebook Developers](https://developers.facebook.com/)
2. Create new app
3. Add Facebook Login product
4. Configure OAuth redirect URIs
5. Get App ID and App Secret

## 📱 **User Experience:**

### **Option 1: Manual Gmail Signup**
1. User enters Gmail address
2. Receives verification email
3. Enters 6-digit code
4. Account created

### **Option 2: Google Login**
1. User taps "Sign in with Google"
2. Google login screen appears
3. User logs into Google account
4. Account created automatically

### **Option 3: Facebook Login**
1. User taps "Sign in with Facebook"
2. Facebook login screen appears
3. User logs into Facebook account
4. Account created automatically

## 🎉 **Benefits:**

- **Faster signup** - No email verification needed for social login
- **Better UX** - One-click registration
- **Higher conversion** - Like Shopee, Instagram, etc.
- **Secure** - OAuth 2.0 authentication
- **Professional** - Industry standard

## 🔧 **Configuration Files:**

### **Update config/services.php:**
```php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT_URI'),
],

'facebook' => [
    'client_id' => env('FACEBOOK_CLIENT_ID'),
    'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
    'redirect' => env('FACEBOOK_REDIRECT_URI'),
],
```

### **Update .env file:**
```env
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback

FACEBOOK_CLIENT_ID=your-facebook-app-id
FACEBOOK_CLIENT_SECRET=your-facebook-app-secret
FACEBOOK_REDIRECT_URI=http://localhost:8000/api/auth/facebook/callback
```

## 🚀 **Ready to Use:**

Your backend is already set up with:
- ✅ Google OAuth integration
- ✅ Facebook OAuth integration  
- ✅ Mobile social login endpoints
- ✅ Automatic user creation
- ✅ Token generation

Just add the frontend buttons and configure the OAuth apps!

## 📞 **Next Steps:**

1. **Set up Google OAuth** in Google Cloud Console
2. **Set up Facebook App** in Facebook Developers
3. **Install React Native packages**
4. **Add social login buttons** to your signup screen
5. **Test with different accounts**

Your app will then have **three signup options** just like Shopee! 🎉
