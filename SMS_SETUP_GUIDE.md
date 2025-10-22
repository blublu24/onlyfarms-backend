# SMS Setup Guide for OnlyFarms

## Current Status: ⚠️ SMS DISABLED

Phone verification via SMS is currently **DISABLED** because SMS services require payment and configuration.

---

## Why SMS is Important for Marketplaces

1. **Prevents Fake Accounts** - Phone numbers are harder to fake than emails
2. **Order Notifications** - SMS for order updates is more reliable
3. **Security** - Two-factor authentication via SMS
4. **Trust** - Verified phone numbers build buyer/seller trust

---

## To Enable SMS (When Ready)

### Option 1: Semaphore SMS (Recommended for Philippines) 🇵🇭

**Cost:** ~₱0.60 per SMS  
**Best for:** Philippine market, most cost-effective

1. Sign up at: https://semaphore.co/
2. Get API key from dashboard
3. Add to `.env`:
   ```env
   SEMAPHORE_API_KEY=your_api_key_here
   SEMAPHORE_SENDER_NAME=OnlyFarms
   ```
4. Update `SmsService.php` to use Semaphore instead of AWS

---

### Option 2: Twilio (Global)

**Cost:** ~₱3.00 per SMS  
**Best for:** International users

1. Sign up at: https://www.twilio.com/
2. Get credentials from console
3. Add to `.env`:
   ```env
   TWILIO_SID=your_account_sid
   TWILIO_TOKEN=your_auth_token
   TWILIO_FROM_NUMBER=+1234567890
   ```
4. Update `SmsService.php` to use Twilio SDK

---

### Option 3: AWS SNS (Current Setup)

**Cost:** ~₱0.36 per SMS  
**Best for:** Already using AWS

1. Create AWS account
2. Enable SNS service
3. Add to `.env`:
   ```env
   AWS_ACCESS_KEY_ID=your_access_key
   AWS_SECRET_ACCESS_KEY=your_secret_key
   AWS_DEFAULT_REGION=ap-southeast-1
   ```
4. `SmsService.php` already configured for AWS!

---

## What Happens When You Enable SMS

1. **Registration Flow Changes:**
   ```
   Before: Name + Email + Phone + Password → Instant Login
   After:  Name + Email + Phone + Password → SMS OTP → Verify → Login
   ```

2. **Endpoints Get Enabled:**
   - `POST /send-phone-verification-code` - Sends OTP
   - `POST /resend-phone-verification-code` - Resends OTP
   - `POST /verify-phone` - Verifies OTP and logs user in

3. **Frontend Works Automatically:**
   - `phone-verification.tsx` already exists and is ready
   - Just change signup flow to navigate to verification screen

---

## Files to Modify When Enabling

### Backend:
1. `AuthController.php` - Uncomment phone verification methods
2. `SmsService.php` - Configure your chosen provider
3. `.env` - Add SMS provider credentials

### Frontend:
1. `signup.tsx` - Re-enable navigation to phone verification
2. Already has all phone verification UI!

---

## Cost Estimate (Monthly)

For **1,000 new users per month**:
- Semaphore: ₱600/month
- AWS SNS: ₱360/month  
- Twilio: ₱3,000/month

**Note:** Only pay for actual SMS sent. No signup = no cost.

---

## Recommendation

**Start with Semaphore SMS when you're ready to launch.**

It's the most cost-effective and reliable for the Philippine market. You can always switch providers later.

---

## Current Workaround

Without SMS verification:
- Users are auto-verified on signup
- `phone_verified_at` is set immediately
- Lower security but no SMS costs
- Good for development/testing phase

When you're ready to add SMS, just follow the steps above! 🚀

