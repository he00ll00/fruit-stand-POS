<?php
require 'includes/Mailer.php';

$sent = sendPOSMail(
    'fruitstandpos@gmail.com',
    'Brevo Test Email',
    'If you received this, Brevo + PHPMailer works!'
);
if ($sent) {
    echo 'EMAIL SENT';
} else {
    $err = function_exists('getLastPosMailError') ? getLastPosMailError() : null;
    echo 'EMAIL FAILED' . ($err ? ("\n" . $err) : '');
}
