# Facebook Email Restoration Guide

## When Facebook approves email permission:

### 1. Update AuthController.php:

```php
// In getFacebookLoginUrl() method:
'scope' => 'email,public_profile', // Restore email scope

// In handleFacebookCallback() method:
'fields' => 'id,name,email', // Restore email field

// In user creation logic:
'email' => $facebookUser['email'],
'email_verified_at' => now(),
```

### 2. Test the functionality:
- Clear cache: `php artisan config:clear && php artisan cache:clear`
- Test Facebook login with email

## Current Status:
- ✅ Facebook login works without email
- ✅ Users can sign up using Facebook ID and name
- ⏳ Email permission pending Facebook approval
