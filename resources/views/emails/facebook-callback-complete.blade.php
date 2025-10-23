<!DOCTYPE html>
<html>
<head>
    <title>Completing Facebook Login...</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            color: #333;
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        p {
            color: #666;
            margin: 10px 0;
            line-height: 1.6;
        }
        .spinner {
            display: inline-block;
            width: 30px;
            height: 30px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 20px 0;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✓ Authentication Complete!</h1>
        <p>Completing your login...</p>
        <div class="spinner"></div>
    </div>

    <script>
        // Instead of redirecting, send a message to the parent window (mobile app)
        // This will be intercepted by the WebView's onNavigationStateChange
        try {
            // Send the code and state to the mobile app
            if (window.ReactNativeWebView) {
                window.ReactNativeWebView.postMessage(JSON.stringify({
                    type: 'FACEBOOK_AUTH_SUCCESS',
                    code: '{{ $code }}',
                    state: '{{ $state }}'
                }));
            } else {
                // Fallback: redirect to a special URL that the WebView can intercept
                window.location.href = 'onlyfarms://facebook-auth-success?code={{ $code }}&state={{ $state }}';
            }
        } catch (error) {
            console.error('Error sending message to mobile app:', error);
            // Fallback redirect
            window.location.href = 'onlyfarms://facebook-auth-success?code={{ $code }}&state={{ $state }}';
        }
    </script>
</body>
</html>
