<?php
/**
 * Secure Mail Routing Engine Hook
 * EduManage Enterprise Portal
 * 
 * Provides automated mail routing functions. For testing, it logs the OTP email
 * directly to a persistent local log file in the brain scratch directory.
 * In a live production environment, it integrates with PHPMailer or mail() functions.
 */

function send_otp_email($to_email, $otp_code) {
    $subject = "EduManage Password Reset - OTP Code";
    
    // Create a beautiful, production-ready HTML email template
    $message = "
    <html>
    <head>
        <title>EduManage Password Reset</title>
        <style>
            body { font-family: 'Inter', Arial, sans-serif; background-color: #f4f5f7; color: #333333; margin: 0; padding: 20px; }
            .card { background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 30px; max-width: 500px; margin: 0 auto; border: 1px solid #e2e8f0; }
            .header { text-align: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 20px; }
            .logo { font-size: 24px; font-weight: bold; color: #4f46e5; text-decoration: none; }
            .otp-container { text-align: center; margin: 30px 0; }
            .otp-code { font-size: 32px; font-weight: 800; letter-spacing: 5px; color: #4f46e5; background-color: #e0e7ff; padding: 10px 20px; border-radius: 8px; display: inline-block; }
            .warning { font-size: 12px; color: #ef4444; margin-top: 20px; }
            .footer { text-align: center; font-size: 11px; color: #94a3b8; margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 15px; }
        </style>
    </head>
    <body>
        <div class='card'>
            <div class='header'>
                <a href='#' class='logo'>EduManage Portal</a>
            </div>
            <h3>Password Reset Request</h3>
            <p>We received a request to reset your password. Use the verification code below to proceed with setting a new password.</p>
            <div class='otp-container'>
                <div class='otp-code'>$otp_code</div>
            </div>
            <p><strong>Please Note:</strong> This OTP is strictly time-constrained and will expire in <strong>15 minutes</strong>.</p>
            <p class='warning'>If you did not request this password reset, please ignore this email or contact the IT administration team immediately.</p>
            <div class='footer'>
                &copy; " . date('Y') . " EduManage Enterprise Portal. All Rights Reserved.
            </div>
        </div>
    </body>
    </html>";

    // Write to persistent local log inside scratch/ for inspection and debugging
    $scratch_dir = "C:/Users/mail2/.gemini/antigravity-ide/brain/cb61702a-b716-44b2-b3f0-a4360a525b9e/scratch";
    if (!is_dir($scratch_dir)) {
        mkdir($scratch_dir, 0777, true);
    }
    
    $log_file = $scratch_dir . "/sent_emails.log";
    $timestamp = date("Y-m-d H:i:s");
    
    $log_entry = "==================================================\n";
    $log_entry .= "Timestamp: $timestamp\n";
    $log_entry .= "To: $to_email\n";
    $log_entry .= "Subject: $subject\n";
    $log_entry .= "OTP Code: $otp_code\n";
    $log_entry .= "Message:\n$message\n";
    $log_entry .= "==================================================\n\n";
    
    file_put_contents($log_file, $log_entry, FILE_APPEND);

    // Additionally, we can use the native PHP mail if server configured, but for local sandbox, logging is primary
    // @mail($to_email, $subject, $message, "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n");

    return true;
}
?>
