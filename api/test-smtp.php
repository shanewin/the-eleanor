<?php
require_once 'config.php';
require_once 'smtp-mail.php';

header('Content-Type: text/plain');

echo "Testing SMTP...\n";
echo "Host: " . SMTP_HOST . "\n";
echo "Port: " . SMTP_PORT . "\n";
echo "User: " . SMTP_USER . "\n";
echo "To: " . NOTIFICATION_EMAIL . "\n\n";

$result = smtpSend(
    NOTIFICATION_EMAIL,
    'SMTP Test from The Eleanor',
    'If you received this, SMTP is working correctly.',
    null
);

echo $result ? "SUCCESS: Email sent!\n" : "FAILED: Check error log.\n";
