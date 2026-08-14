<?php
/**
 * Email / PHPMailer Configuration
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Adjust path if needed
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

// =====================================================
// SMTP SETTINGS – CHANGE THESE
// =====================================================
define('MAIL_HOST',        'smtp.gmail.com');
define('MAIL_PORT',        587);
define('MAIL_USERNAME',    'agwengtonnybrian@gmail.com');
define('MAIL_PASSWORD',    'hbfg hzki xqvs duqk');
define('MAIL_ENCRYPTION',  'tls');                     // tls or ssl
define('MAIL_FROM_EMAIL',  'noreply@enonomarket.com');
define('MAIL_FROM_NAME',   'enono MARKET');

/**
 * Send an email using PHPMailer
 * Returns: ['success' => bool, 'message' => string]
 */
function sendMail(string $toEmail, string $toName, string $subject, string $body, bool $isHTML = true): array
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;

        // Uncomment only while debugging
        // $mail->SMTPDebug = 2;

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        if ($isHTML) {
            $mail->AltBody = strip_tags($body);
        }

        $mail->send();

        return ['success' => true, 'message' => 'Email sent successfully'];

    } catch (Exception $e) {
        // Log the real error (for you) – never show it to the user
        error_log('PHPMailer Error: ' . $mail->ErrorInfo);

        return [
            'success' => false,
            'message' => 'We could not send the verification email at the moment. Please try again later.'
        ];
    }
}