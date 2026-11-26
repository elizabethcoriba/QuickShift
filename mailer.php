<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

function send_otp_email(string $toGmail, string $code, string $purpose = 'Verification'): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your-email@gmail.com';
        $mail->Password   = 'your-16-char-app-password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('your-email@gmail.com', 'QuickShift Security');
        $mail->addAddress($toGmail);

        $mail->isHTML(true);
        $mail->Subject = "QuickShift OTP - {$purpose}";
        $mail->Body    = "
            <div style='font-family:Arial,sans-serif;padding:16px;border:1px solid #ddd;border-radius:10px;max-width:420px'>
                <h2 style='margin:0 0 8px;color:#071a2e'>QuickShift {$purpose}</h2>
                <p style='color:#444'>Use this 6-digit One-Time Password (OTP). Valid for <b>5 minutes</b>:</p>
                <div style='font-size:28px;font-weight:bold;letter-spacing:6px;padding:12px;background:#f1f4f6;text-align:center;border-radius:8px;color:#10243b'>
                    {$code}
                </div>
                <p style='font-size:12px;color:#777;margin-top:12px'>If you did not request this code, ignore this email.</p>
            </div>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}