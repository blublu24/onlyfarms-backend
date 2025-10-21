# PHPMailer Email Verification Setup for Railway

## ⚠️ IMPORTANT: Required Environment Variables

The email verification feature requires SMTP configuration in Railway. You need to add these environment variables to your Railway project:

### Required SMTP Variables

```bash
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_ENCRYPTION=tls
SMTP_FROM_ADDRESS=noreply@onlyfarms.com
SMTP_FROM_NAME=OnlyFarms
```

## Setup Instructions

### Step 1: Get SMTP Credentials

You mentioned you used **SendGrid** for SMTP. Here's how to configure it:

#### Option A: SendGrid (Recommended if you already have it)
```bash
SMTP_HOST=smtp.sendgrid.net
SMTP_PORT=587
SMTP_USERNAME=apikey
SMTP_PASSWORD=<your-sendgrid-api-key>
SMTP_ENCRYPTION=tls
SMTP_FROM_ADDRESS=noreply@onlyfarms.com
SMTP_FROM_NAME=OnlyFarms
```

#### Option B: Brevo/Sendinblue (Alternative)
```bash
SMTP_HOST=smtp-relay.brevo.com
SMTP_PORT=587
SMTP_USERNAME=<your-brevo-email>
SMTP_PASSWORD=<your-brevo-smtp-key>
SMTP_ENCRYPTION=tls
SMTP_FROM_ADDRESS=noreply@onlyfarms.com
SMTP_FROM_NAME=OnlyFarms
```

#### Option C: Gmail (For Testing Only)
⚠️ **Not recommended for production** - Gmail has strict sending limits
```bash
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=<your-app-password>
SMTP_ENCRYPTION=tls
SMTP_FROM_ADDRESS=your-email@gmail.com
SMTP_FROM_NAME=OnlyFarms
```

### Step 2: Add Variables to Railway

1. Go to your Railway project dashboard
2. Click on your **Web service** (not the database)
3. Go to the **Variables** tab
4. Click **+ New Variable** for each of the SMTP variables above
5. **Important:** Remove any quotes around the values
6. Click **Deploy** to restart with new variables

### Step 3: Verify Deployment

After Railway finishes deploying (about 2-3 minutes):

1. Check the deployment logs for any errors
2. Test the endpoint: `https://onlyfarms-backend-production.up.railway.app/api/send-email-verification-code`
3. Try sending a verification code from your app

## Testing the Endpoint

You can test if the endpoint is working using this curl command:

```bash
curl -X POST https://onlyfarms-backend-production.up.railway.app/api/send-email-verification-code \
  -H "Content-Type: application/json" \
  -d '{"email":"your-test-email@gmail.com"}'
```

Expected response (in development):
```json
{
  "message": "Verification code sent to your email",
  "code": "123456"
}
```

## Troubleshooting

### "Connection Error" in App
- **Cause:** SMTP variables are not set in Railway
- **Solution:** Add all SMTP_* variables and redeploy

### "Failed to send verification email"
- **Cause:** Invalid SMTP credentials or wrong SMTP host/port
- **Solution:** Double-check your SMTP credentials and configuration

### Email not received
- **Cause:** Email might be in spam, or SMTP service is rejecting
- **Solution:** 
  1. Check spam folder
  2. Check Railway logs for errors
  3. Verify your SMTP service is active

### Rate Limiting
- **Cause:** Too many requests in a short time
- **Solution:** Wait 1 minute between attempts (rate limit: 3 per minute)

## What the Email Verification Does

1. **User enters email** in signup form
2. **App sends request** to `/api/send-email-verification-code`
3. **Backend generates** a 6-digit code and stores it in cache (10 min expiry)
4. **Backend sends email** with the code via PHPMailer
5. **User enters code** and clicks "Verify"
6. **App sends request** to `/api/verify-email` with the code
7. **Backend validates** the code and marks email as verified (1 hour)
8. **User can proceed** with signup after verification

## Next Steps

1. ✅ Add SMTP variables to Railway
2. ✅ Wait for deployment to complete
3. ✅ Test email verification in your app
4. ✅ Check if you receive the verification code email

---

**Note:** Make sure you're using a valid SMTP service. SendGrid, Brevo, and Mailgun all offer free tiers that work well for this purpose.

