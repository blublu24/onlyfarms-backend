<?php

namespace App\Services;

use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Illuminate\Support\Facades\Log;

class GmailApiService
{
    private $client;
    private $gmail;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setApplicationName('OnlyFarms Email Verification');
        $this->client->setScopes([
            Gmail::GMAIL_READONLY,
            Gmail::GMAIL_SEND
        ]);
        $this->client->setAuthConfig(config('services.google.credentials_path'));
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');
        
        $this->gmail = new Gmail($this->client);
    }

    /**
     * Send verification email via Gmail API
     */
    public function sendVerificationEmail($to, $verificationCode)
    {
        try {
            $subject = 'OnlyFarms - Email Verification Code';
            $body = $this->createEmailBody($verificationCode);
            
            $message = $this->createMessage($to, $subject, $body);
            $result = $this->gmail->users_messages->send('me', $message);
            
            Log::info('Gmail API: Verification email sent', [
                'to' => $to,
                'message_id' => $result->getId()
            ]);
            
            return [
                'success' => true,
                'message_id' => $result->getId(),
                'verification_code' => $verificationCode
            ];
            
        } catch (\Exception $e) {
            Log::error('Gmail API: Failed to send email', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Search for verification emails in user's Gmail
     */
    public function searchVerificationEmails($userEmail, $verificationCode = null)
    {
        try {
            // Set up OAuth2 for the specific user
            $this->setUserCredentials($userEmail);
            
            $query = "from:onlyfarms@gmail.com subject:\"OnlyFarms - Email Verification\"";
            if ($verificationCode) {
                $query .= " \"{$verificationCode}\"";
            }
            
            $messages = $this->gmail->users_messages->listUsersMessages('me', [
                'q' => $query,
                'maxResults' => 10
            ]);
            
            $verificationEmails = [];
            
            foreach ($messages->getMessages() as $message) {
                $fullMessage = $this->gmail->users_messages->get('me', $message->getId());
                $verificationEmails[] = $this->extractVerificationData($fullMessage);
            }
            
            return [
                'success' => true,
                'emails' => $verificationEmails
            ];
            
        } catch (\Exception $e) {
            Log::error('Gmail API: Failed to search emails', [
                'user_email' => $userEmail,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Auto-verify by finding the latest verification email
     */
    public function autoVerifyEmail($userEmail)
    {
        try {
            $this->setUserCredentials($userEmail);
            
            // Search for the most recent verification email
            $query = "from:onlyfarms@gmail.com subject:\"OnlyFarms - Email Verification\"";
            $messages = $this->gmail->users_messages->listUsersMessages('me', [
                'q' => $query,
                'maxResults' => 1
            ]);
            
            if (empty($messages->getMessages())) {
                return [
                    'success' => false,
                    'error' => 'No verification email found'
                ];
            }
            
            $latestMessage = $messages->getMessages()[0];
            $fullMessage = $this->gmail->users_messages->get('me', $latestMessage->getId());
            
            $verificationData = $this->extractVerificationData($fullMessage);
            
            return [
                'success' => true,
                'verification_code' => $verificationData['code'],
                'email_id' => $latestMessage->getId(),
                'sent_at' => $verificationData['sent_at']
            ];
            
        } catch (\Exception $e) {
            Log::error('Gmail API: Auto-verification failed', [
                'user_email' => $userEmail,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get OAuth2 authorization URL
     */
    public function getAuthUrl($userEmail)
    {
        $this->client->setState($userEmail);
        return $this->client->createAuthUrl();
    }

    /**
     * Exchange authorization code for access token
     */
    public function exchangeCodeForToken($code, $userEmail)
    {
        try {
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($code);
            
            if (isset($accessToken['error'])) {
                throw new \Exception($accessToken['error_description']);
            }
            
            // Store the token for the user
            $this->storeUserToken($userEmail, $accessToken);
            
            return [
                'success' => true,
                'access_token' => $accessToken
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create email message for Gmail API
     */
    private function createMessage($to, $subject, $body)
    {
        $message = new Message();
        
        $rawMessage = "To: {$to}\r\n";
        $rawMessage .= "Subject: {$subject}\r\n";
        $rawMessage .= "Content-Type: text/html; charset=UTF-8\r\n";
        $rawMessage .= "\r\n";
        $rawMessage .= $body;
        
        $message->setRaw(base64_encode($rawMessage));
        
        return $message;
    }

    /**
     * Create HTML email body
     */
    private function createEmailBody($verificationCode)
    {
        return view('emails.verification-code', [
            'verificationCode' => $verificationCode
        ])->render();
    }

    /**
     * Extract verification data from email
     */
    private function extractVerificationData($message)
    {
        $headers = $message->getPayload()->getHeaders();
        $body = $message->getPayload()->getBody();
        
        $subject = '';
        $date = '';
        
        foreach ($headers as $header) {
            if ($header->getName() === 'Subject') {
                $subject = $header->getValue();
            }
            if ($header->getName() === 'Date') {
                $date = $header->getValue();
            }
        }
        
        // Extract verification code from email body
        $code = $this->extractCodeFromBody($body);
        
        return [
            'code' => $code,
            'subject' => $subject,
            'sent_at' => $date,
            'message_id' => $message->getId()
        ];
    }

    /**
     * Extract verification code from email body
     */
    private function extractCodeFromBody($body)
    {
        // This would need to parse the actual email content
        // For now, return a placeholder
        return '123456'; // This should be extracted from the actual email content
    }

    /**
     * Set user credentials for OAuth2
     */
    private function setUserCredentials($userEmail)
    {
        $token = $this->getUserToken($userEmail);
        if ($token) {
            $this->client->setAccessToken($token);
        }
    }

    /**
     * Store user's access token
     */
    private function storeUserToken($userEmail, $token)
    {
        // Store in database or cache
        // This is a simplified version
        cache()->put("gmail_token_{$userEmail}", $token, now()->addHour());
    }

    /**
     * Get user's access token
     */
    private function getUserToken($userEmail)
    {
        return cache()->get("gmail_token_{$userEmail}");
    }
}
