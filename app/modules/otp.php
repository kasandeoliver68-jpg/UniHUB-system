<?php

declare(strict_types=1);

function generate_otp(): string
{
    return (string) random_int(100000, 999999);
}

function issue_otp(int $userId, string $email): string
{
    $otp = generate_otp();
    $hash = password_hash($otp, PASSWORD_DEFAULT);
    $expires = (new DateTimeImmutable('+' . OTP_EXPIRY_MINUTES . ' minutes'))->format('Y-m-d H:i:s');

    $stmt = db()->prepare('UPDATE users SET otp_code_hash = ?, otp_expires_at = ? WHERE id = ?');
    $stmt->execute([$hash, $expires, $userId]);

    $subject = 'Your UniHUB Email Verification Code';
    $message = "Your UniHUB verification code is: {$otp}\n\n";
    $message .= "This code will expire in " . OTP_EXPIRY_MINUTES . " minutes.\n\n";
    $message .= "If you did not request this code, please ignore this email.";
    $headers = "From: noreply@unihub.local\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    mail($email, $subject, $message, $headers);

    return $otp;
}