<!DOCTYPE html>
<html>
<head>
    <title>Facebook Authorization Error</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 400px;
        }
        h1 {
            color: #d32f2f;
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        p {
            color: #666;
            margin: 10px 0;
            line-height: 1.6;
        }
        .error-code {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            color: #d32f2f;
            margin: 15px 0;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✗ Authorization Failed</h1>
        <p>Facebook authorization could not be completed.</p>
        <div class="error-code">{{ $error }}: {{ $description ?? 'Unknown error' }}</div>
        <p style="font-size: 12px; color: #999; margin-top: 20px;">
            Please try again or return to the app.
        </p>
    </div>
</body>
</html>
