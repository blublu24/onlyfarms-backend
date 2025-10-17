# Quick Gmail Setup for Shopee-Style Verification

## What You Need (5 minutes):
1. Your Gmail account (jamessbatu@gmail.com)
2. Gmail App Password
3. Update .env file

## Step 1: Get Gmail App Password
1. Go to: https://myaccount.google.com/security
2. Enable "2-Step Verification"
3. Click "App passwords"
4. Select "Mail" → "Other" → "OnlyFarms"
5. Copy the 16-character password (like: abcd efgh ijkl mnop)

## Step 2: Update .env File
Add these lines to your .env file:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=jamessbatu@gmail.com
MAIL_PASSWORD=your-16-character-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=jamessbatu@gmail.com
MAIL_FROM_NAME="OnlyFarms"
```

## Step 3: Test
Run: `php test_send_to_any_gmail.php your-test-gmail@gmail.com`

## Result:
✅ Users sign up with ANY Gmail address
✅ They receive verification email in THEIR Gmail inbox
✅ They enter code to verify ownership
✅ Account created only after verification
✅ Same security as Shopee!

## User Experience:
1. User enters: john@gmail.com
2. User receives email in john@gmail.com inbox
3. User enters 6-digit code
4. Account verified and created
5. Secure like Shopee!
