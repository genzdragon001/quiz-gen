<?php
// PHPMailer wrapper
// Install PHPMailer: composer require phpmailer/phpmailer
// Or download and place in vendor/ folder

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Autoload if using Composer
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

function sendEmail(string $to, string $subject, string $body): bool {
    // If PHPMailer is not available, log to file instead
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        file_put_contents(
            "$logDir/emails.log",
            date('Y-m-d H:i:s') . " | TO: $to | SUBJECT: $subject\n$body\n---\n",
            FILE_APPEND
        );
        return true; // Don't block submission if email fails
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email failed: " . ($mail->ErrorInfo ?? 'unknown'));
        return false;
    }
}