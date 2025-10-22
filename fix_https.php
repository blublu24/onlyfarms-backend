<?php
$file = 'app/Http/Controllers/AuthController.php';
$content = file_get_contents($file);

// Find and replace the section
$old = <<<'OLD'
            // Create a state parameter for security
            $state = bin2hex(random_bytes(16));

            $params = [
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'scope' => 'public_profile email', // Added email scope
                'response_type' => 'code',
                'state' => $state,
            ];
OLD;

$new = <<<'NEW'
            // Create a state parameter for security
            $state = bin2hex(random_bytes(16));

            // Facebook REQUIRES HTTPS for the redirect_uri (security requirement from Facebook)
            $facebookRedirectUri = $redirectUri;
            if (strpos($facebookRedirectUri, 'http://') === 0) {
                $facebookRedirectUri = str_replace('http://', 'https://', $facebookRedirectUri);
            }

            $params = [
                'client_id' => $clientId,
                'redirect_uri' => $facebookRedirectUri, // MUST be HTTPS for Facebook
                'scope' => 'public_profile,email',
                'response_type' => 'code',
                'state' => $state,
            ];
NEW;

$content = str_replace($old, $new, $content);
file_put_contents($file, $content);
echo "✓ Fixed HTTPS requirement for Facebook\n";
?>
