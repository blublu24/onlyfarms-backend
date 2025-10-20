<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnlyFarms - Email Verification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f9f4;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #2c5938;
            margin-bottom: 10px;
        }
        .title {
            font-size: 20px;
            color: #2c5938;
            margin-bottom: 20px;
        }
        .otp-code {
            background-color: #f0f8f0;
            border: 2px solid #2c5938;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        .otp-number {
            font-size: 32px;
            font-weight: bold;
            color: #2c5938;
            letter-spacing: 4px;
            margin: 10px 0;
        }
        .message {
            margin: 20px 0;
            line-height: 1.8;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 14px;
        }
        .button {
            display: inline-block;
            background-color: #2c5938;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🌱 OnlyFarms</div>
            <h1 class="title">Email Verification Code</h1>
        </div>

        <div class="message">
            @if($mailData['userName'])
                <p>Hello <strong>{{ $mailData['userName'] }}</strong>,</p>
            @else
                <p>Hello,</p>
            @endif
            
            <p>Thank you for signing up with OnlyFarms! To complete your registration, please use the verification code below:</p>
        </div>

        <div class="otp-code">
            <p style="margin: 0 0 10px 0; font-weight: bold;">Your verification code is:</p>
            <div class="otp-number">{{ $mailData['otp'] }}</div>
            <p style="margin: 10px 0 0 0; font-size: 14px; color: #666;">This code will expire in 10 minutes</p>
        </div>

        <div class="message">
            <p>Enter this code in the verification screen to activate your account and start shopping for fresh farm products!</p>
        </div>

        <div class="warning">
            <strong>⚠️ Security Notice:</strong>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>Never share this code with anyone</li>
                <li>OnlyFarms will never ask for your verification code</li>
                <li>If you didn't request this code, please ignore this email</li>
            </ul>
        </div>

        <div class="footer">
            <p>This email was sent to <strong>{{ $mailData['email'] }}</strong></p>
            <p>If you have any questions, please contact our support team.</p>
            <p style="margin-top: 20px;">
                <strong>OnlyFarms</strong><br>
                Your trusted source for fresh farm products
            </p>
        </div>
    </div>
</body>
</html>
