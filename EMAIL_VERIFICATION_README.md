# OnlyFarms Email Verification System

## Overview
This system implements Gmail-based email verification for user registration, similar to popular apps like Instagram, Facebook, etc. Users receive a 6-digit verification code via email to complete their registration.

## How It Works

### 1. User Flow
1. User enters email address in signup form
2. System sends 6-digit verification code to their Gmail
3. User receives email with verification code
4. User enters code in the app
5. System verifies code and completes registration
6. User is automatically logged in

### 2. Technical Flow
1. **Send Code**: `POST /api/send-verification-code`
   - Generates 6-digit code
   - Stores code in database with 10-minute expiry
   - Sends email via Gmail SMTP
   - Returns code for development/testing

2. **Verify Code**: `POST /api/verify-email`
   - Validates 6-digit code
   - Checks expiry time
   - Creates user account
   - Returns authentication token

3. **Resend Code**: `POST /api/resend-verification-code`
   - Generates new code
   - Updates database record
   - Sends new email

## Files Created/Modified

### Backend Files
- `app/Http/Controllers/EmailVerificationController.php` - Main controller
- `app/Mail/VerificationCodeMail.php` - Email mailable class
- `resources/views/emails/verification-code.blade.php` - Email template
- `database/migrations/2025_10_15_093039_create_email_verifications_table.php` - Database table
- `routes/api.php` - Added email verification routes
- `config/mail.php` - Updated mail configuration

### Frontend Files
- `app/(tabs)/email-verification.tsx` - Already exists and working

### Configuration Files
- `GMAIL_SETUP_GUIDE.md` - Step-by-step Gmail setup guide
- `test_email.php` - Email testing script

## Database Schema

### email_verifications table
```sql
CREATE TABLE email_verifications (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    verification_code VARCHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_email_code (email, verification_code),
    INDEX idx_expires_at (expires_at)
);
```

## API Endpoints

### Send Verification Code
```
POST /api/send-verification-code
Content-Type: application/json

{
    "email": "user@example.com"
}

Response:
{
    "message": "Verification code sent successfully!",
    "verification_code": "123456",
    "gmail_url": "https://mail.google.com/mail/?view=cm&to=..."
}
```

### Verify Email
```
POST /api/verify-email
Content-Type: application/json

{
    "user_id": 1,
    "verification_code": "123456",
    "name": "John Doe",
    "password": "password123",
    "password_confirmation": "password123"
}

Response:
{
    "message": "Email verified successfully!",
    "user": { ... },
    "token": "1|abc123..."
}
```

### Resend Verification Code
```
POST /api/resend-verification-code
Content-Type: application/json

{
    "user_id": 1
}

Response:
{
    "message": "New verification code sent successfully!",
    "verification_code": "789012"
}
```

## Setup Instructions

### 1. Configure Gmail SMTP
Follow the detailed guide in `GMAIL_SETUP_GUIDE.md`:

1. Enable 2-Factor Authentication on Gmail
2. Generate App Password
3. Update `.env` file with credentials

### 2. Run Database Migration
```bash
php artisan migrate
```

### 3. Test Email Functionality
```bash
php test_email.php
```

### 4. Test in App
1. Start your development server
2. Try the signup flow
3. Check Gmail for verification email

## Security Features

### Rate Limiting
- Send code: 5 requests per minute
- Verify code: 10 requests per minute  
- Resend code: 3 requests per minute

### Code Security
- 6-digit numeric codes
- 10-minute expiry
- One-time use
- Automatic cleanup

### Email Security
- Uses Gmail's secure SMTP
- App passwords (not main password)
- TLS encryption

## Troubleshooting

### Common Issues

1. **"Authentication failed"**
   - Use App Password, not regular Gmail password
   - Enable 2-Factor Authentication
   - Check Gmail address spelling

2. **"Connection refused"**
   - Check internet connection
   - Verify firewall settings
   - Try port 465 with SSL

3. **Emails not received**
   - Check spam folder
   - Verify recipient email
   - Check sender email verification

4. **Database errors**
   - Run migrations: `php artisan migrate`
   - Check database connection
   - Verify table exists

### Debug Mode
The system includes fallback mechanisms:
- Returns verification code in API response for development
- Generates Gmail URL for easy access
- Shows code in app interface as backup

## Production Considerations

### Email Service
For production, consider using:
- **Mailgun** - Reliable, good deliverability
- **SendGrid** - Popular, good analytics
- **Amazon SES** - Cost-effective, scalable

### Security
- Use environment variables for credentials
- Enable HTTPS
- Implement proper logging
- Monitor failed attempts

### Performance
- Use queue system for email sending
- Implement email templates caching
- Add database indexes for performance

## Support

If you encounter issues:
1. Check the Gmail setup guide
2. Run the test script
3. Check Laravel logs: `storage/logs/laravel.log`
4. Verify environment configuration

## Future Enhancements

Potential improvements:
- SMS verification as backup
- Voice call verification
- Multiple email providers
- Email templates customization
- Analytics and reporting
- A/B testing for email content
