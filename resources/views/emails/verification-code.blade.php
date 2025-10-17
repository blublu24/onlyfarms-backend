<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnlyFarms - Email Verification</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #2e7d32;
            margin-bottom: 10px;
        }
        .title {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
        }
        .verification-code {
            background-color: #f8f9fa;
            border: 2px dashed #2e7d32;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 30px 0;
        }
        .code {
            font-size: 36px;
            font-weight: bold;
            color: #2e7d32;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
        }
        .instructions {
            background-color: #e8f5e8;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
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
            background-color: #2e7d32;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🌱 OnlyFarms</div>
            <h1 class="title">Email Verification</h1>
        </div>

        <p>Hello!</p>
        
        <p>Thank you for signing up with OnlyFarms! To complete your registration, please use the verification code below:</p>

        <div class="verification-code">
            <div class="code">{{ $verificationCode }}</div>
        </div>

        <div class="instructions">
            <h3>📱 How to verify:</h3>
            <ol>
                <li>Return to the OnlyFarms app</li>
                <li>Enter the 6-digit code above</li>
                <li>Complete your registration</li>
            </ol>
        </div>

        <div class="warning">
            <strong>⚠️ Important:</strong>
            <ul>
                <li>This code will expire in <strong>10 minutes</strong></li>
                <li>Do not share this code with anyone</li>
                <li>OnlyFarms will never ask for your verification code via phone or email</li>
            </ul>
        </div>

        <p>If you didn't request this verification code, please ignore this email.</p>

        <div class="footer">
            <p>This email was sent by OnlyFarms</p>
            <p>If you have any questions, please contact our support team.</p>
            <p style="font-size: 12px; color: #999;">
                © {{ date('Y') }} OnlyFarms. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
