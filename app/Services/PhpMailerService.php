<?php

namespace App\Services;

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

class PhpMailerService
{
    /**
     * Send an email using PHPMailer over SMTP
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, ?string $textBody = null): void
    {
        $mail = new PHPMailer(true);

        try {
            // SMTP settings from env
            $host = env('SMTP_HOST');
            $port = (int) (env('SMTP_PORT') ?: 587);
            $username = env('SMTP_USERNAME');
            $password = env('SMTP_PASSWORD');
            $encryption = env('SMTP_ENCRYPTION', 'tls');
            $fromEmail = env('SMTP_FROM_ADDRESS', env('MAIL_FROM_ADDRESS'));
            $fromName = env('SMTP_FROM_NAME', env('MAIL_FROM_NAME', 'OnlyFarms'));

            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->Port = $port;
            $mail->Timeout = 30; // 30 second timeout
            $mail->SMTPKeepAlive = true;
            if ($encryption) {
                $mail->SMTPSecure = $encryption;
            }
            
            // Log configuration for debugging
            \Log::info('PHPMailer SMTP Configuration', [
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'encryption' => $encryption,
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'to_email' => $toEmail
            ]);

            $mail->setFrom($fromEmail, (string) $fromName);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            if ($textBody) {
                $mail->AltBody = $textBody;
            }

            $mail->send();
            \Log::info('Email sent successfully', ['to' => $toEmail, 'subject' => $subject]);
        } catch (MailException $e) {
            \Log::error('PHPMailer failed to send email', [
                'to' => $toEmail,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'error_info' => $mail->ErrorInfo ?? 'No error info available'
            ]);
            throw new \RuntimeException('Email send failed: ' . $e->getMessage(), 0, $e);
        }
    }
}


