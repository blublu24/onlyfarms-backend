# Gmail SMTP Setup Guide for OnlyFarms

## Overview
This guide will help you configure Gmail SMTP to send verification emails for your OnlyFarms application.

## Step 1: Enable 2-Factor Authentication
1. Go to your Google Account settings: https://myaccount.google.com/
2. Click on "Security" in the left sidebar
3. Under "Signing in to Google", click "2-Step Verification"
4. Follow the prompts to enable 2-Factor Authentication

## Step 2: Generate App Password
1. In Google Account settings, go to "Security"
2. Under "Signing in to Google", click "App passwords"
3. Select "Mail" as the app
4. Select "Other" as the device and enter "OnlyFarms Backend"
5. Click "Generate"
6. Copy the 16-character password (it will look like: abcd efgh ijkl mnop)

## Step 3: Configure Your .env File
Add these lines to your `.env` file in the backend directory:

```env
# Gmail SMTP Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-gmail@gmail.com
MAIL_PASSWORD=your-16-character-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-gmail@gmail.com
MAIL_FROM_NAME="OnlyFarms"
```

## Step 4: Replace Placeholder Values
- Replace `your-gmail@gmail.com` with your actual Gmail address
- Replace `your-16-character-app-password` with the app password you generated
- Make sure to remove any spaces from the app password

## Step 5: Test the Configuration
1. Start your Laravel development server: `php artisan serve`
2. Try the email verification flow in your app
3. Check your Gmail inbox for the verification email

## Troubleshooting

### "Authentication failed" error
- Make sure you're using an App Password, not your regular Gmail password
- Verify that 2-Factor Authentication is enabled
- Check that the Gmail address is correct

### "Connection refused" error
- Make sure your internet connection is working
- Check if your firewall is blocking port 587
- Try using port 465 with SSL encryption instead of TLS

### Emails not being received
- Check your spam folder
- Verify the recipient email address is correct
- Make sure the sender email is verified in Gmail

## Alternative: Use a Different Email Service
If Gmail doesn't work for you, you can use other email services:

### Mailgun
```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=your-domain.mailgun.org
MAILGUN_SECRET=your-mailgun-secret
```

### SendGrid
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
MAIL_ENCRYPTION=tls
```

## Security Notes
- Never commit your `.env` file to version control
- Use App Passwords instead of your main Gmail password
- Consider using a dedicated email address for your application
- Regularly rotate your App Passwords

## Support
If you're still having issues, check the Laravel documentation on mail configuration:
https://laravel.com/docs/10.x/mail#configuration
