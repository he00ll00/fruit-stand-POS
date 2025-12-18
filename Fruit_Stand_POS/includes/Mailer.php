<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!function_exists('sendEmail')) {
    function sendEmail($to, $subject, $body, $isHtml = true) {
        $config = require __DIR__ . '/MailConfig.php';
        $fromEmail = $config['from_email'] ?? '';
        $fromName = $config['from_name'] ?? '';
        $host = $config['host'] ?? '';
        $port = $config['port'] ?? 587;
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $enc = $config['encryption'] ?? 'tls';

        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            $autoload = __DIR__ . '/../vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            } else {
                // Try includes/PHPMailer/ (files placed under includes)
                $base0 = __DIR__ . '/PHPMailer/';
                if (file_exists($base0 . 'PHPMailer.php') && file_exists($base0 . 'SMTP.php') && file_exists($base0 . 'Exception.php')) {
                    require_once $base0 . 'Exception.php';
                    require_once $base0 . 'PHPMailer.php';
                    require_once $base0 . 'SMTP.php';
                } else {
                    // Try includes/PHPMailer/src/
                    $base0b = __DIR__ . '/PHPMailer/src/';
                    if (file_exists($base0b . 'PHPMailer.php') && file_exists($base0b . 'SMTP.php') && file_exists($base0b . 'Exception.php')) {
                        require_once $base0b . 'Exception.php';
                        require_once $base0b . 'PHPMailer.php';
                        require_once $base0b . 'SMTP.php';
                    } else {
                        // Try project-root/PHPMailer/
                        $base1 = __DIR__ . '/../PHPMailer/';
                        if (file_exists($base1 . 'PHPMailer.php') && file_exists($base1 . 'SMTP.php') && file_exists($base1 . 'Exception.php')) {
                            require_once $base1 . 'Exception.php';
                            require_once $base1 . 'PHPMailer.php';
                            require_once $base1 . 'SMTP.php';
                        } else {
                            // Try project-root/PHPMailer/src/
                            $base2 = __DIR__ . '/../PHPMailer/src/';
                            if (file_exists($base2 . 'PHPMailer.php') && file_exists($base2 . 'SMTP.php') && file_exists($base2 . 'Exception.php')) {
                                require_once $base2 . 'Exception.php';
                                require_once $base2 . 'PHPMailer.php';
                                require_once $base2 . 'SMTP.php';
                            } else {
                                die('PHPMailer library not found. Place PHPMailer files in includes/PHPMailer/ or /PHPMailer/ (PHPMailer.php, SMTP.php, Exception.php), or install via Composer.');
                            }
                        }
                    }
                }
            }
        }

        try {
            $GLOBALS['POS_MAIL_LAST_ERROR'] = null;
            $mail = new PHPMailer(true);
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->Port = (int)$port;
            $mail->CharSet = 'UTF-8';
            if ($enc === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($enc === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML((bool)$isHtml);
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);

            $ok = $mail->send();
            if (!$ok) {
                $GLOBALS['POS_MAIL_LAST_ERROR'] = $mail->ErrorInfo;
            }
            return $ok;
        } catch (\Throwable $e) {
            $msg = 'Mailer send failed: ' . $e->getMessage();
            if (isset($mail) && property_exists($mail, 'ErrorInfo') && $mail->ErrorInfo) {
                $msg .= ' | ' . $mail->ErrorInfo;
            }
            $GLOBALS['POS_MAIL_LAST_ERROR'] = $msg;
            error_log($msg);
            return false;
        }
    }
}

if (!function_exists('sendPOSMail')) {
    function sendPOSMail($to, $subject, $body, $isHtml = true) {
        return sendEmail($to, $subject, $body, $isHtml);
    }
}

if (!function_exists('getLastPosMailError')) {
    function getLastPosMailError() {
        return isset($GLOBALS['POS_MAIL_LAST_ERROR']) ? $GLOBALS['POS_MAIL_LAST_ERROR'] : null;
    }
}
