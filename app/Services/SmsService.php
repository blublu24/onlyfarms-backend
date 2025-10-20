<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SmsService
{
    private $accessKey;
    private $secretKey;
    private $region;

    public function __construct()
    {
        $this->accessKey = config('services.aws.access_key');
        $this->secretKey = config('services.aws.secret_key');
        $this->region = config('services.aws.region', 'us-east-1');
    }

    /**
     * Send SMS message via AWS SNS using SDK
     */
    public function sendSms($to, $message)
    {
        try {
            // Format phone number for AWS SNS
            $formattedNumber = $this->formatPhoneNumber($to);
            
            // Create AWS SNS client
            $sns = new \Aws\Sns\SnsClient([
                'version' => 'latest',
                'region' => $this->region,
                'credentials' => [
                    'key' => $this->accessKey,
                    'secret' => $this->secretKey,
                ],
            ]);
            
            // Send SMS
            $result = $sns->publish([
                'PhoneNumber' => $formattedNumber,
                'Message' => $message,
            ]);
            
            $messageId = $result['MessageId'] ?? 'unknown';
            
            Log::info("SMS sent successfully via AWS SDK", [
                'to' => $formattedNumber,
                'message_id' => $messageId,
                'status' => 'sent'
            ]);

            return [
                'success' => true,
                'message_id' => $messageId,
                'status' => 'sent'
            ];

        } catch (\Exception $e) {
            Log::error("SMS sending failed via AWS SDK", [
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
     * Send OTP verification code
     */
    public function sendOtp($phoneNumber, $otpCode)
    {
        $message = "Your OnlyFarms verification code is: {$otpCode}. This code will expire in 5 minutes. Do not share this code with anyone.";
        
        return $this->sendSms($phoneNumber, $message);
    }

    /**
     * Format Philippine phone number for AWS SNS
     */
    private function formatPhoneNumber($phoneNumber)
    {
        // Remove all non-digit characters
        $cleanNumber = preg_replace('/\D/', '', $phoneNumber);
        
        // AWS SNS expects format: +639XXXXXXXXX (with + sign)
        if (strpos($cleanNumber, '09') === 0) {
            // Convert 09XX to +639XX
            $cleanNumber = '+63' . substr($cleanNumber, 1);
        } elseif (strpos($cleanNumber, '639') === 0) {
            // Add + prefix
            $cleanNumber = '+' . $cleanNumber;
        } else {
            // Add +63 prefix
            $cleanNumber = '+63' . $cleanNumber;
        }
        
        return $cleanNumber;
    }

    /**
     * Test SMS functionality
     */
    public function testSms($phoneNumber)
    {
        $message = "Test message from OnlyFarms. SMS service is working correctly!";
        return $this->sendSms($phoneNumber, $message);
    }
}