<?php
require_once 'config.php';
require_once 'smtp-mail.php';

header('Content-Type: text/plain');

echo "Testing SMTP send to " . NOTIFICATION_EMAIL . "...\n\n";

$result = smtpSend(
    NOTIFICATION_EMAIL,
    'SMTP Test from The Eleanor',
    "If you received this, email delivery is working.\n\nSent: " . date('Y-m-d H:i:s'),
    null
);

echo $result ? "SUCCESS: Email sent!\n" : "FAILED: Check server error log.\n";
