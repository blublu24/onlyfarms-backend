# Gmail Sender Setup for OnlyFarms

## Overview
This guide shows how to configure your Gmail account to send verification emails to any Gmail address through your OnlyFarms app.

## How It Works
- **Your Gmail** (jamessbatu@gmail.com) = Sender account
- **Any Gmail address** = Recipient (user's email)
- **Gmail SMTP** = Sends emails from your account to any recipient

## Setup Steps

### Step 1: Enable 2-Factor Authentication
1. Go to https://myaccount.google.com/security
2. Under "Signing in to Google", click "2-Step Verification"
3. Follow prompts to enable 2FA

### Step 2: Generate App Password
1. In Google Account settings, go to "Security"
2. Under "Signing in to Google", click "App passwords"
3. Select "Mail" as the app
4. Select "Other" and enter "OnlyFarms Backend"
5. Click "Generate"
6. **Copy the 16-character password** (looks like: `abcd efgh ijkl mnop`)

### Step 3: Configure .env File
Add these lines to your `.env` file:

```env
# Gmail SMTP Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=jamessbatu@gmail.com
MAIL_PASSWORD=your-16-character-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=jamessbatu@gmail.com
MAIL_FROM_NAME="OnlyFarms"
```

**Replace `your-16-character-app-password` with the app password from Step 2.**

### Step 4: Test Email Sending
Run the test script:
```bash
php test_gmail.php recipient@gmail.com
```

## How It Works in Your App

### User Signup Flow:
1. **User enters**: `user@gmail.com` (any Gmail address)
2. **Your app sends email**: From `jamessbatu@gmail.com` → To `user@gmail.com`
3. **User receives email**: In their Gmail inbox from "OnlyFarms"
4. **User verifies**: Enters code and completes registration

### Email Details:
- **From**: jamessbatu@gmail.com (OnlyFarms)
- **To**: user@gmail.com (any Gmail address)
- **Subject**: OnlyFarms - Email Verification Code
- **Content**: Beautiful HTML email with 6-digit code

## Security Notes
- Your Gmail account is the sender (like a business email)
- Recipients see emails from "OnlyFarms"
- Gmail handles all the delivery
- No need for recipient to configure anything

## Troubleshooting

### "Authentication failed" error:
- Use App Password, not your regular Gmail password
- Make sure 2FA is enabled
- Check Gmail address spelling

### "Connection refused" error:
- Check internet connection
- Verify firewall settings
- Try port 465 with SSL

### Emails not received:
- Check recipient's spam folder
- Verify recipient email is correct
- Check Gmail sending limits

## Production Considerations
- Gmail has daily sending limits (500 emails/day for free accounts)
- For production, consider Gmail Workspace or other email services
- Monitor email delivery rates
- Implement email templates and tracking

## Testing
1. Configure your Gmail credentials
2. Test with your own Gmail address first
3. Test with different Gmail addresses
4. Check spam folders
5. Verify email formatting and content
