    /**
     * Facebook Callback - Handles redirect from Facebook OAuth
     * WebView intercepts this URL and extracts the authorization code
     */
    public function facebookCallback(Request $request)
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');
        $errorDescription = $request->query('error_description');

        \Log::info('Facebook callback received', [
            'has_code' => !!$code,
            'has_state' => !!$state,
            'error' => $error
        ]);

        if ($error) {
            \Log::error('Facebook authorization error in callback', [
                'error' => $error,
                'description' => $errorDescription
            ]);
            // Return JSON so WebView can handle it
            return response()->json([
                'error' => true,
                'message' => $error,
                'description' => $errorDescription
            ], 400);
        }

        if (!$code) {
            \Log::error('No code in Facebook callback');
            return response()->json([
                'error' => true,
                'message' => 'No authorization code received'
            ], 400);
        }

        \Log::info('Facebook callback success - code received');

        // Return JSON response - WebView will still intercept the URL
        // and extract the code from the URL, not from the response body
        return response()->json([
            'success' => true,
            'message' => 'Authorization successful',
            'code' => $code
        ]);
    }
