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
            if ($encryption) {
                $mail->SMTPSecure = $encryption;
            }

            $mail->setFrom($fromEmail, (string) $fromName);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            if ($textBody) {
                $mail->AltBody = $textBody;
            }

            $mail->send();
        } catch (MailException $e) {
            throw new \RuntimeException('Email send failed: ' . $e->getMessage(), 0, $e);
        }
    }
}


