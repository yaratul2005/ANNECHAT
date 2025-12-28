<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SmtpSettings;
use App\Models\EmailLog;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\SMTP;

class EmailService {
    private SmtpSettings $smtpModel;
    private ?array $smtpSettings = null;

    public function __construct() {
        $this->smtpModel = new SmtpSettings();
        $this->smtpSettings = $this->smtpModel->get();
    }

    public function isConfigured(): bool {
        return $this->smtpModel->isConfigured();
    }

    public function isActive(): bool {
        return $this->smtpModel->isActive();
    }

    public function testConnection(): array {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'SMTP is not configured'
            ];
        }

        try {
            $mailer = $this->createMailer();
            
            // Set test email details
            $mailer->setFrom($this->smtpSettings['from_email'], $this->smtpSettings['from_name'] ?? 'Test');
            $mailer->addAddress($this->smtpSettings['from_email']);
            $mailer->Subject = 'SMTP Test';
            $mailer->Body = 'This is a test email from the admin panel. If you receive this, SMTP configuration is working correctly!';
            $mailer->isHTML(false);
            
            $result = $mailer->send();
            
            if ($result) {
                $this->smtpModel->updateTestStatus('success', 'Test email sent successfully');
                return [
                    'success' => true,
                    'message' => 'Test email sent successfully to ' . $this->smtpSettings['from_email']
                ];
            } else {
                $this->smtpModel->updateTestStatus('failed', 'Failed to send test email: ' . $mailer->ErrorInfo);
                return [
                    'success' => false,
                    'error' => 'Failed to send test email: ' . $mailer->ErrorInfo
                ];
            }
        } catch (\Exception $e) {
            $this->smtpModel->updateTestStatus('failed', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function sendEmail(string $to, string $toName, string $subject, string $body, bool $isHtml = true): array {
        if (!$this->isActive()) {
            return [
                'success' => false,
                'error' => 'SMTP is not active. Please enable SMTP in the admin panel.'
            ];
        }

        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'SMTP is not configured. Please configure SMTP settings in the admin panel.'
            ];
        }

        $emailLog = new EmailLog();
        $logId = $emailLog->create($to, $toName, $subject, $body);

        try {
            $fromEmail = $this->smtpSettings['from_email'];
            $fromName = $this->smtpSettings['from_name'] ?? 'System';
            
            // Use PHPMailer for reliable SMTP sending
            $mailer = $this->createMailer();
            
            // Set email details
            $mailer->setFrom($fromEmail, $fromName);
            $mailer->addAddress($to, $toName ?: $to);
            $mailer->addReplyTo($fromEmail, $fromName);
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            $mailer->isHTML($isHtml);
            
            $result = $mailer->send();
            
            if ($result) {
                $emailLog->updateStatus($logId, 'sent');
                return [
                    'success' => true,
                    'message' => 'Email sent successfully'
                ];
            } else {
                $errorMsg = $mailer->ErrorInfo ?? 'Unknown error';
                $emailLog->updateStatus($logId, 'failed', $errorMsg);
                return [
                    'success' => false,
                    'error' => 'Failed to send email: ' . $errorMsg
                ];
            }
        } catch (PHPMailerException $e) {
            $errorMsg = $e->getMessage();
            $emailLog->updateStatus($logId, 'failed', $errorMsg);
            return [
                'success' => false,
                'error' => 'Email sending failed: ' . $errorMsg
            ];
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            $emailLog->updateStatus($logId, 'failed', $errorMsg);
            return [
                'success' => false,
                'error' => 'Email sending failed: ' . $errorMsg
            ];
        }
    }
    
    private function createMailer(): PHPMailer {
        if (!$this->smtpSettings || empty($this->smtpSettings['host'])) {
            throw new \Exception('SMTP settings are not configured');
        }
        
        $mailer = new PHPMailer(true);
        
        // Server settings
        $mailer->isSMTP();
        $mailer->Host = $this->smtpSettings['host'];
        $mailer->SMTPAuth = true;
        $mailer->Username = $this->smtpSettings['username'] ?? '';
        $mailer->Password = $this->smtpSettings['password'] ?? '';
        $mailer->SMTPSecure = $this->getSmtpSecure($this->smtpSettings['encryption'] ?? 'tls');
        $mailer->Port = (int)($this->smtpSettings['port'] ?? 587);
        $mailer->Timeout = 30;
        
        // Enable verbose debug output (optional, for troubleshooting)
        // $mailer->SMTPDebug = SMTP::DEBUG_SERVER;
        
        // Character encoding
        $mailer->CharSet = 'UTF-8';
        
        return $mailer;
    }
    
    private function getSmtpSecure(?string $encryption): string {
        if ($encryption === 'ssl') {
            return PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            return PHPMailer::ENCRYPTION_STARTTLS;
        }
        return PHPMailer::ENCRYPTION_STARTTLS; // Default to TLS
    }

    public function sendVerificationEmail(string $email, string $username, string $token): array {
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $verifyUrl = $baseUrl . '/verify-email.php?token=' . $token;

        $subject = 'Verify Your Email Address';
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .button { display: inline-block; padding: 12px 24px; background-color: #1a73e8; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0; }
                .button:hover { background-color: #1557b0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Hello {$username}!</h2>
                <p>Thank you for registering with Anne Chat. Please verify your email address by clicking the button below:</p>
                <a href='{$verifyUrl}' class='button'>Verify Email Address</a>
                <p>Or copy and paste this link into your browser:</p>
                <p><a href='{$verifyUrl}'>{$verifyUrl}</a></p>
                <p>This link will expire in 24 hours.</p>
                <p>If you didn't create an account, you can safely ignore this email.</p>
            </div>
        </body>
        </html>
        ";

        return $this->sendEmail($email, $username, $subject, $body, true);
    }
}
