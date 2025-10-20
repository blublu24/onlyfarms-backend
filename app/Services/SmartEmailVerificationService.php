<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use App\Mail\VerificationCodeMail;

class SmartEmailVerificationService
{
    /**
     * Send verification email with Gmail integration
     */
    public function sendVerificationEmail($email, $verificationCode)
    {
        try {
            // Send via SMTP (fast and reliable)
            Mail::to($email)->send(new VerificationCodeMail($verificationCode));
            
            // Generate Gmail deep link for easy access
            $gmailUrl = $this->generateGmailDeepLink($email, $verificationCode);
            
            return [
                'success' => true,
                'verification_code' => $verificationCode,
                'gmail_url' => $gmailUrl,
                'gmail_app_url' => $this->generateGmailAppLink($email),
                'instructions' => $this->getGmailInstructions($verificationCode)
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'verification_code' => $verificationCode, // Fallback
                'gmail_url' => $this->generateGmailDeepLink($email, $verificationCode)
            ];
        }
    }

    /**
     * Generate Gmail deep link for easy access
     */
    private function generateGmailDeepLink($email, $verificationCode)
    {
        $subject = 'OnlyFarms - Email Verification Code';
        $body = "Your verification code is: {$verificationCode}";
        
        return "https://mail.google.com/mail/?view=cm&to=" . urlencode($email) . 
               "&su=" . urlencode($subject) . 
               "&body=" . urlencode($body);
    }

    /**
     * Generate Gmail app link
     */
    private function generateGmailAppLink($email)
    {
        return "googlegmail://co?to=" . urlencode($email);
    }

    /**
     * Get Gmail-specific instructions
     */
    private function getGmailInstructions($verificationCode)
    {
        return [
            'title' => 'Check Your Gmail',
            'steps' => [
                '1. Open Gmail app or website',
                '2. Look for "OnlyFarms Verification" email',
                '3. Find the 6-digit code in the email',
                '4. Enter the code in the app',
                '5. Complete your registration'
            ],
            'tips' => [
                'Check your spam folder if not found',
                'The code expires in 10 minutes',
                'You can also use the code shown below'
            ],
            'fallback_code' => $verificationCode
        ];
    }

    /**
     * Smart Gmail detection and assistance
     */
    public function getGmailAssistance($email)
    {
        $isGmail = $this->isGmailAddress($email);
        
        if ($isGmail) {
            return [
                'is_gmail' => true,
                'assistance' => [
                    'gmail_app_url' => $this->generateGmailAppLink($email),
                    'gmail_web_url' => 'https://mail.google.com',
                    'search_query' => 'OnlyFarms Verification',
                    'instructions' => $this->getGmailInstructions('')
                ]
            ];
        }
        
        return [
            'is_gmail' => false,
            'assistance' => [
                'instructions' => [
                    'title' => 'Check Your Email',
                    'steps' => [
                        '1. Open your email app',
                        '2. Look for "OnlyFarms Verification" email',
                        '3. Find the 6-digit code',
                        '4. Enter the code in the app'
                    ]
                ]
            ]
        ];
    }

    /**
     * Check if email is Gmail
     */
    private function isGmailAddress($email)
    {
        $gmailDomains = ['gmail.com', 'googlemail.com'];
        $domain = substr(strrchr($email, "@"), 1);
        return in_array(strtolower($domain), $gmailDomains);
    }

    /**
     * Generate Gmail search URL
     */
    public function generateGmailSearchUrl($email)
    {
        if ($this->isGmailAddress($email)) {
            return "https://mail.google.com/mail/u/0/#search/OnlyFarms+Verification";
        }
        return null;
    }

    /**
     * Get Gmail app store links
     */
    public function getGmailAppLinks()
    {
        return [
            'android' => 'https://play.google.com/store/apps/details?id=com.google.android.gm',
            'ios' => 'https://apps.apple.com/app/gmail-email-by-google/id422689480',
            'web' => 'https://mail.google.com'
        ];
    }

    /**
     * Enhanced verification with Gmail features
     */
    public function enhancedVerification($email, $verificationCode)
    {
        $isGmail = $this->isGmailAddress($email);
        
        $result = [
            'verification_code' => $verificationCode,
            'email' => $email,
            'is_gmail' => $isGmail,
            'expires_in' => 600, // 10 minutes
        ];

        if ($isGmail) {
            $result['gmail_features'] = [
                'deep_link' => $this->generateGmailDeepLink($email, $verificationCode),
                'app_link' => $this->generateGmailAppLink($email),
                'search_url' => $this->generateGmailSearchUrl($email),
                'app_store_links' => $this->getGmailAppLinks(),
                'instructions' => $this->getGmailInstructions($verificationCode)
            ];
        }

        return $result;
    }
}
