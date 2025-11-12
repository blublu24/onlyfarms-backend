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
        // SMTP settings from env
        $host = env('SMTP_HOST');
        $port = (int) (env('SMTP_PORT') ?: 587);
        $username = env('SMTP_USERNAME');
        $password = env('SMTP_PASSWORD');
        $encryption = env('SMTP_ENCRYPTION', 'tls');
        $fromEmail = env('SMTP_FROM_ADDRESS', env('MAIL_FROM_ADDRESS'));
        $fromName = env('SMTP_FROM_NAME', env('MAIL_FROM_NAME', 'OnlyFarms'));
        $timeout = (int) (env('SMTP_TIMEOUT') ?: 30);

        if (empty($host) || empty($username) || empty($password) || empty($fromEmail)) {
            throw new \RuntimeException('SMTP credentials are not fully configured.');
        }

        $attempts = $this->buildConnectionAttempts($host, $port, $encryption);
        $attemptErrors = [];
        $lastException = null;

        foreach ($attempts as $index => $attempt) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->CharSet = 'UTF-8';
                $mail->Host = $attempt['host'];
                $mail->SMTPAuth = true;
                $mail->Username = $username;
                $mail->Password = $password;
                $mail->Port = $attempt['port'];
                $mail->Timeout = $timeout;
                $mail->SMTPKeepAlive = true;
                $mail->SMTPAutoTLS = true;

                if ($attempt['encryption'] === 'ssl') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($attempt['encryption'] === 'tls') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } elseif ($attempt['encryption']) {
                    $mail->SMTPSecure = $attempt['encryption'];
                }

                // Log configuration for debugging
                \Log::info('PHPMailer SMTP attempt', [
                    'attempt' => $index + 1,
                    'host' => $attempt['host'],
                    'port' => $attempt['port'],
                    'encryption' => $attempt['encryption'],
                    'username_masked' => $this->maskString($username),
                    'from_email' => $fromEmail,
                    'to_email' => $toEmail,
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

                \Log::info('Email sent successfully', [
                    'to' => $toEmail,
                    'subject' => $subject,
                    'attempt' => $index + 1,
                    'fallback_used' => $index > 0,
                ]);

                return;
            } catch (MailException $e) {
                $lastException = $e;
                $attemptError = [
                    'attempt' => $index + 1,
                    'host' => $attempt['host'],
                    'port' => $attempt['port'],
                    'encryption' => $attempt['encryption'],
                    'error' => $e->getMessage(),
                    'error_info' => $mail->ErrorInfo ?? 'No error info available',
                ];
                $attemptErrors[] = $attemptError;

                \Log::warning('PHPMailer attempt failed', $attemptError);
                error_log('[PHPMailer] Attempt failed: ' . json_encode($attemptError));
            } finally {
                $mail->smtpClose();
            }
        }

        $finalErrorContext = [
            'to' => $toEmail,
            'subject' => $subject,
            'attempt_errors' => $attemptErrors,
        ];
        \Log::error('PHPMailer failed to send email after all attempts', $finalErrorContext);
        error_log('[PHPMailer] All attempts exhausted: ' . json_encode($finalErrorContext));

        $message = 'Email send failed';
        if ($lastException instanceof MailException) {
            $message .= ': ' . $lastException->getMessage();
        }

        throw new \RuntimeException($message, 0, $lastException);
    }

    /**
     * Build connection attempts with sensible fallbacks (e.g., Gmail SSL fallback)
     *
     * @return array<int, array{host:string, port:int, encryption: ?string}>
     */
    private function buildConnectionAttempts(string $host, int $port, ?string $encryption): array
    {
        $attempts = [];

        $preferredPort = $port ?: 587;
        $preferredEncryption = $encryption ? strtolower($encryption) : 'tls';

        $attempts[] = [
            'host' => $host,
            'port' => $preferredPort,
            'encryption' => $preferredEncryption,
        ];

        $normalizedEncryption = $encryption ? strtolower($encryption) : null;
        $isGmailHost = str_contains($host, 'gmail') || str_contains($host, 'googlemail');

        if ($isGmailHost) {
            if (!($preferredPort === 587 && ($normalizedEncryption === 'tls' || $normalizedEncryption === 'starttls'))) {
                $attempts[] = [
                    'host' => $host,
                    'port' => 587,
                    'encryption' => 'tls',
                ];
            }

            if (!($preferredPort === 465 && $normalizedEncryption === 'ssl')) {
                $attempts[] = [
                    'host' => $host,
                    'port' => 465,
                    'encryption' => 'ssl',
                ];
            }
        } elseif ($preferredPort !== 587) {
            $attempts[] = [
                'host' => $host,
                'port' => 587,
                'encryption' => $normalizedEncryption ?: 'tls',
            ];
        }

        // De-duplicate attempts while preserving order
        $unique = [];
        $filtered = [];
        foreach ($attempts as $attempt) {
            $key = "{$attempt['host']}|{$attempt['port']}|" . ($attempt['encryption'] ?? '');
            if (!isset($unique[$key])) {
                $unique[$key] = true;
                $filtered[] = $attempt;
            }
        }

        return $filtered;
    }

    /**
     * Mask sensitive strings for logging (shows first and last character only)
     */
    private function maskString(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $length = mb_strlen($value);
        if ($length <= 2) {
            return str_repeat('*', $length);
        }

        return mb_substr($value, 0, 1) . str_repeat('*', $length - 2) . mb_substr($value, -1);
    }
}


