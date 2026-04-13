<?php
declare(strict_types=1);

// Load PHPMailer classes (downloaded into core/phpmailer/).
require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

/**
 * Thin wrapper around PHPMailer.
 *
 * Reads SMTP credentials from the global config variables set in config.php
 * (which loads config.local.php for real credentials on your machine).
 *
 * Every send attempt is also written to app.log so you always have a record.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $body): void
    {
        // Always log — useful to confirm the email was triggered.
        app_log('info', 'Email', [
            'to'      => $to,
            'subject' => $subject,
            'body'    => $body,
        ]);

        // Pull SMTP settings from config.php globals.
        global $smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $smtpFromName, $smtpDevRedirect;

        // Dev redirect: send all emails to one address instead of the real recipient.
        if (!empty($smtpDevRedirect)) {
            $to = $smtpDevRedirect;
        }

        $mail = new PHPMailer(true);   // true = throw exceptions on error

        try {
            // ── Transport ────────────────────────────────────────────────────
            $mail->isSMTP();
            $mail->Host       = $smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->Port       = $smtpPort;
            // ENCRYPTION_STARTTLS = port 587 (Mailtrap default)
            // ENCRYPTION_SMTPS   = port 465 SSL (Zoho, Gmail)
            // Switch to ENCRYPTION_SMTPS if you change port to 465.
            $mail->SMTPSecure = $smtpPort === 465
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;

            // ── Sender / recipient ───────────────────────────────────────────
            $mail->setFrom($smtpFrom, $smtpFromName);
            $mail->addAddress($to);

            // ── Content ──────────────────────────────────────────────────────
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Subject = $subject;
            $mail->Body    = $body;       // plain text

            $mail->send();

            app_log('info', 'Email sent via SMTP', ['to' => $to]);
        } catch (MailerException $e) {
            // Log the error but don't crash the request — the user still gets
            // the "check your email" success message (no user enumeration).
            app_log('error', 'Email failed', [
                'to'    => $to,
                'error' => $mail->ErrorInfo,
            ]);
        }
    }
}
